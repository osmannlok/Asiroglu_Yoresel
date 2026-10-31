<?php

/**
 * Aşıroğlu Yöresel Market - Girdi doğrulama
 *
 * Form ve Fetch isteklerinden gelen verileri kurallara göre doğrular.
 * Her alan için ilk hatada durur ve Türkçe hata mesajı üretir.
 *
 * Örnek:
 *   $v = Validator::make($request->all(), [
 *       'email'    => 'required|email|max:191',
 *       'password' => 'required|password|confirmed',
 *   ], ['email' => 'E-posta']);
 *
 *   if ($v->fails()) { ... $v->errors() ... }
 *   $data = $v->validated();
 */

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use InvalidArgumentException;

final class Validator
{
    /** Sayısal karşılaştırma yapılmasını sağlayan kurallar */
    private const NUMERIC_RULES = ['numeric', 'integer', 'decimal'];

    /** bcrypt'in işlediği en fazla bayt sayısı */
    private const PASSWORD_MAX_BYTES = 72;

    /** @var array<string, string> Alan => hata mesajı */
    private array $errors = [];

    /** @var array<string, mixed> Doğrulanmış ve temizlenmiş veri */
    private array $validated = [];

    /**
     * @param array<string, mixed>                     $data   Doğrulanacak veri
     * @param array<string, string|list<string>>       $rules  Alan => kurallar
     * @param array<string, string>                    $labels Alan => görünen ad
     */
    private function __construct(
        private array $data,
        private array $rules,
        private array $labels
    ) {
    }

    /**
     * Doğrulamayı çalıştırır ve sonucu tutan nesneyi döndürür.
     *
     * @param array<string, mixed>               $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string>              $labels
     */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        $validator = new self($data, $rules, $labels);
        $validator->run();

