-- =============================================================================
-- Migration 010: coupons, coupon_usages
-- Aşıroğlu Yöresel Market - Kupon tanımları ve kupon kullanım kayıtları
-- Bağımlılık: users (001)
-- Not: coupon_usages.order_id için FK, orders migration'ında eklenecek.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Kupon tanımları
--    İş kuralları (süre, limit, minimum tutar vb.) Coupon modelinde uygulanır;
--    tablo yalnızca verileri saklar.
-- -----------------------------------------------------------------------------
CREATE TABLE coupons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Benzersiz kupon kodu (örn. HOSGELDIN10)
    code VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(500) NULL,

    -- percentage: yüzde indirim, fixed_amount: sabit tutar (TRY),
    -- free_shipping: ücretsiz kargo (discount_value kullanılmaz)
    discount_type ENUM('percentage','fixed_amount','free_shipping') NOT NULL DEFAULT 'percentage',
    discount_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    -- Kuponun geçerli olması için gereken minimum sepet tutarı
    min_order_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    -- Yüzdesel kuponlarda uygulanabilecek en yüksek indirim; NULL = sınırsız
    max_discount_amount DECIMAL(12,2) NULL,

    -- Toplam kullanım limiti; NULL = sınırsız
    usage_limit INT NULL,

    -- Kullanıcı başına kullanım limiti; NULL = sınırsız
    usage_limit_per_user INT NULL,

    -- Toplam kullanım sayacı (artırma işlemi sipariş transaction'ında yapılır)
    used_count INT UNSIGNED NOT NULL DEFAULT 0,

    -- Geçerlilik aralığı; NULL = kısıtsız
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,

    -- Pasif kupon kullanılamaz
    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_coupons_code (code),
    INDEX idx_coupons_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Kupon kullanım kayıtları
--    Her başarılı siparişte bir kayıt eklenir; limit kontrolleri bu tabloya
--    bakılarak yapılır.
-- -----------------------------------------------------------------------------
CREATE TABLE coupon_usages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coupon_id INT UNSIGNED NOT NULL,

    -- Misafir siparişlerinde veya kullanıcı silindiğinde NULL olabilir
    user_id INT UNSIGNED NULL,

    -- Kuponun kullanıldığı sipariş.
    -- DİKKAT: orders tablosu henüz olmadığı için bu sütunda FK YOKTUR.
    -- orders migration'ında şu komutla eklenecek:
    --   ALTER TABLE coupon_usages
    --     ADD CONSTRAINT fk_coupon_usages_order
    --     FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL;
    order_id INT UNSIGNED NULL,

    -- Siparişe uygulanan gerçek indirim tutarı
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Kupon fiziksel silinirse kullanım kayıtları da silinir
    CONSTRAINT fk_coupon_usages_coupon
        FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,

    -- Kullanıcı silinirse kayıt korunur, user_id NULL olur
    CONSTRAINT fk_coupon_usages_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_coupon_usages_coupon (coupon_id),
    INDEX idx_coupon_usages_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;