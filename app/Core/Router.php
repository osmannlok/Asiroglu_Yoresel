<?php

/**
 * Aşıroğlu Yöresel Market - Router
 *
 * HTTP metodu ve URL yolunu controller metotlarıyla eşleştirir.
 * Dinamik parametreleri ({slug}, {id:\d+}) destekler.
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
     * @var array<string, list<array{pattern: string, regex: string, handler: array{0: class-string, 1: string}|Closure}>>
     */
    private array $routes = [];

    /**
     * @param array{0: class-string, 1: string}|Closure $handler
     */
    public function get(string $pattern, array|Closure $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    /**
     * @param array{0: class-string, 1: string}|Closure $handler
     */
    public function post(string $pattern, array|Closure $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /**
     * @param array{0: class-string, 1: string}|Closure $handler
     */
    public function put(string $pattern, array|Closure $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    /**
     * @param array{0: class-string, 1: string}|Closure $handler
     */
    public function patch(string $pattern, array|Closure $handler): void
    {
        $this->add('PATCH', $pattern, $handler);
    }

    /**
     * @param array{0: class-string, 1: string}|Closure $handler
     */
    public function delete(string $pattern, array|Closure $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
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

                    return $this->invoke($route['handler'], $request, $params);
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
     * Route'u kaydeder ve desenini düzenli ifadeye çevirir.
     *
     * @param array{0: class-string, 1: string}|Closure $handler
     */
    private function add(string $method, string $pattern, array|Closure $handler): void
    {
        $pattern = $this->normalizePath($pattern);

        $this->routes[$method][] = [
            'pattern' => $pattern,
            'regex'   => $this->compile($pattern),
            'handler' => $handler,
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
     */
    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

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
     * Eşleşen handler'ı çalıştırır ve sonucu Response'a çevirir.
     *
     * @param array{0: class-string, 1: string}|Closure $handler
     * @param array<string, string>                     $params
     */
    private function invoke(array|Closure $handler, Request $request, array $params): Response
    {
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