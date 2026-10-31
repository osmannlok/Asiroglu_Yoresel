<?php

/**
 * Aşıroğlu Yöresel Market - Marka modeli
 *
 * brands tablosu (migration 004) için veri erişimi sağlar.
 * Markalar soft delete kullanır (deleted_at). Tüm sorgular Model sınıfının
 * PDO prepared statement yardımcılarıyla çalışır; girdi doğrulaması
 * (Validator, Uploader) controller katmanında yapılır, bu sınıfta
 * $_GET / $_POST kullanılmaz.
 *
 * Gereksinimler: FR-032 (marka yönetimi), FR-033 (pasif marka ürünleri
 * vitrinde listelenmez; sipariş geçmişi etkilenmez).
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Brand extends Model
{
    /** Sayfalamada tek sayfada izin verilen en yüksek kayıt sayısı */
    private const MAX_PER_PAGE = 100;

    protected string $table = 'brands';

    /** Markalar fiziksel silinmez; deleted_at doldurulur */
    protected bool $softDelete = true;

    /**
     * create() ve update() ile yazılabilen sütunlar.
     * id, created_at, updated_at ve deleted_at bilinçli olarak dışarıda tutulur.
     *
     * @var list<string>
     */
    protected array $fillable = [
        'name',
        'slug',
        'description',
        'logo',
        'website',
        'is_active',
    ];

    // -------------------------------------------------------------------------
    // Vitrin (müşteri tarafı) sorguları
    // -------------------------------------------------------------------------

    /**
     * Slug'a göre aktif ve silinmemiş markayı getirir (vitrin sayfası için).
     *
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM brands'
            . ' WHERE slug = ? AND is_active = 1 AND deleted_at IS NULL',
            [$slug]
        );
    }

    /**
     * Filtre ve menüler için aktif markaları ada göre sıralı döndürür.
     *
     * @return list<array<string, mixed>>
     */
    public function getActive(): array
    {
        return $this->fetchAll(
            'SELECT * FROM brands'
            . ' WHERE is_active = 1 AND deleted_at IS NULL'
            . ' ORDER BY name ASC'
        );
    }

    // -------------------------------------------------------------------------
    // Admin paneli sorguları
    // -------------------------------------------------------------------------

    /**
     * Admin listesi için sayfalı marka listesi.
     * $search verilirse marka adında arar.
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
        $where  = 'deleted_at IS NULL';
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $where   .= " AND name LIKE ? ESCAPE '!'";
            $params[] = '%' . $this->escapeLike(trim($search)) . '%';
        }

        $total      = (int) $this->fetchValue(
            'SELECT COUNT(*) FROM brands WHERE ' . $where,
            $params
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = min(max(1, $page), $totalPages);
        $offset     = ($page - 1) * $perPage;

        // LIMIT ve OFFSET tamsayıya çevrilmiş değerlerdir, güvenle eklenir
        $items = $this->fetchAll(
            'SELECT * FROM brands'
            . ' WHERE ' . $where
            . ' ORDER BY name ASC'
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
     * Slug başka bir markada kullanılıyor mu?
     *
     * DİKKAT: slug sütunundaki UNIQUE indeks soft delete ile silinmiş
     * kayıtları da kapsar. Bu yüzden silinmiş markalar da kontrole dahildir.
     * $exceptId, düzenlenen kaydın kendisini hariç tutar.
     */
    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM brands WHERE slug = ?';
        $params = [$slug];

        if ($exceptId !== null) {
            $sql     .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return (int) $this->fetchValue($sql, $params) > 0;
    }

    /**
     * Markaya bağlı silinmemiş ürün var mı? (ürünün aktif/pasif olması önemsizdir.)
     * Silme işleminden önce çağrılmalıdır; ürünü olan marka önce pasifleştirilir.
     */
    public function hasActiveProducts(int $id): bool
    {
        return (int) $this->fetchValue(
            'SELECT COUNT(*) FROM products WHERE brand_id = ? AND deleted_at IS NULL',
            [$id]
        ) > 0;
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * LIKE aramasında özel anlamı olan karakterleri kaçırır.
     * Kaçış karakteri olarak '!' kullanılır (sorguda ESCAPE '!' ile eşleşir).
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}