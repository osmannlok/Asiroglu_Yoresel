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
- [x] Migration 001–009
- [x] Kalıcı dosyalar: `DECISIONS.md`, `DATABASE-DEPENDENCIES.md`, `TEST-COMMANDS.md`, `PROJE-DURUMU.md`
- [x] Proje durum raporu hazırlandı ve onaylandı
- [x] Kritik kararlar onaylandı (bkz. Bölüm 7)
- [x] `DATABASE-DEPENDENCIES.md` FK listesi tamamlandı (2026-10-31)
- [x] `TEST-COMMANDS.md` var olmayan dosya referansları temizlendi (2026-10-31)
- [x] `PROJE-DURUMU.md` güncellendi (2026-10-31)

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

Toplam: 9 migration dosyası, 11 tablo.

## 4. Oluşturulan Modeller

- Şu anda yalnızca soyut `App\Core\Model` sınıfı vardır.
- `app/Models/` altında alt sınıf model henüz yoktur.

## 5. Sıradaki Dosya

- `CLAUDE.md` düzeltmesi (durum adları, `stocks` ve `logs` tabloları, `DECISIONS.md` ile uyum)

Ardından sırayla: `app/Views/home/index.php` → `database/migrate.php` (yoksa) → `database/migrations/010_create_coupons.sql`.

## 6. Bekleyen Migration Dosyaları

| No | Tablolar |
|---|---|
| 010 | coupons, coupon_usages |
| 011 | carts, cart_items |
| 012 | orders, order_items, order_status_history + `coupon_usages.order_id` FK |
| 013 | payments, payment_logs |
| 014 | shipping_methods, shipments |
| 015 | return_requests |
| 016 | reviews, wishlists |
| 017 | banners, pages, newsletter_subscribers, contact_messages |
| 018 | settings, activity_logs |

Bekleyen: 9 migration dosyası, 20 tablo. Planlanan toplam: 31 tablo.

## 7. Alınmış Kritik Kararlar

1. **Kupon sayacı:** `coupons.used_count` korunur. `coupon_usages` kaydı eklenirken `used_count` aynı transaction içinde artırılır. Sipariş iptalinde kullanım kaydı silinirken `used_count` azaltılır. Limit kontrolünde `COUNT(*)` yerine `used_count` kullanılır.
2. **Kupon silme:** Kuponlar fiziksel silinmez. Admin panelinde yalnızca `is_active = 0` veya `deleted_at` doldurma kullanılır. Kupon sorgularında `deleted_at IS NULL` koşulu zorunludur.
3. **Şema kaynağı:** `database-schema.sql` yalnızca başlangıç referansıdır. Gerçek ve güncel veritabanı kaynağı `database/migrations` klasörüdür.
4. **`md/` klasörü:** Kopya dosyalar kullanılmaz. Proje kökündeki dosyalar esastır. `md/` klasörü `.gitignore`'a eklenir veya içeriği `docs/archive` altına taşınır.
5. **Oturum dosyaları:** `storage/sessions/` Git takibine alınmaz. `.gitignore`: `storage/sessions/*` ve `!storage/sessions/.gitkeep`.
6. **Log dosyaları:** `storage/logs/` Git takibine alınmaz. `.gitignore`: `storage/logs/*` ve `!storage/logs/.gitkeep`.
7. **Ortam dosyası:** `.env` Git takibine alınmaz. `.gitignore`'a `.env` satırı eklenir.
8. **`Request::ip()`:** Şu aşamada değiştirilmez. Proxy veya Cloudflare arkasına geçildiğinde güvenilir proxy başlıkları ile IP tespiti yeniden değerlendirilir.
9. **Ayrı görevler:** Güvenlik başlıkları, SRI ve 405 yanıtı düzeltmesi ayrı görevlerde yapılır.
10. **Migration sırası:** 001–018 sırası Bölüm 3 ve Bölüm 6'daki gibidir ve `DATABASE-DEPENDENCIES.md` ile aynıdır.

## 8. Bilinen Riskler

### Kritik

- `app/Views/home/index.php` bozuk: PHP/HTML yerine klasör ağacı metni içeriyor.
- `database/migrate.php` doğrulanamadı veya yok. `TEST-COMMANDS.md` migration komutları bu dosyaya dayanıyor.
- `CLAUDE.md` ile `DECISIONS.md` çelişiyor: durum adı (`odendi` / `paid`, `odeme_basarisiz` / `payment_failed`) ve tablo adı (`stocks`, `logs`). `DECISIONS.md` önceliklidir ama `CLAUDE.md` düzeltilmeden 012 ve 013'te yanlış kod üretme riski var.
- İkinci bir spesifikasyon seti (`GEREKSİNİMLER.MD`, `00-PROJE-TALİMATI.MD`, `02`, `03`, `04`) mevcut şema ile çelişiyor ve `gereksinimler.md`'yi "tek doğruluk kaynağı" ilan ediyor. Farklar: `roles`/`permissions` tabloları, `order_addresses`/`order_shipments`, varyant `price_difference`, varyant `critical_stock_quantity`, ayrı iade durumları, alan adları (`name`/`sale_price`/`tax_rate`/`stock`), `resources/views` ve `Services`/`Repositories` klasörleri. Hangi belge setinin geçerli olduğu netleşmeli.
- `storage/sessions` dosyaları Git'e girmiş olabilir. `git ls-files storage .env` ile kontrol edilmeli, gerekirse `git rm --cached` uygulanmalı. `.gitignore` doğrulanamadı.

