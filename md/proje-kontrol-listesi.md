# Aşıroğlu Yöresel Market — E-Ticaret Proje Kontrol Listesi

Bu dosya; PHP MVC e-ticaret projesinin iş kuralları, ekran akışları, Claude prompt dosyaları, örnek veri seti ve test senaryolarını içerir.

Proje adı: **Aşıroğlu Yöresel Market**  
Teknoloji: PHP 8.2+, Custom MVC, MySQL/MariaDB, PDO, Bootstrap 5, Vanilla JavaScript, Fetch API

---

## 1. Proje Kapsamı

Proje; yöresel gıda, kuruyemiş, baharat, reçel, pekmez, sabun ve benzeri yerel ürünlerin online satışı için kurulacaktır.

Sistemde bulunması gereken temel özellikler:

- Ürün kataloğu ve kategori yönetimi
- Ürün varyantları ve stok takibi
- Sepet ve sipariş yönetimi
- Online ödeme, havale/EFT ve kapıda ödeme
- Kargo takibi ve iade yönetimi
- Kampanya ve kupon yönetimi
- Müşteri hesabı ve adres defteri
- Admin paneli ve raporlama
- SEO, blog, banner ve e-bülten yönetimi

---

## 2. İş Kuralları

### 2.1 Kullanıcı ve Hesap Kuralları

- Müşteri e-posta ve şifre ile kayıt olabilir.
- E-posta adresi sistemde benzersiz olmalıdır.
- Şifreler `password_hash()` ile saklanmalıdır.
- Şifre en az 8 karakter olmalı ve harf ile rakam içermelidir.
- Kullanıcı e-posta doğrulaması olmadan da alışveriş yapabilmelidir; ancak sipariş takibi için kayıt teşvik edilmelidir.
- Misafir kullanıcılar sepet oluşturabilir ve sipariş verebilir.
- Kullanıcı birden fazla teslimat adresi ekleyebilir.
- Kullanıcı yalnızca bir varsayılan teslimat adresi seçebilir.
- Kullanıcı hesabından kendi siparişlerini görebilmelidir.
- Kullanıcı şifresini e-posta ile sıfırlayabilmelidir.
- Admin kullanıcıları ayrı yetkilendirme ile yönetilmelidir.

### 2.2 Ürün Kuralları

- Her ürünün benzersiz bir `sku` değeri olmalıdır.
- Her ürünün SEO uyumlu benzersiz bir `slug` değeri olmalıdır.
- Ürün en az bir kategoriye bağlı olmalıdır.
- Ürün birden fazla görsel içerebilir.
- İlk ürün görseli ana görsel olarak tanımlanmalıdır.
- Ürünün satış fiyatı sıfırdan büyük olmalıdır.
- İndirimli fiyat, normal satış fiyatından düşük olmalıdır.
- İndirimli fiyatın geçerlilik tarihi olabilir.
- Ürün stokta yoksa “Stokta Yok” olarak gösterilmelidir.
- Kritik stok seviyesinin altına düşen ürünler admin panelinde uyarı olarak listelenmelidir.
- Yöresel gıda ürünlerinde içerik, alerjen, menşe, raf ömrü ve saklama koşulu bilgileri gösterilmelidir.
- Ürün yorumları admin onayından sonra yayınlanmalıdır.
- Yalnızca sipariş veren kullanıcılar ürün yorumu yapabilmelidir.

### 2.3 Ürün Varyant Kuralları

- Aynı ürünün farklı gramaj, paket veya aroma seçenekleri varyant olarak tanımlanabilir.
- Her varyantın kendi SKU, fiyatı ve stok adedi olabilir.
- Varyant stokta yoksa seçilemez.
- Sepette ürün, ürün ID ve varsa varyant ID ile tanımlanır.
- Aynı ürün ve aynı varyant sepete tekrar eklenirse adet artırılmalıdır.

### 2.4 Stok Kuralları

