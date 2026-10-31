# Alınmış Mimari Kararlar

> Bu dosya projenin mimari kararlarının tek kaynağıdır.
> `CLAUDE.md` ile çelişki olursa bu dosya önceliklidir.
KARAR 1 — BELGE HİYERARŞİSİ:

Projenin belge hiyerarşisi şu şekilde olacak:

1. DECISIONS.md:
   Mimari, veritabanı, güvenlik ve uygulama kararlarının tek kaynağıdır.
   Diğer belgelerle çelişirse DECISIONS.md geçerlidir.

2. GEREKSINIMLER.MD:
   İş gereksinimleri, FR numaraları ve kabul kriterleri için referanstır.
   Mimari karar kaynağı değildir.
   DECISIONS.md ile çelişen maddeleri DECISIONS.md'e göre düzeltilecektir.

3. CLAUDE.md:
   Claude Code için kalıcı çalışma kurallarını içerir.
   DECISIONS.md ile çelişemez.
   Çelişki tespit edilirse DECISIONS.md kazanır.

4. database/migrations klasörü:
   Gerçek ve güncel veritabanı yapısının kaynağıdır.

5. database-schema.sql:
   Yalnızca başlangıç referansıdır.
   Güncel veritabanı kaynağı değildir.

## Veritabanı

- Veritabanı: MySQL 8 / MariaDB
- Charset: utf8mb4
- Collation: utf8mb4_unicode_ci
- Engine: InnoDB
- Tüm sorgular PDO prepared statement ile yazılır.
- `database-schema.sql` yalnızca başlangıç referansıdır.
- Gerçek ve güncel veritabanı kaynağı `database/migrations` klasöründeki migration dosyalarıdır.

## Migration Kuralları

- İlişkili tablo grupları tek migration dosyasında olabilir.
- Migration sırası foreign key bağımlılıklarına göre belirlenir.
- Dosya adı: NNN_create_<tablo_veya_grup>.sql
- Migration sırası DATABASE-DEPENDENCIES.md dosyasına göre belirlenir.
- Aynı kolon iki kez eklenmez.
- Bir migration çalıştıysa dosyası değiştirilmez.
- database/migrate.php migration'ları dosya adı sırasıyla çalıştırır; başarılıları schema_migrations'a checksum ile kaydeder.
- Çalışmış dosya değişirse migrate.php durur. Yeni dosya, uygulanmış son dosyadan sonra numaralandırılır.
- MySQL'de DDL geri alınamaz; dosya ortasında hata olursa o dosya kaydedilmez ve oluşan tablolar elle temizlenir.

## Stok Kuralları

- stocks tablosu yoktur.
- Stok hareketleri stock_movements tablosunda tutulur.
- Varyantsız üründe stok: products.stock_quantity
- Varyantlı üründe stok: product_variants.stock_quantity
- Sipariş oluşturma ve stok düşürme aynı transaction içinde yapılır.

## Sepet Kuralları

- carts yalnızca giriş yapmış kullanıcıların sepetleri içindir; carts.user_id her zaman dolu olmalıdır (NOT NULL).
- carts.session_id rezerve alandır, şu aşamada kullanılmaz.
- Misafir sepeti yalnızca PHP session'da tutulur (bkz. MİSAFİR SEPETİ).
- cart_items.variant_key VIRTUAL generated column olur.
- variant_key: IFNULL(product_variant_id, 0)
- UNIQUE index: uq_cart_item_product (cart_id, product_id, variant_key)
- cart_items.product_variant_id FK: ON DELETE CASCADE

## Sipariş Kuralları

- Sipariş numarası: ASY-YYYYMMDD-NNNNNN
- Sipariş durumları İngilizce olur.
- Durumlar: pending, awaiting_payment, paid, preparing, shipped, delivered, cancelled, payment_failed, returned
- Ödeme durumları: unpaid, paid, failed, refunded, partially_refunded
- orders.customer_email VARCHAR(191) NOT NULL olur.
- orders.customer_phone VARCHAR(20) NULL olabilir.
- orders tablosunda idx_orders_customer_email (customer_email) indeksi bulunur.
- order_items.product_id ve product_variant_id ON DELETE SET NULL olur.
- Sipariş geçmişi order_status_history tablosunda tutulur.
MİSAFİR SEPETİ:

Misafir sepeti yalnızca PHP session içinde tutulacaktır.

- Ziyaretçi sepete ürün eklediğinde veritabanına carts veya cart_items 
  kaydı açılmayacaktır.
