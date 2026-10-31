# Aşıroğlu Yöresel Market — PHP MVC E-Ticaret Projesi

## Belge Önceliği

Çelişki durumunda öncelik sırası şöyledir:

1. `DECISIONS.md` (mimari kararların tek kaynağı)
2. `CLAUDE.md` (bu dosya)
3. `DATABASE-DEPENDENCIES.md` (migration sırası ve FK haritası)
4. Diğer belgeler (`GEREKSİNİMLER.MD`, `00-PROJE-TALİMATI.MD`, `02`, `03`, `04`, `proje-kontrol-listesi.md`, `ashiroglu-market-php-mvc-eticaret-raporu.md`) yalnızca referanstır. Bu belgelerle `DECISIONS.md` çelişirse `DECISIONS.md` geçerlidir.

## Proje Amacı

Türkiye genelinde yöresel gıda ve yerel ürün satışı yapan bir e-ticaret sitesi geliştiriyoruz.

Proje adı: **Aşıroğlu Yöresel Market**

Proje; ürün kataloğu, sepet, sipariş, ödeme, kargo, stok, kampanya, müşteri yönetimi ve admin panelini içerecektir.

## Kapsam

- Blog modülü şu aşamada kapsam dışıdır.
- Tek satıcılı yapı kurulur; çok satıcılı marketplace mimarisi kurulmaz.

## Teknoloji Kuralları

- PHP 8.2 veya üzeri kullanılacak.
- Custom MVC mimarisi kullanılacak.
- Laravel, Symfony, CodeIgniter veya hazır e-ticaret paketi kullanılmayacak.
- MySQL 8 veya MariaDB kullanılacak.
- Tüm veritabanı işlemleri PDO ve prepared statement ile yapılacak.
- Frontend Bootstrap 5, HTML5, CSS3 ve Vanilla JavaScript ile yazılacak.
- Dinamik işlemler için Fetch API kullanılacak.
- jQuery kullanılmayacak.
- Kod PSR-12 uyumlu, Türkçe yorumlu, okunabilir ve modüler olacaktır.
- Controller dosyaları ince tutulacak; iş mantığı Model veya Service katmanında yer alacaktır.
- View dosyalarında tekrar eden yapılar için layout sistemi kullanılacaktır.

## Proje Hedefleri

- Mobil uyumlu, hızlı ve güvenli bir e-ticaret sitesi oluşturmak.
- Yöresel ürünlerde gramaj, raf ömrü, menşe, saklama koşulu ve alerjen bilgilerini yönetmek.
- Gerçek zamanlı stok takibi yapmak.
- Admin panelinden ürün, kategori, sipariş, müşteri, kupon ve rapor yönetimi sağlamak.
- Sepet, sipariş, ödeme ve kargo süreçlerini entegre etmek.
- SEO uyumlu URL yapısı ve meta yönetimi sunmak.

## Klasör Yapısı

