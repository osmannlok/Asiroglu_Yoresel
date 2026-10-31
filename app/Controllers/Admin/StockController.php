<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli stok controller'ı
 *
 * GET  /admin/stok                -> stok kalemleri (ürün/varyant) ve kritik stok uyarısı
 * GET  /admin/stok/hareket        -> manuel stok hareketi formu
 * POST /admin/stok/hareket        -> hareketi kaydet (stok + hareket aynı transaction)
 * GET  /admin/stok/gecmis         -> hareket geçmişi (filtre + sayfalama)
 *
 * Kararlar:
 * - Erişim denetimi route grubundaki AdminMiddleware ile yapılır; burada
 *   ikinci savunma katmanı olarak yetki yeniden kontrol edilir.
 * - POST isteklerinin CSRF doğrulaması App::handle() içinde yapılır.
 * - Formdan yalnızca manuel türler kabul edilir (incoming, outgoing, return,
 *   correction). 'reservation' sistem kaynaklıdır.
 * - Kalem tek alanda gelir: "p12" (varyantsız ürün) veya "p12v34" (varyant).
 *   Ürün/varyant ilişkisi StockMovement::record() içinde yeniden doğrulanır.
 * - İşlem nedeni zorunludur (FR-026). Model kural hataları (yetersiz stok,
 *   varyantlı ürüne varyantsız hareket vb.) alan hatası olarak gösterilir.
 * - Girdiler Validator'dan geçer; çıktı kaçırma view'lardadır.
 *
 * Gereksinimler: FR-023, FR-024, FR-026, NFR-003, NFR-004.
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
use App\Models\StockMovement;
use InvalidArgumentException;

final class StockController extends Controller
{
    /** Stok kalemleri sayfasının yolu */
    private const LIST_PATH = '/admin/stok';

    /** Hareket formunun yolu */
    private const FORM_PATH = '/admin/stok/hareket';

    /** Hareket geçmişinin yolu */
    private const HISTORY_PATH = '/admin/stok/gecmis';

    /** Geçmişte sayfa başına kayıt */
    private const PER_PAGE = 20;

    /** Arama metninin en fazla uzunluğu */
    private const MAX_SEARCH_LENGTH = 100;

    /** Formdan kabul edilen manuel hareket türleri */
    private const MANUAL_TYPES = ['incoming', 'outgoing', 'return', 'correction'];

    /** Formdan okunan metin alanları */
    private const TEXT_FIELDS = ['item', 'movement_type', 'quantity', 'note'];

    // -------------------------------------------------------------------------
    // Stok kalemleri
    // -------------------------------------------------------------------------

