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
- [x] `admin/brands/form.php` yazıldı; 6 marka route'u `App.php`'ye eklendi (2026-10-31)
- [x] Ürün modülü yazıldı (2026-10-31): `Models/Product.php`, `Admin/ProductController.php`, `admin/products/index.php`, `admin/products/form.php`; 6 ürün route'u `App.php`'de; menüye "Ürünler" eklendi (`layouts/admin.php`)
- [x] `app/Core/Uploader.php` yazıldı (jpg/png/webp, finfo + getimagesize, rastgele ad, 2 MB, 6000 px)
- [x] `app/Models/ProductImage.php` yazıldı (tek ana görsel kuralı, sıralama, alt metin)
- [ ] Marka, ürün ve ürün görseli dosyalarında `php -l` ve elle test çalıştırılmadı
- [x] Varyant modülü başladı (2026-10-31): `Models/ProductVariant.php`, `Admin/ProductVariantController.php`, `admin/products/variants/index.php` yazıldı; 6 varyant route'u `App.php`'de (liste, oluştur, kaydet, düzenle, güncelle, durum)
- [x] Varyant dosyalarında `php -l` geçti (model, controller, `App.php`, index view); veritabanıyla elle test yapılmadı
- [x] `Admin/ProductImageController.php` ve `admin/products/images/index.php` yazıldı; 6 görsel route'u `App.php`'de (2026-10-31)
- [x] `admin/products/variants/form.php` yazıldı
- [x] Ürün formu ve ürün listesine "Görseller" ve "Varyantlar" bağlantıları eklendi
- [x] `App.php` düzeltildi: çift `use` satırları ve çift `sil` route'u kaldırıldı
- [x] `Auth.php` `app/Core/` altına taşındı; `AuthMiddleware.php` kopyası silindi
- [x] Stok modülü yazıldı: `Models/StockMovement.php`, `Admin/StockController.php`, `admin/stock/{index,form,history}.php`
- [x] Stok route'ları `App.php`'ye ve "Stok" menüsü `layouts/admin.php`'ye eklendi
- [x] Dashboard: `DashboardController` ve `admin/dashboard/index.php` güncellendi (ürün sayısı, kritik stok tablosu, FR-097)
- [x] `ProductController` ve `ProductVariantController`: başlangıç stoğu > 0 ise `stock_movements` kaydı (aynı transaction)
- [x] Kategori görseli (`CategoryController`, `admin/categories/form.php`) ve marka logosu (`BrandController`, `admin/brands/form.php`) eklendi
- [x] Sepet modülü başladı: `Models/Cart.php`, `Services/CartService.php`, `Controllers/CartController.php` yazıldı
- [ ] `cart/index` view'ı, sepet JS'i ve `/sepet*` route'ları (`App.php`) yazılacak
- [ ] Migration 011'in `migrate.php` ile gerçek MySQL'de çalıştırılması
- [x] `Cart.php`: `resolveSellable()` ve `assertQuantity()` `public` yapıldı; `resolveSellable()` içindeki tekrarlanan `ProductVariant` örneği tekilleştirildi.
- [x] `CartController`: `update()` ve `remove()` içindeki `KEY_PATTERN` regex kuralı dizi biçimine çevrildi; `keyRules()` yardımcısı eklendi.
- [x] `app/Views/cart/index.php` yazıldı: sepet listesi, adet güncelleme, kalem silme, stok/satış durumu uyarıları ve Fetch JS.
- [x] `App.php`: `CartController` için `use` satırı ve 5 sepet route'u eklendi: `GET /sepet`, `GET /sepet/ozet`, `POST /sepet/ekle`, `POST /sepet/guncelle`, `POST /sepet/sil`.
- [x] `Product.php`: vitrin için `paginateStorefront()` ve `SORTS` eklendi; arama, kategori, fiyat aralığı, sıralama ve sayfalama desteği geldi.
- [x] Vitrin `ProductController` yazıldı: `GET /urunler` ve `GET /urun/{slug}`; admin `Admin\ProductController` değişmedi.
- [x] `app/Views/products/index.php` yazıldı: filtre formu, ürün kartları, sayfalama, Fetch ile sepete ekleme ve “Tükendi” durumu.
- [x] `app/Views/products/show.php` yazıldı: galeri, varyant seçimi, dinamik fiyat/stok güncelleme, miktar seçimi, Fetch ile sepete ekleme ve benzer ürünler.
- [x] `App.php`: `use App\Controllers\ProductController as StorefrontProductController;` ve vitrin route'ları eklendi: `GET /urunler`, `GET /urun/{slug:[a-z0-9]+(?:-[a-z0-9]+)*}`.
- [x] `layouts/main.php`: menüye Ürünler, Sepet (rozetli), ürün arama formu, oturum durumuna göre Giriş / Kayıt Ol / ad + Çıkış ve staff için "Yönetim Paneli" bağlantısı eklendi (2026-11-01)
- [x] `CartService::GUEST_KEY_PATTERN` kullanılmayan sabiti silindi
- [x] Müşteri kimlik doğrulama modülü yazıldı: `Controllers/AuthController.php` (vitrin), `Views/auth/login.php`, `Views/auth/register.php`; 5 route `App.php`'de (`GET/POST /giris`, `GET/POST /kayit`, `POST /cikis`; `StorefrontAuthController` takma adı)
- [x] Girişte ve kayıtta `CartService::mergeGuestCart()` bağlandı (FR-041)
- [ ] 2026-11-01 tarihli yeni dosyalarda `php -l` ve elle test çalıştırılmadı: `layouts/main.php`, `AuthController.php`, `auth/login.php`, `auth/register.php`, `App.php`, `CartService.php`


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
- `Product`: soft delete; `findBySlug` (vitrin görünürlüğü FR-033), `getFeatured`, `incrementViewCount`, `paginate` (ad/SKU arama, kategori ve durum filtresi, variant_count), `getLowStock`, `slugExists`, `skuExists` (ürün + varyant), `hasVariants`, `getAvailableStock`, `currentPrice`; `create()`/`update()` fiyat kurallarını denetler; varyantlı üründe `stock_quantity` yazılamaz
- `ProductImage`: fiziksel silme; `getByProduct`, `findMain`, `findForProduct`, `countByProduct`, `addImage` (ilk görsel otomatik ana), `setMain`, `reorder`, `updateAlt`, `removeImage` (yol döner, dosyayı controller siler)
- `Uploader` (App\Core): statik `save`, `saveMany`, `delete`; klasör beyaz listesi `products|categories|brands|banners`
- `Admin\ProductController`
- View: `admin/brands/{index,form}`, `admin/products/{index,form}`
- `ProductVariant`: fiziksel silme yok (`delete()` kapalı, `setActive()` kullanılır); `getByProduct`, `findForProduct`, `countByProduct`, `isPurchasable`, `currentPrice`, `skuExists` (varyant + ürün), `nameExists`; `create()` ürünün varlığını ve fiyat kurallarını denetler; `update()` `product_id` ve `stock_quantity` yazmayı reddeder
- `Admin\ProductVariantController`: `index`, `create`, `store`, `edit`, `update`, `toggle` (Fetch için JSON, form için yönlendirme); varyantın URL'deki ürüne ait olduğunu doğrular
- View: `admin/products/variants/index` (aktif/pasif anahtarı Fetch ile)
- `ProductImageController`: `index`, `store` (en fazla 10 görsel, hata olursa dosyalar silinir), `setMain`, `updateAlt`, `reorder` (Fetch, JSON), `destroy`
- `StockMovement`: yalnızca eklenir (`create/update/delete` kapalı); `record()` stok güncelleme ve hareketi aynı transaction'da, `FOR UPDATE` ile yapar; `paginate`, `getStockItems`
- `Admin\StockController`: `index`, `create`, `store`, `history`
- View: `admin/products/images/index`, `admin/products/variants/form`, `admin/stock/{index,form,history}`
- `Cart`: yalnızca üye sepeti (`carts`, `cart_items`); `findActiveByUser`, `getOrCreateActive`, `getItems`, `countQuantity`, `addItem` (aynı ürün+varyantta adet artar, transaction + `FOR UPDATE`), `setQuantity` (0 siler), `removeItem`, `clear`, `getTotals`; `create/update/delete` kapalı; stok, varyant ve vitrin görünürlüğü kontrolü `resolveSellable()` içinde
- `Services\CartService`: misafir (`$_SESSION['cart']['items']`) ve üye sepetini tek arayüzde toplar; `summary`, `count`, `items`, `add`, `setQuantity`, `remove`, `mergeGuestCart` (giriş sonrası birleştirme; sığmayan kalem uyarı olarak döner)
- `CartController`: `index`, `summary`, `add`, `update`, `remove` (JSON, Fetch); servis hataları 422 ile döner
- `Product`: vitrin için `paginateStorefront()` ve `SORTS` eklendi. Arama ürün adı, kısa açıklama, açıklama ve SKU üzerinden yapılır; kategori filtresi seçilen kategori ile doğrudan alt kategorileri kapsar. Sıralama beyaz listeyle sınırlıdır: `new`, `price_asc`, `price_desc`, `name`.
- `ProductController` (vitrin, `App\Controllers`): `index` ve `show`; `/urunler` filtreleri doğrular, `/urun/{slug}` ürün detayını, varyantları, görselleri ve benzer ürünleri hazırlar. `view_count` her detay görüntülemesinde artar.
- `Views/products/index`: filtre formu, ürün kartı ızgarası, sayfalama, Fetch ile sepete ekleme.
- `Views/products/show`: galeri, varyant seçimi, dinamik fiyat/stok, adet ve Fetch ile sepete ekleme.
- `Views/cart/index`: sepet sayfası; Fetch ile adet güncelleme, kalem silme ve `/sepet/ozet` senkronizasyonu.
- `Controllers\AuthController` (vitrin, `App\Controllers`): `showLogin`, `login`, `showRegister`, `register`, `logout`. Kayıtta `user_type` her zaman `customer` olur. Başarısız girişte mesaj hesap bilgisi sızdırmaz. `next` yalnızca güvenli site içi yollarla sınırlıdır. Giriş ve kayıttan sonra misafir sepeti birleştirilir; birleştirme hatası girişi engellemez, loglanır.
- `Views/auth/login`: e-posta, şifre, gizli `next`; şifre geri doldurulmaz.
- `Views/auth/register`: ad, soyad, e-posta, telefon, şifre, şifre tekrarı; alan bazlı hatalar.
- `Views/layouts/main`: sepet rozeti ilk açılışta `CartService::count()` ile hesaplanır. `window.fetch` sarmalayıcısı `/sepet/` altına yapılan başarılı POST yanıtındaki `count` değerini rozete yazar. Geri tuşuyla dönüşte (`pageshow`) rozet `/sepet/ozet` ile tazelenir.