```text
ashiroglu-market/
├── public/
│   ├── index.php
│   ├── .htaccess
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── images/
│   └── uploads/
│       ├── products/
│       ├── categories/
│       └── banners/
├── app/
│   ├── Core/
│   │   ├── App.php
│   │   ├── Router.php
│   │   ├── Controller.php
│   │   ├── Model.php
│   │   ├── Database.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Auth.php
│   │   ├── Session.php
│   │   ├── Csrf.php
│   │   ├── Validator.php
│   │   ├── Uploader.php
│   │   └── Logger.php
│   ├── Controllers/
│   │   ├── HomeController.php
│   │   ├── ProductController.php
│   │   ├── CartController.php
│   │   ├── CheckoutController.php
│   │   ├── OrderController.php
│   │   ├── AuthController.php
│   │   ├── AccountController.php
│   │   └── Admin/
│   │       ├── DashboardController.php
│   │       ├── ProductController.php
│   │       ├── CategoryController.php
│   │       ├── StockController.php
│   │       ├── OrderController.php
│   │       ├── CustomerController.php
│   │       ├── CouponController.php
│   │       ├── AuthController.php
│   │       ├── BrandController.php
│   │       └── SettingController.php
│   ├── Middleware/
│   │       └── AdminMiddleware.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Address.php
│   │   ├── Category.php
│   │   ├── Brand.php
│   │   ├── Product.php
│   │   ├── ProductImage.php
│   │   ├── ProductVariant.php
│   │   ├── StockMovement.php
│   │   ├── Cart.php
│   │   ├── Order.php
│   │   ├── OrderItem.php
│   │   ├── Payment.php
│   │   ├── Shipment.php
│   │   ├── Coupon.php
│   │   └── Setting.php
│   ├── Views/
│   │   ├── layouts/
│   │   ├── home/
│   │   ├── products/
│   │   ├── cart/
│   │   ├── checkout/
│   │   ├── account/
│   │   ├── errors/
│   │   └── admin/
│   └── Helpers/
├── config/
│   ├── config.php
│   ├── database.php
│   ├── payment.php
│   └── mail.php
├── database/
│   ├── migrate.php
│   ├── migrations/
│   └── seeds/
├── storage/
│   ├── logs/
│   ├── sessions/
│   └── cache/
└── tests/
```

## Veritabanı Kuralları

- Gerçek ve güncel şema kaynağı `database/migrations` klasörüdür. `database-schema.sql` yalnızca başlangıç referansıdır ve doğrudan çalıştırılmaz.
- Migration sırası `DATABASE-DEPENDENCIES.md` dosyasına göre belirlenir.
- Bir migration çalıştıysa dosyası değiştirilmez. Yeni kolon için yeni migration yazılır.
- Tüm tablolar InnoDB motoru ile oluşturulacak.
- Karakter seti `utf8mb4`, collation `utf8mb4_unicode_ci` olacak.
- Primary key alanları `id` olarak tanımlanacak.
- Foreign key ilişkileri kullanılacak.
- Para birimi alanları `DECIMAL(12,2)` olacak.
- Tarih alanları `DATETIME` olacak.
- Silme işlemlerinde gerektiğinde soft delete kullanılacak.
- Migration dosyaları `database/migrations` klasöründe tutulacak.
- Örnek veriler `database/seeds` klasöründe tutulacak.
- `schema_migrations` tablosu uygulama tablolarına dahil değildir; `database/migrate.php` tarafından oluşturulur.

## Temel Tablolar

Planlanan 31 uygulama tablosu:

- Kullanıcı: `users`, `addresses`
- Katalog: `categories`, `brands`, `products`, `product_images`, `product_variants`, `attributes`, `attribute_values`, `product_attributes`
- Stok: `stock_movements`
- Kampanya: `coupons`, `coupon_usages`
- Sepet: `carts`, `cart_items`
- Sipariş: `orders`, `order_items`, `order_status_history`
- Ödeme: `payments`, `payment_logs`
- Kargo ve iade: `shipping_methods`, `shipments`, `return_requests`
- Etkileşim: `reviews`, `wishlists`
- İçerik: `banners`, `pages`, `newsletter_subscribers`, `contact_messages`
- Sistem: `settings`, `activity_logs`

`stocks` ve `logs` adlı tablolar yoktur.

## Ürün Yönetimi Kuralları

Ürün tablosunda aşağıdaki bilgiler bulunmalıdır:

- Ürün adı
- SEO uyumlu slug
- SKU
- Barkod
- Kısa açıklama
- Detaylı açıklama
- Fiyat
- İndirimli fiyat
- KDV oranı
- Stok miktarı
- Birim türü: adet, kg, gram, paket, litre
- Ağırlık
- Menşe
- Saklama koşulları
- Raf ömrü
- Alerjen bilgileri
- SEO başlığı
- SEO açıklaması
- Aktiflik durumu
- Öne çıkan ürün durumu

Ürünler;