        return $validator;
    }

    /** Doğrulama başarısız mı? */
    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** Doğrulama başarılı mı? */
    public function passes(): bool
    {
        return $this->errors === [];
    }

    /**
     * Alan => hata mesajı dizisi.
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /** İlk hata mesajı; hata yoksa null. */
    public function firstError(): ?string
    {
        return $this->errors === [] ? null : reset($this->errors);
    }

    /**
     * Yalnızca kuralı tanımlı alanları, temizlenmiş haliyle döndürür.
     * Doğrulama başarısızsa yalnızca geçen alanlar içerir.
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return $this->validated;
    }

    // -------------------------------------------------------------------------
    // Doğrulama döngüsü
    // -------------------------------------------------------------------------

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleSet) {
            $field = (string) $field;
            $rules = $this->parseRules($ruleSet);
            $names = array_column($rules, 'name');

            $value = $this->data[$field] ?? null;

            // Şifre alanlarında boşluk anlamlıdır; diğer metinler kırpılır
            if (is_string($value) && !in_array('password', $names, true)) {
                $value = trim($value);
            }

            $required = in_array('required', $names, true);

            if ($this->isEmpty($value)) {
                if ($required) {
                    $this->errors[$field] = $this->message('required', $field);
                } else {
                    $this->validated[$field] = null;
                }
                continue;
            }

            // Dizi/nesne gibi beklenmeyen tipler güvenli şekilde reddedilir
            if (!is_scalar($value)) {
                $this->errors[$field] = $this->message('invalid', $field);
                continue;
            }

            $numeric = array_intersect($names, self::NUMERIC_RULES) !== [];
            $error   = null;

            foreach ($rules as $rule) {
                if (in_array($rule['name'], ['required', 'nullable'], true)) {
                    continue;
                }

                $error = $this->check($rule['name'], $rule['params'], $field, $value, $numeric);

                if ($error !== null) {
                    break; // Alan başına ilk hata yeterli
                }
            }

            if ($error !== null) {
                $this->errors[$field] = $error;
            } else {
                $this->validated[$field] = $value;
            }
        }
    }

    /**
     * Kural tanımını ad ve parametre listesine ayırır.
     * "min:3" => ['name' => 'min', 'params' => ['3']]
     * Metin biçiminde "|" ile ayrılır; regex gibi "|" içeren kurallar dizi ile verilmelidir.
     *
     * @param string|list<string> $ruleSet
     * @return list<array{name: string, params: list<string>, raw: string}>
     */
    private function parseRules(string|array $ruleSet): array
    {
        $items  = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
        $parsed = [];

        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }

            // Yalnızca ilk ":" ayırıcıdır (regex içinde ":" olabilir)
            $parts = explode(':', $item, 2);
            $name  = strtolower($parts[0]);
            $raw   = $parts[1] ?? '';

            // "regex" ham kalır; diğer kurallar virgülle bölünür
            $params = $raw === '' ? [] : ($name === 'regex' ? [$raw] : explode(',', $raw));

            $parsed[] = ['name' => $name, 'params' => $params, 'raw' => $raw];
        }

        return $parsed;
    }

    /**
     * Tek bir kuralı değere uygular. Hata mesajı veya null (geçti) döndürür.
     *
     * @param list<string> $params
     */
    private function check(string $rule, array $params, string $field, mixed $value, bool $numeric): ?string
    {
        $text = (string) $value;

        switch ($rule) {
            case 'string':
                return is_string($value) ? null : $this->message('string', $field);

            case 'email':
                return filter_var($text, FILTER_VALIDATE_EMAIL) !== false
                    ? null
                    : $this->message('email', $field);

            case 'min':
            case 'max':
                $limit = $this->requireParam($rule, $params);

                if ($numeric) {
                    if (!is_numeric($text)) {
                        return null; // Sayı kuralı zaten hatayı yakalar
                    }
                    $failed = $rule === 'min' ? (float) $text < (float) $limit : (float) $text > (float) $limit;
                    $key    = $rule . '_numeric';
                } else {
                    $length = mb_strlen($text);
                    $failed = $rule === 'min' ? $length < (int) $limit : $length > (int) $limit;
                    $key    = $rule . '_string';
                }

                return $failed ? $this->message($key, $field, [':' . $rule => $limit]) : null;

            case 'numeric':
                return preg_match('/^-?\d+(\.\d+)?$/', $text) === 1 ? null : $this->message('numeric', $field);

            case 'integer':
                return preg_match('/^-?\d+$/', $text) === 1 ? null : $this->message('integer', $field);

            case 'decimal':
                // Negatif olmayan, en fazla iki ondalık basamaklı (para/fiyat/miktar)
                return preg_match('/^\d+(\.\d{1,2})?$/', $text) === 1 ? null : $this->message('decimal', $field);

            case 'boolean':
                $allowed = [true, false, 0, 1, '0', '1', 'true', 'false', 'on', 'off'];

                return in_array($value, $allowed, true) ? null : $this->message('invalid', $field);

            case 'in':
                return in_array($text, $params, true) ? null : $this->message('invalid', $field);

            case 'regex':
                $pattern = $this->requireParam($rule, $params);
                $result  = preg_match($pattern, $text);

                if ($result === false) {
                    throw new InvalidArgumentException('Geçersiz regex kuralı: ' . $pattern);
                }

                return $result === 1 ? null : $this->message('regex', $field);

            case 'same':
                $other = $this->requireParam($rule, $params);

                return $text === (string) ($this->data[$other] ?? '')
                    ? null
                    : $this->message('same', $field, [':other' => $this->label($other)]);

            case 'confirmed':
                $confirmation = $this->data[$field . '_confirmation'] ?? null;

                return is_scalar($confirmation) && hash_equals($text, (string) $confirmation)
                    ? null
                    : $this->message('confirmed', $field);

            case 'url':
                return $this->isHttpUrl($text) ? null : $this->message('url', $field);

            case 'date':
                return $this->isDate($text) ? null : $this->message('date', $field);

            case 'phone':
                // 0532 123 45 67 / +90 532 123 45 67 / (532) 123-45-67
                $digits = preg_replace('/[\s\-\(\)]/', '', $text) ?? '';

                return preg_match('/^(\+90|0)?5\d{9}$/', $digits) === 1 ? null : $this->message('phone', $field);

            case 'slug':
                return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $text) === 1 ? null : $this->message('slug', $field);

            case 'password':
                if (strlen($text) > self::PASSWORD_MAX_BYTES) {
                    return $this->message('password_max', $field);
                }

                return mb_strlen($text) >= 8
                    && preg_match('/\p{L}/u', $text) === 1
                    && preg_match('/\d/', $text) === 1
                    ? null
                    : $this->message('password', $field);
        }

        // Tanımsız kural bir geliştirici hatasıdır; loglanır, kullanıcıya genel hata gider
        throw new InvalidArgumentException('Tanımsız doğrulama kuralı: ' . $rule);
    }

    // -------------------------------------------------------------------------
    // Yardımcılar
    // -------------------------------------------------------------------------

    /** Değer boş mu? ('0' ve 0 boş sayılmaz) */
    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * @param list<string> $params
     */
    private function requireParam(string $rule, array $params): string
    {
        if (!isset($params[0]) || $params[0] === '') {
            throw new InvalidArgumentException('"' . $rule . '" kuralı parametre gerektirir.');
        }

        return $params[0];
    }

    /** Yalnızca http/https adreslerini kabul eder (javascript: vb. reddedilir). */
    private function isHttpUrl(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    /** "2026-10-30" veya "2026-10-30 14:30:00" biçimindeki gerçek tarihleri kabul eder. */
    private function isDate(string $value): bool
    {
        foreach (['Y-m-d', 'Y-m-d H:i:s'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);

            if ($date !== false && $date->format($format) === $value) {
                return true;
            }
        }

        return false;
    }

    /** Alanın görünen adı; tanımlı değilse alan adı. */
    private function label(string $field): string
    {
        return $this->labels[$field] ?? $field;
    }

    /**
     * Hata mesajı üretir.
     *
     * @param array<string, string> $replace Ek yer tutucular (":min" => "3")
     */
    private function message(string $key, string $field, array $replace = []): string
    {
        $messages = [
            'required'     => ':field alanı zorunludur.',
            'invalid'      => ':field alanı geçersiz bir değer içeriyor.',
            'string'       => ':field metin olmalıdır.',
            'email'        => ':field geçerli bir e-posta adresi olmalıdır.',
            'min_string'   => ':field en az :min karakter olmalıdır.',
            'max_string'   => ':field en fazla :max karakter olmalıdır.',
            'min_numeric'  => ':field en az :min olmalıdır.',
            'max_numeric'  => ':field en fazla :max olmalıdır.',
            'numeric'      => ':field sayı olmalıdır (ondalık ayırıcı nokta olmalıdır).',
            'integer'      => ':field tam sayı olmalıdır.',
            'decimal'      => ':field en fazla iki ondalık basamaklı, negatif olmayan bir sayı olmalıdır.',
            'regex'        => ':field biçimi geçersiz.',
            'same'         => ':field alanı :other alanı ile aynı olmalıdır.',
            'confirmed'    => ':field doğrulaması eşleşmiyor.',
            'url'          => ':field geçerli bir web adresi (http veya https) olmalıdır.',
            'date'         => ':field geçerli bir tarih olmalıdır (YYYY-AA-GG).',
            'phone'        => ':field geçerli bir cep telefonu numarası olmalıdır.',
            'slug'         => ':field yalnızca küçük harf, rakam ve tire içermelidir.',
            'password'     => ':field en az 8 karakter olmalı ve en az bir harf ile bir rakam içermelidir.',
            'password_max' => ':field en fazla 72 bayt uzunluğunda olabilir.',
        ];

        $replace[':field'] = $this->label($field);

        return strtr($messages[$key] ?? $messages['invalid'], $replace);
    }
}