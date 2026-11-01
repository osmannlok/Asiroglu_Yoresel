<?php

/**
 * Aşıroğlu Yöresel Market - Müşteri kayıt, giriş ve çıkış controller'ı
 *
 * GET  /giris  -> giriş formu
 * POST /giris  -> giriş denemesi
 * GET  /kayit  -> kayıt formu
 * POST /kayit  -> kayıt
 * POST /cikis  -> çıkış
 *
 * Kararlar:
 * - Kayıt olan kullanıcı her zaman 'customer' olur; user_type formdan alınmaz.
 * - Şifre giriş formunda Validator'dan geçmez (boşluk anlamlıdır); kayıtta
 *   'password' kuralıyla doğrulanır. Şifre hiçbir zaman flash'a yazılmaz.
 * - Başarısız girişte mesaj hesap bilgisi sızdırmaz (yanlış e-posta ve yanlış
 *   şifre aynı mesajı verir).
 * - Giriş ve kayıttan sonra Auth::login() session ID ve CSRF token'ı yeniler;
 *   ardından misafir (session) sepeti üye sepetiyle birleştirilir (FR-041).
 *   Birleştirme hatası girişi engellemez; hata loglanır, session sepeti korunur.
 * - Giriş sonrası dönülecek adres ("next") yalnızca site içi güvenli yollarla
 *   sınırlıdır (açık yönlendirme koruması).
 * - Eşzamanlı kayıtta e-posta UNIQUE ihlali (MySQL 1062) alan hatasına çevrilir.
 * - Çıkış yalnızca POST ile yapılır; CSRF doğrulaması App::handle() içindedir.
 * - E-posta doğrulaması (FR-051) ve brute-force koruması (rate limiting)
 *   henüz yoktur; Request::ip() proxy düzeltmesinden sonra ayrı görevde
 *   eklenecektir (DECISIONS.md, Bilinen Teknik Borçlar).
 *
 * Gereksinimler: FR-041, FR-050, NFR-002, NFR-003, NFR-004.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\User;
use App\Services\CartService;
use PDOException;
use RuntimeException;
use Throwable;

final class AuthController extends Controller
{
    /** Giriş formunun yolu */
    private const LOGIN_PATH = '/giris';

    /** Kayıt formunun yolu */
    private const REGISTER_PATH = '/kayit';

    /** Giriş/kayıt sonrası varsayılan hedef */
    private const DEFAULT_REDIRECT = '/';

    /** MySQL "yinelenen kayıt" hata kodu (UNIQUE ihlali) */
    private const MYSQL_DUPLICATE_ENTRY = 1062;

    /** Kabul edilen en uzun giriş şifresi (bayt); aşırı uzun girdiyle yük bindirmeyi önler */
    private const MAX_PASSWORD_BYTES = 255;

    /** "next" değerinin en fazla uzunluğu */
    private const MAX_NEXT_LENGTH = 200;

    /** Dönüş adresi olarak kabul edilmeyen yollar (döngü oluşturur) */
    private const NEXT_BLOCKED = ['/giris', '/kayit', '/cikis'];

    /** Kullanıcıya gösterilen ortak hata mesajı (hesap bilgisi sızdırmaz) */
    private const GENERIC_ERROR = 'E-posta veya şifre hatalı.';

    /** Kayıt formundan okunan metin alanları (şifre alanları dahil değildir) */
    private const REGISTER_FIELDS = ['first_name', 'last_name', 'email', 'phone'];

    // -------------------------------------------------------------------------
    // Giriş
    // -------------------------------------------------------------------------

    /**
     * Giriş formunu gösterir: GET /giris
     *
     * @param array<string, string> $params
     */
    public function showLogin(Request $request, array $params = []): Response
    {
        if (Auth::check()) {
            return $this->redirect(self::DEFAULT_REDIRECT);
        }

        $flashedNext = $this->flashString('old_next');
        $next        = $flashedNext !== ''
            ? $flashedNext
            : $this->resolveNext($request->query('next'));

        return $this->view('auth/login', [
            'title'        => 'Giriş Yap',
            'oldEmail'     => $this->flashString('old_email'),
            'next'         => $next,
            'registerPath' => self::REGISTER_PATH,
            'loginPath'    => self::LOGIN_PATH,
        ]);
    }

    /**
     * Giriş denemesini işler: POST /giris
     *
     * @param array<string, string> $params
     */
    public function login(Request $request, array $params = []): Response
    {
        if (Auth::check()) {
            return $this->redirect(self::DEFAULT_REDIRECT);
        }

        $rawEmail = $request->input('email');
        $email    = is_string($rawEmail) ? trim($rawEmail) : '';
        $next     = $this->resolveNext($request->input('next'));

        $validator = Validator::make(
            ['email' => $email],
            ['email' => 'required|email|max:191'],
            ['email' => 'E-posta']
        );

        if ($validator->fails()) {
            return $this->loginFailed((string) $validator->firstError(), $email, $next);
        }

        // Şifre Validator'dan geçmez: baştaki/sondaki boşluk anlamlıdır
        $password = $request->input('password');

        if (!is_string($password) || $password === '') {
            return $this->loginFailed('Şifre alanı zorunludur.', $email, $next);
        }

        if (strlen($password) > self::MAX_PASSWORD_BYTES) {
            return $this->loginFailed(self::GENERIC_ERROR, $email, $next);
        }

        $user = (new User())->verifyCredentials($email, $password);

        // Pasif/banlı hesap veya yanlış bilgi: mesaj aynıdır
        if ($user === null || !Auth::login((int) $user['id'])) {
            return $this->loginFailed(self::GENERIC_ERROR, $email, $next);
        }

        $this->mergeGuestCart((int) $user['id']);

        Session::flash('success', 'Giriş yaptınız.');

        return $this->redirect($next !== '' ? $next : self::DEFAULT_REDIRECT);
    }

    // -------------------------------------------------------------------------
    // Kayıt
    // -------------------------------------------------------------------------

    /**
     * Kayıt formunu gösterir: GET /kayit
     *
     * @param array<string, string> $params
     */
    public function showRegister(Request $request, array $params = []): Response
    {
        if (Auth::check()) {
            return $this->redirect(self::DEFAULT_REDIRECT);
        }

        $values = array_fill_keys(self::REGISTER_FIELDS, '');

        // Başarısız denemeden dönülüyorsa kullanıcının girdiği değerler korunur
        $old = Session::getFlash('old');

        if (is_array($old)) {
            foreach (self::REGISTER_FIELDS as $field) {
                if (isset($old[$field]) && is_string($old[$field])) {
                    $values[$field] = $old[$field];
                }
            }
        }

        $errors = Session::getFlash('form_errors');

        return $this->view('auth/register', [
            'title'        => 'Kayıt Ol',
            'values'       => $values,
            'errors'       => is_array($errors) ? $errors : [],
            'formAction'   => self::REGISTER_PATH,
            'loginPath'    => self::LOGIN_PATH,
        ]);
    }

    /**
     * Kayıt işlemini yapar: POST /kayit
     *
     * @param array<string, string> $params
     */
    public function register(Request $request, array $params = []): Response
    {
        if (Auth::check()) {
            return $this->redirect(self::DEFAULT_REDIRECT);
        }

        $input = $this->readRegisterInput($request);

        // Şifre alanları yalnızca doğrulamaya girer; flash'a yazılmaz
        $password     = $request->input('password');
        $confirmation = $request->input('password_confirmation');

        $validator = Validator::make(
            $input + [
                'password'              => is_string($password) ? $password : '',
                'password_confirmation' => is_string($confirmation) ? $confirmation : '',
            ],
            [
                'first_name' => 'required|string|max:100',
                'last_name'  => 'required|string|max:100',
                'email'      => 'required|email|max:191',
                'phone'      => 'nullable|phone',
                'password'   => 'required|password|confirmed',
            ],
            [
                'first_name' => 'Ad',
                'last_name'  => 'Soyad',
                'email'      => 'E-posta',
                'phone'      => 'Telefon',
                'password'   => 'Şifre',
            ]
        );

        $errors = $validator->errors();
        $data   = $validator->validated();
        $users  = new User();

        if (!isset($errors['email']) && $users->emailExists((string) $data['email'])) {
            $errors['email'] = 'Bu e-posta adresi zaten kayıtlı. Giriş yapmayı deneyin.';
        }

        if ($errors !== []) {
            return $this->registerFailed($input, $errors);
        }

        $row = [
            'first_name' => (string) $data['first_name'],
            'last_name'  => (string) $data['last_name'],
            'email'      => (string) $data['email'],
            'phone'      => $this->normalizePhone($data['phone'] ?? null),
        ];

        try {
            // user_type verilmez: createUser() varsayılanı 'customer'dır
            $userId = $users->createUser($row, (string) $password);
        } catch (RuntimeException $e) {
            // Eşzamanlı istekte e-posta çakışması: UNIQUE ihlali alan hatası olarak döner
            $previous = $e->getPrevious();

            if (
                $previous instanceof PDOException
                && (int) ($previous->errorInfo[1] ?? 0) === self::MYSQL_DUPLICATE_ENTRY
            ) {
                return $this->registerFailed(
                    $input,
                    ['email' => 'Bu e-posta adresi zaten kayıtlı. Giriş yapmayı deneyin.']
                );
            }

            throw $e;
        }

        if (!Auth::login($userId)) {
            // Beklenmeyen durum: hesap açıldı ama oturum açılamadı
            Session::flash('info', 'Hesabınız oluşturuldu. Lütfen giriş yapın.');

            return $this->redirect(self::LOGIN_PATH);
        }

        $this->mergeGuestCart($userId);

        Session::flash('success', 'Hesabınız oluşturuldu, hoş geldiniz.');

        return $this->redirect(self::DEFAULT_REDIRECT);
    }

    // -------------------------------------------------------------------------
    // Çıkış
    // -------------------------------------------------------------------------

    /**
     * Oturumu kapatır: POST /cikis
     *
     * @param array<string, string> $params
     */
    public function logout(Request $request, array $params = []): Response
    {
        Auth::logout();

        Session::flash('success', 'Çıkış yaptınız.');

        return $this->redirect(self::DEFAULT_REDIRECT);
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Misafir sepetini üye sepetiyle birleştirir (FR-041). Hata girişi
     * engellemez; sığmayan kalemler kullanıcıya bilgi mesajı olarak gösterilir.
     */
    private function mergeGuestCart(int $userId): void
    {
        try {
            $warnings = (new CartService())->mergeGuestCart($userId);
        } catch (Throwable $e) {
            error_log(sprintf(
                '[%s] AuthController: sepet birleştirilemedi (kullanıcı %d): %s',
                date('Y-m-d H:i:s'),
                $userId,
                $e->getMessage()
            ));

            return;
        }

        if ($warnings !== []) {
            Session::flash('info', implode(' ', $warnings));
        }
    }

    /**
     * Başarısız girişte hata mesajını, girilen e-postayı ve dönüş adresini
     * flash'a yazıp giriş sayfasına yönlendirir. Şifre asla saklanmaz.
     */
    private function loginFailed(string $message, string $email, string $next): Response
    {
        Session::flash('error', $message);
        Session::flash('old_email', mb_substr($email, 0, 191));
        Session::flash('old_next', $next);

        return $this->redirect(self::LOGIN_PATH);
    }

    /**
     * Hata durumunda eski girdiyi ve hataları flash'a yazıp forma döner.
     *
     * @param array<string, string> $input
     * @param array<string, string> $errors
     */
    private function registerFailed(array $input, array $errors): Response
    {
        Session::flash('error', 'Lütfen formdaki hataları düzeltin.');
        Session::flash('form_errors', $errors);
        Session::flash('old', $input);

        return $this->redirect(self::REGISTER_PATH);
    }

    /**
     * Kayıt formunun metin alanlarını okur; metin olmayan değerler boş sayılır.
     * Şifre alanları bilinçli olarak dahil değildir.
     *
     * @return array<string, string>
     */
    private function readRegisterInput(Request $request): array
    {
        $input = [];

        foreach (self::REGISTER_FIELDS as $field) {
            $value         = $request->input($field);
            $input[$field] = is_string($value) ? $value : '';
        }

        return $input;
    }

    /**
     * Telefondaki boşluk, tire ve parantezleri temizler (users.phone VARCHAR(20)).
     * Boş değer null döner. Biçim Validator 'phone' kuralında doğrulanmıştır.
     */
    private function normalizePhone(mixed $phone): ?string
    {
        if (!is_string($phone) || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/[\s\-\(\)]/', '', $phone) ?? '';

        return $digits === '' ? null : $digits;
    }

    /**
     * Giriş sonrası dönülecek adresi doğrular. Yalnızca site içi, sabit karakter
     * kümesine uyan yollar kabul edilir; geçersiz değer boş metin döner
     * (varsayılan hedef kullanılır).
     */
    private function resolveNext(mixed $raw): string
    {
        if (!is_string($raw)) {
            return '';
        }

        $next = trim($raw);

        if ($next === '' || strlen($next) > self::MAX_NEXT_LENGTH) {
            return '';
        }

        if (
            preg_match('#^/[A-Za-z0-9_\-/.]*(\?[A-Za-z0-9_\-=&%.]*)?$#', $next) !== 1
            || str_contains($next, '//')
            || str_contains($next, '..')
        ) {
            return '';
        }

        $path = explode('?', $next, 2)[0];

        if (in_array(rtrim($path, '/'), self::NEXT_BLOCKED, true)) {
            return '';
        }

        return $next;
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