### Orta

- `database-schema.sql` güncel tek kaynak değildir. `variant_key`, `customer_email`, `coupons.deleted_at` kararlarını içermez. `carts`, `coupons` tablosundan önce tanımlıdır; doğrudan çalıştırılmamalıdır.
- Sepet modeli belirsiz: `CLAUDE.md` misafir sepetini session tabanlı, şema ise `carts.session_id` ile DB tabanlı gösteriyor. 011'den önce karar verilmeli.
- Model API adları farklı: `model-ekle` skill'i `findById` / `getAll` / `paginate` istiyor, `Model.php`'de `find` / `all` var ve `paginate` yok.
- Dosya adı büyük/küçük harf riski: `proje-durumu.md` / `PROJE-DURUMU.md`, `proje-kontrol-listesi.md` / `PROJE-KONTROL-LISTESI.md`, `GEREKSİNİMLER.MD` / `gereksinimler.md`. Linux sunucuda bunlar farklı dosyadır.
- `ashiroglu-market-php-mvc-eticaret-raporu.md` eski kararları içeriyor (`stocks`, `odendi`, orders 1—1 payments/shipments). `md/` altına taşınmalı.
- `md/` klasöründe kopya dosyalar var ve sapma riski taşıyor.
- `Request::ip()` proxy veya Cloudflare arkasında yanlış IP verir. Admin rate limiting'den önce çözülmeli.

### Düşük

- Güvenlik başlıkları (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, HSTS), Bootstrap CDN SRI ve admin rate limiting henüz yok.
- `Router` 405 yanıtı Fetch istekleri için JSON değil ve `Allow` başlığı yok.
- Bilinmeyen bir yola yapılan POST, CSRF kontrolü route eşleşmesinden önce yapıldığı için 404 yerine 419 döner.
- Kupon iptalinde `coupon_usages` kaydı silindiği için kullanım denetim izi kaybolur. Gerekirse sonradan `activity_logs` ile desteklenmeli.

## 9. Son Doğrulama Tarihi

- 2026-10-31

## Teknik Kararlar

- schema_migrations tablosu, 31 uygulama tablosuna dahil değildir.
- schema_migrations tablosu database/migrate.php tarafından otomatik oluşturulur.
- Kullanıcı yetkilendirmesi şu aşamada users.user_type ENUM ile yönetilir.
- Admin paneline erişim: user_type IN ('admin','staff'). Tam yönetici yetkisi: user_type = 'admin'.
- roles, permissions, role_permissions ve user_roles tabloları şu aşamada oluşturulmaz.
- Ayrıntılı RBAC ihtiyacı ileride ayrı migration ile değerlendirilecektir.

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

KARAR 2 — MİSAFİR SEPETİ:

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
- Bu nedenle carts.user_id alanı NOT NULL olacak şekilde değerlendirilecek.
- Ancak mevcut carts tablosu henüz oluşturulmadıysa user_id NOT NULL yapılacak.
- Eğer carts tablosu zaten oluşturulduysa şema değişikliği yapılmayacak; 
  bu karar yeni migration gerektirir.
EK KARAR:

carts tablosu yalnızca giriş yapmış kullanıcıların sepetleri için kullanılacak.

- carts.user_id INT UNSIGNED NOT NULL olacak.
- carts.user_id, users(id) tablosuna foreign key verecek.
- Foreign key davranışı ON DELETE CASCADE olacak.
- carts.session_id VARCHAR(128) NULL olarak kalacak.
- session_id şu aşamada kullanılmayacak; ileride rezerve alandır.
- 'user_id veya session_id en az biri dolu olmalı' kuralı kaldırılacak.
- Yeni kural: carts.user_id her zaman dolu olmalıdır.
- Misafir sepeti yalnızca PHP session içinde tutulacaktır.
- Bu karar yalnızca carts tablosu henüz oluşturulmadıysa 011 migration'ında uygulanacak.
- carts tablosu zaten oluşturulduysa şema değişikliği yapılmayacak; 
  yeni bir migration ile ALTER TABLE yapılacak.


## Sıradaki Adım


- Ardından `database/migrations/010_create_coupons.sql` dosyasının oluşturulması.