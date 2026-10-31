<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli kategori controller'ı
 *
 * GET  /admin/kategoriler                  -> liste (arama + sayfalama)
 * GET  /admin/kategoriler/olustur          -> yeni kategori formu
 * POST /admin/kategoriler                  -> kaydet
 * GET  /admin/kategoriler/{id}/duzenle     -> düzenleme formu
 * POST /admin/kategoriler/{id}/guncelle    -> güncelle
 * POST /admin/kategoriler/{id}/sil         -> sil (soft delete)
 *
 * Kararlar:
 * - Erişim denetimi route grubundaki AdminMiddleware ile yapılır; burada
 *   ikinci savunma katmanı olarak yetki yeniden kontrol edilir.
 * - POST isteklerinin CSRF doğrulaması App::handle() içinde yapılır.
 * - Slug boşsa addan üretilir ve benzersizleştirilir; elle girilen slug
 *   çakışırsa hata verilir. UNIQUE indeks silinmiş kayıtları da kapsar.
 * - Alt kategorisi veya silinmemiş ürünü olan kategori silinemez
 *   (önce pasifleştirilir). Pasif kategori vitrinde görünmez (FR-033).
 * - Görsel yükleme bu controller'da yoktur; Uploader yazılınca eklenecektir.
 * - Girdiler Validator'dan geçer; çıktı kaçırma view'lardadır.
 *
 * Gereksinimler: FR-030, FR-031, FR-033, FR-090, NFR-003, NFR-004.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Middleware\AdminMiddleware;
use App\Models\Category;
use InvalidArgumentException;
use PDOException;
use RuntimeException;

final class CategoryController extends Controller
{
    /** Liste sayfasının yolu */
    private const LIST_PATH = '/admin/kategoriler';

    /** Listede sayfa başına kayıt */
    private const PER_PAGE = 20;

    /** Arama metninin en fazla uzunluğu */
    private const MAX_SEARCH_LENGTH = 100;

    /** MySQL "yinelenen kayıt" hata kodu (UNIQUE ihlali) */
    private const MYSQL_DUPLICATE_ENTRY = 1062;

    /** Slug üretiminde Türkçe karakter karşılıkları */
    private const TURKISH_MAP = [
        'ç' => 'c', 'Ç' => 'c',
        'ğ' => 'g', 'Ğ' => 'g',
        'ı' => 'i', 'İ' => 'i',
        'ö' => 'o', 'Ö' => 'o',
        'ş' => 's', 'Ş' => 's',
        'ü' => 'u', 'Ü' => 'u',
    ];

    /** Formdan okunan metin alanları */
    private const TEXT_FIELDS = [
        'parent_id',
        'name',
        'slug',
        'description',
        'icon',
        'meta_title',
        'meta_description',
        'sort_order',
    ];

    // -------------------------------------------------------------------------
    // Liste
    // -------------------------------------------------------------------------

    /**
     * Kategori listesi: GET /admin/kategoriler
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $page   = max(1, (int) $request->query('page', 1));
        $search = $request->query('q', '');
        $search = is_string($search) ? mb_substr(trim($search), 0, self::MAX_SEARCH_LENGTH) : '';

        $result = (new Category())->paginate($page, self::PER_PAGE, $search === '' ? null : $search);

        return $this->view(
            'admin/categories/index',
            [
                'title'      => 'Kategoriler',
                'activeMenu' => 'categories',
                'result'     => $result,
                'search'     => $search,
            ],
            'layouts/admin'
        );
    }

    // -------------------------------------------------------------------------
    // Oluşturma
    // -------------------------------------------------------------------------

    /**
     * Yeni kategori formu: GET /admin/kategoriler/olustur
     *
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        return $this->showForm(null, null);
    }

    /**
     * Yeni kategoriyi kaydeder: POST /admin/kategoriler
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        return $this->save($request, null);
    }

    // -------------------------------------------------------------------------
    // Düzenleme
    // -------------------------------------------------------------------------

    /**
     * Düzenleme formu: GET /admin/kategoriler/{id}/duzenle
     *
     * @param array<string, string> $params
     */
    public function edit(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $id       = (int) ($params['id'] ?? 0);
        $category = $id > 0 ? (new Category())->find($id) : null;

        if ($category === null) {
            return $this->notFound();
        }

        return $this->showForm($id, $category);
    }

