<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli ürün controller'ı
 *
 * GET  /admin/urunler                  -> liste (arama, kategori/durum filtresi, sayfalama)
 * GET  /admin/urunler/olustur          -> yeni ürün formu
 * POST /admin/urunler                  -> kaydet
 * GET  /admin/urunler/{id}/duzenle     -> düzenleme formu
 * POST /admin/urunler/{id}/guncelle    -> güncelle
 * POST /admin/urunler/{id}/sil         -> sil (soft delete)
 *
 * Kararlar:
 * - Erişim denetimi route grubundaki AdminMiddleware ile yapılır; burada
 *   ikinci savunma katmanı olarak yetki yeniden kontrol edilir.
 * - POST isteklerinin CSRF doğrulaması App::handle() içinde yapılır.
 * - Slug boşsa addan üretilir ve benzersizleştirilir; elle girilen slug
 *   çakışırsa hata verilir. SKU ürün ve varyantlar arasında benzersizdir.
 * - stock_quantity yalnızca oluşturmada başlangıç stoğu olarak yazılır;
 *   güncellemede yazılmaz (stok değişikliği stok modülünden yapılır, FR-026).
 *   Varyantlı ürünlerin stoğu varyant düzeyindedir.
 * - İndirimli fiyat, satış fiyatından düşük olmalıdır. İndirim tarihleri
 *   yalnızca indirimli fiyat varsa saklanır.
 * - Görsel yükleme ve varyant yönetimi bu controller'da yoktur (sonraki adımlar).
 * - Girdiler Validator'dan geçer; çıktı kaçırma view'lardadır.
 *
 * Gereksinimler: FR-010, FR-011, FR-015, FR-033, FR-090, NFR-003, NFR-004.
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
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use InvalidArgumentException;
use PDOException;
use RuntimeException;

final class ProductController extends Controller
{
    /** Liste sayfasının yolu */
    private const LIST_PATH = '/admin/urunler';

    /** Listede sayfa başına kayıt */
    private const PER_PAGE = 20;

    /** Arama metninin en fazla uzunluğu */
    private const MAX_SEARCH_LENGTH = 100;

    /** MySQL "yinelenen kayıt" hata kodu (UNIQUE ihlali) */
    private const MYSQL_DUPLICATE_ENTRY = 1062;

    /** Slug uzunluğu (products.slug VARCHAR(255)) */
    private const MAX_SLUG_LENGTH = 255;

    /** Geçerli birim türleri (products.unit_type ENUM) */
    private const UNIT_TYPES = ['adet', 'kg', 'gram', 'paket', 'litre'];

    /** Slug üretiminde Türkçe karakter karşılıkları */
    private const TURKISH_MAP = [
        'ç' => 'c', 'Ç' => 'c',
        'ğ' => 'g', 'Ğ' => 'g',
        'ı' => 'i', 'İ' => 'i',
        'ö' => 'o', 'Ö' => 'o',
        'ş' => 's', 'Ş' => 's',
        'ü' => 'u', 'Ü' => 'u',
    ];

