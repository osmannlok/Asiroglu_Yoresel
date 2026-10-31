<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli ürün görselleri view'ı
 *
 * Admin\ProductImageController::index() tarafından render edilir ve
 * layouts/admin.php içine yerleştirilir.
 *
 * Değişkenler:
 *   $product   (zorunlu) ürün satırı (id, name, sku ...)
 *   $images    (zorunlu) product_images satırları (sort_order sırasıyla)
 *   $maxImages (zorunlu) üründe izin verilen en fazla görsel sayısı
 *   $basePath  (zorunlu) /admin/urunler/{id}/gorseller
 *   $listPath  (zorunlu) /admin/urunler
 *
 * Sıralama: "Yukarı/Aşağı" butonlarıyla yapılır (mobilde de çalışır) ve
 * sayfa yenilenmeden Fetch API ile kaydedilir (X-CSRF-TOKEN başlığı
 * layout'taki csrf-token meta etiketinden okunur). Başarısızsa eski sıra
 * geri yüklenir. Ana görsel yapma, alt metin ve silme CSRF korumalı POST
 * formlarıdır; silme layout'taki ortak onay modalını kullanır.
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url(), $this->csrfField()
 *
 * Gereksinimler: FR-012, FR-013, FR-014, NFR-003, NFR-005, NFR-006.
 */

declare(strict_types=1);

$images    = isset($images) && is_array($images) ? $images : [];
$maxImages = max(1, (int) ($maxImages ?? 10));
$basePath  = (string) ($basePath ?? '');
$listPath  = (string) ($listPath ?? '/admin/urunler');
$productId = (int) $product['id'];

$imageCount = count($images);
$canUpload  = $imageCount < $maxImages;
$remaining  = max(0, $maxImages - $imageCount);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= $this->e($this->url($listPath)) ?>">Ürünler</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?= $this->e($this->url($listPath . '/' . $productId . '/duzenle')) ?>"><?= $this->e($product['name']) ?></a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Görseller</li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold mb-0">Ürün Görselleri</h1>
        <p class="text-muted small mb-0">
            <?= $this->e((string) $imageCount) ?> / <?= $this->e((string) $maxImages) ?> görsel &middot;
            İlk sıradaki veya "Ana" işaretli görsel ürünün kapak görselidir.
        </p>
    </div>
    <a href="<?= $this->e($this->url($listPath . '/' . $productId . '/duzenle')) ?>"
       class="btn btn-outline-secondary">Ürüne Dön</a>
</div>

<!-- Yükleme -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h5 fw-semibold mb-3">Görsel Yükle</h2>

        <?php if ($canUpload): ?>
            <form id="imageUploadForm" method="post" enctype="multipart/form-data"
                  action="<?= $this->e($this->url($basePath)) ?>" novalidate>
                <?= $this->csrfField() ?>

                <div class="mb-3">
                    <label for="images" class="form-label">Görseller</label>
                    <input type="file" class="form-control" id="images" name="images[]"
                           accept="image/jpeg,image/png,image/webp" multiple required
                           aria-describedby="imagesHelp">
                    <div id="imagesHelp" class="form-text">
                        JPG, PNG veya WebP; her biri en fazla 2 MB ve 6000x6000 piksel.
                        En fazla <?= $this->e((string) $remaining) ?> görsel daha ekleyebilirsiniz.
                        İlk yüklenen görsel otomatik ana görsel olur.
                    </div>
                    <div id="imagesError" class="text-danger small mt-1" role="alert" hidden></div>
                </div>

                <button type="submit" class="btn btn-success px-4" id="imageUploadSubmit">Yükle</button>
            </form>
        <?php else: ?>
            <div class="alert alert-warning mb-0" role="note">
                Bu ürün için en fazla <?= $this->e((string) $maxImages) ?> görsel eklenebilir.
                Yeni görsel eklemek için önce mevcut görsellerden birini silin.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Görsel listesi -->
<?php if ($images === []): ?>
    <div class="alert alert-info mb-0" role="note">
        Bu ürün için henüz görsel yok. Yukarıdan görsel yükleyebilirsiniz.
        Görseli olmayan ürün vitrinde görselsiz gösterilir.
    </div>
<?php else: ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h2 class="h5 fw-semibold mb-0">Mevcut Görseller</h2>
        <span class="text-muted small" id="orderStatus" aria-live="polite"></span>
    </div>

    <div class="row g-3" id="imageGrid"
         data-order-url="<?= $this->e($this->url($basePath . '/sirala')) ?>">
        <?php foreach ($images as $index => $image): ?>
            <?php
            $imageId  = (int) $image['id'];
            $isMain   = (int) $image['is_main'] === 1;
            $altText  = isset($image['alt_text']) ? (string) $image['alt_text'] : '';
            $src      = $this->url('/uploads/' . (string) $image['image_path']);
            $altInput = 'alt' . $imageId;
            $delForm  = 'sil-gorsel-' . $imageId;
            ?>
            <div class="col-12 col-sm-6 col-xl-4" data-image-card data-image-id="<?= $this->e((string) $imageId) ?>">
                <div class="card h-100 border-0 shadow-sm<?= $isMain ? ' border border-success' : '' ?>">
                    <div class="ratio ratio-4x3 bg-light">
                        <img src="<?= $this->e($src) ?>"
                             alt="<?= $this->e($altText !== '' ? $altText : (string) $product['name']) ?>"
                             class="card-img-top object-fit-contain" loading="lazy">
                    </div>

                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div>
                                <span class="badge text-bg-secondary" data-position-badge>
                                    Sıra: <?= $this->e((string) ($index + 1)) ?>
                                </span>
                                <?php if ($isMain): ?>
                                    <span class="badge text-bg-success">Ana görsel</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($imageCount > 1): ?>
                                <div class="btn-group btn-group-sm" role="group"
                                     aria-label="Sıralama">
                                    <button type="button" class="btn btn-outline-secondary"
                                            data-move="up" aria-label="Yukarı taşı">&uarr;</button>
                                    <button type="button" class="btn btn-outline-secondary"
                                            data-move="down" aria-label="Aşağı taşı">&darr;</button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Alt metin (SEO) -->
                        <form method="post" class="mb-3"
                              action="<?= $this->e($this->url($basePath . '/' . $imageId . '/alt')) ?>">
                            <?= $this->csrfField() ?>
                            <label for="<?= $this->e($altInput) ?>" class="form-label small mb-1">Alt metin</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="<?= $this->e($altInput) ?>"
                                       name="alt_text" value="<?= $this->e($altText) ?>" maxlength="255"
                                       placeholder="<?= $this->e((string) $product['name']) ?>">
                                <button type="submit" class="btn btn-outline-primary">Kaydet</button>
                            </div>
                        </form>

                        <div class="d-flex flex-wrap gap-2">
                            <?php if (!$isMain): ?>
                                <form method="post" class="m-0"
                                      action="<?= $this->e($this->url($basePath . '/' . $imageId . '/ana')) ?>">
                                    <?= $this->csrfField() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-success">Ana yap</button>
                                </form>
                            <?php endif; ?>

                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    data-confirm-form="<?= $this->e($delForm) ?>"
                                    data-confirm-message="Bu görsel silinsin mi? Dosya da kalıcı olarak kaldırılır.">
                                Sil
                            </button>

                            <form id="<?= $this->e($delForm) ?>" method="post" class="d-none"
                                  action="<?= $this->e($this->url($basePath . '/' . $imageId . '/sil')) ?>">
                                <?= $this->csrfField() ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
    (function () {
        'use strict';

        var MAX_BYTES = 2 * 1024 * 1024;
        var ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

        function notify(message, type) {
            if (typeof window.showToast === 'function') {
                window.showToast(message, type);
            }
        }

        // --- Yükleme formu: istemci tarafı ön kontrol (asıl doğrulama sunucudadır) ---
        var form = document.getElementById('imageUploadForm');
        var button = document.getElementById('imageUploadSubmit');
        var input = document.getElementById('images');
        var errorBox = document.getElementById('imagesError');
        var maxFiles = <?= (int) $remaining ?>;

        if (form && button && input && errorBox) {
            var originalText = button.textContent;

            form.addEventListener('submit', function (event) {
                var problem = '';
                var files = input.files ? Array.prototype.slice.call(input.files) : [];

                if (files.length === 0) {
                    problem = 'Lütfen en az bir görsel seçin.';
                } else if (files.length > maxFiles) {
                    problem = 'En fazla ' + maxFiles + ' görsel daha ekleyebilirsiniz.';
                } else {
                    files.forEach(function (file) {
                        if (problem === '' && ALLOWED.indexOf(file.type) === -1) {
                            problem = 'Yalnızca JPG, PNG veya WebP yüklenebilir.';
                        } else if (problem === '' && file.size > MAX_BYTES) {
                            problem = 'Her görsel en fazla 2 MB olabilir.';
                        }
                    });
                }

                if (problem !== '') {
                    event.preventDefault();
                    errorBox.textContent = problem;
                    errorBox.hidden = false;
                    return;
                }

                errorBox.hidden = true;
                button.disabled = true;
                button.textContent = 'Yükleniyor...';
            });

            // Tarayıcının geri tuşuyla dönüldüğünde buton eski haline gelir
            window.addEventListener('pageshow', function () {
                button.disabled = false;
                button.textContent = originalText;
            });
        }

        // --- Sıralama: Yukarı/Aşağı + Fetch ---------------------------------------
        var grid = document.getElementById('imageGrid');
        var status = document.getElementById('orderStatus');

        if (!grid) {
            return;
        }

        var tokenMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : '';
        var orderUrl = grid.getAttribute('data-order-url');
        var saving = false;

        function cards() {
            return Array.prototype.slice.call(grid.querySelectorAll('[data-image-card]'));
        }

        function refreshBadges() {
            cards().forEach(function (card, index) {
                var badge = card.querySelector('[data-position-badge]');
                if (badge) {
                    badge.textContent = 'Sıra: ' + (index + 1);
                }
            });
        }

        function setBusy(busy) {
            saving = busy;
            grid.querySelectorAll('[data-move]').forEach(function (btn) {
                btn.disabled = busy;
            });
            if (status) {
                status.textContent = busy ? 'Kaydediliyor...' : '';
            }
        }

        function saveOrder(previousOrder) {
            var ids = cards().map(function (card) {
                return parseInt(card.getAttribute('data-image-id'), 10);
            });

            setBusy(true);

            fetch(orderUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ order: ids })
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.data || result.data.success !== true) {
                        throw new Error((result.data && result.data.message) || 'Sıra kaydedilemedi.');
                    }
                    notify(result.data.message, 'success');
                })
                .catch(function (error) {
                    // Başarısızsa eski sıraya dön
                    previousOrder.forEach(function (card) {
                        grid.appendChild(card);
                    });
                    refreshBadges();
                    notify(error.message || 'Sıra kaydedilemedi.', 'danger');
                })
                .finally(function () {
                    setBusy(false);
                });
        }

        grid.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-move]');

            if (!trigger || saving) {
                return;
            }

            var card = trigger.closest('[data-image-card]');
            var previous = cards();
            var position = previous.indexOf(card);
            var direction = trigger.getAttribute('data-move');

            if (direction === 'up' && position > 0) {
                grid.insertBefore(card, previous[position - 1]);
            } else if (direction === 'down' && position < previous.length - 1) {
                grid.insertBefore(previous[position + 1], card);
            } else {
                return;
            }

            refreshBadges();
            saveOrder(previous);
        });
    })();
</script>