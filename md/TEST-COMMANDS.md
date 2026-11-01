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
php -l app/Controllers/Admin/ProductImageController.php
php -l app/Views/admin/products/images/index.php
php -l app/Views/admin/products/variants/form.php
php -l app/Models/StockMovement.php
php -l app/Controllers/Admin/StockController.php
php -l app/Views/admin/stock/index.php
php -l app/Views/admin/stock/form.php
php -l app/Views/admin/stock/history.php
php -l app/Models/Cart.php
php -l app/Services/CartService.php
php -l app/Controllers/CartController.php

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
8. Görsel: `/admin/urunler/{id}/gorseller`
   - JPG yükle (ilk görsel otomatik "Ana görsel" olmalı); `.php` uzantılı veya 2 MB üzeri dosya (reddedilmeli)
   - 10 görselden fazlası (reddedilmeli)
   - "Ana yap", alt metin kaydet, Yukarı/Aşağı sıralama (sayfa yenilenmeden; ağ hatasında eski sıraya dönmeli)
   - Ana görseli sil: sıradaki görsel ana olmalı, dosya `public/uploads/products/` altından kalkmalı
   - Başka ürünün görsel ID'siyle `/gorseller/{gid}/sil` (reddedilmeli)
9. Stok: `/admin/stok`
   - Stok 0 → "Tükendi", ≤ eşik → "Kritik"; varyantlı üründe satırlar varyant olmalı
   - Giriş +10, çıkış -3: geçmişte `önce → sonra` doğru, ürün stoğu güncellenmeli
   - Mevcut stoktan fazla çıkış (reddedilmeli); nedensiz hareket (reddedilmeli)
   - Düzeltme: hedef stok mevcutla aynıysa reddedilmeli; farklıysa fark kaydedilmeli
   - Varyantlı ürüne varyantsız hareket (reddedilmeli, form listesinde zaten çıkmaz)
   - Geçmiş filtresi ve sayfalamada filtrenin korunması
10. CSRF'siz POST → 419
11. Dashboard: `/admin` üç sayaç kartı (kategori, marka, ürün) göstermeli; eşik altı stoklu ürün "Kritik stok" tablosunda görünmeli, stok 0 olan "Tükendi" rozetiyle.
12. Başlangıç stoğu: stok 10 ile yeni ürün ve stok 20 ile yeni varyant ekle; `/admin/stok/gecmis` içinde `0 → 10` ve `0 → 20` giriş kayıtları (kaynak: product/variant) görünmeli. Stok 0 ile eklemede kayıt oluşmamalı.
13. Kategori görseli / marka logosu: JPG yükle, değiştir (eski dosya `public/uploads/categories|brands/` altından kalkmalı), "kaldır" işaretle; `.php` uzantılı dosya reddedilmeli.


## Elle Test: Sepet (migration 011 uygulandıktan sonra)

1. Misafir: `POST /sepet/ekle` (Fetch) ile ürün ekle; `GET /sepet/ozet` adedi ve toplamı vermeli; veritabanında `carts`/`cart_items` kaydı AÇILMAMALI.
2. Aynı ürün+varyantı tekrar ekle: adet artmalı, yeni kalem açılmamalı.
3. Stoktan fazla adet (reddedilmeli, "Yetersiz stok" mesajı); stok 0 ürün (reddedilmeli); varyantlı üründe varyantsız ekleme (reddedilmeli); pasif varyant (reddedilmeli).
4. Adet güncelle (`quantity` = 0 kalemi silmeli); geçersiz `key` (422).
5. CSRF başlığı olmadan POST → 419 (JSON).
6. Üye (giriş yapılınca): sepet `carts`/`cart_items` içinde tutulmalı; başka kullanıcının kalem ID'siyle güncelle/sil (404).
7. Birleştirme (müşteri girişi yazıldığında): misafir sepeti girişte üye sepetine eklenmeli, adetler toplanmalı, stoğa sığmayan kalem uyarı vermeli, session sepeti temizlenmeli.