## 5. Sıradaki Dosya


1. Doğrulama turu (kullanıcı adımı): `php database/migrate.php --status`, ardından `php database/migrate.php`. Sonra tüm dosyalar için `php -l` ve sepet, vitrin, müşteri girişi için elle test (TEST-COMMANDS.md).
2. Sipariş modülü: `app/Models/Order.php` (sipariş numarası `ASY-YYYYMMDD-NNNNNN`, transaction içinde stok düşme, kupon kullanımı, durum geçmişi). Ardından `OrderItem`/servis, `CheckoutController` ve checkout view'ları.
3. Müşteri hesabı sayfaları: profil, adres defteri, siparişlerim (FR-053, FR-054, FR-055).
4. Şifremi unuttum (FR-052) ve e-posta doğrulaması (FR-051).
5. Kupon modeli ve admin kupon yönetimi.

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
11. Misafir sepeti yalnızca PHP session'da tutulur (`$_SESSION['cart']['items']`).
`carts` ve `cart_items` tabloları yalnızca üye sepeti içindir; `carts.user_id` NOT NULL'dır.
Giriş yapıldığında misafir sepeti `CartService::mergeGuestCart()` ile üye sepetine birleştirilir.
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
23. **Ürün stoğu:** `products.stock_quantity` yalnızca ürün oluştururken başlangıç stoğu olarak yazılır; güncellemede yazılmaz (stok değişikliği stok modülünden, FR-026). Varyantlı üründe `Product::update()` bu alanı reddeder.
24. **SKU benzersizliği:** ürünler ve varyantlar arasında benzersizdir; `Product::skuExists()` iki tabloya da bakar, silinmiş ürünler de sayılır.
25. **Fiyat kuralları:** `price > 0`, `discount_price < price`, indirim bitişi başlangıçtan önce olamaz. İndirim tarihleri yalnızca indirimli fiyat varsa saklanır (başlangıç 00:00:00, bitiş 23:59:59). Fiyat girişinde ondalık ayırıcı nokta; virgül kabul edilmez.
26. **Vitrin görünürlüğü (FR-033):** pasif/silinmiş kategorideki veya markadaki ürünler vitrinde listelenmez (`Product::findBySlug`, `getFeatured`).
27. **Ürün silme:** soft delete; sipariş kalemleri anlık kopya tuttuğu için sipariş geçmişi etkilenmez. Görsel dosyaları ürün geri getirilebilsin diye silinmez.
28. **Uploader:** yalnızca jpg/jpeg/png/webp; MIME `finfo` ile içerikten, `getimagesize()` ile doğrulanır; uzantı MIME ile tutarlı olmalı; en fazla 2 MB ve 6000x6000 px; dosya adı rastgele (32 hex); DB'ye yalnızca göreli yol yazılır (`products/ab12....jpg`).
29. **Ana görsel:** üründe tek `is_main`; ilk görsel otomatik ana; ana görsel silinirse sıradaki ana olur. Model dosya sistemine dokunmaz.
30. **Varyantlar silinmez:** satıştan kaldırmak için `is_active = 0` kullanılır (`ProductVariant::delete()` kapalı). Sipariş kalemleri ve stok hareketleri bu yüzden etkilenmez.
31. **Varyant stoğu:** `product_variants.stock_quantity` yalnızca varyant oluştururken başlangıç stoğu olarak yazılır; güncellemede yazılmaz (stok modülünden, FR-026). Varyant ürün değiştiremez (`product_id` değişmez).
32. **Varyant benzersizliği:** aynı üründe aynı varyant adı kullanılamaz; SKU ürünler ve varyantlar arasında benzersizdir (karar 24 ile aynı kural).
33. **Kritik stok eşiği:** varyantlarda ayrı eşik yok; "Kritik" uyarısı ürünün `critical_stock_quantity` değerini kullanır.
34. **Ürün görselleri:** ürün başına en fazla 10 görsel. DB kaydı başarısız olursa yüklenen dosyalar silinir. Silmede önce kayıt, sonra dosya kaldırılır. Sıralama Yukarı/Aşağı butonları ve Fetch ile yapılır.
35. **Stok hareketleri:** `stock_movements` yalnızca eklenir. Stok güncelleme ve hareket kaydı aynı transaction'da, satır `FOR UPDATE` ile kilitlenerek yapılır. Stok negatife düşemez. Manuel harekette işlem nedeni zorunludur (FR-026). `correction` türünde girilen değer hedef stoktur; kaydedilen miktar farkın mutlak değeridir. Formdan yalnızca `incoming`, `outgoing`, `return`, `correction` kabul edilir; `reservation` sistem kaynaklıdır.
36. **Kalem seçimi:** stok formunda kalem `p{ürün}` veya `p{ürün}v{varyant}` biçimindedir. Varyantlı ürüne varyantsız hareket yazılamaz.
37. **Başlangıç stoğu:** ürün veya varyant oluşturulurken stok doğrudan yazılmaz; kayıt 0 stokla açılır ve başlangıç stoğu > 0 ise `incoming` hareketi (`reference_type` = `product`/`variant`) aynı transaction'da yazılır.
38. **Kategori/marka görseli:** `Uploader` ile (`categories`, `brands` klasörleri). Yeni dosya DB hatasında silinir; eski dosya yalnızca başarılı güncellemeden sonra silinir. Formlarda `enctype="multipart/form-data"` vardır.
39. **Sepet:** misafir sepeti yalnızca session'da, üye sepeti DB'de (DECISIONS.md). Kalem anahtarı misafirde `p5`/`p5v3`, üyede `cart_items.id`. Sepete ekleme/güncellemede stok, varyant ve vitrin görünürlüğü denetlenir; varyantlı üründe varyant zorunludur. Fiyat bilgilendirmedir, sipariş anında yeniden hesaplanır. Sepette en fazla 50 kalem, kalem başına en fazla 1000 adet.
40. **Sepet birleştirme:** girişte `CartService::mergeGuestCart()`; aynı ürün+varyantta adetler toplanır, stoğa sığmayan kalem eklenmez ve uyarı döner, ardından session sepeti temizlenir.
41. **Sepet route'ları:** `/sepet`, `/sepet/ozet`, `/sepet/ekle`, `/sepet/guncelle` ve `/sepet/sil` route'ları admin grubunun dışındadır; misafir kullanıcılar da sepeti kullanabilir. POST isteklerinde genel CSRF denetimi geçerlidir.

