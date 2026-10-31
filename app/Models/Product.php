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
 *
 * Gereksinimler: FR-002, FR-003, FR-010, FR-011, FR-015, FR-020, FR-024,
 * FR-033, FR-097.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;

final class Product extends Model
{
    /** Sayfalamada tek sayfada izin verilen en yüksek kayıt sayısı */
    private const MAX_PER_PAGE = 100;

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