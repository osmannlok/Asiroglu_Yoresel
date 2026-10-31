-- =============================================================================
-- Migration 007: product_variants
-- Aşıroğlu Yöresel Market - Ürün varyantları (500 Gr, 1 Kg, 2 Kg vb.)
-- Bağımlılık: products (005)
-- =============================================================================

CREATE TABLE product_variants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,

    -- Görünen ad: "500 Gr", "1 Kg" gibi
    variant_name VARCHAR(150) NOT NULL,

    -- Varyantın benzersiz SKU'su.
    -- Ürün SKU'su ile çakışma kontrolü modelde yapılır (farklı tablolar).
    sku VARCHAR(64) NOT NULL UNIQUE,
    barcode VARCHAR(64) NULL,

    -- Fiyatlar TRY ve KDV dahildir; KDV oranı products.vat_rate'ten gelir
    price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_price DECIMAL(12,2) NULL,

    -- Varyantı olan üründe GEÇERLİ stok budur (kural Product modelinde zorlanır)
    stock_quantity INT NOT NULL DEFAULT 0,

    weight DECIMAL(10,2) NULL,

    -- Satıştan kaldırmak için is_active = 0 kullanılır
    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    -- Ürün fiziksel silinirse varyantları da silinir
    CONSTRAINT fk_product_variants_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_variants_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;