- Ürün stoğu, sepete ekleme sırasında kontrol edilmelidir.
- Stokta olmayan ürün sepete eklenememelidir.
- Kullanıcı, mevcut stok miktarından fazla adet ekleyememelidir.
- Sipariş oluşturulduğunda stok düşürülmelidir.
- Sipariş iptal edilirse stok geri eklenmelidir.
- İade onaylanan ürün stoğa geri eklenmelidir.
- Tüm stok hareketleri `stock_movements` tablosuna kaydedilmelidir.
- Stok düşürme ve sipariş oluşturma işlemi aynı database transaction içinde yapılmalıdır.

### 2.5 Sepet Kuralları

- Misafir kullanıcılar için session tabanlı sepet kullanılmalıdır.
- Üye kullanıcılar için veritabanı tabanlı sepet kullanılmalıdır.
- Kullanıcı giriş yaptığında misafir sepeti üye sepetiyle birleştirilmelidir.
- Sepette ürün adedi artırılıp azaltılabilmelidir.
- Sepetten ürün çıkarılabilmelidir.
- Sepet toplamı; ürün toplamı, indirim, KDV ve kargo ücretinden hesaplanmalıdır.
- Sepetteki ürün fiyatı, sipariş oluşturma anında yeniden kontrol edilmelidir.
- Sepet boşsa checkout sayfasına geçilememelidir.

### 2.6 Kupon Kuralları

- Kupon kodu benzersiz olmalıdır.
- Kupon yüzdesel indirim, sabit tutar indirim veya ücretsiz kargo olabilir.
- Kuponun minimum sipariş tutarı olabilir.
- Kuponun maksimum indirim tutarı olabilir.
- Kuponun başlangıç ve bitiş tarihi olabilir.
- Kupon toplam kullanım limitine sahip olabilir.
- Kupon kullanıcı başına kullanım limitine sahip olabilir.
- Süresi geçmiş, pasif veya limiti dolmuş kupon kullanılamaz.
- Kupon indirimi yalnızca uygun sipariş tutarına uygulanmalıdır.
- Kupon kullanımı `coupon_usages` tablosuna kaydedilmelidir.

### 2.7 Sipariş Kuralları

- Sipariş numarası benzersiz ve okunabilir formatta üretilmelidir.
- Örnek: `ASY-20261030-000123`
- Sipariş oluşturulmadan önce stok, fiyat ve kupon geçerliliği yeniden kontrol edilmelidir.
- Sipariş oluşturulurken teslimat adresi anlık kopya olarak saklanmalıdır.
- Sipariş kalemlerinde ürün adı, SKU, birim fiyat, adet, KDV ve satır toplamı saklanmalıdır.
- Sipariş durumu değişiklikleri `order_status_history` tablosuna kaydedilmelidir.
- Ödeme başarısız olursa sipariş silinmemeli, `payment_failed` durumuna alınmalıdır.
- Sipariş iptal edilirse stok geri eklenmelidir.
- Sipariş teslim edildiğinde müşteriye bilgilendirme gönderilmelidir.

### 2.8 Ödeme Kuralları

- Kart bilgileri asla veritabanında saklanmamalıdır.
- Kart numarası, CVV ve son kullanma tarihi loglara yazılmamalıdır.
- Ödeme sağlayıcısı genişletilebilir yapıda olmalıdır.
- `PaymentGatewayInterface` arayüzü kullanılmalıdır.
- PayTR veya iyzico için ayrı adapter sınıfları yazılabilmelidir.
- 3D Secure desteklenmelidir.
- Ödeme callback işlemlerinde imza/hash doğrulaması yapılmalıdır.
- Başarılı ödemede sipariş `paid`, ödeme `completed` durumuna alınmalıdır.
- Başarısız ödemede sipariş `payment_failed`, ödeme `failed` durumuna alınmalıdır.
- İade işleminde ödeme `refunded` veya `partially_refunded` durumuna alınmalıdır.
- Ödeme istek ve yanıtları loglanmalı; hassas veriler maskelenmelidir.

### 2.9 Kargo Kuralları

