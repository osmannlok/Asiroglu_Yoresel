<?php

/**
 * Aşıroğlu Yöresel Market - HTTP yanıtı
 *
 * Durum kodu, başlıklar ve gövdeyi tek nesnede toplar. HTML, JSON ve
 * yönlendirme yanıtları için hazır üreticiler sunar.
 */

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

final class Response
{
    /** Gövde göndermemesi gereken durum kodları */
    private const NO_BODY_STATUSES = [204, 304];

    /** @var array<string, string> Başlık adı => değer */
    private array $headers = [];

    private function __construct(
        private string $body,
        private int $status
    ) {
    }

    /**
     * HTML yanıtı üretir.
     */
    public static function html(string $html, int $status = 200): self
    {
        $response = new self($html, $status);
        $response->header('Content-Type', 'text/html; charset=UTF-8');

        return $response;
    }

    /**
     * JSON yanıtı üretir (Fetch API uç noktaları için).
     *
     * @param array<mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_THROW_ON_ERROR
        );

        $response = new self($json, $status);
        $response->header('Content-Type', 'application/json; charset=UTF-8');
        $response->header('Cache-Control', 'no-store');

        return $response;
    }

    /**
     * Yönlendirme yanıtı üretir.
     * Adres güvenliği (yalnızca site içi yol) Controller::redirect() tarafından denetlenir.
     */
    public static function redirect(string $url, int $status = 302): self
    {
        if (!in_array($status, [301, 302, 303, 307, 308], true)) {
            throw new InvalidArgumentException('Geçersiz yönlendirme durum kodu: ' . $status);
        }

        $response = new self('', $status);
        $response->header('Location', $url);

        return $response;
    }

    /**
     * Başlık ekler veya değiştirir. Zincirleme kullanılabilir.
     * Başlık adı ve değeri doğrulanır (başlık enjeksiyonu koruması).
     */
    public function header(string $name, string $value): self
    {
        if (preg_match('/^[A-Za-z0-9\-]+$/', $name) !== 1) {
            throw new InvalidArgumentException('Geçersiz başlık adı.');
        }

        if (preg_match('/[\r\n\0]/', $value) === 1) {
            throw new InvalidArgumentException('Başlık değeri satır sonu karakteri içeremez.');
        }

        $this->headers[$name] = $value;

        return $this;
    }

    /** HTTP durum kodunu döndürür. */
    public function status(): int
    {
        return $this->status;
    }

    /** Yanıt gövdesini döndürür. */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Yanıtı tarayıcıya gönderir: durum kodu, başlıklar ve gövde.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        // HEAD isteği ile 204/304 yanıtlarında gövde gönderilmez
        $isHead = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'HEAD';

        if ($isHead || in_array($this->status, self::NO_BODY_STATUSES, true)) {
            return;
        }

        echo $this->body;
    }
}