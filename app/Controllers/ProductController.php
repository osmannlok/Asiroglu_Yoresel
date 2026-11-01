<?php

/**
 * Aşıroğlu Yöresel Market - Vitrin ürün controller'ı
 *
 * GET /urunler        -> ürün listesi (arama, kategori, fiyat, sıralama, sayfalama)
 * GET /urun/{slug}    -> ürün detay sayfası
 *
 * Kararlar:
 * - Bu controller yalnızca okuma yapar (GET); CSRF gerektiren işlem yoktur.
 * - Filtre girdileri Validator'dan geçer. Geçersiz filtre yok sayılır ve
 *   kullanıcıya uyarı olarak gösterilir; sayfa yine de açılır.
 * - Vitrin görünürlüğü (pasif/silinmiş ürün, kategori ve marka) Product
 *   modelindeki sorgularda uygulanır (FR-033). Bulunamayan ürün 404 verir.
 * - Varyantlı üründe fiyat ve stok varyant bazında hazırlanır; satın alınabilirlik
 *   varyantın aktifliği ve stoğundan hesaplanır (FR-022, FR-025).
 * - Fiyatlar KDV dahil, TRY. Sepetteki fiyat bilgilendirme amaçlıdır.
 * - Çıktı kaçırma view'lardadır.
 *
 * Gereksinimler: FR-002, FR-003, FR-004, FR-005, FR-006, FR-014, FR-015,
 * FR-022, FR-025, FR-033.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;

final class ProductController extends Controller
{
    /** Listede sayfa başına ürün */
    private const PER_PAGE = 12;

    /** Arama metninin en fazla uzunluğu */
    private const MAX_SEARCH_LENGTH = 100;

    /** Detay sayfasında gösterilecek benzer ürün sayısı */
    private const RELATED_LIMIT = 4;

    /** Geçerli sıralama anahtarları (Product::SORT_ORDERS ile aynı) */
    private const SORTS = ['new', 'price_asc', 'price_desc', 'name'];

    /** Slug biçimi (Admin slugify çıktısıyla uyumlu) */
    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    // -------------------------------------------------------------------------
    // Liste
    // -------------------------------------------------------------------------

    /**
     * Ürün listesi: GET /urunler
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $validator = Validator::make(
            [
                'q'      => $this->scalar($request->query('q')),
                'kategori' => $this->scalar($request->query('kategori')),
                'min'    => $this->scalar($request->query('min')),
                'max'    => $this->scalar($request->query('max')),
                'sirala' => $this->scalar($request->query('sirala')),
                'page'   => $this->scalar($request->query('page')),
            ],
            [
                'q'        => 'nullable|string|max:' . self::MAX_SEARCH_LENGTH,
                'kategori' => 'nullable|integer|min:1|max:4294967295',
                'min'      => 'nullable|decimal|max:9999999999',
                'max'      => 'nullable|decimal|max:9999999999',
                'sirala'   => 'nullable|in:' . implode(',', self::SORTS),
                'page'     => 'nullable|integer|min:1|max:100000',
            ],
            [
                'q'        => 'Arama',
                'kategori' => 'Kategori',
                'min'      => 'En düşük fiyat',
                'max'      => 'En yüksek fiyat',
                'sirala'   => 'Sıralama',
                'page'     => 'Sayfa',
            ]
        );

        // Geçersiz alanlar yok sayılır; yalnızca doğrulananlar kullanılır
        $errors = array_values($validator->errors());
        $data   = $validator->validated();

        $search     = isset($data['q']) ? (string) $data['q'] : '';
        $categoryId = isset($data['kategori']) ? (int) $data['kategori'] : null;
        $minPrice   = isset($data['min']) ? (float) $data['min'] : null;
        $maxPrice   = isset($data['max']) ? (float) $data['max'] : null;
        $sort       = isset($data['sirala']) ? (string) $data['sirala'] : Product::DEFAULT_SORT;
        $page       = isset($data['page']) ? (int) $data['page'] : 1;

        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            $errors[] = 'En düşük fiyat, en yüksek fiyattan büyük olamaz. Fiyat filtresi yok sayıldı.';
            $minPrice = null;
            $maxPrice = null;
        }

        $result = (new Product())->paginateStorefront(
            $page,
            self::PER_PAGE,
            $search === '' ? null : $search,
            $categoryId,
            $minPrice,
            $maxPrice,
            $sort
        );

        return $this->view('products/index', [
            'title'           => $search !== '' ? 'Arama: ' . $search : 'Ürünler',
            'metaDescription' => 'Yöresel ve doğal gıda ürünlerimizi inceleyin, güvenle sipariş verin.',
            'result'          => $result,
            'search'          => $search,
            'categoryId'      => $categoryId,
            'minPrice'        => $minPrice,
            'maxPrice'        => $maxPrice,
            'sort'            => $sort,
            'sorts'           => self::SORTS,
            'categories'      => (new Category())->getActiveTree(),
            'filterErrors'    => $errors,
        ]);
    }

    // -------------------------------------------------------------------------
    // Detay
    // -------------------------------------------------------------------------

    /**
     * Ürün detay sayfası: GET /urun/{slug}
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');

        if ($slug === '' || strlen($slug) > 255 || preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            return $this->notFound();
        }

        $products = new Product();
        $product  = $products->findBySlug($slug);

        if ($product === null) {
            return $this->notFound();
        }

        $productId = (int) $product['id'];
        $variants  = $this->prepareVariants($productId);
        $hasVariants = $variants !== [];

        // Varyantsız üründe fiyat ürünün kendisinden gelir
        $basePrice    = (float) $product['price'];
        $currentPrice = $products->currentPrice($product);
        $oldPrice     = $currentPrice < $basePrice ? $basePrice : null;

        $categories = new Category();
        $category   = $categories->find((int) $product['category_id']);
        $parent     = $category !== null && $category['parent_id'] !== null
            ? $categories->find((int) $category['parent_id'])
            : null;

        $brand = $product['brand_id'] !== null
            ? (new Brand())->find((int) $product['brand_id'])
            : null;

        $products->incrementViewCount($productId);

        $metaTitle = trim((string) ($product['meta_title'] ?? ''));
        $metaDesc  = trim((string) ($product['meta_description'] ?? ''));

        if ($metaDesc === '') {
            $metaDesc = mb_substr(trim((string) ($product['short_description'] ?? '')), 0, 160);
        }

        return $this->view('products/show', [
            'title'           => $metaTitle !== '' ? $metaTitle : (string) $product['name'],
            'metaDescription' => $metaDesc,
            'product'         => $product,
            'images'          => (new ProductImage())->getByProduct($productId),
            'variants'        => $variants,
            'hasVariants'     => $hasVariants,
            'currentPrice'    => $currentPrice,
            'oldPrice'        => $oldPrice,
            'availableStock'  => $products->getAvailableStock($productId),
            'category'        => $category,
            'parentCategory'  => $parent,
            'brand'           => $brand,
            'related'         => $this->relatedProducts($products, $product),
        ]);
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Ürünün aktif varyantlarını görünüm için hazırlar: geçerli fiyat, indirim
     * öncesi fiyat (varsa), stok ve satın alınabilirlik.
     *
     * @return list<array<string, mixed>>
     */
    private function prepareVariants(int $productId): array
    {
        $model  = new ProductVariant();
        $result = [];

        foreach ($model->getByProduct($productId, true) as $variant) {
            $price   = (float) $variant['price'];
            $current = $model->currentPrice($variant);
            $stock   = max(0, (int) $variant['stock_quantity']);

            $result[] = [
                'id'          => (int) $variant['id'],
                'name'        => (string) $variant['variant_name'],
                'sku'         => (string) $variant['sku'],
                'price'       => $current,
                'old_price'   => $current < $price ? $price : null,
                'stock'       => $stock,
                'purchasable' => $stock > 0,
            ];
        }

        return $result;
    }

    /**
     * Aynı kategoriden benzer ürünler (mevcut ürün hariç).
     *
     * @param array<string, mixed> $product
     *
     * @return list<array<string, mixed>>
     */
    private function relatedProducts(Product $products, array $product): array
    {
        $list = $products->paginateStorefront(
            1,
            self::RELATED_LIMIT + 1,
            null,
            (int) $product['category_id']
        );

        $related = [];

        foreach ($list['items'] as $item) {
            if ((int) $item['id'] === (int) $product['id']) {
                continue;
            }

            $related[] = $item;

            if (count($related) >= self::RELATED_LIMIT) {
                break;
            }
        }

        return $related;
    }

    /**
     * Metin olmayan (dizi vb.) değerleri boş sayar; Validator yalnızca
     * skaler değer alır.
     */
    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * Ürün bulunamadığında 404 yanıtı üretir (teknik ayrıntı içermez).
     */
    private function notFound(): Response
    {
        $title = htmlspecialchars('Ürün bulunamadı', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text  = htmlspecialchars(
            'Aradığınız ürün mevcut değil veya satıştan kaldırılmış olabilir.',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $link  = htmlspecialchars($this->url('/urunler'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $title . '</title>'
            . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">'
            . '</head><body class="bg-light">'
            . '<main class="container text-center py-5">'
            . '<h1 class="display-6 mb-3">' . $title . '</h1>'
            . '<p class="text-muted mb-4">' . $text . '</p>'
            . '<a href="' . $link . '" class="btn btn-success">Tüm Ürünler</a>'
            . '</main></body></html>';

        return Response::html($html, 404);
    }
}