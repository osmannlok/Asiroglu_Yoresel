<?php

/**
 * Aşıroğlu Yöresel Market - Temel Model
 *
 * Tüm modeller bu sınıftan türetilir. PDO prepared statement ile güvenli
 * sorgu yardımcıları ve temel CRUD işlemlerini sağlar.
 */

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

abstract class Model
{
    /** Tablo adı (alt sınıfta tanımlanır, örn. 'products') */
    protected string $table = '';

    /** Birincil anahtar sütunu */
    protected string $primaryKey = 'id';

    /** true ise delete() kaydı silmez, deleted_at alanını doldurur */
    protected bool $softDelete = false;

    /**
     * create() ve update() ile yazılmasına izin verilen sütunlar.
     * Boş bırakılırsa yazma işlemleri reddedilir (güvenli varsayılan).
     *
     * @var list<string>
     */
    protected array $fillable = [];

    // -------------------------------------------------------------------------
    // Hazır CRUD metotları
    // -------------------------------------------------------------------------

    /**
     * Birincil anahtara göre tek kayıt getirir.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $sql = 'SELECT * FROM ' . $this->quotedTable()
            . ' WHERE ' . $this->quoteIdentifier($this->primaryKey) . ' = ?'
            . $this->softDeleteCondition('AND');

        return $this->fetchOne($sql, [$id]);
    }

    /**
     * Tüm kayıtları sıralı getirir. Büyük tablolarda limit kullanın.
     *
     * @return list<array<string, mixed>>
     */
    public function all(string $orderBy = 'id', string $direction = 'ASC', ?int $limit = null): array
    {
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Geçersiz sıralama yönü.');
        }

        $sql = 'SELECT * FROM ' . $this->quotedTable()
            . $this->softDeleteCondition('WHERE')
            . ' ORDER BY ' . $this->quoteIdentifier($orderBy) . ' ' . $direction;

        if ($limit !== null) {
            // Limit tamsayıya çevrilir, SQL'e güvenle eklenir
            $sql .= ' LIMIT ' . max(1, $limit);
        }

        return $this->fetchAll($sql);
    }

    /**
     * Yeni kayıt ekler ve eklenen kaydın ID'sini döndürür.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $data = $this->filterFillable($data);

        $columns      = array_map([$this, 'quoteIdentifier'], array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = 'INSERT INTO ' . $this->quotedTable()
            . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';

        $this->execute($sql, array_values($data));

        return (int) $this->db()->lastInsertId();
    }

    /**
     * Kaydı günceller. Güncellenen satır varsa true döner.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $data = $this->filterFillable($data);

        $assignments = array_map(
            fn (string $column): string => $this->quoteIdentifier($column) . ' = ?',
            array_keys($data)
        );

        $sql = 'UPDATE ' . $this->quotedTable()
            . ' SET ' . implode(', ', $assignments)
            . ' WHERE ' . $this->quoteIdentifier($this->primaryKey) . ' = ?'
            . $this->softDeleteCondition('AND');

        $params   = array_values($data);
        $params[] = $id;

        return $this->execute($sql, $params) > 0;
    }

    /**
     * Kaydı siler. Soft delete açıksa deleted_at alanını doldurur.
     */
    public function delete(int $id): bool
    {
        if ($this->softDelete) {
            $sql = 'UPDATE ' . $this->quotedTable()
                . ' SET `deleted_at` = NOW()'
                . ' WHERE ' . $this->quoteIdentifier($this->primaryKey) . ' = ?'
                . ' AND `deleted_at` IS NULL';
        } else {
            $sql = 'DELETE FROM ' . $this->quotedTable()
                . ' WHERE ' . $this->quoteIdentifier($this->primaryKey) . ' = ?';
        }

        return $this->execute($sql, [$id]) > 0;
    }

    /**
     * Tablodaki (silinmemiş) kayıt sayısını döndürür.
     */
    public function count(): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->quotedTable() . $this->softDeleteCondition('WHERE');

        return (int) $this->fetchValue($sql);
    }

    /**
     * Callback'i veritabanı transaction'ı içinde çalıştırır.
     * Hata olursa geri alır (rollBack) ve hatayı yeniden fırlatır.
     * Zaten açık bir transaction varsa onun içinde çalışır.
     *
     * Örnek: $this->transaction(fn () => $this->siparisOlustur(...));
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->db();

        // İç içe çağrıda dıştaki transaction'a katıl
        if ($pdo->inTransaction()) {
            return $callback();
        }

        try {
            $pdo->beginTransaction();
            $result = $callback();
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Alt sınıfların özel sorgularda kullanacağı yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Tüm satırları getirir.
     *
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /**
     * İlk satırı getirir; yoksa null.
     *
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * İlk satırın ilk sütununu getirir (COUNT, SUM gibi sorgular için).
     *
     * @param array<int|string, mixed> $params
     */
    protected function fetchValue(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * INSERT/UPDATE/DELETE çalıştırır ve etkilenen satır sayısını döndürür.
     *
     * @param array<int|string, mixed> $params
     */
    protected function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * PDO bağlantısını döndürür (tembel bağlantı: ilk kullanımda açılır).
     */
    protected function db(): PDO
    {
        return Database::connection();
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Prepared statement hazırlar ve çalıştırır.
     * Teknik hata ayrıntısı loga yazılır, çağırana genel bir hata fırlatılır.
     * Yinelenen kayıt (UNIQUE) gibi durumları yakalamak isteyen kod,
     * $e->getPrevious() ile asıl PDOException'a ulaşabilir.
     *
     * @param array<int|string, mixed> $params
     */
    private function run(string $sql, array $params): PDOStatement
    {
        try {
            $statement = $this->db()->prepare($sql);
            $statement->execute($params);

            return $statement;
        } catch (PDOException $e) {
            error_log(sprintf(
                '[%s] Veritabanı hatası (%s): %s | SQL: %s',
                date('Y-m-d H:i:s'),
                static::class,
                $e->getMessage(),
                $sql
            ));

            throw new RuntimeException('Veritabanı işlemi başarısız oldu.', 0, $e);
        }
    }

    /**
     * Veriyi $fillable listesine göre süzer. İzinli sütun kalmazsa hata verir.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filterFillable(array $data): array
    {
        $filtered = array_intersect_key($data, array_flip($this->fillable));

        if ($filtered === []) {
            throw new InvalidArgumentException(
                static::class . ': yazılabilir (fillable) sütun bulunamadı.'
            );
        }

        return $filtered;
    }

    /**
     * Soft delete açıksa sorguya eklenecek koşulu döndürür.
     *
     * @param 'WHERE'|'AND' $keyword
     */
    private function softDeleteCondition(string $keyword): string
    {
        return $this->softDelete ? ' ' . $keyword . ' `deleted_at` IS NULL' : '';
    }

    /**
     * Doğrulanmış ve ters tırnaklı tablo adını döndürür.
     */
    private function quotedTable(): string
    {
        if ($this->table === '') {
            throw new RuntimeException(static::class . ': $table tanımlanmamış.');
        }

        return $this->quoteIdentifier($this->table);
    }

    /**
     * Tablo/sütun adını doğrular ve ters tırnak içine alır.
     * Yalnızca harf, rakam ve alt çizgiye izin verilir.
     */
    private function quoteIdentifier(string $name): string
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException('Geçersiz sütun veya tablo adı: ' . $name);
        }

        return '`' . $name . '`';
    }
}