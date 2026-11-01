<?php

/**
 * Aşıroğlu Yöresel Market - Müşteri giriş sayfası view'ı
 *
 * AuthController::showLogin() tarafından render edilir ve layouts/main.php
 * içine yerleştirilir. Hata ve başarı mesajları layout'taki flash alanında
 * gösterilir; bu view mesaj basmaz.
 *
 * Değişkenler (hepsi isteğe bağlı; yoksa boş kabul edilir):
 *   $oldEmail     (string) başarısız denemeden dönen e-posta
 *   $next         (string) giriş sonrası dönülecek güvenli site içi yol
 *   $registerPath (string) kayıt sayfasının yolu
 *   $loginPath    (string) formun gönderileceği yol
 *
 * Şifre hiçbir zaman geri doldurulmaz.
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url(), $this->csrfField()
 *
 * Gereksinimler: FR-050, NFR-003, NFR-005.
 */

declare(strict_types=1);

$oldEmail     = isset($oldEmail) ? (string) $oldEmail : '';
$next         = isset($next) ? (string) $next : '';
$registerPath = isset($registerPath) ? (string) $registerPath : '/kayit';
$loginPath    = isset($loginPath) ? (string) $loginPath : '/giris';
?>
<div class="container py-4 py-lg-5">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 fw-bold mb-3">Giriş Yap</h1>

                    <form id="loginForm" method="post"
                          action="<?= $this->e($this->url($loginPath)) ?>" novalidate>
                        <?= $this->csrfField() ?>

                        <?php if ($next !== ''): ?>
                            <input type="hidden" name="next" value="<?= $this->e($next) ?>">
                        <?php endif; ?>

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

                    <hr class="my-4">

                    <p class="text-center text-muted small mb-0">
                        Hesabınız yok mu?
                        <a href="<?= $this->e($this->url($registerPath)) ?>" class="fw-semibold">Kayıt olun</a>
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    // Gönderim sırasında butonu devre dışı bırakır (çift tıklamayı önler)
    (function () {
        'use strict';

        var form = document.getElementById('loginForm');
        var button = document.getElementById('loginButton');

        if (!form || !button) {
            return;
        }

        var originalText = button.textContent;

        form.addEventListener('submit', function () {
            button.disabled = true;
            button.textContent = 'Giriş yapılıyor...';
        });

        // Tarayıcının geri tuşuyla dönüldüğünde buton eski haline gelir
        window.addEventListener('pageshow', function () {
            button.disabled = false;
            button.textContent = originalText;
        });
    })();
</script>