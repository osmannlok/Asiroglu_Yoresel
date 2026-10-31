# Proje Durumu — Aşıroğlu Yöresel Market

> Bu dosya projenin güncel durum kaydıdır. Her görev sonunda güncellenir.
> Mimari kararların kaynağı `DECISIONS.md`, migration sırasının kaynağı
> `DATABASE-DEPENDENCIES.md`, test komutlarının kaynağı `TEST-COMMANDS.md` dosyasıdır.

## 1. Proje Özeti

- **Proje:** Aşıroğlu Yöresel Market
- **Amaç:** Türkiye genelinde yöresel gıda ve yerel ürün satışı yapan özel e-ticaret sitesi
- **Teknoloji:** PHP 8.2+, Custom MVC, MySQL 8 / MariaDB, PDO, Bootstrap 5, Vanilla JavaScript, Fetch API
- **Kod standardı:** OOP, PSR-12, Türkçe yorumlar
- **Çalışma modeli:** Her görev tek dosya. Önce rapor, onaydan sonra dosya. Kullanıcı `DEVAM` yazmadan sonraki dosyaya geçilmez.

## 2. Tamamlanan Adımlar

- [x] MVC çekirdeği: `App`, `Router`, `Controller`, `Model`, `Database`, `Request`, `Response`, `Session`, `Csrf`, `Validator`
- [x] `public/index.php` front controller
- [x] `config/config.php` (`.env` okuyucu dahil) ve `config/database.php`
- [x] `HomeController` ve `app/Views/layouts/main.php`
- [x] Migration 001–018 yazıldı (2026-10-31; 010, 011, 012 hiçbir veritabanına uygulanmadan DECISIONS.md'ye göre düzeltildi)
- [x] `database/migrate.php` yazıldı (checksum denetimi, `--status`); gerçek MySQL'de henüz denenmedi
- [x] Kalıcı dosyalar: `DECISIONS.md`, `DATABASE-DEPENDENCIES.md`, `TEST-COMMANDS.md`, `PROJE-DURUMU.md`
- [x] Proje durum raporu hazırlandı ve onaylandı
- [x] Kritik kararlar onaylandı (bkz. Bölüm 7)
- [x] `DATABASE-DEPENDENCIES.md` FK listesi tamamlandı (2026-10-31)
- [x] `TEST-COMMANDS.md` var olmayan dosya referansları temizlendi (2026-10-31)
- [x] `PROJE-DURUMU.md` güncellendi (2026-10-31)
- [x] Kategori/marka modülü başladı (2026-10-31): `app/Models/Category.php`, `app/Models/Brand.php` yazıldı
- [x] Kimlik doğrulama altyapısı yazıldı (2026-10-31): `app/Models/User.php`, `app/Core/Auth.php`, `app/Middleware/AdminMiddleware.php`
- [x] `app/Core/Router.php` middleware ve `group()` desteğiyle yeniden yazıldı
- [x] Admin giriş/çıkış: `app/Controllers/Admin/AuthController.php`, `app/Views/admin/auth/login.php`; `/admin/giris` ve `/admin/cikis` route'ları `App.php`'ye eklendi
- [x] `app/Views/layouts/admin.php` (sidebar, üst bar, toast, onay modalı) yazıldı
- [x] `app/Controllers/Admin/DashboardController.php` yazıldı
- [ ] Yukarıdaki dosyalarda `php -l` ve geçici test betikleri henüz çalıştırılmadı (çalıştırınca [x] yapın)
- [x] `app/Views/admin/dashboard/index.php` yazıldı (2026-10-31)
- [x] `App.php`: korumalı `/admin` grubu (AdminMiddleware) ve `GET /admin` route'u eklendi
- [x] Kategori modülü yazıldı: `Admin/CategoryController.php`, `admin/categories/index.php`, `admin/categories/form.php`; 6 kategori route'u `App.php`'de
- [x] `Admin/BrandController.php` ve `admin/brands/index.php` yazıldı
- [ ] `admin/brands/form.php` ve marka route'ları (App.php) bekliyor

## 3. Oluşturulan Migration Dosyaları

| No | Dosya | Tablolar |
|---|---|---|
| 001 | `001_create_users.sql` | users |
| 002 | `002_create_addresses.sql` | addresses |
| 003 | `003_create_categories.sql` | categories |
| 004 | `004_create_brands.sql` | brands |
| 005 | `005_create_products.sql` | products |
| 006 | `006_create_product_images.sql` | product_images |
| 007 | `007_create_product_variants.sql` | product_variants |
| 008 | `008_create_attributes.sql` | attributes, attribute_values, product_attributes |
| 009 | `009_create_stock_movements.sql` | stock_movements |
| 010 | `010_create_coupons.sql` | coupons (deleted_at dahil), coupon_usages |
| 011 | `011_create_carts.sql` | carts (user_id NOT NULL), cart_items |
| 012 | `012_create_orders.sql` | orders (customer_email/phone dahil), order_items, order_status_history + coupon_usages.order_id FK |
| 013 | `013_create_payments.sql` | payments, payment_logs |
| 014 | `014_create_shipping.sql` | shipping_methods, shipments |
| 015 | `015_create_return_requests.sql` | return_requests |
| 016 | `016_create_reviews_wishlists.sql` | reviews, wishlists |
| 017 | `017_create_content_tables.sql` | banners, pages, newsletter_subscribers, contact_messages |
| 018 | `018_create_settings.sql` | settings, activity_logs |

Toplam: 18 migration dosyası, 31 uygulama tablosu (schema_migrations hariç).

## 4. Oluşturulan Modeller

- Soyut `App\Core\Model` (temel CRUD, transaction, prepared statement yardımcıları)
- `Category`: soft delete, `findBySlug`, `getActiveTree`, `paginate`, `slugExists`, `wouldCreateCycle`, `hasChildren`, `hasActiveProducts`; `update()` döngü kontrolü yapar
- `Brand`: soft delete, `findBySlug`, `getActive`, `paginate`, `slugExists`, `hasActiveProducts`
- `User`: soft delete; `create()` kapalı, kullanıcı yalnızca `createUser()` ile oluşturulur; `verifyCredentials`, `findActiveById`, `findByEmail`, `emailExists`, `changePassword`, `updateProfile`; `find()`/`all()` çıktısında `password_hash` ve `remember_token` yoktur; `update()` `user_type` ve `password_hash` yazmayı reddeder
- `Auth` (App\Core): oturumda yalnızca kullanıcı ID'si; `login`, `logout`, `user`, `check`, `guest`, `isAdmin`, `canAccessAdmin`

- `Admin\AuthController`, `Admin\DashboardController`, `Admin\CategoryController`, `Admin\BrandController`
- View: `admin/auth/login`, `admin/dashboard/index`, `admin/categories/{index,form}`, `admin/brands/index`; layout: `layouts/main`, `layouts/admin`
- Middleware: `AdminMiddleware`


## 5. Sıradaki Dosya

1. `app/Views/admin/brands/form.php`
2. `App.php` içine marka route'ları (6 adet, /admin grubunun içine)
3. `php -l` ve elle test: dashboard, kategori, marka akışları
4. `CLAUDE.md` düzeltmesi (bkz. CLAUDE.md değişiklikleri)
5. Ürün modeli (`Product`), ardından ürün CRUD

## 6. Bekleyen Migration Dosyaları

Bekleyen migration yazımı yok (001–018 yazıldı). Bekleyen iş: 010–018'in gerçek MySQL'de
`migrate.php` ile çalıştırılıp doğrulanması. Planlanan 31 tablonun tamamı migration'larda mevcut.

## 7. Alınmış Kritik Kararlar

1. **Kupon sayacı:** `coupons.used_count` korunur. `coupon_usages` kaydı eklenirken `used_count` aynı transaction içinde artırılır. Sipariş iptalinde kullanım kaydı silinirken `used_count` azaltılır. Limit kontrolünde `COUNT(*)` yerine `used_count` kullanılır.
2. **Kupon silme:** Kuponlar fiziksel silinmez. Admin panelinde yalnızca `is_active = 0` veya `deleted_at` doldurma kullanılır. Kupon sorgularında `deleted_at IS NULL` koşulu zorunludur.
3. **Şema kaynağı:** `database-schema.sql` yalnızca başlangıç referansıdır. Gerçek ve güncel veritabanı kaynağı `database/migrations` klasörüdür.
4. **`md/` klasörü:** "md/ klasörü: Proje belgeleri md/ altında tutulur ve esastır (DECISIONS.md). Köktekiler kopyadır, kullanılmaz veya silinir."
5. **Oturum dosyaları:** `storage/sessions/` Git takibine alınmaz. `.gitignore`: `storage/sessions/*` ve `!storage/sessions/.gitkeep`.
6. **Log dosyaları:** `storage/logs/` Git takibine alınmaz. `.gitignore`: `storage/logs/*` ve `!storage/logs/.gitkeep`.
7. **Ortam dosyası:** `.env` Git takibine alınmaz. `.gitignore`'a `.env` satırı eklenir.
8. **`Request::ip()`:** Şu aşamada değiştirilmez. Proxy veya Cloudflare arkasına geçildiğinde güvenilir proxy başlıkları ile IP tespiti yeniden değerlendirilir.
9. **Ayrı görevler:** Güvenlik başlıkları, SRI ve 405 yanıtı düzeltmesi ayrı görevlerde yapılır.
10. **Migration sırası:** 001–018 sırası Bölüm 3'teki gibidir ve `DATABASE-DEPENDENCIES.md` ile aynıdır.
11. Misafir sepeti yalnızca PHP session'da; carts yalnızca üye sepeti (user_id NOT NULL).
12. Migration çalıştırıcı: schema_migrations'a checksum kaydeder; çalışmış dosya değişirse durur.
13. **Yetkilendirme:** Oturumda yalnızca kullanıcı ID'si tutulur (`_auth_user_id`); rol ve durum her istekte DB'den okunur, pasifleştirilen kullanıcı hemen düşer. Panel erişimi `admin` ve `staff`, tam yönetici yetkisi yalnızca `admin`.
14. **Kullanıcı oluşturma:** `User::create()` kapalıdır; `user_type` hiçbir zaman form girdisinden alınmaz.
15. **Admin girişi:** Müşteri hesabı admin girişi yapamaz; hata mesajı yanlış şifre ile aynıdır. Şifre `Validator`'dan geçirilmez (boşluk anlamlıdır). Giriş sonrası dönüş adresi yalnızca `/admin` altındaki güvenli yollarla sınırlıdır. Çıkış yalnızca POST.
16. **Middleware:** `handle(Request): ?Response` sözleşmesi; `null` = devam, `Response` = zincir durur. Geçersiz middleware tanımı istekten geçirilmez, hata fırlatır (500). Admin route'ları `Router::group()` ile korunur; giriş route'ları grubun dışında kalır.
17. **Slug/e-posta benzersizliği:** UNIQUE indeksler soft delete'li kayıtları da kapsadığı için `slugExists()` ve `emailExists()` silinmiş kayıtları da sayar.
18. **Silme kuralları:** Silinmemiş ürünü veya alt kategorisi olan kategori, silinmemiş ürünü olan marka silinemez (önce pasifleştirilir).
19. **Slug üretimi (kategori, marka):** Boşsa addan üretilir (Türkçe karakterler sadeleştirilir) ve çakışırsa -2, -3 eklenir. Elle girilen slug çakışırsa alan hatası verilir. Eşzamanlı çakışma (MySQL 1062) alan hatasına çevrilir.
20. **Form hatası akışı:** Hata durumunda `old` ve `form_errors` flash'a yazılır, forma yönlendirilir (şifre benzeri alan flash'a yazılmaz).
21. **Silme:** Silme işlemleri soft delete; POST + CSRF + ortak onay modalı. `admin` ve `staff` ikisi de silebilir (ayrı kısıt kararı yok; gerekirse sonradan `isAdmin()` ile daraltılır).
22. **Görsel/logo:** `Uploader` yazılana kadar kategori görseli ve marka logosu formlarda yoktur.

