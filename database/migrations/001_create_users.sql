-- =============================================================================
-- Migration 001: users
-- Aşıroğlu Yöresel Market - Kullanıcılar (müşteri, yönetici, personel)
-- Bağımlılık: yok
-- =============================================================================

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Rol: müşteri, yönetici veya personel
    user_type ENUM('customer','admin','staff') NOT NULL DEFAULT 'customer',

    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,

    -- 191 karakter: utf8mb4 ile UNIQUE indeks uzunluk sınırına uygun
    email VARCHAR(191) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,

    -- Yalnızca password_hash() çıktısı saklanır, düz metin şifre asla
    password_hash VARCHAR(255) NOT NULL,

    status ENUM('active','passive','banned') NOT NULL DEFAULT 'active',

    -- Doğrulama zorunlu değil; NULL = doğrulanmamış
    email_verified_at DATETIME NULL,
    phone_verified_at DATETIME NULL,

    -- "Beni hatırla" için token HASH'i saklanacak (token'ın kendisi değil)
    remember_token VARCHAR(100) NULL,

    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    -- Soft delete: NULL = silinmemiş
    deleted_at DATETIME NULL,

    INDEX idx_users_email (email),
    INDEX idx_users_status (status),
    INDEX idx_users_type (user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