42. **Sepet görünümü:** Sepet sayfası sunucuda render edilir; adet değiştirme ve kalem silme Fetch API ile yapılır. Bildirimler Bootstrap alert alanına `textContent` ile yazılır; `innerHTML` kullanılmaz.

43. **Sepet anahtarı doğrulaması:** `CartController` içindeki `KEY_PATTERN` regex'i Validator'a dizi biçiminde verilir; çünkü desen `|` içerir ve metin kuralı `|` ile bölünür.

44. **Vitrin ürün listesi:** `/urunler` sayfası arama, kategori, fiyat aralığı, sıralama ve sayfalama destekler. Geçersiz filtreler sayfayı kırmaz; kullanıcıya uyarı gösterilir. `min > max` ise her iki fiyat filtresi yok sayılır.

45. **Vitrin sıralaması:** Kullanıcıdan gelen sıralama değeri doğrudan SQL'e konmaz; yalnızca `new`, `price_asc`, `price_desc` ve `name` değerleri sabit eşlemeyle kullanılır.

46. **Ürün detay:** `/urun/{slug}` yalnızca vitrinde görünebilir ürünleri açar. Varyantlı üründe fiyat ve stok varyant bazlıdır; varyant seçilmeden sepete ekleme yapılamaz.

47. **Vitrin controller ayrımı:** Müşteri tarafı `App\Controllers\ProductController`, yönetim tarafı `App\Controllers\Admin\ProductController` olur. `App.php` içinde vitrin controller'ı `StorefrontProductController` takma adıyla kullanılır.