- Kargo ücreti sipariş tutarına ve teslimat adresine göre hesaplanabilir.
- Belirli tutar üzerindeki siparişlerde ücretsiz kargo uygulanabilir.
- Sipariş kargoya verildiğinde takip numarası girilmelidir.
- Müşteriye kargo takip numarası e-posta ile bildirilmelidir.
- Kargo teslim edildiğinde sipariş `delivered` durumuna alınmalıdır.
- Kırılgan ve sıvı ürünler için özel paketleme kuralları uygulanmalıdır.

### 2.10 İade Kuralları

- Müşteri teslimattan sonra iade talebi oluşturabilmelidir.
- İade talebinde sipariş, ürün ve iade nedeni seçilmelidir.
- İade talebi admin onayına düşmelidir.
- Onaylanan iade için kargo gönderim süreci başlatılmalıdır.
- Ürün depoya ulaştığında iade kontrolü yapılmalıdır.
- Onaylanan iadelerde ödeme iadesi başlatılmalıdır.
- İade edilen ürün stok hareketi olarak sisteme kaydedilmelidir.

---

## 3. Ekran Akışları

### 3.1 Ziyaretçi Akışı

```text
Ana Sayfa
├── Kategoriler
│   └── Ürün Listesi
│       ├── Ürün Filtreleme
│       └── Ürün Detayı
│           ├── Sepete Ekle
│           └── Favorilere Ekle
├── Kampanyalar
├── Hakkımızda
├── İletişim
├── Sepet
│   └── Checkout
│       ├── Teslimat Bilgileri
│       ├── Kargo Seçimi
│       ├── Ödeme Yöntemi
│       ├── Sipariş Özeti
│       └── Ödeme
│           ├── Başarılı Ödeme
│           └── Başarısız Ödeme
└── Giriş / Kayıt
```

### 3.2 Üye Müşteri Akışı

```text
Kayıt Ol
├── E-posta Doğrulama
├── Giriş Yap
├── Hesabım
│   ├── Siparişlerim
│   │   ├── Sipariş Detayı
│   │   └── İade Talebi
│   ├── Adreslerim
│   ├── Favorilerim
│   ├── Yorumlarım
│   └── Şifre Değiştir
├── Ürün Ara
├── Sepete Ekle
└── Checkout
```

### 3.3 Admin Akışı

```text
Admin Girişi
├── Dashboard
├── Ürün Yönetimi
│   ├── Ürün Ekle
│   ├── Ürün Düzenle
│   ├── Görsel Yükle
│   ├── Varyant Ekle
│   └── Stok Güncelle
├── Kategori Yönetimi
├── Marka Yönetimi
├── Sipariş Yönetimi
│   ├── Sipariş Detayı
│   ├── Durum Güncelle
│   └── Kargo Takip No Gir
├── Müşteri Yönetimi
├── Kupon Yönetimi
├── İade Talepleri
├── Raporlar
└── Site Ayarları
```

### 3.4 Sipariş Durum Akışı

```text
pending
└── awaiting_payment
    ├── paid
    │   ├── preparing
    │   │   ├── shipped
    │   │   │   ├── delivered
    │   │   │   └── returned
    │   │   └── cancelled
    │   └── cancelled
    └── payment_failed
```

---

## 4. Claude Prompt Dosyaları

### 4.1 `CLAUDE.md`

`CLAUDE.md` dosyasında şu kurallar bulunmalıdır:

- PHP 8.2+, Custom MVC, PDO, Bootstrap 5 ve Vanilla JavaScript kullanılacak.
- Tüm SQL sorguları prepared statement ile yazılacak.
- Kart bilgileri veritabanında saklanmayacak.
- Her modül ayrı ayrı geliştirilecek.
- Her adımda dosya listesi ve tam dosya içerikleri verilecek.
- Her modül sonunda kullanıcı onayı beklenecek.
- Türkçe yorumlar ve PSR-12 uyumlu kod standartları kullanılacak.

### 4.2 `PROMPT-01-MVC-CORE.md`

