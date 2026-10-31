<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli giriş sayfası
 *
 * Admin\AuthController::showLogin() tarafından layout olmadan render edilir,
 * bu yüzden tam bir HTML sayfasıdır.
 *
 * Değişkenler (hepsi isteğe bağlı; yoksa boş kabul edilir):
 *   $title, $oldEmail, $errorMessage, $successMessage, $infoMessage
 *
 * Kullanılabilir yardımcılar: $this->e() (XSS kaçırma), $this->url() (site içi adres),
 * $this->csrfField() (CSRF gizli alanı)
 *
 * Gereksinimler: FR-090, G-04, NFR-003, NFR-005.
 */

declare(strict_types=1);

$pageTitle      = isset($title) && $title !== '' ? (string) $title : 'Yönetim Paneli Girişi';
$oldEmail       = isset($oldEmail) ? (string) $oldEmail : '';
$errorMessage   = isset($errorMessage) ? (string) $errorMessage : '';
$successMessage = isset($successMessage) ? (string) $successMessage : '';
$infoMessage    = isset($infoMessage) ? (string) $infoMessage : '';

// Mesaj türü => Bootstrap alert sınıfı (boş mesajlar gösterilmez)
$alerts = [
    'danger'  => $errorMessage,
    'success' => $successMessage,
    'info'    => $infoMessage,
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $this->e($pageTitle) ?> | Aşıroğlu Yöresel Market</title>

    <!-- Bootstrap 5.3 (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="d-flex align-items-center min-vh-100" style="background-color: #f7f1e3;">

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">

            <div class="text-center mb-4">
                <h1 class="h3 fw-bold text-success mb-1">Aşıroğlu Yöresel Market</h1>
                <p class="text-muted mb-0">Yönetim Paneli</p>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Giriş Yap</h2>

                    <?php foreach ($alerts as $type => $message): ?>
                        <?php if ($message !== ''): ?>
                            <div class="alert alert-<?= $this->e($type) ?>" role="alert">
                                <?= $this->e($message) ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <form id="loginForm" method="post"
                          action="<?= $this->e($this->url('/admin/giris')) ?>" novalidate>
                        <?= $this->csrfField() ?>

                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= $this->e($oldEmail) ?>"
                                   maxlength="191" autocomplete="username"
                                   autofocus required>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Şifre</label>
                            <input type="password" class="form-control" id="password" name="password"
                                   maxlength="255" autocomplete="current-password" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg" id="loginButton">
                                Giriş Yap
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <p class="text-center text-muted small mt-3 mb-0">
                <a href="<?= $this->e($this->url('/')) ?>" class="link-secondary">Siteye dön</a>
            </p>

        </div>
    </div>
</main>

<script>
    // Gönderim sırasında butonu devre dışı bırakır (çift tıklamayı önler)
    (function () {
        'use strict';

        var form = document.getElementById('loginForm');
        var button = document.getElementById('loginButton');

        if (!form || !button) {
            return;
        }

        form.addEventListener('submit', function () {
            button.disabled = true;
            button.textContent = 'Giriş yapılıyor...';
        });

        // Tarayıcının geri tuşuyla dönüldüğünde buton eski haline gelir
        window.addEventListener('pageshow', function () {
            button.disabled = false;
            button.textContent = 'Giriş Yap';
        });
    })();
</script>

<!-- Bootstrap 5.3 JS (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>