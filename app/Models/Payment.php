<?php

/**
 * Aşıroğlu Yöresel Market - Ödeme modeli
 *
 * payments tablosu (migration 013) için veri erişimi sağlar.
 *
 * Kurallar (DECISIONS.md, Ödeme / Kargo; migration 013):
 * - Kart numarası, CVV ve son kullanma tarihi ASLA saklanmaz; bu sınıfta
 *   bunlar için alan veya parametre yoktur. Yalnızca sağlayıcının döndürdüğü
 *   maskeli kart bilgisi (card_mask) kabul edilir ve biçimi denetlenir.
 * - payments.order_id UNIQUE değildir: bir sipariş için birden fazla ödeme
 *   denemesi olabilir.
 * - Ödeme tutarı çağıranın verdiği değerden değil, siparişin grand_total
 *   değerinden alınır (TRY, KDV dahil).
 * - Durum geçişleri: pending -> completed | failed | cancelled. Sonuçlanmış
 *   ödeme tekrar değiştirilmez; tekrar eden callback işlem yapmaz (false döner).
 * - Başarılı ödemede payments.status = completed ve orders.status = paid;
 *   başarısızda failed ve payment_failed. Ödeme ve sipariş güncellemesi AYNI
 *   transaction içinde yapılır; ödeme satırı FOR UPDATE ile kilitlenir.
 * - İade durumları (refunded, partially_refunded) iade modülünde eklenecektir.
 * - create(), update() ve delete() kapalıdır: durum tutarlılığı yalnızca
 *   createPending(), markCompleted(), markFailed() ve markCancelled() ile korunur.
 *
 * Tüm sorgular PDO prepared statement ile çalışır; $_GET / $_POST kullanılmaz.
 *
 * Gereksinimler: FR-071, FR-073, FR-075, FR-076, FR-077, NFR-009.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use BadMethodCallException;
use InvalidArgumentException;

final class Payment extends Model
{
    /** Geçerli ödeme yöntemleri (payments.payment_method ENUM) */
    public const METHODS = ['credit_card', 'bank_transfer', 'cash_on_delivery', 'wallet'];

    /** Kart yöntemlerinde taksit üst sınırı */
    private const MAX_INSTALLMENTS = 12;

    /** Kabul edilen maskeli kart biçimi: rakam, yıldız, boşluk, tire (en fazla 30) */
    private const CARD_MASK_PATTERN = '/^[0-9*\s\-]{1,30}$/';

    protected string $table = 'payments';

    /** payments tablosunda deleted_at yoktur */
    protected bool $softDelete = false;

    /**
     * Yalnızca bu sınıfın metotları parent::create() ile yazar.
     * Kart verisi için sütun yoktur.
     *
     * @var list<string>
     */
    protected array $fillable = [
        'order_id',
        'payment_method',
        'payment_provider',
        'provider_transaction_id',
        'provider_reference',
        'status',
        'amount',
        'currency',
        'installment_count',
        'card_mask',
        'error_message',
        'paid_at',
    ];

    // -------------------------------------------------------------------------
    // Sorgular
    // -------------------------------------------------------------------------

    /**
     * Siparişin ödeme denemelerini eskiden yeniye döndürür.
     *
     * @return list<array<string, mixed>>
     */
    public function getByOrder(int $orderId): array
    {
        return $this->fetchAll(
            'SELECT * FROM payments WHERE order_id = ? ORDER BY id ASC',
            [$orderId]
        );
    }

    /**
     * Siparişin son ödeme denemesini döndürür; yoksa null.
     *
     * @return array<string, mixed>|null
     */
    public function findLatestByOrder(int $orderId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1',
            [$orderId]
        );
    }

    /**
     * Sağlayıcı işlem numarasına göre ödemeyi getirir (callback eşleştirme).
     *
     * @return array<string, mixed>|null
     */
    public function findByProviderTransaction(string $provider, string $transactionId): ?array
    {
        $provider      = trim($provider);
        $transactionId = trim($transactionId);

        if ($provider === '' || $transactionId === '') {
            return null;
        }

        return $this->fetchOne(
            'SELECT * FROM payments WHERE payment_provider = ? AND provider_transaction_id = ?'
            . ' ORDER BY id DESC LIMIT 1',
            [$provider, $transactionId]
        );
    }

    // -------------------------------------------------------------------------
    // Ödeme oluşturma
    // -------------------------------------------------------------------------

    /**
     * Sipariş için bekleyen (pending) ödeme kaydı açar ve ID'sini döndürür.
     * Tutar siparişin grand_total değerinden alınır.
     *
     * @param string|null $provider Sağlayıcı adı ('paytr', 'iyzico'); havale ve kapıda ödemede null
     *
     * @throws InvalidArgumentException Sipariş yoksa, yöntem veya taksit geçersizse
     */
    public function createPending(
        int $orderId,
        string $method,
        ?string $provider = null,
        int $installmentCount = 1
    ): int {
        if (!in_array($method, self::METHODS, true)) {
            throw new InvalidArgumentException('Geçersiz ödeme yöntemi.');
        }

        if ($installmentCount < 1 || $installmentCount > self::MAX_INSTALLMENTS) {
            throw new InvalidArgumentException('Geçersiz taksit sayısı.');
        }

        if ($method !== 'credit_card' && $installmentCount !== 1) {
            throw new InvalidArgumentException('Taksit yalnızca kart ödemesinde kullanılabilir.');
        }

        $cleanProvider = $provider === null ? '' : trim($provider);

        if ($cleanProvider !== '' && preg_match('/^[a-z0-9_]{1,50}$/', $cleanProvider) !== 1) {
            throw new InvalidArgumentException('Geçersiz ödeme sağlayıcısı.');
        }

        $order = $this->fetchOne(
            'SELECT id, grand_total, currency FROM orders WHERE id = ? AND deleted_at IS NULL',
            [$orderId]
        );

        if ($order === null) {
            throw new InvalidArgumentException('Sipariş bulunamadı.');
        }

        if ((float) $order['grand_total'] <= 0) {
            throw new InvalidArgumentException('Ödenecek tutar sıfırdan büyük olmalıdır.');
        }

        return parent::create([
            'order_id'          => $orderId,
            'payment_method'    => $method,
            'payment_provider'  => $cleanProvider === '' ? null : $cleanProvider,
            'status'            => 'pending',
            'amount'            => number_format((float) $order['grand_total'], 2, '.', ''),
            'currency'          => (string) $order['currency'],
            'installment_count' => $installmentCount,
        ]);
    }

    // -------------------------------------------------------------------------
    // Sonuçlandırma
    // -------------------------------------------------------------------------

    /**
     * Ödemeyi başarılı olarak sonuçlandırır ve siparişi 'paid' yapar.
     * Ödeme ve sipariş güncellemesi tek transaction içindedir.
     *
     * Havale/EFT'de yönetici onayı da bu metotla yapılır (FR-073); kart
     * bilgisi ve sağlayıcı alanları o durumda null verilir.
     *
     * @param float|null $paidAmount Sağlayıcının bildirdiği tutar; verilirse kayıtlı tutarla eşleşmelidir
     * @param int|null   $changedBy  İşlemi yapan yönetici; callback'te null
     *
     * @return bool Durum değiştiyse true; ödeme zaten sonuçlanmışsa (tekrarlanan callback) false
     *
     * @throws InvalidArgumentException Ödeme yoksa, tutar uyuşmuyorsa veya kart maskesi geçersizse
     */
    public function markCompleted(
        int $paymentId,
        ?string $providerTransactionId = null,
        ?string $providerReference = null,
        ?string $cardMask = null,
        ?float $paidAmount = null,
        ?int $changedBy = null
    ): bool {
        $mask = $this->cleanCardMask($cardMask);

        return (bool) $this->transaction(
            function () use (
                $paymentId,
                $providerTransactionId,
                $providerReference,
                $mask,
                $paidAmount,
                $changedBy
            ): bool {
                $payment = $this->lockPayment($paymentId);

                // Tekrarlanan callback: sonuçlanmış ödeme değişmez
                if ((string) $payment['status'] !== 'pending') {
                    return false;
                }

                if ($paidAmount !== null && round($paidAmount, 2) !== round((float) $payment['amount'], 2)) {
                    throw new InvalidArgumentException('Ödenen tutar sipariş tutarıyla uyuşmuyor.');
                }

                $this->execute(
                    'UPDATE payments SET status = ?, provider_transaction_id = COALESCE(?, provider_transaction_id),'
                    . ' provider_reference = COALESCE(?, provider_reference),'
                    . ' card_mask = COALESCE(?, card_mask), error_message = NULL, paid_at = NOW()'
                    . ' WHERE id = ?',
                    [
                        'completed',
                        $this->shorten($providerTransactionId, 191),
                        $this->shorten($providerReference, 191),
                        $mask,
                        $paymentId,
                    ]
                );

                // Geçersiz geçişte (örn. iptal edilmiş sipariş) exception fırlar; her şey geri alınır
                (new Order())->changeStatus(
                    (int) $payment['order_id'],
                    'paid',
                    $changedBy,
                    'Ödeme alındı.'
                );

                return true;
            }
        );
    }

    /**
     * Ödemeyi başarısız olarak sonuçlandırır. Sipariş 'awaiting_payment'
     * durumundaysa 'payment_failed' yapılır; diğer durumlarda sipariş durumu
     * değişmez (örn. sipariş zaten iptal edilmişse).
     *
     * @param string|null $errorMessage Kullanıcıya gösterilebilir kısa özet; teknik ayrıntı payment_logs'ta tutulur
     *
     * @return bool Durum değiştiyse true; ödeme zaten sonuçlanmışsa false
     */
    public function markFailed(int $paymentId, ?string $errorMessage = null, ?int $changedBy = null): bool
    {
        return (bool) $this->transaction(
            function () use ($paymentId, $errorMessage, $changedBy): bool {
                $payment = $this->lockPayment($paymentId);

                if ((string) $payment['status'] !== 'pending') {
                    return false;
                }

                $this->execute(
                    'UPDATE payments SET status = ?, error_message = ? WHERE id = ?',
                    ['failed', $this->shorten($errorMessage, 500), $paymentId]
                );

                $order = $this->fetchOne(
                    'SELECT status FROM orders WHERE id = ? AND deleted_at IS NULL',
                    [(int) $payment['order_id']]
                );

                if ($order !== null && (string) $order['status'] === 'awaiting_payment') {
                    (new Order())->changeStatus(
                        (int) $payment['order_id'],
                        'payment_failed',
                        $changedBy,
                        'Ödeme başarısız.'
                    );
                }

                return true;
            }
        );
    }

    /**
     * Bekleyen ödemeyi iptal eder (örn. müşteri 3D Secure sayfasından vazgeçti).
     * Sipariş durumu değişmez; müşteri yeniden ödeme deneyebilir.
     *
     * @return bool Durum değiştiyse true; ödeme zaten sonuçlanmışsa false
     */
    public function markCancelled(int $paymentId): bool
    {
        return (bool) $this->transaction(
            function () use ($paymentId): bool {
                $payment = $this->lockPayment($paymentId);

                if ((string) $payment['status'] !== 'pending') {
                    return false;
                }

                $this->execute('UPDATE payments SET status = ? WHERE id = ?', ['cancelled', $paymentId]);

                return true;
            }
        );
    }

    // -------------------------------------------------------------------------
    // Yazma yasakları
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $data
     *
     * @throws BadMethodCallException Her zaman
     */
    public function create(array $data): int
    {
        throw new BadMethodCallException('Payment::create() kapalıdır. createPending() kullanın.');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws BadMethodCallException Her zaman
     */
    public function update(int $id, array $data): bool
    {
        throw new BadMethodCallException(
            'Payment::update() kapalıdır. markCompleted(), markFailed() veya markCancelled() kullanın.'
        );
    }

    /**
     * @throws BadMethodCallException Her zaman
     */
    public function delete(int $id): bool
    {
        throw new BadMethodCallException('Ödeme kayıtları silinemez.');
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Ödeme satırını kilitleyip döndürür (transaction içinde çağrılmalıdır).
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException Ödeme bulunamazsa
     */
    private function lockPayment(int $paymentId): array
    {
        $payment = $this->fetchOne(
            'SELECT * FROM payments WHERE id = ? FOR UPDATE',
            [$paymentId]
        );

        if ($payment === null) {
            throw new InvalidArgumentException('Ödeme kaydı bulunamadı.');
        }

        return $payment;
    }

    /**
     * Maskeli kart bilgisini doğrular. Yalnızca rakam, yıldız, boşluk ve tire
     * kabul edilir; 13 veya daha fazla rakam içeren değer (tam kart numarası
     * olabilir) REDDEDİLİR. Boş değer null döner.
     *
     * @throws InvalidArgumentException Biçim geçersizse veya maske yetersizse
     */
    private function cleanCardMask(?string $mask): ?string
    {
        if ($mask === null || trim($mask) === '') {
            return null;
        }

        $mask = trim($mask);

        if (preg_match(self::CARD_MASK_PATTERN, $mask) !== 1) {
            throw new InvalidArgumentException('Geçersiz kart maskesi.');
        }

        $digits = preg_replace('/\D/', '', $mask) ?? '';

        if (strlen($digits) > 10) {
            throw new InvalidArgumentException('Kart bilgisi yeterince maskelenmemiş.');
        }

        return $mask;
    }

    /** Metni en fazla $max karaktere kısaltır; boşsa null döner. */
    private function shorten(?string $value, int $max): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, $max);
    }
}