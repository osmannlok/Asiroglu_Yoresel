<?php

/**
 * Aşıroğlu Yöresel Market - Temel Controller
 *
 * Tüm controller'lar bu sınıftan türetilir. View render etme, JSON yanıtı,
 * yönlendirme ve view içinde kullanılan güvenlik yardımcılarını sağlar.
 */

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

abstract class Controller
{
    /**
     * Bir view dosyasını render eder ve layout içine yerleştirir.
     *
     * @param string               $view   "home/index" gibi, app/Views altındaki yol (.php yazılmaz)
     * @param array<string, mixed> $data   View'a aktarılacak değişkenler
     * @param string|null          $layout Layout yolu; null ise layout kullanılmaz (parçalı çıktı için)
     * @param int                  $status HTTP durum kodu
     */
    protected function view(
        string $view,
        array $data = [],
        ?string $layout = 'layouts/main',
        int $status = 200
    ): Response {
        $content = $this->render($view, $data);

        if ($layout !== null) {
            // Layout, view çıktısını $content değişkeni ile alır
            $content = $this->render($layout, $data + ['content' => $content]);
        }

        return Response::html($content, $status);
    }

    /**
     * JSON yanıtı üretir (Fetch API uç noktaları için).
     *
     * @param array<string, mixed> $data
     */
    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /**
     * Yönlendirme yanıtı üretir. Yalnızca site içi yollara izin verilir.
     */
    protected function redirect(string $url, int $status = 302): Response
    {
        // "/" ile başlamalı; "//site.com" ve "/\" biçimleri harici adrese gider, reddedilir
        $isLocalPath = str_starts_with($url, '/')
            && !str_starts_with($url, '//')
            && !str_starts_with($url, '/\\')
            && !preg_match('/[\r\n]/', $url);

        if (!$isLocalPath) {
            throw new InvalidArgumentException('Güvenli olmayan yönlendirme adresi.');
        }

        return Response::redirect($this->url($url), $status);
    }

    /**
     * XSS koruması: değeri HTML için güvenli hale getirir.
     * View içinde kullanım: <?= $this->e($urun['name']) ?>
     */
    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Formlar için gizli CSRF token alanını döndürür.
     * View içinde kullanım: <?= $this->csrfField() ?>
     */
    public function csrfField(): string
    {
        return Csrf::field();
    }

    /**
     * Site içi adres üretir. app.url ayarı varsa başına eklenir.
     * Örnek: $this->url('/sepet')
     */
    public function url(string $path = '/'): string
    {
        $base = rtrim((string) App::config('app.url', ''), '/');

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * View dosyasını çalıştırır ve çıktısını string olarak döndürür.
     *
     * @param array<string, mixed> $data
     */
    private function render(string $view, array $data): string
    {
        // Yol geçişi (path traversal) ve beklenmeyen karakterlere karşı doğrulama
        if (
            preg_match('#^[a-zA-Z0-9_\-/]+$#', $view) !== 1
            || str_contains($view, '..')
        ) {
            throw new InvalidArgumentException('Geçersiz view adı: ' . $view);
        }

        $file = APP_PATH . '/Views/' . $view . '.php';

        if (!is_file($file)) {
            throw new RuntimeException('View dosyası bulunamadı: ' . $view);
        }

        // Değişken adı çakışmasını önlemek için "__" önekli yerel isimler kullanılır
        $__file = $file;
        $__data = $data;

        ob_start();

        try {
            // EXTR_SKIP: $this ve yukarıdaki yerel değişkenlerin ezilmesini engeller
            extract($__data, EXTR_SKIP);
            require $__file;

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            // Yarım kalmış çıktı kullanıcıya sızmasın
            ob_end_clean();

            throw $e;
        }
    }
}