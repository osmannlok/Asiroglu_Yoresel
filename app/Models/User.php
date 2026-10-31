<?php

/**
 * Aşıroğlu Yöresel Market - Kullanıcı modeli
 *
 * users tablosu (migration 001) için veri erişimi sağlar: müşteri, yönetici
 * ve personel hesapları. Soft delete kullanır (deleted_at).
 *
 * Güvenlik kararları:
 * - Kullanıcı yalnızca createUser() ile oluşturulur; create() kapalıdır.
 *   Böylece form verisiyle user_type veya password_hash yazılamaz.
 * - Şifreler password_hash() ile saklanır, password_verify() ile doğrulanır.
 * - find() ve all() çıktısından password_hash ve remember_token silinir.
 * - Girdi doğrulaması (Validator) controller katmanında yapılır; bu sınıfta
 *   $_GET / $_POST kullanılmaz.
 *
 * Gereksinimler: FR-050, NFR-002, FR-090.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use BadMethodCallException;
use InvalidArgumentException;

final class User extends Model
{
    /** Kullanıcı türü olarak atanabilecek değerler (users.user_type ENUM) */
    private const USER_TYPES = ['customer', 'admin', 'staff'];

    /** Kullanıcıdan ve görünümlerden gizlenen sütunlar */
    private const SECRET_COLUMNS = ['password_hash', 'remember_token'];

    /** Profil güncellemesinde izin verilen alanlar */
    private const PROFILE_FIELDS = ['first_name', 'last_name', 'phone'];

    protected string $table = 'users';

    /** Kullanıcılar fiziksel silinmez; deleted_at doldurulur */
    protected bool $softDelete = true;

    /**
     * Yazılabilen sütunlar. remember_token bilinçli olarak dışarıdadır;
     * "beni hatırla" özelliği eklenirken ayrı metotla yönetilecek.
     *
     * @var list<string>
     */
    protected array $fillable = [
        'user_type',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password_hash',
        'status',
        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
    ];

    // -------------------------------------------------------------------------
    // Kayıt ve oluşturma
    // -------------------------------------------------------------------------

    /**
     * Yeni kullanıcı oluşturur ve ID'sini döndürür.
     *
     * $data yalnızca first_name, last_name, email ve phone alanlarını taşır;
     * başka anahtarlar yok sayılır. Şifre düz metin olarak $plainPassword ile
     * verilir ve burada hash'lenir.
     *
     * DİKKAT: $userType form girdisinden ALINMAMALIDIR. Müşteri kaydında
     * varsayılan değer kullanılır; personel/yönetici ekleme kod içinden,
     * yetkili işlemde belirtilir.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException Şifre boşsa veya tür geçersizse
     */
    public function createUser(array $data, string $plainPassword, string $userType = 'customer'): int
    {
        if ($plainPassword === '') {
            throw new InvalidArgumentException('Şifre boş olamaz.');
        }

        if (!in_array($userType, self::USER_TYPES, true)) {
            throw new InvalidArgumentException('Geçersiz kullanıcı türü.');
        }

        $row = array_intersect_key($data, array_flip(['first_name', 'last_name', 'email', 'phone']));

        if (isset($row['email'])) {
            $row['email'] = $this->normalizeEmail((string) $row['email']);
        }

        $row['user_type']     = $userType;
        $row['status']        = 'active';
        $row['password_hash'] = password_hash($plainPassword, PASSWORD_DEFAULT);

        return parent::create($row);
    }

    /**
     * Doğrudan create() kullanımı kapalıdır; createUser() kullanılmalıdır.
     *
     * @param array<string, mixed> $data
     *
     * @throws BadMethodCallException Her zaman
     */
    public function create(array $data): int
    {
        throw new BadMethodCallException('User::create() kapalıdır. createUser() kullanın.');
    }

    // -------------------------------------------------------------------------
    // Giriş doğrulama
    // -------------------------------------------------------------------------

    /**
     * E-posta ve şifreyi doğrular. Başarılıysa kullanıcıyı (hash olmadan),
     * aksi halde null döndürür. Hesap pasif veya banlıysa giriş reddedilir.
     *
     * Kullanıcı bulunamasa da bir hash işlemi yapılır; böylece yanıt süresinden
     * e-postanın kayıtlı olup olmadığı anlaşılamaz.
     *
     * @return array<string, mixed>|null
     */
    public function verifyCredentials(string $email, string $password): ?array
    {
        $user = $this->fetchOne(
            'SELECT * FROM users WHERE email = ? AND deleted_at IS NULL',
            [$this->normalizeEmail($email)]
        );

        if ($user === null) {
            // Süre eşitleme: bulunan kullanıcıdaki password_verify ile benzer maliyet
            password_hash($password, PASSWORD_DEFAULT);

            return null;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return null;
        }

        if ($user['status'] !== 'active') {
            return null;
        }

        // Hash algoritması veya maliyeti güncellendiyse şifreyi yeniden hash'le
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $this->execute(
                'UPDATE users SET password_hash = ? WHERE id = ?',
                [password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]
            );
        }

        return $this->withoutSecrets($user);
    }

    // -------------------------------------------------------------------------
    // Sorgular
    // -------------------------------------------------------------------------

    /**
     * Aktif ve silinmemiş kullanıcıyı ID ile getirir (Auth her istekte kullanır).
     *
     * @return array<string, mixed>|null
     */
    public function findActiveById(int $id): ?array
    {
        $user = $this->fetchOne(
            "SELECT * FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL",
            [$id]
        );

        return $user === null ? null : $this->withoutSecrets($user);
    }

    /**
     * E-postaya göre silinmemiş kullanıcıyı getirir (hash olmadan).
     *
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $user = $this->fetchOne(
            'SELECT * FROM users WHERE email = ? AND deleted_at IS NULL',
            [$this->normalizeEmail($email)]
        );

        return $user === null ? null : $this->withoutSecrets($user);
    }

    /**
     * E-posta başka bir kullanıcıda kayıtlı mı?
     *
     * DİKKAT: email sütunundaki UNIQUE indeks soft delete ile silinmiş
     * kayıtları da kapsar; bu yüzden silinmiş hesaplar da kontrole dahildir.
     * $exceptId, düzenlenen kullanıcının kendisini hariç tutar.
     */
    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM users WHERE email = ?';
        $params = [$this->normalizeEmail($email)];

        if ($exceptId !== null) {
            $sql     .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return (int) $this->fetchValue($sql, $params) > 0;
    }

    /**
     * Birincil anahtara göre kullanıcıyı getirir (hash ve token olmadan).
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $user = parent::find($id);

        return $user === null ? null : $this->withoutSecrets($user);
    }

    /**
     * Tüm kullanıcıları getirir (hash ve token olmadan). Büyük tablolarda limit kullanın.
     *
     * @return list<array<string, mixed>>
     */
    public function all(string $orderBy = 'id', string $direction = 'ASC', ?int $limit = null): array
    {
        return array_map(
            fn (array $user): array => $this->withoutSecrets($user),
            parent::all($orderBy, $direction, $limit)
        );
    }

    // -------------------------------------------------------------------------
    // Güncelleme
    // -------------------------------------------------------------------------

    /**
     * Kullanıcıyı günceller. password_hash ve user_type bu yolla yazılamaz;
     * şifre için changePassword() kullanılır.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException Yasaklı alan verilirse
     */
    public function update(int $id, array $data): bool
    {
        foreach (['password_hash', 'user_type'] as $forbidden) {
            if (array_key_exists($forbidden, $data)) {
                throw new InvalidArgumentException(
                    $forbidden . ' alanı update() ile değiştirilemez.'
                );
            }
        }

        if (isset($data['email'])) {
            $data['email'] = $this->normalizeEmail((string) $data['email']);
        }

        return parent::update($id, $data);
    }

    /**
     * Profil bilgilerini günceller (yalnızca ad, soyad, telefon).
     *
     * @param array<string, mixed> $data
     */
    public function updateProfile(int $id, array $data): bool
    {
        return $this->update($id, array_intersect_key($data, array_flip(self::PROFILE_FIELDS)));
    }

    /**
     * Şifreyi değiştirir. Yeni şifre burada hash'lenir.
     *
     * @throws InvalidArgumentException Şifre boşsa
     */
    public function changePassword(int $id, string $newPlainPassword): bool
    {
        if ($newPlainPassword === '') {
            throw new InvalidArgumentException('Şifre boş olamaz.');
        }

        return $this->execute(
            'UPDATE users SET password_hash = ? WHERE id = ? AND deleted_at IS NULL',
            [password_hash($newPlainPassword, PASSWORD_DEFAULT), $id]
        ) > 0;
    }

    /**
     * Başarılı girişte son giriş zamanını günceller.
     */
    public function updateLastLogin(int $id): void
    {
        $this->execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * E-postayı karşılaştırma ve kayıt için tutarlı hale getirir.
     */
    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Kullanıcı satırından hash ve token alanlarını çıkarır.
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function withoutSecrets(array $user): array
    {
        foreach (self::SECRET_COLUMNS as $column) {
            unset($user[$column]);
        }

        return $user;
    }
}