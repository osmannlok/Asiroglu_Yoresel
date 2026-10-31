-- =============================================================================
-- Migration 015: return_requests
-- Aşıroğlu Yöresel Market - İade talepleri
-- Bağımlılık: orders (012), order_items (012), users (001)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- İade talepleri
--
--    İş kuralları (ReturnRequest modeli ve iade servisinde uygulanır):
--    - Müşteri teslimattan sonra iade talebi oluşturabilir; talepte sipariş,
--      ürün (kalem) ve iade nedeni seçilir.
--    - Talep 'requested' durumunda admin onayına düşer.
--      Akış: requested -> approved | rejected -> shipped -> received
--            -> refunded -> completed
--    - Onaylanan iadede kargo gönderim süreci başlatılır; ürün depoya
--      ulaşınca ('received') kontrol yapılır.
--    - Ürün stoğa geri eklenirken stock_movements tablosuna 'return' türünde
--      hareket yazılır (aynı transaction içinde).
--    - Ödeme iadesi onaylanınca orders.payment_status ve payments.status
--      'refunded' veya 'partially_refunded' olur. refund_amount kısmi iadede
--      iade edilecek tutarı tutar (TRY, KDV dahil).
--    - Gıda ürünlerinde cayma hakkı istisnaları yasal metinlerle uyumlu
--      uygulanır (kural serviste denetlenir).
-- -----------------------------------------------------------------------------
CREATE TABLE return_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Sipariş fiziksel silinirse iade talepleri de silinir
    order_id INT UNSIGNED NOT NULL,

    -- İade edilmek istenen sipariş kalemi; kalem silinirse NULL olur
    order_item_id INT UNSIGNED NULL,

    -- Talebi oluşturan kullanıcı; kullanıcı silinirse NULL olur (talep korunur)
    user_id INT UNSIGNED NULL,

    return_reason VARCHAR(500) NOT NULL,

    status ENUM(
        'requested',
        'approved',
        'rejected',
        'shipped',
        'received',
        'refunded',
        'completed'
    ) NOT NULL DEFAULT 'requested',

    -- İade edilecek tutar; admin onayında belirlenir, NULL = henüz belirlenmedi
    refund_amount DECIMAL(12,2) NULL,

    -- Yönetici notu (red gerekçesi, kontrol sonucu vb.)
    admin_note VARCHAR(500) NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_return_requests_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,

    CONSTRAINT fk_return_requests_item
        FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE SET NULL,

    CONSTRAINT fk_return_requests_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_return_requests_order (order_id),
    INDEX idx_return_requests_user (user_id),

    -- Admin panelinde duruma göre iade listesi için
    INDEX idx_return_requests_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;