# AŞİROĞLU YÖRESEL — Gereksinim Belgesi (SRS)

## 1. Proje Özeti
- Proje adı: AŞİROĞLU YÖRESEL
- Amaç: Türkiye genelinde yöresel gıda ürünleri satan, tek satıcılı bir e-ticaret sitesi geliştirmek.
- Teknoloji: PHP 8.2+ Custom MVC, OOP, PDO, Vanilla JavaScript, Fetch API, Bootstrap 5, HTML5, CSS.
- Dil ve para birimi: Türkçe, Türk Lirası (TRY).
- Ürün tipi: Yalnızca fiziksel yöresel gıda ürünleri.
- Satış modeli: Tek satıcılı mağaza.
- Kargo: Manuel kargo takibi; otomatik kargo firması API entegrasyonu MVP kapsamında değildir.
- Ödeme: Kredi/banka kartı, havale/EFT, kapıda ödeme ve ödeme sağlayıcısı üzerinden desteklenen diğer yöntemler.

## 2. Paydaşlar ve Roller
| Rol | Yetkiler |
|---|---|
| Ziyaretçi | Ürün görüntüleme, arama, filtreleme, sepete ekleme |
| Müşteri | Kayıt/giriş, profil, adres, sipariş oluşturma, sipariş takibi |
| Yönetici | Ürün, kategori, marka, stok, sipariş, kullanıcı, kupon ve kampanya yönetimi |
| Süper Admin | Yönetici ekleme, rol/izin yönetimi, sistem ayarları, ödeme ve kargo ayarları |

## 3. Teknoloji ve Mimari Kuralları
- Backend: PHP 8.2+, Custom MVC mimarisi, OOP.
- Veritabanı: MySQL/MariaDB; tüm sorgular PDO ve prepared statement ile yapılmalıdır.
- Frontend: Bootstrap 5, Vanilla JavaScript, Fetch API; jQuery kullanılmayacaktır.
- Şablonlar: Ayrı View dosyaları ve ortak layout yapısı kullanılacaktır.
- Router: SEO dostu URL yapısı kullanılacaktır; örn. `/urun/urun-adi`, `/kategori/kategori-adi`.
- Oturum: PHP Session; “beni hatırla” için güvenli, HttpOnly ve Secure cookie kullanılacaktır.
- Şifre: `password_hash()` ve `password_verify()` kullanılacaktır.
- Güvenlik: CSRF token tüm POST formlarında zorunludur; çıktılarda XSS koruması için `htmlspecialchars()` kullanılacaktır.
- Dosya yükleme: Uzantı, MIME type, boyut kontrolü ve benzersiz dosya adı uygulanacaktır.
- Ortam değişkenleri: `.env` dosyasında tutulacaktır; veritabanı ve API anahtarları kod içinde sabitlenmeyecektir.
- Hata yönetimi: Kullanıcıya sade hata mesajı gösterilecek, teknik hatalar log dosyasına yazılacaktır.

## 4. İşlevsel Gereksinimler

### 4.1 Ana Sayfa ve Ürün Vitrini
- FR-001: Ana sayfada öne çıkan ürünler, yeni ürünler, indirimli ürünler ve kategoriler gösterilecektir.
- FR-002: Ziyaretçi ürünleri kategoriye, markaya, fiyata, stok durumuna ve sıralamaya göre filtreleyebilecektir.
- FR-003: Ürün arama; ürün adı, açıklama ve SKU üzerinden çalışacaktır.
- FR-004: Ürün listeleme sayfasında sayfalama bulunacaktır.
- FR-005: Ürün kartında görsel, ürün adı, fiyat, indirimli fiyat varsa indirim oranı, stok durumu ve “Sepete Ekle” butonu yer alacaktır.
- FR-006: Tükendi ürünlerde “Sepete Ekle” yerine “Tükendi” bilgisi gösterilecektir.

### 4.2 Ürün Yönetimi
- FR-010: Yönetici ürün ekleyebilecek, düzenleyebilecek, pasifleştirebilecek ve silebilecektir.
- FR-011: Ürün kaydında şu alanlar bulunacaktır: ad, slug, SKU, kısa açıklama, uzun açıklama, kategori, marka, KDV oranı, satış fiyatı, indirimli fiyat, stok adedi, kritik stok eşiği, durum, SEO başlık, SEO açıklama.
- FR-012: Yönetici bir ürüne birden fazla görsel yükleyebilecektir; ilk görsel kapak görseli olacaktır.
- FR-013: Görseller sürüklenebilir şekilde sıralanabilecektir.
- FR-014: Ürün detay sayfasında galeri, fiyat, KDV dahil fiyat, stok bilgisi, açıklama, teslimat bilgisi ve benzer ürünler yer alacaktır.
- FR-015: Ürün slug’ları benzersiz ve SEO dostu olacaktır.

