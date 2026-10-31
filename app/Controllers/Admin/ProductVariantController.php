<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli ürün varyantı controller'ı
 *
 * GET  /admin/urunler/{id}/varyantlar                       -> liste
 * GET  /admin/urunler/{id}/varyantlar/olustur               -> yeni varyant formu
 * POST /admin/urunler/{id}/varyantlar                       -> kaydet
 * GET  /admin/urunler/{id}/varyantlar/{vid}/duzenle         -> düzenleme formu
 * POST /admin/urunler/{id}/varyantlar/{vid}/guncelle        -> güncelle
 * POST /admin/urunler/{id}/varyantlar/{vid}/durum           -> aktif/pasif (Fetch veya form)
 *
 * Kararlar:
 * - Erişim denetimi route grubundaki AdminMiddleware ile yapılır; burada
 *   ikinci savunma katmanı olarak yetki yeniden kontrol edilir.
 * - POST isteklerinin CSRF doğrulaması App::handle() içinde yapılır.
 * - Varyant fiziksel silinmez; satıştan kaldırmak için is_active = 0 yapılır
 *   (ProductVariant::delete() kapalıdır). Bu yüzden "sil" route'u yoktur.
 * - stock_quantity yalnızca oluşturmada başlangıç stoğu olarak yazılır;
 *   güncellemede yazılmaz (stok değişikliği stok modülünden yapılır, FR-026).
 * - SKU ürün ve varyantlar arasında benzersizdir; aynı üründe aynı varyant
 *   adı iki kez kullanılamaz.
 * - Her varyant işleminde varyantın URL'deki ürüne ait olduğu doğrulanır.
 * - Girdiler Validator'dan geçer; çıktı kaçırma view'lardadır.
 *
 * Gereksinimler: FR-020, FR-021, FR-022, FR-025, NFR-003, NFR-004.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Middleware\AdminMiddleware;
use App\Models\Product;
use App\Models\ProductVariant;
use InvalidArgumentException;
use PDOException;
use RuntimeException;

final class ProductVariantController extends Controller
{
    /** Ürün listesinin yolu */
    private const PRODUCT_LIST_PATH = '/admin/urunler';

    /** MySQL "yinelenen kayıt" hata kodu (UNIQUE ihlali) */
    private const MYSQL_DUPLICATE_ENTRY = 1062;

    /** Formdan okunan metin alanları */
    private const TEXT_FIELDS = [
        'variant_name',
        'sku',
        'barcode',
        'price',
        'discount_price',
        'stock_quantity',
        'weight',
    ];

    // -------------------------------------------------------------------------
    // Liste
    // -------------------------------------------------------------------------

    /**
     * Ürünün varyant listesi: GET /admin/urunler/{id}/varyantlar
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $product = $this->findProduct($params);

        if ($product === null) {
            return $this->productNotFound();
        }

        $variants   = (new ProductVariant())->getByProduct((int) $product['id']);
        $totalStock = 0;

        foreach ($variants as $variant) {
            $totalStock += (int) $variant['stock_quantity'];
        }

        return $this->view(
            'admin/products/variants/index',
            [
                'title'      => 'Varyantlar - ' . (string) $product['name'],
                'activeMenu' => 'products',
                'product'    => $product,
                'variants'   => $variants,
                'totalStock' => $totalStock,
                'basePath'   => $this->basePath((int) $product['id']),
                'listPath'   => self::PRODUCT_LIST_PATH,
            ],
            'layouts/admin'
        );
    }

    // -------------------------------------------------------------------------
    // Oluşturma
    // -------------------------------------------------------------------------

    /**
     * Yeni varyant formu: GET /admin/urunler/{id}/varyantlar/olustur
     *
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $product = $this->findProduct($params);

        if ($product === null) {
            return $this->productNotFound();
        }

        return $this->showForm($product, null);
    }

    /**
     * Yeni varyantı kaydeder: POST /admin/urunler/{id}/varyantlar
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $product = $this->findProduct($params);

        if ($product === null) {
            return $this->productNotFound();
        }

        return $this->save($request, $product, null);
    }

    // -------------------------------------------------------------------------
    // Düzenleme
    // -------------------------------------------------------------------------

    /**
     * Düzenleme formu: GET /admin/urunler/{id}/varyantlar/{vid}/duzenle
     *
     * @param array<string, string> $params
     */
    public function edit(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $product = $this->findProduct($params);

        if ($product === null) {
            return $this->productNotFound();
        }

        $variant = $this->findVariant($params, (int) $product['id']);

        if ($variant === null) {
            return $this->variantNotFound((int) $product['id']);
        }

        return $this->showForm($product, $variant);
    }