```text
CLAUDE PROMPT:

"CLAUDE.md kurallarına göre PHP MVC çekirdek altyapısını kur.

Görevler:
1. public/index.php front controller oluştur.
2. Router, Controller, Model, Database sınıflarını yaz.
3. Request, Response, Session, Csrf ve Validator sınıflarını yaz.
4. config/config.php ve config/database.php oluştur.
5. Bootstrap 5 temel layout oluştur.
6. Ana sayfa route’unu tanımla.
7. Her dosyanın tam içeriğini ver.
8. Kurulum ve test adımlarını yaz."
```

### 4.3 `PROMPT-02-DATABASE.md`

```text
CLAUDE PROMPT:

"database-schema.sql dosyasındaki şemayı temel alarak migration dosyalarını oluştur.

Görevler:
1. Her tablo için ayrı migration SQL dosyası oluştur.
2. Foreign key sırasına göre migration sırasını belirle.
3. Her tablo için PDO tabanlı Model sınıfı oluştur.
4. Şemayı değiştirmeden önce bana gerekçeli öneri sun.
5. Migration çalıştırma ve test adımlarını yaz."
```

### 4.4 `PROMPT-03-PRODUCTS.md`

```text
CLAUDE PROMPT:

"Kategori, marka, ürün, ürün görseli, varyant ve stok yönetimi modülünü oluştur.

Görevler:
1. Admin panelinde CRUD işlemleri oluştur.
2. Çoklu görsel yükleme yap.
3. Ürün varyantları ekle.
4. Stok hareketlerini kaydet.
5. Ürün listeleme, arama, filtreleme ve sayfalama oluştur.
6. Ürün detay sayfası oluştur.
7. Tüm sorguları PDO prepared statement ile yaz."
```

### 4.5 `PROMPT-04-CART-ORDER.md`

```text
CLAUDE PROMPT:

"Sepet ve sipariş modülünü oluştur.

Görevler:
1. Misafir ve üye sepetini destekle.
2. Fetch API ile sepete ekleme, adet güncelleme ve silme işlemleri yap.
3. Stok kontrolü uygula.
4. Kupon uygulama mantığını yaz.
5. Checkout sürecini oluştur.
6. Sipariş oluşturma işlemini transaction içinde yap.
7. Sipariş durum geçmişini kaydet."
```

### 4.6 `PROMPT-05-PAYMENT.md`

```text
CLAUDE PROMPT:

"Güvenli ödeme entegrasyonu altyapısını oluştur.

Görevler:
1. PaymentGatewayInterface arayüzü oluştur.
2. Sandbox/test modunu destekle.
3. 3D Secure akışını tasarla.
4. Callback doğrulaması yap.
5. Başarılı ve başarısız ödeme durumlarını yönet.
6. Kart bilgilerini asla kaydetme.
7. Hassas verileri maskeleyerek log tut."
```

### 4.7 `PROMPT-06-SHIPPING-RETURN.md`

```text
CLAUDE PROMPT:

"Kargo ve iade yönetimi modülünü oluştur.

Görevler:
1. Kargo yöntemi yönetimi oluştur.
2. Siparişe kargo takip numarası ekleme ekranı yap.
3. Kargo durum güncellemelerini yönet.
4. Müşteri iade talebi oluşturma ekranı yap.
5. Admin iade onay ve red ekranı yap.
6. İade onaylandığında stok ve ödeme iadesi akışını hazırla."
```

### 4.8 `PROMPT-07-ADMIN-REPORTS.md`

```text
CLAUDE PROMPT:

"Admin dashboard ve raporlama modülünü oluştur.

Görevler:
1. Günlük, haftalık ve aylık satış raporları oluştur.
2. En çok satan ürünler raporunu hazırla.
3. Düşük stoklu ürünler uyarısını ekle.
4. Sipariş durum dağılımını göster.
5. Müşteri bazlı sipariş geçmişi raporu oluştur.
6. CSV export özelliği ekle."
```

---

## 5. Örnek Veri Seti

### 5.1 Kategoriler