- Birden fazla görsel içerebilmelidir.
- Kategori ve alt kategori yapısını desteklemelidir.
- Marka ile ilişkilendirilebilmelidir.
- Varyant desteklemelidir.
- Stok takibi yapılabilmelidir.

## Stok Kuralları

- Stok hareketleri `stock_movements` tablosunda tutulur.
- Varyantsız üründe stok `products.stock_quantity` üzerinden yönetilir.
- Varyantlı üründe stok `product_variants.stock_quantity` üzerinden yönetilir.
- Sipariş oluşturma ve stok düşürme aynı transaction içinde yapılır.

## Sepet ve Sipariş Kuralları


- `carts` yalnızca giriş yapmış kullanıcılar içindir; `carts.user_id` NOT NULL'dır. `carts.session_id` rezerve alandır, kullanılmaz.
- Giriş yapınca session sepeti kullanıcının DB sepetiyle transaction içinde birleştirilir (aynı ürün+varyantta adetler toplanır, stok sınırı aşılmaz).
- Kullanıcı giriş yaptığında misafir sepeti üye sepetiyle birleştirilir.
- `cart_items.variant_key` VIRTUAL generated column'dur: `IFNULL(product_variant_id, 0)`. UNIQUE index: `uq_cart_item_product (cart_id, product_id, variant_key)`.
- Sepet işlemleri Fetch API ile AJAX üzerinden yapılacak.
- Sepete ekleme, adet güncelleme ve ürün silme işlemlerinde stok kontrolü yapılacak.
- Stokta olmayan veya yetersiz stoklu ürün sepete eklenemeyecek.
- Sipariş oluşturma işlemi database transaction içinde yapılacak.
- Sipariş oluştuğunda stok düşürülecek ve sepet temizlenecek.
- Sipariş numarası formatı: `ASY-YYYYMMDD-NNNNNN`. Her sipariş için benzersiz üretilir.
- Sipariş durumları İngilizcedir: `pending`, `awaiting_payment`, `paid`, `preparing`, `shipped`, `delivered`, `cancelled`, `payment_failed`, `returned`.
- Ödeme durumları (`orders.payment_status`) İngilizcedir: `unpaid`, `paid`, `failed`, `refunded`, `partially_refunded`.
- `orders.customer_email` zorunlu, `orders.customer_phone` isteğe bağlıdır. Misafir siparişleri bu alanlarla takip edilir.
- Sipariş durumu değişiklikleri `order_status_history` tablosuna kaydedilecek.
- `payments.order_id` ve `shipments.order_id` UNIQUE değildir (birden fazla ödeme denemesi ve parçalı gönderim olabilir).

## Kupon Kuralları

- Kuponlar fiziksel silinmez. Admin panelinde yalnızca `is_active = 0` veya `deleted_at` doldurma kullanılır.
- Kupon sorgularında `deleted_at IS NULL` koşulu zorunludur.
- Limit kontrolünde `COUNT(*)` yerine `coupons.used_count` kullanılır.
- Kupon kullanımı yalnızca transaction içinde yönetilir: `coupon_usages` kaydı eklenirken `used_count` artırılır, sipariş iptalinde kayıt silinirken azaltılır.

## Ödeme Kuralları

- Ödeme sağlayıcısı genişletilebilir bir yapı ile kurulacak.
- `PaymentGatewayInterface` arayüzü oluşturulacak.
- PayTR veya iyzico gibi sağlayıcılar için ayrı adapter sınıfları yazılabilecek.
- Ödeme sağlayıcısı bilgileri `config/payment.php` dosyasında tutulacak.
- API key, secret key ve hassas bilgiler asla public klasörüne konulmayacak.
- Kart numarası, CVV ve kart son kullanma tarihi veritabanına veya loglara kaydedilmeyecek.
- 3D Secure akışı desteklenecek.
- Ödeme callback işlemlerinde imza/hash doğrulaması yapılacak.
- Başarılı ödemede sipariş durumu `paid`, ödeme durumu `completed` olarak güncellenecek.
- Başarısız ödemede sipariş durumu `payment_failed`, ödeme durumu `failed` olarak güncellenecek.
- Ödeme logları `payment_logs` tablosunda tutulacak; hassas veriler maskelenecek.

