<?php

/**
 * Aşıroğlu Yöresel Market - Kategori modeli
 *
 * categories tablosu (migration 003) için veri erişimi sağlar.
 * Kategoriler alt kategori destekler (parent_id) ve soft delete kullanır
 * (deleted_at). Tüm sorgular Model sınıfının PDO prepared statement
 * yardımcılarıyla çalışır; girdi doğrulaması (Validator) controller
 * katmanında yapılır, bu sınıfta $_GET / $_POST kullanılmaz.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;

final class Category extends Model
{
    /** Sayfalamada tek sayfada izin verilen en yüksek kayıt sayısı */
    private const MAX_PER_PAGE = 100;

    protected string $table = 'categories';

    /** Kategoriler fiziksel silinmez; deleted_at doldurulur */
    protected bool $softDelete = true;

    /**
     * create() ve update() ile yazılabilen sütunlar.
     * id, created_at, updated_at ve deleted_at bilinçli olarak dışarıda tutulur.
     *
     * @var list<string>
     */
    protected array $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
        'icon',
        'meta_title',
        'meta_description',
        'sort_order',
        'is_active',
        'is_featured',
    ];

    // -------------------------------------------------------------------------
    // Vitrin (müşteri tarafı) sorguları
    // -------------------------------------------------------------------------

    /**
     * Slug'a göre aktif ve silinmemiş kategoriyi getirir (vitrin sayfası için).
     *
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM categories'
            . ' WHERE slug = ? AND is_active = 1 AND deleted_at IS NULL',
            [$slug]
        );
    }

    /**
     * Menü ve filtre için aktif kategorileri ağaç yapısında döndürür.
     * Her kategoride alt kategoriler 'children' anahtarında bulunur.
     * Üst kategorisi pasif veya silinmiş olan alt kategoriler gösterilmez.
     *
     * @return list<array<string, mixed>>
     */
    public function getActiveTree(): array
    {
        $rows = $this->fetchAll(
            'SELECT * FROM categories'
            . ' WHERE is_active = 1 AND deleted_at IS NULL'
            . ' ORDER BY sort_order ASC, name ASC'
        );

        // Satırları üst kategori kimliğine göre grupla (ana kategoriler: 0)
        $byParent = [];
        foreach ($rows as $row) {
            $key = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
            $byParent[$key][] = $row;
        }

        return $this->buildTree($byParent, 0);
    }

    // -------------------------------------------------------------------------
    // Admin paneli sorguları
    // -------------------------------------------------------------------------

    /**
     * Admin listesi için sayfalı kategori listesi (üst kategori adıyla).
     * $search verilirse kategori adında arar.
     *
     * @return array{
     *     items: list<array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     perPage: int,
     *     totalPages: int
     * }
     */
    public function paginate(int $page = 1, int $perPage = 20, ?string $search = null): array
    {
        $perPage = min(self::MAX_PER_PAGE, max(1, $perPage));

        // $where yalnızca bu metottaki sabit parçalardan oluşur; kullanıcı
        // girdisi her zaman '?' parametresi olarak bağlanır.
        $where  = 'c.deleted_at IS NULL';
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $where   .= " AND c.name LIKE ? ESCAPE '!'";
            $params[] = '%' . $this->escapeLike(trim($search)) . '%';
        }

        $total      = (int) $this->fetchValue(
            'SELECT COUNT(*) FROM categories c WHERE ' . $where,
            $params
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = min(max(1, $page), $totalPages);
        $offset     = ($page - 1) * $perPage;

        // LIMIT ve OFFSET tamsayıya çevrilmiş değerlerdir, güvenle eklenir
        $items = $this->fetchAll(
            'SELECT c.*, p.name AS parent_name'
            . ' FROM categories c'
            . ' LEFT JOIN categories p ON p.id = c.parent_id AND p.deleted_at IS NULL'
            . ' WHERE ' . $where
            . ' ORDER BY c.sort_order ASC, c.name ASC'
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
        ];
    }

    // -------------------------------------------------------------------------
    // İş kuralı kontrolleri (controller / form doğrulamasında kullanılır)
    // -------------------------------------------------------------------------

    /**
     * Slug başka bir kategoride kullanılıyor mu?
     *
     * DİKKAT: slug sütunundaki UNIQUE indeks soft delete ile silinmiş
     * kayıtları da kapsar. Bu yüzden silinmiş kategoriler de kontrole dahildir.
     * $exceptId, düzenlenen kaydın kendisini hariç tutar.
     */
    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM categories WHERE slug = ?';
        $params = [$slug];

        if ($exceptId !== null) {
            $sql     .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return (int) $this->fetchValue($sql, $params) > 0;
    }

    /**
     * Kategoriyi $parentId altına taşımak döngü oluşturur mu?
     * Kategorinin kendisi veya kendi alt kategorilerinden biri üst kategori
     * seçilirse true döner. $parentId null ise (ana kategori) döngü olmaz.
     */
    public function wouldCreateCycle(int $id, ?int $parentId): bool
    {
        if ($parentId === null) {
            return false;
        }

        $current = $parentId;
        $visited = [];

        // Üst kategori zincirinde yukarı doğru ilerle
        while ($current !== null) {
            if ($current === $id) {
                return true;
            }

            // Veride zaten bir döngü varsa sonsuz döngüye girme; güvenli tarafta kal
            if (isset($visited[$current])) {
                return true;
            }
            $visited[$current] = true;

            $row = $this->fetchOne('SELECT parent_id FROM categories WHERE id = ?', [$current]);

            if ($row === null) {
                break;
            }

            $current = $row['parent_id'] === null ? null : (int) $row['parent_id'];
        }

        return false;
    }

    /**
     * Kategorinin silinmemiş alt kategorisi var mı?
     */
    public function hasChildren(int $id): bool
    {
        return (int) $this->fetchValue(
            'SELECT COUNT(*) FROM categories WHERE parent_id = ? AND deleted_at IS NULL',
            [$id]
        ) > 0;
    }

    /**
     * Kategoriye bağlı silinmemiş ürün var mı? (ürünün aktif/pasif olması önemsizdir;
     * silinen kategorinin altında canlı ürün kalmasın diye deleted_at denetlenir.)
     * Silme işleminden önce çağrılmalıdır.
     */
    public function hasActiveProducts(int $id): bool
    {
        return (int) $this->fetchValue(
            'SELECT COUNT(*) FROM products WHERE category_id = ? AND deleted_at IS NULL',
            [$id]
        ) > 0;
    }

    // -------------------------------------------------------------------------
    // Model sınıfının davranışını genişleten metotlar
    // -------------------------------------------------------------------------

    /**
     * Kaydı günceller. Üst kategori değişiyorsa döngü oluşmadığını doğrular.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException Döngü oluşacaksa
     */
    public function update(int $id, array $data): bool
    {
        if (array_key_exists('parent_id', $data)) {
            $parentId = $data['parent_id'] === null || $data['parent_id'] === ''
                ? null
                : (int) $data['parent_id'];

            if ($this->wouldCreateCycle($id, $parentId)) {
                throw new InvalidArgumentException(
                    'Bir kategori kendisinin veya kendi alt kategorisinin altına taşınamaz.'
                );
            }
        }

        return parent::update($id, $data);
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Üst kategori kimliğine göre gruplanmış satırlardan özyinelemeli ağaç kurar.
     * Kökten başladığı için veride döngü olsa bile ulaşılamayan düğümler atlanır.
     *
     * @param array<int, list<array<string, mixed>>> $byParent
     * @return list<array<string, mixed>>
     */
    private function buildTree(array $byParent, int $parentKey): array
    {
        $branch = [];

        foreach ($byParent[$parentKey] ?? [] as $row) {
            $row['children'] = $this->buildTree($byParent, (int) $row['id']);
            $branch[]        = $row;
        }

        return $branch;
    }

    /**
     * LIKE aramasında özel anlamı olan karakterleri kaçırır.
     * Kaçış karakteri olarak '!' kullanılır (sorguda ESCAPE '!' ile eşleşir).
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}