<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli erişim denetimi
 *
 * /admin altındaki route'lara girmeden önce kullanıcının yetkisini denetler.
 *
 * Sözleşme: handle() null döndürürse istek devam eder; Response döndürürse
 * istek orada sonlanır ve o yanıt gönderilir.
 *
 * - Giriş yapmamış ziyaretçi: Fetch/AJAX ise 401 JSON, değilse giriş
 *   sayfasına yönlendirilir (istenen sayfa, yalnızca GET isteklerinde,
 *   giriş sonrası dönmek için oturumda saklanır).
 * - Giriş yapmış ama panele yetkisi olmayan kullanıcı (müşteri): 403.
 * - Panel erişimi 'admin' ve 'staff' kullanıcı türlerine açıktır.
 *   Yalnızca 'admin'e açık işlemler ayrıca denetlenmelidir (Auth::isAdmin()).
 *
 * Gereksinimler: FR-090, G-04.
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AdminMiddleware
{
    /** Yönetim paneli giriş sayfasının yolu (sabit; kullanıcı girdisi değildir) */
    public const LOGIN_PATH = '/admin/giris';

    /** Giriş sonrası dönülecek sayfanın oturumda saklandığı anahtar */
    public const INTENDED_KEY = '_intended_url';

    /**
     * İsteği denetler.
     *
     * @return Response|null null = devam et, Response = isteği bu yanıtla bitir
     */
    public function handle(Request $request): ?Response
    {
        if (Auth::guest()) {
            return $this->unauthenticated($request);
        }

        if (!Auth::canAccessAdmin()) {
            return $this->forbidden($request);
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Yanıtlar
    // -------------------------------------------------------------------------

    /**
     * Giriş yapmamış ziyaretçi için yanıt üretir.
     */
    private function unauthenticated(Request $request): Response
    {
        if ($request->expectsJson()) {
            return Response::json(
                ['success' => false, 'message' => 'Bu işlem için giriş yapmanız gerekiyor.'],
                401
            );
        }

        // Yalnızca GET isteklerinde istenen sayfa hatırlanır; POST'a geri dönülemez.
        // Giriş sayfasının kendisi kaydedilmez.
        if ($request->method() === 'GET' && $request->path() !== self::LOGIN_PATH) {
            Session::set(self::INTENDED_KEY, $request->path());
        }

        Session::flash('error', 'Yönetim paneline erişmek için giriş yapmalısınız.');

        $base = rtrim((string) App::config('app.url', ''), '/');

        return Response::redirect($base . self::LOGIN_PATH, 302)
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Giriş yapmış ama yetkisi olmayan kullanıcı için 403 yanıtı üretir.
     * Yönlendirme yapılmaz; kullanıcı zaten girişlidir ve döngü oluşabilir.
     */
    private function forbidden(Request $request): Response
    {
        $message = 'Bu sayfaya erişim yetkiniz bulunmuyor.';

        if ($request->expectsJson()) {
            return Response::json(['success' => false, 'message' => $message], 403)
                ->header('Cache-Control', 'no-store');
        }

        $title = htmlspecialchars('Erişim engellendi', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text  = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $title . '</title>'
            . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">'
            . '</head><body class="bg-light">'
            . '<main class="container text-center py-5">'
            . '<h1 class="display-6 mb-3">' . $title . '</h1>'
            . '<p class="text-muted mb-4">' . $text . '</p>'
            . '<a href="/" class="btn btn-success">Ana Sayfaya Dön</a>'
            . '</main></body></html>';

        return Response::html($html, 403)->header('Cache-Control', 'no-store');
    }
}