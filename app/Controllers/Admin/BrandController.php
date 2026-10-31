<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli marka controller'ı
 *
 * GET  /admin/markalar                  -> liste (arama + sayfalama)
 * GET  /admin/markalar/olustur          -> yeni marka formu
 * POST /admin/markalar                  -> kaydet
 * GET  /admin/markalar/{id}/duzenle     -> düzenleme formu
 * POST /admin/markalar/{id}/guncelle    -> güncelle
 * POST /admin/markalar/{id}/sil         -> sil (soft delete)
 *
 * Kararlar:
 * - Erişim denetimi route grubundaki AdminMiddleware ile yapılır; burada
 *   ikinci savunma katmanı olarak yetki yeniden kontrol edilir.
 * - POST isteklerinin CSRF doğrulaması App::handle() içinde yapılır.
 * - Slug boşsa addan üretilir ve benzersizleştirilir; elle girilen slug
 *   çakışırsa hata verilir. UNIQUE indeks silinmiş kayıtları da kapsar.
 * - Web sitesi yalnızca http/https adresi olabilir (Validator 'url' kuralı).
 * - Silinmemiş ürünü olan marka silinemez (önce pasifleştirilir).
 *   Pasif marka ürünleri vitrinde listelenmez (FR-033).
 * - Logo yükleme bu controller'da yoktur; Uploader yazılınca eklenecektir.
 *
 * Gereksinimler: FR-032, FR-033, FR-090, NFR-003, NFR-004.
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
use App\Models\Brand;
use PDOException;
use RuntimeException;

