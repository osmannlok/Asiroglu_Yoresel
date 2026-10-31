<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli layout'u
 *
 * Controller::view($view, $data, 'layouts/admin') ile kullanılır.
 *
 * Değişkenler:
 *   $content    (zorunlu) view çıktısı; kaçırılmadan basılır, içindeki dinamik
 *               değerlerden ilgili view sorumludur
 *   $title      (isteğe bağlı) sayfa başlığı
 *   $activeMenu (isteğe bağlı) menüde işaretlenecek öğenin anahtarı
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url(), $this->csrfField()
 *
 * Gereksinimler: FR-090, FR-091, NFR-003, NFR-005.
 */

use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;

$siteName   = (string) App::config('app.name', 'Aşıroğlu Yöresel Market');
$pageTitle  = isset($title) && $title !== '' ? (string) $title : 'Yönetim Paneli';
$activeMenu = isset($activeMenu) ? (string) $activeMenu : '';

// Giriş yapan kullanıcı (şifre hash'i ve token içermez)
$currentUser = Auth::user();
$userName    = $currentUser === null
    ? ''
    : trim((string) $currentUser['first_name'] . ' ' . (string) $currentUser['last_name']);
$isAdmin     = Auth::isAdmin();

// Yan menü: anahtar => [etiket, yol, yalnızca 'admin' mi?]
// Yeni modüller yazıldıkça satır eklenir. Menüde gizlemek yetki kontrolü
// değildir; asıl denetim route ve controller katmanında yapılır.
$menuItems = [
    'dashboard'  => ['Genel Bakış', '/admin', false],
    'categories' => ['Kategoriler', '/admin/kategoriler', false],
    'brands'     => ['Markalar', '/admin/markalar', false],
];

