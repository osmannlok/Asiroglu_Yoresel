<?php

/**
 * Aşıroğlu Yöresel Market - Uygulama yapılandırması
 *
 * Bu dosya ayarları bir dizi olarak döndürür. Hassas değerler (şifre, API
 * anahtarı vb.) buraya yazılmaz; proje kökündeki .env dosyasından okunur.
 * .env dosyası web kökünün (public/) dışında durur ve Git'e eklenmez.
 */

declare(strict_types=1);

// -----------------------------------------------------------------------------
// 1. env() yardımcısı: ortam değişkenini okur, true/false/null'ı tipine çevirir
// -----------------------------------------------------------------------------
if (!function_exists('env')) {
    /**
     * Ortam değişkenini döndürür; tanımsız veya boşsa $default döner.
     * Örnek: env('APP_DEBUG', false)
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        if (!is_string($value)) {
            return $value;
        }

        switch (strtolower($value)) {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
                return null;
            case '':
                return $default;
        }

        return $value;
    }
}

// -----------------------------------------------------------------------------
// 2. .env dosyasını oku (varsa). Gerçek ortam değişkenleri öncelikli kalır.
// -----------------------------------------------------------------------------
(static function (): void {
    $file = ROOT_PATH . '/.env';

    if (!is_file($file) || !is_readable($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        // Yorum satırlarını atla
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        // "export KEY=value" biçimini destekle
        if (str_starts_with($line, 'export ')) {
            $line = ltrim(substr($line, 7));
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key   = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));

        // Yalnızca BÜYÜK_HARF_ANAHTARLAR kabul edilir
        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1) {
            continue;
        }

        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            // Tırnaklı değer: kapanış tırnağına kadar al
            $quote = $value[0];
            $end   = strpos($value, $quote, 1);
            $value = $end === false ? substr($value, 1) : substr($value, 1, $end - 1);
        } else {
            // Tırnaksız değerde satır sonu yorumunu ("deger # not") temizle
            $hash = strpos($value, ' #');
            if ($hash !== false) {
                $value = rtrim(substr($value, 0, $hash));
            }
        }

        // Sunucuda tanımlı gerçek ortam değişkeni .env'den önceliklidir
        if (getenv($key) !== false || isset($_ENV[$key])) {
            continue;
        }

        $_ENV[$key] = $value;
    }
})();

// -----------------------------------------------------------------------------
// 3. Yapılandırma dizisi
// -----------------------------------------------------------------------------
return (static function (): array {
    // Varsayılan "production": .env eksik olsa bile hata ayrıntısı sızmaz
    $appEnv       = (string) env('APP_ENV', 'production');
    $isProduction = $appEnv === 'production';

    // Debug yalnızca production dışında VE APP_DEBUG=true ise açılır
    $debug = !$isProduction
        && filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);

    // SESSION_SECURE tanımsızsa null kalır: Session HTTPS'i otomatik algılar
    $sessionSecure = env('SESSION_SECURE');

    return [
        // ---------------------------------------------------------------------
        // Uygulama
        // ---------------------------------------------------------------------
        'app' => [
            'name'     => 'Aşıroğlu Yöresel Market',
            'env'      => $appEnv,
            'debug'    => $debug,
            // Sonunda "/" olmadan. Boşsa göreli adresler kullanılır.
            'url'      => rtrim((string) env('APP_URL', ''), '/'),
            'timezone' => (string) env('APP_TIMEZONE', 'Europe/Istanbul'),
            'locale'   => 'tr_TR',
            // Fiyatlar TRY ve KDV dahil gösterilir
            'currency' => 'TRY',
        ],

        // ---------------------------------------------------------------------
        // Oturum (Session::start bu diziyi alır)
        // ---------------------------------------------------------------------
        'session' => [
            'name'      => (string) env('SESSION_NAME', 'ASIROGLU_SESSID'),
            'lifetime'  => (int) env('SESSION_LIFETIME', 7200),
            // null = otomatik; canlıda SSL sonrası SESSION_SECURE=true yapın
            'secure'    => $sessionSecure === null ? null : filter_var($sessionSecure, FILTER_VALIDATE_BOOLEAN),
            'samesite'  => (string) env('SESSION_SAMESITE', 'Lax'),
            // Oturum dosyaları web kökünün dışında, özel klasörde tutulur
            'save_path' => (string) env('SESSION_SAVE_PATH', STORAGE_PATH . '/sessions'),
        ],

        // ---------------------------------------------------------------------
        // CSRF
        // ---------------------------------------------------------------------
        'csrf' => [
            // CSRF doğrulamasından muaf yollar. Örn. '/odeme/callback/*'
            // Muaf tutulan her uç nokta imza/hash doğrulaması yapmak ZORUNDADIR.
            'except' => [],
        ],

        // ---------------------------------------------------------------------
        // Veritabanı (ayrıntılar config/database.php içinde)
        // ---------------------------------------------------------------------
        'database' => require CONFIG_PATH . '/database.php',
    ];
})();