## 8. Bilinen Riskler

### Kritik

- app/Models/Auth.php yolu: sınıf App\Core\Auth ama dosya app/Models/ altında görünüyor. Autoloader app/Core/Auth.php arar; taşınmazsa /admin 500 verir.
- app/Middleware/AuthMiddleware.php içinde AdminMiddleware sınıfı tanımlı; AdminMiddleware.php’nin kopyası. Silinmeli.
- .env içeriği (DB şifresi dahil) sohbete yüklendi. Şifre değiştirilmeli. .env projeden yüklenen dosyalardan çıkarılmalı.
- DECISIONS.md kendi içinde çelişiyor: "Sepet Kuralları" bölümü eski kuralı (user_id veya session_id) söylüyor. Bkz. DECISIONS.md 3.1.
- İkinci bir spesifikasyon seti (`GEREKSİNİMLER.MD`, `00-PROJE-TALİMATI.MD`, `02`, `03`, `04`) mevcut şema ile çelişiyor ve `gereksinimler.md`'yi "tek doğruluk kaynağı" ilan ediyor. Farklar: `roles`/`permissions` tabloları, `order_addresses`/`order_shipments`, varyant `price_difference`, varyant `critical_stock_quantity`, ayrı iade durumları, alan adları (`name`/`sale_price`/`tax_rate`/`stock`), `resources/views` ve `Services`/`Repositories` klasörleri. Hangi belge setinin geçerli olduğu netleşmeli.
- `storage/sessions` dosyaları Git'e girmiş olabilir. `git ls-files storage .env` ile kontrol edilmeli, gerekirse `git rm --cached` uygulanmalı. `.gitignore` doğrulanamadı.
- `.env` içeriği sohbette paylaşıldı. DB şifresi değiştirilmeli; `git ls-files .env storage` ile .env ve storage/sessions/sess_* dosyalarının Git'te olmadığı doğrulanmalı.
- **Admin girişinde rate limiting yok.** `Request::ip()` proxy düzeltmesinden önce eklenemez (DECISIONS.md teknik borcu). Bu çözülene kadar sunucu internete açılmamalı.
- `CLAUDE.md` "Sepet ve Sipariş Kuralları" bölümü hâlâ misafir sepetini `carts.session_id` ile DB'de tutuyor diyor; DECISIONS.md'ye göre yalnızca session. Sepet modülünden önce düzeltilmeli.

