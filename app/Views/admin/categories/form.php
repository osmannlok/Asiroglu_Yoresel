<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli kategori formu view'ı
 *
 * Admin\CategoryController::showForm() tarafından render edilir ve
 * layouts/admin.php içine yerleştirilir. Hem oluşturma hem düzenleme için kullanılır.
 *
 * Değişkenler:
 *   $isEdit     (bool) düzenleme mi?
 *   $categoryId (int|null) düzenlenen kategori
 *   $values     (array) form değerleri
 *   $errors     (array) alan => hata mesajı
 *   $parents    (list) üst kategori seçenekleri
 *   $formAction (string) formun gönderileceği yol
 *   $listPath   (string) liste sayfasının yolu
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url(), $this->csrfField()
 *
 * Gereksinimler: FR-030, FR-031, FR-033, NFR-003, NFR-004, NFR-005.
 */

declare(strict_types=1);

$isEdit     = !empty($isEdit);
$categoryId = isset($categoryId) ? (int) $categoryId : 0;
$values     = isset($values) && is_array($values) ? $values : [];
$errors     = isset($errors) && is_array($errors) ? $errors : [];
$parents    = isset($parents) && is_array($parents) ? $parents : [];
$formAction = isset($formAction) ? (string) $formAction : '/admin/kategoriler';
$listPath   = isset($listPath) ? (string) $listPath : '/admin/kategoriler';

/** Form değerini metin olarak döndürür. */
$val = static fn (string $key): string => isset($values[$key]) ? (string) $values[$key] : '';

/** Alan hatalıysa Bootstrap sınıfını döndürür. */
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' is-invalid' : '';

/** Alan hata mesajını (varsa) döndürür. */
$error = static fn (string $key): string => isset($errors[$key]) ? (string) $errors[$key] : '';

$selectedParent = $val('parent_id');
$isActive       = !empty($values['is_active']);
$isFeatured     = !empty($values['is_featured']);
$currentImage   = isset($currentImage) ? (string) $currentImage : '';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 fw-bold mb-0"><?= $isEdit ? 'Kategoriyi Düzenle' : 'Yeni Kategori' ?></h1>
    <a href="<?= $this->e($this->url($listPath)) ?>" class="btn btn-outline-secondary">Listeye Dön</a>
</div>

<form id="categoryForm" method="post" enctype="multipart/form-data"
      action="<?= $this->e($this->url($formAction)) ?>" novalidate>
    <?= $this->csrfField() ?>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Genel Bilgiler</h2>

                    <div class="mb-3">
                        <label for="name" class="form-label">Kategori adı <span class="text-danger">*</span></label>
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
                            Adres parçasıdır (örn. <code>recel</code>). Boş bırakırsanız kategori adından üretilir.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Üst kategori</label>
                        <select class="form-select<?= $invalid('parent_id') ?>" id="parent_id" name="parent_id">
                            <option value="">— Ana kategori —</option>
                            <?php foreach ($parents as $parent): ?>
                                <?php $parentId = (string) (int) $parent['id']; ?>
                                <option value="<?= $this->e($parentId) ?>"<?= $selectedParent === $parentId ? ' selected' : '' ?>>
                                    <?= $this->e($parent['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($error('parent_id') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('parent_id')) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Açıklama</label>
                        <textarea class="form-control<?= $invalid('description') ?>" id="description"
                                  name="description" rows="4" maxlength="5000"><?= $this->e($val('description')) ?></textarea>
                        <?php if ($error('description') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('description')) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label for="icon" class="form-label">İkon</label>
                            <input type="text" class="form-control<?= $invalid('icon') ?>" id="icon" name="icon"
                                   value="<?= $this->e($val('icon')) ?>" maxlength="100">
                            <?php if ($error('icon') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('icon')) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="sort_order" class="form-label">Sıra</label>
                            <input type="number" class="form-control<?= $invalid('sort_order') ?>" id="sort_order"
                                   name="sort_order" value="<?= $this->e($val('sort_order')) ?>"
                                   min="0" max="9999" step="1" inputmode="numeric">
                            <?php if ($error('sort_order') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('sort_order')) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Yayın</h2>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active"
                               name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Aktif</label>
                    </div>
                    <div class="form-text mb-3">
                        Pasif kategori vitrinde listelenmez; sipariş geçmişi etkilenmez.
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_featured"
                               name="is_featured" value="1"<?= $isFeatured ? ' checked' : '' ?>>
                        <label class="form-check-label" for="is_featured">Öne çıkan</label>
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Görsel</h2>

                    <?php if ($currentImage !== ''): ?>
                        <div class="ratio ratio-4x3 bg-light mb-3">
                            <img src="<?= $this->e($this->url('/uploads/' . $currentImage)) ?>"
                                 alt="<?= $this->e($val('name')) ?>"
                                 class="object-fit-contain" loading="lazy">
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="remove_image"
                                   name="remove_image" value="1">
                            <label class="form-check-label" for="remove_image">Mevcut görseli kaldır</label>
                        </div>
                    <?php endif; ?>

                    <label for="image" class="form-label">
                        <?= $currentImage !== '' ? 'Görseli değiştir' : 'Görsel yükle' ?>
                    </label>
                    <input type="file" class="form-control<?= $invalid('image') ?>" id="image" name="image"
                           accept="image/jpeg,image/png,image/webp" aria-describedby="imageHelp">
                    <?php if ($error('image') !== ''): ?>
                        <div class="invalid-feedback"><?= $this->e($error('image')) ?></div>
                    <?php endif; ?>
                    <div id="imageHelp" class="form-text">
                        JPG, PNG veya WebP; en fazla 2 MB ve 6000x6000 piksel.
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">SEO</h2>

                    <div class="mb-3">
                        <label for="meta_title" class="form-label">SEO başlığı</label>
                        <input type="text" class="form-control<?= $invalid('meta_title') ?>" id="meta_title"
                               name="meta_title" value="<?= $this->e($val('meta_title')) ?>" maxlength="255">
                        <?php if ($error('meta_title') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('meta_title')) ?></div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label for="meta_description" class="form-label">SEO açıklaması</label>
                        <textarea class="form-control<?= $invalid('meta_description') ?>" id="meta_description"
                                  name="meta_description" rows="3" maxlength="500"><?= $this->e($val('meta_description')) ?></textarea>
                        <?php if ($error('meta_description') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('meta_description')) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="submit" class="btn btn-success px-4" id="categorySubmit">
            <?= $isEdit ? 'Güncelle' : 'Kaydet' ?>
        </button>
        <a href="<?= $this->e($this->url($listPath)) ?>" class="btn btn-outline-secondary">Vazgeç</a>
    </div>
</form>

<script>
    // Gönderim sırasında butonu devre dışı bırakır (çift tıklamayı önler)
    (function () {
        'use strict';

        var form = document.getElementById('categoryForm');
        var button = document.getElementById('categorySubmit');

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