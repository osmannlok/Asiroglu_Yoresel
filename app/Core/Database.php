<?php

/**
 * Aşıroğlu Yöresel Market - Veritabanı bağlantısı (Singleton PDO)
 *
 * Uygulama boyunca tek bir PDO bağlantısı kullanılır. Bağlantı ilk
 * ihtiyaç duyulduğunda (tembel) açılır. Ayarlar config/database.php
 * dosyasından, App::config('database') ile okunur.
 */

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    /** Tek PDO örneği */
    private static ?PDO $connection = null;

    /** Dışarıdan new Database() yapılamaz */
    private function __construct()
    {
    }

    /** Singleton'ın kopyalanması engellenir */
    private function __clone()
    {
    }

    /** Singleton'ın unserialize ile çoğaltılması engellenir */
    public function __wakeup(): void
    {
        throw new RuntimeException('Database nesnesi unserialize edilemez.');
    }

    /**
     * PDO bağlantısını döndürür; yoksa oluşturur.
     */
    public static function connection(): PDO
    {
        if (self::$connection === null) {
            self::$connection = self::connect();
        }

        return self::$connection;
    }

    /**
     * Bağlantıyı kapatır (test veya uzun süren işlemler için).
     * Açık bir transaction varsa PDO nesnesi yok edilirken geri alınır.
     */
    public static function disconnect(): void
    {
        self::$connection = null;
    }

    /**
     * Yapılandırmayı okuyup yeni PDO bağlantısı açar.
     */
    private static function connect(): PDO
    {
        $config = (array) App::config('database', []);

        $host      = (string) ($config['host'] ?? '127.0.0.1');
        $port      = (int) ($config['port'] ?? 3306);
        $name      = (string) ($config['database'] ?? '');
        $user      = (string) ($config['username'] ?? '');
        $password  = (string) ($config['password'] ?? '');
        $charset   = (string) ($config['charset'] ?? 'utf8mb4');
        $collation = (string) ($config['collation'] ?? 'utf8mb4_unicode_ci');

        if ($name === '' || $user === '') {
            error_log('[' . date('Y-m-d H:i:s') . '] Veritabanı ayarları eksik (database, username).');

            throw new RuntimeException('Veritabanı yapılandırması eksik.');
        }

        // SET NAMES parametre bağlamayı desteklemez; değerler önce doğrulanır
        if (
            preg_match('/^[a-z0-9_]+$/i', $charset) !== 1
            || preg_match('/^[a-z0-9_]+$/i', $collation) !== 1
        ) {
            throw new RuntimeException('Geçersiz veritabanı karakter seti ayarı.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $host,
            $port,
            $name,
            $charset
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Gerçek prepared statement
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $password, $options);

            // Bağlantı karakter seti ve collation'ı şema ile aynı olsun
            $pdo->exec('SET NAMES ' . $charset . ' COLLATE ' . $collation);

            return $pdo;
        } catch (PDOException $e) {
            // Yalnızca mesaj loglanır; kullanıcıya ve exception zincirine
            // bağlantı bilgisi (host, kullanıcı, şifre) taşınmaz
            error_log(sprintf(
                '[%s] Veritabanına bağlanılamadı: %s',
                date('Y-m-d H:i:s'),
                $e->getMessage()
            ));

            throw new RuntimeException('Veritabanı bağlantısı kurulamadı.');
        }
    }
}