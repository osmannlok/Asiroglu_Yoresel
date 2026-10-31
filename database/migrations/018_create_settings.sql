-- =============================================================================
-- Migration 018: settings, activity_logs
-- Aşıroğlu Yöresel Market - Site ayarları ve yönetici/sistem işlem günlüğü
-- Bağımlılık: users (001)
-- Not: Bu, planlanan 31 uygulama tablosunun son migration'ıdır.
--      schema_migrations tablosu uygulama tablosu değildir; database/migrate.php
--      tarafından oluşturulur.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Site ayarları (anahtar - değer)
--
--    Örnekler: site adı, iletişim bilgileri, sosyal medya adresleri, varsayılan
--    KDV oranı, havale/EFT banka bilgileri.
--
--    GİZLİLİK KURALI: API anahtarı, secret, şifre gibi hassas değerler bu
--    tabloya YAZILMAZ; bunlar yalnızca .env (ve config/payment.php) içinde
--    tutulur.
--
--    Kargo ücreti ve ücretsiz kargo eşiği shipping_methods tablosundadır;
--    ayarlar tablosunda tekrar edilmez.
-- -----------------------------------------------------------------------------
CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Benzersiz ayar anahtarı (örn. 'site_name'); UNIQUE olduğu için
    -- ayrıca indeks yazılmadı
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,

    -- Yönetim panelinde gruplamak için (örn. 'general', 'contact', 'social')
    setting_group VARCHAR(100) NOT NULL DEFAULT 'general',

    -- 1 ise uygulama başlarken toplu yüklenir (Setting modeli önbelleğe alır)
    is_autoload TINYINT(1) NOT NULL DEFAULT 1,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_settings_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. İşlem günlüğü (yalnızca eklenir, düzenlenmez)
--
--    Yönetici ve sistem işlemlerinin denetim kaydıdır (ürün/sipariş/stok
--    değişiklikleri, giriş denemeleri vb.). Ödeme günlükleri ayrıdır:
--    payment_logs.
--
--    GİZLİLİK KURALI: description alanına şifre, kart bilgisi, API anahtarı veya
--    token yazılmaz.
--
--    entity_type + entity_id birden çok tabloya işaret edebildiği için
--    entity_id üzerinde bilinçli olarak FK yoktur.
-- -----------------------------------------------------------------------------
CREATE TABLE activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- İşlemi yapan kullanıcı; sistem işlemlerinde veya kullanıcı silinince NULL
    user_id INT UNSIGNED NULL,

    -- İşlem kodu (örn. 'product.update', 'order.status_change', 'admin.login')
    action VARCHAR(100) NOT NULL,

    -- Etkilenen kayıt: tür (örn. 'order') ve ID'si
    entity_type VARCHAR(100) NULL,
    entity_id INT UNSIGNED NULL,

    description VARCHAR(1000) NULL,

    -- IPv6 dahil en fazla 45 karakter
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Kullanıcı silinirse kayıt korunur, user_id NULL olur
    CONSTRAINT fk_activity_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_activity_logs_user (user_id),
    INDEX idx_activity_logs_action (action),
    INDEX idx_activity_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;