48. **Görüntülenme sayacı:** Ürün detayı her açılışta `view_count` değerini artırır. Bot/yenileme filtresi şu aşamada uygulanmaz; gerekirse sonradan oturum bazlı sayım eklenir.
49. **Müşteri route'ları:** `/giris`, `/kayit`, `/cikis` herkese açıktır ve admin grubunun dışındadır. Yönetim paneli girişi ayrıdır: `/admin/giris`, `/admin/cikis`. `App.php`'de vitrin sınıfı `StorefrontAuthController` takma adıyla kullanılır.
50. **Kayıt:** `user_type` hiçbir zaman formdan alınmaz; `User::createUser()` varsayılanı `customer`'dır. Şifre kuralı: en az 8 karakter, harf ve rakam, en fazla 72 bayt. Şifre hiçbir zaman flash'a yazılmaz.
51. **Giriş sonrası sepet:** Giriş ve kayıttan sonra `CartService::mergeGuestCart()` çağrılır. Hata girişi engellemez, loglanır ve session sepeti korunur. Sığmayan kalemler `info` flash'ı olarak gösterilir.
52. **Dönüş adresi (`next`):** Yalnızca `/` ile başlayan, sabit karakter kümesine uyan, `//` ve `..` içermeyen yollar kabul edilir. `/giris`, `/kayit`, `/cikis` kabul edilmez (döngü).
53. **Menü ve sepet rozeti:** Sepet rozeti ilk açılışta sunucuda, sonrasında Fetch yanıtlarındaki `count` ile güncellenir. Menüde gizleme yetki kontrolü değildir; asıl denetim route ve middleware katmanındadır.
54. **Admin ve vitrin oturumu:** Oturum anahtarı (`_auth_user_id`) ortaktır; admin/staff kullanıcı vitrin `/giris` üzerinden de oturum açabilir. Müşteri hesabı `/admin/giris` ile panele giremez. Kısıtlama istenirse ayrı karar olur.