// Flash bildirimleri: tür => Bootstrap alert sınıfı
$flashTypes = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $this->e($pageTitle) ?> | Yönetim Paneli</title>

    <!-- Fetch API istekleri için CSRF token -->
    <meta name="csrf-token" content="<?= $this->e(Csrf::token()) ?>">

    <!-- Bootstrap 5.3 (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <style>
        .admin-sidebar { background-color: #2f4a35; width: 260px; }
        .admin-sidebar .nav-link { color: #dfe8e0; border-radius: .375rem; }
        .admin-sidebar .nav-link:hover { background-color: rgba(255, 255, 255, .08); color: #fff; }
        .admin-sidebar .nav-link.active { background-color: #198754; color: #fff; }
        @media (min-width: 992px) {
            .admin-sidebar { position: sticky; top: 0; height: 100vh; overflow-y: auto; }
        }
    </style>
</head>
<body class="bg-light">

<div class="d-lg-flex min-vh-100">

    <!-- Yan menü: masaüstünde sabit, mobilde offcanvas -->
    <aside class="offcanvas-lg offcanvas-start admin-sidebar flex-shrink-0 text-white"
           tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
        <div class="offcanvas-header border-bottom border-secondary-subtle">
            <span class="fw-bold" id="adminSidebarLabel"><?= $this->e($siteName) ?></span>
            <button type="button" class="btn-close btn-close-white"
                    data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar"
                    aria-label="Menüyü kapat"></button>
        </div>

        <div class="offcanvas-body d-flex flex-column p-3">
            <a class="d-none d-lg-block text-white text-decoration-none fw-bold fs-5 mb-1"
               href="<?= $this->e($this->url('/admin')) ?>">
                <?= $this->e($siteName) ?>
            </a>
            <p class="d-none d-lg-block small text-white-50 mb-3">Yönetim Paneli</p>

            <nav aria-label="Yönetim menüsü">
                <ul class="nav nav-pills flex-column gap-1">
                    <?php foreach ($menuItems as $key => [$label, $path, $adminOnly]): ?>
                        <?php if ($adminOnly && !$isAdmin) { continue; } ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $activeMenu === $key ? ' active' : '' ?>"
                               <?= $activeMenu === $key ? 'aria-current="page"' : '' ?>
                               href="<?= $this->e($this->url($path)) ?>">
                                <?= $this->e($label) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="mt-auto pt-4">
                <a class="small text-white-50 text-decoration-none"
                   href="<?= $this->e($this->url('/')) ?>">&larr; Siteye dön</a>
            </div>
        </div>
    </aside>

    <!-- Sağ taraf: üst bar + içerik -->
    <div class="flex-grow-1 d-flex flex-column min-w-0" style="min-width: 0;">

        <header class="navbar bg-white border-bottom shadow-sm px-3">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary d-lg-none" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#adminSidebar"
                        aria-controls="adminSidebar" aria-label="Menüyü aç">
                    &#9776;
                </button>
                <span class="fw-semibold"><?= $this->e($pageTitle) ?></span>
            </div>

            <div class="d-flex align-items-center gap-3">
                <?php if ($userName !== ''): ?>
                    <span class="small text-muted d-none d-sm-inline"><?= $this->e($userName) ?></span>
                <?php endif; ?>

                <!-- Çıkış yalnızca POST ile yapılır (CSRF korumalı) -->
                <form method="post" action="<?= $this->e($this->url('/admin/cikis')) ?>" class="m-0">
                    <?= $this->csrfField() ?>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Çıkış</button>
                </form>
            </div>
        </header>

        <main class="flex-grow-1 p-3 p-lg-4">
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
            <?= $flashHtml ?>

            <?= $content ?>
        </main>

        <footer class="border-top bg-white small text-muted px-3 py-2">
            &copy; <?= $this->e(date('Y')) ?> <?= $this->e($siteName) ?>
        </footer>
    </div>
</div>

<!-- Ortak silme/onay modalı: data-confirm-form="formId" taşıyan butonlar açar -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="confirmModalLabel">İşlemi onaylayın</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body" id="confirmModalMessage">Bu işlemi yapmak istediğinize emin misiniz?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-danger" id="confirmModalAccept">Onayla</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast bildirimleri: window.showToast(mesaj, tür) ile kullanılır -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" aria-live="polite"></div>

<!-- Bootstrap 5.3 JS (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    (function () {
        'use strict';

        // --- Toast: window.showToast('Kaydedildi.', 'success') ------------------
        // Tür: success | danger | warning | info. Metin textContent ile yazılır (XSS güvenli).
        window.showToast = function (message, type) {
            var container = document.getElementById('toastContainer');
            if (!container || !window.bootstrap) {
                return;
            }

            var allowed = ['success', 'danger', 'warning', 'info'];
            var color = allowed.indexOf(type) === -1 ? 'info' : type;

            var toast = document.createElement('div');
            toast.className = 'toast align-items-center text-bg-' + color + ' border-0';
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');

            var wrapper = document.createElement('div');
            wrapper.className = 'd-flex';

            var body = document.createElement('div');
            body.className = 'toast-body';
            body.textContent = String(message);

            var close = document.createElement('button');
            close.type = 'button';
            close.className = 'btn-close btn-close-white me-2 m-auto';
            close.setAttribute('data-bs-dismiss', 'toast');
            close.setAttribute('aria-label', 'Kapat');

            wrapper.appendChild(body);
            wrapper.appendChild(close);
            toast.appendChild(wrapper);
            container.appendChild(toast);

            toast.addEventListener('hidden.bs.toast', function () {
                toast.remove();
            });

            new bootstrap.Toast(toast, { delay: 5000 }).show();
        };

        // --- Onay modalı: <button type="button" data-confirm-form="silForm5"
        //                          data-confirm-message="Kategori silinsin mi?"> ---
        var modalElement = document.getElementById('confirmModal');
        var messageElement = document.getElementById('confirmModalMessage');
        var acceptButton = document.getElementById('confirmModalAccept');

        if (!modalElement || !messageElement || !acceptButton || !window.bootstrap) {
            return;
        }

        var modal = new bootstrap.Modal(modalElement);
        var pendingForm = null;

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-confirm-form]');
            if (!trigger) {
                return;
            }

            var form = document.getElementById(trigger.getAttribute('data-confirm-form'));
            if (!form) {
                return;
            }

            event.preventDefault();
            pendingForm = form;
            messageElement.textContent = trigger.getAttribute('data-confirm-message')
                || 'Bu işlemi yapmak istediğinize emin misiniz?';
            modal.show();
        });

        acceptButton.addEventListener('click', function () {
            if (!pendingForm) {
                return;
            }

            acceptButton.disabled = true; // Çift tıklamayı önle
            pendingForm.submit();
        });

        modalElement.addEventListener('hidden.bs.modal', function () {
            pendingForm = null;
            acceptButton.disabled = false;
        });
    })();
</script>
</body>
</html>