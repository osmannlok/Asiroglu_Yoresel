<?php

/**
 * Aşıroğlu Yöresel Market - Uygulama çekirdeği
 *
 * Yapılandırmayı tutar, session'ı başlatır, route'ları kaydeder,
 * CSRF doğrulamasını yapar ve isteği Router'a yönlendirir.
 */

declare(strict_types=1);

namespace App\Core;

use App\Controllers\Admin\AuthController as AdminAuthController;
use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\HomeController;
use App\Middleware\AdminMiddleware;
use Throwable;

final class App
{
    /** @var array<string, mixed> Yüklenen yapılandırma dizisi */
    private static array $config = [];

    private Router $router;

    /**
     * @param array<string, mixed> $config config/config.php dosyasından gelen dizi
     */
    public function __construct(array $config)
    {
        self::$config = $config;
        $this->router = new Router();
    }

    /**
     * Yapılandırma değerini nokta notasyonu ile okur.
     * Örnek: App::config('app.debug', false)
     */
    public static function config(string $key, mixed $default = null): mixed
    {
        $value = self::$config;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Uygulamayı çalıştırır: istek alınır, işlenir, yanıt gönderilir.
     */
    public function run(): void
    {
        Session::start((array) self::config('session', []));

        $this->registerRoutes();

        $request = Request::capture();

        try {
            $response = $this->handle($request);
        } catch (Throwable $e) {
            // Geliştirme modunda hatayı index.php'deki global handler'a bırak
            if ((bool) self::config('app.debug', false)) {
                throw $e;
            }

            $this->logException($e);
            $response = $this->errorResponse($request, 500);
        }

        $response->send();
    }

    /**
     * Route'ları kaydeder.
     * Yeni modüller eklendikçe route'lar buraya eklenecektir.
     */
    private function registerRoutes(): void
    {
        // --- Vitrin ---------------------------------------------------------
        $this->router->get('/', [HomeController::class, 'index']);

        // --- Yönetim paneli: giriş ve çıkış ---------------------------------
        // Bu route'lar AdminMiddleware grubunun DIŞINDA kalmalıdır; aksi halde
        // giriş sayfası giriş yapmamış kullanıcıyı kendisine yönlendirir.
        $this->router->get(AdminMiddleware::LOGIN_PATH, [AdminAuthController::class, 'showLogin']);
        $this->router->post(AdminMiddleware::LOGIN_PATH, [AdminAuthController::class, 'login']);
        $this->router->post('/admin/cikis', [AdminAuthController::class, 'logout']);

        // --- Yönetim paneli: korumalı route'lar -----------------------------
        // Marka, ürün vb. yeni admin route'ları bu grubun İÇİNE eklenir.
        // Grup içindeki yollar "/admin" önekiyle birleşir ('/' => '/admin').
        $this->router->group(
            ['prefix' => '/admin', 'middleware' => [AdminMiddleware::class]],
            function (Router $router): void {
                $router->get('/', [DashboardController::class, 'index']);

                // Kategoriler (FR-030, FR-031, FR-033)
                $router->get('/kategoriler', [CategoryController::class, 'index']);
                $router->get('/kategoriler/olustur', [CategoryController::class, 'create']);
                $router->post('/kategoriler', [CategoryController::class, 'store']);
                $router->get('/kategoriler/{id:\d+}/duzenle', [CategoryController::class, 'edit']);
                $router->post('/kategoriler/{id:\d+}/guncelle', [CategoryController::class, 'update']);
                $router->post('/kategoriler/{id:\d+}/sil', [CategoryController::class, 'destroy']);
            }
        );
    }

    /**
     * İsteği işler: CSRF kontrolü, route eşleştirme ve yanıt üretimi.
     */
    private function handle(Request $request): Response
    {
        // Durum değiştiren isteklerde CSRF token zorunlu
        if ($this->requiresCsrf($request) && !Csrf::validate($request)) {
            return $this->errorResponse($request, 419);
        }

        $response = $this->router->dispatch($request);

        // Eşleşen route yoksa 404
        return $response ?? $this->errorResponse($request, 404);
    }

    /**
     * Bu isteğin CSRF doğrulamasına tabi olup olmadığını belirler.
     */
    private function requiresCsrf(Request $request): bool
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return false;
        }

        // İstisna listesi (örn. ödeme callback'leri; bunlar imza ile doğrulanacak)
        $exceptions = (array) self::config('csrf.except', []);
        $path = $request->path();

        foreach ($exceptions as $pattern) {
            $pattern = (string) $pattern;

            // "/odeme/callback/*" gibi sonu yıldızlı desenleri destekle
            if (str_ends_with($pattern, '*')) {
                if (str_starts_with($path, rtrim($pattern, '*'))) {
                    return false;
                }
            } elseif ($path === $pattern) {
                return false;
            }
        }

        return true;
    }

    /**
     * Hata durumları için güvenli yanıt üretir (teknik detay içermez).
     * İstek JSON bekliyorsa (Fetch API) JSON, aksi halde basit HTML döner.
     */
    private function errorResponse(Request $request, int $status): Response
    {
        $messages = [
            404 => ['Sayfa bulunamadı', 'Aradığınız sayfa mevcut değil veya taşınmış olabilir.'],
            419 => ['Oturum süresi doldu', 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.'],
            500 => ['Bir hata oluştu', 'İşleminiz şu anda gerçekleştirilemiyor. Lütfen daha sonra tekrar deneyin.'],
        ];

        [$title, $text] = $messages[$status] ?? $messages[500];

        if ($request->expectsJson()) {
            return Response::json(['success' => false, 'message' => $text], $status);
        }

        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $text  = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

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

        return Response::html($html, $status);
    }

    /**
     * Exception'ı storage/logs/php-error.log dosyasına yazar.
     */
    private function logException(Throwable $e): void
    {
        error_log(sprintf(
            "[%s] %s: %s in %s:%d\nStack trace:\n%s",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
    }
}