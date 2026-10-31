<?php

/**
 * Aşıroğlu Yöresel Market - Router
 *
 * HTTP metodu ve URL yolunu controller metotlarıyla eşleştirir.
 * Dinamik parametreleri ({slug}, {id:\d+}) destekler.
 *
 * Middleware: route'a veya route grubuna sınıf adı listesi verilebilir.
 * Middleware sınıfları handle(Request $request): ?Response metoduna sahip
 * olmalıdır. null dönerse istek devam eder; Response dönerse zincir durur
 * ve controller çağrılmaz. Middleware yalnızca eşleşen route için çalışır.
 *
 * Örnek:
 *   $router->group(
 *       ['prefix' => '/admin', 'middleware' => [AdminMiddleware::class]],
 *       function (Router $router): void {
 *           $router->get('/', [DashboardController::class, 'index']);
 *       }
 *   );
 *
 * Gereksinimler: FR-090, G-04.
 */

declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;

final class Router
{
    /**
     * Kayıtlı route'lar: HTTP metodu => route listesi
     *
     * @var array<string, list<array{
     *     pattern: string,
     *     regex: string,
     *     handler: array{0: class-string, 1: string}|Closure,
     *     middleware: list<class-string>
     * }>>
     */
    private array $routes = [];

    /**
     * Açık grupların yığını (en içteki sonda).
     *
     * @var list<array{prefix: string, middleware: list<class-string>}>
     */
    private array $groupStack = [];

