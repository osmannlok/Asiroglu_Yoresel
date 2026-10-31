<?php

/**
 * Aşıroğlu Yöresel Market - Ana sayfa controller'ı
 *
 * Ana sayfayı hazırlar. Şimdilik yalnızca karşılama içeriği gösterir;
 * öne çıkan ürünler ve kategoriler ürün modülüyle eklenecektir.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class HomeController extends Controller
{
    /**
     * Ana sayfa: GET /
     *
     * @param array<string, string> $params Route parametreleri (bu route'ta yok)
     */
    public function index(Request $request, array $params = []): Response
    {
        return $this->view('home/index', [
            'title'           => 'Aşıroğlu Yöresel Market',
            'metaDescription' => 'Türkiye\'nin yöresel lezzetleri kapınızda. '
                . 'Doğal ve yöresel gıda ürünlerini güvenle sipariş edin.',
        ]);
    }
}