## Güvenlik Kuralları

- Tüm kullanıcı girdileri doğrulanacak.
- Tüm SQL sorguları PDO prepared statement ile yazılacak.
- Tüm formlarda CSRF token kullanılacak.
- XSS saldırılarına karşı çıktı escaping yapılacak.
- Şifreler `password_hash()` ile saklanacak.
- Şifre doğrulama `password_verify()` ile yapılacak.
- Session cookie’lerinde `HttpOnly`, `Secure` ve `SameSite` ayarları kullanılacak.
- Admin paneline yalnızca yetkili kullanıcılar erişebilecek.
- Yetkilendirme `users.user_type` ENUM ile yönetilir: panel erişimi `admin` ve `staff`, tam yönetici yetkisi yalnızca `admin`. `roles` ve `permissions` tabloları şu aşamada yoktur.
- Admin girişinde brute-force koruması için rate limiting uygulanacak.(henüz yok; Request::ip() proxy düzeltmesinden sonra. Bu çözülene kadar sunucu internete açılmaz.)
- Dosya yüklemelerinde uzantı, MIME tipi, dosya boyutu ve gerçek içerik kontrolü yapılacak.
- Yüklenen dosyalara rastgele ve güvenli dosya adı verilecek.
- Production ortamında hata detayları kullanıcıya gösterilmeyecek.
- Hatalar `storage/logs` klasörüne yazılacak.
- `.env` ve `config/payment.php` dosyaları Claude tarafından okunmaz.
- `.env`, `storage/sessions/*` ve `storage/logs/*` Git takibine alınmaz.
- Veritabanı ve yüklenen dosyalar için düzenli yedekleme planlanacak.

## Bilinen Teknik Borçlar

- `Request::ip()` şu aşamada değiştirilmez. Proxy veya Cloudflare arkasına geçilince ayrı güvenlik görevi olarak çözülür ve admin rate limiting'den önce tamamlanmalıdır.
- Güvenlik başlıkları, SRI ve 405 yanıtı düzeltmesi ayrı görevlerde yapılır.

## Admin Panel Kuralları

Admin paneli aşağıdaki modülleri içerecektir:

- Dashboard
- Kategori yönetimi
- Marka yönetimi
- Ürün yönetimi
- Ürün görseli yönetimi
- Varyant yönetimi
- Stok yönetimi
- Sipariş yönetimi
- Müşteri yönetimi
- Kupon yönetimi
- Kargo yönetimi
- Raporlama
- Site ayarları

Admin paneli Bootstrap 5 ile responsive olacak ve masaüstü, tablet ile mobil cihazlarda düzgün çalışacaktır.

## Frontend Kuralları

- Bootstrap 5 kullanılacak.
- Mobil öncelikli responsive tasarım uygulanacak.
- Ana sayfada öne çıkan ürünler, kategoriler ve kampanyalar gösterilecek.
- Ürün listeleme sayfasında arama, kategori filtresi, fiyat filtresi ve sayfalama bulunacak.
- Ürün detay sayfasında görsel galerisi, stok durumu, sepete ekleme ve ürün bilgileri yer alacak.
- Sepet sayfasında adet güncelleme, ürün silme ve toplam tutar hesaplama AJAX ile yapılacak.
- Checkout sürecinde teslimat adresi, fatura adresi, kargo yöntemi, ödeme yöntemi ve sipariş özeti gösterilecek.
- Kullanıcıya işlem sonuçları için anlaşılır başarı ve hata mesajları gösterilecek.
- JavaScript işlemlerinde loading state ve hata yönetimi bulunacak.

## SEO Kuralları

