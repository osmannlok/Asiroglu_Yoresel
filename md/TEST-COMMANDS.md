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
php -l app/Services/CartService.php
php -l app/Controllers/CartController.php
php -l app/Views/cart/index.php
php -l app/Controllers/ProductController.php
php -l app/Views/products/index.php
php -l app/Views/products/show.php
php -l app/Controllers/AuthController.php
php -l app/Views/auth/login.php
php -l app/Views/auth/register.php
php -l app/Models/Order.php
php -l app/Models/ShippingMethod.php
php -l app/Services/CheckoutService.php
php -l app/Controllers/CheckoutController.php
php -l app/Views/checkout/index.php
php -l app/Views/checkout/complete.php

### Tüm PHP dosyalarını tek seferde tara

find app config public database -name "*.php" -print0 | xargs -0 -n1 php -l

Not: `config/payment.php` ve `.env` okunmaz. Tarama `config/payment.php` dosyasını da kapsıyorsa
bu dosya için sözdizimi kontrolü atlanır veya kullanıcı kendisi çalıştırır.

## Migration Kontrolü

- Uygulanan migration'lar 001–018'dir (migrate.php yazıldı).
- Sepet ve müşteri girişini elle test etmeden önce `php database/migrate.php --status` çıktısında 001–018'in hepsi `[uygulandı]` olmalı (`carts`/`cart_items` için 011 şart).


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
- `GET /sepet` boş sepet görünümünü döndürmeli.
- `GET /sepet/ozet` JSON dönmeli: `{"success":true,"count":0,...}`.
- `POST /sepet/ekle` CSRF başlığı olmadan 419 dönmeli.
- `POST /sepet/guncelle` için `key=p5v3` veya `key=12` kabul edilmeli; `key=abc` 422 dönmeli.
- Ürün listesinde arama, kategori, fiyat, sıralama ve sayfalama çalışmalı.
- `?min=850,50` gibi virgüllü fiyat reddedilmeli; ondalık ayırıcı nokta olmalı.
- `?sirala=gecersiz` uyarı göstermeli ve varsayılan sıralamaya dönmeli.
- `/urun/olmayan-slug` ve geçersiz slug 404 dönmeli.
- Pasif ürün, pasif kategori veya pasif markadaki ürün vitrinde görünmemeli.
- Varyantlı üründe varyant seçilmeden “Sepete Ekle” kilitli kalmalı.
- Stoksuz varyant seçilemez; stoktan fazla adet istenirse 422 dönmeli.

## Elle Test: Vitrin Menüsü ve Müşteri Girişi

1. Ziyaretçi: menüde "Ürünler", "Sepet", "Giriş", "Kayıt Ol" görünmeli; "Çıkış" ve "Yönetim Paneli" görünmemeli. Sepet rozeti 0 iken gizli olmalı.
2. Rozet: `/urunler` içinde varyantsız ürünü sepete ekle, rozet sayfa yenilenmeden artmalı. `/sepet` içinde adet değiştir, rozet toplam adede eşitlenmeli. Son kalemi çıkar, rozet kaybolmalı. Geri tuşuyla önceki sayfaya dönünce rozet doğru adedi göstermeli.
3. Üst arama kutusu: "fıstık" yazınca `/urunler?q=...` açılmalı, kutuda aranan metin görünmeli.
4. Kayıt (`/kayit`):
   - Boş gönderim: ad, soyad, e-posta, şifre hataları görünmeli; girilen değerler korunmalı, şifre alanları boş olmalı.
   - Aynı e-posta ile ikinci kayıt: "zaten kayıtlı" hatası.
   - `abc123` (kısa) ve `abcdefgh` (rakamsız): şifre hatası. Şifre ≠ tekrar: şifre alanında eşleşme hatası.
   - Telefon `0532 123 45 67` kabul, `12345` reddedilmeli.
   - Geçerli kayıt: ana sayfaya yönlenmeli, oturum açık olmalı; `users.user_type` değeri `customer` olmalı.