    /**
     * Stok kalemleri: GET /admin/stok
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $search = $request->query('q', '');
        $search = is_string($search) ? mb_substr(trim($search), 0, self::MAX_SEARCH_LENGTH) : '';

        return $this->view(
            'admin/stock/index',
            [
                'title'       => 'Stok',
                'activeMenu'  => 'stock',
                'items'       => (new StockMovement())->getStockItems($search === '' ? null : $search),
                'search'      => $search,
                'formPath'    => self::FORM_PATH,
                'historyPath' => self::HISTORY_PATH,
                'listPath'    => self::LIST_PATH,
            ],
            'layouts/admin'
        );
    }

    // -------------------------------------------------------------------------
    // Hareket formu
    // -------------------------------------------------------------------------

    /**
     * Manuel stok hareketi formu: GET /admin/stok/hareket
     *
     * Ön seçim için "?urun=ID" ve isteğe bağlı "&varyant=ID" kullanılır.
     *
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $values = array_fill_keys(self::TEXT_FIELDS, '');

        $values['movement_type'] = 'incoming';

        $productId = (int) $request->query('urun', 0);
        $variantId = (int) $request->query('varyant', 0);

        if ($productId > 0) {
            $values['item'] = 'p' . $productId . ($variantId > 0 ? 'v' . $variantId : '');
        }

        // Başarısız denemeden dönülüyorsa kullanıcının girdiği değerler korunur
        $old = Session::getFlash('old');

        if (is_array($old)) {
            foreach (self::TEXT_FIELDS as $field) {
                if (isset($old[$field]) && is_string($old[$field])) {
                    $values[$field] = $old[$field];
                }
            }
        }

        $errors = Session::getFlash('form_errors');

        return $this->view(
            'admin/stock/form',
            [
                'title'       => 'Stok Hareketi',
                'activeMenu'  => 'stock',
                'values'      => $values,
                'errors'      => is_array($errors) ? $errors : [],
                'items'       => (new StockMovement())->getStockItems(),
                'types'       => self::MANUAL_TYPES,
                'formAction'  => self::FORM_PATH,
                'listPath'    => self::LIST_PATH,
                'historyPath' => self::HISTORY_PATH,
            ],
            'layouts/admin'
        );
    }

    /**
     * Hareketi kaydeder: POST /admin/stok/hareket
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $input = $this->readInput($request);

        $validator = Validator::make(
            $input,
            [
                'item'          => 'required|string|max:30|regex:/^p[1-9]\d{0,9}(v[1-9]\d{0,9})?$/',
                'movement_type' => 'required|in:' . implode(',', self::MANUAL_TYPES),
                'quantity'      => 'required|integer|min:0|max:10000000',
                'note'          => 'required|string|max:500',
            ],
            [
                'item'          => 'Ürün / varyant',
                'movement_type' => 'Hareket türü',
                'quantity'      => 'Miktar',
                'note'          => 'İşlem nedeni',
            ]
        );

        $errors = $validator->errors();
        $data   = $validator->validated();

        if ($errors !== []) {
            return $this->backWithErrors($input, $errors);
        }

        [$productId, $variantId] = $this->parseItem((string) $data['item']);

        try {
            (new StockMovement())->record(
                $productId,
                $variantId,
                (string) $data['movement_type'],
                (int) $data['quantity'],
                (string) $data['note'],
                Auth::id()
            );
        } catch (InvalidArgumentException $e) {
            // Model kuralları kullanıcıya gösterilebilir Türkçe mesajlardır
            $field = str_contains($e->getMessage(), 'Miktar')
                || str_contains($e->getMessage(), 'stok')
                || str_contains($e->getMessage(), 'Stok')
                ? 'quantity'
                : 'item';

            return $this->backWithErrors($input, [$field => $e->getMessage()]);
        }

        Session::flash('success', 'Stok hareketi kaydedildi.');

        return $this->redirect(self::HISTORY_PATH);
    }

    // -------------------------------------------------------------------------
    // Geçmiş
    // -------------------------------------------------------------------------

    /**
     * Hareket geçmişi: GET /admin/stok/gecmis
     *
     * Filtreler: ?urun=ID, ?varyant=ID, ?tur=incoming|outgoing|...
     *
     * @param array<string, string> $params
     */
    public function history(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $page = max(1, (int) $request->query('page', 1));

        $productId = (int) $request->query('urun', 0);
        $productId = $productId > 0 ? $productId : null;

        $variantId = (int) $request->query('varyant', 0);
        $variantId = $variantId > 0 ? $variantId : null;

        $type = $request->query('tur', '');
        $type = is_string($type) && in_array($type, StockMovement::TYPES, true) ? $type : null;

        $result = (new StockMovement())->paginate(
            $page,
            self::PER_PAGE,
            $productId,
            $variantId,
            $type
        );

        return $this->view(
            'admin/stock/history',
            [
                'title'       => 'Stok Hareketleri',
                'activeMenu'  => 'stock',
                'result'      => $result,
                'productId'   => $productId,
                'variantId'   => $variantId,
                'type'        => $type ?? '',
                'types'       => StockMovement::TYPES,
                'formPath'    => self::FORM_PATH,
                'listPath'    => self::LIST_PATH,
                'historyPath' => self::HISTORY_PATH,
            ],
            'layouts/admin'
        );
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * "p12" veya "p12v34" biçimindeki kalem değerini [ürün, varyant] çiftine çevirir.
     * Biçim Validator'da doğrulanmıştır.
     *
     * @return array{0: int, 1: int|null}
     */
    private function parseItem(string $item): array
    {
        if (preg_match('/^p(\d+)(?:v(\d+))?$/', $item, $matches) !== 1) {
            throw new InvalidArgumentException('Ürün / varyant geçersiz.');
        }

        return [
            (int) $matches[1],
            isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : null,
        ];
    }

    /**
     * Hata durumunda eski girdiyi ve hataları flash'a yazıp forma döner.
     *
     * @param array<string, string> $input
     * @param array<string, string> $errors
     */
    private function backWithErrors(array $input, array $errors): Response
    {
        Session::flash('error', 'Lütfen formdaki hataları düzeltin.');
        Session::flash('form_errors', $errors);
        Session::flash('old', $input);

        return $this->redirect(self::FORM_PATH);
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

    /**
     * Savunma katmanı: panele giremeyen kullanıcıyı giriş sayfasına gönderir.
     */
    private function guard(): ?Response
    {
        return Auth::canAccessAdmin() ? null : $this->redirect(AdminMiddleware::LOGIN_PATH);
    }
}