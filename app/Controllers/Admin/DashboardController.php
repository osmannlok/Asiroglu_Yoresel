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
 * Şimdilik yalnızca kategori ve marka sayıları gösterilir. Ürün, sipariş
 * ve stok özetleri ilgili modeller yazıldığında eklenecektir.
 *
 * Gereksinimler: FR-090, FR-091.
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

final class DashboardController extends Controller
{
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

        $user = Auth::user();

        return $this->view(
            'admin/dashboard/index',
            [
                'title'      => 'Genel Bakış',
                'activeMenu' => 'dashboard',
                'firstName'  => $user === null ? '' : (string) $user['first_name'],
                'stats'      => [
                    'categories' => (new Category())->count(),
                    'brands'     => (new Brand())->count(),
                ],
            ],
            'layouts/admin'
        );
    }
}