## 8. Bilinen Riskler

### Kritik


- `app/Middleware/AuthMiddleware.php` içinde `AdminMiddleware` sınıfı tanımlı (kopya); silinmeli.
- app/Middleware/AuthMiddleware.php içinde AdminMiddleware sınıfı tanımlı; AdminMiddleware.php’nin kopyası. Silinmeli.
- .env içeriği (DB şifresi dahil) sohbete yüklendi. Şifre değiştirilmeli. .env projeden yüklenen dosyalardan çıkarılmalı.
- DECISIONS.md kendi içinde çelişiyor: "Sepet Kuralları" bölümü eski kuralı (user_id veya session_id) söylüyor. Bkz. DECISIONS.md 3.1.
- İkinci bir spesifikasyon seti (`GEREKSİNİMLER.MD`, `00-PROJE-TALİMATI.MD`, `02`, `03`, `04`) mevcut şema ile çelişiyor ve `gereksinimler.md`'yi "tek doğruluk kaynağı" ilan ediyor. Farklar: `roles`/`permissions` tabloları, `order_addresses`/`order_shipments`, varyant `price_difference`, varyant `critical_stock_quantity`, ayrı iade durumları, alan adları (`name`/`sale_price`/`tax_rate`/`stock`), `resources/views` ve `Services`/`Repositories` klasörleri. Hangi belge setinin geçerli olduğu netleşmeli.
- `storage/sessions` dosyaları Git'e girmiş olabilir. `git ls-files storage .env` ile kontrol edilmeli, gerekirse `git rm --cached` uygulanmalı. `.gitignore` doğrulanamadı.
- `.env` içeriği sohbette paylaşıldı. DB şifresi değiştirilmeli; `git ls-files .env storage` ile .env ve storage/sessions/sess_* dosyalarının Git'te olmadığı doğrulanmalı.
- **Admin girişinde rate limiting yok.** `Request::ip()` proxy düzeltmesinden önce eklenemez (DECISIONS.md teknik borcu). Bu çözülene kadar sunucu internete açılmamalı.
- `CLAUDE.md` "Sepet ve Sipariş Kuralları" bölümü hâlâ misafir sepetini `carts.session_id` ile DB'de tutuyor diyor; DECISIONS.md'ye göre yalnızca session. Sepet modülünden önce düzeltilmeli.

