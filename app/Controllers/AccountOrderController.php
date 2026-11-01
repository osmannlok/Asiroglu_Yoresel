<?php

/**
 * Aşıroğlu Yöresel Market - Hesabım: sipariş listesi ve detayı
 *
 * GET /hesabim/siparisler        -> üyenin siparişleri (sayfalı)
 * GET /hesabim/siparisler/{id}   -> sipariş detayı (kalemler, adres, durum geçmişi)
 *
 * Kararlar (DECISIONS.md, Order modeli / Müşteri Auth):
 * - Yalnızca giriş yapmış üyeye açıktır. Misafir /giris sayfasına, dönüş
 *   adresiyle ("next") yönlendirilir; "next" AuthController::resolveNext()
 *   kurallarına uyan sabit bir yoldur.
 * - Sipariş her zaman Order::findForUser() ile okunur (user_id eşleşmeli);
 *   başka kullanıcının sipariş ID'si "bulunamadı" ile aynı sonucu verir (G-05).
 * - Durum geçmişinden müşteriye yalnızca durum, not ve tarih verilir;
 *   değişikliği yapan personelin adı view'a taşınmaz.
 * - Ödeme yöntemi orders tablosunda saklanmaz (payments modeli gelene kadar);
 *   bu sayfa yalnızca payment_status gösterir.
 * - Yalnızca GET; CSRF gerektiren işlem yoktur. Çıktı kaçırma view'lardadır.
 *
 * Gereksinimler: FR-055, FR-069, G-05, NFR-003, NFR-004.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Order;

final class AccountOrderController extends Controller
{
    /** Sipariş listesinin yolu */
    private const LIST_PATH = '/hesabim/siparisler';

    /** Giriş sayfasının yolu */
    private const LOGIN_PATH = '/giris';

    /** Listede sayfa başına sipariş */
    private const PER_PAGE = 10;

    /** Sayfa numarası üst sınırı (aşırı büyük değerlere karşı) */
    private const MAX_PAGE = 100000;

    /** Sipariş durumu => görünen ad (FR-066) */
    public const STATUS_LABELS = [
        'pending'          => 'Beklemede',
        'awaiting_payment' => 'Ödeme Bekliyor',
        'paid'             => 'Ödendi',
        'preparing'        => 'Hazırlanıyor',
        'shipped'          => 'Kargoya Verildi',
        'delivered'        => 'Teslim Edildi',
        'cancelled'        => 'İptal Edildi',
        'payment_failed'   => 'Ödeme Başarısız',
        'returned'         => 'İade Edildi',
    ];

    /** Ödeme durumu => görünen ad */
    public const PAYMENT_STATUS_LABELS = [
        'unpaid'             => 'Ödenmedi',
        'paid'               => 'Ödendi',
        'failed'             => 'Başarısız',
        'refunded'           => 'İade Edildi',
        'partially_refunded' => 'Kısmen İade Edildi',
    ];

    // -------------------------------------------------------------------------
    // Liste
    // -------------------------------------------------------------------------

    /**
     * Üyenin sipariş listesi: GET /hesabim/siparisler
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $userId = Auth::id();

        if ($userId === null) {
            return $this->requireLogin(self::LIST_PATH);
        }

        $page = $request->query('page', 1);
        $page = is_scalar($page) && preg_match('/^[1-9]\d{0,5}$/', (string) $page) === 1
            ? min(self::MAX_PAGE, (int) $page)
            : 1;

        $result = (new Order())->paginateByUser($userId, $page, self::PER_PAGE);

        return $this->view('account/orders/index', [
            'title'               => 'Siparişlerim',
            'result'              => $result,
            'statusLabels'        => self::STATUS_LABELS,
            'paymentStatusLabels' => self::PAYMENT_STATUS_LABELS,
            'basePath'            => self::LIST_PATH,
        ]);
    }

    // -------------------------------------------------------------------------
    // Detay
    // -------------------------------------------------------------------------

    /**
     * Sipariş detayı: GET /hesabim/siparisler/{id}
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params = []): Response
    {
        $userId = Auth::id();
        $id     = (int) ($params['id'] ?? 0);

        if ($userId === null) {
            return $this->requireLogin($id > 0 ? self::LIST_PATH . '/' . $id : self::LIST_PATH);
        }

        $orders = new Order();
        $order  = $id > 0 ? $orders->findForUser($id, $userId) : null;

        // Başka kullanıcının siparişi ile olmayan sipariş aynı yanıtı verir (G-05)
        if ($order === null) {
            Session::flash('error', 'Sipariş bulunamadı.');

            return $this->redirect(self::LIST_PATH);
        }

        $orderId = (int) $order['id'];

        return $this->view('account/orders/show', [
            'title'               => 'Sipariş ' . (string) $order['order_number'],
            'order'               => $order,
            'items'               => $orders->getItems($orderId),
            'address'             => $this->decodeSnapshot((string) $order['shipping_address_snapshot']),
            'history'             => $this->customerHistory($orders->getHistory($orderId)),
            'statusLabels'        => self::STATUS_LABELS,
            'paymentStatusLabels' => self::PAYMENT_STATUS_LABELS,
            'listPath'            => self::LIST_PATH,
        ]);
    }

    // -------------------------------------------------------------------------
    // Dahili yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Misafiri giriş sayfasına yönlendirir; girişten sonra $next adresine döner.
     * $next sabit, kullanıcı girdisi içermeyen bir yoldur (yalnızca rakam ve sabit metin).
     */
    private function requireLogin(string $next): Response
    {
        Session::flash('info', 'Siparişlerinizi görmek için giriş yapın.');

        return $this->redirect(self::LOGIN_PATH . '?' . http_build_query(['next' => $next]));
    }

    /**
     * Durum geçmişini müşteriye gösterilecek alanlarla sınırlar.
     * Değişikliği yapan personelin adı ve kimliği view'a taşınmaz.
     *
     * @param list<array<string, mixed>> $history Order::getHistory() satırları
     *
     * @return list<array{status: string, note: string, created_at: string}>
     */
    private function customerHistory(array $history): array
    {
        $result = [];

        foreach ($history as $row) {
            $result[] = [
                'status'     => (string) $row['new_status'],
                'note'       => trim((string) ($row['note'] ?? '')),
                'created_at' => (string) $row['created_at'],
            ];
        }

        return $result;
    }

    /**
     * Adres anlık kopyasını (JSON) diziye çevirir; bozuksa boş dizi döner.
     *
     * @return array<string, mixed>
     */
    private function decodeSnapshot(string $json): array
    {
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }
}