### 4.3 Ürün Varyantları ve Stok
- FR-020: Yönetici ürüne varyant tanımlayabilecektir; örneğin “500 Gr”, “1 Kg”, “2 Kg” gibi paket/gramaj seçenekleri.
- FR-021: Her varyant için ayrı SKU, fiyat farkı, stok adedi ve kritik stok eşiği tanımlanabilecektir.
- FR-022: Ürün detay sayfasında kullanıcı varyant seçebilecektir; seçilen varyantın fiyatı ve stok durumu anlık güncellenecektir.
- FR-023: Stok, sipariş onaylandığında düşülecektir.
- FR-024: Stok adedi kritik eşik altına indiğinde yönetici panelinde uyarı gösterilecektir.
- FR-025: Stok 0 olduğunda ilgili ürün/varyant satışa kapatılacaktır.
- FR-026: Yönetici manuel stok girişi, çıkışı ve düzeltmesi yapabilecektir; işlem nedeni kaydedilecektir.

### 4.4 Kategori ve Marka Yönetimi
- FR-030: Yönetici hiyerarşik kategori oluşturabilecektir.
- FR-031: Kategori; ad, slug, açıklama, görsel, üst kategori ve aktiflik alanlarına sahip olacaktır.
- FR-032: Yönetici marka ekleyebilecek, düzenleyebilecek ve pasifleştirebilecektir.
- FR-033: Kategori veya marka pasifleştirildiğinde ilgili ürünler vitrinde listelenmeyecek ancak sipariş geçmişi etkilenmeyecektir.

### 4.5 Sepet
- FR-040: Ziyaretçi ürünü sepete ekleyebilecektir; sepet session üzerinden tutulacaktır.
- FR-041: Kullanıcı giriş yaptığında misafir sepeti üye sepetiyle birleştirilecektir.
- FR-042: Kullanıcı ürün adedini güncelleyebilecek, ürünü sepetten çıkarabilecek ve sepet toplamını görebilecektir.
- FR-043: Sepet işlemleri sayfa yenilenmeden AJAX/Fetch API ile yapılacaktır.
- FR-044: Sepete ekleme sırasında stok kontrolü yapılacaktır; stok yetersizse kullanıcı bilgilendirilecektir.
- FR-045: Sepette ara toplam, indirim, kargo ücreti, KDV bilgisi ve genel toplam gösterilecektir.
- FR-046: Sepet sayfasında ücretsiz kargo eşiğine kalan tutar gösterilecektir.

### 4.6 Üyelik ve Hesap Yönetimi
- FR-050: Kullanıcı ad, e-posta, telefon ve şifre ile kayıt olabilecektir.
- FR-051: E-posta doğrulaması uygulanacaktır.
- FR-052: Kullanıcı şifremi unuttum akışını kullanabilecektir.
- FR-053: Kullanıcı profil bilgilerini güncelleyebilecektir.
- FR-054: Kullanıcı birden fazla teslimat adresi ekleyebilecek, düzenleyebilecek ve varsayılan adres seçebilecektir.
- FR-055: Kullanıcı sipariş geçmişini ve her siparişin durumunu görebilecektir.
- FR-056: Kullanıcı hesabını kapatabilecektir; kişisel veriler KVKK uyumlu şekilde anonimleştirilecektir.

