-- =============================================================================
-- Migration 017: banners, pages, newsletter_subscribers, contact_messages
-- Aşıroğlu Yöresel Market - İçerik tabloları
-- Bağımlılık: yok (bu tabloların hiçbirinde foreign key yoktur)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Bannerlar (ana sayfa slider'ı, kampanya alanları vb.)
--
--    İş kuralları (Banner modelinde uygulanır):
--    - Yalnızca güvenli dosya yolu saklanır (public/uploads/banners/).
--    - link_url yalnızca site içi yol veya http/https adresi olabilir
--      ("javascript:" gibi şemalar Validator 'url' kuralıyla reddedilir).
--    - Yayın aralığı starts_at / ends_at ile belirlenir; NULL = kısıtsız.
-- -----------------------------------------------------------------------------
CREATE TABLE banners (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NULL,

    -- Yalnızca dosya yolu saklanır (public/uploads/banners/)
    image_path VARCHAR(255) NOT NULL,
    link_url VARCHAR(500) NULL,

    position ENUM('home_slider','home_middle','category_top','sidebar') NOT NULL DEFAULT 'home_slider',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,

    starts_at DATETIME NULL,
    ends_at DATETIME NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    -- Vitrinde konuma göre aktif banner listesi için
    INDEX idx_banners_position_active (position, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Sayfalar (Hakkımızda, KVKK, Mesafeli Satış Sözleşmesi, İade Politikası vb.)
--
--    İçerik yalnızca yetkili yöneticiler tarafından girilir. Yine de içerik
--    HTML barındırdığı için çıktıda izin verilen etiket listesiyle
--    temizlenerek (sanitize) gösterilmelidir.
-- -----------------------------------------------------------------------------
CREATE TABLE pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,

    -- SEO uyumlu benzersiz URL parçası (örn. "kvkk-aydinlatma-metni")
    slug VARCHAR(191) NOT NULL UNIQUE,
    content LONGTEXT NULL,

    meta_title VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. E-bülten aboneleri
--
--    İş kuralları (NewsletterSubscriber modelinde uygulanır):
--    - E-posta benzersizdir; aynı adres tekrar kaydedilmez.
--    - Pazarlama e-postası göndermeden önce abonenin onayı alınır
--      (pending -> subscribed). Aboneliği bırakma bağlantısı token ile çalışır.
--    - token tahmin edilemez, rastgele üretilir (random_bytes).
-- -----------------------------------------------------------------------------
CREATE TABLE newsletter_subscribers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(191) NOT NULL UNIQUE,
    name VARCHAR(150) NULL,

    status ENUM('subscribed','unsubscribed','pending') NOT NULL DEFAULT 'subscribed',

    -- Onay ve abonelikten çıkış bağlantıları için rastgele token
    token VARCHAR(100) NULL,

    subscribed_at DATETIME NULL,
    unsubscribed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. İletişim formu mesajları
--
--    Mesaj içeriği kullanıcı girdisidir; admin panelinde çıktı mutlaka
--    escape edilir (XSS). Kişisel veri (ad, e-posta, telefon, IP) KVKK
--    kapsamındadır; saklama süresi ve silme politikası yasal metinlerle uyumlu
--    belirlenir.
-- -----------------------------------------------------------------------------
CREATE TABLE contact_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(191) NOT NULL,
    phone VARCHAR(20) NULL,
    subject VARCHAR(255) NULL,
    message TEXT NOT NULL,

    status ENUM('new','read','replied','closed') NOT NULL DEFAULT 'new',

    -- IPv6 dahil en fazla 45 karakter
    ip_address VARCHAR(45) NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_contact_messages_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;