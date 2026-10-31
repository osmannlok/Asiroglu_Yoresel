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

### Tüm PHP dosyalarını tek seferde tara

find app config public database -name "*.php" -print0 | xargs -0 -n1 php -l

Not: `config/payment.php` ve `.env` okunmaz. Tarama `config/payment.php` dosyasını da kapsıyorsa
bu dosya için sözdizimi kontrolü atlanır veya kullanıcı kendisi çalıştırır.

## Migration Kontrolü

- Uygulanan migration'lar 001–018'dir (migrate.php yazıldı).

Beklenen durum:
- Uygulanan migration'lar 001–009'dur (migration 010 yazılana kadar).
- Başarıyla çalışan her dosya `schema_migrations` tablosuna kaydedilir ve tekrar çalıştırılmaz.

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

1. Girişsiz `/admin` → `/admin/giris`'e yönlendirir
2. Admin ile giriş → `/admin` açılır; kategori/marka sayaçları görünür
3. Müşteri hesabıyla `/admin/giris` → "E-posta veya şifre hatalı."
4. Kategori: ekle, aynı slug ile ikinci kayıt (hata), düzenle (kendi slug'ı hata vermemeli), alt kategorili kategoriyi sil (reddedilmeli)
5. Marka: aynı akış; `javascript:` ile başlayan web sitesi reddedilmeli
6. CSRF'siz POST → 419