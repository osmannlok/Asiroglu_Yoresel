<?php

/**
 * Aşıroğlu Yöresel Market - Oturum (Session) yönetimi
 *
 * Güvenli cookie ayarlarıyla oturumu başlatır; oturum verisi, flash
 * mesajları, ID yenileme ve oturum sonlandırma işlemlerini sağlar.
 * Anahtarları "_" ile başlayan değerler çekirdek için ayrılmıştır.
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Session
{
    /** Varsayılan oturum cookie adı */
    private const DEFAULT_NAME = 'ASIROGLU_SESSID';

    /** Varsayılan boşta kalma süresi (saniye): 2 saat */
    private const DEFAULT_LIFETIME = 7200;

    /**
     * Oturumu güvenli ayarlarla başlatır. Zaten başlamışsa bir şey yapmaz.
     *
     * Desteklenen ayarlar: name, lifetime, secure (null = otomatik),
     * samesite (Lax|Strict|None), save_path (isteğe bağlı).
     *
     * @param array<string, mixed> $config config/config.php içindeki 'session' dizisi
     */
    public static function start(array $config = []): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $name = (string) ($config['name'] ?? self::DEFAULT_NAME);
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new RuntimeException('Geçersiz oturum adı.');
        }

        $lifetime = max(60, (int) ($config['lifetime'] ?? self::DEFAULT_LIFETIME));

        // secure ayarı verilmemişse HTTPS durumuna göre otomatik belirlenir
        $secure = isset($config['secure']) ? (bool) $config['secure'] : self::isHttps();

        $sameSite = (string) ($config['samesite'] ?? 'Lax');
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            $sameSite = 'Lax';
        }
        // SameSite=None yalnızca Secure ile çalışır; değilse Lax'a düşür
        if ($sameSite === 'None' && !$secure) {
            $sameSite = 'Lax';
        }

        // Sıkı mod: sunucunun üretmediği oturum ID'leri kabul edilmez
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);

        // İsteğe bağlı: oturum dosyalarını web köküne uzak, özel bir klasörde tut
        $savePath = (string) ($config['save_path'] ?? '');
        if ($savePath !== '') {
            if (!is_dir($savePath)) {
                @mkdir($savePath, 0700, true);
            }
            if (is_dir($savePath) && is_writable($savePath)) {
                session_save_path($savePath);
            }
        }

        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0, // Tarayıcı kapanınca silinir; süre sunucu tarafında denetlenir
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);

        if (!session_start()) {
            throw new RuntimeException('Oturum başlatılamadı.');
        }

        // Boşta kalma süresi dolduysa oturumu sıfırla ve ID'yi yenile
        $now  = time();
        $last = $_SESSION['_last_activity'] ?? null;

        if (is_int($last) && ($now - $last) > $lifetime) {
            $_SESSION = [];
            session_regenerate_id(true);
        }

        $_SESSION['_last_activity'] = $now;

        // Flash verisi: önceki istekte yazılanlar bu istekte okunur, sonra silinir
        $_SESSION['_flash']      = is_array($_SESSION['_flash_next'] ?? null) ? $_SESSION['_flash_next'] : [];
        $_SESSION['_flash_next'] = [];
    }

    /** Oturum değerini okur. */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /** Oturum değerini yazar. */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /** Anahtar oturumda var mı? (null değer de "var" sayılır) */
    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    /** Oturum değerini siler. */
    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Değeri okur ve oturumdan siler. */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);

        return $value;
    }

    /**
     * Bir sonraki istekte okunabilecek tek kullanımlık mesaj yazar.
     * Örnek: Session::flash('success', 'Ürün sepete eklendi.')
     */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_next'][$key] = $value;
    }

    /** Önceki istekte flash ile yazılan mesajı okur. */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash'][$key] ?? $default;
    }

    /**
     * Oturum ID'sini yeniler. Giriş yapıldığında ve yetki değiştiğinde
     * çağrılmalıdır (session fixation koruması).
     */
    public static function regenerate(bool $deleteOld = true): void
    {
        session_regenerate_id($deleteOld);
    }

    /**
     * Oturumu tamamen sonlandırır: veri, sunucu kaydı ve cookie silinir.
     * Çıkış (logout) işleminde kullanılır.
     */
    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (!headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?: 'Lax',
            ]);
        }

        session_destroy();
    }

    /**
     * Oturum dosyasının kilidini erken bırakır (okuma sonrası yazma yoksa).
     * Aynı anda gelen Fetch isteklerinin birbirini beklemesini önler.
     * Bu çağrıdan sonra oturuma yazılan değerler kaydedilmez.
     */
    public static function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * İstek HTTPS üzerinden mi geliyor? (Secure cookie otomatik kararı için)
     */
    private static function isHttps(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));

        return ($https !== '' && $https !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }
}