    /**
     * Varyantı günceller: POST /admin/urunler/{id}/varyantlar/{vid}/guncelle
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $product = $this->findProduct($params);

        if ($product === null) {
            return $this->productNotFound();
        }

        $variant = $this->findVariant($params, (int) $product['id']);

        if ($variant === null) {
            return $this->variantNotFound((int) $product['id']);
        }

        return $this->save($request, $product, $variant);
    }

    // -------------------------------------------------------------------------
    // Aktif / pasif
    // -------------------------------------------------------------------------

    /**
     * Varyantı satışa açar veya kapatır: POST /admin/urunler/{id}/varyantlar/{vid}/durum
     *
     * Gövdede "is_active" = 1 veya 0 beklenir. Fetch isteklerine JSON,
     * normal form isteklerine yönlendirme döner.
     *
     * @param array<string, string> $params
     */
    public function toggle(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $asJson  = $request->expectsJson();
        $product = $this->findProduct($params);

        if ($product === null) {
            return $asJson
                ? $this->json(['success' => false, 'message' => 'Ürün bulunamadı.'], 404)
                : $this->productNotFound();
        }

        $productId = (int) $product['id'];
        $variant   = $this->findVariant($params, $productId);

        if ($variant === null) {
            return $asJson
                ? $this->json(['success' => false, 'message' => 'Varyant bulunamadı.'], 404)
                : $this->variantNotFound($productId);
        }

        $value = $request->input('is_active');

        if (!in_array($value, ['0', '1', 0, 1], true)) {
            if ($asJson) {
                return $this->json(['success' => false, 'message' => 'Geçersiz durum değeri.'], 422);
            }

            Session::flash('error', 'Geçersiz durum değeri.');

            return $this->redirect($this->basePath($productId));
        }

        $active = (int) $value === 1;

        // Değişiklik yoksa MySQL 0 satır etkilenmiş bildirir; bu hata değildir.
        (new ProductVariant())->setActive((int) $variant['id'], $active);

        $message = $active ? 'Varyant satışa açıldı.' : 'Varyant satıştan kaldırıldı.';

        if ($asJson) {
            return $this->json(['success' => true, 'message' => $message, 'is_active' => $active]);
        }

        Session::flash('success', $message);

        return $this->redirect($this->basePath($productId));
    }

    // -------------------------------------------------------------------------
    // Ortak kaydetme akışı
    // -------------------------------------------------------------------------

