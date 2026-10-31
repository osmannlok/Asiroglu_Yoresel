# E-Ticaret Proje Yol Haritası

## Aşama 1 — Proje Altyapısı
1. Klasör yapısı
2. `.env` ve config
3. Autoload
4. Router
5. Base Controller / Base Model
6. Veritabanı bağlantısı
7. Güvenlik yardımcıları: CSRF, sanitization, auth
8. Temel layout

## Aşama 2 — Veritabanı ve Modeller
1. users, roles, permissions
2. categories, brands
3. products, product_images, product_variants
4. stock_movements
5. carts, cart_items
6. orders, order_items, order_addresses, order_shipments
7. coupons, coupon_usages
8. shipping_methods, payments, settings, activity_logs

## Aşama 3 — Ürün Modülü
1. Kategori CRUD
2. Marka CRUD
3. Ürün CRUD
4. Ürün görselleri
5. Varyant yönetimi
6. Stok yönetimi ve kritik stok uyarısı
7. Ürün vitrini, filtreleme, arama, ürün detayı

## Aşama 4 — Kullanıcı Modülü
1. Kayıt
2. Giriş / çıkış
3. E-posta doğrulama
4. Şifre sıfırlama
5. Profil
6. Adres yönetimi

## Aşama 5 — Sepet Modülü
1. Sepet oluşturma
2. Sepete ekleme
3. Adet güncelleme
4. Ürün çıkarma
5. Misafir sepeti birleştirme
6. AJAX sepet işlemleri

## Aşama 6 — Sipariş Modülü
1. Checkout adımları
2. Adres seçimi
3. Kargo yöntemi
4. Sipariş özeti
5. Sipariş oluşturma
6. Sipariş durumları
7. Manuel kargo takibi

## Aşama 7 — Ödeme Modülü
1. Ödeme yöntemi seçimi
2. Kredi kartı entegrasyonu
3. Havale/EFT akışı
4. Kapıda ödeme
5. Webhook doğrulama
6. Ödeme sonrası stok ve sipariş güncelleme

## Aşama 8 — Kampanya Modülü
1. İndirimli fiyat
2. Kupon yönetimi
3. Kupon uygulama
4. Ücretsiz kargo eşiği

## Aşama 9 — Admin Paneli
1. Dashboard
2. Ürün yönetimi
3. Sipariş yönetimi
4. Müşteri yönetimi
5. Stok raporları
6. Satış raporları
7. Rol/izin yönetimi
8. Site ayarları

## Aşama 10 — Test ve Yayın
1. Güvenlik testleri
2. Mobil uyumluluk testleri
3. Ödeme testleri
4. Sipariş-stok testleri
5. Hata loglama
6. Yayına alma