    /**
     * Kategoriyi günceller: POST /admin/kategoriler/{id}/guncelle
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0 || (new Category())->find($id) === null) {
            return $this->notFound();
        }

        return $this->save($request, $id);
    }

    // -------------------------------------------------------------------------
    // Silme
    // -------------------------------------------------------------------------

    /**
     * Kategoriyi siler (soft delete): POST /admin/kategoriler/{id}/sil
     *
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $id         = (int) ($params['id'] ?? 0);
        $categories = new Category();

        if ($id <= 0 || $categories->find($id) === null) {
            return $this->notFound();
        }

        if ($categories->hasChildren($id)) {
            Session::flash('error', 'Alt kategorisi olan kategori silinemez. Önce alt kategorileri taşıyın veya silin.');

            return $this->redirect(self::LIST_PATH);
        }

        if ($categories->hasActiveProducts($id)) {
            Session::flash('error', 'Ürünü olan kategori silinemez. Önce kategoriyi pasifleştirin veya ürünleri taşıyın.');

            return $this->redirect(self::LIST_PATH);
        }

        $categories->delete($id);

        Session::flash('success', 'Kategori silindi.');

        return $this->redirect(self::LIST_PATH);
    }

    // -------------------------------------------------------------------------
    // Ortak kaydetme akışı
    // -------------------------------------------------------------------------

    /**
     * Form girdisini doğrular ve oluşturur ($id null) veya günceller.
     * Hata varsa eski girdi ve hatalar flash'a yazılır, forma dönülür.
     */
    private function save(Request $request, ?int $id): Response
    {
        $input = $this->readInput($request);

        $validator = Validator::make(
            $input,
            [
                'parent_id'        => 'nullable|integer|min:1',
                'name'             => 'required|string|max:150',
                'slug'             => 'nullable|string|max:191',
                'description'      => 'nullable|string|max:5000',
                'icon'             => 'nullable|string|max:100',
                'meta_title'       => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:500',
                'sort_order'       => 'nullable|integer|min:0|max:9999',
            ],
            [
                'parent_id'        => 'Üst kategori',
                'name'             => 'Kategori adı',
                'slug'             => 'Slug',
                'description'      => 'Açıklama',
                'icon'             => 'İkon',
                'meta_title'       => 'SEO başlığı',
                'meta_description' => 'SEO açıklaması',
                'sort_order'       => 'Sıra',
            ]
        );

        $errors     = $validator->errors();
        $data       = $validator->validated();
        $categories = new Category();

        // --- Üst kategori: var olmalı ve döngü oluşturmamalı ------------------
        $parentId = null;

        if (!isset($errors['parent_id']) && ($data['parent_id'] ?? null) !== null) {
            $parentId = (int) $data['parent_id'];

            if ($categories->find($parentId) === null) {
                $errors['parent_id'] = 'Seçilen üst kategori bulunamadı.';
            } elseif ($id !== null && $categories->wouldCreateCycle($id, $parentId)) {
                $errors['parent_id'] = 'Bir kategori kendisinin veya kendi alt kategorisinin altına taşınamaz.';
            }
        }

        // --- Slug: elle girilmişse benzersiz olmalı, boşsa üretilir -----------
        $slug = '';

        if (!isset($errors['slug']) && !isset($errors['name'])) {
            $rawSlug = trim($input['slug']);

            if ($rawSlug !== '') {
                $slug = $this->slugify($rawSlug);

                if ($slug === '') {
                    $errors['slug'] = 'Slug en az bir harf veya rakam içermelidir.';
                } elseif ($categories->slugExists($slug, $id)) {
                    $errors['slug'] = 'Bu slug başka bir kategoride kullanılıyor.';
                }
            } else {
                $base = $this->slugify((string) ($data['name'] ?? ''));
                $slug = $this->uniqueSlug($categories, $base === '' ? 'kategori' : $base, $id);
            }
        }

        if ($errors !== []) {
            return $this->backWithErrors($input, $errors, $id, $request);
        }

        $row = [
            'parent_id'        => $parentId,
            'name'             => (string) $data['name'],
            'slug'             => $slug,
            'description'      => $data['description'] ?? null,
            'icon'             => $data['icon'] ?? null,
            'meta_title'       => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'sort_order'       => (int) ($data['sort_order'] ?? 0),
            'is_active'        => $this->checkbox($request, 'is_active') ? 1 : 0,
            'is_featured'      => $this->checkbox($request, 'is_featured') ? 1 : 0,
        ];

        try {
            if ($id === null) {
                $categories->create($row);
                Session::flash('success', 'Kategori eklendi.');
            } else {
                // Değişiklik yoksa MySQL 0 satır etkilenmiş bildirir; bu hata değildir
                $categories->update($id, $row);
                Session::flash('success', 'Kategori güncellendi.');
            }
        } catch (InvalidArgumentException $e) {
            // Model döngü kontrolü (yarış durumuna karşı ikinci kontrol)
            return $this->backWithErrors($input, ['parent_id' => $e->getMessage()], $id, $request);
        } catch (RuntimeException $e) {
            // Eşzamanlı istekte slug çakışması: UNIQUE ihlali kullanıcıya alan hatası olarak döner
            $previous = $e->getPrevious();

            if (
                $previous instanceof PDOException
                && (int) ($previous->errorInfo[1] ?? 0) === self::MYSQL_DUPLICATE_ENTRY
            ) {
                return $this->backWithErrors(
                    $input,
                    ['slug' => 'Bu slug başka bir kategoride kullanılıyor.'],
                    $id,
                    $request
                );
            }

            throw $e;
        }

        return $this->redirect(self::LIST_PATH);
    }