    /**
     * Form girdisini doğrular ve oluşturur ($variant null) veya günceller.
     * Hata varsa eski girdi ve hatalar flash'a yazılır, forma dönülür.
     *
     * @param array<string, mixed>      $product
     * @param array<string, mixed>|null $variant Düzenlenen kayıt; yeni kayıtta null
     */
    private function save(Request $request, array $product, ?array $variant): Response
    {
        $productId = (int) $product['id'];
        $variantId = $variant === null ? null : (int) $variant['id'];
        $input     = $this->readInput($request);

        $validator = Validator::make(
            $input,
            [
                'variant_name'   => 'required|string|max:150',
                'sku'            => 'required|string|max:64',
                'barcode'        => 'nullable|string|max:64',
                'price'          => 'required|decimal|min:0.01|max:9999999999',
                'discount_price' => 'nullable|decimal|min:0.01|max:9999999999',
                'stock_quantity' => 'nullable|integer|min:0|max:10000000',
                'weight'         => 'nullable|decimal|max:99999999',
            ],
            [
                'variant_name'   => 'Varyant adı',
                'sku'            => 'SKU',
                'barcode'        => 'Barkod',
                'price'          => 'Satış fiyatı',
                'discount_price' => 'İndirimli fiyat',
                'stock_quantity' => 'Başlangıç stoğu',
                'weight'         => 'Ağırlık',
            ]
        );

        $errors   = $validator->errors();
        $data     = $validator->validated();
        $variants = new ProductVariant();

        // --- Fiyat kuralı -----------------------------------------------------
        if (
            !isset($errors['price']) && !isset($errors['discount_price'])
            && ($data['discount_price'] ?? null) !== null
            && (float) $data['discount_price'] >= (float) $data['price']
        ) {
            $errors['discount_price'] = 'İndirimli fiyat, satış fiyatından düşük olmalıdır.';
        }

        // --- Ad: aynı üründe benzersiz ----------------------------------------
        $name = trim((string) ($data['variant_name'] ?? ''));

        if (!isset($errors['variant_name']) && $variants->nameExists($productId, $name, $variantId)) {
            $errors['variant_name'] = 'Bu üründe aynı adlı bir varyant zaten var.';
        }

        // --- SKU: ürün ve varyantlar arasında benzersiz -----------------------
        $sku = trim((string) ($data['sku'] ?? ''));

        if (!isset($errors['sku']) && $variants->skuExists($sku, $variantId)) {
            $errors['sku'] = 'Bu SKU başka bir ürün veya varyantta kullanılıyor.';
        }

        if ($errors !== []) {
            return $this->backWithErrors($input, $errors, $productId, $variantId, $request);
        }

        // --- Kaydedilecek satır ----------------------------------------------
        $row = [
            'variant_name'   => $name,
            'sku'            => $sku,
            'barcode'        => $data['barcode'] ?? null,
            'price'          => (string) $data['price'],
            'discount_price' => isset($data['discount_price']) ? (string) $data['discount_price'] : null,
            'weight'         => isset($data['weight']) ? (string) $data['weight'] : null,
            'is_active'      => $this->checkbox($request, 'is_active') ? 1 : 0,
        ];

        try {
            if ($variantId === null) {
                // product_id ve başlangıç stoğu yalnızca oluşturmada yazılır
                $row['product_id']     = $productId;
                $row['stock_quantity'] = (int) ($data['stock_quantity'] ?? 0);

                $variants->create($row);
                Session::flash('success', 'Varyant eklendi.');
            } else {
                // product_id ve stock_quantity bilinçli olarak yazılmaz.
                // Değişiklik yoksa MySQL 0 satır etkilenmiş bildirir; bu hata değildir.
                $variants->update($variantId, $row);
                Session::flash('success', 'Varyant güncellendi.');
            }
        } catch (InvalidArgumentException $e) {
            // Model kuralları (ikinci savunma katmanı)
            return $this->backWithErrors(
                $input,
                ['variant_name' => $e->getMessage()],
                $productId,
                $variantId,
                $request
            );
        } catch (RuntimeException $e) {
            // Eşzamanlı istekte SKU çakışması: UNIQUE ihlali alan hatası olarak döner
            $previous = $e->getPrevious();

            if (
                $previous instanceof PDOException
                && (int) ($previous->errorInfo[1] ?? 0) === self::MYSQL_DUPLICATE_ENTRY
            ) {
                return $this->backWithErrors(
                    $input,
                    ['sku' => 'Bu SKU başka bir ürün veya varyantta kullanılıyor.'],
                    $productId,
                    $variantId,
                    $request
                );
            }

            throw $e;
        }

        return $this->redirect($this->basePath($productId));
    }

    // -------------------------------------------------------------------------
    // Form gösterimi
    // -------------------------------------------------------------------------

