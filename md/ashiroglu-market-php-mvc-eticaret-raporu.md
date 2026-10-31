# Aşıroğlu Yöresel Market — PHP MVC E-Ticaret Projesi Raporu

## 1. Proje Amacı

Aşıroğlu Yöresel Market için; ürün kataloğu, sepet, sipariş, ödeme, kargo, stok, kampanya ve müşteri yönetimini içeren **özel PHP MVC e-ticaret platformu** kurulacaktır.

Sistem hem masaüstü hem mobil cihazlarda Bootstrap 5 ile responsive çalışmalı, yöresel ürünlerin gramaj, raf ömrü, menşe ve saklama bilgilerini desteklemelidir.

## 2. Teknoloji Kararları

| Alan | Tercih | Gerekçe |
|---|---|---|
| Backend | PHP 8.2+, OOP | Modern tip sistemi, performans ve sürdürülebilirlik |
| Veritabanı erişimi | PDO + prepared statements | SQL injection riskini azaltır |
| Veritabanı | MySQL 8 / MariaDB | E-ticaret için yaygın, yeterli ve yönetilebilir |
| Frontend | Bootstrap 5, HTML5, CSS3 | Hızlı responsive arayüz |
| JavaScript | Vanilla JS + Fetch API | Ek framework yükü olmadan dinamik işlemler |
| Kimlik doğrulama | Session + `password_hash()` | Güvenli şifre saklama |
| Ödeme | Sanal POS / ödeme kuruluşu API | Kart verisi sunucuda tutulmaz |
| Sürüm kontrolü | Git + GitHub | Her modülün izlenebilir olması |
| Sunucu | Linux VPS, Nginx/Apache + PHP-FPM | Üretim için uygun performans |

