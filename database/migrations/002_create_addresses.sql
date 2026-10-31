-- =============================================================================
-- Migration 002: addresses
-- Aşıroğlu Yöresel Market - Kullanıcı adres defteri
-- Bağımlılık: users (001)
-- =============================================================================

CREATE TABLE addresses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,

    -- Teslimat, fatura veya her ikisi için kullanılabilir
    address_type ENUM('shipping','billing','both') NOT NULL DEFAULT 'shipping',

    -- Adres başlığı: "Ev", "İş" gibi
    title VARCHAR(100) NOT NULL,

    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,

    country VARCHAR(100) NOT NULL DEFAULT 'Türkiye',
    city VARCHAR(100) NOT NULL,
    district VARCHAR(100) NOT NULL,
    neighborhood VARCHAR(150) NULL,
    postal_code VARCHAR(10) NULL,
    address_line VARCHAR(500) NOT NULL,

    -- Kullanıcı başına tek varsayılan adres kuralı Address modelinde uygulanır
    is_default TINYINT(1) NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    -- Soft delete: NULL = silinmemiş
    deleted_at DATETIME NULL,

    CONSTRAINT fk_addresses_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_addresses_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;