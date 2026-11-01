<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli kargo yöntemi controller'ı
 *
 * GET  /admin/kargo                      -> liste (pasifler dahil)
 * GET  /admin/kargo/olustur              -> yeni yöntem formu
 * POST /admin/kargo                      -> kaydet
 * GET  /admin/kargo/{id}/duzenle         -> düzenleme formu
 * POST /admin/kargo/{id}/guncelle        -> güncelle
 * POST /admin/kargo/{id}/durum           -> aktif/pasif (Fetch veya form)
 *
 * Kararlar:
 * - Erişim denetimi route grubundaki AdminMiddleware ile yapılır; burada
 *   ikinci savunma katmanı olarak yetki yeniden kontrol edilir.
 * - POST isteklerinin CSRF doğrulaması App::handle() içinde yapılır.
 * - Kargo yöntemi fiziksel silinmez (ShippingMethod::delete() kapalıdır);
 *   satıştan kaldırmak için is_active = 0 yapılır. Bu yüzden "sil" route'u yoktur.
 * - "code" programatik kimliktir ve yalnızca oluştururken yazılır; güncellemede
 *   değiştirilemez (kodu kullanan kod/entegrasyon bozulmasın).
 * - Son aktif yöntem kapatılamaz: aktif yöntem kalmazsa checkout kargo
 *   seçemez ve sipariş verilemez.
 * - Ücret ve eşik TRY, KDV dahildir; ondalık ayırıcı nokta. Ücret 0 olabilir;
 *   ücretsiz kargo eşiği boşsa eşik yoktur.
 * - Girdiler Validator'dan geçer; çıktı kaçırma view'lardadır.
 *
 * Gereksinimler: FR-084, FR-085, FR-090, NFR-003, NFR-004.
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
use App\Models\ShippingMethod;
use InvalidArgumentException;
use PDOException;
use RuntimeException;

final class ShippingMethodController extends Controller
{
    /** Liste sayfasının yolu */
    private const LIST_PATH = '/admin/kargo';

    /** MySQL "yinelenen kayıt" hata kodu (UNIQUE ihlali) */
    private const MYSQL_DUPLICATE_ENTRY = 1062;

    /** Kod biçimi (ShippingMethod::CODE_PATTERN ile aynı) */
    private const CODE_PATTERN = '/^[a-z0-9_]{1,50}$/';

    /** Formdan okunan metin alanları */
    private const TEXT_FIELDS = [
        'name',
        'code',
        'description',
        'price',
        'free_shipping_limit',
        'sort_order',
    ];

    // -------------------------------------------------------------------------
    // Liste
    // -------------------------------------------------------------------------

    /**
     * Kargo yöntemi listesi: GET /admin/kargo
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        return $this->view(
            'admin/shipping/index',
            [
                'title'      => 'Kargo Yöntemleri',
                'activeMenu' => 'shipping',
                'methods'    => (new ShippingMethod())->getAllForAdmin(),
                'basePath'   => self::LIST_PATH,
            ],
            'layouts/admin'
        );
    }

    // -------------------------------------------------------------------------
    // Oluşturma
    // -------------------------------------------------------------------------

    /**
     * Yeni yöntem formu: GET /admin/kargo/olustur
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
     * Yeni yöntemi kaydeder: POST /admin/kargo
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
     * Düzenleme formu: GET /admin/kargo/{id}/duzenle
     *
     * @param array<string, string> $params
     */
    public function edit(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $id     = (int) ($params['id'] ?? 0);
        $method = $id > 0 ? (new ShippingMethod())->find($id) : null;

        if ($method === null) {
            return $this->notFound();
        }

        return $this->showForm($id, $method);
    }

