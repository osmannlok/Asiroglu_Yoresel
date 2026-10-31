-- =============================================================================
-- Migration 012: orders, order_items, order_status_history
--                + coupon_usages.order_id foreign key
-- Aşıroğlu Yöresel Market - Siparişler, sipariş kalemleri, durum geçmişi
-- Bağımlılık: users (001), products (005), product_variants (007),
--             coupons (010), coupon_usages (010)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Sipariş başlığı
--
--    İş kuralları (Order modelinde uygulanır):
--    - order_number benzersiz ve okunabilir üretilir: ASY-20261030-000123
--    - Sipariş oluşturma, stok düşürme, kupon kullanım kaydı ve sepetin
--      'converted' yapılması aynı transaction içinde yapılır.
--    - Ödeme başarısız olursa sipariş silinmez, 'payment_failed' olur.
--    - İptalde stok geri eklenir.
--    - Her durum değişikliği order_status_history tablosuna yazılır.
-- -----------------------------------------------------------------------------
CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Okunabilir benzersiz sipariş numarası
    order_number VARCHAR(30) NOT NULL UNIQUE,

    -- Misafir siparişinde veya kullanıcı silindiğinde NULL
    user_id INT UNSIGNED NULL,

    -- Siparişe uygulanan kupon; yoksa NULL
    coupon_id INT UNSIGNED NULL,

    status ENUM(
        'pending',
        'awaiting_payment',
        'paid',
        'preparing',
        'shipped',
        'delivered',
        'cancelled',
        'payment_failed',
        'returned'
    ) NOT NULL DEFAULT 'pending',

    payment_status ENUM('unpaid','paid','failed','refunded','partially_refunded') NOT NULL DEFAULT 'unpaid',

    -- Tutarlar TRY'dir. Sipariş anında hesaplanıp saklanır, sonradan yeniden
    -- hesaplanmaz.
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    vat_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    shipping_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'TRY',

    -- Adres defterindeki kayıt sonradan değişse de sipariş adresi sabit kalsın
    -- diye anlık kopya (JSON metni) olarak saklanır.
    shipping_address_snapshot TEXT NOT NULL,
    billing_address_snapshot TEXT NULL,

    customer_note VARCHAR(1000) NULL,
    admin_note VARCHAR(1000) NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    -- Soft delete: NULL = silinmemiş
    deleted_at DATETIME NULL,

    -- Kullanıcı silinirse sipariş korunur, user_id NULL olur
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,

    -- Kupon silinirse sipariş korunur, coupon_id NULL olur
    CONSTRAINT fk_orders_coupon
        FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL,

    -- order_number UNIQUE olduğu için ayrıca indeks yazılmadı
    INDEX idx_orders_user (user_id),
    INDEX idx_orders_status (status),
    INDEX idx_orders_payment_status (payment_status),
    INDEX idx_orders_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Sipariş kalemleri
--    Ürün adı, SKU ve fiyat sipariş anındaki haliyle saklanır (anlık kopya).
--    Bu yüzden ürün veya varyant silinse bile kalem korunur (SET NULL).
-- -----------------------------------------------------------------------------
CREATE TABLE order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,

    -- Ürün/varyant silinirse NULL olur; kimlik bilgisi aşağıdaki kopyada kalır
    product_id INT UNSIGNED NULL,
    product_variant_id INT UNSIGNED NULL,

    -- Sipariş anındaki ürün bilgisi (anlık kopya)
    product_name VARCHAR(255) NOT NULL,
    product_sku VARCHAR(64) NULL,

    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    quantity INT NOT NULL DEFAULT 1,
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Sipariş fiziksel silinirse kalemleri de silinir
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,

    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,

    CONSTRAINT fk_order_items_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,

    INDEX idx_order_items_order (order_id),
    INDEX idx_order_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Sipariş durum geçmişi (yalnızca eklenir, düzenlenmez)
--    old_status ilk kayıtta NULL'dır. Durumlar VARCHAR'dır; geçerli değerler
--    orders.status ENUM'u ile Order modelinde denetlenir.
-- -----------------------------------------------------------------------------
CREATE TABLE order_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NOT NULL,

    -- Değişikliği yapan kullanıcı; sistem işlemlerinde (ödeme callback'i) NULL
    changed_by INT UNSIGNED NULL,
    note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_order_status_history_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,

    -- Kullanıcı silinirse kayıt korunur
    CONSTRAINT fk_order_status_history_user
        FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_order_status_history_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. coupon_usages.order_id foreign key (010'da orders olmadığı için eklenmemişti)
--    Sipariş silinirse kullanım kaydı korunur, order_id NULL olur.
-- -----------------------------------------------------------------------------
ALTER TABLE coupon_usages
    ADD CONSTRAINT fk_coupon_usages_order
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL;