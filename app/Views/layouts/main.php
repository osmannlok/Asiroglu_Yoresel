<?php

/**
 * Aşıroğlu Yöresel Market - Ana layout
 *
 * Değişkenler: $content (zorunlu, view çıktısı), $title, $metaDescription (isteğe bağlı)
 *
 * Menüde Ana Sayfa, Ürünler ve Sepet bağlantıları bulunur. Sepet rozeti ilk
 * açılışta sunucuda hesaplanır; sonrasında /sepet/ altına yapılan başarılı
 * POST yanıtlarındaki "count" değeriyle sayfa yenilenmeden güncellenir.
 * Giriş yapmış üyede "Siparişlerim" bağlantısı gösterilir (FR-055).
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url()
 *
 * Gereksinimler: FR-001, FR-003, FR-043, FR-045, FR-055, NFR-005.
 */
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Services\CartService;


$siteName  = (string) App::config('app.name', 'Aşıroğlu Yöresel Market');
$pageTitle = isset($title) && $title !== '' ? (string) $title : $siteName;
$pageDesc  = isset($metaDescription) ? (string) $metaDescription : '';

// Flash bildirimleri: tür => Bootstrap alert sınıfı
$flashTypes = ['success' => 'success', 'error' => 'danger', 'info' => 'info'];

// Sepet rozeti: veritabanı/oturum sorunu sayfayı çökertmemeli
try {
    $cartCount = max(0, (new CartService())->count());
} catch (Throwable $e) {
    error_log(sprintf(
        '[%s] layouts/main: sepet sayısı okunamadı: %s',
        date('Y-m-d H:i:s'),
        $e->getMessage()
    ));
    $cartCount = 0;
}

// Oturum açmış kullanıcı: veritabanı sorunu sayfayı çökertmemeli (ziyaretçi gibi gösterilir)
$authUserName = '';
$authIsStaff  = false;

try {
    $authUser = Auth::user();

    if ($authUser !== null) {
        $authUserName = trim((string) $authUser['first_name']);
        $authUserName = $authUserName !== '' ? $authUserName : 'Hesabım';
        $authIsStaff  = Auth::canAccessAdmin();
    }
} catch (Throwable $e) {
    error_log(sprintf(
        '[%s] layouts/main: kullanıcı okunamadı: %s',
        date('Y-m-d H:i:s'),
        $e->getMessage()
    ));
}

$isLoggedIn = $authUserName !== '';
$isGuestPage = in_array($currentPathForAuth = '/' . ltrim((string) explode('?', (string) ($_SERVER['REQUEST_URI'] ?? '/'), 2)[0], '/'), ['/giris', '/kayit'], true);

// Menüde işaretlenecek öğe: yalnızca istek yolundan türetilir (kullanıcı girdisi çıktıya basılmaz)
$currentPath = '/' . ltrim((string) explode('?', (string) ($_SERVER['REQUEST_URI'] ?? '/'), 2)[0], '/');

$isHome     = $currentPath === '/';
$isProducts = $currentPath === '/urunler' || str_starts_with($currentPath, '/urun/');
$isCart     = $currentPath === '/sepet';
$isOrders   = $currentPath === '/hesabim/siparisler' || str_starts_with($currentPath, '/hesabim/siparisler/'); // YENİ

