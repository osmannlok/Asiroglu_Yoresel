<?php

/**
 * Aşıroğlu Yöresel Market - Veritabanı yapılandırması
 *
 * Bu dosya config/config.php tarafından yüklenir ve 'database' anahtarına
 * yerleştirilir. Değerler .env dosyasından okunur; gerçek bilgiler koda
 * yazılmaz. env() yardımcısı config.php içinde tanımlıdır.
 */

declare(strict_types=1);

return (static function (): array {
    // Şifre ham okunur: env() "true", "false", "null" gibi metinleri tipe çevirdiği
    // için bu kelimelerden oluşan bir şifreyi bozabilirdi.
    $rawPassword = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');

    return [
        // Veritabanı sunucusu. "localhost" yerine 127.0.0.1 kullanmak,
        // bazı sunucularda socket kaynaklı bağlantı sorunlarını önler.
        'host'      => (string) env('DB_HOST', '127.0.0.1'),
        'port'      => (int) env('DB_PORT', 3306),

        // Varsayılan YOK: .env'de tanımlanmazsa Database sınıfı güvenli şekilde hata verir
        'database'  => (string) env('DB_DATABASE', ''),
        'username'  => (string) env('DB_USERNAME', ''),
        'password'  => is_string($rawPassword) ? $rawPassword : '',

        // Şema ile aynı karakter seti ve collation
        'charset'   => (string) env('DB_CHARSET', 'utf8mb4'),
        'collation' => (string) env('DB_COLLATION', 'utf8mb4_unicode_ci'),
    ];
})();