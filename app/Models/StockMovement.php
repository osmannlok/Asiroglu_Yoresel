<?php

/**
 * Aşıroğlu Yöresel Market - Stok hareketi modeli
 *
 * stock_movements tablosu (migration 009) için veri erişimi sağlar. Tablo
 * yalnızca eklenir, düzenlenmez ve silinmez (denetim izi).
 *
 * Kurallar (DECISIONS.md, Stok Kuralları):
 * - Varyantsız üründe stok products.stock_quantity, varyantlı üründe
 *   product_variants.stock_quantity üzerinden değişir. Varyantı olan ürüne
 *   varyantsız hareket yazılamaz.
 * - Stok güncellemesi ve hareket kaydı AYNI transaction içinde yapılır; ilgili
 *   stok satırı FOR UPDATE ile kilitlenir.
 * - Stok negatife düşemez.
 * - Manuel hareketlerde işlem nedeni (note) zorunludur (FR-026). Sistem
 *   kaynaklı hareketlerde (reference_type dolu) not isteğe bağlıdır.
 *
 * Hareket türleri:
 * - incoming, return : stok artar
 * - outgoing, reservation : stok azalır
 * - correction : $quantity HEDEF stok adedidir; kaydedilen quantity farkın
 *   mutlak değeridir, yön previous_quantity / new_quantity'den anlaşılır
 *
 * Tüm sorgular PDO prepared statement ile çalışır; girdi doğrulaması
 * (Validator) controller katmanında yapılır, bu sınıfta $_GET / $_POST
 * kullanılmaz.
 *
 * Gereksinimler: FR-023, FR-024, FR-026.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use BadMethodCallException;
use InvalidArgumentException;

final class StockMovement extends Model
{
    /** Sayfalamada tek sayfada izin verilen en yüksek kayıt sayısı */
    private const MAX_PER_PAGE = 100;

    /** Geçerli hareket türleri (stock_movements.movement_type ENUM) */
    public const TYPES = ['incoming', 'outgoing', 'return', 'correction', 'reservation'];

    /** Stoğu artıran türler */
    private const INCREASING_TYPES = ['incoming', 'return'];

    /** Stoğu azaltan türler */
    private const DECREASING_TYPES = ['outgoing', 'reservation'];

    /** En büyük tek seferlik miktar (products.stock_quantity ile uyumlu sınır) */
    private const MAX_QUANTITY = 10000000;

    protected string $table = 'stock_movements';

    /** Hareketler silinmez ve değiştirilmez */
    protected bool $softDelete = false;

    /**
     * @var list<string>
     */
    protected array $fillable = [
        'product_id',
        'product_variant_id',
        'movement_type',
        'quantity',
        'previous_quantity',
        'new_quantity',
        'reference_type',
        'reference_id',
        'note',
        'created_by',
    ];

    // -------------------------------------------------------------------------
    // Hareket kaydı
    // -------------------------------------------------------------------------

    /**
     * Stok hareketi kaydeder ve stok adedini aynı transaction içinde günceller.
     * Yeni hareketin ID'sini döndürür.
     *
     * @param int         $productId   Ürün kimliği (silinmemiş olmalı)
     * @param int|null    $variantId   Varyant kimliği; varyantsız üründe null
     * @param string      $type        TYPES içinden bir tür
     * @param int         $quantity    Miktar (correction'da hedef stok adedi)
     * @param string|null $note        İşlem nedeni (manuel harekette zorunlu)
     * @param int|null    $createdBy   İşlemi yapan kullanıcı; sistem işleminde null
     * @param string|null $referenceType Kaynak türü (örn. 'order'); manuel harekette null
     * @param int|null    $referenceId Kaynak kimliği
     *
     * @throws InvalidArgumentException Kural ihlal edilirse
     */
    public function record(
        int $productId,
        ?int $variantId,
        string $type,
        int $quantity,
        ?string $note,
        ?int $createdBy = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): int {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Geçersiz stok hareketi türü.');
        }

        if ($quantity > self::MAX_QUANTITY) {
            throw new InvalidArgumentException('Miktar çok büyük.');
        }

        // correction hedef stoktur ve 0 olabilir; diğer türlerde miktar pozitif olmalı
        if ($type === 'correction' ? $quantity < 0 : $quantity <= 0) {
            throw new InvalidArgumentException(
                $type === 'correction'
                    ? 'Hedef stok adedi negatif olamaz.'
                    : 'Miktar sıfırdan büyük olmalıdır.'
            );
        }

        $cleanNote = $note === null ? '' : mb_substr(trim($note), 0, 500);

        // Manuel hareketlerde işlem nedeni zorunludur (FR-026)
        if ($referenceType === null && $cleanNote === '') {
            throw new InvalidArgumentException('İşlem nedeni zorunludur.');
        }

        return (int) $this->transaction(
            function () use (
                $productId,
                $variantId,
                $type,
                $quantity,
                $cleanNote,
                $createdBy,
                $referenceType,
                $referenceId
            ): int {
                $previous = $this->lockCurrentStock($productId, $variantId);

                if (in_array($type, self::INCREASING_TYPES, true)) {
                    $new   = $previous + $quantity;
                    $moved = $quantity;
                } elseif (in_array($type, self::DECREASING_TYPES, true)) {
                    if ($quantity > $previous) {
                        throw new InvalidArgumentException(
                            'Yetersiz stok. Mevcut stok: ' . $previous . '.'
                        );
                    }
                    $new   = $previous - $quantity;
                    $moved = $quantity;
                } else {
                    // correction: miktar hedef stoktur
                    $new   = $quantity;
                    $moved = abs($new - $previous);
                }

                if ($new > self::MAX_QUANTITY) {
                    throw new InvalidArgumentException('Stok adedi izin verilen üst sınırı aşıyor.');
                }

                // Değişiklik olmayan düzeltme kayıt oluşturmaz
                if ($type === 'correction' && $moved === 0) {
                    throw new InvalidArgumentException('Hedef stok mevcut stokla aynı.');
                }

                if ($variantId === null) {
                    $this->execute(
                        'UPDATE products SET stock_quantity = ? WHERE id = ?',
                        [$new, $productId]
                    );
                } else {
                    $this->execute(
                        'UPDATE product_variants SET stock_quantity = ? WHERE id = ? AND product_id = ?',
                        [$new, $variantId, $productId]
                    );
                }

                return parent::create([
                    'product_id'         => $productId,
                    'product_variant_id' => $variantId,
                    'movement_type'      => $type,
                    'quantity'           => $moved,
                    'previous_quantity'  => $previous,
                    'new_quantity'       => $new,
                    'reference_type'     => $referenceType,
                    'reference_id'       => $referenceId,
                    'note'               => $cleanNote === '' ? null : $cleanNote,
                    'created_by'         => $createdBy,
                ]);
            }
        );
    }

    // -------------------------------------------------------------------------
    // Sorgular
    // -------------------------------------------------------------------------

    /**
     * Hareket geçmişi: sayfalı, ürün/varyant adı ve kullanıcı adıyla.
     * $productId, $variantId ve $type isteğe bağlı filtrelerdir.
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
        ?int $productId = null,
        ?int $variantId = null,
        ?string $type = null
    ): array {
        $perPage = min(self::MAX_PER_PAGE, max(1, $perPage));

        // $where yalnızca bu metottaki sabit parçalardan oluşur; kullanıcı
        // girdisi her zaman '?' parametresi olarak bağlanır.
        $where  = '1 = 1';
        $params = [];

        if ($productId !== null && $productId > 0) {
            $where   .= ' AND m.product_id = ?';
            $params[] = $productId;
        }

        if ($variantId !== null && $variantId > 0) {
            $where   .= ' AND m.product_variant_id = ?';
            $params[] = $variantId;
        }

        if ($type !== null && in_array($type, self::TYPES, true)) {
            $where   .= ' AND m.movement_type = ?';
            $params[] = $type;
        }

        $total      = (int) $this->fetchValue(
            'SELECT COUNT(*) FROM stock_movements m WHERE ' . $where,
            $params
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = min(max(1, $page), $totalPages);
        $offset     = ($page - 1) * $perPage;

        // LIMIT ve OFFSET tamsayıya çevrilmiş değerlerdir, güvenle eklenir
        $items = $this->fetchAll(
            'SELECT m.*, p.name AS product_name, p.sku AS product_sku,'
            . ' v.variant_name, v.sku AS variant_sku,'
            . " CONCAT(u.first_name, ' ', u.last_name) AS user_name"
            . ' FROM stock_movements m'
            . ' INNER JOIN products p ON p.id = m.product_id'
            . ' LEFT JOIN product_variants v ON v.id = m.product_variant_id'
            . ' LEFT JOIN users u ON u.id = m.created_by'
            . ' WHERE ' . $where
            . ' ORDER BY m.created_at DESC, m.id DESC'
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
     * Stok hareketi yapılabilecek kalemler (ürün veya varyant), kritik stok
     * bilgisiyle. Varyantlı üründe satırlar varyantlardır; ürünün kendisi
     * listelenmez. Yalnızca silinmemiş ürünler gelir.
     *
     * @return list<array<string, mixed>>
     */
    public function getStockItems(?string $search = null, int $limit = 200): array
    {
        $limit  = min(500, max(1, $limit));
        $params = [];
        $filter = '';

        if ($search !== null && trim($search) !== '') {
            $filter   = " AND (p.name LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!')";
            $like     = '%' . $this->escapeLike(trim($search)) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        // Varyantsız ürünler ile varyantlar tek listede birleştirilir
        return $this->fetchAll(
            'SELECT p.id AS product_id, NULL AS variant_id, p.name AS product_name,'
            . ' NULL AS variant_name, p.sku AS sku, p.stock_quantity AS stock_quantity,'
            . ' p.critical_stock_quantity AS critical_stock_quantity'
            . ' FROM products p'
            . ' WHERE p.deleted_at IS NULL'
            . ' AND NOT EXISTS (SELECT 1 FROM product_variants x WHERE x.product_id = p.id)'
            . $filter
            . ' UNION ALL'
            . ' SELECT p.id, v.id, p.name, v.variant_name, v.sku, v.stock_quantity,'
            . ' p.critical_stock_quantity'
            . ' FROM product_variants v'
            . ' INNER JOIN products p ON p.id = v.product_id AND p.deleted_at IS NULL'
            . ' WHERE 1 = 1' . $filter
            . ' ORDER BY product_name ASC, variant_name ASC'
            . ' LIMIT ' . $limit,
            array_merge($params, $params)
        );
    }

    // -------------------------------------------------------------------------
    // Yazma yasakları
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $data
     *
     * @throws BadMethodCallException Her zaman
     */
    public function create(array $data): int
    {
        throw new BadMethodCallException('StockMovement::create() kapalıdır. record() kullanın.');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws BadMethodCallException Her zaman
     */
    public function update(int $id, array $data): bool
    {
        throw new BadMethodCallException('Stok hareketleri değiştirilemez.');
    }

    /**
     * @throws BadMethodCallException Her zaman
     */
    public function delete(int $id): bool
    {
        throw new BadMethodCallException('Stok hareketleri silinemez.');
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * İlgili stok satırını kilitleyip mevcut stoğu döndürür (transaction içinde
     * çağrılmalıdır). Ürün/varyant kuralları burada denetlenir.
     *
     * @throws InvalidArgumentException Ürün/varyant geçersizse
     */
    private function lockCurrentStock(int $productId, ?int $variantId): int
    {
        $product = $this->fetchOne(
            'SELECT id, stock_quantity FROM products'
            . ' WHERE id = ? AND deleted_at IS NULL FOR UPDATE',
            [$productId]
        );

        if ($product === null) {
            throw new InvalidArgumentException('Ürün bulunamadı.');
        }

        $hasVariants = (int) $this->fetchValue(
            'SELECT COUNT(*) FROM product_variants WHERE product_id = ?',
            [$productId]
        ) > 0;

        if ($variantId === null) {
            if ($hasVariants) {
                throw new InvalidArgumentException(
                    'Varyantı olan ürünün stoğu varyant düzeyinde yönetilir.'
                );
            }

            return (int) $product['stock_quantity'];
        }

        $variant = $this->fetchOne(
            'SELECT stock_quantity FROM product_variants'
            . ' WHERE id = ? AND product_id = ? FOR UPDATE',
            [$variantId, $productId]
        );

        if ($variant === null) {
            throw new InvalidArgumentException('Varyant bu ürüne ait değil.');
        }

        return (int) $variant['stock_quantity'];
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