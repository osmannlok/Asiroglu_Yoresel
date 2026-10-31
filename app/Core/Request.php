<?php

/**
 * Aşıroğlu Yöresel Market - HTTP isteği
 *
 * Gelen isteğin metodu, yolu, parametreleri, başlıkları ve dosyalarını
 * tek bir nesnede toplar. Değerler ham haliyle saklanır; doğrulama
 * Validator sınıfında, HTML kaçırma ise çıktı anında yapılır.
 */

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** HTML formlarından _method alanı ile taklit edilebilen metotlar */
    private const SPOOFABLE_METHODS = ['PUT', 'PATCH', 'DELETE'];

    /**
     * @param array<string, mixed>  $query   GET parametreleri
     * @param array<string, mixed>  $body    POST alanları veya çözülmüş JSON gövdesi
     * @param array<string, mixed>  $files   $_FILES dizisi
     * @param array<string, string> $headers Küçük harfli başlık adı => değer
     * @param array<string, mixed>  $server  $_SERVER dizisi
     */
    private function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $body,
        private array $files,
        private array $headers,
        private array $server
    ) {
    }

    /**
     * PHP global değişkenlerinden istek nesnesi oluşturur.
     */
    public static function capture(): self
    {
        $server  = $_SERVER;
        $headers = self::extractHeaders($server);

        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));

        // Gövde: JSON ise çözülür, değilse $_POST kullanılır
        $contentType = strtolower($headers['content-type'] ?? '');
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $body    = is_array($decoded) ? $decoded : [];
        } else {
            $body = $_POST;
        }

        // Formlar için metot taklidi: yalnızca gerçek POST ve izinli değerler
        if ($method === 'POST' && isset($body['_method']) && is_string($body['_method'])) {
            $spoofed = strtoupper($body['_method']);
            if (in_array($spoofed, self::SPOOFABLE_METHODS, true)) {
                $method = $spoofed;
            }
        }

        return new self(
            $method,
            self::resolvePath($server),
            $_GET,
            $body,
            $_FILES,
            $headers,
            $server
        );
    }

    /** HTTP metodu (büyük harf): GET, POST, PUT, PATCH, DELETE... */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Sorgu dizesi çıkarılmış, URL-decode edilmemiş istek yolu.
     * Alt klasör öneki çıkarılmıştır. Örnek: "/urun/antep-fistigi"
     */
    public function path(): string
    {
        return $this->path;
    }

    /** GET parametresi okur. */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** POST alanı veya JSON gövde değeri okur. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * Gövde ve sorgu parametrelerinin birleşimi (gövde önceliklidir).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->body + $this->query;
    }

    /**
     * Yalnızca belirtilen anahtarları döndürür (mass assignment'a karşı).
     *
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /** Yüklenen dosya bilgisini döndürür; yoksa null. */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    /** Başlık değerini okur (büyük/küçük harf duyarsız). */
    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /** İstek JSON yanıt bekliyor mu? (Fetch API / AJAX) */
    public function expectsJson(): bool
    {
        $accept = strtolower($this->header('accept', '') ?? '');
        $type   = strtolower($this->header('content-type', '') ?? '');

        return str_contains($accept, 'application/json')
            || str_contains($type, 'application/json')
            || strtolower($this->header('x-requested-with', '') ?? '') === 'xmlhttprequest';
    }

    /** İstek HTTPS üzerinden mi geldi? */
    public function isSecure(): bool
    {
        $https = strtolower((string) ($this->server['HTTPS'] ?? ''));

        return ($https !== '' && $https !== 'off')
            || (int) ($this->server['SERVER_PORT'] ?? 0) === 443;
    }

    /**
     * İstemci IP adresi. Yalnızca REMOTE_ADDR kullanılır;
     * X-Forwarded-For istemci tarafından sahte gönderilebileceği için güvenilmez.
     */
    public function ip(): string
    {
        $ip = (string) ($this->server['REMOTE_ADDR'] ?? '');

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
    }

    /** Tarayıcı bilgisi (en fazla 500 karakter, şema ile uyumlu). */
    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 500);
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * REQUEST_URI'den yolu çıkarır. parse_url() kullanılmaz, çünkü "//x/y"
     * biçimindeki yolları host olarak yorumlar.
     *
     * @param array<string, mixed> $server
     */
    private static function resolvePath(array $server): string
    {
        $uri = (string) ($server['REQUEST_URI'] ?? '/');

        // Sorgu dizesi ve fragment çıkarılır
        $uri = explode('#', explode('?', $uri, 2)[0], 2)[0];

        // Alt klasörde çalışma: /ashiroglu-market/public/urun/x => /urun/x
        $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($server['SCRIPT_NAME'] ?? ''))), '/');
        if ($scriptDir !== '' && ($uri === $scriptDir || str_starts_with($uri, $scriptDir . '/'))) {
            $uri = substr($uri, strlen($scriptDir));
        }

        return '/' . ltrim($uri, '/');
    }

    /**
     * $_SERVER içinden HTTP başlıklarını çıkarır.
     *
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function extractHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                // HTTP_X_CSRF_TOKEN => x-csrf-token
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            } elseif ($key === 'CONTENT_TYPE') {
                $headers['content-type'] = $value;
            } elseif ($key === 'CONTENT_LENGTH') {
                $headers['content-length'] = $value;
            }
        }

        return $headers;
    }
}