    // -------------------------------------------------------------------------
    // Form gösterimi
    // -------------------------------------------------------------------------

    /**
     * Oluşturma/düzenleme formunu hazırlar.
     * Önceki istekte hata olduysa eski girdi ve hatalar flash'tan okunur.
     *
     * @param array<string, mixed>|null $category Düzenlenen kayıt; yeni kayıtta null
     */
    private function showForm(?int $id, ?array $category): Response
    {
        $categories = new Category();

        // Varsayılan değerler (yeni kayıt)
        $values = [
            'parent_id'        => '',
            'name'             => '',
            'slug'             => '',
            'description'      => '',
            'icon'             => '',
            'meta_title'       => '',
            'meta_description' => '',
            'sort_order'       => '0',
            'is_active'        => true,
            'is_featured'      => false,
        ];

        if ($category !== null) {
            foreach (self::TEXT_FIELDS as $field) {
                $values[$field] = (string) ($category[$field] ?? '');
            }
            $values['is_active']   = (int) $category['is_active'] === 1;
            $values['is_featured'] = (int) $category['is_featured'] === 1;
        }

        // Başarısız denemeden dönülüyorsa kullanıcının girdiği değerler korunur
        $old = Session::getFlash('old');

        if (is_array($old)) {
            foreach (self::TEXT_FIELDS as $field) {
                if (isset($old[$field]) && is_string($old[$field])) {
                    $values[$field] = $old[$field];
                }
            }
            $values['is_active']   = !empty($old['is_active']);
            $values['is_featured'] = !empty($old['is_featured']);
        }

        $errors = Session::getFlash('form_errors');

        return $this->view(
            'admin/categories/form',
            [
                'title'      => $id === null ? 'Yeni Kategori' : 'Kategoriyi Düzenle',
                'activeMenu' => 'categories',
                'isEdit'     => $id !== null,
                'categoryId' => $id,
                'values'     => $values,
                'errors'     => is_array($errors) ? $errors : [],
                'parents'    => $this->parentOptions($categories, $id),
                'formAction' => $id === null
                    ? self::LIST_PATH
                    : self::LIST_PATH . '/' . $id . '/guncelle',
                'listPath'   => self::LIST_PATH,
            ],
            'layouts/admin'
        );
    }