### Orta


- Misafir sepetindeki fiyat her görüntülemede güncel hesaplanır; sepete eklendiği anki fiyat saklanmaz (üyede saklanır). Sipariş anında yeniden kontrol edileceği için kabul edildi.
- Migration 011 gerçek MySQL'de henüz çalıştırılmadı; sepet bu yüzden test edilemez.
- Marka ve kategori listelerinde görsel/logo küçük resmi gösterilmiyor.
- `StockController::store()` model hata mesajını anahtar kelimeyle `quantity`/`item` alanına eşliyor; mesajlar değişirse eşleme bozulur. İleride özel exception sınıfı kullanılmalı.
- `admin/stock/index.php` içinde `const STOCK_LIST_LIMIT` view içinde tanımlı; değişkene çevrilmeli.
- `ProductImage::addImage()` closure + `parent::create()` kalıbı ve `StockMovement::record()` aynı kalıbı kullanıyor; ilk çalıştırmada doğrulanmalı.

- FR-021 varyant başına kritik stok eşiği istiyor; migration 007'de bu sütun yok. Karar 33'e göre ürünün eşiği kullanılıyor, migration 019 açılmadı. `Product::getLowStock()` yalnızca varyantsız ürünleri kapsıyor; varyantlı ürünlerin kritik stok listesi stok modülünde ele alınmalı.
- Varyant oluşturulurken girilen başlangıç stoğu için `stock_movements` kaydı atılmıyor (`StockMovement` modeli yok). Ürün başlangıç stoğuyla aynı sorun; stok modülünde birlikte çözülecek.
- WebP yüklemesi için sunucuda GD/WebP desteği gerekir; yoksa "geçerli görsel değil" hatası verir.
- `php.ini` `upload_max_filesize` ve `post_max_size` 2 MB'tan düşükse Uploader'a ulaşmadan reddedilir.
- Kategori ve marka formlarında `enctype="multipart/form-data"` yok; görsel/logo eklenirken eklenecek.
- `ProductImage::addImage()` içinde closure + `parent::create()` kullanımı ilk çalıştırmada doğrulanmalı.
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
- Müşteri girişinde ve kayıtta brute-force koruması (rate limiting) yok; `Request::ip()` proxy düzeltmesine bağlı. Admin girişiyle aynı teknik borç: sunucu internete açılmadan çözülmeli.
- E-posta doğrulaması (FR-051) ve şifremi unuttum (FR-052) yok. Kayıtta e-posta doğrulanmadığı için başkasının adresiyle hesap açılabilir.
- `layouts/main.php` `$_GET['q']` ve `$_SERVER['REQUEST_URI']` okuyor (yalnızca gösterim ve menü işaretleme). Temiz çözüm: controller'dan `$searchQuery` ve `$currentPath` geçirmek.
- `layouts/main.php` içindeki `$isGuestPage` satırı geçici bir ifade içeriyor (`$currentPathForAuth` atamasıyla). `$currentPath` tanımından sonraya taşınıp `in_array($currentPath, ['/giris', '/kayit'], true)` biçimine sadeleştirilmeli.
- `layouts/main.php` `window.fetch`'i sarar ve `/sepet/` içeren her POST'u izler. Sepet dışında bu yol parçasını kullanan bir modül eklenirse gözden geçirilmeli. `count` alanı yoksa etkisizdir.
- Vitrin sayfalarında `Hesabım` (profil, adresler, siparişler) henüz yok; menüdeki ad bağlantı değildir.
- Kayıtta telefon boşluk, tire ve parantezden arındırılarak saklanır (`users.phone VARCHAR(20)`). Uluslararası biçimler (`+90 ...`) 20 karakteri aşmamalı; Validator `phone` kuralı yalnızca Türkiye cep numaralarını kabul eder.



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
- 2026-10-31 (dashboard view, kategori ve marka dosyaları yazıldı; php -l çalıştırılmadı).
- 2026-10-31 (ürün modülü, Uploader ve ProductImage yazıldı; php -l ve elle test çalıştırılmadı).
- 2026-10-31 (varyant modeli, controller, route'lar ve liste view'ı yazıldı; yalnızca `php -l` çalıştırıldı, veritabanı ve tarayıcı testi yapılmadı).
- 2026-10-31 (görsel, varyant formu, Auth taşıma, stok modülü yazıldı; `php -l` ve elle test çalıştırılmadı).
- 2026-11-01 (dashboard, başlangıç stoğu hareketi, kategori/marka görseli, Cart modeli, CartService, CartController yazıldı; `php -l` ve elle test çalıştırılmadı).
- 2026-11-01 (sepet view/JS/route'ları, vitrin Product modeli, vitrin ProductController, products/index, products/show ve vitrin route'ları yazıldı; php -l ve elle test henüz çalıştırılmadı).
- 2026-11-01 (vitrin menüsü, müşteri AuthController, login/register view'ları ve route'ları yazıldı; `php -l` ve elle test çalıştırılmadı).


## Teknik Kararlar

- schema_migrations tablosu, 31 uygulama tablosuna dahil değildir.
- schema_migrations tablosu database/migrate.php tarafından otomatik oluşturulur.
- Kullanıcı yetkilendirmesi şu aşamada users.user_type ENUM ile yönetilir.
- Admin paneline erişim: user_type IN ('admin','staff'). Tam yönetici yetkisi: user_type = 'admin'.
- roles, permissions, role_permissions ve user_roles tabloları şu aşamada oluşturulmaz.
- Ayrıntılı RBAC ihtiyacı ileride ayrı migration ile değerlendirilecektir.

Kararların tamamı için bkz. DECISIONS.md.