### 4.7 Sipariş Akışı
- FR-060: Kullanıcı sepetten checkout adımına geçebilecektir.
- FR-061: Checkout adımları: teslimat adresi, kargo yöntemi, ödeme yöntemi, sipariş özeti ve onay.
- FR-062: Kullanıcı misafir olarak da sipariş verebilecektir; ancak e-posta, telefon ve teslimat adresi zorunludur.
- FR-063: Sipariş özetinde ürünler, adetler, birim fiyatlar, ara toplam, indirim, kargo, KDV ve genel toplam gösterilecektir.
- FR-064: Sipariş oluşturulduğunda benzersiz sipariş numarası üretilecektir.
- FR-065: Başarılı sipariş sonrası kullanıcıya sipariş onay e-postası gönderilecektir.
- FR-066: Sipariş durumları şunlar olacaktır: Beklemede, Ödeme Bekliyor, Ödendi, Hazırlanıyor, Kargoya Verildi, Teslim Edildi, İptal Edildi, İade Sürecinde, İade Edildi.
- FR-067: Kargo takibi manuel olacaktır; yönetici kargo firması, takip numarası ve kargo tarihi girebilecektir.
- FR-068: Kargo takip numarası girildiğinde müşteriye bilgilendirme e-postası gönderilecektir.
- FR-069: Müşteri sipariş detayında kargo firması, takip numarası ve sipariş durumunu görebilecektir.

### 4.8 Ödeme Yöntemleri
- FR-070: Ödeme yöntemleri: kredi/banka kartı, havale/EFT, kapıda ödeme ve ödeme sağlayıcısı tarafından desteklenen alternatif yöntemler.
- FR-071: Kredi kartı ödemeleri ödeme sağlayıcısı üzerinden alınacaktır; kart bilgileri site veritabanında saklanmayacaktır.
- FR-072: Havale/EFT seçilirse sistem banka hesap bilgilerini ve açıklama/reference kodunu gösterecektir.
- FR-073: Havale/EFT ödemesinde yönetici ödemeyi manuel onaylayabilecektir.
- FR-074: Kapıda ödeme seçilirse sipariş “Beklemede” durumunda oluşturulacaktır.
- FR-075: Ödeme sağlayıcısından gelen callback/webhook imzası doğrulanacaktır.
- FR-076: Başarılı ödeme sonrası sipariş “Ödendi” durumuna alınacak ve stok düşülecektir.
- FR-077: Başarısız ödeme sonrası kullanıcı bilgilendirilecek ve sipariş “Ödeme Bekliyor” veya “İptal Edildi” durumunda tutulacaktır.

### 4.9 İndirim, Kupon ve Kargo
- FR-080: Yönetici ürüne indirimli fiyat tanımlayabilecektir.
- FR-081: Yönetici yüzde veya sabit tutarlı kupon oluşturabilecektir.
- FR-082: Kupon alanları: kod, indirim tipi, indirim değeri, minimum sepet tutarı, başlangıç/bitiş tarihi, toplam kullanım limiti, kullanıcı başına limit, aktiflik.
- FR-083: Kupon yalnızca geçerli koşullarda uygulanabilecektir.
- FR-084: Yönetici ücretsiz kargo eşiği tanımlayabilecektir; örn. 750 TL üzeri siparişlerde ücretsiz kargo.
- FR-085: Kargo ücreti yönetim panelinden tanımlanabilecektir.
- FR-086: İndirim ve kupon uygulamaları sipariş özetinde ayrı ayrı gösterilecektir.

### 4.10 Kapsamlı Yönetim Paneli
- FR-090: Yönetim paneli yalnızca yetkili rollerle erişilebilecektir.
- FR-091: Panelde ürün, kategori, marka, varyant, stok, sipariş, müşteri, kupon, kampanya ve sistem ayarları yönetilebilecektir.
- FR-092: Yönetici sipariş durumunu güncelleyebilecek, sipariş notu ekleyebilecek ve sipariş geçmişini görebilecektir.
- FR-093: Yönetici kargo firması, takip numarası ve teslimat bilgisi girebilecektir.
- FR-094: Panelde günlük/haftalık/aylık satış, sipariş sayısı, en çok satan ürünler, düşük stoklu ürünler ve müşteri sayısı raporları bulunacaktır.
- FR-095: Yönetici site ayarlarını düzenleyebilecektir: site adı, logo, iletişim bilgileri, sosyal medya, kargo ücreti, ücretsiz kargo eşiği, KDV varsayılanı.
- FR-096: Yönetici roller ve yetkiler tanımlayabilecektir.
- FR-097: Kritik stok uyarıları panel ana ekranında listelenecektir.

