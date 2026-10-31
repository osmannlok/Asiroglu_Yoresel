<?php

/**
 * Aşıroğlu Yöresel Market - Ürün görseli modeli
 *
 * product_images tablosu (migration 006) için veri erişimi sağlar.
 *
 * Kurallar:
 * - Üründe tek ana görsel (is_main = 1) olur; bu kural burada uygulanır.
 * - İlk eklenen görsel otomatik ana görsel olur (FR-012).
 * - Ana görsel silinirse sıradaki ilk görsel ana yapılır.
 * - Yeni görsel sıralamada en sona eklenir; reorder() ile sıra değiştirilir (FR-013).
 * - Model dosya sistemine dokunmaz: kayıt silinince image_path döner,
 *   dosyayı controller Uploader::delete() ile kaldırır.
 *
 * Tüm sorgular PDO prepared statement ile çalışır; $_GET / $_POST kullanılmaz.
 *
 * Gereksinimler: FR-012, FR-013, FR-014.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;

final class ProductImage extends Model
{
    protected string $table = 'product_images';

    /** Görsel kayıtları fiziksel silinir */
    protected bool $softDelete = false;

    /**
     * @var list<string>
     */
    protected array $fillable = [
        'product_id',
        'image_path',
        'alt_text',
        'sort_order',
        'is_main',
    ];

    // -------------------------------------------------------------------------
    // Sorgular
    // -------------------------------------------------------------------------

    /**
     * Ürünün görsellerini sıralı döndürür (ana görsel önce gelmez; sıra sort_order'dır).
     *
     * @return list<array<string, mixed>>
     */
    public function getByProduct(int $productId): array
    {
        return $this->fetchAll(
            'SELECT * FROM product_images WHERE product_id = ?'
            . ' ORDER BY sort_order ASC, id ASC',
            [$productId]
        );
    }

    /**
     * Ürünün ana görselini döndürür; yoksa null.
     *
     * @return array<string, mixed>|null
     */
    public function findMain(int $productId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM product_images WHERE product_id = ? AND is_main = 1'
            . ' ORDER BY id ASC LIMIT 1',
            [$productId]
        );
    }

    /**
     * Görsel belirtilen ürüne ait mi? (controller'da sahiplik denetimi için)
     *
     * @return array<string, mixed>|null
     */
    public function findForProduct(int $imageId, int $productId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM product_images WHERE id = ? AND product_id = ?',
            [$imageId, $productId]
        );
    }

    /** Ürünün görsel sayısı. */
    public function countByProduct(int $productId): int
    {
        return (int) $this->fetchValue(
            'SELECT COUNT(*) FROM product_images WHERE product_id = ?',
            [$productId]
        );
    }

    // -------------------------------------------------------------------------
    // Ekleme
    // -------------------------------------------------------------------------

    /**
     * Ürüne görsel ekler ve yeni kaydın ID'sini döndürür.
     * Sıralamada en sona eklenir; üründe ana görsel yoksa ana görsel olur.
     *
     * @throws InvalidArgumentException Ürün kimliği veya yol geçersizse
     */
    public function addImage(int $productId, string $imagePath, ?string $altText = null): int
    {
        if ($productId <= 0 || trim($imagePath) === '') {
            throw new InvalidArgumentException('Geçersiz görsel kaydı.');
        }

        $alt = $altText === null ? null : mb_substr(trim($altText), 0, 255);

        return (int) $this->transaction(function () use ($productId, $imagePath, $alt): int {
            // Aynı ürüne eşzamanlı ekleme sırasında sıra ve ana görsel tutarlı kalsın
            $this->fetchValue('SELECT id FROM products WHERE id = ? FOR UPDATE', [$productId]);

            $nextOrder = (int) $this->fetchValue(
                'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = ?',
                [$productId]
            );

            $hasMain = $this->findMain($productId) !== null;

            return parent::create([
                'product_id' => $productId,
                'image_path' => $imagePath,
                'alt_text'   => $alt === '' ? null : $alt,
                'sort_order' => $nextOrder,
                'is_main'    => $hasMain ? 0 : 1,
            ]);
        });
    }

    // -------------------------------------------------------------------------
    // Ana görsel, sıralama, alt metin
    // -------------------------------------------------------------------------

    /**
     * Görseli ana görsel yapar; üründeki diğerlerinin işaretini kaldırır.
     *
     * @return bool Görsel ürüne aitse true
     */
    public function setMain(int $imageId, int $productId): bool
    {
        if ($this->findForProduct($imageId, $productId) === null) {
            return false;
        }

        $this->transaction(function () use ($imageId, $productId): void {
            $this->execute(
                'UPDATE product_images SET is_main = 0 WHERE product_id = ?',
                [$productId]
            );
            $this->execute(
                'UPDATE product_images SET is_main = 1 WHERE id = ? AND product_id = ?',
                [$imageId, $productId]
            );
        });

        return true;
    }

    /**
     * Görsellerin sırasını günceller. $orderedIds yeni sırayı verir; yalnızca
     * ürüne ait kimlikler kabul edilir, geri kalanlar yok sayılır.
     *
     * @param list<int> $orderedIds
     */
    public function reorder(int $productId, array $orderedIds): void
    {
        $owned = array_map(
            static fn (array $row): int => (int) $row['id'],
            $this->getByProduct($productId)
        );

        $this->transaction(function () use ($productId, $orderedIds, $owned): void {
            $position = 0;

            foreach ($orderedIds as $imageId) {
                $imageId = (int) $imageId;

                if (!in_array($imageId, $owned, true)) {
                    continue;
                }

                $this->execute(
                    'UPDATE product_images SET sort_order = ? WHERE id = ? AND product_id = ?',
                    [$position, $imageId, $productId]
                );
                $position++;
            }
        });
    }

    /**
     * Görselin alt metnini günceller (SEO). Boş metin NULL olur.
     */
    public function updateAlt(int $imageId, int $productId, ?string $altText): bool
    {
        $alt = $altText === null ? '' : mb_substr(trim($altText), 0, 255);

        return $this->execute(
            'UPDATE product_images SET alt_text = ? WHERE id = ? AND product_id = ?',
            [$alt === '' ? null : $alt, $imageId, $productId]
        ) > 0;
    }

    // -------------------------------------------------------------------------
    // Silme
    // -------------------------------------------------------------------------

    /**
     * Görsel kaydını siler ve dosya yolunu döndürür (dosyayı controller siler).
     * Silinen görsel ana görselse sıradaki ilk görsel ana yapılır.
     *
     * @return string|null Silinen kaydın image_path değeri; görsel ürüne ait değilse null
     */
    public function removeImage(int $imageId, int $productId): ?string
    {
        $image = $this->findForProduct($imageId, $productId);

        if ($image === null) {
            return null;
        }

        $this->transaction(function () use ($imageId, $productId, $image): void {
            $this->execute(
                'DELETE FROM product_images WHERE id = ? AND product_id = ?',
                [$imageId, $productId]
            );

            if ((int) $image['is_main'] === 1 && $this->findMain($productId) === null) {
                $this->execute(
                    'UPDATE product_images SET is_main = 1 WHERE product_id = ?'
                    . ' ORDER BY sort_order ASC, id ASC LIMIT 1',
                    [$productId]
                );
            }
        });

        return (string) $image['image_path'];
    }
}