<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli ana sayfa controller'ı
 *
 * GET /admin -> genel bakış sayfası
 *
 * Erişim denetimi route grubundaki AdminMiddleware ile yapılır. Burada
 * ikinci bir savunma katmanı olarak yetki yeniden kontrol edilir; route
 * yanlışlıkla korumasız bırakılsa bile panel içeriği açılmaz.
 *
 * Gösterilenler: kategori, marka ve ürün sayıları ile kritik stok listesi.
 * Sipariş özetleri ilgili modeller yazıldığında eklenecektir.
 *
 * Not: Kritik stok listesi yalnızca varyantsız ürünleri kapsar; varyantlı
 * ürünlerin stoğu varyant düzeyinde izlenir (Product::getLowStock()).
 *
 * Gereksinimler: FR-024, FR-090, FR-091, FR-097.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\AdminMiddleware;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;

final class DashboardController extends Controller
{
    /** Kritik stok listesinde gösterilecek en fazla ürün */
    private const LOW_STOCK_LIMIT = 10;

    /**
     * Genel bakış sayfası: GET /admin
     *
     * @param array<string, string> $params Route parametreleri (bu route'ta yok)
     */
    public function index(Request $request, array $params = []): Response
    {
        // Savunma katmanı: middleware atlanmış olsa bile yetkisiz kullanıcı içeri giremez
        if (!Auth::canAccessAdmin()) {
            return $this->redirect(AdminMiddleware::LOGIN_PATH);
        }

        $user     = Auth::user();
        $products = new Product();

        return $this->view(
            'admin/dashboard/index',
            [
                'title'      => 'Genel Bakış',
                'activeMenu' => 'dashboard',
                'firstName'  => $user === null ? '' : (string) $user['first_name'],
                'stats'      => [
                    'categories' => (new Category())->count(),
                    'brands'     => (new Brand())->count(),
                    'products'   => $products->count(),
                ],
                'lowStock'   => $products->getLowStock(self::LOW_STOCK_LIMIT),
                'stockPath'  => '/admin/stok',
            ],
            'layouts/admin'
        );
    }
}