- Tüm ürün ve kategori sayfaları SEO dostu URL kullanacak.
- URL yapısı örneği: `/urun/urun-adi`
- Her ürün için `meta_title` ve `meta_description` alanları olacak.
- Ürün görsellerinde `alt` etiketi kullanılacak.
- Canonical URL desteği eklenecek.
- Sitemap.xml ve robots.txt dosyaları oluşturulacak.
- Google Search Console ve Google Analytics 4 entegrasyonu planlanacak.

## Yasal Uyumluluk

Aşağıdaki sayfalar ve metinler projede yer almalıdır:

- Ön bilgilendirme formu
- Mesafeli satış sözleşmesi
- İade ve cayma hakkı politikası
- KVKK aydınlatma metni
- Çerez politikası
- Gizlilik politikası
- Teslimat ve kargo politikası
- İletişim sayfası
- Hakkımızda sayfası

Gıda ürünlerinde; içerik, alerjen, menşe, raf ömrü, saklama koşulu ve tüketim tarihi bilgileri eksiksiz gösterilmelidir.

## Geliştirme Sırası

Claude aşağıdaki sırayı takip edecektir:

1. MVC çekirdek altyapısı (tamamlandı)
2. Veritabanı migration dosyaları (001–018 tamamlandı)
3. Kategori ve marka yönetimi (tamamlandı; görsel/logo alanları Uploader ile sonra eklenecek)
4. Ürün yönetimi (model, controller, liste ve form view'ları tamamlandı)
5. Ürün görselleri ve varyantlar (Uploader ve ProductImage modeli tamamlandı; görsel controller'ı ve varyantlar bekliyor)
6. Stok yönetimi
7. Admin paneli
8. Sepet modülü
9. Sipariş modülü
10. Ödeme entegrasyonu
11. Kargo ve iade yönetimi
12. E-posta bildirimleri
13. Raporlama
14. SEO ve performans iyileştirmeleri
15. Yayına alma hazırlığı

## Çalışma Kuralları

- Claude tek seferde tüm projeyi yazmayacaktır.
- Claude her adımda yalnızca ilgili modülü geliştirecektir.
- Her adımın başında yapılacak işlerin listesi verilecektir.
- Her adımın sonunda oluşturulan veya değiştirilen dosyalar listelenecektir.
- Her dosyanın tam içeriği verilecektir.
- Kod çalıştırılamıyorsa kurulum ve test adımları net şekilde açıklanacaktır.
- Her modül tamamlandıktan sonra kullanıcı onayı beklenecektir.
- Kullanıcı onay vermeden sonraki modüle geçilmeyecektir.
- Hata oluşursa önce kök neden açıklanacak, ardından düzeltme yapılacaktır.
- Gereksiz dosya, gereksiz bağımlılık ve tekrar eden kod yazılmayacaktır.


## ZORUNLU İŞ AKIŞI

Her görevde şu sıra izlenecek:

1. Önce şu dosyaları oku:
   - DECISIONS.md
   - PROJE-DURUMU.md
   - DATABASE-DEPENDENCIES.md
   - TEST-COMMANDS.md

2. Görevi tek dosyaya indir.

3. Kod yazmadan önce şu raporu ver:
   - Etkilenecek dosya
   - Bağımlılıklar
   - Alınacak kararlar
   - Varsa şema değişikliği
   - Test planı

4. Kullanıcı onayı olmadan dosya oluşturma veya değiştirme.

5. Her seferinde yalnızca BİR dosya üret.

6. Dosyanın tam içeriğini ver.

7. Dosya sonunda şunu yaz:
   "DEVAM yazarsan sıradaki dosyaya geçeceğim."

8. Kullanıcı 'DEVAM' yazmadan sonraki dosyaya geçme.

9. Her dosya sonunda şu kontrolleri yap:
   - PHP dosyası ise: php -l <dosya>
   - SQL migration ise: bağımlılık sırasını kontrol et
   - Model ise: PDO prepared statement kullanıldığını kontrol et
   - View ise: XSS escaping kontrolü yap
   - Controller ise: CSRF ve yetkilendirme kontrolü yap

10. Görev bitince PROJE-DURUMU.md dosyasını güncelle.