// Arama kutusunda mevcut aramayı göster (yalnızca metin ise)
$searchValue = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 100) : '';

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($pageTitle) ?></title>
    <?php if ($pageDesc !== ''): ?>
        <meta name="description" content="<?= $this->e($pageDesc) ?>">
    <?php endif; ?>

    <!-- Fetch API istekleri için CSRF token -->
    <meta name="csrf-token" content="<?= $this->e(Csrf::token()) ?>">

    <!-- Bootstrap 5.3 (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= $this->e($this->url('/')) ?>">
                <?= $this->e($siteName) ?>
            </a>

            <!-- Mobilde sepet kısayolu: menü kapalıyken de görünür -->
            <a class="btn btn-outline-light btn-sm position-relative ms-auto me-2 d-lg-none"
               href="<?= $this->e($this->url('/sepet')) ?>" aria-label="Sepetim">
                Sepet
                <span class="badge text-bg-light ms-1<?= $cartCount > 0 ? '' : ' d-none' ?>"
                      data-cart-badge><?= $this->e($cartCount > 99 ? '99+' : (string) $cartCount) ?></span>
            </a>

            <button class="navbar-toggler" type="button"
                    data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                    aria-controls="mainNavbar" aria-expanded="false" aria-label="Menüyü aç/kapat">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link<?= $isHome ? ' active' : '' ?>"
                           <?= $isHome ? 'aria-current="page"' : '' ?>
                           href="<?= $this->e($this->url('/')) ?>">Ana Sayfa</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $isProducts ? ' active' : '' ?>"
                           <?= $isProducts ? 'aria-current="page"' : '' ?>
                           href="<?= $this->e($this->url('/urunler')) ?>">Ürünler</a>
                    </li>
                </ul>

                <form class="d-flex me-lg-3 mb-2 mb-lg-0" method="get" role="search"
                      action="<?= $this->e($this->url('/urunler')) ?>">
                    <label for="navSearch" class="visually-hidden">Ürün ara</label>
                    <input type="search" class="form-control form-control-sm me-2" id="navSearch" name="q"
                           value="<?= $this->e($searchValue) ?>" maxlength="100" placeholder="Ürün ara">
                    <button type="submit" class="btn btn-light btn-sm">Ara</button>
                </form>

                <!-- Kullanıcı alanı: misafir için Giriş/Kayıt, üye için Siparişlerim, ad ve Çıkış -->
                <ul class="navbar-nav align-items-lg-center me-lg-3 mb-2 mb-lg-0">
                    <?php if ($isLoggedIn): ?>
                        <?php if ($authIsStaff): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= $this->e($this->url('/admin')) ?>">Yönetim Paneli</a>
                            </li>
                        <?php endif; ?>
                        <!-- YENİ: Siparişlerim (FR-055) -->
                        <li class="nav-item">
                            <a class="nav-link<?= $isOrders ? ' active' : '' ?>"
                               <?= $isOrders ? 'aria-current="page"' : '' ?>
                               href="<?= $this->e($this->url('/hesabim/siparisler')) ?>">Siparişlerim</a>
                        </li>
                        <li class="nav-item">
                            <span class="navbar-text text-white me-lg-2"><?= $this->e($authUserName) ?></span>
                        </li>
                        <li class="nav-item">
                            <!-- Çıkış yalnızca POST ile yapılır (CSRF korumalı) -->
                            <form method="post" action="<?= $this->e($this->url('/cikis')) ?>" class="m-0">
                                <?= $this->csrfField() ?>
                                <button type="submit" class="btn btn-link nav-link px-lg-2">Çıkış</button>
                            </form>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $isGuestPage && str_ends_with($currentPath, '/giris') ? ' active' : '' ?>"
                               href="<?= $this->e($this->url('/giris')) ?>">Giriş</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?= $isGuestPage && str_ends_with($currentPath, '/kayit') ? ' active' : '' ?>"
                               href="<?= $this->e($this->url('/kayit')) ?>">Kayıt Ol</a>
                        </li>
                    <?php endif; ?>
                </ul>

                <!-- Masaüstü sepet bağlantısı -->
                <a class="btn btn-outline-light btn-sm d-none d-lg-inline-block<?= $isCart ? ' active' : '' ?>"
                   <?= $isCart ? 'aria-current="page"' : '' ?>
                   href="<?= $this->e($this->url('/sepet')) ?>">
                    Sepet
                    <span class="badge text-bg-light ms-1<?= $cartCount > 0 ? '' : ' d-none' ?>"
                          data-cart-badge><?= $this->e($cartCount > 99 ? '99+' : (string) $cartCount) ?></span>
                </a>
            </div>
        </div>
    </nav>
</header>

<main class="flex-grow-1">
    <?php
    // Flash bildirimleri (alert/confirm penceresi yerine Bootstrap alert)
    $flashHtml = '';
    foreach ($flashTypes as $type => $class) {
        $message = Session::getFlash($type);
        if (is_string($message) && $message !== '') {
            $flashHtml .= '<div class="alert alert-' . $class . ' alert-dismissible fade show" role="alert">'
                . $this->e($message)
                . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>'
                . '</div>';
        }
    }
    ?>
    <?php if ($flashHtml !== ''): ?>
        <div class="container pt-3"><?= $flashHtml ?></div>
    <?php endif; ?>

    <?= $content ?>
</main>

<footer class="bg-dark text-light py-4 mt-5">
    <div class="container text-center small">
        <p class="mb-1 fw-semibold"><?= $this->e($siteName) ?></p>
        <p class="mb-0 text-secondary">&copy; <?= $this->e(date('Y')) ?> Tüm hakları saklıdır.</p>
    </div>
</footer>

<!-- Bootstrap 5.3 JS (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Sepet rozeti: sayfa yenilenmeden güncellenir (FR-043)
    (function () {
        'use strict';

        var badges = document.querySelectorAll('[data-cart-badge]');
        var summaryUrl = <?= json_encode($this->url('/sepet/ozet'), $jsonFlags) ?>;

        if (badges.length === 0) {
            return;
        }

        // Rozet metni textContent ile yazılır (XSS güvenli)
        function setCount(value) {
            var count = parseInt(value, 10);

            if (isNaN(count) || count < 0) {
                return;
            }

            badges.forEach(function (badge) {
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.classList.toggle('d-none', count === 0);
            });
        }

        // Sayfalardaki mevcut Fetch çağrılarına dokunmadan: /sepet/ altına yapılan
        // başarılı POST yanıtındaki "count" değeri rozete yansıtılır.
        if (typeof window.fetch === 'function') {
            var originalFetch = window.fetch;

            window.fetch = function (input, init) {
                var request = originalFetch.apply(this, arguments);
                var url = typeof input === 'string' ? input : ((input && input.url) || '');
                var method = String((init && init.method) || (input && input.method) || 'GET').toUpperCase();

                if (method === 'POST' && url.indexOf('/sepet/') !== -1) {
                    request
                        .then(function (response) {
                            return response.clone().json();
                        })
                        .then(function (data) {
                            if (data && data.success === true && typeof data.count !== 'undefined') {
                                setCount(data.count);
                            }
                        })
                        .catch(function () {
                            // Yanıt okunamazsa rozet olduğu gibi kalır
                        });
                }

                return request;
            };
        }

        // Geri/ileri tuşuyla önbellekten dönülürse rozeti sunucudan tazele
        window.addEventListener('pageshow', function (event) {
            if (!event.persisted) {
                return;
            }

            fetch(summaryUrl, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data && data.success === true) {
                        setCount(data.count);
                    }
                })
                .catch(function () {
                    // Özet alınamazsa mevcut rozet korunur
                });
        });
    })();
</script>
</body>
</html>