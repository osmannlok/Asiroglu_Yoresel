<?php

/**
 * Aşıroğlu Yöresel Market - Checkout servisi
 *
 * Sepetten siparişe geçiş akışını tek yerde toplar: girdi doğrulama, kargo
 * seçenekleri ve ücret hesabı, sipariş oluşturma ve sepeti boşaltma.
 *
 * Kurallar (DECISIONS.md, Sepet / Sipariş / Ödeme-Kargo):
 * - Sepet fiyatı bilgilendirmedir. Burada tutarlar, ürün/varyant güncel
 *   fiyatıyla (Cart::resolveSellable) yeniden hesaplanır; kargo ücreti bu
 *   tutara göre belirlenir. Order::place() kalemleri yine kendisi okur.
 * - Misafir sipariş verebilir (FR-062): e-posta, telefon ve teslimat adresi
 *   zorunludur. Üye siparişinde Auth::id() kullanıcı kimliği olarak yazılır.
 * - Teslimat adresi anlık kopya olarak Order'a verilir. Fatura adresi şimdilik
 *   ayrı alınmaz (null = teslimatla aynı); FR-061 adımlarında ayrı fatura yok.
 * - Başlangıç durumu: kapıda ödeme 'pending', kredi kartı ve havale/EFT
 *   'awaiting_payment' (FR-074). Ödeme kaydı (payments) ve ödeme yöntemi
 *   kalıcılığı ödeme modülünde yazılacaktır; bu servis yöntemi sonuçta döndürür.
 * - Stok sipariş oluşurken düşer (FR-076 sapması, DECISIONS.md'de kayıtlı).
 * - Kupon henüz yok: discount_total = 0.
 * - Sipariş başarıyla yazıldıktan sonra sepet kalemleri tek tek çıkarılır.
 *   Sepet temizleme hatası siparişi bozmaz; loglanır.
 * - Girdi doğrulaması Validator ile yapılır; bu sınıfta $_GET / $_POST /
 *   $_SESSION kullanılmaz. Çıktı kaçırma view'lardadır.
 *
 * Gereksinimler: FR-045, FR-046, FR-060, FR-061, FR-062, FR-063, FR-064,
 * FR-074, FR-084, FR-085, NFR-004.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Validator;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ShippingMethod;
use InvalidArgumentException;
use Throwable;

final class CheckoutService
{
    /** Ödeme yöntemi => görünen ad (payments.payment_method ile uyumlu) */
    public const PAYMENT_METHODS = [
        'credit_card'      => 'Kredi / Banka Kartı',
        'bank_transfer'    => 'Havale / EFT',
        'cash_on_delivery' => 'Kapıda Ödeme',
    ];

    /** Ödeme yöntemi => siparişin başlangıç durumu */
    private const INITIAL_STATUS = [
        'credit_card'      => 'awaiting_payment',
        'bank_transfer'    => 'awaiting_payment',
        'cash_on_delivery' => 'pending',
    ];

    /** Formdan okunan alanlar */
    private const INPUT_FIELDS = [
        'email',
        'phone',
        'first_name',
        'last_name',
        'city',
        'district',
        'neighborhood',
        'postal_code',
        'address_line',
        'shipping_method_id',
        'payment_method',
        'customer_note',
    ];

    private CartService $cartService;

    private Cart $cart;

    private Order $orders;

    private ShippingMethod $shippingMethods;

    public function __construct(
        ?CartService $cartService = null,
        ?Cart $cart = null,
        ?Order $orders = null,
        ?ShippingMethod $shippingMethods = null
    ) {
        $this->cartService     = $cartService ?? new CartService();
        $this->cart            = $cart ?? new Cart();
        $this->orders          = $orders ?? new Order();
        $this->shippingMethods = $shippingMethods ?? new ShippingMethod();
    }

    // -------------------------------------------------------------------------
    // Özet (checkout sayfası)
    // -------------------------------------------------------------------------

    /**
     * Checkout özeti: kalemler (güncel fiyatla), kargo seçenekleri ve toplamlar.
     * $shippingMethodId verilmezse kargo seçilmemiş sayılır (shipping_total = 0).
     *
     * Dönen dizi:
     *   items, quantity, subtotal, discount_total, shipping_total, grand_total,
     *   methods (id, name, code, description, price, fee, free_shipping_limit,
     *   remaining), selected (seçili yöntem satırı veya null), problems
     *   (satın alınamayan kalemler için mesajlar), ready (bool)
     *
     * @return array<string, mixed>
     */
    public function quote(?int $shippingMethodId = null): array
    {
        $items    = $this->pricedItems($this->cartService->items());
        $subtotal = $this->sumItems($items);
        $quantity = 0;

        foreach ($items as $item) {
            $quantity += (int) $item['quantity'];
        }

        $methods  = [];
        $selected = null;

        foreach ($this->shippingMethods->getActive() as $method) {
            $row = [
                'id'                  => (int) $method['id'],
                'name'                => (string) $method['name'],
                'code'                => (string) $method['code'],
                'description'         => (string) ($method['description'] ?? ''),
                'price'               => (float) $method['price'],
                'fee'                 => $this->shippingMethods->calculateFee($method, $subtotal),
                'free_shipping_limit' => $method['free_shipping_limit'] === null
                    ? null
                    : (float) $method['free_shipping_limit'],
                'remaining'           => $this->shippingMethods->remainingForFreeShipping($method, $subtotal),
            ];

            $methods[] = $row;

            if ($shippingMethodId !== null && $row['id'] === $shippingMethodId) {
                $selected = $row;
            }
        }

        $shippingTotal = $selected === null ? 0.0 : (float) $selected['fee'];
        $problems      = $this->problems($items);

        return [
            'items'          => $items,
            'quantity'       => $quantity,
            'subtotal'       => $subtotal,
            'discount_total' => 0.0,
            'shipping_total' => $shippingTotal,
            'grand_total'    => round($subtotal + $shippingTotal, 2),
            'methods'        => $methods,
            'selected'       => $selected,
            'problems'       => $problems,
            'ready'          => $items !== [] && $problems === [],
        ];
    }

    // -------------------------------------------------------------------------
    // Doğrulama
    // -------------------------------------------------------------------------

    /**
     * Checkout formunu doğrular ve temizlenmiş veriyi döndürür.
     * Hata varsa 'errors' dolu, 'data' boştur.
     *
     * 'data' anahtarları: email, phone, shipping_address (dizi),
     * shipping_method_id (int), payment_method, customer_note (string|null).
     *
     * @param array<string, mixed> $input
     *
     * @return array{errors: array<string, string>, data: array<string, mixed>}
     */
    public function validate(array $input): array
    {
        $raw = [];

        foreach (self::INPUT_FIELDS as $field) {
            $value       = $input[$field] ?? '';
            $raw[$field] = is_scalar($value) ? (string) $value : '';
        }

        $validator = Validator::make(
            $raw,
            [
                'email'              => 'required|email|max:191',
                'phone'              => 'required|phone',
                'first_name'         => 'required|string|max:100',
                'last_name'          => 'required|string|max:100',
                'city'               => 'required|string|max:100',
                'district'           => 'required|string|max:100',
                'neighborhood'       => 'nullable|string|max:150',
                'postal_code'        => ['nullable', 'regex:/^\d{5}$/'],
                'address_line'       => 'required|string|max:500',
                'shipping_method_id' => 'required|integer|min:1|max:4294967295',
                'payment_method'     => 'required|in:' . implode(',', array_keys(self::PAYMENT_METHODS)),
                'customer_note'      => 'nullable|string|max:1000',
            ],
            [
                'email'              => 'E-posta',
                'phone'              => 'Telefon',
                'first_name'         => 'Ad',
                'last_name'          => 'Soyad',
                'city'               => 'İl',
                'district'           => 'İlçe',
                'neighborhood'       => 'Mahalle',
                'postal_code'        => 'Posta kodu',
                'address_line'       => 'Adres',
                'shipping_method_id' => 'Kargo yöntemi',
                'payment_method'     => 'Ödeme yöntemi',
                'customer_note'      => 'Sipariş notu',
            ]
        );

        if ($validator->fails()) {
            return ['errors' => $validator->errors(), 'data' => []];
        }

        $data  = $validator->validated();
        $phone = preg_replace('/[\s\-\(\)]/', '', (string) $data['phone']) ?? '';

        return [
            'errors' => [],
            'data'   => [
                'email'              => mb_strtolower((string) $data['email']),
                'phone'              => $phone,
                'shipping_address'   => [
                    'first_name'   => (string) $data['first_name'],
                    'last_name'    => (string) $data['last_name'],
                    'phone'        => $phone,
                    'country'      => 'Türkiye',
                    'city'         => (string) $data['city'],
                    'district'     => (string) $data['district'],
                    'neighborhood' => $data['neighborhood'] ?? null,
                    'postal_code'  => $data['postal_code'] ?? null,
                    'address_line' => (string) $data['address_line'],
                ],
                'shipping_method_id' => (int) $data['shipping_method_id'],
                'payment_method'     => (string) $data['payment_method'],
                'customer_note'      => $data['customer_note'] ?? null,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Sipariş oluşturma
    // -------------------------------------------------------------------------

    /**
     * Sepeti siparişe çevirir ve sepeti boşaltır.
     *
     * Girdi yeniden doğrulanır (controller validate() çağırmış olsa bile).
     * Stok, fiyat ve satılabilirlik kontrolü ve stok düşümü Order::place()
     * içinde tek transaction'da yapılır; hata olursa hiçbir kayıt kalmaz.
     *
     * @param array<string, mixed> $input Ham form girdisi (INPUT_FIELDS anahtarları)
     *
     * @return array{order_id: int, order_number: string, status: string,
     *               payment_method: string, grand_total: float}
     *
     * @throws InvalidArgumentException Girdi, sepet, kargo veya stok kuralı ihlal edilirse
     */
    public function placeOrder(array $input): array
    {
        $checked = $this->validate($input);

        if ($checked['errors'] !== []) {
            throw new InvalidArgumentException((string) reset($checked['errors']));
        }

        $data = $checked['data'];

        $items = $this->cartService->items();

        if ($items === []) {
            throw new InvalidArgumentException('Sepetiniz boş.');
        }

        $items    = $this->pricedItems($items);
        $problems = $this->problems($items);

        if ($problems !== []) {
            throw new InvalidArgumentException($problems[0]);
        }

        $method = $this->shippingMethods->findActiveById((int) $data['shipping_method_id']);

        if ($method === null) {
            throw new InvalidArgumentException('Seçilen kargo yöntemi geçerli değil.');
        }

        $subtotal      = $this->sumItems($items);
        $shippingTotal = $this->shippingMethods->calculateFee($method, $subtotal);

        $orderItems = [];

        foreach ($items as $item) {
            $orderItems[] = [
                'product_id' => (int) $item['product_id'],
                'variant_id' => $item['variant_id'] === null ? null : (int) $item['variant_id'],
                'quantity'   => (int) $item['quantity'],
            ];
        }

        $paymentMethod = (string) $data['payment_method'];

        $orderId = $this->orders->place(
            ['email' => (string) $data['email'], 'phone' => (string) $data['phone']],
            $data['shipping_address'],
            null,
            $orderItems,
            $shippingTotal,
            Auth::id(),
            $data['customer_note'],
            self::INITIAL_STATUS[$paymentMethod]
        );

        $this->clearCart($items);

        $order = $this->orders->find($orderId);

        return [
            'order_id'       => $orderId,
            'order_number'   => $order === null ? '' : (string) $order['order_number'],
            'status'         => self::INITIAL_STATUS[$paymentMethod],
            'payment_method' => $paymentMethod,
            'grand_total'    => $order === null ? round($subtotal + $shippingTotal, 2) : (float) $order['grand_total'],
        ];
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Kalemlerin birim ve satır fiyatını güncel fiyatla yeniler. Satın
     * alınamayan kalemde (satıştan kalkmış) mevcut fiyat korunur; 'available'
     * zaten false'tur.
     *
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function pricedItems(array $items): array
    {
        foreach ($items as $index => $item) {
            try {
                $info = $this->cart->resolveSellable(
                    (int) $item['product_id'],
                    $item['variant_id'] === null ? null : (int) $item['variant_id']
                );
            } catch (InvalidArgumentException $e) {
                continue;
            }

            $unitPrice = round($info['price'], 2);

            $items[$index]['unit_price']  = $unitPrice;
            $items[$index]['total_price'] = round($unitPrice * (int) $item['quantity'], 2);
        }

        return array_values($items);
    }

    /**
     * Kalem toplamlarının toplamı (KDV dahil, TRY).
     *
     * @param list<array<string, mixed>> $items
     */
    private function sumItems(array $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $total += (float) $item['total_price'];
        }

        return round($total, 2);
    }

    /**
     * Satın alınamayan kalemler için kullanıcıya gösterilebilir mesajlar.
     *
     * @param list<array<string, mixed>> $items
     *
     * @return list<string>
     */
    private function problems(array $items): array
    {
        $problems = [];

        foreach ($items as $item) {
            if (!empty($item['available'])) {
                continue;
            }

            $label = (string) $item['name'];

            if ((string) ($item['variant_name'] ?? '') !== '') {
                $label .= ' (' . (string) $item['variant_name'] . ')';
            }

            $stock = (int) $item['stock'];

            $problems[] = $stock > 0
                ? $label . ': stokta yalnızca ' . $stock . ' adet var.'
                : $label . ': şu an satın alınamıyor.';
        }

        return $problems;
    }

    /**
     * Sipariş yazıldıktan sonra sepet kalemlerini çıkarır (misafir ve üye).
     * Hata siparişi geri almaz; loglanır.
     *
     * @param list<array<string, mixed>> $items
     */
    private function clearCart(array $items): void
    {
        try {
            foreach ($items as $item) {
                $this->cartService->remove((string) $item['key']);
            }
        } catch (Throwable $e) {
            error_log(sprintf(
                '[%s] CheckoutService: sipariş sonrası sepet temizlenemedi: %s',
                date('Y-m-d H:i:s'),
                $e->getMessage()
            ));
        }
    }
}