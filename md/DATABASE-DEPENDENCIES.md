# Veritabanı Bağımlılık Haritası

> Migration sırasının tek kaynağı bu dosyadır.
> Mimari kararlar için `DECISIONS.md` önceliklidir.
> Son güncelleme: 2026-10-31 (FK listesi tamamlandı)

## Migration Sırası

001 users
002 addresses
003 categories
004 brands
005 products
006 product_images
007 product_variants
008 attributes, attribute_values, product_attributes
009 stock_movements
010 coupons, coupon_usages
011 carts, cart_items
012 orders, order_items, order_status_history + coupon_usages.order_id FK
013 payments, payment_logs
014 shipping_methods, shipments
015 return_requests
016 reviews, wishlists
017 banners, pages, newsletter_subscribers, contact_messages
018 settings, activity_logs

## Foreign Key Bağımlılıkları

Biçim: `tablo.kolon → hedef_tablo.kolon` | ON DELETE davranışı | tanımlandığı migration

### Mevcut migration'lar (001–009)

| Foreign key | ON DELETE | Migration |
|---|---|---|
| addresses.user_id → users.id | CASCADE | 002 |
| categories.parent_id → categories.id | SET NULL | 003 |
| products.category_id → categories.id | RESTRICT (varsayılan) | 005 |
| products.brand_id → brands.id | SET NULL | 005 |
| product_images.product_id → products.id | CASCADE | 006 |
| product_variants.product_id → products.id | CASCADE | 007 |
| attribute_values.attribute_id → attributes.id | CASCADE | 008 |
| product_attributes.product_id → products.id | CASCADE | 008 |
| product_attributes.attribute_id → attributes.id | CASCADE | 008 |
| product_attributes.attribute_value_id → attribute_values.id | SET NULL | 008 |
| stock_movements.product_id → products.id | CASCADE | 009 |
| stock_movements.product_variant_id → product_variants.id | SET NULL | 009 |
| stock_movements.created_by → users.id | SET NULL | 009 |

### Bekleyen migration'lar (010–018)

| Foreign key | ON DELETE | Migration |
|---|---|---|
| coupon_usages.coupon_id → coupons.id | CASCADE | 010 |
| coupon_usages.user_id → users.id | SET NULL | 010 |
| carts.user_id → users.id | CASCADE | 011 |
| carts.coupon_id → coupons.id | SET NULL | 011 |
| cart_items.cart_id → carts.id | CASCADE | 011 |
| cart_items.product_id → products.id | CASCADE | 011 |
| cart_items.product_variant_id → product_variants.id | CASCADE (karar) | 011 |
| orders.user_id → users.id | SET NULL | 012 |
| orders.coupon_id → coupons.id | SET NULL | 012 |
| order_items.order_id → orders.id | CASCADE | 012 |
| order_items.product_id → products.id | SET NULL (karar) | 012 |
| order_items.product_variant_id → product_variants.id | SET NULL (karar) | 012 |
| order_status_history.order_id → orders.id | CASCADE | 012 |
| order_status_history.changed_by → users.id | SET NULL | 012 |
| coupon_usages.order_id → orders.id | SET NULL (karar, ALTER TABLE) | 012 |
| payments.order_id → orders.id | CASCADE | 013 |
| payment_logs.payment_id → payments.id | SET NULL | 013 |
| payment_logs.order_id → orders.id | CASCADE | 013 |
| shipments.order_id → orders.id | CASCADE | 014 |
| shipments.shipping_method_id → shipping_methods.id | SET NULL | 014 |
| return_requests.order_id → orders.id | CASCADE | 015 |
| return_requests.order_item_id → order_items.id | SET NULL | 015 |
| return_requests.user_id → users.id | SET NULL | 015 |
| reviews.product_id → products.id | CASCADE | 016 |
| reviews.user_id → users.id | SET NULL | 016 |
| reviews.order_id → orders.id | SET NULL | 016 |
| wishlists.user_id → users.id | CASCADE | 016 |
| wishlists.product_id → products.id | CASCADE | 016 |
| activity_logs.user_id → users.id | SET NULL | 018 |

Not: ON DELETE değerleri `database-schema.sql` referansından alınmıştır. `DECISIONS.md` ile çelişen yerde `DECISIONS.md` geçerlidir. Çelişen tek yer `cart_items.product_variant_id` alanıdır; şemada SET NULL, kararda CASCADE.

## Bilinçli Olarak FK Olmayan Alanlar

- stock_movements.reference_id: Birden çok tabloya işaret edebildiği için (reference_type ile birlikte).
- activity_logs.entity_id: Aynı gerekçe (entity_type ile birlikte).
- carts.session_id: Oturum kimliğidir, tabloya bağlı değildir.
- payments.order_id ve shipments.order_id UNIQUE değildir. Gerekçe: bir siparişte birden fazla ödeme denemesi ve parçalı gönderim olabilir.

## FK İçermeyen Tablolar

users, brands, attributes, coupons, shipping_methods, banners, pages, newsletter_subscribers, contact_messages, settings

## Kritik Kurallar

- coupons, carts'tan önce oluşturulur.
- coupon_usages, orders'tan önce oluşturulur. coupon_usages.order_id kolonu 010'da FK'sız açılır.
- 012'de orders oluşturulduktan sonra coupon_usages.order_id için ALTER TABLE ile FK eklenir (ON DELETE SET NULL).
- cart_items.variant_key VIRTUAL generated column olur: IFNULL(product_variant_id, 0).
- cart_items UNIQUE index: uq_cart_item_product (cart_id, product_id, variant_key).
- cart_items.product_variant_id FK: ON DELETE CASCADE.
- order_items.product_id ve order_items.product_variant_id FK: ON DELETE SET NULL.
- orders.customer_email VARCHAR(191) NOT NULL, orders.customer_phone VARCHAR(20) NULL. idx_orders_customer_email indeksi bulunur.
- coupons.deleted_at DATETIME NULL alanı 010'da eklenir.
- Bir migration çalıştıysa dosyası değiştirilmez. Yeni kolon için yeni migration yazılır.
- Kaynak şema database/migrations klasörüdür. database-schema.sql yalnızca başlangıç referansıdır ve doğrudan çalıştırılmamalıdır.

## (karar VERİLEN KONULAR)

1. schema_migrations tablosu, planlanan 31 uygulama tablosuna dahil değildir.
   Bu tabloyu database/migrate.php kendisi otomatik oluşturacak.
   migrate.php, migration'ları sırayla çalıştıracak, başarıyla çalışanları 
   schema_migrations tablosuna kaydedecek ve aynı dosyayı tekrar çalıştırmayacak.

2. Rol modeli olarak users.user_type ENUM('customer','admin','staff') korunacak.
   roles, permissions, role_permissions ve user_roles tabloları şu aşamada 
   eklenmeyecek.
   Admin paneline erişim: user_type IN ('admin','staff')
   Tam yönetici yetkisi: user_type = 'admin'
   Personel yetkisi: user_type = 'staff'
   Ayrıntılı RBAC ileride gerekirse ayrı migration ile eklenecek.