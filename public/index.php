<?php

/**
 * Aşıroğlu Yöresel Market - Front Controller
 *
 * Tüm HTTP istekleri public/.htaccess üzerinden bu dosyaya yönlendirilir.
 * Görevi: sabitleri tanımlamak, autoloader'ı kaydetmek, yapılandırmayı
 * yüklemek, hata yönetimini kurmak ve uygulamayı başlatmaktır.
 */

declare(strict_types=1);

// -----------------------------------------------------------------------------
// 1. PHP sürüm kontrolü
// -----------------------------------------------------------------------------
if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Bu uygulama PHP 8.2 veya üzeri gerektirir.');
}

// -----------------------------------------------------------------------------
// 2. Proje yolu sabitleri
// -----------------------------------------------------------------------------
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('LOG_PATH', STORAGE_PATH . '/logs');

// -----------------------------------------------------------------------------
// 3. Autoloader (PSR-4: App\ => app/)
// -----------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    // Yalnızca App\ ad alanındaki sınıfları yükle
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    // App\Core\Router => app/Core/Router.php
    $relativeClass = substr($class, strlen($prefix));
    $file = APP_PATH . '/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// -----------------------------------------------------------------------------
// 4. Yapılandırmayı yükle
// -----------------------------------------------------------------------------
$configFile = CONFIG_PATH . '/config.php';

if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Yapılandırma dosyası bulunamadı.');
}

/** @var array<string, mixed> $config */
$config = require $configFile;

$appConfig = $config['app'] ?? [];
$isDebug   = (bool) ($appConfig['debug'] ?? false);

// -----------------------------------------------------------------------------
// 5. Zaman dilimi ve karakter kodlaması
// -----------------------------------------------------------------------------
date_default_timezone_set((string) ($appConfig['timezone'] ?? 'Europe/Istanbul'));
mb_internal_encoding('UTF-8');

// -----------------------------------------------------------------------------
// 6. Hata yönetimi (production'da teknik detay kullanıcıya gösterilmez)
// -----------------------------------------------------------------------------
if (!is_dir(LOG_PATH)) {
    @mkdir(LOG_PATH, 0750, true);
}

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH . '/php-error.log');
ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('display_startup_errors', $isDebug ? '1' : '0');

/**
 * Yakalanmayan exception'ları loglar ve kullanıcıya genel bir hata sayfası gösterir.
 */
set_exception_handler(static function (Throwable $e) use ($isDebug): void {
    error_log(sprintf(
        "[%s] %s: %s in %s:%d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    if ($isDebug) {
        echo '<pre style="padding:1rem;white-space:pre-wrap;">'
            . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8')
            . '</pre>';
        return;
    }

    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Bir hata oluştu</title></head>'
        . '<body style="font-family:sans-serif;text-align:center;padding:4rem 1rem;">'
        . '<h1>Bir hata oluştu</h1>'
        . '<p>İşleminiz şu anda gerçekleştirilemiyor. Lütfen daha sonra tekrar deneyin.</p>'
        . '</body></html>';
});

/**
 * PHP uyarı ve notice'larını ErrorException'a çevirerek tek noktadan yönetir.
 */
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    // error_reporting ile bastırılmış (@) hataları yoksay
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

/**
 * Script ölümcül hata ile sonlanırsa (fatal error) logla.
 */
register_shutdown_function(static function (): void {
    $error = error_get_last();
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

    if ($error !== null && in_array($error['type'], $fatal, true)) {
        error_log(sprintf(
            '[%s] FATAL: %s in %s:%d',
            date('Y-m-d H:i:s'),
            $error['message'],
            $error['file'],
            $error['line']
        ));
    }
});

// -----------------------------------------------------------------------------
// 7. Uygulamayı başlat
// -----------------------------------------------------------------------------
$app = new App\Core\App($config);
$app->run();