<?php

/**
 * Aşıroğlu Yöresel Market - Ürün varyantı modeli
 *
 * product_variants tablosu (migration 007) için veri erişimi sağlar:
 * "500 Gr", "1 Kg", "2 Kg" gibi gramaj/paket seçenekleri.
 *
 * Kurallar:
 * - Varyantı olan üründe geçerli stok bu tablodaki stock_quantity'dir
 *   (DECISIONS.md, Stok Kuralları).
 * - Varyantlar fiziksel silinmez; satıştan kaldırmak için is_active = 0
 *   kullanılır. Bu yüzden delete() kapalıdır, setActive() kullanılır.
 * - stock_quantity yalnızca oluşturmada başlangıç stoğu olarak yazılır.
 *   Sonraki değişiklikler stok modülünden (stock_movements) yapılır (FR-026).
 * - price > 0 ve discount_price < price. Asıl doğrulama controller'dadır;
 *   burada ikinci savunma katmanı olarak denetlenir.
 * - SKU, ürünler ve varyantlar arasında benzersizdir (farklı tablolar olduğu
 *   için kontrol modelde yapılır).
 * - Stok 0 olan veya pasif varyant satın alınamaz (FR-025).
 *
 * Tüm sorgular PDO prepared statement ile çalışır; girdi doğrulaması
 * (Validator) controller katmanında yapılır, bu sınıfta $_GET / $_POST
 * kullanılmaz.
 *
 * Gereksinimler: FR-020, FR-021, FR-022, FR-025.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use BadMethodCallException;
use InvalidArgumentException;

final class ProductVariant extends Model
{
    protected string $table = 'product_variants';

    /** product_variants tablosunda deleted_at yoktur; pasifleştirme kullanılır */
    protected bool $softDelete = false;

    /**
     * create() ve update() ile yazılabilen sütunlar.
     * id, created_at ve updated_at bilinçli olarak dışarıda tutulur.
     *
     * @var list<string>
     */
    protected array $fillable = [
        'product_id',
        'variant_name',
        'sku',
        'barcode',
        'price',
        'discount_price',
        'stock_quantity',
        'weight',
        'is_active',
    ];

    // -------------------------------------------------------------------------
    // Sorgular
    // -------------------------------------------------------------------------

    /**
     * Ürünün varyantlarını fiyata göre sıralı döndürür.
     * $onlyActive true ise yalnızca aktif varyantlar gelir (vitrin).
     *
     * @return list<array<string, mixed>>
     */
    public function getByProduct(int $productId, bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM product_variants WHERE product_id = ?';

        if ($onlyActive) {
            $sql .= ' AND is_active = 1';
        }

        return $this->fetchAll($sql . ' ORDER BY price ASC, id ASC', [$productId]);
    }

    /**
     * Varyant belirtilen ürüne ait mi? (controller'da sahiplik denetimi için)
     *
     * @return array<string, mixed>|null
     */
    public function findForProduct(int $variantId, int $productId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM product_variants WHERE id = ? AND product_id = ?',
            [$variantId, $productId]
        );
    }

    /** Ürünün varyant sayısı (pasifler dahil). */
    public function countByProduct(int $productId): int
    {
        return (int) $this->fetchValue(
            'SELECT COUNT(*) FROM product_variants WHERE product_id = ?',
            [$productId]
        );
    }

    /**
     * Varyant şu an satın alınabilir mi? Aktif olmalı ve stoğu bulunmalıdır;
     * ürün silinmemiş olmalıdır (FR-025).
     */
    public function isPurchasable(int $variantId): bool
    {
        return (int) $this->fetchValue(
            'SELECT COUNT(*) FROM product_variants v'
            . ' INNER JOIN products p ON p.id = v.product_id AND p.deleted_at IS NULL'
            . ' WHERE v.id = ? AND v.is_active = 1 AND v.stock_quantity > 0',
            [$variantId]
        ) > 0;
    }

    /**
     * Varyantın şu an geçerli satış fiyatı (KDV dahil, TRY).
     * İndirimli fiyat satış fiyatından düşükse o geçerlidir.
     *
     * @param array<string, mixed> $variant product_variants satırı
     */
    public function currentPrice(array $variant): float
    {
        $price    = (float) ($variant['price'] ?? 0);
        $discount = $variant['discount_price'] ?? null;

        if ($discount === null || $discount === '' || (float) $discount >= $price) {
            return $price;
        }

        return (float) $discount;
    }

    // -------------------------------------------------------------------------
    // İş kuralı kontrolleri (controller / form doğrulamasında kullanılır)
    // -------------------------------------------------------------------------

    /**
     * SKU başka bir varyantta veya herhangi bir üründe kullanılıyor mu?
     *
     * Ürün ve varyant SKU'ları farklı tablolarda olduğu için UNIQUE indeks
     * birbirlerini kapsamaz; çakışma burada denetlenir. Silinmiş ürünler de
     * sayılır (ürün tablosundaki UNIQUE indeks onları da kapsar).
     * $exceptVariantId, düzenlenen varyantın kendisini hariç tutar.
     */
    public function skuExists(string $sku, ?int $exceptVariantId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM product_variants WHERE sku = ?';
        $params = [$sku];

        if ($exceptVariantId !== null) {
            $sql     .= ' AND id <> ?';
            $params[] = $exceptVariantId;
        }

        if ((int) $this->fetchValue($sql, $params) > 0) {
            return true;
        }

        return (int) $this->fetchValue(
            'SELECT COUNT(*) FROM products WHERE sku = ?',
            [$sku]
        ) > 0;
    }

    /**
     * Aynı üründe aynı adlı varyant var mı? (örn. iki tane "500 Gr" olmasın)
     * $exceptVariantId, düzenlenen varyantın kendisini hariç tutar.
     */
    public function nameExists(int $productId, string $name, ?int $exceptVariantId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM product_variants WHERE product_id = ? AND variant_name = ?';
        $params = [$productId, trim($name)];

        if ($exceptVariantId !== null) {
            $sql     .= ' AND id <> ?';
            $params[] = $exceptVariantId;
        }

        return (int) $this->fetchValue($sql, $params) > 0;
    }

    // -------------------------------------------------------------------------
    // Yazma işlemleri
    // -------------------------------------------------------------------------

    /**
     * Yeni varyant ekler ve ID'sini döndürür. Ürün var ve silinmemiş olmalı;
     * fiyat kuralları denetlenir. stock_quantity başlangıç stoğudur.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException Ürün yoksa veya kural ihlal edilirse
     */
    public function create(array $data): int
    {
        $productId = (int) ($data['product_id'] ?? 0);

        if ($productId <= 0 || (new Product())->find($productId) === null) {
            throw new InvalidArgumentException('Varyantın ürünü bulunamadı.');
        }

        $this->assertValues($data, null);

        return parent::create($data);
    }

    /**
     * Varyantı günceller. product_id ve stock_quantity bu yolla değiştirilemez:
     * varyant başka ürüne taşınmaz, stok değişikliği stok modülünden yapılır.
     * Fiyat kuralları mevcut kayıtla birleştirilerek denetlenir.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException Yasaklı alan verilirse veya kural ihlal edilirse
     */
    public function update(int $id, array $data): bool
    {
        foreach (['product_id', 'stock_quantity'] as $forbidden) {
            if (array_key_exists($forbidden, $data)) {
                throw new InvalidArgumentException(
                    $forbidden . ' alanı update() ile değiştirilemez.'
                );
            }
        }

        $existing = $this->find($id);

        if ($existing === null) {
            return false;
        }

        $this->assertValues($data, $existing);

        return parent::update($id, $data);
    }

    /**
     * Varyantı satışa açar veya kapatır (FR-025). Kayıt silinmez; sipariş
     * geçmişi ve stok hareketleri etkilenmez.
     */
    public function setActive(int $id, bool $active): bool
    {
        return $this->execute(
            'UPDATE product_variants SET is_active = ? WHERE id = ?',
            [$active ? 1 : 0, $id]
        ) > 0;
    }

    /**
     * Varyantlar fiziksel silinmez; satıştan kaldırmak için setActive(false)
     * kullanılmalıdır.
     *
     * @throws BadMethodCallException Her zaman
     */
    public function delete(int $id): bool
    {
        throw new BadMethodCallException(
            'ProductVariant::delete() kapalıdır. setActive(false) kullanın.'
        );
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Fiyat ve ad kurallarını denetler. $existing, güncellemede verilmeyen
     * alanların mevcut değerlerini sağlar.
     *
     * @param array<string, mixed>      $data
     * @param array<string, mixed>|null $existing
     *
     * @throws InvalidArgumentException Kural ihlal edilirse
     */
    private function assertValues(array $data, ?array $existing): void
    {
        $merged = $data + ($existing ?? []);

        $name = $merged['variant_name'] ?? null;

        if (!is_string($name) || trim($name) === '') {
            throw new InvalidArgumentException('Varyant adı zorunludur.');
        }

        $price = $merged['price'] ?? null;

        if ($price === null || $price === '' || (float) $price <= 0) {
            throw new InvalidArgumentException('Varyant fiyatı sıfırdan büyük olmalıdır.');
        }

        $discount = $merged['discount_price'] ?? null;

        if ($discount !== null && $discount !== '' && (float) $discount >= (float) $price) {
            throw new InvalidArgumentException('İndirimli fiyat, satış fiyatından düşük olmalıdır.');
        }

        $stock = $merged['stock_quantity'] ?? 0;

        if ((int) $stock < 0) {
            throw new InvalidArgumentException('Stok adedi negatif olamaz.');
        }
    }
}