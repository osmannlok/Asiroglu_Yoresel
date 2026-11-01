<?php

/**
 * Aşıroğlu Yöresel Market - Sepet controller'ı
 *
 * GET  /sepet           -> sepet sayfası
 * GET  /sepet/ozet      -> sepet özeti (JSON; menü rozeti için)
 * POST /sepet/ekle      -> sepete ekle (JSON)
 * POST /sepet/guncelle  -> adet ayarla; 0 kalemi siler (JSON)
 * POST /sepet/sil       -> kalemi çıkar (JSON)
 *
 * Kararlar:
 * - Misafir ve üye ayrımı CartService içindedir; controller yalnızca girdiyi
 *   doğrular ve yanıtı üretir.
 * - POST isteklerinin CSRF doğrulaması App::handle() içinde yapılır
 *   (Fetch için X-CSRF-TOKEN başlığı).
 * - Servis kural hataları (stok, varyant vb.) kullanıcıya gösterilebilir
 *   Türkçe mesajlardır ve 422 ile döner.
 * - Her başarılı yanıt güncel özeti içerir; arayüz sayfayı yenilemeden günceller.
 * - Girdiler Validator'dan geçer; çıktı kaçırma view'lardadır.
 *
 * Gereksinimler: FR-040, FR-042, FR-043, FR-044, FR-045, NFR-003, NFR-004.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\CartService;
use InvalidArgumentException;

final class CartController extends Controller
{
    /** Kalem anahtarı: misafirde p5 / p5v3, üyede yalnızca rakam */
    private const KEY_PATTERN = '/^(p[1-9]\d{0,9}(v[1-9]\d{0,9})?|[1-9]\d{0,9})$/';

    // -------------------------------------------------------------------------
    // Sayfa
    // -------------------------------------------------------------------------

    /**
     * Sepet sayfası: GET /sepet
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $summary = (new CartService())->summary();

        return $this->view('cart/index', [
            'title'    => 'Sepetim',
            'items'    => $summary['items'],
            'subtotal' => $summary['subtotal'],
            'quantity' => $summary['quantity'],
        ]);
    }

    /**
     * Sepet özeti (JSON): GET /sepet/ozet
     *
     * @param array<string, string> $params
     */
    public function summary(Request $request, array $params = []): Response
    {
        // Salt okunur uç nokta: eşzamanlı Fetch isteklerini bekletmemek için kilit bırakılır
        $service = new CartService();
        $data    = $this->summaryPayload($service);

        Session::close();

        return $this->json(['success' => true] + $data);
    }

    // -------------------------------------------------------------------------
    // İşlemler (JSON)
    // -------------------------------------------------------------------------

    /**
     * Sepete ekler: POST /sepet/ekle
     * Gövde: product_id, variant_id (isteğe bağlı), quantity (varsayılan 1)
     *
     * @param array<string, string> $params
     */
    public function add(Request $request, array $params = []): Response
    {
        $validator = Validator::make(
            [
                'product_id' => $this->scalar($request->input('product_id')),
                'variant_id' => $this->scalar($request->input('variant_id')),
                'quantity'   => $this->scalar($request->input('quantity', '1')),
            ],
            [
                'product_id' => 'required|integer|min:1|max:4294967295',
                'variant_id' => 'nullable|integer|min:1|max:4294967295',
                'quantity'   => 'required|integer|min:1|max:1000',
            ],
            [
                'product_id' => 'Ürün',
                'variant_id' => 'Seçenek',
                'quantity'   => 'Adet',
            ]
        );

        if ($validator->fails()) {
            return $this->fail((string) $validator->firstError());
        }

        $data      = $validator->validated();
        $variantId = ($data['variant_id'] ?? null) === null ? null : (int) $data['variant_id'];
        $service   = new CartService();

        try {
            $service->add((int) $data['product_id'], $variantId, (int) $data['quantity']);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        }

        return $this->ok($service, 'Ürün sepete eklendi.');
    }

    /**
     * Adedi ayarlar: POST /sepet/guncelle
     * Gövde: key, quantity (0 kalemi siler)
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        $validator = Validator::make(
            [
                'key'      => $this->scalar($request->input('key')),
                'quantity' => $this->scalar($request->input('quantity')),
            ],
            [
                'key'      => 'required|string|max:25|regex:' . self::KEY_PATTERN,
                'quantity' => 'required|integer|min:0|max:1000',
            ],
            [
                'key'      => 'Sepet kalemi',
                'quantity' => 'Adet',
            ]
        );

        if ($validator->fails()) {
            return $this->fail((string) $validator->firstError());
        }

        $data    = $validator->validated();
        $service = new CartService();

        try {
            $found = $service->setQuantity((string) $data['key'], (int) $data['quantity']);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        }

        if (!$found) {
            return $this->fail('Sepet kalemi bulunamadı.', 404);
        }

        return $this->ok($service, 'Sepet güncellendi.');
    }

    /**
     * Kalemi çıkarır: POST /sepet/sil
     * Gövde: key
     *
     * @param array<string, string> $params
     */
    public function remove(Request $request, array $params = []): Response
    {
        $validator = Validator::make(
            ['key' => $this->scalar($request->input('key'))],
            ['key' => 'required|string|max:25|regex:' . self::KEY_PATTERN],
            ['key' => 'Sepet kalemi']
        );

        if ($validator->fails()) {
            return $this->fail((string) $validator->firstError());
        }

        $service = new CartService();

        if (!$service->remove((string) $validator->validated()['key'])) {
            return $this->fail('Sepet kalemi bulunamadı.', 404);
        }

        return $this->ok($service, 'Ürün sepetten çıkarıldı.');
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Başarılı yanıt: mesaj ve güncel özet.
     */
    private function ok(CartService $service, string $message): Response
    {
        return $this->json(
            ['success' => true, 'message' => $message] + $this->summaryPayload($service)
        );
    }

    /**
     * Hata yanıtı (kullanıcıya gösterilebilir mesaj).
     */
    private function fail(string $message, int $status = 422): Response
    {
        return $this->json(['success' => false, 'message' => $message], $status);
    }

    /**
     * Özet verisi: adet, ara toplam ve kalemler (arayüzü yeniden çizmek için).
     *
     * @return array<string, mixed>
     */
    private function summaryPayload(CartService $service): array
    {
        $summary = $service->summary();
        $items   = [];

        foreach ($summary['items'] as $item) {
            $items[] = [
                'key'              => (string) $item['key'],
                'quantity'         => (int) $item['quantity'],
                'stock'            => (int) $item['stock'],
                'available'        => (bool) $item['available'],
                'unit_price_text'  => $this->money((float) $item['unit_price']),
                'total_price_text' => $this->money((float) $item['total_price']),
            ];
        }

        return [
            'count'         => $summary['quantity'],
            'subtotal'      => $summary['subtotal'],
            'subtotal_text' => $this->money($summary['subtotal']),
            'items'         => $items,
        ];
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
        return is_scalar($value) ? (string) $value : '';
    }
}