5. Giriş (`/giris`):
   - Yanlış şifre ve olmayan e-posta aynı mesajı vermeli: "E-posta veya şifre hatalı."; e-posta dolu, şifre boş kalmalı.
   - Pasif/banlı kullanıcı aynı mesajı almalı.
   - `/giris?next=/sepet` ile giriş: `/sepet`'e dönmeli. `?next=//evil.com`, `?next=/giris` ve `?next=https://evil.com` yok sayılıp `/`'a dönmeli.
   - Oturum açıkken `/giris` ve `/kayit` ana sayfaya yönlendirmeli.
6. Misafir sepeti birleştirme: misafirken sepete ürün ekle, sonra giriş yap veya kayıt ol. `cart_items`'a geçmeli, session sepeti boşalmalı, rozet aynı kalmalı. Üyenin sepetinde aynı ürün varsa adetler toplanmalı; stoğa sığmayan kalem için bilgi mesajı çıkmalı.
7. Çıkış: menüdeki "Çıkış" butonu POST yapmalı, "Çıkış yaptınız." mesajı görünmeli. CSRF'siz `POST /cikis` → 419. `GET /cikis` → 405.
8. Yetki: müşteri hesabıyla `/admin/giris` → "E-posta veya şifre hatalı."; müşteri oturumuyla `/admin` → 403. Admin/staff girişinden sonra menüde "Yönetim Paneli" görünmeli, müşteride görünmemeli.
9. `POST /giris` ve `POST /kayit` CSRF'siz → 419.

Elle test (migration 012 uygulandıktan sonra):
- Stok 5 olan ürüne 6 adetlik sipariş reddedilmeli; `orders` ve `order_items` içinde kayıt kalmamalı.
- Başarılı siparişte `stock_movements` içinde `outgoing` kaydı (`reference_type = order`) ve `order_status_history` içinde `NULL → pending` kaydı oluşmalı.
- `pending → cancelled` stoğu geri eklemeli (`reference_type = order_cancel`).
- `delivered → paid` gibi geçersiz geçiş `InvalidArgumentException` vermeli.
- `Order::create()`, `update()` ve `delete()` `BadMethodCallException` vermeli.
- `findForUser()` başka kullanıcının sipariş ID'siyle `null` dönmeli.

## Elle Test: Checkout (migration 012 + 014 uygulandı; en az bir aktif kargo yöntemi gerekir)

Örnek kargo: INSERT INTO shipping_methods (name, code, price, free_shipping_limit, sort_order) VALUES ('Standart Kargo','standard',89.00,750.00,1);

1. Boş sepetle `/odeme` → `/sepet`'e yönlenmeli. Satın alınamayan kalemle de `/sepet`'e dönmeli.
2. CSRF'siz `POST /odeme` → 419. `GET /odeme/ozet?kargo=abc` → 422 JSON.
3. Kargo değişince toplamlar sayfa yenilenmeden güncellenmeli; eşik altında "X ₺ daha ekleyin", eşik ve üstünde "Ücretsiz".
4. Eksik alanla gönderim: alan hataları görünmeli, değerler korunmalı. Telefon `12345` reddedilmeli.
5. Kapıda ödeme → sipariş `pending`; kart/havale → `awaiting_payment`.
6. Misafir siparişi: `orders.user_id` NULL, `customer_email` dolu. Üyede ad/e-posta/telefon ön dolu gelmeli.
7. Başarılı sipariş: sepet boşalmalı, stok düşmeli (`stock_movements` outgoing, reference=order), `/siparis/tamamlandi` açılmalı.
8. Stoğu yetmeyen kalem: sipariş reddedilmeli, `orders`/`order_items` kaydı kalmamalı, sepet korunmalı.
9. Başka oturum/üye ile `/siparis/tamamlandi` → ana sayfa (G-05). Adreste `<script>` kaçırılmalı (G-02).
10. Sepette "Ödemeye Geç" `/odeme`'ye gitmeli; stoksuz kalemde kilitli, kalem çıkarılınca aktifleşmeli.
11. ShippingMethod: `calculateFee` eşik altında price, eşikte 0; `remainingForFreeShipping` eşikte null; `delete()` BadMethodCallException; negatif ücret/bozuk `code` reddedilmeli.