## 5. İşlevsel Olmayan Gereksinimler
- NFR-001: Tüm veritabanı işlemleri PDO prepared statement ile yapılacaktır.
- NFR-002: Şifreler bcrypt veya Argon2id ile hashlenecektir.
- NFR-003: Tüm POST isteklerinde CSRF koruması bulunacaktır.
- NFR-004: Tüm kullanıcı girdileri sunucu tarafında doğrulanacaktır.
- NFR-005: Arayüz mobil, tablet ve masaüstünde Bootstrap 5 ile responsive olacaktır.
- NFR-006: Ürün görselleri optimize edilecek; gereksiz büyük görseller yüklenmeyecektir.
- NFR-007: Kod; Controller, Model, View ve gerekli yerlerde Service/Repository katmanlarına ayrılacaktır.
- NFR-008: Ödeme sağlayıcı API anahtarları `.env` içinde tutulacaktır.
- NFR-009: Kredi kartı verileri veritabanında saklanmayacaktır.
- NFR-010: Site; KVKK aydınlatma metni, gizlilik politikası, çerez politikası, mesafeli satış sözleşmesi, ön bilgilendirme formu, iade/cayma koşulları ve iletişim sayfalarını içerecektir. [23][26]
- NFR-011: Zorunlu olmayan çerezler için kullanıcı onayı alınacaktır. [16]
- NFR-012: Sistem logları; ödeme, sipariş, stok ve yönetici işlemleri için tutulacaktır.
- NFR-013: Proje Git ile sürümlendirilecektir.

## 6. Veri Modeli
- users: id, name, email, phone, password_hash, role_id, email_verified_at, status, created_at
- roles: id, name, slug
- permissions: id, name, slug
- role_permissions: role_id, permission_id
- addresses: id, user_id, title, full_name, phone, city, district, neighborhood, address_line, postal_code, is_default
- categories: id, parent_id, name, slug, description, image, is_active
- brands: id, name, slug, logo, is_active
- products: id, category_id, brand_id, name, slug, sku, short_description, description, price, sale_price, tax_rate, stock, critical_stock, is_active, seo_title, seo_description, created_at
- product_images: id, product_id, image_path, is_main, sort_order
- product_variants: id, product_id, name, sku, price_difference, stock, critical_stock, is_active
- stock_movements: id, product_id, variant_id, type, quantity, note, created_by, created_at
- carts: id, user_id, session_id, created_at
- cart_items: id, cart_id, product_id, variant_id, quantity, unit_price
- coupons: id, code, discount_type, discount_value, min_cart_total, starts_at, ends_at, usage_limit, per_user_limit, used_count, is_active
- coupon_usages: id, coupon_id, user_id, order_id, used_at
- shipping_methods: id, name, fee, free_shipping_threshold, is_active
- orders: id, user_id, order_no, status, subtotal, discount_total, coupon_id, shipping_fee, tax_total, grand_total, payment_method, payment_status, note, created_at
- order_items: id, order_id, product_id, variant_id, product_name, variant_name, sku, unit_price, quantity, line_total
- order_addresses: id, order_id, full_name, phone, city, district, neighborhood, address_line, postal_code
- order_shipments: id, order_id, shipping_company, tracking_number, shipped_at, delivered_at, note
- payments: id, order_id, method, provider, transaction_id, status, amount, raw_response, created_at
- settings: id, key, value
- activity_logs: id, user_id, action, entity_type, entity_id, description, ip_address, created_at

## 7. Kabul Kriterleri
- Ziyaretçi ürünleri gezebilir, sepete ekleyebilir ve sipariş verebilir.
- Kullanıcı varyant seçtiğinde doğru fiyat ve stok bilgisi görünür.
- Sipariş onaylandığında stok doğru şekilde düşer.
- Kritik stok eşiği aşıldığında yönetici panelinde uyarı görünür.
- Yönetici panelden ürün, görsel, varyant, kategori, marka ve kupon yönetebilir.
- Kredi kartı, havale/EFT ve kapıda ödeme akışları çalışır.
- Yönetici manuel kargo takip numarası girebilir; müşteri bu bilgiyi görebilir.
- Tüm POST istekleri CSRF korumalıdır.
- SQL injection, XSS ve yetkisiz panel erişimi senaryolarında sistem güvenli davranır.
- Site mobil cihazlarda sorunsuz kullanılabilir.

## 8. MVP Dışı / Sonraki Sürüm
- Ürün yorumları ve puanlama
- Favori listesi
- Otomatik kargo firması API entegrasyonu
- E-fatura entegrasyonu
- Çoklu dil ve çoklu para birimi
- Pazaryeri entegrasyonları
- Gelişmiş müşteri segmentasyonu ve e-posta pazarlama