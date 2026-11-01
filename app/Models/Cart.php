<?php

/**
 * Aşıroğlu Yöresel Market - Sepet modeli (üye sepeti)
 *
 * carts ve cart_items tabloları (migration 011) için veri erişimi sağlar.
 * Tablo yalnızca giriş yapmış kullanıcılar içindir; misafir sepeti PHP
 * session'da tutulur ve burada yer almaz (DECISIONS.md, Misafir Sepeti).
 *
 * Kurallar:
 * - Kullanıcının tek aktif sepeti olur; yoksa ilk eklemede oluşturulur.
 * - Aynı ürün + aynı varyant tekrar eklenirse adet artırılır.
 * - Varyantlı üründe varyant seçimi zorunludur; stok varyant düzeyindedir.
 * - Stok yetersizse veya varyant pasifse ekleme/güncelleme reddedilir (FR-044).
 * - unit_price sepete eklendiği andaki güncel fiyattır; sipariş oluşturulurken
 *   yeniden kontrol edilir.
 * - Ekleme/güncelleme transaction içinde, sepet satırı FOR UPDATE ile
 *   kilitlenerek yapılır.
 * - resolveSellable() ve assertQuantity() public'tir: CartService misafir
 *   (session) sepetinde aynı stok/varyant/vitrin kurallarını bunlarla uygular.
 *
 * Tüm sorgular PDO prepared statement ile çalışır; $_GET / $_POST / $_SESSION
 * kullanılmaz.
 *
 * Gereksinimler: FR-040, FR-041, FR-042, FR-044, NFR-001.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use BadMethodCallException;
use InvalidArgumentException;

final class Cart extends Model
{
    /** Sepette bulunabilecek en fazla farklı kalem sayısı */
    private const MAX_ITEMS = 50;

    /** Bir kalemde izin verilen en fazla adet */
    private const MAX_QUANTITY = 1000;

    protected string $table = 'carts';

    /** carts tablosunda deleted_at yoktur */
    protected bool $softDelete = false;

    /**
     * @var list<string>
     */
    protected array $fillable = [
        'user_id',
        'coupon_id',
        'status',
    ];

    // -------------------------------------------------------------------------
    // Sepet
    // -------------------------------------------------------------------------

    /**
     * Kullanıcının aktif sepetini döndürür; yoksa null.
     *
     * @return array<string, mixed>|null
     */
    public function findActiveByUser(int $userId): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM carts WHERE user_id = ? AND status = 'active'"
            . ' ORDER BY id DESC LIMIT 1',
            [$userId]
        );
    }

    /**
     * Kullanıcının aktif sepetinin ID'sini döndürür; yoksa oluşturur.
     */
    public function getOrCreateActive(int $userId): int
    {
        $cart = $this->findActiveByUser($userId);

        if ($cart !== null) {
            return (int) $cart['id'];
        }

        return parent::create([
            'user_id' => $userId,
            'status'  => 'active',
        ]);
    }

    // -------------------------------------------------------------------------
    // Kalemler
    // -------------------------------------------------------------------------

    /**
     * Sepet kalemlerini ürün ve varyant bilgisiyle döndürür.
     * Vitrinde görünmeyen (pasif/silinmiş) ürünler de gelir; view bunları
     * "satın alınamaz" olarak işaretleyebilsin diye 'available_stock' alanı eklenir.
     *
     * @return list<array<string, mixed>>
     */
    public function getItems(int $cartId): array
    {
        return $this->fetchAll(
            'SELECT i.*, p.name AS product_name, p.slug AS product_slug, p.sku AS product_sku,'
            . ' p.vat_rate AS vat_rate, p.unit_type AS unit_type,'
            . ' v.variant_name, v.sku AS variant_sku,'
            . ' CASE'
            . '   WHEN p.deleted_at IS NOT NULL OR p.is_active = 0 THEN 0'
            . '   WHEN i.product_variant_id IS NOT NULL THEN'
            . '     (CASE WHEN v.id IS NOT NULL AND v.is_active = 1 THEN v.stock_quantity ELSE 0 END)'
            . '   ELSE p.stock_quantity'
            . ' END AS available_stock'
            . ' FROM cart_items i'
            . ' INNER JOIN products p ON p.id = i.product_id'
            . ' LEFT JOIN product_variants v ON v.id = i.product_variant_id'
            . ' WHERE i.cart_id = ?'
            . ' ORDER BY i.id ASC',
            [$cartId]
        );
    }

    /** Sepetteki toplam adet (rozet için). */
    public function countQuantity(int $cartId): int
    {
        return (int) $this->fetchValue(
            'SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ?',
            [$cartId]
        );
    }

    /**
     * Sepete ürün ekler. Aynı ürün + varyant varsa adedi artırır.
     * Kalemin ID'sini döndürür.
     *
     * @throws InvalidArgumentException Stok/varyant/ürün kuralı ihlal edilirse
     */
    public function addItem(int $userId, int $productId, ?int $variantId, int $quantity): int
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Adet sıfırdan büyük olmalıdır.');
        }

        return (int) $this->transaction(
            function () use ($userId, $productId, $variantId, $quantity): int {
                $cartId = $this->getOrCreateActive($userId);

                // Aynı sepete eşzamanlı ekleme sırasında tutarlılık için kilitle
                $this->fetchValue('SELECT id FROM carts WHERE id = ? FOR UPDATE', [$cartId]);

                $info = $this->resolveSellable($productId, $variantId);

                $existing = $this->fetchOne(
                    'SELECT id, quantity FROM cart_items'
                    . ' WHERE cart_id = ? AND product_id = ? AND product_variant_id <=> ?',
                    [$cartId, $productId, $variantId]
                );

                $newQuantity = $quantity + ($existing === null ? 0 : (int) $existing['quantity']);
                $this->assertQuantity($newQuantity, $info['stock']);

                $unitPrice  = $info['price'];
                $totalPrice = round($unitPrice * $newQuantity, 2);

                if ($existing !== null) {
                    $this->execute(
                        'UPDATE cart_items SET quantity = ?, unit_price = ?, total_price = ? WHERE id = ?',
                        [$newQuantity, $unitPrice, $totalPrice, (int) $existing['id']]
                    );

                    return (int) $existing['id'];
                }

                $count = (int) $this->fetchValue(
                    'SELECT COUNT(*) FROM cart_items WHERE cart_id = ?',
                    [$cartId]
                );

                if ($count >= self::MAX_ITEMS) {
                    throw new InvalidArgumentException(
                        'Sepette en fazla ' . self::MAX_ITEMS . ' farklı ürün olabilir.'
                    );
                }

                $this->execute(
                    'INSERT INTO cart_items (cart_id, product_id, product_variant_id, quantity, unit_price, total_price)'
                    . ' VALUES (?, ?, ?, ?, ?, ?)',
                    [$cartId, $productId, $variantId, $newQuantity, $unitPrice, $totalPrice]
                );

                return (int) $this->db()->lastInsertId();
            }
        );
    }

    /**
     * Kalemin adedini belirtilen değere ayarlar. Adet 0 ise kalem silinir.
     * Kalem kullanıcının kendi aktif sepetinde olmalıdır.
     *
     * @return bool Kalem bulunduysa true
     *
     * @throws InvalidArgumentException Stok kuralı ihlal edilirse
     */
    public function setQuantity(int $userId, int $itemId, int $quantity): bool
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Adet negatif olamaz.');
        }

        return (bool) $this->transaction(
            function () use ($userId, $itemId, $quantity): bool {
                $item = $this->fetchOne(
                    'SELECT i.id, i.product_id, i.product_variant_id'
                    . ' FROM cart_items i'
                    . ' INNER JOIN carts c ON c.id = i.cart_id'
                    . " WHERE i.id = ? AND c.user_id = ? AND c.status = 'active' FOR UPDATE",
                    [$itemId, $userId]
                );

                if ($item === null) {
                    return false;
                }

                if ($quantity === 0) {
                    $this->execute('DELETE FROM cart_items WHERE id = ?', [$itemId]);

                    return true;
                }

                $variantId = $item['product_variant_id'] === null ? null : (int) $item['product_variant_id'];
                $info      = $this->resolveSellable((int) $item['product_id'], $variantId);

                $this->assertQuantity($quantity, $info['stock']);

                $this->execute(
                    'UPDATE cart_items SET quantity = ?, unit_price = ?, total_price = ? WHERE id = ?',
                    [$quantity, $info['price'], round($info['price'] * $quantity, 2), $itemId]
                );

                return true;
            }
        );
    }

    /**
     * Kalemi sepetten çıkarır. Kalem kullanıcının kendi aktif sepetinde olmalıdır.
     *
     * @return bool Kalem silindiyse true
     */
    public function removeItem(int $userId, int $itemId): bool
    {
        return $this->execute(
            'DELETE i FROM cart_items i'
            . ' INNER JOIN carts c ON c.id = i.cart_id'
            . " WHERE i.id = ? AND c.user_id = ? AND c.status = 'active'",
            [$itemId, $userId]
        ) > 0;
    }

    /** Sepetteki tüm kalemleri siler. */
    public function clear(int $cartId): void
    {
        $this->execute('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
    }

    /**
     * Sepet toplamları: ara toplam (KDV dahil, kalem toplamlarının toplamı) ve adet.
     * Fiyatlar sepete eklendiği andaki değerlerdir; sipariş anında yeniden hesaplanır.
     *
     * @return array{subtotal: float, quantity: int}
     */
    public function getTotals(int $cartId): array
    {
        $row = $this->fetchOne(
            'SELECT COALESCE(SUM(total_price), 0) AS subtotal, COALESCE(SUM(quantity), 0) AS quantity'
            . ' FROM cart_items WHERE cart_id = ?',
            [$cartId]
        );

        return [
            'subtotal' => (float) ($row['subtotal'] ?? 0),
            'quantity' => (int) ($row['quantity'] ?? 0),
        ];
    }

    // -------------------------------------------------------------------------
    // Satılabilirlik ve stok denetimi (CartService de kullanır)
    // -------------------------------------------------------------------------

    /**
     * Ürün/varyantın satın alınabilirliğini denetler ve stok ile güncel fiyatı döndürür.
     * Ürün vitrinde görünür olmalı (aktif, silinmemiş, kategori/marka aktif; FR-033).
     *
     * Public'tir: CartService misafir (session) sepetinde aynı kuralları uygular.
     *
     * @return array{stock: int, price: float}
     *
     * @throws InvalidArgumentException Kural ihlal edilirse
     */
    public function resolveSellable(int $productId, ?int $variantId): array
    {
        $product = $this->fetchOne(
            'SELECT p.* FROM products p'
            . ' INNER JOIN categories c ON c.id = p.category_id'
            . ' AND c.is_active = 1 AND c.deleted_at IS NULL'
            . ' LEFT JOIN brands b ON b.id = p.brand_id'
            . ' WHERE p.id = ? AND p.is_active = 1 AND p.deleted_at IS NULL'
            . ' AND (p.brand_id IS NULL OR (b.is_active = 1 AND b.deleted_at IS NULL))',
            [$productId]
        );

        if ($product === null) {
            throw new InvalidArgumentException('Ürün satışta değil.');
        }

        $products    = new Product();
        $hasVariants = $products->hasVariants($productId);

        if ($variantId === null) {
            if ($hasVariants) {
                throw new InvalidArgumentException('Lütfen bir seçenek (varyant) seçin.');
            }

            return [
                'stock' => max(0, (int) $product['stock_quantity']),
                'price' => $products->currentPrice($product),
            ];
        }

        $variants = new ProductVariant();
        $variant  = $variants->findForProduct($variantId, $productId);

        if ($variant === null || (int) $variant['is_active'] !== 1) {
            throw new InvalidArgumentException('Seçilen seçenek satışta değil.');
        }

        return [
            'stock' => max(0, (int) $variant['stock_quantity']),
            'price' => $variants->currentPrice($variant),
        ];
    }

    /**
     * Adedin stok ve üst sınır kurallarına uyduğunu denetler (FR-044).
     *
     * Public'tir: CartService misafir (session) sepetinde aynı kuralı uygular.
     *
     * @throws InvalidArgumentException Kural ihlal edilirse
     */
    public function assertQuantity(int $quantity, int $stock): void
    {
        if ($stock <= 0) {
            throw new InvalidArgumentException('Bu ürün stokta yok.');
        }

        if ($quantity > $stock) {
            throw new InvalidArgumentException('Yetersiz stok. Eklenebilecek en fazla adet: ' . $stock . '.');
        }

        if ($quantity > self::MAX_QUANTITY) {
            throw new InvalidArgumentException('Bir üründen en fazla ' . self::MAX_QUANTITY . ' adet alınabilir.');
        }
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
        throw new BadMethodCallException('Cart::create() kapalıdır. getOrCreateActive() kullanın.');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws BadMethodCallException Her zaman
     */
    public function update(int $id, array $data): bool
    {
        throw new BadMethodCallException('Cart::update() kapalıdır.');
    }

    /**
     * @throws BadMethodCallException Her zaman
     */
    public function delete(int $id): bool
    {
        throw new BadMethodCallException('Cart::delete() kapalıdır. clear() kullanın.');
    }
}