    /**
     * Oluşturma/düzenleme formunu hazırlar.
     * Önceki istekte hata olduysa eski girdi ve hatalar flash'tan okunur.
     *
     * @param array<string, mixed>      $product
     * @param array<string, mixed>|null $variant Düzenlenen kayıt; yeni kayıtta null
     */
    private function showForm(array $product, ?array $variant): Response
    {
        $productId = (int) $product['id'];
        $variantId = $variant === null ? null : (int) $variant['id'];

        // Varsayılan değerler (yeni kayıt)
        $values = array_fill_keys(self::TEXT_FIELDS, '');

        $values['stock_quantity'] = '0';
        $values['is_active']      = true;

        if ($variant !== null) {
            foreach (self::TEXT_FIELDS as $field) {
                $values[$field] = (string) ($variant[$field] ?? '');
            }

            $values['is_active'] = (int) $variant['is_active'] === 1;
        }

        // Başarısız denemeden dönülüyorsa kullanıcının girdiği değerler korunur
        $old = Session::getFlash('old');

        if (is_array($old)) {
            foreach (self::TEXT_FIELDS as $field) {
                if (isset($old[$field]) && is_string($old[$field])) {
                    $values[$field] = $old[$field];
                }
            }

            $values['is_active'] = !empty($old['is_active']);

            // Düzenlemede stok salt okunurdur; eski girdi onu ezmemelidir
            if ($variant !== null) {
                $values['stock_quantity'] = (string) $variant['stock_quantity'];
            }
        }

        $errors = Session::getFlash('form_errors');

        return $this->view(
            'admin/products/variants/form',
            [
                'title'      => $variantId === null ? 'Yeni Varyant' : 'Varyantı Düzenle',
                'activeMenu' => 'products',
                'isEdit'     => $variantId !== null,
                'product'    => $product,
                'variantId'  => $variantId,
                'values'     => $values,
                'errors'     => is_array($errors) ? $errors : [],
                'formAction' => $variantId === null
                    ? $this->basePath($productId)
                    : $this->basePath($productId) . '/' . $variantId . '/guncelle',
                'listPath'   => $this->basePath($productId),
            ],
            'layouts/admin'
        );
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Hata durumunda eski girdiyi ve hataları flash'a yazıp forma döner.
     *
     * @param array<string, string> $input
     * @param array<string, string> $errors
     */
    private function backWithErrors(
        array $input,
        array $errors,
        int $productId,
        ?int $variantId,
        Request $request
    ): Response {
        $old              = $input;
        $old['is_active'] = $this->checkbox($request, 'is_active');

        Session::flash('error', 'Lütfen formdaki hataları düzeltin.');
        Session::flash('form_errors', $errors);
        Session::flash('old', $old);

        return $this->redirect(
            $variantId === null
                ? $this->basePath($productId) . '/olustur'
                : $this->basePath($productId) . '/' . $variantId . '/duzenle'
        );
    }

    /**
     * Metin alanlarını istekten okur; metin olmayan değerler boş sayılır.
     *
     * @return array<string, string>
     */
    private function readInput(Request $request): array
    {
        $input = [];

        foreach (self::TEXT_FIELDS as $field) {
            $value         = $request->input($field);
            $input[$field] = is_string($value) ? $value : '';
        }

        return $input;
    }

    /** İşaret kutusu (checkbox) işaretli mi? */
    private function checkbox(Request $request, string $name): bool
    {
        $value = $request->input($name);

        return in_array($value, ['1', 'on', 'true', 1, true], true);
    }

    /**
     * URL'deki ürünü bulur (silinmiş ürünler dahil edilmez).
     *
     * @param array<string, string> $params
     *
     * @return array<string, mixed>|null
     */
    private function findProduct(array $params): ?array
    {
        $id = (int) ($params['id'] ?? 0);

        return $id > 0 ? (new Product())->find($id) : null;
    }

    /**
     * URL'deki varyantı bulur; varyant bu ürüne ait değilse null döner.
     *
     * @param array<string, string> $params
     *
     * @return array<string, mixed>|null
     */
    private function findVariant(array $params, int $productId): ?array
    {
        $variantId = (int) ($params['vid'] ?? 0);

        return $variantId > 0
            ? (new ProductVariant())->findForProduct($variantId, $productId)
            : null;
    }

    /** Ürünün varyant sayfalarının kök yolu. */
    private function basePath(int $productId): string
    {
        return self::PRODUCT_LIST_PATH . '/' . $productId . '/varyantlar';
    }

    /**
     * Savunma katmanı: panele giremeyen kullanıcıyı giriş sayfasına gönderir.
     */
    private function guard(): ?Response
    {
        return Auth::canAccessAdmin() ? null : $this->redirect(AdminMiddleware::LOGIN_PATH);
    }

    /** Ürün bulunamadığında ürün listesine hata mesajıyla döner. */
    private function productNotFound(): Response
    {
        Session::flash('error', 'Ürün bulunamadı.');

        return $this->redirect(self::PRODUCT_LIST_PATH);
    }

    /** Varyant bulunamadığında varyant listesine hata mesajıyla döner. */
    private function variantNotFound(int $productId): Response
    {
        Session::flash('error', 'Varyant bulunamadı.');

        return $this->redirect($this->basePath($productId));
    }
}