-- =============================================================================
-- Migration 016: reviews, wishlists
-- Aşıroğlu Yöresel Market - Ürün yorumları ve favori listesi
-- Bağımlılık: products (005), users (001), orders (012)
-- Not: Yorum ve favori arayüzleri MVP kapsamı dışındadır; tablolar planlanan
--      31 uygulama tablosunun parçası olduğu için şimdiden oluşturulur.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Ürün yorumları
--
--    İş kuralları (Review modeli ve servisinde uygulanır):
--    - Yorumlar admin onayından sonra yayınlanır (is_approved = 0 -> bekliyor).
--    - Yalnızca ürünü sipariş etmiş kullanıcılar yorum yapabilir; yorum
--      yapılan sipariş order_id ile bağlanır.
--    - rating 1-5 aralığında olmalıdır. Aralık SQL CHECK ile değil, Validator
--      ve Review modelinde denetlenir.
--    - Yorum metni kullanıcı girdisidir; çıktıda mutlaka escape edilir (XSS).
-- -----------------------------------------------------------------------------
CREATE TABLE reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Ürün fiziksel silinirse yorumları da silinir
    product_id INT UNSIGNED NOT NULL,

    -- Kullanıcı silinirse yorum korunur, user_id NULL olur
    user_id INT UNSIGNED NULL,

    -- Yorumun dayandığı sipariş; sipariş silinirse NULL olur
    order_id INT UNSIGNED NULL,

    -- 1-5 arası puan (aralık Validator/Review modelinde denetlenir)
    rating TINYINT UNSIGNED NOT NULL DEFAULT 5,

    title VARCHAR(255) NULL,
    comment TEXT NULL,

    -- 0 = onay bekliyor (yayınlanmaz), 1 = yayında
    is_approved TINYINT(1) NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_reviews_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,

    CONSTRAINT fk_reviews_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,

    CONSTRAINT fk_reviews_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,

    INDEX idx_reviews_product (product_id),
    INDEX idx_reviews_user (user_id),
    INDEX idx_reviews_approved (is_approved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Favori listesi
--    Kullanıcı başına aynı ürün yalnızca bir kez favoriye eklenebilir.
-- -----------------------------------------------------------------------------
CREATE TABLE wishlists (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Kullanıcı fiziksel silinirse favorileri de silinir
    user_id INT UNSIGNED NOT NULL,

    -- Ürün fiziksel silinirse favori kaydı da silinir
    product_id INT UNSIGNED NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_wishlists_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    CONSTRAINT fk_wishlists_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,

    -- Aynı kullanıcı aynı ürünü iki kez ekleyemez
    UNIQUE KEY uq_wishlist_user_product (user_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;