    /**
     * Yöntemi günceller: POST /admin/kargo/{id}/guncelle
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0 || (new ShippingMethod())->find($id) === null) {
            return $this->notFound();
        }

        return $this->save($request, $id);
    }

    // -------------------------------------------------------------------------
    // Aktif / pasif
    // -------------------------------------------------------------------------

    /**
     * Yöntemi satışa açar veya kapatır: POST /admin/kargo/{id}/durum
     *
     * Gövdede "is_active" = 1 veya 0 beklenir. Fetch isteklerine JSON,
     * normal form isteklerine yönlendirme döner. Son aktif yöntem kapatılamaz.
     *
     * @param array<string, string> $params
     */
    public function toggle(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $asJson  = $request->expectsJson();
        $methods = new ShippingMethod();
        $id      = (int) ($params['id'] ?? 0);
        $method  = $id > 0 ? $methods->find($id) : null;

        if ($method === null) {
            return $asJson
                ? $this->json(['success' => false, 'message' => 'Kargo yöntemi bulunamadı.'], 404)
                : $this->notFound();
        }

        $value = $request->input('is_active');

        if (!in_array($value, ['0', '1', 0, 1], true)) {
            return $this->toggleFailed($asJson, 'Geçersiz durum değeri.', 422);
        }

        $active = (int) $value === 1;

        // Son aktif yöntemin kapatılması checkout'u kargosuz bırakır
        if (
            !$active
            && (int) $method['is_active'] === 1
            && count($methods->getActive()) <= 1
        ) {
            return $this->toggleFailed(
                $asJson,
                'Son aktif kargo yöntemi kapatılamaz. Önce başka bir yöntemi etkinleştirin.',
                422
            );
        }

        // Değişiklik yoksa MySQL 0 satır etkilenmiş bildirir; bu hata değildir.
        $methods->setActive($id, $active);

        $message = $active ? 'Kargo yöntemi etkinleştirildi.' : 'Kargo yöntemi kapatıldı.';

        if ($asJson) {
            return $this->json(['success' => true, 'message' => $message, 'is_active' => $active]);
        }

        Session::flash('success', $message);

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
        $input   = $this->readInput($request);
        $methods = new ShippingMethod();

        // Güncellemede kod değiştirilemez; mevcut değer kullanılır
        if ($id !== null) {
            $existing      = $methods->find($id);
            $input['code'] = $existing === null ? '' : (string) $existing['code'];
        }

        $validator = Validator::make(
            $input,
            [
                'name'                => 'required|string|max:150',
                // Desen "|" içermese de regex kuralları dizi biçiminde verilir
                'code'                => ['required', 'string', 'max:50', 'regex:' . self::CODE_PATTERN],
                'description'         => 'nullable|string|max:500',
                'price'               => 'required|decimal|min:0|max:9999999999',
                'free_shipping_limit' => 'nullable|decimal|min:0.01|max:9999999999',
                'sort_order'          => 'nullable|integer|min:0|max:9999',
            ],
            [
                'name'                => 'Yöntem adı',
                'code'                => 'Kod',
                'description'         => 'Açıklama',
                'price'               => 'Kargo ücreti',
                'free_shipping_limit' => 'Ücretsiz kargo eşiği',
                'sort_order'          => 'Sıra',
            ]
        );

        $errors = $validator->errors();
        $data   = $validator->validated();

        // --- Kod: yalnızca oluştururken benzersiz olmalı -----------------------
        if ($id === null && !isset($errors['code']) && $methods->codeExists((string) $data['code'])) {
            $errors['code'] = 'Bu kod başka bir kargo yönteminde kullanılıyor.';
        }

        if ($errors !== []) {
            return $this->backWithErrors($input, $errors, $id, $request);
        }

        $row = [
            'name'                => (string) $data['name'],
            'description'         => $data['description'] ?? null,
            'price'               => (string) $data['price'],
            'free_shipping_limit' => isset($data['free_shipping_limit']) ? (string) $data['free_shipping_limit'] : null,
            'sort_order'          => (int) ($data['sort_order'] ?? 0),
            'is_active'           => $this->checkbox($request, 'is_active') ? 1 : 0,
        ];

        // Son aktif yöntem form üzerinden de pasifleştirilemez
        if (
            $id !== null
            && $row['is_active'] === 0
            && (int) ($methods->find($id)['is_active'] ?? 0) === 1
            && count($methods->getActive()) <= 1
        ) {
            return $this->backWithErrors(
                $input,
                ['is_active' => 'Son aktif kargo yöntemi kapatılamaz. Önce başka bir yöntemi etkinleştirin.'],
                $id,
                $request
            );
        }

        try {
            if ($id === null) {
                // Kod yalnızca oluşturmada yazılır
                $row['code'] = (string) $data['code'];
                $methods->create($row);
                Session::flash('success', 'Kargo yöntemi eklendi.');
            } else {
                // code bilinçli olarak yazılmaz.
                // Değişiklik yoksa MySQL 0 satır etkilenmiş bildirir; bu hata değildir.
                $methods->update($id, $row);
                Session::flash('success', 'Kargo yöntemi güncellendi.');
            }
        } catch (InvalidArgumentException $e) {
            // Model kuralları (ikinci savunma katmanı)
            return $this->backWithErrors(
                $input,
                [$this->fieldForMessage($e->getMessage()) => $e->getMessage()],
                $id,
                $request
            );
        } catch (RuntimeException $e) {
            // Eşzamanlı istekte kod çakışması: UNIQUE ihlali alan hatası olarak döner
            $previous = $e->getPrevious();

            if (
                $previous instanceof PDOException
                && (int) ($previous->errorInfo[1] ?? 0) === self::MYSQL_DUPLICATE_ENTRY
            ) {
                return $this->backWithErrors(
                    $input,
                    ['code' => 'Bu kod başka bir kargo yönteminde kullanılıyor.'],
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
     * @param array<string, mixed>|null $method Düzenlenen kayıt; yeni kayıtta null
     */
    private function showForm(?int $id, ?array $method): Response
    {
        // Varsayılan değerler (yeni kayıt)
        $values = array_fill_keys(self::TEXT_FIELDS, '');

        $values['sort_order'] = '0';
        $values['is_active']  = true;

        if ($method !== null) {
            foreach (self::TEXT_FIELDS as $field) {
                $values[$field] = (string) ($method[$field] ?? '');
            }

            $values['is_active'] = (int) $method['is_active'] === 1;
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

            // Düzenlemede kod salt okunurdur; eski girdi onu ezmemelidir
            if ($method !== null) {
                $values['code'] = (string) $method['code'];
            }
        }

        $errors = Session::getFlash('form_errors');

        return $this->view(
            'admin/shipping/form',
            [
                'title'      => $id === null ? 'Yeni Kargo Yöntemi' : 'Kargo Yöntemini Düzenle',
                'activeMenu' => 'shipping',
                'isEdit'     => $id !== null,
                'methodId'   => $id,
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
     * Model kural mesajını ilgili form alanına eşler (bilinmiyorsa "name").
     */
    private function fieldForMessage(string $message): string
    {
        if (str_contains($message, 'eşik')) {
            return 'free_shipping_limit';
        }

        if (str_contains($message, 'ücret')) {
            return 'price';
        }

        if (str_contains($message, 'Kod')) {
            return 'code';
        }

        if (str_contains($message, 'Açıklama')) {
            return 'description';
        }

        if (str_contains($message, 'Sıra')) {
            return 'sort_order';
        }

        return 'name';
    }

    /**
     * Aktif/pasif geçişi başarısız olduğunda JSON veya yönlendirme döner.
     */
    private function toggleFailed(bool $asJson, string $message, int $status): Response
    {
        if ($asJson) {
            return $this->json(['success' => false, 'message' => $message], $status);
        }

        Session::flash('error', $message);

        return $this->redirect(self::LIST_PATH);
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
            $input[$field] = is_string($value) ? trim($value) : '';
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
        Session::flash('error', 'Kargo yöntemi bulunamadı.');

        return $this->redirect(self::LIST_PATH);
    }
}