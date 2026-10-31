-- =============================================================================
-- Migration 004: brands
-- Aşıroğlu Yöresel Market - Markalar / üreticiler
-- Bağımlılık: yok
-- =============================================================================

CREATE TABLE brands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(150) NOT NULL,

    -- SEO uyumlu benzersiz URL parçası
    slug VARCHAR(191) NOT NULL UNIQUE,

    description TEXT NULL,

    -- Yalnızca dosya yolu saklanır (public/uploads/ altında)
    logo VARCHAR(255) NULL,

    -- Yalnızca http/https adresi kabul edilir (Validator 'url' kuralı)
    website VARCHAR(255) NULL,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    -- Soft delete: NULL = silinmemiş
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;