- Misafir sepeti session içinde tutulacaktır.
- Sepet verisi session'da şu yapıda tutulabilir:
  $_SESSION['cart'] = [
      'items' => [
          [
              'product_id' => 5,
              'product_variant_id' => null,
              'quantity' => 2
          ]
      ]
  ];

- Kullanıcı giriş yaptığında session sepeti, kullanıcının mevcut DB sepetiyle 
  birleştirilecek.
- Aynı ürün ve aynı varyant varsa adetler toplanacak.
- Aynı ürün + varyant kombinasyonu yoksa yeni sepet kalemi oluşturulacak.
- Birleştirme işlemi transaction içinde yapılacak.
- Stok kontrolü birleştirme sırasında yapılacak.
- Stok yetersizse kullanıcı bilgilendirilecek ve mümkün olan maksimum adet eklenmeyecek; 
  sepet kaydı stok sınırını aşmayacak.

- Giriş yapmış kullanıcı için sepet carts ve cart_items tablolarında tutulacak.
- Sipariş oluşturulduğunda kullanıcı sepetinin status değeri 'converted' olacak.

- carts.session_id alanı şu aşamada misafir sepeti için kullanılmayacak.
- Bu alan ileride terk edilmiş sepet analizi veya kalıcı misafir sepeti 
  özelliği için rezerve olarak kalacak.

- carts tablosu yalnızca giriş yapmış kullanıcıların sepetleri için kullanılacak.
- carts.user_id NOT NULL'dır. Uygulandı: 011 migration'ı hiçbir veritabanına uygulanmadan
  önce (2026-10-31) bu karara göre düzeltildi; ALTER TABLE gerekmedi.
- Uygulandı: 011 migration'ında (2026-10-31).

## Ödeme ve Kargo İlişkileri

- payments.order_id için UNIQUE eklenmez.
- shipments.order_id için UNIQUE eklenmez.
- Gerekçe: Bir sipariş için birden fazla ödeme denemesi ve parçalı gönderim olabilir.

## Kupon Kuralları

- coupons.deleted_at DATETIME NULL alanı vardır.
- Kuponlar fiziksel silinmez.
- Admin panelinde yalnızca is_active = 0 veya deleted_at doldurma kullanılır.
- Kupon sorgularında deleted_at IS NULL koşulu zorunludur.
- coupons.used_count korunur.
- Kupon kullanımı yalnızca transaction içinde yönetilir.
- coupon_usages kaydı eklenirken used_count aynı transaction içinde artırılır.
- Sipariş iptalinde coupon_usages kaydı silinirken used_count azaltılır.
- Kupon limit kontrolünde COUNT(*) yerine used_count kullanılır.
- coupon_usages.order_id foreign key, orders migration sonrası ALTER TABLE ile eklenir.
- coupon_usages.order_id foreign key davranışı: ON DELETE SET NULL

## Kapsam

- Blog modülü şu aşamada kapsam dışıdır.

## Güvenlik

- Kart numarası, CVV ve son kullanma tarihi ne veritabanına ne de loglara yazılır.
- .env ve config/payment.php dosyaları Claude tarafından okunmaz.
- Tüm formlarda CSRF token kullanılır.
- Şifreler password_hash() ile saklanır.
- Admin paneli yetkilendirme ile korunur.

## Depo ve Dosya Yönetimi

- Proje belgeleri (DECISIONS.md, CLAUDE.md, PROJE-DURUMU.md, DATABASE-DEPENDENCIES.md,
  TEST-COMMANDS.md, GEREKSİNİMLER.MD vb.) md/ klasöründe tutulur ve esastır (2026-10-31).
- Belgelerin kopyası kökte veya başka klasörlerde tutulmaz.
- storage/sessions/ Git takibine alınmaz.
  - .gitignore: storage/sessions/* ve !storage/sessions/.gitkeep
- storage/logs/ Git takibine alınmaz.
  - .gitignore: storage/logs/* ve !storage/logs/.gitkeep
- .env dosyası Git takibine alınmaz.
  - .gitignore: .env

## Bilinen Teknik Borçlar

- Request::ip() şu aşamada değiştirilmez.
- Proxy veya Cloudflare arkasına geçildiğinde güvenilir proxy başlıkları ile IP tespiti yeniden değerlendirilir.
- Proxy uyumu ayrı bir güvenlik görevi olarak planlanır ve admin rate limiting'den önce çözülmelidir.
- Güvenlik başlıkları, SRI ve 405 yanıtı düzeltmesi ayrı görevlerde yapılır.