    /**
     * @param array{0: class-string, 1: string}|Closure $handler
     * @param list<class-string>                        $middleware
     */
    public function get(string $pattern, array|Closure $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string}|Closure $handler
     * @param list<class-string>                        $middleware
     */
    public function post(string $pattern, array|Closure $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string}|Closure $handler
     * @param list<class-string>                        $middleware
     */
    public function put(string $pattern, array|Closure $handler, array $middleware = []): void
    {
        $this->add('PUT', $pattern, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string}|Closure $handler
     * @param list<class-string>                        $middleware
     */
    public function patch(string $pattern, array|Closure $handler, array $middleware = []): void
    {
        $this->add('PATCH', $pattern, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string}|Closure $handler
     * @param list<class-string>                        $middleware
     */
    public function delete(string $pattern, array|Closure $handler, array $middleware = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middleware);
    }

    /**
     * Ortak ön ek ve/veya middleware'e sahip route grubu tanımlar.
     * Gruplar iç içe olabilir; ön ekler ve middleware'ler birleştirilir.
     *
     * Desteklenen seçenekler: 'prefix' (örn. '/admin'), 'middleware' (sınıf listesi).
     *
     * @param array{prefix?: string, middleware?: list<class-string>} $options
     * @param Closure(self): void                                      $callback Route'ları kaydeden fonksiyon
     */
    public function group(array $options, Closure $callback): void
    {
        $this->groupStack[] = [
            'prefix'     => (string) ($options['prefix'] ?? ''),
            'middleware' => array_values($options['middleware'] ?? []),
        ];

        try {
            $callback($this);
        } finally {
            // Callback hata fırlatsa bile grup yığını tutarlı kalır
            array_pop($this->groupStack);
        }
    }

    /**
     * İsteği eşleşen route'a yönlendirir.
     *
     * @return Response|null Eşleşme yoksa null (App bunu 404'e çevirir)
     */
    public function dispatch(Request $request): ?Response
    {
        $method = $request->method();
        $path   = $this->normalizePath($request->path());

        // HEAD istekleri önce HEAD, bulunamazsa GET route'larıyla eşleştirilir
        $methodsToTry = $method === 'HEAD' ? ['HEAD', 'GET'] : [$method];

        foreach ($methodsToTry as $tryMethod) {
            foreach ($this->routes[$tryMethod] ?? [] as $route) {
                if (preg_match($route['regex'], $path, $matches) === 1) {
                    // Yalnızca adlandırılmış (string anahtarlı) grupları parametre olarak al
                    $params = [];
                    foreach ($matches as $key => $value) {
                        if (is_string($key)) {
                            $params[$key] = rawurldecode($value);
                        }
                    }

                    return $this->invoke($route['handler'], $request, $params, $route['middleware']);
                }
            }
        }

        // Yol başka bir HTTP metodunda tanımlıysa 404 yerine 405 döndür
        if ($this->pathExistsForOtherMethod($path, $methodsToTry)) {
            return Response::html(
                '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">'
                . '<title>Yöntem desteklenmiyor</title></head>'
                . '<body><h1>405 - Yöntem desteklenmiyor</h1></body></html>',
                405
            );
        }

        return null;
    }

    /**
     * Route'u kaydeder; grup ön eklerini ve middleware'lerini uygular ve
     * desenini düzenli ifadeye çevirir.
     *
     * @param array{0: class-string, 1: string}|Closure $handler
     * @param list<class-string>                        $middleware
     */
    private function add(string $method, string $pattern, array|Closure $handler, array $middleware): void
    {
        $prefix      = '';
        $inherited   = [];

        foreach ($this->groupStack as $group) {
            $prefix   .= '/' . trim($group['prefix'], '/');
            $inherited = array_merge($inherited, $group['middleware']);
        }

        // Ön ek + desen birleştirilir; normalizePath fazla "/" karakterlerini temizler
        $pattern = $this->normalizePath($prefix . '/' . ltrim($pattern, '/'));

        $this->routes[$method][] = [
            'pattern'    => $pattern,
            'regex'      => $this->compile($pattern),
            'handler'    => $handler,
            // Önce grup middleware'leri, sonra route'a özel olanlar çalışır
            'middleware' => array_merge($inherited, array_values($middleware)),
        ];
    }

    /**
     * "/urun/{slug}" gibi bir deseni düzenli ifadeye çevirir.
     *
     * - {ad}       => [^/]+ (varsayılan, "/" içermeyen herhangi bir değer)
     * - {ad:\d+}   => özel kısıt (kısıt içinde "}" karakteri kullanılamaz)
     */
    private function compile(string $pattern): string
    {
        $parts = preg_split(
            '#(\{[a-zA-Z_][a-zA-Z0-9_]*(?::[^}]+)?\})#',
            $pattern,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        $regex = '';

        foreach ($parts ?: [] as $part) {
            if (preg_match('#^\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}$#', $part, $m) === 1) {
                $name       = $m[1];
                $constraint = $m[2] ?? '[^/]+';
                $regex     .= '(?P<' . $name . '>' . $constraint . ')';
            } else {
                // Sabit parçadaki özel karakterleri kaçır
                $regex .= preg_quote($part, '#');
            }
        }

        return '#^' . $regex . '$#u';
    }

    /**
     * Yolu normalleştirir: başında "/" olur, sonunda "/" olmaz (kök hariç).
     * Ardışık "/" karakterleri tek "/" olur.
     */
    private function normalizePath(string $path): string
    {
        $path = '/' . trim((string) preg_replace('#/{2,}#', '/', $path), '/');

        return $path === '' ? '/' : $path;
    }

    /**
     * Verilen yol, denenen metotlar dışında başka bir metotta tanımlı mı?
     *
     * @param list<string> $excludedMethods
     */
    private function pathExistsForOtherMethod(string $path, array $excludedMethods): bool
    {
        foreach ($this->routes as $method => $routes) {
            if (in_array($method, $excludedMethods, true)) {
                continue;
            }

            foreach ($routes as $route) {
                if (preg_match($route['regex'], $path) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Middleware zincirini, ardından eşleşen handler'ı çalıştırır ve sonucu
     * Response'a çevirir. Bir middleware Response döndürürse zincir durur.
     *
     * @param array{0: class-string, 1: string}|Closure $handler
     * @param array<string, string>                     $params
     * @param list<class-string>                        $middleware
     */
    private function invoke(array|Closure $handler, Request $request, array $params, array $middleware): Response
    {
        $blocked = $this->runMiddleware($middleware, $request);

        if ($blocked !== null) {
            return $blocked;
        }

        if ($handler instanceof Closure) {
            $result = $handler($request, $params);
        } else {
            [$class, $action] = $handler;

            if (!class_exists($class)) {
                throw new RuntimeException('Controller bulunamadı: ' . $class);
            }

            $controller = new $class();

            if (!is_callable([$controller, $action])) {
                throw new RuntimeException('Controller metodu çağrılamıyor: ' . $class . '::' . $action);
            }

            $result = $controller->$action($request, $params);
        }

        return $this->toResponse($result);
    }

    /**
     * Middleware'leri sırayla çalıştırır.
     *
     * Geçersiz bir middleware tanımı istekten GEÇİRİLMEZ; hata fırlatılır
     * (App::run() bunu 500'e çevirir). Böylece yanlış yazılmış bir sınıf
     * adı koruma açığına dönüşmez.
     *
     * @param list<class-string> $middleware
     *
     * @return Response|null Zinciri durduran yanıt; hepsi geçtiyse null
     */
    private function runMiddleware(array $middleware, Request $request): ?Response
    {
        foreach ($middleware as $class) {
            if (!is_string($class) || !class_exists($class)) {
                throw new RuntimeException('Middleware bulunamadı: ' . (is_string($class) ? $class : gettype($class)));
            }

            $instance = new $class();

            if (!method_exists($instance, 'handle')) {
                throw new RuntimeException('Middleware handle() metoduna sahip değil: ' . $class);
            }

            $result = $instance->handle($request);

            if ($result instanceof Response) {
                return $result;
            }

            if ($result !== null) {
                throw new RuntimeException('Middleware geçersiz bir değer döndürdü: ' . $class);
            }
        }

        return null;
    }

    /**
     * Controller çıktısını Response nesnesine dönüştürür.
     */
    private function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        throw new RuntimeException('Controller geçersiz bir yanıt döndürdü.');
    }
}