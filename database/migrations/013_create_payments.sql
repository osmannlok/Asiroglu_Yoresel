-- =============================================================================
-- Migration 013: payments, payment_logs
-- Aşıroğlu Yöresel Market - Ödeme kayıtları ve ödeme günlükleri
-- Bağımlılık: orders (012)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Ödemeler
--
--    İş kuralları (Payment modeli ve ödeme servisinde uygulanır):
--    - payments.order_id UNIQUE DEĞİLDİR: bir sipariş için birden fazla ödeme
--      denemesi olabilir (başarısız deneme sonrası yeniden deneme, kısmi iade vb.).
--    - Kart numarası, CVV ve son kullanma tarihi ASLA veritabanına yazılmaz.
--      Yalnızca ödeme sağlayıcısının döndürdüğü maskeli kart bilgisi
--      (örn. "5406 12** **** 3456") card_mask alanında saklanabilir.
--    - Ödeme callback'lerinde imza/hash doğrulaması zorunludur.
--    - Başarılı ödemede: payments.status = 'completed', orders.status = 'paid',
--      orders.payment_status = 'paid'.
--    - Başarısız ödemede: payments.status = 'failed', orders.status =
--      'payment_failed', orders.payment_status = 'failed'.
--    - Ödeme durumu değişikliği ve sipariş durumu güncellemesi aynı
--      transaction içinde yapılır.
-- -----------------------------------------------------------------------------
CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,

    payment_method ENUM('credit_card','bank_transfer','cash_on_delivery','wallet') NOT NULL DEFAULT 'credit_card',

    -- Ödeme sağlayıcısı adı (örn. 'paytr', 'iyzico'); havale ve kapıda ödemede NULL
    payment_provider VARCHAR(50) NULL,

    -- Sağlayıcının işlem ve referans numaraları
    provider_transaction_id VARCHAR(191) NULL,
    provider_reference VARCHAR(191) NULL,

    status ENUM('pending','completed','failed','cancelled','refunded','partially_refunded') NOT NULL DEFAULT 'pending',

    -- Tutar TRY'dir ve KDV dahildir
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'TRY',
    installment_count TINYINT UNSIGNED NOT NULL DEFAULT 1,

    -- Yalnızca maskeli kart bilgisi; tam kart numarası asla saklanmaz
    card_mask VARCHAR(30) NULL,

    -- Kullanıcıya gösterilebilir kısa hata özeti; teknik ayrıntı payment_logs'ta
    error_message VARCHAR(500) NULL,

    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    -- Sipariş fiziksel silinirse ödeme kayıtları da silinir
    CONSTRAINT fk_payments_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,

    INDEX idx_payments_order (order_id),
    INDEX idx_payments_status (status),
    INDEX idx_payments_provider_transaction (provider_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Ödeme günlükleri (yalnızca eklenir, düzenlenmez)
--
--    Her ödeme isteği, yanıtı ve callback'i kaydedilir. GİZLİLİK KURALI:
--    request_data ve response_data alanlarına yazılmadan önce hassas veriler
--    maskelenir; kart numarası, CVV, son kullanma tarihi, API anahtarı ve
--    secret değerleri bu alanlara ASLA yazılmaz.
--    payment_id NULL olabilir: ödeme kaydı oluşmadan önceki hatalar ve imzası
--    geçersiz callback'ler de loglanabilsin diye.
-- -----------------------------------------------------------------------------
CREATE TABLE payment_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Ödeme kaydı silinirse günlük korunur, payment_id NULL olur
    payment_id INT UNSIGNED NULL,

    -- Sipariş fiziksel silinirse günlükleri de silinir
    order_id INT UNSIGNED NULL,

    provider VARCHAR(50) NULL,

    -- Olay türü (örn. 'init', 'callback', 'callback_invalid_signature', 'refund')
    event_type VARCHAR(100) NOT NULL,

    -- Maskelenmiş istek ve yanıt içeriği
    request_data TEXT NULL,
    response_data TEXT NULL,

    -- IPv6 dahil en fazla 45 karakter
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_payment_logs_payment
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,

    CONSTRAINT fk_payment_logs_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,

    INDEX idx_payment_logs_payment (payment_id),
    INDEX idx_payment_logs_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;