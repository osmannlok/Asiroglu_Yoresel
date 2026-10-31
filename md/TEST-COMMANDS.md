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

### Tüm PHP dosyalarını tek seferde tara

find app config public database -name "*.php" -print0 | xargs -0 -n1 php -l

Not: `config/payment.php` ve `.env` okunmaz. Tarama `config/payment.php` dosyasını da kapsıyorsa
bu dosya için sözdizimi kontrolü atlanır veya kullanıcı kendisi çalıştırır.

## Migration Kontrolü

> `database/migrate.php` henüz oluşturulmadı. Aşağıdaki komutlar dosya oluşturulduktan sonra geçerlidir.

php database/migrate.php --status
php database/migrate.php

Beklenen durum:
- Uygulanan migration'lar 001–009'dur (migration 010 yazılana kadar).
- Başarıyla çalışan her dosya `schema_migrations` tablosuna kaydedilir ve tekrar çalıştırılmaz.

## Veritabanı Kontrolü

### Tablo sayısı

SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema = DATABASE();

Beklenen: 001–009 sonrası 11 uygulama tablosu (+ `schema_migrations` varsa 12). Planlanan toplam 31 uygulama tablosudur.

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