-- =====================================================
-- AŞIROĞLU YÖRESEL MARKET
-- VERİTABANI ŞEMASI KURULUM TEST DOSYASI
-- Bu dosyayı schema kurulduktan sonra çalıştırın.
-- =====================================================

-- 1. Veritabanının seçili olduğunu kontrol edin
SELECT DATABASE() AS current_database;

-- 2. Beklenen tabloların var olup olmadığını kontrol edin
SELECT
    table_name,
    engine,
    table_collation,
    table_rows
FROM information_schema.tables
WHERE table_schema = DATABASE()
ORDER BY table_name;

-- 3. Tablo sayısını kontrol edin
SELECT COUNT(*) AS total_tables
FROM information_schema.tables
WHERE table_schema = DATABASE();

-- 4. Foreign key ilişkilerini kontrol edin
SELECT
    table_name AS child_table,
    column_name AS child_column,
    referenced_table_name AS parent_table,
    referenced_column_name AS parent_column,
    constraint_name
FROM information_schema.key_column_usage
WHERE table_schema = DATABASE()
  AND referenced_table_name IS NOT NULL
ORDER BY table_name, constraint_name;

-- 5. carts tablosunun coupons tablosuna bağlı olduğunu kontrol edin
SELECT
    table_name,
    column_name,
    referenced_table_name,
    referenced_column_name,
    constraint_name
FROM information_schema.key_column_usage
WHERE table_schema = DATABASE()
  AND table_name = 'carts'
  AND referenced_table_name IS NOT NULL;

-- 6. coupons tablosunun yapısını kontrol edin
DESCRIBE coupons;

-- 7. carts tablosunun yapısını kontrol edin
DESCRIBE carts;

-- 8. Kupon ekleme testi
INSERT INTO coupons (
    code,
    description,
    discount_type,
    discount_value,
    min_order_amount,
    max_discount_amount,
    usage_limit,
    usage_limit_per_user,
    starts_at,
    ends_at,
    is_active
) VALUES (
    'TESTKUPON10',
    'Kurulum testi için %10 indirim kuponu',
    'percentage',
    10.00,
    100.00,
    50.00,
    10,
    1,
    NOW(),
    DATE_ADD(NOW(), INTERVAL 7 DAY),
    1
);

-- 9. Kupon kaydının oluştuğunu kontrol edin
SELECT * FROM coupons WHERE code = 'TESTKUPON10';

-- 10. Test kullanıcısı ekleme
INSERT INTO users (
    user_type,
    first_name,
    last_name,
    email,
    phone,
    password_hash,
    status
) VALUES (
    'customer',
    'Test',
    'Kullanıcı',
    'test@ashiroglumarket.com',
    '05550000000',
    '$2y$10$abcdefghijklmnopqrstuv',
    'active'
);

-- 11. Test kullanıcısını kontrol edin
SELECT id, first_name, last_name, email, user_type, status
FROM users
WHERE email = 'test@ashiroglumarket.com';

-- 12. Test sepeti ekleme
INSERT INTO carts (
    user_id,
    session_id,
    coupon_id,
    status
)
SELECT
    u.id,
    'test-session-001',
    c.id,
    'active'
FROM users u
CROSS JOIN coupons c
WHERE u.email = 'test@ashiroglumarket.com'
  AND c.code = 'TESTKUPON10'
LIMIT 1;

-- 13. Sepetin oluştuğunu ve foreign key ilişkisinin çalıştığını kontrol edin
SELECT
    c.id AS cart_id,
    u.email AS user_email,
    co.code AS coupon_code,
    c.status
FROM carts c
LEFT JOIN users u ON u.id = c.user_id
LEFT JOIN coupons co ON co.id = c.coupon_id
WHERE c.session_id = 'test-session-001';

-- 14. Test sepetini silin
DELETE FROM carts WHERE session_id = 'test-session-001';

-- 15. Test kullanıcısını silin
DELETE FROM users WHERE email = 'test@ashiroglumarket.com';

-- 16. Test kuponunu silin
DELETE FROM coupons WHERE code = 'TESTKUPON10';

-- 17. Temizlik sonrası kontrol
SELECT COUNT(*) AS remaining_test_carts
FROM carts
WHERE session_id = 'test-session-001';
