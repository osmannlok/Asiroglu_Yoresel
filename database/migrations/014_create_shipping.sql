-- =============================================================================
-- Migration 014: shipping_methods, shipments
-- Aşıroğlu Yöresel Market - Kargo yöntemleri ve sipariş gönderimleri
-- Bağımlılık: orders (012)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Kargo yöntemleri (örn. Standart Kargo, Hızlı Kargo, Yerel Teslimat)
--
--    İş kuralları (ShippingMethod modeli ve sipariş servisinde uygulanır):
--    - Kargo ücreti ve ücretsiz kargo eşiği yönetim panelinden tanımlanır.
--    - Sipariş tutarı free_shipping_limit değerine ulaşırsa kargo ücretsiz olur;
--      NULL = ücretsiz kargo eşiği yok.
--    - Sipariş oluşturulurken hesaplanan kargo tutarı orders.shipping_total
--      alanına anlık olarak yazılır; yöntemin fiyatı sonradan değişse de
--      geçmiş siparişler etkilenmez.
--    - Satıştan kaldırmak için is_active = 0 kullanılır.
-- -----------------------------------------------------------------------------
CREATE TABLE shipping_methods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,

    -- Programatik benzersiz kod (örn. 'standard', 'express')
    code VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(500) NULL,

    -- Kargo ücreti TRY ve KDV dahildir
    price DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    -- Bu tutar ve üzerindeki siparişlerde kargo ücretsiz; NULL = eşik yok
    free_shipping_limit DECIMAL(12,2) NULL,

    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Gönderimler
--
--    Kargo takibi MANUELDİR: kargo firması, takip numarası ve kargo tarihi
--    yönetici tarafından girilir (otomatik kargo API entegrasyonu kapsam dışı).
--
--    İş kuralları (Shipment modeli ve sipariş servisinde uygulanır):
--    - shipments.order_id UNIQUE DEĞİLDİR: bir siparişin parçalı gönderimi
--      olabilir.
--    - Takip numarası girilip gönderim 'handed_to_cargo' olduğunda müşteriye
--      bilgilendirme e-postası gönderilir ve shipped_at doldurulur.
--    - Gönderim 'delivered' olduğunda delivered_at doldurulur ve sipariş
--      durumu 'delivered' olur (order_status_history'ye yazılır).
-- -----------------------------------------------------------------------------
CREATE TABLE shipments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,

    -- Yöntem silinirse gönderim kalır, shipping_method_id NULL olur
    shipping_method_id INT UNSIGNED NULL,

    -- Yönetici tarafından girilen kargo firması adı (örn. Yurtiçi, Aras, MNG)
    carrier_name VARCHAR(100) NULL,
    tracking_number VARCHAR(100) NULL,

    -- Yalnızca http/https adresi kabul edilir (Validator 'url' kuralı)
    tracking_url VARCHAR(500) NULL,

    status ENUM(
        'preparing',
        'handed_to_cargo',
        'in_transit',
        'out_for_delivery',
        'delivered',
        'failed',
        'returned'
    ) NOT NULL DEFAULT 'preparing',

    shipped_at DATETIME NULL,
    delivered_at DATETIME NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    -- Sipariş fiziksel silinirse gönderimleri de silinir
    CONSTRAINT fk_shipments_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,

    CONSTRAINT fk_shipments_method
        FOREIGN KEY (shipping_method_id) REFERENCES shipping_methods(id) ON DELETE SET NULL,

    INDEX idx_shipments_order (order_id),
    INDEX idx_shipments_tracking (tracking_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;