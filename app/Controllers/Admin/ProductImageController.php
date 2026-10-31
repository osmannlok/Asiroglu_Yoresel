<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli ürün görseli controller'ı
 *
 * GET  /admin/urunler/{id}/gorseller                   -> görsel listesi ve yükleme formu
 * POST /admin/urunler/{id}/gorseller                   -> çoklu görsel yükle
 * POST /admin/urunler/{id}/gorseller/sirala            -> sıralamayı kaydet (Fetch, JSON)
 * POST /admin/urunler/{id}/gorseller/{gid}/ana         -> ana görsel yap
 * POST /admin/urunler/{id}/gorseller/{gid}/alt         -> alt metni güncelle
 * POST /admin/urunler/{id}/gorseller/{gid}/sil         -> görseli sil (kayıt + dosya)
 *
 * Kararlar:
 * - Erişim denetimi route grubundaki AdminMiddleware ile yapılır; burada
 *   ikinci savunma katmanı olarak yetki yeniden kontrol edilir.
 * - POST isteklerinin CSRF doğrulaması App::handle() içinde yapılır.
 * - Dosya doğrulaması (MIME, boyut, piksel, uzantı) Uploader'dadır; bu
 *   controller yalnızca Uploader'ın Türkçe hata mesajını kullanıcıya iletir.
 * - Ürün başına en fazla MAX_IMAGES görsel yüklenebilir.
 * - Veritabanı kaydı başarısız olursa bu istekte yüklenen dosyalar silinir;
 *   diskte sahipsiz dosya kalmaz.
 * - Görsel silinirken önce kayıt silinir (ana görsel devri ProductImage'dadır),
 *   ardından dosya Uploader::delete() ile kaldırılır (DECISIONS.md karar 29).
 * - Her görsel işleminde görselin URL'deki ürüne ait olduğu doğrulanır.
 * - Girdiler Validator'dan geçer; çıktı kaçırma view'lardadır.
 *
 * Gereksinimler: FR-012, FR-013, FR-014, NFR-003, NFR-004, NFR-006.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Uploader;
use App\Core\Validator;
use App\Middleware\AdminMiddleware;
use App\Models\Product;
use App\Models\ProductImage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ProductImageController extends Controller
{
    /** Ürün listesinin yolu */
    private const PRODUCT_LIST_PATH = '/admin/urunler';

    /** Ürün başına en fazla görsel sayısı */
    private const MAX_IMAGES = 10;

    /** Sıralama isteğinde kabul edilen en fazla kimlik sayısı */
    private const MAX_REORDER_IDS = 100;

    /** Uploader hedef klasörü (beyaz listede tanımlıdır) */
    private const UPLOAD_FOLDER = 'products';

    /** Formdaki dosya alanının adı (name="images[]" multiple) */
    private const FILE_FIELD = 'images';

    // -------------------------------------------------------------------------
    // Liste
    // -------------------------------------------------------------------------

    /**
     * Ürünün görsel listesi: GET /admin/urunler/{id}/gorseller
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $product = $this->findProduct($params);

        if ($product === null) {
            return $this->productNotFound();
        }

        $productId = (int) $product['id'];

        return $this->view(
            'admin/products/images/index',
            [
                'title'      => 'Görseller - ' . (string) $product['name'],
                'activeMenu' => 'products',
                'product'    => $product,
                'images'     => (new ProductImage())->getByProduct($productId),
                'maxImages'  => self::MAX_IMAGES,
                'basePath'   => $this->basePath($productId),
                'listPath'   => self::PRODUCT_LIST_PATH,
            ],
            'layouts/admin'
        );
    }

    // -------------------------------------------------------------------------
    // Yükleme
    // -------------------------------------------------------------------------

    /**
     * Bir veya birden çok görsel yükler: POST /admin/urunler/{id}/gorseller
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $product = $this->findProduct($params);

        if ($product === null) {
            return $this->productNotFound();
        }

        $productId = (int) $product['id'];
        $images    = new ProductImage();
        $files     = $request->file(self::FILE_FIELD);

        $selected = $this->countSelectedFiles($files);

        if ($selected === 0) {
            return $this->failBack($productId, 'Lütfen en az bir görsel seçin.');
        }

        $existing = $images->countByProduct($productId);

        if ($existing + $selected > self::MAX_IMAGES) {
            $remaining = max(0, self::MAX_IMAGES - $existing);

            return $this->failBack(
                $productId,
                'Bir ürüne en fazla ' . self::MAX_IMAGES . ' görsel eklenebilir. '
                . 'Eklenebilecek görsel sayısı: ' . $remaining . '.'
            );
        }

        // Dosya doğrulaması ve kaydı: hata olursa Uploader o ana kadarkileri siler
        try {
            $paths = Uploader::saveMany($files, self::UPLOAD_FOLDER);
        } catch (RuntimeException $e) {
            // Uploader mesajları kullanıcıya gösterilebilir Türkçe mesajlardır
            return $this->failBack($productId, $e->getMessage());
        }

        // Veritabanı kayıtları: hata olursa yüklenen dosyaları geri al
        $added = [];

        try {
            foreach ($paths as $path) {
                $images->addImage($productId, $path);
                $added[] = $path;
            }
        } catch (Throwable $e) {
            foreach ($paths as $path) {
                Uploader::delete($path);
            }

            // Kayıt eklenmiş görseller varsa kayıtlarını da geri al
            foreach ($added as $path) {
                $this->removeRecordByPath($images, $productId, $path);
            }

            throw $e;
        }

        Session::flash(
            'success',
            count($paths) === 1 ? 'Görsel yüklendi.' : count($paths) . ' görsel yüklendi.'
        );

        return $this->redirect($this->basePath($productId));
    }

    // -------------------------------------------------------------------------
    // Ana görsel
    // -------------------------------------------------------------------------

    /**
     * Görseli ana görsel yapar: POST /admin/urunler/{id}/gorseller/{gid}/ana
     *
     * @param array<string, string> $params
     */
    public function setMain(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $product = $this->findProduct($params);

        if ($product === null) {
            return $this->productNotFound();
        }

        $productId = (int) $product['id'];
        $imageId   = (int) ($params['gid'] ?? 0);

        if ($imageId <= 0 || !(new ProductImage())->setMain($imageId, $productId)) {
            return $this->imageNotFound($productId);
        }

        Session::flash('success', 'Ana görsel güncellendi.');

        return $this->redirect($this->basePath($productId));
    }

    // -------------------------------------------------------------------------
    // Alt metin
    // -------------------------------------------------------------------------

    /**
     * Alt metni günceller: POST /admin/urunler/{id}/gorseller/{gid}/alt
     *
     * @param array<string, string> $params
     */
    public function updateAlt(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $product = $this->findProduct($params);

        if ($product === null) {
            return $this->productNotFound();
        }

        $productId = (int) $product['id'];
        $imageId   = (int) ($params['gid'] ?? 0);
        $images    = new ProductImage();

        if ($imageId <= 0 || $images->findForProduct($imageId, $productId) === null) {
            return $this->imageNotFound($productId);
        }

        $rawAlt = $request->input('alt_text');

        $validator = Validator::make(
            ['alt_text' => is_string($rawAlt) ? $rawAlt : ''],
            ['alt_text' => 'nullable|string|max:255'],
            ['alt_text' => 'Alt metin']
        );

        if ($validator->fails()) {
            return $this->failBack($productId, (string) $validator->firstError());
        }

        // Değişiklik yoksa MySQL 0 satır etkilenmiş bildirir; bu hata değildir
        $images->updateAlt($imageId, $productId, $validator->validated()['alt_text'] ?? null);

        Session::flash('success', 'Alt metin güncellendi.');

        return $this->redirect($this->basePath($productId));
    }

    // -------------------------------------------------------------------------
    // Sıralama
    // -------------------------------------------------------------------------

    /**
     * Görsel sırasını kaydeder: POST /admin/urunler/{id}/gorseller/sirala
     *
     * Fetch ile gönderilir. Gövde (JSON): {"order": [12, 7, 9]}
     * Form olarak gönderilirse "order[]" alanı da kabul edilir.
     *
     * @param array<string, string> $params
     */
    public function reorder(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $product = $this->findProduct($params);

        if ($product === null) {
            return $this->json(['success' => false, 'message' => 'Ürün bulunamadı.'], 404);
        }

        $order = $request->input('order');

        if (!is_array($order) || $order === [] || count($order) > self::MAX_REORDER_IDS) {
            return $this->json(['success' => false, 'message' => 'Geçersiz sıralama verisi.'], 422);
        }

        $ids = [];

        foreach ($order as $value) {
            // Yalnızca pozitif tamsayı kimlikler kabul edilir
            if (!is_scalar($value) || preg_match('/^[1-9]\d*$/', (string) $value) !== 1) {
                return $this->json(['success' => false, 'message' => 'Geçersiz sıralama verisi.'], 422);
            }

            $ids[] = (int) $value;
        }

        // Ürüne ait olmayan kimlikleri model yok sayar
        (new ProductImage())->reorder((int) $product['id'], $ids);

        return $this->json(['success' => true, 'message' => 'Görsel sırası kaydedildi.']);
    }

    // -------------------------------------------------------------------------
    // Silme
    // -------------------------------------------------------------------------

    /**
     * Görseli siler (kayıt + dosya): POST /admin/urunler/{id}/gorseller/{gid}/sil
     *
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $product = $this->findProduct($params);

        if ($product === null) {
            return $this->productNotFound();
        }

        $productId = (int) $product['id'];
        $imageId   = (int) ($params['gid'] ?? 0);

        // Önce kayıt silinir (ana görsel devri modelde yapılır), sonra dosya
        $path = $imageId > 0 ? (new ProductImage())->removeImage($imageId, $productId) : null;

        if ($path === null) {
            return $this->imageNotFound($productId);
        }

        Uploader::delete($path);

        Session::flash('success', 'Görsel silindi.');

        return $this->redirect($this->basePath($productId));
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Çoklu dosya alanında gerçekten dosya seçilen girdi sayısını döndürür.
     * Boş bırakılan alanlar (UPLOAD_ERR_NO_FILE) sayılmaz.
     *
     * @param array<string, mixed>|null $files
     */
    private function countSelectedFiles(?array $files): int
    {
        if ($files === null || !isset($files['error']) || !is_array($files['error'])) {
            return 0;
        }

        $count = 0;

        foreach ($files['error'] as $error) {
            if ($error !== UPLOAD_ERR_NO_FILE) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Yükleme sırasında eklenen bir kaydı dosya yoluyla bulup siler (geri alma).
     * Geri alma sırasında oluşacak hata asıl hatayı gizlememelidir.
     */
    private function removeRecordByPath(ProductImage $images, int $productId, string $path): void
    {
        try {
            foreach ($images->getByProduct($productId) as $row) {
                if ((string) $row['image_path'] === $path) {
                    $images->removeImage((int) $row['id'], $productId);
                    break;
                }
            }
        } catch (Throwable $e) {
            error_log(sprintf(
                '[%s] ProductImageController: geri alma başarısız (%s): %s',
                date('Y-m-d H:i:s'),
                $path,
                $e->getMessage()
            ));
        }
    }

    /**
     * Hata mesajını flash'a yazıp görsel sayfasına döner.
     */
    private function failBack(int $productId, string $message): Response
    {
        Session::flash('error', $message);

        return $this->redirect($this->basePath($productId));
    }

    /**
     * URL'deki ürünü bulur (silinmiş ürünler dahil edilmez).
     *
     * @param array<string, string> $params
     *
     * @return array<string, mixed>|null
     */
    private function findProduct(array $params): ?array
    {
        $id = (int) ($params['id'] ?? 0);

        return $id > 0 ? (new Product())->find($id) : null;
    }

    /** Ürünün görsel sayfalarının kök yolu. */
    private function basePath(int $productId): string
    {
        return self::PRODUCT_LIST_PATH . '/' . $productId . '/gorseller';
    }

    /**
     * Savunma katmanı: panele giremeyen kullanıcıyı giriş sayfasına gönderir.
     */
    private function guard(): ?Response
    {
        return Auth::canAccessAdmin() ? null : $this->redirect(AdminMiddleware::LOGIN_PATH);
    }

    /** Ürün bulunamadığında ürün listesine hata mesajıyla döner. */
    private function productNotFound(): Response
    {
        Session::flash('error', 'Ürün bulunamadı.');

        return $this->redirect(self::PRODUCT_LIST_PATH);
    }

    /** Görsel bulunamadığında görsel listesine hata mesajıyla döner. */
    private function imageNotFound(int $productId): Response
    {
        Session::flash('error', 'Görsel bulunamadı.');

        return $this->redirect($this->basePath($productId));
    }
}