## 3. Klasör Yapısı

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
│   │       └── SettingController.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Address.php
│   │   ├── Category.php
│   │   ├── Brand.php
│   │   ├── Product.php
│   │   ├── ProductImage.php
│   │   ├── ProductVariant.php
│   │   ├── Stock.php
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
│   ├── migrations/
│   └── seeds/
├── storage/
│   ├── logs/
│   └── cache/
└── tests/
```

## 4. Veritabanı Şeması

### Temel İlişkiler

```text
categories 1—N products
brands 1—N products
products 1—N product_images
products 1—N product_variants
products 1—N stocks
users 1—N addresses
users 1—N orders
orders 1—N order_items
orders 1—1 payments
orders 1—1 shipments
users 1—1 carts
carts 1—N cart_items
```

### Örnek products Tablosu

```sql
CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    brand_id INT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    sku VARCHAR(64) NOT NULL UNIQUE,
    barcode VARCHAR(64) NULL,
    short_description TEXT NULL,
    description TEXT NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_price DECIMAL(12,2) NULL,
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    stock_quantity INT NOT NULL DEFAULT 0,
    unit_type ENUM('adet','kg','gram','paket','litre') NOT NULL DEFAULT 'adet',
    weight DECIMAL(10,2) NULL,
    origin VARCHAR(255) NULL,
    storage_conditions VARCHAR(255) NULL,
    shelf_life VARCHAR(100) NULL,
    allergens TEXT NULL,
    meta_title VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (brand_id) REFERENCES brands(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 5. Modül Detayları

| Modül | Kapsam |
|---|---|
| Katalog | Kategori, marka, ürün, varyant, görsel, etiket, arama, filtre |
| Sepet | Misafir/üye sepeti, adet, varyant, stok kontrolü, AJAX güncelleme |
| Sipariş | Adres, kargo, kupon, vergi, sipariş durumu, sipariş geçmişi |
| Ödeme | Sanal POS, 3D Secure, kapıda ödeme, havale/EFT, ödeme durumu |
| Kargo | Kargo firması, takip numarası, teslimat durumu, iade talebi |
| Müşteri | Kayıt, giriş, şifre sıfırlama, adres defteri, siparişler |
| Admin | Dashboard, ürün, stok, sipariş, müşteri, kupon, rapor, ayarlar |
| Pazarlama | Kampanya, kupon, e-bülten, blog, SEO, sosyal medya entegrasyonu |

## 6. Güvenlik Kuralları

- Tüm formlarda CSRF token kullanılmalı.
- Tüm SQL sorguları PDO prepared statement olmalı.
- Kullanıcı girdileri çıktıya basılırken HTML escaping yapılmalı.
- Şifreler `password_hash()` ile saklanmalı, `password_verify()` ile doğrulanmalı.
- Oturum cookie’lerinde `HttpOnly`, `Secure` ve `SameSite` ayarları kullanılmalı.
- Yönetici paneli ayrı giriş, güçlü şifre, 2FA ve rate limiting ile korunmalı.
- Dosya yüklemelerinde uzantı, MIME tipi, boyut ve gerçek içerik kontrolü yapılmalı.
- Kart bilgisi, CVV veya tam kart numarası veritabanında saklanmamalı.
- Hata mesajları üretimde kullanıcıya teknik detay göstermemeli; log dosyasına yazılmalı.
- Veritabanı ve `uploads/` klasörü için düzenli otomatik yedekleme kurulmalı.

## 7. Test Planı

| Test türü | Kontrol edilecekler |
|---|---|
| Birim testi | Fiyat hesaplama, stok düşme, kupon, KDV |
| Entegrasyon testi | Sepet → sipariş → ödeme → kargo akışı |
| Güvenlik testi | SQL injection, XSS, CSRF, yetkisiz admin erişimi |
| Mobil test | Ürün sayfası, sepet, ödeme adımları |
| Stres testi | Aynı anda sepete ekleme ve stok güncelleme |
| Kullanıcı testi | Ürün bulma, sipariş verme, iade talebi |

## 8. Yayına Alma Kontrol Listesi

- Domain ve SSL aktif mi?
- `APP_ENV=production` ve hata gösterimi kapalı mı?
- Veritabanı şifreleri `.env` veya korumalı config dosyasında mı?
- Admin varsayılan şifresi değiştirildi mi?
- ETBİS kaydı ve yasal metinler tamam mı?
- Mesafeli satış, iade, KVKK ve gizlilik sayfaları yayında mı?
- Test ödemesi ve gerçek ödeme testi yapıldı mı?
- Kargo entegrasyonu ve takip numarası akışı test edildi mi?
- Otomatik yedekleme çalışıyor mu?
- Google Search Console ve Analytics kuruldu mu?

## 9. Claude Çalışma Modeli

Projeyi tek büyük promptla yaptırmayın. Her modülü ayrı bir görev olarak verin, her adımda çalışan kod ve test isteyin.

```text
Siz: Mimar, ürün sahibi, test eden ve onaylayan kişi
Claude: Dosya oluşturan, kod yazan, hatayı düzelten geliştirici
Git: Her modül sonunda commit yapılan güvenlik ağı
```

### Geliştirme Sırası

1. Proje kuralları ve `CLAUDE.md`
2. MVC çekirdek altyapısı
3. Veritabanı şeması ve migration
4. Ürün, kategori, stok modülü
5. Admin paneli
6. Sepet ve sipariş akışı
7. Ödeme entegrasyonu
8. Kargo, iade ve bildirimler
9. Güvenlik testi, performans ve yayına alma

## 10. Claude İçin `CLAUDE.md`

```text
# Aşıroğlu Yöresel Market — PHP MVC E-Ticaret Projesi

## Proje Amacı
Türkiye genelinde yöresel gıda ve yerel ürün satışı yapan bir e-ticaret sitesi 
geliştiriyoruz. Proje adı: Aşıroğlu Yöresel Market.

## Teknoloji Kuralları
- PHP 8.2+, OOP ve Custom MVC kullanılacak.
- MySQL/MariaDB kullanılacak.
- Tüm veritabanı işlemleri PDO ve prepared statement ile yapılacak.
- Frontend Bootstrap 5, HTML5, CSS3 ve Vanilla JavaScript ile yazılacak.
- Dinamik işlemler için Fetch API kullanılacak.
- jQuery, Laravel, Symfony veya hazır e-ticaret paketi kullanılmayacak.
- Kod PSR-12 uyumlu, Türkçe yorumlu ve modüler olacaktır.

## Güvenlik Kuralları
- SQL injection, XSS, CSRF ve dosya yükleme açıklarına karşı koruma uygula.
- Şifreler password_hash() ile saklanacak.
- Kart bilgileri asla veritabanına kaydedilmeyecek.
- Admin paneline yalnızca yetkili kullanıcılar erişebilecek.
- Production modunda hata detayları kullanıcıya gösterilmeyecek.

## Kod Yazma Kuralları
- Her yeni modülde önce dosya listesi, sonra tam dosya içerikleri ver.
- Her dosyanın başına kısa açıklama ekle.
- Controller’lar ince kalsın; iş mantığı Service/Model katmanında olsun.
- View dosyalarında tekrar eden yapılar için layout kullan.
- JavaScript işlemlerinde hata yönetimi ve loading state ekle.
- Her modül için kısa test talimatı ver.

## Çalışma Sırası
1. MVC çekirdek altyapısı
2. Veritabanı migration
3. Kategori ve ürün yönetimi
4. Admin paneli
5. Sepet
6. Sipariş
7. Ödeme entegrasyonu
8. Kargo ve iade
9. Raporlama
10. Yayına alma hazırlığı

## Önemli Kısıtlar
- Claude tek seferde tüm projeyi yazmayacak.
- Her adım tamamlandıktan sonra duracak ve kullanıcı onayı bekleyecek.
- Her adımda oluşturulan/değiştirilen dosyaları listeleyecek.
- Kod çalıştırılamıyorsa kurulum ve test adımlarını net verecek.
```

## 11. İlk Claude Code Promptu

```text
CLAUDE PROMPT:

"CLAUDE.md dosyasındaki kurallara uygun olarak Aşıroğlu Yöresel Market e-ticaret 
projesinin PHP MVC temel altyapısını kur.

Görevin:
1. Yukarıdaki klasör yapısını oluştur.
2. public/index.php front controller dosyasını yaz.
3. app/Core/App.php ve app/Core/Router.php sınıflarını yaz.
4. app/Core/Controller.php, app/Core/Model.php ve app/Core/Database.php sınıflarını yaz.
5. app/Core/Request.php, app/Core/Response.php, app/Core/Session.php, 
   app/Core/Csrf.php ve app/Core/Validator.php sınıflarını yaz.
6. config/config.php ve config/database.php örnek dosyalarını oluştur.
7. Basit bir HomeController ve Bootstrap 5 layout oluştur.
8. Ana sayfa route’unu tanımla.
9. Her dosyanın tam içeriğini ver.
10. Kurulum, yerel sunucuda çalıştırma ve test adımlarını yaz.

Kurallar:
- PHP 8.2+, OOP, PDO, prepared statement kullan.
- Bootstrap 5 CDN kullanabilirsin.
- Kod Türkçe yorumlu ve PSR-12 uyumlu olsun.
- Henüz ürün, sepet, ödeme veya admin paneli ekleme.
- Sadece sağlam, güvenli ve genişletilebilir temel altyapıyı kur."
```

## 12. İkinci Prompt: Veritabanı ve Ürünler

```text
CLAUDE PROMPT:

"PHP MVC e-ticaret projesinin veritabanı ve ürün yönetimi modülünü oluştur.

Gereksinimler:
1. database/migrations altında şu tabloları oluşturan SQL migration dosyaları yaz:
   users, addresses, categories, brands, products, product_images, 
   product_variants, stocks, settings.

2. app/Models altında ilgili Model sınıflarını PDO ile yaz.

3. Admin panelinde şu işlemler olsun:
   - Kategori ekleme, düzenleme, silme, listeleme
   - Marka yönetimi
   - Ürün ekleme, düzenleme, silme, listeleme
   - Çoklu ürün görseli yükleme
   - Stok güncelleme
   - Ürün aktif/pasif yapma

4. Ürün tablosunda fiyat, indirimli fiyat, KDV, stok, birim, gramaj, raf ömrü, 
   saklama koşulu, menşe, alerjen, SEO başlığı, SEO açıklaması, slug ve aktiflik alanları olsun.

5. Ürün listeleme sayfasında arama, kategori filtresi, fiyat filtresi ve sayfalama olsun.

6. Tüm sorgular PDO prepared statement olsun.
7. Dosya yüklemelerinde güvenlik kontrolleri olsun.
8. Admin paneli Bootstrap 5 ile responsive olsun.
9. Her dosyanın tam içeriğini ver.
10. Migration çalıştırma, test etme ve olası hataları giderme adımlarını yaz."
```

## 13. Üçüncü Prompt: Sepet ve Sipariş

```text
CLAUDE PROMPT:

"PHP MVC e-ticaret projesine sepet ve sipariş modülü ekle.

Gereksinimler:
1. carts ve cart_items tablolarını oluştur.
2. Misafir kullanıcılar için session tabanlı, üye kullanıcılar için veritabanı 
   tabanlı sepet desteği kur.
3. Ürün detay sayfasından Fetch API ile sepete ürün ekle.
4. Sepet sayfasında adet artırma/azaltma, ürün silme ve toplam tutar güncelleme AJAX olsun.
5. Stok kontrolü yap; stoktan fazla adet eklenemesin.
6. Kupon tablosu ve kupon uygulama mantığı ekle.
7. Checkout sürecinde teslimat adresi, fatura adresi, kargo yöntemi, ödeme yöntemi 
   ve sipariş özeti olsun.
8. orders, order_items, order_status_history, payments ve shipments tablolarını oluştur.
9. Sipariş oluşturulduğunda stok düşsün, sepet temizlensin ve sipariş numarası üretilsin.
10. Sipariş başarı ve başarısızlık sayfaları ile e-posta bildirimi altyapısını hazırla.
11. Tüm işlemler transaction içinde yapılsın.
12. Her dosyanın tam içeriğini ver ve test adımlarını yaz."
```

## 14. Dördüncü Prompt: Ödeme Entegrasyonu

```text
CLAUDE PROMPT:

"PHP MVC e-ticaret projesine güvenli ödeme entegrasyonu ekle.

Gereksinimler:
1. Ödeme sağlayıcısı olarak önce sandbox/test modunu destekleyen bir sanal POS yapısı kur.
2. config/payment.php içinde API key, secret key, base URL, success URL, fail URL 
   ve callback URL tanımları olsun.
3. CheckoutController içinde ödeme başlatma, ödeme doğrulama ve sipariş durumu 
   güncelleme akışını yaz.
4. 3D Secure akışını destekle.
5. Ödeme callback’lerinde imza/hash doğrulaması yap.
6. Ödeme başarılıysa siparişi 'odendi' durumuna getir.
7. Ödeme başarısızsa siparişi 'ödeme_başarısız' durumuna getir.
8. Kart bilgilerini asla veritabanına kaydetme.
9. Tüm ödeme isteklerinde log tut, ancak hassas verileri maskele.
10. Test kartı ile test senaryoları, hata durumları ve canlıya geçiş kontrol listesi hazırla.

Ödeme sağlayıcısı henüz belli değil; bu yüzden PaymentGatewayInterface adında 
bir arayüz ve örnek bir gateway sınıfı oluştur. Sonrasında PayTR veya iyzico 
için ayrı adapter yazılabilecek şekilde tasarımı genişletilebilir yap."
```

## 15. Yasal Hazırlık Listesi

- Ön bilgilendirme formu
- Mesafeli satış sözleşmesi
- İade ve cayma hakkı politikası
- KVKK aydınlatma metni
- Çerez politikası ve çerez onayı
- Gizlilik politikası
- Teslimat ve kargo politikası
- İletişim sayfası: ticaret unvanı/ad-soyad, vergi kimlik no, adres, telefon, e-posta
- Hakkımızda sayfası
- ETBİS kaydı
- E-fatura/e-arşiv fatura altyapısı
- Gıda ürünleri için etiket, raf ömrü, saklama koşulu ve alerjen bilgileri

## 16. Operasyonel İhtiyaçlar

- Ürün fotoğraf çekimi veya profesyonel görsel seti
- Ürün açıklamaları, gramaj, içerik ve alerjen bilgileri
- Barkod/SKU planı
- Kargo anlaşması: Yurtiçi, Aras, MNG, Sürat veya PTT Kargo
- Kırılgan ürün paketleme malzemesi
- Sıvı ürünler için sızdırmaz ambalaj
- İade süreci için müşteri hizmetleri akışı
- Muhasebe/e-fatura entegrasyonu
- Sosyal medya hesapları ve içerik planı

## 17. İlk 90 Günlük Yol Haritası

| Dönem | Yapılacaklar |
|---|---|
| 1–2. hafta | İş modeli, ürün listesi, kategori yapısı, kargo/ödeme araştırması |
| 3–4. hafta | Yasal metinler, ETBİS, domain/hosting, marka görselleri |
| 5–7. hafta | MVC altyapı, veritabanı, ürün yönetimi, admin paneli |
| 8–9. hafta | Sepet, ödeme, kargo ve sipariş akışı |
| 10. hafta | Test siparişleri, stok, iade, mobil uyumluluk testleri |
| 11. hafta | Ödeme kuruluşu onayı, canlı ortam kurulumu |
| 12. hafta | Ürün yüklemeleri, SEO, reklam ve lansman |
