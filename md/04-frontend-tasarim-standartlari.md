# Frontend & Tasarım Standartları

## Genel
- Bootstrap 5 kullanılacak.
- Mobil öncelikli responsive tasarım uygulanacak.
- Vanilla JavaScript ve Fetch API kullanılacak.
- jQuery kullanılmayacaktır.
- Arayüz dili Türkçe olacak.
- Para birimi TRY olarak gösterilecek.

## Sayfa Yapısı
- Ortak header: logo, arama, kategoriler, kullanıcı menüsü, sepet.
- Ortak footer: iletişim, kurumsal, yasal sayfalar, sosyal medya.
- Ürün kartı: görsel, ad, fiyat, indirimli fiyat, stok durumu, sepete ekleme.
- Ürün detayı: galeri, varyant seçimi, fiyat, stok, açıklama, sepete ekleme.
- Sepet: ürün listesi, adet güncelleme, toplamlar, kupon alanı.
- Checkout: adres, kargo, ödeme, özet, onay.
- Admin panel: sidebar, üst bar, dashboard, tablo listeleri, form ekranları.

## Bootstrap Kuralları
- Grid sistemi için `container`, `row`, `col-*` kullanılacak.
- Mobilde tek kolon, tablette iki kolon, masaüstünde üç-dört kolon ürün grid’i kullanılacak.
- Form doğrulama mesajları Bootstrap alert/badge ile gösterilecek.
- Tablolarda `table-responsive` kullanılacak.
- Modal onayları silme gibi kritik işlemlerde kullanılacak.
- Toast bildirimleri sepet, stok ve sipariş işlemlerinde kullanılacak.

## JavaScript Kuralları
- Tüm AJAX istekleri `fetch()` ile yapılacak.
- JSON request/response formatı kullanılacak.
- CSRF token her POST isteğine eklenecek.
- Butonlar işlem sırasında disabled edilecek.
- Kullanıcıya anlaşılır başarı/hata mesajı gösterilecek.
- DOM manipülasyonu için modern JavaScript kullanılacak.

## Tasarım Tonu
- Yöresel, güvenilir, sıcak ve temiz bir görünüm hedeflenecek.
- Doğal tonlar: krem, kahverengi, yeşil ve turuncu tonları tercih edilebilir.
- Ürün görselleri net, beyaz veya açık arka planlı olmalı.
- Fiyatlar belirgin, indirim oranı vurgulu gösterilmeli.
- Mobilde sepet ve ödeme akışı kolay erişilebilir olmalı.