| Kategori | Alt Kategori |
|---|---|
| Yöresel Gıda | Reçel, Pekmez, Tahin, Bal |
| Kuruyemiş | Fındık, Fıstık, Badem, Ceviz |
| Baharat | Pul Biber, Kimyon, Nane, Kekik |
| Doğal Ürünler | Sabun, Bitkisel Yağ, Şifalı Bitki |
| Yöresel Tekstil | El Dokuması, Peştemal, Mutfak Ürünleri |

### 5.2 Örnek Ürünler

| Ürün | Kategori | Fiyat | Stok | Birim |
|---|---|---:|---:|---|
| Antep Fıstığı | Kuruyemiş | 850,00 TL | 25 | kg |
| Çeşme Nane | Baharat | 75,00 TL | 100 | paket |
| Karadut Pekmezi | Yöresel Gıda | 180,00 TL | 40 | adet |
| Organik Çiçek Balı | Yöresel Gıda | 320,00 TL | 30 | adet |
| Zeytinyağlı Defne Sabunu | Doğal Ürünler | 45,00 TL | 200 | adet |

### 5.3 Örnek Kuponlar

| Kod | Tür | Değer | Minimum Sepet | Geçerlilik |
|---|---|---:|---:|---|
| `HOSGELDIN10` | Yüzde | %10 | 300 TL | 30 gün |
| `KARGOBEDAVA` | Ücretsiz kargo | — | 500 TL | 15 gün |
| `YORESEL50` | Sabit indirim | 50 TL | 750 TL | 60 gün |

### 5.4 Örnek Kargo Yöntemleri

| Yöntem | Ücret | Ücretsiz Limit |
|---|---:|---:|
| Standart Kargo | 89,00 TL | 750 TL |
| Hızlı Kargo | 149,00 TL | 1.500 TL |
| Yerel Teslimat | 49,00 TL | 500 TL |

### 5.5 Örnek Admin Kullanıcısı

```text
Ad: Sistem Yöneticisi
E-posta: admin@ashiroglumarket.com
Şifre: Güçlü, rastgele üretilmiş şifre
Rol: admin
Durum: active
```

---

## 6. Test Senaryoları

### 6.1 Kullanıcı Testleri

| Test No | Senaryo | Beklenen Sonuç |
|---:|---|---|
| U-01 | Geçerli bilgilerle kayıt olma | Hesap oluşur, giriş yapılabilir |
| U-02 | Aynı e-posta ile tekrar kayıt | Hata mesajı gösterilir |
| U-03 | Yanlış şifre ile giriş | Hata mesajı gösterilir |
| U-04 | Şifremi unuttum | Şifre sıfırlama e-postası gönderilir |
| U-05 | Adres ekleme | Adres kaydedilir |
| U-06 | Varsayılan adres değiştirme | Yalnızca bir varsayılan adres kalır |

### 6.2 Ürün Testleri

| Test No | Senaryo | Beklenen Sonuç |
|---:|---|---|
| P-01 | Ürün arama | İlgili ürünler listelenir |
| P-02 | Kategori filtreleme | Yalnızca seçilen kategori ürünleri görünür |
| P-03 | Fiyat filtresi | Belirlenen aralıktaki ürünler listelenir |
| P-04 | Stokta olmayan ürün görüntüleme | “Stokta Yok” gösterilir |
| P-05 | Stokta olmayan ürünü sepete ekleme | İşlem engellenir |
| P-06 | Stoktan fazla adet ekleme | İşlem engellenir |

### 6.3 Sepet Testleri

| Test No | Senaryo | Beklenen Sonuç |
|---:|---|---|
| S-01 | Sepete ürün ekleme | Ürün sepete eklenir |
| S-02 | Aynı ürünü tekrar ekleme | Adet artar |
| S-03 | Adet azaltma | Toplam tutar güncellenir |
| S-04 | Ürün silme | Ürün sepetten çıkarılır |
| S-05 | Kupon uygulama | İndirim hesaplanır |
| S-06 | Geçersiz kupon girme | Hata mesajı gösterilir |
| S-07 | Boş sepette checkout | Kullanıcı sepete yönlendirilir |

### 6.4 Sipariş ve Ödeme Testleri

