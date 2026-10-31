-- =============================================================================
-- Migration 003: categories
-- Aşıroğlu Yöresel Market - Ürün kategorileri (alt kategori destekli)
-- Bağımlılık: yok (yalnızca kendine bağlanır)
-- =============================================================================

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- NULL = ana kategori, dolu = alt kategori
    parent_id INT UNSIGNED NULL,

    name VARCHAR(150) NOT NULL,

    -- SEO uyumlu benzersiz URL parçası (örn. "recel")
    slug VARCHAR(191) NOT NULL UNIQUE,

    description TEXT NULL,

    -- Yalnızca dosya yolu saklanır (public/uploads/categories/)
    image VARCHAR(255) NULL,
    icon VARCHAR(100) NULL,

    meta_title VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,

    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    -- Soft delete: NULL = silinmemiş
    deleted_at DATETIME NULL,

    -- Üst kategori fiziksel silinirse alt kategoriler ana kategoriye dönüşür
    CONSTRAINT fk_categories_parent
        FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_categories_parent (parent_id),
    INDEX idx_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;