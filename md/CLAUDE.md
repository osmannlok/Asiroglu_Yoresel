# Aşıroğlu Yöresel Market — PHP MVC E-Ticaret

## Belge Önceliği

1. `DECISIONS.md` (mimari kararlar — tek kaynak)
2. `CLAUDE.md` (bu dosya)
3. `DATABASE-DEPENDENCIES.md` (migration sırası / FK)
4. Diğer belgeler yalnızca referans; çelişirse `DECISIONS.md` geçerli

## Proje

- **Amaç:** Türkiye geneli yöresel gıda e-ticaret sitesi
- **Kapsam dışı:** Blog, çok satıcılı marketplace
- **Stack:** PHP 8.2+, Custom MVC (Laravel/Symfony yok), MySQL 8/MariaDB, PDO, Bootstrap 5, Vanilla JS, Fetch (jQuery yok)
- **Standart:** PSR-12, Türkçe yorum, ince controller, Model/Service’de iş mantığı, layout sistemi

## Klasör Yapısı (özet)
asiroglu-yoresel/
├── public/          index.php, .htaccess, assets/, uploads/
├── app/
│   ├── Core/        App, Router, Controller, Model, Database, Request, Response,
│   │                Auth, Session, Csrf, Validator, Uploader, Logger
│   ├── Controllers/ Home, Product, Cart, Checkout, Order, Auth, Account + Admin/*
│   ├── Middleware/  AdminMiddleware
│   ├── Models/      User, Address, Category, Brand, Product, ProductImage,
│   │                ProductVariant, StockMovement, Cart, Order, Payment, …
│   ├── Services/    CartService, …
│   ├── Views/       layouts, home, products, auth, cart, checkout, account, admin, errors
│   └── Helpers/
├── config/          config, database, payment, mail
├── database/        migrate.php, migrations/, seeds/
├── storage/         logs/, sessions/, cache/
└── tests/



## Veritabanı

- Kaynak: `database/migrations` (001–018). `database-schema.sql` sadece referans, çalıştırılmaz.
- Sıra: `DATABASE-DEPENDENCIES.md`. Çalışmış migration değiştirilmez → yeni migration.
- InnoDB, utf8mb4_unicode_ci, PK=`id`, FK kullanılır, para=`DECIMAL(12,2)`, soft delete gerektiğinde.
- `schema_migrations` uygulama tablosu değil; migrate.php oluşturur.
- **31 tablo:** users, addresses · categories, brands, products, product_images, product_variants, attributes, attribute_values, product_attributes · stock_movements · coupons, coupon_usages · carts, cart_items · orders, order_items, order_status_history · payments, payment_logs · shipping_methods, shipments, return_requests · reviews, wishlists · banners, pages, newsletter_subscribers, contact_messages · settings, activity_logs
- `stocks` / `logs` tablosu yok.

## Ürün

Ad, slug, SKU, barkod, kısa/detay açıklama, price, discount_price, KDV, stok, birim (adet/kg/gram/paket/litre), ağırlık, menşe, saklama, raf ömrü, alerjen, SEO, aktif/öne çıkan. Çoklu görsel, kategori/alt kategori, marka, varyant, stok takibi.

## Stok

- Hareketler: `stock_movements`
- Varyantsız → `products.stock_quantity`; varyantlı → `product_variants.stock_quantity`
- Sipariş + stok düşümü aynı transaction

## Sepet & Sipariş

- Misafir sepeti **sadece session** (`$_SESSION['cart']['items']`)
- `carts.user_id NOT NULL`; `session_id` kullanılmaz
- Girişte `CartService::mergeGuestCart()` (transaction, stok sınırı)
- `cart_items.variant_key` VIRTUAL; UNIQUE `(cart_id, product_id, variant_key)`
- Sepet: Fetch API; stok kontrolü zorunlu
- Sipariş: transaction içinde stok düş + sepet temizle
- Sipariş no: `ASY-YYYYMMDD-NNNNNN`
- Durumlar: `pending, awaiting_payment, paid, preparing, shipped, delivered, cancelled, payment_failed, returned`
- `payment_status`: `unpaid, paid, failed, refunded, partially_refunded`
- `customer_email` zorunlu; `customer_phone` opsiyonel (misafir takibi)
- Durum geçmişi → `order_status_history`
- `payments.order_id` / `shipments.order_id` UNIQUE değil (çoklu deneme / parçalı gönderim)

## Kupon

- Fiziksel silme yok → `is_active=0` veya `deleted_at`
- Sorguda `deleted_at IS NULL` zorunlu
- Limit: `used_count` (COUNT(*) değil)
- Kullanım yalnızca transaction: eklerken artır, iptalde azalt

## Ödeme

- `PaymentGatewayInterface` + adapter (PayTR/iyzico)
- Config: `config/payment.php`; key’ler public’te olmaz
- Kart verisi DB/log’a yazılmaz; 3D Secure; callback imza doğrulama
- Başarı → order `paid`, payment `completed`; hata → `payment_failed` / `failed`
- Loglar: `payment_logs` (hassas veri maskeli)

## Güvenlik

- PDO prepared, CSRF tüm formlarda, XSS escape, `password_hash`/`verify`
- Session: HttpOnly, Secure, SameSite
- Yetki: `users.user_type` ENUM (`admin`/`staff` panel, sadece `admin` tam yetki); roles/permissions yok
- Müşteri: `/giris`, `/kayit`, `/cikis`; `user_type` formdan alınmaz; hata mesajı sızdırmaz; `next` sadece güvenli yol
- Rate limiting henüz yok (`Request::ip()` borcu); sunucu internete açılmadan çözülecek
- Upload: uzantı + MIME + boyut + içerik; rastgele ad
- Production’da hata detayı yok → `storage/logs`
- `.env`, `storage/sessions/*`, `storage/logs/*` Git dışı; Claude `.env` / `payment.php` okumaz

## Teknik Borç

- `Request::ip()` proxy düzeltmesi (rate limiting’den önce)
- Güvenlik başlıkları, SRI, 405 JSON → ayrı görev
- E-posta doğrulama (FR-051), şifre sıfırlama (FR-052) yok

## Admin Panel

Dashboard, kategori, marka, ürün, görsel, varyant, stok, sipariş, müşteri, kupon, kargo, rapor, ayarlar. Bootstrap 5, responsive.

## Frontend

- Mobil öncelikli Bootstrap 5
- Ana sayfa: öne çıkan, kategori, kampanya
- Liste: arama, kategori/fiyat filtresi, sayfalama
- Detay: galeri, stok, sepete ekle
- Sepet: AJAX adet/sil/toplam
- Checkout: adres, kargo, ödeme, özet
- Loading state + hata yönetimi

## SEO & Yasal

- URL: `/urun/urun-adi`; meta_title/description; alt; canonical; sitemap; robots.txt
- Sayfalar: ön bilgilendirme, mesafeli satış, iade/cayma, KVKK, çerez, gizlilik, teslimat, iletişim, hakkımızda
- Gıda: içerik, alerjen, menşe, raf ömrü, saklama, tüketim tarihi zorunlu

## Geliştirme Sırası

1. MVC çekirdek ✅
2. Migration 001–018 ✅
3. Kategori/marka ✅
4. Ürün ✅
5. Görsel + varyant ✅
6. Stok ✅
7. Admin dashboard ✅
8. Sepet + vitrin ürün + müşteri auth ✅ (MySQL elle test bekliyor)
9. Sipariş / checkout ✅ (MySQL elle test bekliyor)
10. Ödeme
11. Kargo + iade
12. E-posta
13. Raporlama
14. SEO / performans
15. Yayın

## Zorunlu İş Akışı

1. Oku: `DECISIONS.md`, `PROJE-DURUMU.md`, `DATABASE-DEPENDENCIES.md`, `TEST-COMMANDS.md`
2. Görevi tek dosyaya indir
3. Kod öncesi rapor: etkilenen dosya, bağımlılık, karar, şema, test planı
4. Onay olmadan dosya yazma/değiştirme
5. Her seferinde **bir** dosya; tam içerik ver
6. Sonunda: “DEVAM yazarsan sıradaki dosyaya geçeceğim.”
7. `DEVAM` olmadan sonraki dosyaya geçme
8. Kontroller: `php -l` · migration bağımlılık · Model→PDO · View→XSS · Controller→CSRF/yetki
9. Görev bitince `PROJE-DURUMU.md` güncelle