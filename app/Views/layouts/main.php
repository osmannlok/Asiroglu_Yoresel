<?php

/**
 * Aşıroğlu Yöresel Market - Ana layout
 *
 * Değişkenler: $content (zorunlu, view çıktısı), $title, $metaDescription (isteğe bağlı)
 */

use App\Core\App;
use App\Core\Csrf;
use App\Core\Session;

$siteName  = (string) App::config('app.name', 'Aşıroğlu Yöresel Market');
$pageTitle = isset($title) && $title !== '' ? (string) $title : $siteName;
$pageDesc  = isset($metaDescription) ? (string) $metaDescription : '';

// Flash bildirimleri: tür => Bootstrap alert sınıfı
$flashTypes = ['success' => 'success', 'error' => 'danger', 'info' => 'info'];
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

            <button class="navbar-toggler" type="button"
                    data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                    aria-controls="mainNavbar" aria-expanded="false" aria-label="Menüyü aç/kapat">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page"
                           href="<?= $this->e($this->url('/')) ?>">Ana Sayfa</a>
                    </li>
                    <!-- Ürünler, Sepet ve Hesabım bağlantıları ilgili modüllerde eklenecek -->
                </ul>
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
</body>
</html>