<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli marka formu view'ı
 *
 * Admin\BrandController::showForm() tarafından render edilir ve
 * layouts/admin.php içine yerleştirilir. Hem oluşturma hem düzenleme için kullanılır.
 *
 * Değişkenler:
 *   $isEdit     (bool) düzenleme mi?
 *   $brandId    (int|null) düzenlenen marka
 *   $values     (array) form değerleri
 *   $errors     (array) alan => hata mesajı
 *   $formAction (string) formun gönderileceği yol
 *   $listPath   (string) liste sayfasının yolu
 *
 * Logo yükleme bu formda yoktur; Uploader yazılınca eklenecektir.
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url(), $this->csrfField()
 *
 * Gereksinimler: FR-032, FR-033, NFR-003, NFR-004, NFR-005.
 */

declare(strict_types=1);

$isEdit     = !empty($isEdit);
$brandId    = isset($brandId) ? (int) $brandId : 0;
$values     = isset($values) && is_array($values) ? $values : [];
$errors     = isset($errors) && is_array($errors) ? $errors : [];
$formAction = isset($formAction) ? (string) $formAction : '/admin/markalar';
$listPath   = isset($listPath) ? (string) $listPath : '/admin/markalar';

/** Form değerini metin olarak döndürür. */
$val = static fn (string $key): string => isset($values[$key]) ? (string) $values[$key] : '';

/** Alan hatalıysa Bootstrap sınıfını döndürür. */
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' is-invalid' : '';

/** Alan hata mesajını (varsa) döndürür. */
$error = static fn (string $key): string => isset($errors[$key]) ? (string) $errors[$key] : '';

$isActive = !empty($values['is_active']);
$currentLogo = isset($currentLogo) ? (string) $currentLogo : '';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 fw-bold mb-0"><?= $isEdit ? 'Markayı Düzenle' : 'Yeni Marka' ?></h1>
    <a href="<?= $this->e($this->url($listPath)) ?>" class="btn btn-outline-secondary">Listeye Dön</a>
</div>

<form id="brandForm" method="post" enctype="multipart/form-data"
      action="<?= $this->e($this->url($formAction)) ?>" novalidate>
<?= $this->csrfField() ?>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Genel Bilgiler</h2>

                    <div class="mb-3">
                        <label for="name" class="form-label">Marka adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control<?= $invalid('name') ?>" id="name" name="name"
                               value="<?= $this->e($val('name')) ?>" maxlength="150" required autofocus>
                        <?php if ($error('name') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('name')) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="slug" class="form-label">Slug</label>
                        <input type="text" class="form-control<?= $invalid('slug') ?>" id="slug" name="slug"
                               value="<?= $this->e($val('slug')) ?>" maxlength="191"
                               aria-describedby="slugHelp">
                        <?php if ($error('slug') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('slug')) ?></div>
                        <?php endif; ?>
                        <div id="slugHelp" class="form-text">
                            Adres parçasıdır (örn. <code>ashiroglu</code>). Boş bırakırsanız marka adından üretilir.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="website" class="form-label">Web sitesi</label>
                        <input type="url" class="form-control<?= $invalid('website') ?>" id="website" name="website"
                               value="<?= $this->e($val('website')) ?>" maxlength="255"
                               placeholder="https://www.ornek.com" inputmode="url"
                               aria-describedby="websiteHelp">
                        <?php if ($error('website') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('website')) ?></div>
                        <?php endif; ?>
                        <div id="websiteHelp" class="form-text">
                            Yalnızca <code>http://</code> veya <code>https://</code> ile başlayan adresler kabul edilir.
                        </div>
                    </div>

                    <div>
                        <label for="description" class="form-label">Açıklama</label>
                        <textarea class="form-control<?= $invalid('description') ?>" id="description"
                                  name="description" rows="5" maxlength="5000"><?= $this->e($val('description')) ?></textarea>
                        <?php if ($error('description') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('description')) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Yayın</h2>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active"
                               name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Aktif</label>
                    </div>
                    <div class="form-text">
                        Pasif markanın ürünleri vitrinde listelenmez; sipariş geçmişi etkilenmez.
                    </div>
                </div>

            </div>
                        <div class="card border-0 shadow-sm mt-4">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Logo</h2>

                    <?php if ($currentLogo !== ''): ?>
                        <div class="ratio ratio-4x3 bg-light mb-3">
                            <img src="<?= $this->e($this->url('/uploads/' . $currentLogo)) ?>"
                                 alt="<?= $this->e($val('name')) ?>"
                                 class="object-fit-contain" loading="lazy">
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="remove_logo"
                                   name="remove_logo" value="1">
                            <label class="form-check-label" for="remove_logo">Mevcut logoyu kaldır</label>
                        </div>
                    <?php endif; ?>

                    <label for="logo" class="form-label">
                        <?= $currentLogo !== '' ? 'Logoyu değiştir' : 'Logo yükle' ?>
                    </label>
                    <input type="file" class="form-control<?= $invalid('logo') ?>" id="logo" name="logo"
                           accept="image/jpeg,image/png,image/webp" aria-describedby="logoHelp">
                    <?php if ($error('logo') !== ''): ?>
                        <div class="invalid-feedback"><?= $this->e($error('logo')) ?></div>
                    <?php endif; ?>
                    <div id="logoHelp" class="form-text">
                        JPG, PNG veya WebP; en fazla 2 MB ve 6000x6000 piksel.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="submit" class="btn btn-success px-4" id="brandSubmit">
            <?= $isEdit ? 'Güncelle' : 'Kaydet' ?>
        </button>
        <a href="<?= $this->e($this->url($listPath)) ?>" class="btn btn-outline-secondary">Vazgeç</a>
    </div>
</form>

<script>
    // Gönderim sırasında butonu devre dışı bırakır (çift tıklamayı önler)
    (function () {
        'use strict';

        var form = document.getElementById('brandForm');
        var button = document.getElementById('brandSubmit');

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