-- =============================================================================
-- Migration 006: product_images
-- Aşıroğlu Yöresel Market - Ürün görselleri
-- Bağımlılık: products (005)
-- =============================================================================

CREATE TABLE product_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,

    -- Yalnızca güvenli dosya yolu saklanır (public/uploads/products/)
    image_path VARCHAR(255) NOT NULL,

    -- SEO: img etiketindeki alt metni; boşsa ürün adı kullanılır
    alt_text VARCHAR(255) NULL,

    sort_order INT NOT NULL DEFAULT 0,

    -- "Üründe tek ana görsel" kuralı ProductImage modeli ve Uploader'da uygulanır
    is_main TINYINT(1) NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_product_images_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_images_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;