    /**
     * Üst kategori seçenekleri: silinmemiş tüm kategoriler. Düzenlemede
     * kategorinin kendisi ve alt kategorileri (döngü yaratacak olanlar) çıkarılır.
     *
     * @return list<array<string, mixed>>
     */
    private function parentOptions(Category $categories, ?int $id): array
    {
        $options = [];

        foreach ($categories->all('name', 'ASC') as $row) {
            $candidateId = (int) $row['id'];

            if ($id !== null && $categories->wouldCreateCycle($id, $candidateId)) {
                continue;
            }

            $options[] = $row;
        }

        return $options;
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Hata durumunda eski girdiyi ve hataları flash'a yazıp forma döner.
     *
     * @param array<string, string> $input
     * @param array<string, string> $errors
     */
    private function backWithErrors(array $input, array $errors, ?int $id, Request $request): Response
    {
        $old                = $input;
        $old['is_active']   = $this->checkbox($request, 'is_active');
        $old['is_featured'] = $this->checkbox($request, 'is_featured');

        Session::flash('error', 'Lütfen formdaki hataları düzeltin.');
        Session::flash('form_errors', $errors);
        Session::flash('old', $old);

        return $this->redirect(
            $id === null
                ? self::LIST_PATH . '/olustur'
                : self::LIST_PATH . '/' . $id . '/duzenle'
        );
    }

    /**
     * Metin alanlarını istekten okur; metin olmayan değerler boş sayılır.
     *
     * @return array<string, string>
     */
    private function readInput(Request $request): array
    {
        $input = [];

        foreach (self::TEXT_FIELDS as $field) {
            $value         = $request->input($field);
            $input[$field] = is_string($value) ? $value : '';
        }

        return $input;
    }

    /** İşaret kutusu (checkbox) işaretli mi? */
    private function checkbox(Request $request, string $name): bool
    {
        $value = $request->input($name);

        return in_array($value, ['1', 'on', 'true', 1, true], true);
    }

    /**
     * Metni URL parçasına çevirir: Türkçe karakterler sadeleştirilir,
     * harf/rakam dışındakiler tireye dönüşür. Sonuç en fazla 191 karakterdir.
     */
    private function slugify(string $text): string
    {
        $text = mb_strtolower(strtr($text, self::TURKISH_MAP), 'UTF-8');
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

        return trim(mb_substr($text, 0, 191), '-');
    }

    /**
     * Çakışan slug'a -2, -3 ... ekleyerek benzersiz bir değer üretir.
     */
    private function uniqueSlug(Category $categories, string $base, ?int $exceptId): string
    {
        $slug = $base;

        for ($i = 2; $categories->slugExists($slug, $exceptId); $i++) {
            if ($i > 1000) {
                // Aşırı çakışma: rastgele sonek ile kesin çözüm
                return mb_substr($base, 0, 180) . '-' . bin2hex(random_bytes(4));
            }

            $suffix = '-' . $i;
            $slug   = mb_substr($base, 0, 191 - strlen($suffix)) . $suffix;
        }

        return $slug;
    }

    /**
     * Savunma katmanı: panele giremeyen kullanıcıyı giriş sayfasına gönderir.
     */
    private function guard(): ?Response
    {
        return Auth::canAccessAdmin() ? null : $this->redirect(AdminMiddleware::LOGIN_PATH);
    }

    /**
     * Kayıt bulunamadığında listeye hata mesajıyla döner.
     */
    private function notFound(): Response
    {
        Session::flash('error', 'Kategori bulunamadı.');

        return $this->redirect(self::LIST_PATH);
    }
}