| Test No | Senaryo | Beklenen Sonuç |
|---:|---|---|
| O-01 | Başarılı ödeme | Sipariş `paid`, ödeme `completed` olur |
| O-02 | Başarısız ödeme | Sipariş `payment_failed`, ödeme `failed` olur |
| O-03 | Sipariş sonrası stok kontrolü | Stok düşer |
| O-04 | Sipariş iptali | Stok geri eklenir |
| O-05 | Kuponlu sipariş | İndirim doğru hesaplanır |
| O-06 | Kargo ücreti hesaplama | Doğru kargo tutarı eklenir |
| O-07 | Ücretsiz kargo limiti | Limit üzerinde kargo ücretsiz olur |

### 6.5 Güvenlik Testleri

| Test No | Senaryo | Beklenen Sonuç |
|---:|---|---|
| G-01 | SQL injection denemesi | Sorgu çalışmaz, hata güvenli şekilde yönetilir |
| G-02 | XSS denemesi | Kod çalıştırılmaz, çıktı escape edilir |
| G-03 | CSRF token olmadan form gönderimi | İşlem reddedilir |
| G-04 | Yetkisiz admin sayfasına erişim | Kullanıcı giriş sayfasına yönlendirilir |
| G-05 | Başka kullanıcının siparişini görme | Erişim engellenir |
| G-06 | Kart bilgisi kaydetme | Kart verisi veritabanına yazılmaz |
| G-07 | Geçersiz dosya yükleme | Dosya reddedilir |

### 6.6 Mobil Testleri

| Test No | Senaryo | Beklenen Sonuç |
|---:|---|---|
| M-01 | Mobilde ana sayfa | Tasarım bozulmadan görünür |
| M-02 | Mobilde ürün listesi | Filtreler kullanılabilir |
| M-03 | Mobilde sepet | Adet güncelleme çalışır |
| M-04 | Mobilde checkout | Form alanları kullanılabilir |
| M-05 | Mobilde ödeme | Ödeme akışı tamamlanır |

---

## 7. Yayına Alma Kontrol Listesi

- [ ] Domain ve SSL aktif
- [ ] Production ortamı yapılandırıldı
- [ ] Hata mesajları kullanıcıya gizlendi
- [ ] Veritabanı yedekleme kuruldu
- [ ] Admin varsayılan şifresi değiştirildi
- [ ] ETBİS kaydı tamamlandı
- [ ] Mesafeli satış sözleşmesi yayınlandı
- [ ] İade politikası yayınlandı
- [ ] KVKK metni yayınlandı
- [ ] Çerez onayı eklendi
- [ ] Ödeme sağlayıcısı onaylandı
- [ ] Test ödemesi yapıldı
- [ ] Gerçek ödeme testi yapıldı
- [ ] Kargo entegrasyonu test edildi
- [ ] E-posta bildirimleri test edildi
- [ ] Google Search Console kuruldu
- [ ] Google Analytics kuruldu
- [ ] Sitemap.xml oluşturuldu
- [ ] robots.txt oluşturuldu
- [ ] Mobil uyumluluk testleri tamamlandı
- [ ] Güvenlik testleri tamamlandı
- [ ] Performans testleri tamamlandı

---

## 8. Önerilen Claude Çalışma Sırası

1. `CLAUDE.md` dosyasını proje köküne ekle
2. `database-schema.sql` dosyasını proje köküne ekle
3. `PROMPT-01-MVC-CORE.md` ile temel altyapıyı kur
4. `PROMPT-02-DATABASE.md` ile migration ve modelleri oluştur
5. `PROMPT-03-PRODUCTS.md` ile ürün yönetimini tamamla
6. `PROMPT-04-CART-ORDER.md` ile sepet ve sipariş akışını kur
7. `PROMPT-05-PAYMENT.md` ile ödeme entegrasyonunu yap
8. `PROMPT-06-SHIPPING-RETURN.md` ile kargo ve iade modülünü ekle
9. `PROMPT-07-ADMIN-REPORTS.md` ile raporlamayı tamamla
10. Test senaryolarını uygula
11. Yayına alma kontrol listesini tamamla
