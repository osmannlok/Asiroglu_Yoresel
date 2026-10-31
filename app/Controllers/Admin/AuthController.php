<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli giriş/çıkış controller'ı
 *
 * GET  /admin/giris  -> giriş formu
 * POST /admin/giris  -> giriş denemesi
 * POST /admin/cikis  -> çıkış
 *
 * Güvenlik kararları:
 * - Yalnızca 'admin' ve 'staff' kullanıcıları panele giriş yapabilir. Müşteri
 *   hesabı doğru şifreyle bile oturum açamaz ve hata mesajı yanlış şifre ile
 *   aynıdır (hangi e-postanın yönetici olduğu anlaşılamaz).
 * - Şifre Validator'dan geçirilmez (boşluk anlamlıdır) ve flash'a yazılmaz.
 * - Giriş sonrası dönülecek adres yalnızca /admin altındaki güvenli yollarla
 *   sınırlıdır (açık yönlendirme koruması).
 * - Çıkış yalnızca POST ile yapılır; CSRF doğrulaması App::handle() içindedir.
 * - Brute-force koruması (rate limiting) henüz yoktur; Request::ip() proxy
 *   düzeltmesinden sonra ayrı görevde eklenecektir (DECISIONS.md).
 *
 * Gereksinimler: FR-090, G-04, NFR-003.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Middleware\AdminMiddleware;
use App\Models\User;

final class AuthController extends Controller
{
    /** Giriş sonrası varsayılan hedef */
    private const DEFAULT_REDIRECT = '/admin';

    /** Kabul edilen en uzun şifre (bayt); aşırı uzun girdiyle yük bindirmeyi önler */
    private const MAX_PASSWORD_BYTES = 255;

    /** Panele girebilen kullanıcı türleri */
    private const PANEL_TYPES = ['admin', 'staff'];

    /** Kullanıcıya gösterilen ortak hata mesajı (hesap bilgisi sızdırmaz) */
    private const GENERIC_ERROR = 'E-posta veya şifre hatalı.';

    /**
     * Giriş formunu gösterir: GET /admin/giris
     *
     * @param array<string, string> $params
     */
    public function showLogin(Request $request, array $params = []): Response
    {
        // Zaten yetkili kullanıcıyı panele gönder
        if (Auth::canAccessAdmin()) {
            return $this->redirect(self::DEFAULT_REDIRECT);
        }

        return $this->view(
            'admin/auth/login',
            [
                'title'          => 'Yönetim Paneli Girişi',
                'oldEmail'       => $this->flashString('old_email'),
                'errorMessage'   => $this->flashString('error'),
                'successMessage' => $this->flashString('success'),
                'infoMessage'    => $this->flashString('info'),
            ],
            null // Giriş sayfası kendi başına tam bir HTML sayfasıdır (layout yok)
        );
    }

    /**
     * Giriş denemesini işler: POST /admin/giris
     *
     * @param array<string, string> $params
     */
    public function login(Request $request, array $params = []): Response
    {
        $rawEmail = $request->input('email');
        $email    = is_string($rawEmail) ? trim($rawEmail) : '';

        $validator = Validator::make(
            ['email' => $email],
            ['email' => 'required|email|max:191'],
            ['email' => 'E-posta']
        );

        if ($validator->fails()) {
            return $this->loginFailed((string) $validator->firstError(), $email);
        }

        // Şifre Validator'dan geçmez: baştaki/sondaki boşluk anlamlıdır
        $password = $request->input('password');

        if (!is_string($password) || $password === '') {
            return $this->loginFailed('Şifre alanı zorunludur.', $email);
        }

        if (strlen($password) > self::MAX_PASSWORD_BYTES) {
            return $this->loginFailed(self::GENERIC_ERROR, $email);
        }

        $user = (new User())->verifyCredentials($email, $password);

        // Müşteri hesabı veya pasif/banlı kullanıcı panele giremez; mesaj aynıdır
        if (
            $user === null
            || !in_array((string) $user['user_type'], self::PANEL_TYPES, true)
            || !Auth::login((int) $user['id'])
        ) {
            return $this->loginFailed(self::GENERIC_ERROR, $email);
        }

        return $this->redirect($this->resolveIntendedPath());
    }

    /**
     * Oturumu kapatır: POST /admin/cikis
     *
     * @param array<string, string> $params
     */
    public function logout(Request $request, array $params = []): Response
    {
        Auth::logout();

        Session::flash('success', 'Çıkış yaptınız.');

        return $this->redirect(AdminMiddleware::LOGIN_PATH);
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Başarısız girişte hata mesajını ve girilen e-postayı flash'a yazıp
     * giriş sayfasına yönlendirir. Şifre asla saklanmaz.
     */
    private function loginFailed(string $message, string $email): Response
    {
        Session::flash('error', $message);
        Session::flash('old_email', mb_substr($email, 0, 191));

        return $this->redirect(AdminMiddleware::LOGIN_PATH);
    }

    /**
     * AdminMiddleware'in sakladığı hedef sayfayı okur ve oturumdan siler.
     * Yalnızca /admin altındaki güvenli yollar kabul edilir; giriş sayfasının
     * kendisi ve diğer her değer varsayılan hedefe düşer.
     */
    private function resolveIntendedPath(): string
    {
        $intended = Session::pull(AdminMiddleware::INTENDED_KEY);

        if (
            is_string($intended)
            && $intended !== AdminMiddleware::LOGIN_PATH
            && preg_match('#^/admin(/[A-Za-z0-9_\-/.]*)?$#', $intended) === 1
            && !str_contains($intended, '//')
            && !str_contains($intended, '..')
        ) {
            return $intended;
        }

        return self::DEFAULT_REDIRECT;
    }

    /**
     * Flash mesajını metin olarak okur; yoksa veya metin değilse boş döner.
     */
    private function flashString(string $key): string
    {
        $value = Session::getFlash($key, '');

        return is_string($value) ? $value : '';
    }
}