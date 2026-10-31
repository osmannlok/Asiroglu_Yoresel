# PHP MVC & Kodlama Standartları

## Mimari
- Custom MVC kullanılacak.
- Klasör yapısı:

```text
/app
  /Core
  /Controllers
  /Models
  /Services
  /Repositories
  /Helpers
  /Middleware
/public
  /assets
  /uploads
  index.php
/resources
  /views
/config
/database
/storage
  /logs
```

- Tüm istekler `public/index.php` üzerinden karşılanacak.
- Router; controller, method ve parametreleri güvenli şekilde eşleştirecek.
- Controller yalnızca istek yönetimi ve response üretiminden sorumlu olacak.
- İş mantığı Service katmanında, veritabanı işlemleri Repository/Model katmanında olacak.
- View dosyaları HTML ve minimum PHP içerecek.

## PHP Kuralları
- PHP 8.2+ kullanılacak.
- Strict types tercih edilecek.
- Tüm class’lar namespace kullanacak.
- Controller, Model, Service ve Repository ayrımı korunacak.
- Kod tekrarından kaçınılacak; ortak işlemler Helper veya Service’e taşınacak.
- Hata yönetimi try-catch ile yapılacak.
- Kullanıcıya teknik hata gösterilmeyecek; hata log dosyasına yazılacak.

## Veritabanı
- Tüm bağlantılar PDO ile yapılacak.
- Yalnızca prepared statement kullanılacak.
- Dinamik tablo/kolon adı asla kullanıcı girdisinden alınmayacak.
- Transaction gerektiren işlemlerde `beginTransaction`, `commit`, `rollBack` kullanılacak.
- Sipariş oluşturma ve stok düşme işlemi aynı transaction içinde yapılacak.

## Güvenlik
- CSRF token tüm POST formlarında zorunlu olacak.
- Çıktılarda `htmlspecialchars()` kullanılacak.
- Şifreler `password_hash()` ile saklanacak.
- Oturum fixation’a karşı `session_regenerate_id(true)` kullanılacak.
- Dosya yüklemelerinde uzantı, MIME type, boyut ve benzersiz dosya adı kontrolü yapılacak.
- Yönetim paneli route’ları auth ve role middleware ile korunacak.
- Ödeme webhook’larında imza doğrulaması yapılacak.

## Kod Üretim Kuralı
- Her dosya için tam yol verilecek.
- Kod eksiksiz ve çalışır olacak.
- Gerekiyorsa ilgili migration/SQL, route, controller, model, view ve JS dosyası birlikte verilecek.
- Her modülde ilgili gereksinim ID’leri belirtilecek.