### Orta

`database-schema.sql` carts.user_id NULL ve cart_items FK SET NULL içeriyor (güncel migration'larla uyumsuz); yalnızca referans, çalıştırılmaz.
- md/database-schema-test.sql eski sepet yapısını (session_id ile misafir sepeti) test ediyor; güncellenmeli veya kullanılmamalı.
- `database-schema.sql` güncel tek kaynak değildir. `variant_key`, `customer_email`, `coupons.deleted_at` kararlarını içermez. `carts`, `coupons` tablosundan önce tanımlıdır; doğrudan çalıştırılmamalıdır.
- Sepet modeli belirsiz: `CLAUDE.md` misafir sepetini session tabanlı, şema ise `carts.session_id` ile DB tabanlı gösteriyor. 011'den önce karar verilmeli.
- Model API adları farklı: `model-ekle` skill'i `findById` / `getAll` / `paginate` istiyor, `Model.php`'de `find` / `all` var ve `paginate` yok.
- Dosya adı büyük/küçük harf riski: `proje-durumu.md` / `PROJE-DURUMU.md`, `proje-kontrol-listesi.md` / `PROJE-KONTROL-LISTESI.md`, `GEREKSİNİMLER.MD` / `gereksinimler.md`. Linux sunucuda bunlar farklı dosyadır.
- `ashiroglu-market-php-mvc-eticaret-raporu.md` eski kararları içeriyor (`stocks`, `odendi`, orders 1—1 payments/shipments). `md/` altına taşınmalı.
- `md/` klasöründe kopya dosyalar var ve sapma riski taşıyor.
- `Request::ip()` proxy veya Cloudflare arkasında yanlış IP verir. Admin rate limiting'den önce çözülmeli.
- Yeni dosyalarda `php -l` ve geçici test betikleri çalıştırılmadı; `tmp_*.php` dosyaları işiniz bitince silinmeli (özellikle `tmp_create_admin.php`).
- `Router::normalizePath()` artık ardışık `/` karakterlerini tek `/` yapıyor; `/admin//x` ile `/admin/x` aynı route'a eşleşir.


### Düşük

- Güvenlik başlıkları (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, HSTS), Bootstrap CDN SRI ve admin rate limiting henüz yok.
- `Router` 405 yanıtı Fetch istekleri için JSON değil ve `Allow` başlığı yok.
- Bilinmeyen bir yola yapılan POST, CSRF kontrolü route eşleşmesinden önce yapıldığı için 404 yerine 419 döner.
- Kupon iptalinde `coupon_usages` kaydı silindiği için kullanım denetim izi kaybolur. Gerekirse sonradan `activity_logs` ile desteklenmeli.
- Bootstrap CDN SRI hash'i admin giriş sayfası ve admin layout'unda da yok (mevcut SRI görevine dahil).
- Admin girişleri `activity_logs`'a yazılmıyor (`ActivityLog` modeli henüz yok).

## 9. Son Doğrulama Tarihi

- 2026-10-31 (010–018 ve migrate.php sonrası)
- 2026-10-31 (Category, Brand, User, Auth, AdminMiddleware, Router, admin giriş ve layout dosyaları; php -l henüz çalıştırılmadı)
2026-10-31 (dashboard view, kategori ve marka dosyaları yazıldı; php -l çalıştırılmadı).

## Teknik Kararlar

- schema_migrations tablosu, 31 uygulama tablosuna dahil değildir.
- schema_migrations tablosu database/migrate.php tarafından otomatik oluşturulur.
- Kullanıcı yetkilendirmesi şu aşamada users.user_type ENUM ile yönetilir.
- Admin paneline erişim: user_type IN ('admin','staff'). Tam yönetici yetkisi: user_type = 'admin'.
- roles, permissions, role_permissions ve user_roles tabloları şu aşamada oluşturulmaz.
- Ayrıntılı RBAC ihtiyacı ileride ayrı migration ile değerlendirilecektir.

Kararların tamamı için bkz. DECISIONS.md.

## Sıradaki Adım

1. `app/Views/admin/brands/form.php`
2. `App.php` içine marka route'ları (6 adet, /admin grubunun içine)
3. `php -l` ve elle test: dashboard, kategori, marka akışları
4. `CLAUDE.md` düzeltmesi (bkz. CLAUDE.md değişiklikleri)
5. Ürün modeli (`Product`), ardından ürün CRUD