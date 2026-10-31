-- =============================================================================
-- Migration 011: carts, cart_items
-- Aşıroğlu Yöresel Market - Sepetler ve sepet kalemleri
-- Bağımlılık: users (001), products (005), product_variants (007), coupons (010)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Sepet başlığı
--    Üye sepeti: user_id dolu. Misafir sepeti: session_id dolu.
--
--    İş kuralları (Cart modelinde uygulanır):
--    - "user_id veya session_id en az biri dolu olmalı" kuralı SQL CHECK ile
--      değil, Cart modelinde transaction içinde zorlanır.
--    - Giriş yapılınca misafir sepeti üye sepetiyle birleştirilir.
-- -----------------------------------------------------------------------------
CREATE TABLE carts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Üye sepeti için; misafirde NULL
    user_id INT UNSIGNED NULL,

    -- Misafir sepeti için oturum kimliği; üyede NULL olabilir
    session_id VARCHAR(128) NULL,

    -- Sepete uygulanan kupon; yoksa NULL
    coupon_id INT UNSIGNED NULL,

    -- active: açık sepet, converted: siparişe dönüştü, abandoned: terk edildi
    status ENUM('active','converted','abandoned') NOT NULL DEFAULT 'active',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    -- Kullanıcı fiziksel silinirse sepeti de silinir
    CONSTRAINT fk_carts_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    -- Kupon fiziksel silinirse sepet kalır, kupon bilgisi NULL olur
    CONSTRAINT fk_carts_coupon
        FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL,

    INDEX idx_carts_user (user_id),
    INDEX idx_carts_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Sepet kalemleri
--
--    İş kuralları (Cart modelinde uygulanır):
--    - Aynı ürün + aynı varyant tekrar eklenirse yeni satır açılmaz,
--      mevcut satırın adedi artırılır.
--    - Fiyat ve stok, sipariş oluşturma anında yeniden kontrol edilir.
--
--    Tekrar engelleme: product_variant_id NULL olduğunda UNIQUE index tekrarı
--    engellemez (MySQL'de NULL değerler birbirinden farklı sayılır). Bu yüzden
--    variant_key adlı üretilmiş sütun NULL'ı 0'a çevirir ve UNIQUE index
--    bu sütun üzerinden kurulur.
--
--    NOT: variant_key VIRTUAL'dır, STORED değildir. MySQL, STORED generated
--    column'ın dayandığı sütunda (product_variant_id) CASCADE kullanılmasına
--    izin vermez (hata 3104). VIRTUAL sütunda bu kısıt yoktur ve InnoDB
--    üzerinde UNIQUE index desteklenir.
-- -----------------------------------------------------------------------------
CREATE TABLE cart_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,

    -- Varyantsız üründe NULL
    product_variant_id INT UNSIGNED NULL,

    -- Tekrar kontrolü için üretilmiş sütun: NULL varyant => 0
    variant_key INT UNSIGNED GENERATED ALWAYS AS (IFNULL(product_variant_id, 0)) VIRTUAL,

    quantity INT NOT NULL DEFAULT 1,

    -- Fiyatlar TRY ve KDV dahildir. Sepete eklendiği andaki fiyattır;
    -- sipariş oluşturulurken güncel fiyatla yeniden kontrol edilir.
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    -- Sepet silinirse kalemleri de silinir
    CONSTRAINT fk_cart_items_cart
        FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,

    -- Ürün fiziksel silinirse sepet kalemi de silinir
    CONSTRAINT fk_cart_items_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,

    -- Varyant fiziksel silinirse kalem de silinir (SET NULL kalemi varyantsız
    -- ürüne çevirir ve fiyat/stok mantığını bozar)
    CONSTRAINT fk_cart_items_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,

    -- Aynı sepette aynı ürün + aynı varyant tekrar edemez
    UNIQUE KEY uq_cart_item_product (cart_id, product_id, variant_key),

    INDEX idx_cart_items_cart (cart_id),
    INDEX idx_cart_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;