---
name: migration-ekle
description: Aşıroğlu Yöresel Market PHP MVC projesine foreign key sırasına uygun yeni veritabanı migration dosyası ekler.
---

# Migration Ekleme Kuralı

Bu skill yalnızca Aşıroğlu Yöresel Market PHP MVC projesinde kullanılır.

## Kurallar

1. Önce database/migrations klasöründeki mevcut migration dosyalarını oku.
2. Foreign key bağımlılıklarını kontrol et.
3. Yeni tablo, mevcut tablolara foreign key veriyorsa ilgili tablonun migration sırasından sonra gelmesini sağla.
4. Migration dosya adını şu formatta oluştur: NNN_create_<tablo>.sql
5. Her migration dosyasında yalnızca ilgili tablo ve ilişkili index/foreign key tanımları olsun.
6. Tüm tablolar InnoDB, utf8mb4 ve utf8mb4_unicode_ci kullanacak.
7. Primary key adı id olacak.
8. Tarih alanları DATETIME olacak.
9. Para alanları DECIMAL(12,2) olacak.
10. Migration sonunda schema_migrations kaydının oluşacağını dikkate al.
11. Dosyayı tek tek üret; kullanıcı onayı olmadan sonraki dosyaya geçme.

## Çıktı Formatı

1. Migration dosyasının amacı
2. Etkilenecek tablolar
3. Foreign key bağımlılıkları
4. Tam SQL içeriği
5. Çalıştırma ve geri alma talimatı

---
name: model-ekle
description: Aşıroğlu Yöresel Market projesi için PDO tabanlı, güvenli ve PSR-12 uyumlu PHP Model sınıfı oluşturur.
---

# Model Ekleme Kuralı

## Kurallar

1. Model sınıfı app/Models klasöründe oluşturulacak.
2. Tüm sorgular PDO prepared statement ile yazılacak.
3. Model, app/Core/Database.php sınıfını kullanacak.
4. CRUD metotları eklenecek: create, update, delete, findById, getAll.
5. Sayfalama için paginate metodu eklenecek.
6. Input doğrulama Controller veya Validator katmanında yapılacak.
7. Model içinde doğrudan $_GET, $_POST veya $_REQUEST kullanılmayacak.
8. Şifre alanı varsa password_hash() kullanılacak.
9. Kodlar Türkçe yorumlu ve PSR-12 uyumlu olacak.
10. Dosyayı tek tek üret; kullanıcı onayı olmadan sonraki dosyaya geçme.

## Çıktı Formatı

1. Modelin amacı
2. İlgili veritabanı tablosu
3. Kullanılacak foreign key ilişkileri
4. Tam PHP dosya içeriği
5. Test talimatı

---
name: guvenlik-kontrolu
description: PHP MVC e-ticaret projesinde SQL injection, XSS, CSRF, dosya yükleme, oturum ve ödeme güvenliğini denetler.
---

# Güvenlik Kontrolü

## Kontrol Edilecekler

1. Tüm SQL sorguları PDO prepared statement kullanıyor mu?
2. Tüm formlarda CSRF token var mı?
3. Kullanıcı girdileri çıktıya basılırken escape ediliyor mu?
4. Şifreler password_hash() ile saklanıyor mu?
5. Session cookie ayarları HttpOnly, Secure ve SameSite içeriyor mu?
6. Admin paneli yetkilendirme kontrolü yapıyor mu?
7. Dosya yüklemede MIME, uzantı, boyut ve içerik kontrolü var mı?
8. Kart bilgileri veritabanına veya loglara yazılıyor mu?
9. Hata mesajları production modunda kullanıcıya teknik detay gösteriyor mu?
10. Rate limiting ve brute-force koruması var mı?

## Rapor Formatı

| Dosya | Risk | Önem | Çözüm |

## Kural

- Sadece denetim yap; kullanıcı onayı olmadan dosya değiştirme.
- Kritik riskleri önce listele.
- Her öneri için ilgili dosya ve satır aralığını belirt.

---
name: kod-incelemesi
description: Aşıroğlu Yöresel Market PHP MVC projesindeki son değişiklikleri mimari, güvenlik, performans ve PSR-12 açısından inceler.
---

# Kod İnceleme Kuralı

## İnceleme Başlıkları

1. MVC katmanları doğru kullanılmış mı?
2. Controller içinde gereksiz iş mantığı var mı?
3. Model ve Service katmanları doğru ayrılmış mı?
4. PDO prepared statement kullanılmış mı?
5. View dosyalarında XSS koruması var mı?
6. Bootstrap 5 yapısı responsive mi?
7. Fetch API hata yönetimi yapıyor mu?
8. Kod tekrarı var mı?
9. PSR-12 uyumu var mı?
10. Türkçe yorumlar yeterli mi?

## Rapor Formatı

1. Genel değerlendirme
2. Kritik sorunlar
3. Orta düzey sorunlar
4. İyileştirme önerileri
5. Değiştirilmesi gereken dosyalar
6. Test edilmesi gereken senaryolar

## Kural

- Önce yalnızca inceleme raporu ver.
- Kullanıcı onay vermeden dosya değiştirme.

---
name: veritabani-denetleyici
description: Aşıroğlu Yöresel Market veritabanı şemasını, migration sırasını, foreign key ilişkilerini ve SQL güvenliğini denetler.
tools: Read, Grep, Glob
---

Sen Aşıroğlu Yöresel Market PHP MVC e-ticaret projesinin veritabanı denetçisisin.

Görevin:

1. database/migrations klasöründeki dosyaları sırayla incele.
2. Foreign key bağımlılıklarını kontrol et.
3. Bir tablo, referans verdiği tablodan önce oluşturuluyorsa bunu raporla.
4. Eksik index, yanlış veri tipi ve tutarsız kolon adlarını tespit et.
5. products ve product_variants stok mantığını kontrol et.
6. orders tablosundaki misafir sipariş alanlarını kontrol et.
7. coupon_usages ile orders arasındaki ilişkiyi kontrol et.
8. Şemayı değiştirme; yalnızca denetim raporu hazırla.

Rapor formatı:

1. Genel durum
2. Kritik hatalar
3. Uyarılar
4. İyileştirme önerileri
5. Önerilen migration sırası

---
name: guvenlik-denetleyici
description: PHP MVC e-ticaret projesinde güvenlik açıklarını denetler; SQL injection, XSS, CSRF, dosya yükleme ve ödeme güvenliğini kontrol eder.
tools: Read, Grep, Glob
---

Sen Aşıroğlu Yöresel Market projesinin güvenlik denetçisisin.

Görevin:

1. app/Core, app/Controllers, app/Models ve app/Views klasörlerini incele.
2. SQL injection, XSS, CSRF ve yetkilendirme açıklarını ara.
3. Dosya yükleme güvenliğini kontrol et.
4. Session ve cookie güvenliğini kontrol et.
5. Ödeme callback doğrulamasını kontrol et.
6. Kart bilgisi, API key veya secret değerinin loglandığı yerleri tespit et.
7. Kod değiştirme; yalnızca risk raporu hazırla.

Rapor formatı:

| Dosya | Satır | Risk | Önem | Çözüm |

