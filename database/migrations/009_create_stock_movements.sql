-- =============================================================================
-- Migration 009: stock_movements
-- Aşıroğlu Yöresel Market - Stok hareketleri (yalnızca eklenir, düzenlenmez)
-- Bağımlılık: products (005), product_variants (007), users (001)
-- =============================================================================

CREATE TABLE stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,

    -- Varyantlı üründe hareketin hangi varyanta ait olduğu
    product_variant_id INT UNSIGNED NULL,

    movement_type ENUM('incoming','outgoing','return','correction','reservation') NOT NULL,

    -- Pozitif miktar; yön hareket türünden (correction'da önce/sonra farkından) anlaşılır
    quantity INT NOT NULL,
    previous_quantity INT NOT NULL DEFAULT 0,
    new_quantity INT NOT NULL DEFAULT 0,

    -- Hareketin kaynağı (örn. 'order' + sipariş ID). Birden çok tabloya
    -- işaret edebildiği için bilinçli olarak FK yoktur.
    reference_type VARCHAR(50) NULL,
    reference_id INT UNSIGNED NULL,

    note VARCHAR(500) NULL,

    -- Hareketi yapan kullanıcı (kullanıcı silinirse kayıt korunur)
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Ürün fiziksel silinirse hareketleri de silinir
    CONSTRAINT fk_stock_movements_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,

    -- Varyant silinirse hareket kalır, varyant bilgisi NULL olur
    CONSTRAINT fk_stock_movements_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,

    CONSTRAINT fk_stock_movements_user
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_stock_movements_product (product_id),
    INDEX idx_stock_movements_variant (product_variant_id),
    INDEX idx_stock_movements_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;