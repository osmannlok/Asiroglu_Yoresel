<?php

/**
 * Aşıroğlu Yöresel Market - Kimlik doğrulama ve yetki kontrolü
 *
 * Oturum açma, kapatma ve geçerli kullanıcıyı verme işlerini yapar.
 *
 * Tasarım kararları:
 * - Oturumda yalnızca kullanıcı ID'si saklanır. Rol ve durum her istekte
 *   veritabanından okunur; böylece pasifleştirilen veya rolü değişen
 *   kullanıcının yetkisi hemen güncellenir.
 * - Giriş ve çıkışta session ID ve CSRF token yenilenir (session fixation).
 * - Yetki: panel erişimi 'admin' ve 'staff', tam yönetici yetkisi yalnızca
 *   'admin' (DECISIONS.md).
 * - Brute-force koruması (rate limiting) bu sınıfta yoktur; Request::ip()
 *   proxy düzeltmesinden sonra ayrı görevde eklenecektir.
 *
 * Gereksinimler: FR-090, NFR-003.
 */

declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use InvalidArgumentException;

final class Auth
{
    /** Kullanıcı ID'sinin oturumda saklandığı anahtar */
    private const SESSION_KEY = '_auth_user_id';

    /** Yönetim paneline girebilen kullanıcı türleri */
    private const ADMIN_PANEL_TYPES = ['admin', 'staff'];

    /** @var array<string, mixed>|null Bu istekte çözümlenen kullanıcı */
    private static ?array $user = null;

    /** Kullanıcı bu istekte çözümlendi mi? (null sonucu da önbelleğe alınır) */
    private static bool $resolved = false;

    private static ?User $users = null;

    /** Dışarıdan new Auth() yapılamaz; yalnızca statik metotlar kullanılır */
    private function __construct()
    {
    }

    // -------------------------------------------------------------------------
    // Giriş ve çıkış
    // -------------------------------------------------------------------------

    /**
     * E-posta ve şifreyi doğrular, doğruysa oturum açar.
     *
     * @return array<string, mixed>|null Başarılıysa kullanıcı (hash olmadan), değilse null
     */
    public static function attempt(string $email, string $password): ?array
    {
        $verified = self::users()->verifyCredentials($email, $password);

        if ($verified === null) {
            return null;
        }

        if (!self::login((int) $verified['id'])) {
            return null;
        }

        return self::user();
    }

    /**
     * Verilen ID'li aktif kullanıcı için oturum açar.
     * Kayıttan sonra otomatik giriş için de kullanılabilir.
     *
     * Kullanıcı bulunamazsa veya aktif değilse false döner.
     *
     * @throws InvalidArgumentException ID geçersizse
     */
    public static function login(int $userId): bool
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Geçersiz kullanıcı kimliği.');
        }

        $user = self::users()->findActiveById($userId);

        if ($user === null) {
            return false;
        }

        // Session fixation koruması: yetki değişirken ID ve token yenilenir
        Session::regenerate(true);
        Csrf::regenerate();

        Session::set(self::SESSION_KEY, $userId);

        self::$user     = $user;
        self::$resolved = true;

        self::users()->updateLastLogin($userId);

        return true;
    }

    /**
     * Oturumu kapatır. Session::destroy() kullanılmaz; böylece çıkıştan sonra
     * flash mesajı yazılabilir. ID ve CSRF token yenilenir.
     */
    public static function logout(): void
    {
        Session::forget(self::SESSION_KEY);

        self::$user     = null;
        self::$resolved = true;

        Session::regenerate(true);
        Csrf::regenerate();
    }

    // -------------------------------------------------------------------------
    // Geçerli kullanıcı
    // -------------------------------------------------------------------------

    /**
     * Oturumdaki kullanıcıyı döndürür (password_hash ve remember_token olmadan).
     * Kullanıcı artık aktif değilse veya silinmişse oturumdaki kayıt da temizlenir.
     *
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }

        self::$resolved = true;

        $id = Session::get(self::SESSION_KEY);

        if (!is_int($id) || $id <= 0) {
            return self::$user = null;
        }

        $user = self::users()->findActiveById($id);

        if ($user === null) {
            // Kullanıcı pasifleştirilmiş, banlanmış veya silinmiş: oturumu düşür
            Session::forget(self::SESSION_KEY);
        }

        return self::$user = $user;
    }

    /** Geçerli kullanıcının ID'si; giriş yapılmamışsa null. */
    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    /** Kullanıcı giriş yapmış mı? */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Kullanıcı giriş yapmamış mı? */
    public static function guest(): bool
    {
        return self::user() === null;
    }

    // -------------------------------------------------------------------------
    // Yetki kontrolü
    // -------------------------------------------------------------------------

    /** Kullanıcı tam yönetici mi? (user_type = 'admin') */
    public static function isAdmin(): bool
    {
        return self::userType() === 'admin';
    }

    /** Kullanıcı yönetim paneline girebilir mi? (user_type 'admin' veya 'staff') */
    public static function canAccessAdmin(): bool
    {
        $type = self::userType();

        return $type !== null && in_array($type, self::ADMIN_PANEL_TYPES, true);
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /** Geçerli kullanıcının türü; giriş yapılmamışsa null. */
    private static function userType(): ?string
    {
        $user = self::user();

        return $user === null ? null : (string) $user['user_type'];
    }

    /** User modelini tembel olarak oluşturur (istek başına tek örnek). */
    private static function users(): User
    {
        return self::$users ??= new User();
    }
}