final class BrandController extends Controller
{
    /** Liste sayfasının yolu */
    private const LIST_PATH = '/admin/markalar';

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
        'name',
        'slug',
        'description',
        'website',
    ];

    // -------------------------------------------------------------------------
    // Liste
    // -------------------------------------------------------------------------

    /**
     * Marka listesi: GET /admin/markalar
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

        $result = (new Brand())->paginate($page, self::PER_PAGE, $search === '' ? null : $search);

        return $this->view(
            'admin/brands/index',
            [
                'title'      => 'Markalar',
                'activeMenu' => 'brands',
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
     * Yeni marka formu: GET /admin/markalar/olustur
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
     * Yeni markayı kaydeder: POST /admin/markalar
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
     * Düzenleme formu: GET /admin/markalar/{id}/duzenle
     *
     * @param array<string, string> $params
     */
    public function edit(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $id    = (int) ($params['id'] ?? 0);
        $brand = $id > 0 ? (new Brand())->find($id) : null;

        if ($brand === null) {
            return $this->notFound();
        }

        return $this->showForm($id, $brand);
    }

    /**
     * Markayı günceller: POST /admin/markalar/{id}/guncelle
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0 || (new Brand())->find($id) === null) {
            return $this->notFound();
        }

        return $this->save($request, $id);
    }

    // -------------------------------------------------------------------------
    // Silme
    // -------------------------------------------------------------------------

    /**
     * Markayı siler (soft delete): POST /admin/markalar/{id}/sil
     *
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $id     = (int) ($params['id'] ?? 0);
        $brands = new Brand();

        if ($id <= 0 || $brands->find($id) === null) {
            return $this->notFound();
        }

        if ($brands->hasActiveProducts($id)) {
            Session::flash('error', 'Ürünü olan marka silinemez. Önce markayı pasifleştirin veya ürünleri başka markaya taşıyın.');

            return $this->redirect(self::LIST_PATH);
        }

        $brands->delete($id);

        Session::flash('success', 'Marka silindi.');

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
                'name'        => 'required|string|max:150',
                'slug'        => 'nullable|string|max:191',
                'description' => 'nullable|string|max:5000',
                'website'     => 'nullable|string|max:255|url',
            ],
            [
                'name'        => 'Marka adı',
                'slug'        => 'Slug',
                'description' => 'Açıklama',
                'website'     => 'Web sitesi',
            ]
        );

        $errors = $validator->errors();
        $data   = $validator->validated();
        $brands = new Brand();

        // --- Slug: elle girilmişse benzersiz olmalı, boşsa üretilir -----------
        $slug = '';

        if (!isset($errors['slug']) && !isset($errors['name'])) {
            $rawSlug = trim($input['slug']);

            if ($rawSlug !== '') {
                $slug = $this->slugify($rawSlug);

                if ($slug === '') {
                    $errors['slug'] = 'Slug en az bir harf veya rakam içermelidir.';
                } elseif ($brands->slugExists($slug, $id)) {
                    $errors['slug'] = 'Bu slug başka bir markada kullanılıyor.';
                }
            } else {
                $base = $this->slugify((string) ($data['name'] ?? ''));
                $slug = $this->uniqueSlug($brands, $base === '' ? 'marka' : $base, $id);
            }
        }

        if ($errors !== []) {
            return $this->backWithErrors($input, $errors, $id, $request);
        }

        $row = [
            'name'        => (string) $data['name'],
            'slug'        => $slug,
            'description' => $data['description'] ?? null,
            'website'     => $data['website'] ?? null,
            'is_active'   => $this->checkbox($request, 'is_active') ? 1 : 0,
        ];

        try {
            if ($id === null) {
                $brands->create($row);
                Session::flash('success', 'Marka eklendi.');
            } else {
                // Değişiklik yoksa MySQL 0 satır etkilenmiş bildirir; bu hata değildir
                $brands->update($id, $row);
                Session::flash('success', 'Marka güncellendi.');
            }
        } catch (RuntimeException $e) {
            // Eşzamanlı istekte slug çakışması: UNIQUE ihlali alan hatası olarak döner
            $previous = $e->getPrevious();

            if (
                $previous instanceof PDOException
                && (int) ($previous->errorInfo[1] ?? 0) === self::MYSQL_DUPLICATE_ENTRY
            ) {
                return $this->backWithErrors(
                    $input,
                    ['slug' => 'Bu slug başka bir markada kullanılıyor.'],
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
     * @param array<string, mixed>|null $brand Düzenlenen kayıt; yeni kayıtta null
     */
    private function showForm(?int $id, ?array $brand): Response
    {
        // Varsayılan değerler (yeni kayıt)
        $values = [
            'name'        => '',
            'slug'        => '',
            'description' => '',
            'website'     => '',
            'is_active'   => true,
        ];

        if ($brand !== null) {
            foreach (self::TEXT_FIELDS as $field) {
                $values[$field] = (string) ($brand[$field] ?? '');
            }
            $values['is_active'] = (int) $brand['is_active'] === 1;
        }

        // Başarısız denemeden dönülüyorsa kullanıcının girdiği değerler korunur
        $old = Session::getFlash('old');

        if (is_array($old)) {
            foreach (self::TEXT_FIELDS as $field) {
                if (isset($old[$field]) && is_string($old[$field])) {
                    $values[$field] = $old[$field];
                }
            }
            $values['is_active'] = !empty($old['is_active']);
        }

        $errors = Session::getFlash('form_errors');

        return $this->view(
            'admin/brands/form',
            [
                'title'      => $id === null ? 'Yeni Marka' : 'Markayı Düzenle',
                'activeMenu' => 'brands',
                'isEdit'     => $id !== null,
                'brandId'    => $id,
                'values'     => $values,
                'errors'     => is_array($errors) ? $errors : [],
                'formAction' => $id === null
                    ? self::LIST_PATH
                    : self::LIST_PATH . '/' . $id . '/guncelle',
                'listPath'   => self::LIST_PATH,
            ],
            'layouts/admin'
        );
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
        $old              = $input;
        $old['is_active'] = $this->checkbox($request, 'is_active');

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
    private function uniqueSlug(Brand $brands, string $base, ?int $exceptId): string
    {
        $slug = $base;

        for ($i = 2; $brands->slugExists($slug, $exceptId); $i++) {
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
        Session::flash('error', 'Marka bulunamadı.');

        return $this->redirect(self::LIST_PATH);
    }
}