    /** Formdan okunan metin alanları */
    private const TEXT_FIELDS = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'short_description',
        'description',
        'price',
        'discount_price',
        'discount_start_date',
        'discount_end_date',
        'vat_rate',
        'stock_quantity',
        'critical_stock_quantity',
        'unit_type',
        'weight',
        'origin',
        'producer',
        'storage_conditions',
        'shelf_life',
        'allergens',
        'ingredients',
        'meta_title',
        'meta_description',
    ];

    /** Tarih (Y-m-d) olarak gösterilen alanlar */
    private const DATE_FIELDS = ['discount_start_date', 'discount_end_date'];

    // -------------------------------------------------------------------------
    // Liste
    // -------------------------------------------------------------------------

    /**
     * Ürün listesi: GET /admin/urunler
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $page   = max(1, (int) $request->query('page', 1));
        $search = $request->query('q', '');
        $search = is_string($search) ? mb_substr(trim($search), 0, self::MAX_SEARCH_LENGTH) : '';

        $categoryId = (int) $request->query('kategori', 0);
        $categoryId = $categoryId > 0 ? $categoryId : null;

        $status = $request->query('durum', '');
        $status = is_string($status) && in_array($status, ['active', 'passive'], true) ? $status : null;

        $result = (new Product())->paginate(
            $page,
            self::PER_PAGE,
            $search === '' ? null : $search,
            $categoryId,
            $status
        );

        return $this->view(
            'admin/products/index',
            [
                'title'      => 'Ürünler',
                'activeMenu' => 'products',
                'result'     => $result,
                'search'     => $search,
                'categoryId' => $categoryId,
                'status'     => $status ?? '',
                'categories' => (new Category())->all('name', 'ASC'),
            ],
            'layouts/admin'
        );
    }

    // -------------------------------------------------------------------------
    // Oluşturma
    // -------------------------------------------------------------------------

    /**
     * Yeni ürün formu: GET /admin/urunler/olustur
     *
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        return $this->showForm(null, null);
    }

    /**
     * Yeni ürünü kaydeder: POST /admin/urunler
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        return $this->save($request, null);
    }

    // -------------------------------------------------------------------------
    // Düzenleme
    // -------------------------------------------------------------------------

    /**
     * Düzenleme formu: GET /admin/urunler/{id}/duzenle
     *
     * @param array<string, string> $params
     */
    public function edit(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $id      = (int) ($params['id'] ?? 0);
        $product = $id > 0 ? (new Product())->find($id) : null;

        if ($product === null) {
            return $this->notFound();
        }

        return $this->showForm($id, $product);
    }

    /**
     * Ürünü günceller: POST /admin/urunler/{id}/guncelle
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0 || (new Product())->find($id) === null) {
            return $this->notFound();
        }

        return $this->save($request, $id);
    }

    // -------------------------------------------------------------------------
    // Silme
    // -------------------------------------------------------------------------

    /**
     * Ürünü siler (soft delete): POST /admin/urunler/{id}/sil
     *
     * Sipariş kalemleri ürün adı ve fiyatı anlık kopya olarak sakladığı için
     * sipariş geçmişi etkilenmez.
     *
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $id       = (int) ($params['id'] ?? 0);
        $products = new Product();

        if ($id <= 0 || $products->find($id) === null) {
            return $this->notFound();
        }

        $products->delete($id);

        Session::flash('success', 'Ürün silindi.');

        return $this->redirect(self::LIST_PATH);
    }

    // -------------------------------------------------------------------------
    // Ortak kaydetme akışı
    // -------------------------------------------------------------------------

    /**
     * Form girdisini doğrular ve oluşturur ($id null) veya günceller.
     * Hata varsa eski girdi ve hatalar flash'a yazılır, forma dönülür.
     */
    private function save(Request $request, ?int $id): Response
    {
        $input = $this->readInput($request);

        $validator = Validator::make(
            $input,
            [
                'category_id'             => 'required|integer|min:1',
                'brand_id'                => 'nullable|integer|min:1',
                'name'                    => 'required|string|max:255',
                'slug'                    => 'nullable|string|max:255',
                'sku'                     => 'required|string|max:64',
                'barcode'                 => 'nullable|string|max:64',
                'short_description'       => 'nullable|string|max:500',
                'description'             => 'nullable|string|max:20000',
                'price'                   => 'required|decimal|min:0.01|max:9999999999',
                'discount_price'          => 'nullable|decimal|min:0.01|max:9999999999',
                'discount_start_date'     => 'nullable|date',
                'discount_end_date'       => 'nullable|date',
                'vat_rate'                => 'required|decimal|min:0|max:100',
                'stock_quantity'          => 'nullable|integer|min:0|max:10000000',
                'critical_stock_quantity' => 'nullable|integer|min:0|max:10000000',
                'unit_type'               => 'required|in:' . implode(',', self::UNIT_TYPES),
                'weight'                  => 'nullable|decimal|max:99999999',
                'origin'                  => 'nullable|string|max:255',
                'producer'                => 'nullable|string|max:255',
                'storage_conditions'      => 'nullable|string|max:500',
                'shelf_life'              => 'nullable|string|max:150',
                'allergens'               => 'nullable|string|max:5000',
                'ingredients'             => 'nullable|string|max:5000',
                'meta_title'              => 'nullable|string|max:255',
                'meta_description'        => 'nullable|string|max:500',
            ],
            [
                'category_id'             => 'Kategori',
                'brand_id'                => 'Marka',
                'name'                    => 'Ürün adı',
                'slug'                    => 'Slug',
                'sku'                     => 'SKU',
                'barcode'                 => 'Barkod',
                'short_description'       => 'Kısa açıklama',
                'description'             => 'Açıklama',
                'price'                   => 'Satış fiyatı',
                'discount_price'          => 'İndirimli fiyat',
                'discount_start_date'     => 'İndirim başlangıcı',
                'discount_end_date'       => 'İndirim bitişi',
                'vat_rate'                => 'KDV oranı',
                'stock_quantity'          => 'Stok adedi',
                'critical_stock_quantity' => 'Kritik stok eşiği',
                'unit_type'               => 'Birim',
                'weight'                  => 'Ağırlık',
                'origin'                  => 'Menşe',
                'producer'                => 'Üretici',
                'storage_conditions'      => 'Saklama koşulları',
                'shelf_life'              => 'Raf ömrü',
                'allergens'               => 'Alerjen bilgisi',
                'ingredients'             => 'İçindekiler',
                'meta_title'              => 'SEO başlığı',
                'meta_description'        => 'SEO açıklaması',
            ]
        );

        $errors   = $validator->errors();
        $data     = $validator->validated();
        $products = new Product();

        // --- Kategori ve marka: var olmalı -----------------------------------
        $categoryId = null;

        if (!isset($errors['category_id'])) {
            $categoryId = (int) $data['category_id'];

            if ((new Category())->find($categoryId) === null) {
                $errors['category_id'] = 'Seçilen kategori bulunamadı.';
            }
        }

        $brandId = null;

        if (!isset($errors['brand_id']) && ($data['brand_id'] ?? null) !== null) {
            $brandId = (int) $data['brand_id'];

            if ((new Brand())->find($brandId) === null) {
                $errors['brand_id'] = 'Seçilen marka bulunamadı.';
            }
        }

        // --- Fiyat kuralları --------------------------------------------------
        if (
            !isset($errors['price']) && !isset($errors['discount_price'])
            && ($data['discount_price'] ?? null) !== null
            && (float) $data['discount_price'] >= (float) $data['price']
        ) {
            $errors['discount_price'] = 'İndirimli fiyat, satış fiyatından düşük olmalıdır.';
        }

        if (
            !isset($errors['discount_start_date']) && !isset($errors['discount_end_date'])
            && ($data['discount_start_date'] ?? null) !== null
            && ($data['discount_end_date'] ?? null) !== null
            && strcmp((string) $data['discount_end_date'], (string) $data['discount_start_date']) < 0
        ) {
            $errors['discount_end_date'] = 'İndirim bitiş tarihi, başlangıçtan önce olamaz.';
        }

        // --- SKU: ürün ve varyantlar arasında benzersiz -----------------------
        $sku = trim((string) ($data['sku'] ?? ''));

        if (!isset($errors['sku']) && $products->skuExists($sku, $id)) {
            $errors['sku'] = 'Bu SKU başka bir ürün veya varyantta kullanılıyor.';
        }

        // --- Slug: elle girilmişse benzersiz olmalı, boşsa üretilir -----------
        $slug = '';

        if (!isset($errors['slug']) && !isset($errors['name'])) {
            $rawSlug = trim($input['slug']);

            if ($rawSlug !== '') {
                $slug = $this->slugify($rawSlug);

                if ($slug === '') {
                    $errors['slug'] = 'Slug en az bir harf veya rakam içermelidir.';
                } elseif ($products->slugExists($slug, $id)) {
                    $errors['slug'] = 'Bu slug başka bir üründe kullanılıyor.';
                }
            } else {
                $base = $this->slugify((string) ($data['name'] ?? ''));
                $slug = $this->uniqueSlug($products, $base === '' ? 'urun' : $base, $id);
            }
        }

        if ($errors !== []) {
            return $this->backWithErrors($input, $errors, $id, $request);
        }

        // --- Kaydedilecek satır ----------------------------------------------
        $discountPrice = $data['discount_price'] ?? null;
        $hasDiscount   = $discountPrice !== null;

        $row = [
            'category_id'             => $categoryId,
            'brand_id'                => $brandId,
            'name'                    => (string) $data['name'],
            'slug'                    => $slug,
            'sku'                     => $sku,
            'barcode'                 => $data['barcode'] ?? null,
            'short_description'       => $data['short_description'] ?? null,
            'description'             => $data['description'] ?? null,
            'price'                   => (string) $data['price'],
            'discount_price'          => $hasDiscount ? (string) $discountPrice : null,
            'discount_start_date'     => $hasDiscount ? $this->toDateTime($data['discount_start_date'] ?? null, false) : null,
            'discount_end_date'       => $hasDiscount ? $this->toDateTime($data['discount_end_date'] ?? null, true) : null,
            'vat_rate'                => (string) $data['vat_rate'],
            'critical_stock_quantity' => (int) ($data['critical_stock_quantity'] ?? 5),
            'unit_type'               => (string) $data['unit_type'],
            'weight'                  => isset($data['weight']) ? (string) $data['weight'] : null,
            'origin'                  => $data['origin'] ?? null,
            'producer'                => $data['producer'] ?? null,
            'storage_conditions'      => $data['storage_conditions'] ?? null,
            'shelf_life'              => $data['shelf_life'] ?? null,
            'allergens'               => $data['allergens'] ?? null,
            'ingredients'             => $data['ingredients'] ?? null,
            'meta_title'              => $data['meta_title'] ?? null,
            'meta_description'        => $data['meta_description'] ?? null,
            'is_active'               => $this->checkbox($request, 'is_active') ? 1 : 0,
            'is_featured'             => $this->checkbox($request, 'is_featured') ? 1 : 0,
            'is_new'                  => $this->checkbox($request, 'is_new') ? 1 : 0,
        ];

        try {
            if ($id === null) {
                // Başlangıç stoğu yalnızca oluşturmada yazılır
                $row['stock_quantity'] = (int) ($data['stock_quantity'] ?? 0);

                $products->create($row);
                Session::flash('success', 'Ürün eklendi.');
            } else {
                // stock_quantity bilinçli olarak yazılmaz (stok modülünden yönetilir).
                // Değişiklik yoksa MySQL 0 satır etkilenmiş bildirir; bu hata değildir.
                $products->update($id, $row);
                Session::flash('success', 'Ürün güncellendi.');
            }
        } catch (InvalidArgumentException $e) {
            // Model fiyat kuralları (ikinci savunma katmanı)
            return $this->backWithErrors($input, ['price' => $e->getMessage()], $id, $request);
        } catch (RuntimeException $e) {
            // Eşzamanlı istekte slug/SKU çakışması: UNIQUE ihlali alan hatası olarak döner
            $previous = $e->getPrevious();

            if (
                $previous instanceof PDOException
                && (int) ($previous->errorInfo[1] ?? 0) === self::MYSQL_DUPLICATE_ENTRY
            ) {
                $message = (string) ($previous->errorInfo[2] ?? '');

                $field = str_contains($message, 'sku')
                    ? ['sku' => 'Bu SKU başka bir ürün veya varyantta kullanılıyor.']
                    : ['slug' => 'Bu slug başka bir üründe kullanılıyor.'];

                return $this->backWithErrors($input, $field, $id, $request);
            }

            throw $e;
        }

        return $this->redirect(self::LIST_PATH);
    }

    // -------------------------------------------------------------------------
    // Form gösterimi
    // -------------------------------------------------------------------------

    /**
     * Oluşturma/düzenleme formunu hazırlar.
     * Önceki istekte hata olduysa eski girdi ve hatalar flash'tan okunur.
     *
     * @param array<string, mixed>|null $product Düzenlenen kayıt; yeni kayıtta null
     */
    private function showForm(?int $id, ?array $product): Response
    {
        // Varsayılan değerler (yeni kayıt)
        $values = array_fill_keys(self::TEXT_FIELDS, '');

        $values['vat_rate']                = '10';
        $values['stock_quantity']          = '0';
        $values['critical_stock_quantity'] = '5';
        $values['unit_type']               = 'adet';
        $values['is_active']               = true;
        $values['is_featured']             = false;
        $values['is_new']                  = false;

        if ($product !== null) {
            foreach (self::TEXT_FIELDS as $field) {
                $values[$field] = (string) ($product[$field] ?? '');
            }

            // Tarih alanları formda yalnızca gün olarak gösterilir (Y-m-d)
            foreach (self::DATE_FIELDS as $field) {
                $values[$field] = substr($values[$field], 0, 10);
            }

            $values['is_active']   = (int) $product['is_active'] === 1;
            $values['is_featured'] = (int) $product['is_featured'] === 1;
            $values['is_new']      = (int) $product['is_new'] === 1;
        }

        // Başarısız denemeden dönülüyorsa kullanıcının girdiği değerler korunur
        $old = Session::getFlash('old');

        if (is_array($old)) {
            foreach (self::TEXT_FIELDS as $field) {
                if (isset($old[$field]) && is_string($old[$field])) {
                    $values[$field] = $old[$field];
                }
            }
            $values['is_active']   = !empty($old['is_active']);
            $values['is_featured'] = !empty($old['is_featured']);
            $values['is_new']      = !empty($old['is_new']);
        }

        $errors = Session::getFlash('form_errors');

        return $this->view(
            'admin/products/form',
            [
                'title'       => $id === null ? 'Yeni Ürün' : 'Ürünü Düzenle',
                'activeMenu'  => 'products',
                'isEdit'      => $id !== null,
                'productId'   => $id,
                'hasVariants' => $id !== null && (new Product())->hasVariants($id),
                'values'      => $values,
                'errors'      => is_array($errors) ? $errors : [],
                'categories'  => (new Category())->all('name', 'ASC'),
                'brands'      => (new Brand())->all('name', 'ASC'),
                'unitTypes'   => self::UNIT_TYPES,
                'formAction'  => $id === null
                    ? self::LIST_PATH
                    : self::LIST_PATH . '/' . $id . '/guncelle',
                'listPath'    => self::LIST_PATH,
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
    private function backWithErrors(array $input, array $errors, ?int $id, Request $request): Response
    {
        $old                = $input;
        $old['is_active']   = $this->checkbox($request, 'is_active');
        $old['is_featured'] = $this->checkbox($request, 'is_featured');
        $old['is_new']      = $this->checkbox($request, 'is_new');

        Session::flash('error', 'Lütfen formdaki hataları düzeltin.');
        Session::flash('form_errors', $errors);
        Session::flash('old', $old);

        return $this->redirect(
            $id === null
                ? self::LIST_PATH . '/olustur'
                : self::LIST_PATH . '/' . $id . '/duzenle'
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
     * "Y-m-d" tarihini veritabanı DATETIME değerine çevirir.
     * Başlangıç gün başı (00:00:00), bitiş gün sonu (23:59:59) olur.
     * Boş değer null döner.
     */
    private function toDateTime(mixed $date, bool $endOfDay): ?string
    {
        if (!is_string($date) || $date === '') {
            return null;
        }

        // Validator 'date' kuralı "Y-m-d" veya "Y-m-d H:i:s" kabul eder; yalnızca gün alınır
        return substr($date, 0, 10) . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
    }

    /**
     * Metni URL parçasına çevirir: Türkçe karakterler sadeleştirilir,
     * harf/rakam dışındakiler tireye dönüşür. Sonuç en fazla 255 karakterdir.
     */
    private function slugify(string $text): string
    {
        $text = mb_strtolower(strtr($text, self::TURKISH_MAP), 'UTF-8');
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

        return trim(mb_substr($text, 0, self::MAX_SLUG_LENGTH), '-');
    }

    /**
     * Çakışan slug'a -2, -3 ... ekleyerek benzersiz bir değer üretir.
     */
    private function uniqueSlug(Product $products, string $base, ?int $exceptId): string
    {
        $slug = $base;

        for ($i = 2; $products->slugExists($slug, $exceptId); $i++) {
            if ($i > 1000) {
                // Aşırı çakışma: rastgele sonek ile kesin çözüm
                return mb_substr($base, 0, 240) . '-' . bin2hex(random_bytes(4));
            }

            $suffix = '-' . $i;
            $slug   = mb_substr($base, 0, self::MAX_SLUG_LENGTH - strlen($suffix)) . $suffix;
        }

        return $slug;
    }

    /**
     * Savunma katmanı: panele giremeyen kullanıcıyı giriş sayfasına gönderir.
     */
    private function guard(): ?Response
    {
        return Auth::canAccessAdmin() ? null : $this->redirect(AdminMiddleware::LOGIN_PATH);
    }

    /**
     * Kayıt bulunamadığında listeye hata mesajıyla döner.
     */
    private function notFound(): Response
    {
        Session::flash('error', 'Ürün bulunamadı.');

        return $this->redirect(self::LIST_PATH);
    }
}