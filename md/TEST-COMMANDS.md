# Zorunlu Test Komutları

> Son güncelleme: 2026-10-31
> Yalnızca var olan dosyalar test edilir. Yeni dosya oluşturulunca ilgili `php -l` satırı bu dosyaya eklenir.

## PHP Sözdizimi

### Tek dosya (her PHP dosyası oluşturulduktan veya değiştirildikten sonra)

php -l <dosya>

### Mevcut çekirdek dosyalar

php -l public/index.php
php -l config/config.php
php -l config/database.php
php -l app/Core/App.php
php -l app/Core/Router.php
php -l app/Core/Controller.php
php -l app/Core/Model.php
php -l app/Core/Database.php
php -l app/Core/Request.php
php -l app/Core/Response.php
php -l app/Core/Session.php
php -l app/Core/Csrf.php
php -l app/Core/Validator.php
php -l app/Controllers/HomeController.php
php -l app/Views/layouts/main.php
php -l app/Views/home/index.php
php -l app/Models/Category.php
php -l app/Core/Auth.php
php -l app/Models/Brand.php
php -l app/Models/User.php
php -l app/Middleware/AdminMiddleware.php
php -l app/Controllers/Admin/AuthController.php
php -l app/Controllers/Admin/DashboardController.php
php -l app/Views/admin/auth/login.php
php -l app/Views/layouts/admin.php
php -l app/Controllers/Admin/CategoryController.php
php -l app/Controllers/Admin/BrandController.php
php -l app/Views/admin/dashboard/index.php
php -l app/Views/admin/categories/index.php
php -l app/Views/admin/categories/form.php
php -l app/Views/admin/brands/index.php
php -l app/Views/admin/brands/form.php
php -l app/Models/Product.php
php -l app/Controllers/Admin/ProductController.php
php -l app/Views/admin/products/index.php
php -l app/Views/admin/products/form.php
php -l app/Core/Uploader.php
php -l app/Models/ProductImage.php
php -l app/Models/ProductVariant.php
php -l app/Controllers/Admin/ProductVariantController.php
php -l app/Views/admin/products/variants/index.php

### Tüm PHP dosyalarını tek seferde tara

find app config public database -name "*.php" -print0 | xargs -0 -n1 php -l

Not: `config/payment.php` ve `.env` okunmaz. Tarama `config/payment.php` dosyasını da kapsıyorsa
bu dosya için sözdizimi kontrolü atlanır veya kullanıcı kendisi çalıştırır.

## Migration Kontrolü

- Uygulanan migration'lar 001–018'dir (migrate.php yazıldı).


## Veritabanı Kontrolü

### Tablo sayısı

SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema = DATABASE();

001–018 sonrası 31 uygulama tablosu (+ schema_migrations ile 32).

### Tablo listesi

SELECT table_name FROM information_schema.tables
WHERE table_schema = DATABASE()
ORDER BY table_name;

### Foreign key ilişkileri

SELECT table_name, column_name, referenced_table_name, referenced_column_name
FROM information_schema.key_column_usage
WHERE table_schema = DATABASE()
AND referenced_table_name IS NOT NULL
ORDER BY table_name;

### ON DELETE davranışı (DATABASE-DEPENDENCIES.md ile karşılaştırmak için)

SELECT constraint_name, table_name, referenced_table_name, delete_rule
FROM information_schema.referential_constraints
WHERE constraint_schema = DATABASE()
ORDER BY table_name, constraint_name;

## Git Kontrolü

git status
git diff

### Git'e girmemesi gereken dosyalar (çıktı boş olmalı; .gitkeep hariç)

git ls-files storage .env

Çıktıda `storage/sessions/sess_*`, `storage/logs/*` veya `.env` görünüyorsa:
git rm --cached <dosya>

## Elle Test: Admin Akışı

Ön koşul: `app/Core/Auth.php` yerinde olmalı (`mv app/Models/Auth.php app/Core/Auth.php`) ve
`app/Middleware/AuthMiddleware.php` silinmiş olmalı. Aksi halde `/admin` 500 verir.

1. Girişsiz `/admin` → `/admin/giris`'e yönlendirir
2. Admin ile giriş → `/admin` açılır; kategori/marka sayaçları görünür
3. Müşteri hesabıyla `/admin/giris` → "E-posta veya şifre hatalı."
4. Kategori: ekle, aynı slug ile ikinci kayıt (hata), düzenle (kendi slug'ı hata vermemeli), alt kategorili kategoriyi sil (reddedilmeli)
5. Marka: `/admin/markalar` → ekle; aynı slug (hata); düzenle (kendi slug'ı hata vermemeli); web sitesine `javascript:alert(1)` (reddedilmeli); ürünü olmayan markayı sil (onay modalı)
6. Ürün: en az bir kategori varken `/admin/urunler/olustur`
   - Aynı SKU ile ikinci ürün (hata)
   - Aynı slug (hata); slug boşken addan üretilmeli, çakışınca `-2` eklenmeli
   - İndirimli fiyat ≥ satış fiyatı (hata)
   - İndirim bitişi başlangıçtan önce (hata)
   - Fiyata `850,50` yaz (reddedilmeli), `850.50` (kabul)
   - Düzenlemede stok alanı görünmemeli, kritik eşik değişebilmeli
   - Listede arama (ad ve SKU), kategori ve durum filtresi, sayfalamada filtrelerin korunması
   - Stok 0 → "Tükendi", stok ≤ eşik → "Kritik" rozeti
   - Ürünü sil (onay modalı, listeden kalkmalı)
7. Varyant: ürün düzenlemeden `/admin/urunler/{id}/varyantlar` (ürün yoksa listeye hata ile döner)
   - Boş listede "Yeni Varyant" uyarısı görünmeli
   - "500 Gr" ekle; aynı üründe ikinci "500 Gr" (hata); başka üründe "500 Gr" (kabul)
   - Aynı SKU'yu başka varyant veya ürün SKU'suyla dene (hata)
   - İndirimli fiyat ≥ satış fiyatı (hata); fiyata `250,50` (reddedilmeli), `250.50` (kabul)
   - Düzenlemede başlangıç stoğu salt okunur olmalı ve değişmemeli
   - Başka ürünün varyant ID'siyle `/urunler/{id}/varyantlar/{vid}/duzenle` (listeye "Varyant bulunamadı." ile dönmeli)
   - Listede anahtarı kapat/aç: sayfa yenilenmemeli, toast görünmeli; ağ hatasında anahtar eski haline dönmeli
   - Varyant eklenince ürün listesinde "Varyantlı (n)" rozeti görünmeli 
8. CSRF'siz POST → 419