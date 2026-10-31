<?php

/**
 * Aşıroğlu Yöresel Market - Güvenli görsel yükleme
 *
 * Kategori görseli, marka logosu, ürün galerisi ve banner yüklemeleri bu
 * sınıfla yapılır. Kurallar:
 * - Yalnızca jpg, jpeg, png ve webp kabul edilir.
 * - MIME türü istemciye güvenilmeden finfo ile içerikten okunur; getimagesize()
 *   dosyanın gerçekten geçerli bir görsel olduğunu doğrular; uzantı, MIME
 *   türüyle tutarlı olmalıdır.
 * - Dosya boyutu ve piksel boyutu sınırlıdır.
 * - Dosya adı rastgele üretilir; kullanıcının verdiği ad kullanılmaz.
 * - Hedef klasör yalnızca beyaz listeden seçilir (kullanıcı girdisi olamaz).
 * - Yalnızca is_uploaded_file() ile doğrulanan gerçek yüklemeler kabul edilir.
 *
 * Veritabanına yalnızca göreli yol yazılır (örn. "products/ab12....jpg").
 * Hata durumunda Türkçe, kullanıcıya gösterilebilir mesajlı RuntimeException
 * fırlatılır; teknik ayrıntı loga yazılır.
 *
 * Gereksinimler: FR-012, NFR-006.
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Uploader
{
    /** İzin verilen hedef klasörler (public/uploads altında) */
    public const FOLDERS = ['products', 'categories', 'brands', 'banners'];

    /** En büyük dosya boyutu (bayt): 2 MB */
    private const MAX_BYTES = 2 * 1024 * 1024;

    /** En büyük genişlik ve yükseklik (piksel) */
    private const MAX_DIMENSION = 6000;

    /**
     * Gerçek MIME türü => güvenli uzantı.
     * Dosya uzantısı bu tabloyla tutarlı olmalıdır.
     */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp'],
    ];

    /** PHP yükleme hata kodları => kullanıcı mesajı */
    private const UPLOAD_ERRORS = [
        UPLOAD_ERR_INI_SIZE   => 'Dosya sunucunun izin verdiği boyutu aşıyor.',
        UPLOAD_ERR_FORM_SIZE  => 'Dosya izin verilen boyutu aşıyor.',
        UPLOAD_ERR_PARTIAL    => 'Dosya yarım yüklendi. Lütfen tekrar deneyin.',
        UPLOAD_ERR_NO_TMP_DIR => 'Dosya yüklenemedi (geçici klasör yok).',
        UPLOAD_ERR_CANT_WRITE => 'Dosya yüklenemedi (diske yazılamadı).',
        UPLOAD_ERR_EXTENSION  => 'Dosya yüklemesi bir eklenti tarafından durduruldu.',
    ];

    /** Dışarıdan new Uploader() yapılamaz; yalnızca statik metotlar kullanılır */
    private function __construct()
    {
    }

    /**
     * Tek dosyayı doğrular ve kaydeder; veritabanına yazılacak göreli yolu döndürür.
     *
     * @param array<string, mixed>|null $file   $_FILES girdisi (Request::file())
     * @param string                    $folder FOLDERS içinden bir klasör
     *
     * @throws RuntimeException Dosya geçersizse veya kaydedilemezse
     */
    public static function save(?array $file, string $folder): string
    {
        if ($file === null) {
            throw new RuntimeException('Dosya seçilmedi.');
        }

        $directory = self::directory($folder);

        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if (!is_int($error) || $error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                self::UPLOAD_ERRORS[$error] ?? 'Dosya seçilmedi veya yüklenemedi.'
            );
        }

        $tmpName = $file['tmp_name'] ?? '';

        if (!is_string($tmpName) || $tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Geçersiz dosya yüklemesi.');
        }

        $size = $file['size'] ?? 0;

        if (!is_int($size) || $size <= 0) {
            throw new RuntimeException('Dosya boş.');
        }

        if ($size > self::MAX_BYTES) {
            throw new RuntimeException(
                'Dosya en fazla ' . (int) (self::MAX_BYTES / 1024 / 1024) . ' MB olabilir.'
            );
        }

        // Gerçek MIME türü içerikten okunur (istemcinin bildirdiği tür kullanılmaz)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo === false ? false : finfo_file($finfo, $tmpName);

        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])) {
            throw new RuntimeException('Yalnızca JPG, PNG veya WebP görseller yüklenebilir.');
        }

        // Dosya gerçekten çözümlenebilen bir görsel olmalı
        $info = @getimagesize($tmpName);

        if ($info === false || ($info['mime'] ?? '') !== $mime) {
            throw new RuntimeException('Dosya geçerli bir görsel değil.');
        }

        if ($info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION) {
            throw new RuntimeException(
                'Görsel en fazla ' . self::MAX_DIMENSION . 'x' . self::MAX_DIMENSION . ' piksel olabilir.'
            );
        }

        // Uzantı, gerçek MIME türüyle tutarlı olmalı
        $originalName = is_string($file['name'] ?? null) ? $file['name'] : '';
        $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, self::MIME_EXTENSIONS[$mime], true)) {
            throw new RuntimeException('Dosya uzantısı içeriğiyle uyuşmuyor.');
        }

        // Güvenli uzantı: MIME türünden gelen ilk değer (jpeg => jpg)
        $safeExtension = self::MIME_EXTENSIONS[$mime][0];
        $fileName      = bin2hex(random_bytes(16)) . '.' . $safeExtension;
        $target        = $directory . '/' . $fileName;

        if (!move_uploaded_file($tmpName, $target)) {
            error_log(sprintf('[%s] Uploader: dosya taşınamadı: %s', date('Y-m-d H:i:s'), $target));

            throw new RuntimeException('Dosya kaydedilemedi.');
        }

        @chmod($target, 0644);

        return $folder . '/' . $fileName;
    }

    /**
     * Birden çok dosyayı kaydeder (ürün galerisi). Boş seçilen alanlar atlanır.
     * Bir dosya geçersizse o ana kadar kaydedilenler silinir ve hata fırlatılır;
     * böylece yarım yükleme kalmaz.
     *
     * @param array<string, mixed>|null $files   Çoklu $_FILES girdisi (name[], tmp_name[] ...)
     * @param string                    $folder  FOLDERS içinden bir klasör
     *
     * @return list<string> Göreli yollar
     *
     * @throws RuntimeException Herhangi bir dosya geçersizse
     */
    public static function saveMany(?array $files, string $folder): array
    {
        if ($files === null || !isset($files['name']) || !is_array($files['name'])) {
            return [];
        }

        $saved = [];

        try {
            foreach (array_keys($files['name']) as $index) {
                $error = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;

                // Boş dosya alanı: atla
                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $saved[] = self::save(
                    [
                        'name'     => $files['name'][$index] ?? '',
                        'tmp_name' => $files['tmp_name'][$index] ?? '',
                        'size'     => $files['size'][$index] ?? 0,
                        'error'    => $error,
                    ],
                    $folder
                );
            }
        } catch (RuntimeException $e) {
            foreach ($saved as $path) {
                self::delete($path);
            }

            throw $e;
        }

        return $saved;
    }

    /**
     * Daha önce kaydedilmiş dosyayı siler. Yol doğrulanır; uploads klasörü
     * dışına çıkan veya biçimi geçersiz yollar reddedilir.
     *
     * @return bool Dosya silindiyse true
     */
    public static function delete(string $relativePath): bool
    {
        $pattern = '#^(' . implode('|', self::FOLDERS) . ')/[a-f0-9]{32}\.(jpg|png|webp)$#';

        if (preg_match($pattern, $relativePath, $matches) !== 1) {
            return false;
        }

        $file = self::baseDirectory() . '/' . $relativePath;

        return is_file($file) && @unlink($file);
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Beyaz listedeki klasörün tam yolunu döndürür; yoksa oluşturur.
     *
     * @throws RuntimeException Klasör geçersizse veya oluşturulamazsa
     */
    private static function directory(string $folder): string
    {
        if (!in_array($folder, self::FOLDERS, true)) {
            throw new RuntimeException('Geçersiz yükleme klasörü.');
        }

        $directory = self::baseDirectory() . '/' . $folder;

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            error_log(sprintf('[%s] Uploader: klasör oluşturulamadı: %s', date('Y-m-d H:i:s'), $directory));

            throw new RuntimeException('Yükleme klasörü hazırlanamadı.');
        }

        if (!is_writable($directory)) {
            error_log(sprintf('[%s] Uploader: klasör yazılabilir değil: %s', date('Y-m-d H:i:s'), $directory));

            throw new RuntimeException('Yükleme klasörüne yazılamıyor.');
        }

        return $directory;
    }

    /** Yüklemelerin kök klasörü: public/uploads */
    private static function baseDirectory(): string
    {
        return PUBLIC_PATH . '/uploads';
    }
}