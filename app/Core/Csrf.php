<?php

/**
 * Aşıroğlu Yöresel Market - CSRF koruması
 *
 * Oturum başına tahmin edilemez bir token üretir ve durum değiştiren
 * isteklerde (POST/PUT/PATCH/DELETE) bu token'ı doğrular.
 * Token, formlarda "_token" alanı ya da Fetch isteklerinde
 * "X-CSRF-TOKEN" başlığı ile gönderilir.
 */

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    /** Token'ın oturumda saklandığı anahtar */
    private const SESSION_KEY = '_csrf_token';

    /** Formlardaki gizli alan adı */
    public const FIELD_NAME = '_token';

    /** Fetch API isteklerindeki başlık adı */
    public const HEADER_NAME = 'X-CSRF-TOKEN';

    /** Dışarıdan new Csrf() yapılamaz; yalnızca statik metotlar kullanılır */
    private function __construct()
    {
    }

    /**
     * Geçerli oturumun token'ını döndürür; yoksa oluşturur.
     * Yalnızca sayfa üretirken (form, meta etiketi) çağrılmalıdır.
     */
    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    /**
     * Formlara eklenecek gizli token alanını döndürür.
     * Değer HTML için kaçırılır.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">';
    }

    /**
     * İstekteki token'ı oturumdaki token ile karşılaştırır.
     * Oturumda token yoksa veya istekte gönderilmemişse false döner.
     */
    public static function validate(Request $request): bool
    {
        $sessionToken = Session::get(self::SESSION_KEY);

        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        // Önce form/JSON alanına, yoksa başlığa bakılır
        $requestToken = $request->input(self::FIELD_NAME);

        if (!is_string($requestToken) || $requestToken === '') {
            $requestToken = $request->header(self::HEADER_NAME);
        }

        if (!is_string($requestToken) || $requestToken === '') {
            return false;
        }

        // Zamanlama saldırılarına karşı sabit sürede karşılaştırma
        return hash_equals($sessionToken, $requestToken);
    }

    /**
     * Token'ı yeniler. Giriş/çıkış gibi yetki değişimlerinde
     * Session::regenerate() ile birlikte çağrılmalıdır.
     */
    public static function regenerate(): void
    {
        Session::set(self::SESSION_KEY, bin2hex(random_bytes(32)));
    }
}