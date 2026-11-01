<?php

/**
 * Aşıroğlu Yöresel Market - Ürün modeli
 *
 * products tablosu (migration 005) için veri erişimi sağlar. Ürünler soft
 * delete kullanır (deleted_at). Tüm sorgular Model sınıfının PDO prepared
 * statement yardımcılarıyla çalışır; girdi doğrulaması (Validator, Uploader)
 * controller katmanında yapılır, bu sınıfta $_GET / $_POST kullanılmaz.
 *
 * İş kuralları:
 * - Varyantı olan üründe geçerli stok product_variants.stock_quantity'dir;
 *   bu ürünlerde products.stock_quantity yazılamaz (DECISIONS.md, Stok Kuralları).
 * - price > 0 ve discount_price < price. Asıl doğrulama controller'dadır;
 *   burada ikinci savunma katmanı olarak denetlenir.
 * - SKU, ürünler ve varyantlar arasında benzersizdir (farklı tablolar olduğu
 *   için kontrol modelde yapılır).
 * - Pasif/silinmiş kategorideki veya markadaki ürünler vitrinde listelenmez (FR-033).
 * - Vitrin listesinde görüntülenen fiyat: varyantsız üründe geçerli fiyat
 *   (indirim tarih aralığı içindeyse indirimli), varyantlı üründe en ucuz
 *   aktif varyantın geçerli fiyatı ("başlayan fiyat").
 *
 * Gereksinimler: FR-002, FR-003, FR-004, FR-005, FR-006, FR-010, FR-011,
 * FR-015, FR-020, FR-024, FR-033, FR-097.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;

final class Product extends Model
{
    /** Sayfalamada tek sayfada izin verilen en yüksek kayıt sayısı */
    private const MAX_PER_PAGE = 100;

    /** Vitrin sıralama seçenekleri (anahtar => ORDER BY; kullanıcı girdisi SQL'e girmez) */
    private const SORT_ORDERS = [
        'new'        => 'x.created_at DESC, x.id DESC',
        'price_asc'  => 'x.display_price ASC, x.id ASC',
        'price_desc' => 'x.display_price DESC, x.id DESC',
        'name'       => 'x.name ASC, x.id ASC',
    ];

    /** Varsayılan vitrin sıralaması */
    public const DEFAULT_SORT = 'new';

    /**
     * Vitrinde görünürlük koşulu (sabit SQL parçası, kullanıcı girdisi içermez).
     * "products p" tablosunu kullanan sorgulara eklenir. Kategori aktif ve
     * silinmemiş, marka yoksa veya aktif ve silinmemiş olmalıdır.
     */
    private const STOREFRONT_JOINS = ' INNER JOIN categories c ON c.id = p.category_id'
        . ' AND c.is_active = 1 AND c.deleted_at IS NULL'
        . ' LEFT JOIN brands b ON b.id = p.brand_id';

    private const STOREFRONT_WHERE = ' p.is_active = 1 AND p.deleted_at IS NULL'
        . ' AND (p.brand_id IS NULL OR (b.is_active = 1 AND b.deleted_at IS NULL))';

    /**
     * Ürünün şu an geçerli fiyatı (sabit SQL ifadesi): indirim, tarih aralığı
     * içindeyse (sınırlar boşsa kısıtsız) geçerlidir.
     */
    private const SQL_PRODUCT_PRICE = 'CASE WHEN p.discount_price IS NOT NULL AND p.discount_price < p.price'
        . ' AND (p.discount_start_date IS NULL OR p.discount_start_date <= NOW())'
        . ' AND (p.discount_end_date IS NULL OR p.discount_end_date >= NOW())'
        . ' THEN p.discount_price ELSE p.price END';

    /** En ucuz aktif varyantın geçerli fiyatı (sabit SQL ifadesi); aktif varyant yoksa NULL */
    private const SQL_VARIANT_MIN_PRICE = '(SELECT MIN(CASE WHEN v.discount_price IS NOT NULL'
        . ' AND v.discount_price < v.price THEN v.discount_price ELSE v.price END)'
        . ' FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1)';

    protected string $table = 'products';

    /** Ürünler fiziksel silinmez; deleted_at doldurulur */
    protected bool $softDelete = true;

    /**
     * create() ve update() ile yazılabilen sütunlar.
     * id, view_count, created_at, updated_at ve deleted_at bilinçli olarak
     * dışarıda tutulur.
     *
     * @var list<string>
     */
    protected array $fillable = [
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
        'is_active',
        'is_featured',
        'is_new',
    ];

    // -------------------------------------------------------------------------
    // Vitrin (müşteri tarafı) sorguları
    // -------------------------------------------------------------------------

    /**
     * Slug'a göre vitrinde görünür ürünü getirir (ürün detay sayfası için).
     *
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->fetchOne(
            'SELECT p.* FROM products p'
            . self::STOREFRONT_JOINS
            . ' WHERE p.slug = ? AND' . self::STOREFRONT_WHERE,
            [$slug]
        );
    }

    /**
     * Ana sayfa için öne çıkan, vitrinde görünür ürünleri getirir.
     *
     * @return list<array<string, mixed>>
     */
    public function getFeatured(int $limit = 8): array
    {
        $limit = min(self::MAX_PER_PAGE, max(1, $limit));

        // $limit tamsayıya çevrilmiştir, güvenle eklenir
        return $this->fetchAll(
            'SELECT p.* FROM products p'
            . self::STOREFRONT_JOINS
            . ' WHERE p.is_featured = 1 AND' . self::STOREFRONT_WHERE
            . ' ORDER BY p.created_at DESC, p.id DESC'
            . ' LIMIT ' . $limit
        );
    }

    /**
     * Vitrin ürün listesi: arama, kategori ve fiyat filtresi, sıralama ve
     * sayfalama (FR-002, FR-003, FR-004).
     *
     * - $search ürün adı, kısa açıklama, açıklama ve SKU içinde arar.
     * - $categoryId seçilen kategoriyi ve doğrudan alt kategorilerini kapsar.
     * - $minPrice / $maxPrice görüntülenen fiyat üzerinden süzer (KDV dahil, TRY).
     * - $sort SORT_ORDERS anahtarlarından biridir; geçersizse varsayılan kullanılır.
     *
     * Her satır: id, category_id, name, slug, sku, short_description, price,
     * unit_type, stock_quantity, is_featured, is_new, created_at, category_name,
     * category_slug, image_path (ana görsel, yoksa null), variant_count,
     * in_stock (0|1), display_price, old_price (indirim yoksa null).
     *
     * @return array{
     *     items: list<array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     perPage: int,
     *     totalPages: int
     * }
     */
    public function paginateStorefront(
        int $page = 1,
        int $perPage = 12,
        ?string $search = null,
        ?int $categoryId = null,
        ?float $minPrice = null,
        ?float $maxPrice = null,
        string $sort = self::DEFAULT_SORT
    ): array {
        $perPage = min(self::MAX_PER_PAGE, max(1, $perPage));
        $order   = self::SORT_ORDERS[$sort] ?? self::SORT_ORDERS[self::DEFAULT_SORT];

        // $innerWhere ve $outerWhere yalnızca bu metottaki sabit parçalardan oluşur;
        // kullanıcı girdisi her zaman '?' parametresi olarak bağlanır.
        $innerWhere  = self::STOREFRONT_WHERE;
        $innerParams = [];

        if ($search !== null && trim($search) !== '') {
            $innerWhere .= " AND (p.name LIKE ? ESCAPE '!'"
                . " OR p.short_description LIKE ? ESCAPE '!'"
                . " OR p.description LIKE ? ESCAPE '!'"
                . " OR p.sku LIKE ? ESCAPE '!')";

            $like = '%' . $this->escapeLike(trim($search)) . '%';

            array_push($innerParams, $like, $like, $like, $like);
        }

        if ($categoryId !== null && $categoryId > 0) {
            $innerWhere   .= ' AND (p.category_id = ? OR c.parent_id = ?)';
            $innerParams[] = $categoryId;
            $innerParams[] = $categoryId;
        }

        $outerWhere  = '1 = 1';
        $outerParams = [];

        if ($minPrice !== null && $minPrice > 0) {
            $outerWhere   .= ' AND x.display_price >= ?';
            $outerParams[] = round($minPrice, 2);
        }

        if ($maxPrice !== null && $maxPrice > 0) {
            $outerWhere   .= ' AND x.display_price <= ?';
            $outerParams[] = round($maxPrice, 2);
        }

        $inner  = $this->storefrontListSql($innerWhere);
        $params = array_merge($innerParams, $outerParams);

        $total = (int) $this->fetchValue(
            'SELECT COUNT(*) FROM (' . $inner . ') x WHERE ' . $outerWhere,
            $params
        );

        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = min(max(1, $page), $totalPages);
        $offset     = ($page - 1) * $perPage;

        // LIMIT ve OFFSET tamsayıya çevrilmiş değerlerdir, güvenle eklenir
        $items = $this->fetchAll(
            'SELECT x.* FROM (' . $inner . ') x'
            . ' WHERE ' . $outerWhere
            . ' ORDER BY ' . $order
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Ürün sayfası görüntülenme sayacını artırır.
     */
    public function incrementViewCount(int $id): void
    {
        $this->execute(
            'UPDATE products SET view_count = view_count + 1 WHERE id = ? AND deleted_at IS NULL',
            [$id]
        );
    }

    // -------------------------------------------------------------------------
    // Admin paneli sorguları
    // -------------------------------------------------------------------------

    /**
     * Admin listesi için sayfalı ürün listesi (kategori, marka adı ve varyant
     * sayısıyla). $search ürün adı veya SKU içinde arar. $categoryId ve
     * $status ('active' | 'passive') isteğe bağlı filtrelerdir.
     *
     * @return array{
     *     items: list<array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     perPage: int,
     *     totalPages: int
     * }
     */
    public function paginate(
        int $page = 1,
        int $perPage = 20,
        ?string $search = null,
        ?int $categoryId = null,
        ?string $status = null
    ): array {
        $perPage = min(self::MAX_PER_PAGE, max(1, $perPage));

        // $where yalnızca bu metottaki sabit parçalardan oluşur; kullanıcı
        // girdisi her zaman '?' parametresi olarak bağlanır.
        $where  = 'p.deleted_at IS NULL';
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $where   .= " AND (p.name LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!')";
            $like     = '%' . $this->escapeLike(trim($search)) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if ($categoryId !== null && $categoryId > 0) {
            $where   .= ' AND p.category_id = ?';
            $params[] = $categoryId;
        }

        if ($status === 'active') {
            $where .= ' AND p.is_active = 1';
        } elseif ($status === 'passive') {
            $where .= ' AND p.is_active = 0';
        }

        $total      = (int) $this->fetchValue(
            'SELECT COUNT(*) FROM products p WHERE ' . $where,
            $params
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = min(max(1, $page), $totalPages);
        $offset     = ($page - 1) * $perPage;

        // LIMIT ve OFFSET tamsayıya çevrilmiş değerlerdir, güvenle eklenir
        $items = $this->fetchAll(
            'SELECT p.*, c.name AS category_name, b.name AS brand_name,'
            . ' (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS variant_count'
            . ' FROM products p'
            . ' INNER JOIN categories c ON c.id = p.category_id'
            . ' LEFT JOIN brands b ON b.id = p.brand_id'
            . ' WHERE ' . $where
            . ' ORDER BY p.created_at DESC, p.id DESC'
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Kritik stok eşiğine inmiş (veya altındaki) varyantsız ürünler (FR-024, FR-097).
     * Varyantlı ürünlerin stoğu varyant düzeyinde izlenir; bu liste onları içermez.
     *
     * @return list<array<string, mixed>>
     */
    public function getLowStock(int $limit = 10): array
    {
        $limit = min(self::MAX_PER_PAGE, max(1, $limit));

        return $this->fetchAll(
            'SELECT p.id, p.name, p.sku, p.stock_quantity, p.critical_stock_quantity'
            . ' FROM products p'
            . ' WHERE p.deleted_at IS NULL AND p.is_active = 1'
            . ' AND p.stock_quantity <= p.critical_stock_quantity'
            . ' AND NOT EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id)'
            . ' ORDER BY p.stock_quantity ASC, p.name ASC'
            . ' LIMIT ' . $limit
        );
    }

    // -------------------------------------------------------------------------
    // İş kuralı kontrolleri (controller / form doğrulamasında kullanılır)
    // -------------------------------------------------------------------------

    /**
     * Slug başka bir üründe kullanılıyor mu?
     *
     * DİKKAT: slug sütunundaki UNIQUE indeks soft delete ile silinmiş
     * kayıtları da kapsar; bu yüzden silinmiş ürünler de kontrole dahildir.
     * $exceptId, düzenlenen kaydın kendisini hariç tutar.
     */
    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM products WHERE slug = ?';
        $params = [$slug];

        if ($exceptId !== null) {
            $sql     .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return (int) $this->fetchValue($sql, $params) > 0;
    }

    /**
     * SKU başka bir üründe veya herhangi bir varyantta kullanılıyor mu?
     *
     * Ürün ve varyant SKU'ları farklı tablolarda olduğu için UNIQUE indeks
     * birbirlerini kapsamaz; çakışma burada denetlenir. Silinmiş ürünler de
     * sayılır (UNIQUE indeks onları da kapsar). $exceptProductId, düzenlenen
     * ürünün kendisini hariç tutar.
     */
    public function skuExists(string $sku, ?int $exceptProductId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM products WHERE sku = ?';
        $params = [$sku];

        if ($exceptProductId !== null) {
            $sql     .= ' AND id <> ?';
            $params[] = $exceptProductId;
        }

        if ((int) $this->fetchValue($sql, $params) > 0) {
            return true;
        }

        return (int) $this->fetchValue(
            'SELECT COUNT(*) FROM product_variants WHERE sku = ?',
            [$sku]
        ) > 0;
    }

    /**
     * Ürünün varyantı var mı? Varsa geçerli stok varyant düzeyindedir.
     */
    public function hasVariants(int $id): bool
    {
        return (int) $this->fetchValue(
            'SELECT COUNT(*) FROM product_variants WHERE product_id = ?',
            [$id]
        ) > 0;
    }

    /**
     * Satılabilir toplam stok: varyantlı üründe aktif varyantların toplamı,
     * varyantsız üründe products.stock_quantity. Ürün yoksa 0.
     */
    public function getAvailableStock(int $id): int
    {
        if ($this->hasVariants($id)) {
            return (int) $this->fetchValue(
                'SELECT COALESCE(SUM(stock_quantity), 0) FROM product_variants'
                . ' WHERE product_id = ? AND is_active = 1 AND stock_quantity > 0',
                [$id]
            );
        }

        return max(0, (int) $this->fetchValue(
            'SELECT stock_quantity FROM products WHERE id = ? AND deleted_at IS NULL',
            [$id]
        ));
    }

    /**
     * Ürünün şu an geçerli satış fiyatını döndürür (KDV dahil, TRY).
     * İndirimli fiyat, tarih aralığı içindeyse (sınırlar boşsa kısıtsız)
     * geçerlidir; aksi halde normal fiyat kullanılır.
     *
     * @param array<string, mixed> $product products satırı
     */
    public function currentPrice(array $product, ?int $now = null): float
    {
        $price    = (float) ($product['price'] ?? 0);
        $discount = $product['discount_price'] ?? null;

        if ($discount === null || $discount === '' || (float) $discount >= $price) {
            return $price;
        }

        $now   = $now ?? time();
        $start = $product['discount_start_date'] ?? null;
        $end   = $product['discount_end_date'] ?? null;

        if (is_string($start) && $start !== '' && strtotime($start) > $now) {
            return $price;
        }

        if (is_string($end) && $end !== '' && strtotime($end) < $now) {
            return $price;
        }

        return (float) $discount;
    }

    // -------------------------------------------------------------------------
    // Model sınıfının davranışını genişleten metotlar
    // -------------------------------------------------------------------------

    /**
     * Yeni ürün ekler. Fiyat zorunludur ve fiyat kuralları denetlenir.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException Fiyat kuralları ihlal edilirse
     */
    public function create(array $data): int
    {
        if (!array_key_exists('price', $data)) {
            throw new InvalidArgumentException('Ürün fiyatı zorunludur.');
        }

        $this->assertPricing($data, null);

        return parent::create($data);
    }

    /**
     * Ürünü günceller. Fiyat kuralları mevcut kayıtla birleştirilerek denetlenir;
     * varyantı olan üründe stock_quantity yazılamaz.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException Kural ihlal edilirse
     */
    public function update(int $id, array $data): bool
    {
        $existing = $this->find($id);

        if ($existing === null) {
            return false;
        }

        if (array_key_exists('stock_quantity', $data) && $this->hasVariants($id)) {
            throw new InvalidArgumentException(
                'Varyantı olan ürünün stoğu varyant düzeyinde yönetilir.'
            );
        }

        $this->assertPricing($data, $existing);

        return parent::update($id, $data);
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Vitrin listesinin iç sorgusunu üretir. $where yalnızca sabit parçalardan
     * oluşur (kullanıcı girdisi '?' parametresidir). Sütunlar, kart için
     * gerekenlerle sınırlıdır; uzun açıklama listeye taşınmaz.
     */
    private function storefrontListSql(string $where): string
    {
        return 'SELECT p.id, p.category_id, p.name, p.slug, p.sku, p.short_description,'
            . ' p.price, p.unit_type, p.stock_quantity, p.is_featured, p.is_new, p.created_at,'
            . ' c.name AS category_name, c.slug AS category_slug,'
            . ' (SELECT i.image_path FROM product_images i WHERE i.product_id = p.id'
            . '  ORDER BY i.is_main DESC, i.sort_order ASC, i.id ASC LIMIT 1) AS image_path,'
            . ' (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS variant_count,'
            . ' (CASE WHEN EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id)'
            . '  THEN EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id'
            . '   AND v.is_active = 1 AND v.stock_quantity > 0)'
            . '  ELSE p.stock_quantity > 0 END) AS in_stock,'
            . ' COALESCE(' . self::SQL_VARIANT_MIN_PRICE . ', ' . self::SQL_PRODUCT_PRICE . ') AS display_price,'
            . ' (CASE WHEN NOT EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id)'
            . '  AND (' . self::SQL_PRODUCT_PRICE . ') < p.price THEN p.price ELSE NULL END) AS old_price'
            . ' FROM products p'
            . self::STOREFRONT_JOINS
            . ' WHERE' . $where;
    }

    /**
     * Fiyat kurallarını denetler: price > 0, discount_price < price,
     * indirim bitişi başlangıçtan önce olamaz. $existing, güncellemede
     * verilmeyen alanların mevcut değerlerini sağlar.
     *
     * @param array<string, mixed>      $data
     * @param array<string, mixed>|null $existing
     *
     * @throws InvalidArgumentException Kural ihlal edilirse
     */
    private function assertPricing(array $data, ?array $existing): void
    {
        $merged = $data + ($existing ?? []);

        $price = $merged['price'] ?? null;

        if ($price === null || $price === '' || (float) $price <= 0) {
            throw new InvalidArgumentException('Satış fiyatı sıfırdan büyük olmalıdır.');
        }

        $discount = $merged['discount_price'] ?? null;

        if ($discount !== null && $discount !== '' && (float) $discount >= (float) $price) {
            throw new InvalidArgumentException('İndirimli fiyat, satış fiyatından düşük olmalıdır.');
        }

        $start = $merged['discount_start_date'] ?? null;
        $end   = $merged['discount_end_date'] ?? null;

        if (is_string($start) && $start !== '' && is_string($end) && $end !== '') {
            $startTime = strtotime($start);
            $endTime   = strtotime($end);

            if ($startTime === false || $endTime === false || $endTime < $startTime) {
                throw new InvalidArgumentException('İndirim bitiş tarihi, başlangıçtan önce olamaz.');
            }
        }
    }

    /**
     * LIKE aramasında özel anlamı olan karakterleri kaçırır.
     * Kaçış karakteri olarak '!' kullanılır (sorguda ESCAPE '!' ile eşleşir).
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}