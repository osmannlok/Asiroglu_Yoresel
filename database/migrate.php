<?php

/**
 * Aşıroğlu Yöresel Market - Migration çalıştırıcı (yalnızca komut satırı)
 *
 * Kullanım:
 *   php database/migrate.php            Bekleyen migration'ları sırayla çalıştırır
 *   php database/migrate.php --status   Durumu listeler (veritabanını DEĞİŞTİRMEZ)
 *   php database/migrate.php --help     Yardım
 *
 * Kurallar (DECISIONS.md, DATABASE-DEPENDENCIES.md):
 * - Migration dosyaları database/migrations klasöründedir ve dosya adı sırasıyla
 *   (NNN_create_<ad>.sql) çalıştırılır.
 * - Başarıyla çalışan her dosya schema_migrations tablosuna kaydedilir ve
 *   tekrar çalıştırılmaz. schema_migrations uygulama tablosu değildir; bu betik
 *   tarafından otomatik oluşturulur.
 * - Çalışmış bir migration dosyası değiştirilemez. Dosya içeriği değişmişse
 *   (checksum farkı) betik hata verip durur; yeni kolon için yeni migration yazılır.
 * - MySQL'de DDL (CREATE/ALTER TABLE) transaction ile geri alınamaz. Bir dosyanın
 *   ortasında hata olursa o dosya kaydedilmez; önceki ifadelerin oluşturduğu
 *   tabloları elle temizleyip yeniden çalıştırmak gerekir.
 *
 * Bağlantı ayarları config/config.php üzerinden .env dosyasından okunur.
 * Bu betik ayarları ve şifreyi ekrana yazdırmaz.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.');
}

// config/config.php bu sabitleri bekler (public/index.php ile aynı değerler)
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// Dosya yalnızca çalışır; sınıf adlandırılmadığı için ek bir "declaration" oluşmaz
exit((new class () {
    /** Çalıştırılan migration'ların kaydı (uygulama tablosu değildir) */
    private const TABLE = 'schema_migrations';

    /** Aynı anda iki migration işleminin çalışmasını engelleyen kilit adı */
    private const LOCK_NAME = 'asiroglu_market_migrate';

    /** Geçerli dosya adı: 001_create_users.sql */
    private const FILE_PATTERN = '/^(\d{3})_[a-z0-9_]+\.sql$/';

    private ?\PDO $pdo = null;

    private string $databaseName = '';

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $options = array_slice($argv, 1);

        if (count($options) > 1 || array_diff($options, ['--status', '--help', '-h']) !== []) {
            $this->error('Geçersiz seçenek. Yardım için: php database/migrate.php --help');

            return 2;
        }

        if ($options === ['--help'] || $options === ['-h']) {
            $this->printHelp();

            return 0;
        }

        try {
            $this->pdo = $this->connect();

            return $options === ['--status'] ? $this->status() : $this->migrate();
        } catch (\Throwable $e) {
            $this->error('HATA: ' . $e->getMessage());

            return 1;
        }
    }

    // -------------------------------------------------------------------------
    // Komutlar
    // -------------------------------------------------------------------------

    /**
     * --status: veritabanını değiştirmeden her dosyanın durumunu listeler.
     */
    private function status(): int
    {
        $files   = $this->discoverFiles();
        $applied = $this->fetchApplied(false);

        echo 'Migration durumu (veritabanı: ' . $this->databaseName . ')' . PHP_EOL . PHP_EOL;

        $problem      = false;
        $appliedCount = 0;
        $pendingCount = 0;

        foreach ($files as $name => $file) {
            if (isset($applied[$name])) {
                if ($applied[$name]['checksum'] !== $file['checksum']) {
                    $label   = '[DEĞİŞTİRİLMİŞ]';
                    $note    = 'çalıştıktan sonra dosya değiştirilmiş';
                    $problem = true;
                } else {
                    $label = '[uygulandı]';
                    $note  = $applied[$name]['executed_at'];
                    $appliedCount++;
                }
            } elseif ($file['statements'] === []) {
                $label   = '[BOŞ]';
                $note    = 'dosyada çalıştırılabilir SQL yok';
                $problem = true;
            } else {
                $label = '[bekliyor]';
                $note  = '';
                $pendingCount++;
            }

            echo '  ' . $this->pad($label, 17) . $this->pad($name, 36) . $note . PHP_EOL;
        }

        foreach ($applied as $name => $row) {
            if (!isset($files[$name])) {
                echo '  ' . $this->pad('[DOSYA YOK]', 17) . $this->pad($name, 36)
                    . 'uygulanmış ama dosyası bulunamadı' . PHP_EOL;
                $problem = true;
            }
        }

        echo PHP_EOL . 'Özet: ' . $appliedCount . ' uygulandı, ' . $pendingCount . ' bekliyor.' . PHP_EOL;

        return $problem ? 1 : 0;
    }

    /**
     * Bekleyen migration'ları sırayla çalıştırır.
     */
    private function migrate(): int
    {
        $files = $this->discoverFiles();

        $this->acquireLock();

        try {
            $applied = $this->fetchApplied(true);

            // 1) Çalışmış dosyalar değiştirilmemiş olmalı
            $modified = [];
            foreach ($applied as $name => $row) {
                if (isset($files[$name]) && $files[$name]['checksum'] !== $row['checksum']) {
                    $modified[] = $name;
                }
            }

            if ($modified !== []) {
                throw new \RuntimeException(
                    "Çalışmış migration dosyası değiştirilmiş: " . implode(', ', $modified) . "\n"
                    . "  Kural: çalışmış migration değiştirilmez. Dosyayı eski haline getirin; yeni kolon için yeni migration yazın."
                );
            }

            foreach (array_keys($applied) as $name) {
                if (!isset($files[$name])) {
                    echo 'UYARI: Uygulanmış migration dosyası bulunamadı: ' . $name . PHP_EOL;
                }
            }

            // 2) Bekleyenleri belirle
            $pending = array_diff_key($files, $applied);

            if ($pending === []) {
                echo 'Bekleyen migration yok. Veritabanı güncel.' . PHP_EOL;

                return 0;
            }

            // 3) Foreign key sırası: bekleyen dosya, uygulanmış son dosyadan önce olamaz
            $lastApplied = $applied === [] ? '' : max(array_keys($applied));
            foreach (array_keys($pending) as $name) {
                if ($lastApplied !== '' && strcmp($name, $lastApplied) < 0) {
                    throw new \RuntimeException(
                        'Bekleyen migration (' . $name . '), uygulanmış son migration (' . $lastApplied
                        . ") öncesinde kalıyor.\n  Bağımlılık sırası bozulur; yeni dosyayı son numaradan sonra adlandırın."
                    );
                }
            }

            // 4) Başlamadan önce boş dosyaları yakala (yarım kalmasın)
            foreach ($pending as $name => $file) {
                if ($file['statements'] === []) {
                    throw new \RuntimeException('Migration dosyası boş (çalıştırılabilir SQL yok): ' . $name);
                }
            }

            // 5) Çalıştır
            $count = 0;
            foreach ($pending as $name => $file) {
                echo 'Çalıştırılıyor: ' . $name . ' ... ';
                $this->apply($name, $file);
                echo 'tamam' . PHP_EOL;
                $count++;
            }

            echo PHP_EOL . $count . ' migration uygulandı.' . PHP_EOL;

            return 0;
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Tek migration dosyasının ifadelerini çalıştırır ve başarılıysa kaydeder.
     *
     * @param array{statements: list<string>, checksum: string} $file
     */
    private function apply(string $name, array $file): void
    {
        $total = count($file['statements']);

        foreach ($file['statements'] as $index => $statement) {
            try {
                $this->db()->exec($statement);
            } catch (\PDOException $e) {
                echo 'HATA' . PHP_EOL;

                $preview = mb_substr((string) preg_replace('/\s+/', ' ', $statement), 0, 120);

                throw new \RuntimeException(
                    $name . ': ' . ($index + 1) . '/' . $total . ". ifade başarısız oldu.\n"
                    . '  İfade: ' . $preview . "\n"
                    . '  Neden: ' . $e->getMessage() . "\n"
                    . "  DİKKAT: MySQL'de DDL işlemleri geri alınamaz; bu dosyadaki önceki ifadeler uygulanmış olabilir.\n"
                    . '  Dosya schema_migrations tablosuna KAYDEDİLMEDİ. Yeniden çalıştırmadan önce'
                    . ' bu dosyanın oluşturduğu tabloları elle silin.'
                );
            }
        }

        $stmt = $this->db()->prepare(
            'INSERT INTO ' . self::TABLE . ' (migration, checksum, executed_at) VALUES (?, ?, NOW())'
        );
        $stmt->execute([$name, $file['checksum']]);
    }

    // -------------------------------------------------------------------------
    // Dosya ve kayıt okuma
    // -------------------------------------------------------------------------

    /**
     * database/migrations altındaki dosyaları sırayla okur ve ifadelerine ayırır.
     *
     * @return array<string, array{statements: list<string>, checksum: string}>
     */
    private function discoverFiles(): array
    {
        $paths = glob(__DIR__ . '/migrations/*.sql');

        if ($paths === false || $paths === []) {
            throw new \RuntimeException('database/migrations klasöründe migration dosyası bulunamadı.');
        }

        sort($paths, SORT_STRING);

        $files   = [];
        $numbers = [];

        foreach ($paths as $path) {
            $name = basename($path);

            if (preg_match(self::FILE_PATTERN, $name, $matches) !== 1) {
                throw new \RuntimeException(
                    'Geçersiz migration dosya adı (beklenen biçim: NNN_create_<ad>.sql): ' . $name
                );
            }

            $number = (int) $matches[1];
            if (isset($numbers[$number])) {
                throw new \RuntimeException(
                    'Aynı numaraya sahip iki migration var: ' . $numbers[$number] . ' ve ' . $name
                );
            }
            $numbers[$number] = $name;

            $content = file_get_contents($path);
            if ($content === false) {
                throw new \RuntimeException('Migration dosyası okunamadı: ' . $name);
            }

            // UTF-8 BOM'u kaldır ve satır sonlarını normalleştir (Windows/Linux checksum farkı olmasın)
            $content = str_replace("\r\n", "\n", (string) preg_replace('/^\xEF\xBB\xBF/', '', $content));

            $files[$name] = [
                'statements' => $this->splitStatements($content),
                'checksum'   => hash('sha256', $content),
            ];
        }

        return $files;
    }

    /**
     * schema_migrations kayıtlarını döndürür.
     * $createTable false ise tablo yoksa oluşturulmaz, boş dizi döner.
     *
     * @return array<string, array{checksum: string, executed_at: string}>
     */
    private function fetchApplied(bool $createTable): array
    {
        if ($createTable) {
            $this->db()->exec(
                'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' ('
                . 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, '
                . 'migration VARCHAR(191) NOT NULL UNIQUE, '
                . 'checksum CHAR(64) NOT NULL, '
                . 'executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } else {
            $check = $this->db()->prepare(
                'SELECT COUNT(*) FROM information_schema.tables '
                . 'WHERE table_schema = DATABASE() AND table_name = ?'
            );
            $check->execute([self::TABLE]);

            if ((int) $check->fetchColumn() === 0) {
                return [];
            }
        }

        $rows = $this->db()
            ->query('SELECT migration, checksum, executed_at FROM ' . self::TABLE . ' ORDER BY migration')
            ->fetchAll();

        $applied = [];
        foreach ($rows as $row) {
            $applied[(string) $row['migration']] = [
                'checksum'    => (string) $row['checksum'],
                'executed_at' => (string) $row['executed_at'],
            ];
        }

        return $applied;
    }

    /**
     * SQL metnini ";" ile ifadelere ayırır.
     * Yorumlar (-- , #, / * * /) atılır; tırnak içindeki (' " `) ";" karakterleri
     * ayırıcı sayılmaz. Yorum içindeki kesme işareti ve ";" güvenle yok sayılır.
     *
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $current    = '';
        $quote      = null;
        $length     = strlen($sql);
        $i          = 0;

        while ($i < $length) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            // Tırnak içi: kaçış (\x) ve çift tırnak ('') desteklenir
            if ($quote !== null) {
                $current .= $char;

                if ($char === '\\' && $quote !== '`' && $i + 1 < $length) {
                    $current .= $next;
                    $i += 2;
                    continue;
                }

                if ($char === $quote) {
                    if ($next === $quote) {
                        $current .= $next;
                        $i += 2;
                        continue;
                    }
                    $quote = null;
                }

                $i++;
                continue;
            }

            // Satır yorumu: "-- " (tire tire boşluk) veya "#"
            $afterDashes = $sql[$i + 2] ?? "\n";
            $isDashComment = $char === '-' && $next === '-'
                && in_array($afterDashes, [' ', "\t", "\n", "\r"], true);

            if ($isDashComment || $char === '#') {
                $newline = strpos($sql, "\n", $i);
                $i       = $newline === false ? $length : $newline;
                continue;
            }

            // Blok yorumu
            if ($char === '/' && $next === '*') {
                $end      = strpos($sql, '*/', $i + 2);
                $i        = $end === false ? $length : $end + 2;
                $current .= ' ';
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote    = $char;
                $current .= $char;
                $i++;
                continue;
            }

            if ($char === ';') {
                $statement = trim($current);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $current = '';
                $i++;
                continue;
            }

            $current .= $char;
            $i++;
        }

        $statement = trim($current);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }

    // -------------------------------------------------------------------------
    // Bağlantı ve kilit
    // -------------------------------------------------------------------------

    private function connect(): \PDO
    {
        $file = CONFIG_PATH . '/config.php';

        if (!is_file($file)) {
            throw new \RuntimeException('Yapılandırma dosyası bulunamadı: config/config.php');
        }

        /** @var array<string, mixed> $config */
        $config = require $file;
        $db     = (array) ($config['database'] ?? []);

        $host      = (string) ($db['host'] ?? '127.0.0.1');
        $port      = (int) ($db['port'] ?? 3306);
        $name      = (string) ($db['database'] ?? '');
        $user      = (string) ($db['username'] ?? '');
        $password  = (string) ($db['password'] ?? '');
        $charset   = (string) ($db['charset'] ?? 'utf8mb4');
        $collation = (string) ($db['collation'] ?? 'utf8mb4_unicode_ci');

        if ($name === '' || $user === '') {
            throw new \RuntimeException(
                'Veritabanı ayarları eksik. .env dosyasında DB_DATABASE ve DB_USERNAME tanımlı olmalı.'
            );
        }

        // SET NAMES parametre bağlamayı desteklemez; değerler önce doğrulanır
        if (
            preg_match('/^[a-z0-9_]+$/i', $charset) !== 1
            || preg_match('/^[a-z0-9_]+$/i', $collation) !== 1
        ) {
            throw new \RuntimeException('Geçersiz veritabanı karakter seti ayarı.');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        try {
            $pdo = new \PDO($dsn, $user, $password, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (\PDOException $e) {
            $hint = '';

            if (str_contains($e->getMessage(), '1049')) {
                $hint = "\n  Veritabanı yoksa önce oluşturun:\n  CREATE DATABASE `" . $name
                    . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
            }

            throw new \RuntimeException('Veritabanına bağlanılamadı: ' . $e->getMessage() . $hint);
        }

        $pdo->exec('SET NAMES ' . $charset . ' COLLATE ' . $collation);
        $this->databaseName = $name;

        return $pdo;
    }

    private function db(): \PDO
    {
        if ($this->pdo === null) {
            throw new \RuntimeException('Veritabanı bağlantısı açılmadı.');
        }

        return $this->pdo;
    }

    /**
     * Aynı anda iki migration işleminin çalışmasını engeller.
     */
    private function acquireLock(): void
    {
        $stmt = $this->db()->prepare('SELECT GET_LOCK(?, 0)');
        $stmt->execute([self::LOCK_NAME]);

        if ((int) $stmt->fetchColumn() !== 1) {
            throw new \RuntimeException('Başka bir migration işlemi çalışıyor. Bitmesini bekleyin.');
        }
    }

    private function releaseLock(): void
    {
        try {
            $stmt = $this->db()->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([self::LOCK_NAME]);
        } catch (\Throwable $e) {
            // Bağlantı kapanırken kilit zaten düşer; sessizce geçilir
        }
    }

    // -------------------------------------------------------------------------
    // Çıktı yardımcıları
    // -------------------------------------------------------------------------

    /** Çok baytlı karakterlerde de doğru hizalayan sağa boşluk doldurma. */
    private function pad(string $text, int $width): string
    {
        return $text . str_repeat(' ', max(1, $width - mb_strlen($text)));
    }

    private function error(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
    }

    private function printHelp(): void
    {
        echo <<<'TXT'
Aşıroğlu Yöresel Market - Migration çalıştırıcı

Kullanım:
  php database/migrate.php            Bekleyen migration'ları sırayla çalıştırır
  php database/migrate.php --status   Durumu listeler (veritabanını değiştirmez)
  php database/migrate.php --help     Bu yardımı gösterir

Notlar:
  - Bağlantı ayarları .env dosyasından okunur (DB_HOST, DB_DATABASE, ...).
  - Veritabanı önceden oluşturulmuş olmalıdır.
  - Çalışmış migration dosyaları değiştirilemez; checksum farkı hata verir.

TXT;
    }
})->run($_SERVER['argv'] ?? []));