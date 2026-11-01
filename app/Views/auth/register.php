<?php

/**
 * Aşıroğlu Yöresel Market - Müşteri kayıt sayfası view'ı
 *
 * AuthController::showRegister() tarafından render edilir ve layouts/main.php
 * içine yerleştirilir. Genel hata/başarı mesajları layout'taki flash alanında
 * gösterilir; bu view yalnızca alan bazlı hataları basar.
 *
 * Değişkenler:
 *   $values     (array) form değerleri: first_name, last_name, email, phone
 *   $errors     (array) alan => hata mesajı
 *   $formAction (string) formun gönderileceği yol
 *   $loginPath  (string) giriş sayfasının yolu
 *
 * Şifre alanları hiçbir zaman geri doldurulmaz.
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url(), $this->csrfField()
 *
 * Gereksinimler: FR-050, NFR-003, NFR-004, NFR-005.
 */

declare(strict_types=1);

$values     = isset($values) && is_array($values) ? $values : [];
$errors     = isset($errors) && is_array($errors) ? $errors : [];
$formAction = isset($formAction) ? (string) $formAction : '/kayit';
$loginPath  = isset($loginPath) ? (string) $loginPath : '/giris';

/** Form değerini metin olarak döndürür. */
$val = static fn (string $key): string => isset($values[$key]) ? (string) $values[$key] : '';

/** Alan hatalıysa Bootstrap sınıfını döndürür. */
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' is-invalid' : '';

/** Alan hata mesajını (varsa) döndürür. */
$error = static fn (string $key): string => isset($errors[$key]) ? (string) $errors[$key] : '';
?>
<div class="container py-4 py-lg-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-9 col-lg-7 col-xl-6">

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 fw-bold mb-1">Kayıt Ol</h1>
                    <p class="text-muted small mb-4">
                        Hesap oluşturarak siparişlerinizi takip edebilir ve adreslerinizi kaydedebilirsiniz.
                    </p>

                    <form id="registerForm" method="post"
                          action="<?= $this->e($this->url($formAction)) ?>" novalidate>
                        <?= $this->csrfField() ?>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label for="first_name" class="form-label">Ad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control<?= $invalid('first_name') ?>"
                                       id="first_name" name="first_name"
                                       value="<?= $this->e($val('first_name')) ?>"
                                       maxlength="100" autocomplete="given-name" required autofocus>
                                <?php if ($error('first_name') !== ''): ?>
                                    <div class="invalid-feedback"><?= $this->e($error('first_name')) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="last_name" class="form-label">Soyad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control<?= $invalid('last_name') ?>"
                                       id="last_name" name="last_name"
                                       value="<?= $this->e($val('last_name')) ?>"
                                       maxlength="100" autocomplete="family-name" required>
                                <?php if ($error('last_name') !== ''): ?>
                                    <div class="invalid-feedback"><?= $this->e($error('last_name')) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                            <input type="email" class="form-control<?= $invalid('email') ?>" id="email" name="email"
                                   value="<?= $this->e($val('email')) ?>"
                                   maxlength="191" autocomplete="email" required>
                            <?php if ($error('email') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('email')) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Telefon</label>
                            <input type="tel" class="form-control<?= $invalid('phone') ?>" id="phone" name="phone"
                                   value="<?= $this->e($val('phone')) ?>"
                                   maxlength="20" inputmode="tel" autocomplete="tel"
                                   placeholder="05XX XXX XX XX" aria-describedby="phoneHelp">
                            <?php if ($error('phone') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('phone')) ?></div>
                            <?php endif; ?>
                            <div id="phoneHelp" class="form-text">İsteğe bağlı. Kargo bildirimleri için kullanılır.</div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-12 col-sm-6">
                                <label for="password" class="form-label">Şifre <span class="text-danger">*</span></label>
                                <input type="password" class="form-control<?= $invalid('password') ?>"
                                       id="password" name="password"
                                       maxlength="72" autocomplete="new-password"
                                       aria-describedby="passwordHelp" required>
                                <?php if ($error('password') !== ''): ?>
                                    <div class="invalid-feedback"><?= $this->e($error('password')) ?></div>
                                <?php endif; ?>
                                <div id="passwordHelp" class="form-text">
                                    En az 8 karakter; en az bir harf ve bir rakam içermelidir.
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="password_confirmation" class="form-label">Şifre tekrarı <span class="text-danger">*</span></label>
                                <input type="password" class="form-control<?= $invalid('password_confirmation') ?>"
                                       id="password_confirmation" name="password_confirmation"
                                       maxlength="72" autocomplete="new-password" required>
                                <?php if ($error('password_confirmation') !== ''): ?>
                                    <div class="invalid-feedback"><?= $this->e($error('password_confirmation')) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg" id="registerButton">
                                Kayıt Ol
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <p class="text-center text-muted small mb-0">
                        Zaten hesabınız var mı?
                        <a href="<?= $this->e($this->url($loginPath)) ?>" class="fw-semibold">Giriş yapın</a>
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

        var form = document.getElementById('registerForm');
        var button = document.getElementById('registerButton');

        if (!form || !button) {
            return;
        }

        var originalText = button.textContent;

        form.addEventListener('submit', function () {
            button.disabled = true;
            button.textContent = 'Kaydediliyor...';
        });

        // Tarayıcının geri tuşuyla dönüldüğünde buton eski haline gelir
        window.addEventListener('pageshow', function () {
            button.disabled = false;
            button.textContent = originalText;
        });
    })();
</script>