<?php

/**
 * Aşıroğlu Yöresel Market - Checkout controller'ı
 *
 * GET  /odeme               -> checkout formu (adres, kargo, ödeme, özet)
 * GET  /odeme/ozet          -> kargo seçimine göre toplamlar (JSON; Fetch)
 * POST /odeme               -> siparişi oluştur
 * GET  /siparis/tamamlandi  -> sipariş sonuç sayfası
 *
 * Kararlar:
 * - Misafir ve üye checkout yapabilir (FR-062); bu route'lar AdminMiddleware
 *   dışındadır. POST'un CSRF doğrulaması App::handle() içinde yapılır.
 * - İş mantığı CheckoutService'tedir; burada yalnızca girdi okunur, yanıt
 *   üretilir. Servis kural hataları (stok, kargo vb.) kullanıcıya
 *   gösterilebilir Türkçe mesajlardır.
 * - Boş sepetle veya satın alınamayan kalemle checkout açılmaz; kullanıcı
 *   sepete yönlendirilir (S-07).
 * - Sonuç sayfası sipariş numarasını URL'de taşımaz. Başarılı siparişten sonra
 *   özet oturumda tutulur ve sayfa yalnızca sipariş sahibine gösterilir
 *   (üyede user_id eşleşmeli, misafirde sipariş misafir siparişi olmalıdır).
 *   Bu sayede numara tahminiyle başkasının siparişi görülemez (G-05).
 * - Hata durumunda eski girdi ve hatalar flash'a yazılır; form geri
 *   doldurulur. Şifre veya kart verisi bu akışta yoktur.
 * - Kart ödemesi ve havale bilgisi ödeme modülünde eklenecektir; bu controller
 *   ödeme yöntemini ve sipariş durumunu sonuç sayfasına iletir.
 *
 * Gereksinimler: FR-043, FR-045, FR-046, FR-060, FR-061, FR-062, FR-063,
 * FR-064, FR-074, NFR-003, NFR-004.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Order;
use App\Services\CheckoutService;
use InvalidArgumentException;

final class CheckoutController extends Controller
{
    /** Checkout formunun yolu */
    private const CHECKOUT_PATH = '/odeme';

    /** Checkout özet (JSON) yolu */
    private const SUMMARY_PATH = '/odeme/ozet';

    /** Sonuç sayfasının yolu */
    private const COMPLETE_PATH = '/siparis/tamamlandi';

    /** Sepet sayfasının yolu */
    private const CART_PATH = '/sepet';

    /** Başarılı siparişin özetinin oturumda saklandığı anahtar */
    private const SESSION_LAST_ORDER = '_last_order';

    /** Formdan okunan alanlar (CheckoutService::validate() ile aynı) */
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

    // -------------------------------------------------------------------------
    // Form
    // -------------------------------------------------------------------------

    /**
     * Checkout formu: GET /odeme
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $service = new CheckoutService();
        $quote   = $service->quote();

        // Boş sepetle checkout açılmaz (S-07)
        if ($quote['items'] === []) {
            Session::flash('error', 'Sepetiniz boş. Ödemeye geçmek için önce ürün ekleyin.');

            return $this->redirect(self::CART_PATH);
        }

        // Satın alınamayan kalem varsa önce sepet düzeltilmelidir
        if ($quote['problems'] !== []) {
            Session::flash('error', implode(' ', $quote['problems']));

            return $this->redirect(self::CART_PATH);
        }

        $values = $this->defaultValues();

        // Başarısız denemeden dönülüyorsa kullanıcının girdiği değerler korunur
        $old = Session::getFlash('old');

        if (is_array($old)) {
            foreach (self::INPUT_FIELDS as $field) {
                if (isset($old[$field]) && is_string($old[$field])) {
                    $values[$field] = $old[$field];
                }
            }
        }

        // Kargo yöntemi: önceki seçim, yoksa ilk aktif yöntem
        $selectedId = (int) $values['shipping_method_id'];
        $methodIds  = array_map(static fn (array $m): int => (int) $m['id'], $quote['methods']);

        if (!in_array($selectedId, $methodIds, true)) {
            $selectedId = $methodIds[0] ?? 0;
        }

        $values['shipping_method_id'] = $selectedId > 0 ? (string) $selectedId : '';

        $quote  = $service->quote($selectedId > 0 ? $selectedId : null);
        $errors = Session::getFlash('form_errors');

        return $this->view('checkout/index', [
            'title'           => 'Ödeme',
            'quote'           => $quote,
            'values'          => $values,
            'errors'          => is_array($errors) ? $errors : [],
            'paymentMethods'  => CheckoutService::PAYMENT_METHODS,
            'selectedMethod'  => $selectedId,
            'isMember'        => Auth::check(),
            'formAction'      => self::CHECKOUT_PATH,
            'summaryPath'     => self::SUMMARY_PATH,
            'cartPath'        => self::CART_PATH,
            'loginPath'       => '/giris?' . http_build_query(['next' => self::CHECKOUT_PATH]),
        ]);
    }

    /**
     * Kargo seçimine göre toplamlar (JSON): GET /odeme/ozet?kargo={id}
     *
     * Checkout sayfası kargo yöntemi değişince sayfayı yenilemeden toplamları
     * günceller (FR-043, FR-045, FR-046).
     *
     * @param array<string, string> $params
     */
    public function summary(Request $request, array $params = []): Response
    {
        $validator = Validator::make(
            ['kargo' => $this->scalar($request->query('kargo'))],
            ['kargo' => 'nullable|integer|min:1|max:4294967295'],
            ['kargo' => 'Kargo yöntemi']
        );

        if ($validator->fails()) {
            return $this->json(['success' => false, 'message' => (string) $validator->firstError()], 422);
        }

        $methodId = $validator->validated()['kargo'] ?? null;
        $quote    = (new CheckoutService())->quote($methodId === null ? null : (int) $methodId);

        // Salt okunur uç nokta: eşzamanlı Fetch isteklerini bekletmemek için kilit bırakılır
        Session::close();

        $methods = [];

        foreach ($quote['methods'] as $method) {
            $methods[] = [
                'id'             => (int) $method['id'],
                'fee_text'       => (float) $method['fee'] <= 0.0 ? 'Ücretsiz' : $this->money((float) $method['fee']),
                'remaining_text' => $method['remaining'] === null ? '' : $this->money((float) $method['remaining']),
            ];
        }

        return $this->json([
            'success'          => true,
            'ready'            => (bool) $quote['ready'],
            'count'            => (int) $quote['quantity'],
            'subtotal_text'    => $this->money((float) $quote['subtotal']),
            'discount_text'    => $this->money((float) $quote['discount_total']),
            'shipping_text'    => $quote['selected'] === null
                ? '—'
                : ((float) $quote['shipping_total'] <= 0.0 ? 'Ücretsiz' : $this->money((float) $quote['shipping_total'])),
            'grand_total_text' => $this->money((float) $quote['grand_total']),
            'methods'          => $methods,
        ]);
    }

    // -------------------------------------------------------------------------
    // Sipariş oluşturma
    // -------------------------------------------------------------------------

    /**
     * Siparişi oluşturur: POST /odeme
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $input   = $this->readInput($request);
        $service = new CheckoutService();

        $checked = $service->validate($input);

        if ($checked['errors'] !== []) {
            return $this->backWithErrors($input, $checked['errors']);
        }

        try {
            $result = $service->placeOrder($input);
        } catch (InvalidArgumentException $e) {
            // Servis ve model kuralları kullanıcıya gösterilebilir Türkçe mesajlardır
            return $this->handleRuleError($input, $e->getMessage());
        }

        // Sonuç sayfası yalnızca bu oturumdaki siparişi gösterir
        Session::set(self::SESSION_LAST_ORDER, [
            'order_id'       => (int) $result['order_id'],
            'order_number'   => (string) $result['order_number'],
            'payment_method' => (string) $result['payment_method'],
            'status'         => (string) $result['status'],
        ]);

        return $this->redirect(self::COMPLETE_PATH);
    }

    // -------------------------------------------------------------------------
    // Sonuç
    // -------------------------------------------------------------------------

    /**
     * Sipariş sonuç sayfası: GET /siparis/tamamlandi
     *
     * @param array<string, string> $params
     */
    public function complete(Request $request, array $params = []): Response
    {
        $last = Session::get(self::SESSION_LAST_ORDER);

        if (!is_array($last) || !isset($last['order_id']) || !is_int($last['order_id'])) {
            return $this->redirect('/');
        }

        $orders = new Order();
        $order  = $orders->find($last['order_id']);

        if ($order === null) {
            Session::forget(self::SESSION_LAST_ORDER);

            return $this->redirect('/');
        }

        // Sahiplik: üye siparişi yalnızca sahibine, misafir siparişi misafir oturumuna açıktır
        $orderUserId = $order['user_id'] === null ? null : (int) $order['user_id'];

        if ($orderUserId !== Auth::id()) {
            return $this->redirect('/');
        }

        $paymentMethod = (string) ($last['payment_method'] ?? '');

        return $this->view('checkout/complete', [
            'title'          => 'Siparişiniz Alındı',
            'order'          => $order,
            'items'          => $orders->getItems((int) $order['id']),
            'address'        => $this->decodeSnapshot((string) $order['shipping_address_snapshot']),
            'paymentMethod'  => $paymentMethod,
            'paymentLabel'   => CheckoutService::PAYMENT_METHODS[$paymentMethod] ?? '',
            'isMember'       => Auth::check(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Form varsayılanları: giriş yapmış üyenin bilgileri ön doldurulur.
     *
     * @return array<string, string>
     */
    private function defaultValues(): array
    {
        $values = array_fill_keys(self::INPUT_FIELDS, '');

        $user = Auth::user();

        if ($user !== null) {
            $values['email']      = (string) ($user['email'] ?? '');
            $values['phone']      = (string) ($user['phone'] ?? '');
            $values['first_name'] = (string) ($user['first_name'] ?? '');
            $values['last_name']  = (string) ($user['last_name'] ?? '');
        }

        return $values;
    }

    /**
     * Kural hatasını (stok, kargo, boş sepet vb.) kullanıcıya iletir.
     * Sepet artık geçerli değilse sepete, aksi halde forma döner.
     *
     * @param array<string, string> $input
     */
    private function handleRuleError(array $input, string $message): Response
    {
        $quote = (new CheckoutService())->quote();

        if ($quote['items'] === [] || $quote['problems'] !== []) {
            Session::flash('error', $message);

            return $this->redirect(self::CART_PATH);
        }

        Session::flash('error', $message);
        Session::flash('old', $input);

        return $this->redirect(self::CHECKOUT_PATH);
    }

    /**
     * Hata durumunda eski girdiyi ve hataları flash'a yazıp forma döner.
     *
     * @param array<string, string> $input
     * @param array<string, string> $errors
     */
    private function backWithErrors(array $input, array $errors): Response
    {
        Session::flash('error', 'Lütfen formdaki hataları düzeltin.');
        Session::flash('form_errors', $errors);
        Session::flash('old', $input);

        return $this->redirect(self::CHECKOUT_PATH);
    }

    /**
     * Form alanlarını istekten okur; metin olmayan değerler boş sayılır.
     *
     * @return array<string, string>
     */
    private function readInput(Request $request): array
    {
        $input = [];

        foreach (self::INPUT_FIELDS as $field) {
            $value         = $request->input($field);
            $input[$field] = is_scalar($value) ? trim((string) $value) : '';
        }

        return $input;
    }

    /**
     * Adres anlık kopyasını (JSON) diziye çevirir; bozuksa boş dizi döner.
     *
     * @return array<string, mixed>
     */
    private function decodeSnapshot(string $json): array
    {
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /** Tutarı Türkçe biçimde yazar: 1.250,50 ₺ */
    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' ₺';
    }

    /**
     * Metin olmayan (dizi vb.) değerleri boş sayar; Validator yalnızca
     * skaler değer alır.
     */
    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}