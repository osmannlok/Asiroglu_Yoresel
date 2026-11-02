<?php

/**
 * Aşıroğlu Yöresel Market - Kupon modeli
 *
 * coupons ve coupon_usages tabloları (migration 010) için veri erişimi sağlar.
 *
 * Kurallar (DECISIONS.md, Kupon):
 * - Kuponlar fiziksel silinmez; delete() soft delete yapar (deleted_at).
 *   Tüm kupon sorgularında "deleted_at IS NULL" zorunludur.
 * - Toplam limit kontrolü COUNT(*) ile değil coupons.used_count ile yapılır.
 *   Kullanıcı başına limit coupon_usages kayıtlarından hesaplanır.
 * - used_count yalnızca recordUsage() (artır) ve releaseUsageForOrder()
 *   (azalt) ile değişir; ikisi de sipariş transaction'ı içinde çağrılmalıdır
 *   ve kupon satırını FOR UPDATE ile kilitler. used_count fillable değildir.
 * - Kod büyük harfe çevrilerek saklanır ve aranır (HOSGELDIN10).
 * - Tutarlar TRY ve KDV dahildir.
 *   percentage: yüzde (0-100], max_discount_amount ile sınırlanabilir.
 *   fixed_amount: sabit tutar. free_shipping: indirim tutarı 0, kargo ücretsiz
 *   (discount_value 0 olmalıdır).
 * - İndirim hiçbir zaman sepet tutarını aşamaz.
 * - Misafir siparişinde (user_id NULL) kullanıcı başına limit uygulanamaz;
 *   toplam limit yine geçerlidir.
 * - Kupon henüz Order::place() ile bağlı değildir; bağlama ayrı görevdir.
 *
 * Tüm sorgular PDO prepared statement ile çalışır; $_GET / $_POST kullanılmaz.
 *
 * Gereksinimler: FR-081, FR-082, FR-083, FR-086, NFR-001.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;

final class Coupon extends Model
{
    /** Sayfalamada tek sayfada izin verilen en yüksek kayıt sayısı */
    private const MAX_PER_PAGE = 100;

    /** Geçerli indirim türleri (coupons.discount_type ENUM) */
    public const TYPES = ['percentage', 'fixed_amount', 'free_shipping'];

    /** Kod biçimi: büyük harf, rakam, tire, alt çizgi */
    private const CODE_PATTERN = '/^[A-Z0-9_-]{3,50}$/';

    /** En büyük tutar (DECIMAL(12,2) ile uyumlu sınır) */
    private const MAX_AMOUNT = 9999999999.99;

    protected string $table = 'coupons';

    /** Kuponlar fiziksel silinmez */
    protected bool $softDelete = true;

    /**
     * used_count bilinçli olarak dışarıdadır (yalnızca recordUsage/release).
     *
     * @var list<string>
     */
    protected array $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'usage_limit_per_user',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    // -------------------------------------------------------------------------
    // Sorgular
    // -------------------------------------------------------------------------

    /**
     * Koda göre silinmemiş kuponu getirir (aktif/pasif fark etmez).
     *
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM coupons WHERE code = ? AND deleted_at IS NULL',
            [$this->normalizeCode($code)]
        );
    }

    /**
     * Kullanıcının bu kuponu kaç kez kullandığı.
     */
    public function userUsageCount(int $couponId, int $userId): int
    {
        return (int) $this->fetchValue(
            'SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = ? AND user_id = ?',
            [$couponId, $userId]
        );
    }

    /**
     * Admin listesi için sayfalı kupon listesi. $search kodda arar.
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

        // $where yalnızca sabit parçalardan oluşur; girdi '?' parametresidir.
        $where  = 'deleted_at IS NULL';
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $where   .= " AND code LIKE ? ESCAPE '!'";
            $params[] = '%' . $this->escapeLike($this->normalizeCode($search)) . '%';
        }

        $total      = (int) $this->fetchValue('SELECT COUNT(*) FROM coupons WHERE ' . $where, $params);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = min(max(1, $page), $totalPages);
        $offset     = ($page - 1) * $perPage;

        // LIMIT ve OFFSET tamsayıya çevrilmiş değerlerdir, güvenle eklenir
        $items = $this->fetchAll(
            'SELECT * FROM coupons WHERE ' . $where
            . ' ORDER BY created_at DESC, id DESC'
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
    // Sepete uygulama (FR-083)
    // -------------------------------------------------------------------------

    /**
     * Kuponun verilen sepet için geçerli olup olmadığını denetler ve indirimi
     * hesaplar. Geçersizse kullanıcıya gösterilebilir mesajla hata fırlatır.
     *
     * $subtotal kargo hariç ürün tutarıdır (KDV dahil, TRY).
     * Bu metot yazmaz; kesin limit denetimi recordUsage() içinde kilitle yapılır.
     *
     * @return array{coupon: array<string, mixed>, discount: float, free_shipping: bool}
     *
     * @throws InvalidArgumentException Kupon geçerli değilse
     */
    public function validateForCart(string $code, float $subtotal, ?int $userId = null, ?int $now = null): array
    {
        $normalized = $this->normalizeCode($code);

        if ($normalized === '') {
            throw new InvalidArgumentException('Lütfen bir kupon kodu girin.');
        }

        $coupon = $this->findByCode($normalized);

        if ($coupon === null) {
            throw new InvalidArgumentException('Kupon kodu geçersiz.');
        }

        $this->assertUsable($coupon, $userId, $now ?? time());

        $min = (float) $coupon['min_order_amount'];

        if (round($subtotal, 2) < $min) {
            throw new InvalidArgumentException(
                'Bu kupon için sepet tutarı en az ' . $this->money($min) . ' olmalıdır.'
            );
        }

        return [
            'coupon'        => $coupon,
            'discount'      => $this->calculateDiscount($coupon, $subtotal),
            'free_shipping' => (string) $coupon['discount_type'] === 'free_shipping',
        ];
    }

    /**
     * İndirim tutarını hesaplar (TRY). free_shipping için 0 döner; kargo
     * ücretsizliği çağıran tarafından uygulanır. İndirim sepet tutarını aşamaz.
     *
     * @param array<string, mixed> $coupon coupons satırı
     */
    public function calculateDiscount(array $coupon, float $subtotal): float
    {
        $subtotal = max(0.0, round($subtotal, 2));
        $value    = (float) $coupon['discount_value'];

        switch ((string) $coupon['discount_type']) {
            case 'percentage':
                $discount = round($subtotal * $value / 100, 2);
                $cap      = $coupon['max_discount_amount'] ?? null;

                if ($cap !== null && $cap !== '') {
                    $discount = min($discount, (float) $cap);
                }
                break;

            case 'fixed_amount':
                $discount = $value;
                break;

            default:
                $discount = 0.0;
        }

        return round(min($discount, $subtotal), 2);
    }

    // -------------------------------------------------------------------------
    // Kullanım kaydı (sipariş transaction'ı içinde çağrılmalıdır)
    // -------------------------------------------------------------------------

    /**
     * Kupon kullanımını kaydeder ve used_count'u artırır. Kupon satırı FOR UPDATE
     * ile kilitlenir; limitler kilit altında yeniden denetlenir. Eşzamanlı iki
     * siparişin son hakkı birlikte kullanması böylece engellenir.
     *
     * Order::place() transaction'ının içinde çağrılmalıdır (transaction iç içe
     * çağrıda dıştakine katılır).
     *
     * @return int Yeni coupon_usages kaydının ID'si
     *
     * @throws InvalidArgumentException Kupon artık kullanılamıyorsa
     */
    public function recordUsage(int $couponId, ?int $userId, int $orderId, float $discountAmount): int
    {
        if ($couponId <= 0 || $orderId <= 0 || $discountAmount < 0) {
            throw new InvalidArgumentException('Geçersiz kupon kullanım kaydı.');
        }

        return (int) $this->transaction(
            function () use ($couponId, $userId, $orderId, $discountAmount): int {
                $coupon = $this->fetchOne(
                    'SELECT * FROM coupons WHERE id = ? AND deleted_at IS NULL FOR UPDATE',
                    [$couponId]
                );

                if ($coupon === null) {
                    throw new InvalidArgumentException('Kupon kodu geçersiz.');
                }

                $this->assertUsable($coupon, $userId, time());

                $this->execute(
                    'INSERT INTO coupon_usages (coupon_id, user_id, order_id, discount_amount)'
                    . ' VALUES (?, ?, ?, ?)',
                    [$couponId, $userId, $orderId, number_format(round($discountAmount, 2), 2, '.', '')]
                );

                $usageId = (int) $this->db()->lastInsertId();

                $this->execute(
                    'UPDATE coupons SET used_count = used_count + 1 WHERE id = ?',
                    [$couponId]
                );

                return $usageId;
            }
        );
    }

    /**
     * Siparişin kupon kullanım kaydını siler ve used_count'u azaltır (sipariş
     * iptali). Kayıt yoksa bir şey yapmaz. Sipariş iptal transaction'ı içinde
     * çağrılmalıdır.
     *
     * @return bool Bir kullanım kaydı geri alındıysa true
     */
    public function releaseUsageForOrder(int $orderId): bool
    {
        return (bool) $this->transaction(function () use ($orderId): bool {
            $usages = $this->fetchAll(
                'SELECT id, coupon_id FROM coupon_usages WHERE order_id = ? FOR UPDATE',
                [$orderId]
            );

            foreach ($usages as $usage) {
                $this->execute('DELETE FROM coupon_usages WHERE id = ?', [(int) $usage['id']]);

                // used_count negatife düşmesin (UNSIGNED taşmasına karşı)
                $this->execute(
                    'UPDATE coupons SET used_count = GREATEST(used_count, 1) - 1 WHERE id = ?',
                    [(int) $usage['coupon_id']]
                );
            }

            return $usages !== [];
        });
    }

    // -------------------------------------------------------------------------
    // İş kuralı kontrolleri (controller / form doğrulamasında kullanılır)
    // -------------------------------------------------------------------------

    /**
     * Kod başka bir kuponda kullanılıyor mu?
     *
     * DİKKAT: code UNIQUE indeksi soft delete ile silinmiş kayıtları da
     * kapsar; bu yüzden silinmiş kuponlar da kontrole dahildir.
     * $exceptId, düzenlenen kaydın kendisini hariç tutar.
     */
    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM coupons WHERE code = ?';
        $params = [$this->normalizeCode($code)];

        if ($exceptId !== null) {
            $sql     .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return (int) $this->fetchValue($sql, $params) > 0;
    }

    // -------------------------------------------------------------------------
    // Yazma işlemleri
    // -------------------------------------------------------------------------

    /**
     * Yeni kupon ekler ve ID'sini döndürür. Kod büyük harfe çevrilir; alan
     * kuralları denetlenir.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException Kural ihlal edilirse
     */
    public function create(array $data): int
    {
        if (isset($data['code'])) {
            $data['code'] = $this->normalizeCode((string) $data['code']);
        }

        $this->assertValues($data, null);

        return parent::create($data);
    }

    /**
     * Kuponu günceller. Alan kuralları mevcut kayıtla birleştirilerek denetlenir.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException Kural ihlal edilirse
     */
    public function update(int $id, array $data): bool
    {
        $existing = $this->find($id);

        if ($existing === null) {
            return false;
        }

        if (isset($data['code'])) {
            $data['code'] = $this->normalizeCode((string) $data['code']);
        }

        $this->assertValues($data, $existing);

        return parent::update($id, $data);
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Kuponun şu an kullanılabilir olduğunu denetler: aktif, tarih aralığında,
     * toplam ve kullanıcı başına limit dolmamış.
     *
     * @param array<string, mixed> $coupon coupons satırı
     *
     * @throws InvalidArgumentException Kullanılamazsa
     */
    private function assertUsable(array $coupon, ?int $userId, int $now): void
    {
        if ((int) $coupon['is_active'] !== 1) {
            throw new InvalidArgumentException('Bu kupon şu an kullanılamıyor.');
        }

        $start = $coupon['starts_at'] ?? null;
        $end   = $coupon['ends_at'] ?? null;

        if (is_string($start) && $start !== '' && strtotime($start) > $now) {
            throw new InvalidArgumentException('Bu kuponun geçerlilik süresi henüz başlamadı.');
        }

        if (is_string($end) && $end !== '' && strtotime($end) < $now) {
            throw new InvalidArgumentException('Bu kuponun süresi dolmuş.');
        }

        $limit = $coupon['usage_limit'] ?? null;

        if ($limit !== null && $limit !== '' && (int) $coupon['used_count'] >= (int) $limit) {
            throw new InvalidArgumentException('Bu kuponun kullanım limiti dolmuş.');
        }

        $perUser = $coupon['usage_limit_per_user'] ?? null;

        if (
            $userId !== null
            && $perUser !== null && $perUser !== ''
            && $this->userUsageCount((int) $coupon['id'], $userId) >= (int) $perUser
        ) {
            throw new InvalidArgumentException('Bu kuponu kullanım hakkınızı doldurdunuz.');
        }
    }

    /**
     * Alan kurallarını denetler. $existing, güncellemede verilmeyen alanların
     * mevcut değerlerini sağlar.
     *
     * @param array<string, mixed>      $data
     * @param array<string, mixed>|null $existing
     *
     * @throws InvalidArgumentException Kural ihlal edilirse
     */
    private function assertValues(array $data, ?array $existing): void
    {
        $merged = $data + ($existing ?? []);

        $code = $merged['code'] ?? null;

        if (!is_string($code) || preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new InvalidArgumentException(
                'Kupon kodu 3-50 karakter olmalı; yalnızca harf, rakam, tire ve alt çizgi içerebilir.'
            );
        }

        $type = $merged['discount_type'] ?? null;

        if (!is_string($type) || !in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Geçersiz indirim türü.');
        }

        $value = $merged['discount_value'] ?? 0;

        if (!is_numeric($value) || (float) $value < 0 || (float) $value > self::MAX_AMOUNT) {
            throw new InvalidArgumentException('İndirim değeri geçersiz.');
        }

        if ($type === 'percentage' && ((float) $value <= 0 || (float) $value > 100)) {
            throw new InvalidArgumentException('Yüzde indirim 0 ile 100 arasında olmalıdır.');
        }

        if ($type === 'fixed_amount' && (float) $value <= 0) {
            throw new InvalidArgumentException('Sabit indirim tutarı sıfırdan büyük olmalıdır.');
        }

        if ($type === 'free_shipping' && (float) $value !== 0.0) {
            throw new InvalidArgumentException('Ücretsiz kargo kuponunda indirim değeri 0 olmalıdır.');
        }

        $min = $merged['min_order_amount'] ?? 0;

        if (!is_numeric($min) || (float) $min < 0 || (float) $min > self::MAX_AMOUNT) {
            throw new InvalidArgumentException('Minimum sepet tutarı geçersiz.');
        }

        $cap = $merged['max_discount_amount'] ?? null;

        if ($cap !== null && $cap !== '') {
            if ($type !== 'percentage') {
                throw new InvalidArgumentException('En yüksek indirim tutarı yalnızca yüzde kuponlarında kullanılır.');
            }

            if (!is_numeric($cap) || (float) $cap <= 0 || (float) $cap > self::MAX_AMOUNT) {
                throw new InvalidArgumentException('En yüksek indirim tutarı sıfırdan büyük olmalıdır.');
            }
        }

        foreach (['usage_limit' => 'Toplam kullanım limiti', 'usage_limit_per_user' => 'Kullanıcı başına limit'] as $field => $label) {
            $limit = $merged[$field] ?? null;

            if ($limit !== null && $limit !== '' && (!is_numeric($limit) || (int) $limit < 1)) {
                throw new InvalidArgumentException($label . ' en az 1 olmalıdır (sınırsız için boş bırakın).');
            }
        }

        $start = $merged['starts_at'] ?? null;
        $end   = $merged['ends_at'] ?? null;

        if (is_string($start) && $start !== '' && is_string($end) && $end !== '') {
            $startTime = strtotime($start);
            $endTime   = strtotime($end);

            if ($startTime === false || $endTime === false || $endTime < $startTime) {
                throw new InvalidArgumentException('Bitiş tarihi, başlangıç tarihinden önce olamaz.');
            }
        }
    }

    /** Kodu karşılaştırma ve kayıt için tutarlı hale getirir. */
    private function normalizeCode(string $code): string
    {
        return mb_strtoupper(trim($code), 'UTF-8');
    }

    /** Tutarı Türkçe biçimde yazar: 1.250,50 ₺ */
    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' ₺';
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