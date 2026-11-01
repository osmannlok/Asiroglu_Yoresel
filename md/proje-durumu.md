# Proje Durumu — Aşıroğlu Yöresel Market

> Güncel durum kaydı. Kararlar: `DECISIONS.md` · Migration sırası: `DATABASE-DEPENDENCIES.md` · Test: `TEST-COMMANDS.md`

## 1. Özet

| **Proje** | Aşıroğlu Yöresel Market — yöresel gıda e-ticaret |
| **Stack** | PHP 8.2+, Custom MVC, MySQL 8/MariaDB, PDO, Bootstrap 5, Vanilla JS, Fetch |
| **Standart** | OOP, PSR-12, Türkçe yorumlar |
| **Çalışma** | Her görev tek dosya → rapor → onay → dosya. `DEVAM` olmadan sonraki dosyaya geçilmez |

## 2. Tamamlanan

**Çekirdek**
- [x] MVC: App, Router (middleware/group), Controller, Model, Database, Request, Response, Session, Csrf, Validator
- [x] `public/index.php`, `config/{config,database}.php`, `HomeController`, `layouts/main.php`
- [x] Migration 001–018 + `migrate.php` (checksum; gerçek MySQL’de henüz çalıştırılmadı)
- [x] `DECISIONS.md`, `DATABASE-DEPENDENCIES.md`, `TEST-COMMANDS.md`

**Admin**
- [x] Auth: User, Auth, AdminMiddleware, Admin/AuthController, admin login, DashboardController + view
- [x] Kategori/Marka: Model + Controller + index/form + görsel/logo (Uploader)
- [x] Ürün: Product, ProductImage, ProductVariant + Controller’lar + views + 6’şar route
- [x] Uploader (jpg/png/webp, 2 MB, 6000 px)
- [x] Stok: StockMovement, StockController, stock/{index,form,history}
- [x] Dashboard: ürün sayısı, kritik stok (FR-097)
- [x] Başlangıç stoğu > 0 → stock_movements (aynı transaction)

**Vitrin**
- [x] Cart + CartService + CartController + cart/index + 5 sepet route
- [x] Product::paginateStorefront + vitrin ProductController + products/{index,show}
- [x] layouts/main: menü (Ürünler, Sepet rozetli, arama, Giriş/Kayıt/Çıkış, Yönetim)
- [x] Müşteri Auth: AuthController, auth/{login,register}, 5 route; giriş/kayıtta mergeGuestCart

**Sipariş (başladı)**
- [x] Order modeli: place, changeStatus, findByNumber, getItems, getHistory, paginateByUser

**Bekleyen test**
- [ ] Yeni dosyalarda `php -l` + elle test (layouts/main, AuthController, auth views, App.php, CartService, Order)
- [ ] Migration 011+ gerçek MySQL’de çalıştırma

## 3. Migration’lar (001–018)

| No | Tablolar |
|---|---|
| 001–004 | users, addresses, categories, brands |
| 005–007 | products, product_images, product_variants |
| 008 | attributes, attribute_values, product_attributes |
| 009 | stock_movements |
| 010 | coupons (+deleted_at), coupon_usages |
| 011 | carts (user_id NOT NULL), cart_items |
| 012 | orders (+customer_email/phone), order_items, order_status_history |
| 013–014 | payments, payment_logs · shipping_methods, shipments |
| 015–016 | return_requests · reviews, wishlists |
| 017–018 | banners, pages, newsletter, contact · settings, activity_logs |

**Toplam:** 18 migration, 31 tablo (+ schema_migrations)

## 4. Modeller & Ana API

| Model | Not |
|---|---|
| Category | soft delete, tree, cycle check, slugExists |
| Brand | soft delete, getActive, slugExists |
| User | create() kapalı → createUser(); password_hash asla dönmez |
| Auth | oturumda sadece user_id; isAdmin / canAccessAdmin |
| Product | soft delete, findBySlug (FR-033), paginate + paginateStorefront, currentPrice |
| ProductImage | tek is_main, reorder, max 10 |
| ProductVariant | delete() kapalı → setActive(); stok sadece oluştururken |
| StockMovement | sadece record(); FOR UPDATE + transaction |
| Cart | üye sepeti; resolveSellable (stok/varyant/vitrin) |
| CartService | misafir (session) + üye; mergeGuestCart |
| Order | place, changeStatus, sorgular |

**Uploader:** products|categories|brands|banners · finfo + getimagesize · rastgele ad

## 5. Sıradaki

1. `ShippingMethod.php` (gerekli mi → migration 014’e bak)
2. `CheckoutService.php`
3. `CheckoutController.php` + views
4. App.php sipariş rotaları
5. Hesabım sipariş sayfaları
6. Coupon modeli + sipariş bağlama
7. Ödeme → Kargo → İade

## 6. Kritik Kararlar (özet)

- Misafir sepeti **sadece session**; `carts.user_id NOT NULL`
- Stok sipariş oluşurken düşer (FR-076 sapması, DECISIONS’ta kayıtlı)
- Kupon: used_count transaction’da; soft delete; COUNT(*) yerine used_count
- Yetki: oturumda sadece ID; rol her istekte DB’den
- SKU ürün+varyant arasında unique; soft-delete’li kayıtlar da sayılır
- Fiyat: price>0, discount_price<price; ondalık ayırıcı nokta
- Vitrin görünürlüğü (FR-033): pasif/silinmiş kategori/marka ürünü listelenmez
- Varyant silinmez (`is_active=0`); stok sadece oluştururken yazılır
- Sepet: max 50 kalem, kalem başına max 1000 adet; fiyat bilgilendirme, siparişte yeniden hesap
- `next` parametresi: sadece güvenli site-içi yol
- Admin/vitrin oturumu ortak (`_auth_user_id`); müşteri `/admin/giris` yapamaz

Tam liste → `DECISIONS.md`

## 7. Riskler

**Kritik**
- Migration 010–018 gerçek DB’de çalıştırılmadı → sepet/sipariş test edilemez
- `shipped/delivered` → `returned` stoğu otomatik geri eklemez (iade modülü)
- Ödemesi alınmış sipariş iptalinde iade Order’ın işi değil
- Admin + müşteri girişinde rate limiting yok; `Request::ip()` proxy düzeltmesi öncesi sunucu açılmamalı
- `.env` sohbette paylaşıldı → DB şifresi değiştirilmeli
- İkinci spesifikasyon seti (`GEREKSİNİMLER.MD` vb.) mevcut şema ile çelişiyor; hangi belge geçerli netleşmeli
- `AuthMiddleware.php` içinde AdminMiddleware kopyası var → silinmeli
- DECISIONS.md “Sepet Kuralları” bölümü eski kuralı içeriyor (session_id)

**Orta**
- FR-021 varyant kritik stok eşiği yok; ürün eşiği kullanılıyor
- E-posta doğrulama (FR-051) ve şifremi unuttum (FR-052) yok
- `layouts/main.php` doğrudan `$_GET`/`$_SERVER` okuyor
- Vitrin “Hesabım” (profil/adres/sipariş) henüz yok

**Düşük**
- CSP / güvenlik başlıkları, Bootstrap SRI, 405 JSON yok
- Admin girişleri activity_logs’a yazılmıyor

## 8. Son Güncelleme

2026-11-01 — vitrin menü, müşteri Auth, sepet, Order modeli. `php -l` + elle test bekliyor.