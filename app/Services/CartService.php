<?php

/**
 * Aşıroğlu Yöresel Market - Sepet servisi
 *
 * Misafir (PHP session) ve üye (veritabanı) sepetini tek arayüzde toplar.
 *
 * Kurallar (DECISIONS.md, Misafir Sepeti):
 * - Misafir sepeti yalnızca $_SESSION['cart']['items'] içinde tutulur;
 *   carts/cart_items kaydı açılmaz.
 * - Üyenin sepeti carts/cart_items tablolarındadır (Cart modeli).
 * - Girişte session sepeti üye sepetiyle transaction içinde birleştirilir:
 *   aynı ürün + varyantta adetler toplanır, stok sınırı aşılmaz; sığmayan
 *   kalem eklenmez ve uyarı olarak döndürülür.
 *
 * Kalem anahtarı (key): misafirde "p5" veya "p5v3", üyede cart_items.id.
 *
 * Stok ve satılabilirlik kuralları misafir için de Cart modelindeki
 * resolveSellable() / assertQuantity() ile uygulanır (FR-044).
 *
 * Tüm veritabanı işlemleri model katmanında PDO prepared statement iledir.
 *
 * Gereksinimler: FR-040, FR-041, FR-042, FR-044.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use InvalidArgumentException;

final class CartService
{
    /** Session anahtarı */
    private const SESSION_KEY = 'cart';

    /** Sepette bulunabilecek en fazla farklı kalem sayısı */
    private const MAX_ITEMS = 50;

    /** Misafir kalem anahtarı biçimi: p5 veya p5v3 */
    private const GUEST_KEY_PATTERN = '/^p([1-9]\d{0,9})(?:v([1-9]\d{0,9}))?$/';

    private Cart $cart;

    public function __construct(?Cart $cart = null)
    {
        $this->cart = $cart ?? new Cart();
    }

    // -------------------------------------------------------------------------
    // Okuma
    // -------------------------------------------------------------------------

    /**
     * Sepet özeti: kalemler, ara toplam (KDV dahil) ve toplam adet.
     * Fiyatlar bilgilendirme amaçlıdır; sipariş anında yeniden hesaplanır.
     *
     * @return array{items: list<array<string, mixed>>, subtotal: float, quantity: int}
     */
    public function summary(): array
    {
        $items    = $this->items();
        $subtotal = 0.0;
        $quantity = 0;

        foreach ($items as $item) {
            $subtotal += (float) $item['total_price'];
            $quantity += (int) $item['quantity'];
        }

        return [
            'items'    => $items,
            'subtotal' => round($subtotal, 2),
            'quantity' => $quantity,
        ];
    }

    /** Sepetteki toplam adet (menü rozeti için). */
    public function count(): int
    {
        $userId = Auth::id();

        if ($userId !== null) {
            $existing = $this->cart->findActiveByUser($userId);

            return $existing === null ? 0 : $this->cart->countQuantity((int) $existing['id']);
        }

        $total = 0;

        foreach ($this->readGuestItems() as $item) {
            $total += $item['quantity'];
        }

        return $total;
    }

    /**
     * Sepet kalemleri (ortak biçim).
     *
     * Her kalem: key, product_id, variant_id, name, variant_name, sku, slug,
     * quantity, unit_price, total_price, stock, available.
     *
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        $userId = Auth::id();

        return $userId !== null ? $this->memberItems($userId) : $this->guestItems();
    }

    // -------------------------------------------------------------------------
    // Yazma
    // -------------------------------------------------------------------------

    /**
     * Sepete ürün ekler; aynı ürün + varyant varsa adet artar.
     *
     * @throws InvalidArgumentException Stok/varyant/ürün kuralı ihlal edilirse
     */
    public function add(int $productId, ?int $variantId, int $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Adet sıfırdan büyük olmalıdır.');
        }

        $userId = Auth::id();

        if ($userId !== null) {
            $this->cart->addItem($userId, $productId, $variantId, $quantity);

            return;
        }

        $this->addGuest($productId, $variantId, $quantity);
    }

    /**
     * Kalemin adedini ayarlar; 0 ise kalem silinir.
     *
     * @return bool Kalem bulunduysa true
     *
     * @throws InvalidArgumentException Stok kuralı ihlal edilirse
     */
    public function setQuantity(string $key, int $quantity): bool
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Adet negatif olamaz.');
        }

        $userId = Auth::id();

        if ($userId !== null) {
            $itemId = $this->memberKey($key);

            return $itemId !== null && $this->cart->setQuantity($userId, $itemId, $quantity);
        }

        return $this->setGuestQuantity($key, $quantity);
    }

    /**
     * Kalemi sepetten çıkarır.
     *
     * @return bool Kalem silindiyse true
     */
    public function remove(string $key): bool
    {
        $userId = Auth::id();

        if ($userId !== null) {
            $itemId = $this->memberKey($key);

            return $itemId !== null && $this->cart->removeItem($userId, $itemId);
        }

        $items = $this->readGuestItems();

        foreach ($items as $index => $item) {
            if ($this->guestKey($item['product_id'], $item['product_variant_id']) === $key) {
                unset($items[$index]);
                $this->writeGuestItems(array_values($items));

                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Birleştirme (giriş sonrası)
    // -------------------------------------------------------------------------

    /**
     * Misafir (session) sepetini üyenin veritabanı sepetiyle birleştirir.
     * Giriş başarılı olduktan sonra çağrılmalıdır (FR-041).
     *
     * Aynı ürün + varyantta adetler toplanır. Stok sınırına sığmayan kalem
     * eklenmez; kullanıcıya gösterilecek uyarılar döndürülür.
     *
     * @return list<string> Uyarı mesajları (boşsa sorunsuz birleşti)
     */
    public function mergeGuestCart(int $userId): array
    {
        $guestItems = $this->readGuestItems();

        if ($guestItems === []) {
            return [];
        }

        $warnings = [];

        $this->cart->transaction(function () use ($userId, $guestItems, &$warnings): void {
            foreach ($guestItems as $item) {
                try {
                    $this->cart->addItem(
                        $userId,
                        $item['product_id'],
                        $item['product_variant_id'],
                        $item['quantity']
                    );
                } catch (InvalidArgumentException $e) {
                    $warnings[] = $this->productLabel($item['product_id'], $item['product_variant_id'])
                        . ': ' . $e->getMessage() . ' Bu ürün sepetinize eklenmedi.';
                }
            }
        });

        // Birleştirme tamamlandı: session sepeti temizlenir
        Session::forget(self::SESSION_KEY);

        return $warnings;
    }

    // -------------------------------------------------------------------------
    // Üye sepeti
    // -------------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function memberItems(int $userId): array
    {
        $existing = $this->cart->findActiveByUser($userId);

        if ($existing === null) {
            return [];
        }

        $items = [];

        foreach ($this->cart->getItems((int) $existing['id']) as $row) {
            $stock = max(0, (int) $row['available_stock']);

            $items[] = [
                'key'          => (string) (int) $row['id'],
                'product_id'   => (int) $row['product_id'],
                'variant_id'   => $row['product_variant_id'] === null ? null : (int) $row['product_variant_id'],
                'name'         => (string) $row['product_name'],
                'variant_name' => (string) ($row['variant_name'] ?? ''),
                'sku'          => (string) ($row['variant_sku'] ?? $row['product_sku']),
                'slug'         => (string) $row['product_slug'],
                'quantity'     => (int) $row['quantity'],
                'unit_price'   => (float) $row['unit_price'],
                'total_price'  => (float) $row['total_price'],
                'stock'        => $stock,
                'available'    => $stock >= (int) $row['quantity'] && $stock > 0,
            ];
        }

        return $items;
    }

    /** Üye kalem anahtarını (yalnızca rakam) tamsayıya çevirir; geçersizse null. */
    private function memberKey(string $key): ?int
    {
        return preg_match('/^[1-9]\d{0,9}$/', $key) === 1 ? (int) $key : null;
    }

    // -------------------------------------------------------------------------
    // Misafir (session) sepeti
    // -------------------------------------------------------------------------

    /**
     * @throws InvalidArgumentException Kural ihlal edilirse
     */
    private function addGuest(int $productId, ?int $variantId, int $quantity): void
    {
        // Ürün/varyant satılabilir olmalı; stok ve güncel fiyat burada gelir
        $info  = $this->cart->resolveSellable($productId, $variantId);
        $items = $this->readGuestItems();

        foreach ($items as $index => $item) {
            if ($item['product_id'] === $productId && $item['product_variant_id'] === $variantId) {
                $newQuantity = $item['quantity'] + $quantity;
                $this->cart->assertQuantity($newQuantity, $info['stock']);

                $items[$index]['quantity'] = $newQuantity;
                $this->writeGuestItems($items);

                return;
            }
        }

        if (count($items) >= self::MAX_ITEMS) {
            throw new InvalidArgumentException(
                'Sepette en fazla ' . self::MAX_ITEMS . ' farklı ürün olabilir.'
            );
        }

        $this->cart->assertQuantity($quantity, $info['stock']);

        $items[] = [
            'product_id'         => $productId,
            'product_variant_id' => $variantId,
            'quantity'           => $quantity,
        ];

        $this->writeGuestItems($items);
    }

    /**
     * @throws InvalidArgumentException Stok kuralı ihlal edilirse
     */
    private function setGuestQuantity(string $key, int $quantity): bool
    {
        $items = $this->readGuestItems();

        foreach ($items as $index => $item) {
            if ($this->guestKey($item['product_id'], $item['product_variant_id']) !== $key) {
                continue;
            }

            if ($quantity === 0) {
                unset($items[$index]);
                $this->writeGuestItems(array_values($items));

                return true;
            }

            $info = $this->cart->resolveSellable($item['product_id'], $item['product_variant_id']);
            $this->cart->assertQuantity($quantity, $info['stock']);

            $items[$index]['quantity'] = $quantity;
            $this->writeGuestItems($items);

            return true;
        }

        return false;
    }

    /**
     * Session kalemlerini ürün bilgisiyle zenginleştirir. Silinmiş ürünler atlanır;
     * satıştan kalkan veya stoğu yetmeyenler 'available' = false ile işaretlenir.
     *
     * @return list<array<string, mixed>>
     */
    private function guestItems(): array
    {
        $products = new Product();
        $variants = new ProductVariant();
        $result   = [];

        foreach ($this->readGuestItems() as $item) {
            $product = $products->find($item['product_id']);

            if ($product === null) {
                continue;
            }

            $variant = null;

            if ($item['product_variant_id'] !== null) {
                $variant = $variants->findForProduct($item['product_variant_id'], $item['product_id']);

                if ($variant === null) {
                    continue;
                }
            }

            try {
                $info      = $this->cart->resolveSellable($item['product_id'], $item['product_variant_id']);
                $stock     = $info['stock'];
                $unitPrice = $info['price'];
            } catch (InvalidArgumentException $e) {
                // Ürün satıştan kalkmış: kalem görünür ama satın alınamaz
                $stock     = 0;
                $unitPrice = $variant !== null
                    ? $variants->currentPrice($variant)
                    : $products->currentPrice($product);
            }

            $result[] = [
                'key'          => $this->guestKey($item['product_id'], $item['product_variant_id']),
                'product_id'   => $item['product_id'],
                'variant_id'   => $item['product_variant_id'],
                'name'         => (string) $product['name'],
                'variant_name' => $variant === null ? '' : (string) $variant['variant_name'],
                'sku'          => (string) ($variant['sku'] ?? $product['sku']),
                'slug'         => (string) $product['slug'],
                'quantity'     => $item['quantity'],
                'unit_price'   => $unitPrice,
                'total_price'  => round($unitPrice * $item['quantity'], 2),
                'stock'        => $stock,
                'available'    => $stock > 0 && $stock >= $item['quantity'],
            ];
        }

        return $result;
    }

    /**
     * Session'daki kalemleri okur ve doğrular; bozuk kayıtlar atılır.
     *
     * @return list<array{product_id: int, product_variant_id: int|null, quantity: int}>
     */
    private function readGuestItems(): array
    {
        $cart = Session::get(self::SESSION_KEY);
        $raw  = is_array($cart) && isset($cart['items']) && is_array($cart['items']) ? $cart['items'] : [];

        $items = [];

        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $productId = (int) ($item['product_id'] ?? 0);
            $variantId = isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : 0;
            $quantity  = (int) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $items[] = [
                'product_id'         => $productId,
                'product_variant_id' => $variantId > 0 ? $variantId : null,
                'quantity'           => $quantity,
            ];
        }

        return $items;
    }

    /**
     * @param list<array{product_id: int, product_variant_id: int|null, quantity: int}> $items
     */
    private function writeGuestItems(array $items): void
    {
        Session::set(self::SESSION_KEY, ['items' => array_values($items)]);
    }

    /** Misafir kalem anahtarını üretir: p5 veya p5v3. */
    private function guestKey(int $productId, ?int $variantId): string
    {
        return 'p' . $productId . ($variantId === null ? '' : 'v' . $variantId);
    }

    /** Uyarı mesajlarında gösterilecek ürün (ve varyant) adı. */
    private function productLabel(int $productId, ?int $variantId): string
    {
        $product = (new Product())->find($productId);
        $label   = $product === null ? 'Ürün' : (string) $product['name'];

        if ($variantId !== null) {
            $variant = (new ProductVariant())->findForProduct($variantId, $productId);

            if ($variant !== null) {
                $label .= ' (' . (string) $variant['variant_name'] . ')';
            }
        }

        return $label;
    }
}