<?php

/**
 * Aşıroğlu Yöresel Market - Kargo yöntemi modeli
 *
 * shipping_methods tablosu (migration 014) için veri erişimi sağlar:
 * "Standart Kargo", "Hızlı Kargo", "Yerel Teslimat" gibi yöntemler.
 *
 * Kurallar (DECISIONS.md, Ödeme / Kargo; migration 014):
 * - Kargo ücreti ve ücretsiz kargo eşiği yönetim panelinden tanımlanır.
 * - Fiyatlar TRY ve KDV dahildir.
 * - Sipariş tutarı free_shipping_limit değerine ulaşır veya aşarsa kargo
 *   ücretsizdir; NULL = ücretsiz kargo eşiği yok.
 * - Hesaplanan tutar sipariş anında orders.shipping_total alanına yazılır;
 *   yöntemin fiyatı sonradan değişse de geçmiş siparişler etkilenmez.
 * - Yöntemler fiziksel silinmez (tabloda deleted_at yoktur); satıştan
 *   kaldırmak için is_active = 0 kullanılır. Bu yüzden delete() kapalıdır,
 *   setActive() kullanılır.
 * - code benzersizdir (UNIQUE) ve programatik kullanım içindir
 *   (örn. 'standard'); yalnızca küçük harf, rakam ve alt çizgi içerir.
 * - Ücret kuralları controller'da Validator ile doğrulanır; burada ikinci
 *   savunma katmanı olarak denetlenir.
 *
 * Tüm sorgular PDO prepared statement ile çalışır; $_GET / $_POST
 * kullanılmaz.
 *
 * Gereksinimler: FR-045, FR-046, FR-061, FR-084, FR-085.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use BadMethodCallException;
use InvalidArgumentException;

final class ShippingMethod extends Model
{
    /** Kod biçimi: küçük harf, rakam, alt çizgi (en fazla 50 karakter) */
    private const CODE_PATTERN = '/^[a-z0-9_]{1,50}$/';

    /** En büyük ücret / eşik (DECIMAL(12,2) ile uyumlu sınır) */
    private const MAX_AMOUNT = 9999999999.99;

    protected string $table = 'shipping_methods';

    /** shipping_methods tablosunda deleted_at yoktur; pasifleştirme kullanılır */
    protected bool $softDelete = false;

    /**
     * create() ve update() ile yazılabilen sütunlar.
     * id, created_at ve updated_at bilinçli olarak dışarıda tutulur.
     *
     * @var list<string>
     */
    protected array $fillable = [
        'name',
        'code',
        'description',
        'price',
        'free_shipping_limit',
        'is_active',
        'sort_order',
    ];

    // -------------------------------------------------------------------------
    // Vitrin (checkout) sorguları
    // -------------------------------------------------------------------------

    /**
     * Müşterinin seçebileceği aktif yöntemleri sıralı döndürür (checkout).
     *
     * @return list<array<string, mixed>>
     */
    public function getActive(): array
    {
        return $this->fetchAll(
            'SELECT * FROM shipping_methods WHERE is_active = 1'
            . ' ORDER BY sort_order ASC, id ASC'
        );
    }

    /**
     * Kimliğe göre aktif yöntemi getirir (sipariş oluştururken seçimi doğrulamak
     * için). Pasif veya olmayan yöntem null döner.
     *
     * @return array<string, mixed>|null
     */
    public function findActiveById(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM shipping_methods WHERE id = ? AND is_active = 1',
            [$id]
        );
    }

    /**
     * Koda göre aktif yöntemi getirir.
     *
     * @return array<string, mixed>|null
     */
    public function findActiveByCode(string $code): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM shipping_methods WHERE code = ? AND is_active = 1',
            [trim($code)]
        );
    }

    // -------------------------------------------------------------------------
    // Ücret hesabı
    // -------------------------------------------------------------------------

    /**
     * Verilen sipariş tutarı için kargo ücretini hesaplar (TRY, KDV dahil).
     * Tutar ücretsiz kargo eşiğine ulaştıysa 0 döner.
     *
     * $orderAmount, kargo hariç ödenecek ürün tutarıdır (kupon indirimi
     * uygulanmışsa indirim sonrası tutar verilmelidir).
     *
     * @param array<string, mixed> $method shipping_methods satırı
     */
    public function calculateFee(array $method, float $orderAmount): float
    {
        $limit = $method['free_shipping_limit'] ?? null;

        if ($limit !== null && $limit !== '' && round($orderAmount, 2) >= (float) $limit) {
            return 0.0;
        }

        return round((float) ($method['price'] ?? 0), 2);
    }

    /**
     * Ücretsiz kargoya kalan tutarı döndürür (FR-046). Eşik yoksa veya
     * eşik zaten aşıldıysa null döner (gösterilecek bir şey yok).
     *
     * @param array<string, mixed> $method shipping_methods satırı
     */
    public function remainingForFreeShipping(array $method, float $orderAmount): ?float
    {
        $limit = $method['free_shipping_limit'] ?? null;

        if ($limit === null || $limit === '') {
            return null;
        }

        $remaining = round((float) $limit - $orderAmount, 2);

        return $remaining > 0 ? $remaining : null;
    }

    // -------------------------------------------------------------------------
    // Admin paneli sorguları
    // -------------------------------------------------------------------------

    /**
     * Admin listesi için tüm yöntemler (pasifler dahil). Tablo küçüktür
     * (birkaç satır); sayfalama gerekmez.
     *
     * @return list<array<string, mixed>>
     */
    public function getAllForAdmin(): array
    {
        return $this->fetchAll(
            'SELECT * FROM shipping_methods ORDER BY sort_order ASC, id ASC'
        );
    }

    // -------------------------------------------------------------------------
    // İş kuralı kontrolleri (controller / form doğrulamasında kullanılır)
    // -------------------------------------------------------------------------

    /**
     * Kod başka bir yöntemde kullanılıyor mu?
     * $exceptId, düzenlenen kaydın kendisini hariç tutar.
     */
    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM shipping_methods WHERE code = ?';
        $params = [trim($code)];

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
     * Yeni kargo yöntemi ekler ve ID'sini döndürür. Alan kuralları denetlenir.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException Kural ihlal edilirse
     */
    public function create(array $data): int
    {
        $this->assertValues($data, null);

        return parent::create($data);
    }

    /**
     * Kargo yöntemini günceller. Alan kuralları mevcut kayıtla birleştirilerek
     * denetlenir. Yöntem bulunamazsa false döner.
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

        $this->assertValues($data, $existing);

        return parent::update($id, $data);
    }

    /**
     * Yöntemi satışa açar veya kapatır. Kayıt silinmez; geçmiş siparişler
     * shipping_total alanındaki anlık tutarla etkilenmez.
     */
    public function setActive(int $id, bool $active): bool
    {
        return $this->execute(
            'UPDATE shipping_methods SET is_active = ? WHERE id = ?',
            [$active ? 1 : 0, $id]
        ) > 0;
    }

    /**
     * Kargo yöntemleri fiziksel silinmez; kapatmak için setActive(false)
     * kullanılmalıdır.
     *
     * @throws BadMethodCallException Her zaman
     */
    public function delete(int $id): bool
    {
        throw new BadMethodCallException(
            'ShippingMethod::delete() kapalıdır. setActive(false) kullanın.'
        );
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Ad, kod ve tutar kurallarını denetler. $existing, güncellemede verilmeyen
     * alanların mevcut değerlerini sağlar.
     *
     * @param array<string, mixed>      $data
     * @param array<string, mixed>|null $existing
     *
     * @throws InvalidArgumentException Kural ihlal edilirse
     */
    private function assertValues(array $data, ?array $existing): void
    {
        $merged = $data + ($existing ?? []);

        $name = $merged['name'] ?? null;

        if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 150) {
            throw new InvalidArgumentException('Kargo yöntemi adı zorunludur (en fazla 150 karakter).');
        }

        $code = $merged['code'] ?? null;

        if (!is_string($code) || preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new InvalidArgumentException(
                'Kod yalnızca küçük harf, rakam ve alt çizgi içermelidir (en fazla 50 karakter).'
            );
        }

        $price = $merged['price'] ?? null;

        if ($price === null || $price === '' || !is_numeric($price)) {
            throw new InvalidArgumentException('Kargo ücreti zorunludur.');
        }

        if ((float) $price < 0 || (float) $price > self::MAX_AMOUNT) {
            throw new InvalidArgumentException('Kargo ücreti negatif olamaz.');
        }

        $limit = $merged['free_shipping_limit'] ?? null;

        if ($limit !== null && $limit !== '') {
            if (!is_numeric($limit) || (float) $limit <= 0 || (float) $limit > self::MAX_AMOUNT) {
                throw new InvalidArgumentException(
                    'Ücretsiz kargo eşiği sıfırdan büyük olmalıdır (eşik istemiyorsanız boş bırakın).'
                );
            }
        }

        $description = $merged['description'] ?? null;

        if (is_string($description) && mb_strlen($description) > 500) {
            throw new InvalidArgumentException('Açıklama en fazla 500 karakter olabilir.');
        }

        $sortOrder = $merged['sort_order'] ?? 0;

        if (!is_numeric($sortOrder)) {
            throw new InvalidArgumentException('Sıra sayı olmalıdır.');
        }
    }
}