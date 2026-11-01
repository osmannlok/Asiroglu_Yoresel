<?php

/**
 * Aşıroğlu Yöresel Market - Vitrin ürün detay view'ı
 *
 * ProductController::show() tarafından render edilir ve layouts/main.php
 * içine yerleştirilir.
 *
 * Değişkenler:
 *   $product        products satırı
 *   $images         product_images satırları (sıralı)
 *   $variants       list: id, name, sku, price, old_price, stock, purchasable
 *   $hasVariants    bool
 *   $currentPrice   float (varyantsız ürünün geçerli fiyatı)
 *   $oldPrice       float|null
 *   $availableStock int (satılabilir toplam stok)
 *   $category, $parentCategory, $brand  array|null
 *   $related        list (vitrin liste satırları)
 *
 * Varyant seçimi fiyat ve stoğu sayfa yenilenmeden günceller; sepete ekleme
 * Fetch ile POST /sepet/ekle yapar. Fiyatlar KDV dahil, TRY.
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url()
 *
 * Gereksinimler: FR-014, FR-015, FR-022, FR-025, FR-043, FR-044, NFR-005.
 */

declare(strict_types=1);

$images         = isset($images) && is_array($images) ? $images : [];
$variants       = isset($variants) && is_array($variants) ? $variants : [];
$hasVariants    = !empty($hasVariants);
$currentPrice   = (float) ($currentPrice ?? 0);
$oldPrice       = isset($oldPrice) && $oldPrice !== null ? (float) $oldPrice : null;
$availableStock = (int) ($availableStock ?? 0);
$related        = isset($related) && is_array($related) ? $related : [];
$category       = isset($category) && is_array($category) ? $category : null;
$parentCategory = isset($parentCategory) && is_array($parentCategory) ? $parentCategory : null;
$brand          = isset($brand) && is_array($brand) ? $brand : null;

$productId   = (int) $product['id'];
$productName = (string) $product['name'];

/** Tutarı Türkçe biçimde yazar: 1.250,50 ₺ */
$money = static fn (mixed $amount): string => number_format((float) $amount, 2, ',', '.') . ' ₺';

/** Metni kaçırır ve satır sonlarını <br> yapar (kaçırma önce yapılır) */
$multiline = fn (string $text): string => nl2br($this->e($text), false);

// Ana görsel: is_main olan, yoksa ilk görsel
$mainImage = null;

foreach ($images as $image) {
    if ((int) $image['is_main'] === 1) {
        $mainImage = $image;
        break;
    }
}

if ($mainImage === null && $images !== []) {
    $mainImage = $images[0];
}

$imageSrc = static fn (array $image): string => '/uploads/' . (string) $image['image_path'];
$imageAlt = static fn (array $image): string => trim((string) ($image['alt_text'] ?? '')) !== ''
    ? (string) $image['alt_text']
    : $productName;

// Varyantsız ürün stok durumu
$simpleInStock = $availableStock > 0;

// Ürün bilgileri tablosu: etiket => değer (boşlar atlanır)
$infoRows = [
    'Menşe'             => (string) ($product['origin'] ?? ''),
    'Üretici'           => (string) ($product['producer'] ?? ''),
    'Raf ömrü'          => (string) ($product['shelf_life'] ?? ''),
    'Saklama koşulları' => (string) ($product['storage_conditions'] ?? ''),
    'Alerjen bilgisi'   => (string) ($product['allergens'] ?? ''),
    'İçindekiler'       => (string) ($product['ingredients'] ?? ''),
    'Birim'             => (string) ($product['unit_type'] ?? ''),
    'Ağırlık'           => (string) ($product['weight'] ?? ''),
    'SKU'               => (string) ($product['sku'] ?? ''),
];
$infoRows = array_filter($infoRows, static fn (string $value): bool => trim($value) !== '');

// JS'e aktarılan varyant verisi (yalnızca gösterim için gerekenler)
$variantData = array_map(static fn (array $v): array => [
    'id'          => (int) $v['id'],
    'price'       => (float) $v['price'],
    'old_price'   => $v['old_price'] === null ? null : (float) $v['old_price'],
    'stock'       => (int) $v['stock'],
    'purchasable' => (bool) $v['purchasable'],
    'price_text'  => number_format((float) $v['price'], 2, ',', '.') . ' ₺',
    'old_text'    => $v['old_price'] === null ? '' : number_format((float) $v['old_price'], 2, ',', '.') . ' ₺',
], $variants);

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
?>
<div class="container py-4 py-lg-5">

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= $this->e($this->url('/urunler')) ?>">Ürünler</a></li>
            <?php if ($parentCategory !== null): ?>
                <li class="breadcrumb-item">
                    <a href="<?= $this->e($this->url('/urunler?' . http_build_query(['kategori' => (int) $parentCategory['id']]))) ?>"><?= $this->e($parentCategory['name']) ?></a>
                </li>
            <?php endif; ?>
            <?php if ($category !== null): ?>
                <li class="breadcrumb-item">
                    <a href="<?= $this->e($this->url('/urunler?' . http_build_query(['kategori' => (int) $category['id']]))) ?>"><?= $this->e($category['name']) ?></a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page"><?= $this->e($productName) ?></li>
        </ol>
    </nav>

    <!-- Sepet işlem bildirimi (JS yazar) -->
    <div id="productAlert" class="alert d-none" role="alert" aria-live="polite"></div>

    <div class="row g-4 g-lg-5">

        <!-- Galeri -->
        <div class="col-12 col-md-6">
            <div class="ratio ratio-1x1 bg-white border rounded shadow-sm">
                <?php if ($mainImage !== null): ?>
                    <img id="mainImage" src="<?= $this->e($this->url($imageSrc($mainImage))) ?>"
                         alt="<?= $this->e($imageAlt($mainImage)) ?>" class="object-fit-contain rounded">
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center text-muted">Görsel yok</div>
                <?php endif; ?>
            </div>

            <?php if (count($images) > 1): ?>
                <div class="d-flex flex-wrap gap-2 mt-3" id="thumbList">
                    <?php foreach ($images as $image): ?>
                        <button type="button" class="btn p-0 border rounded bg-white"
                                style="width: 72px; height: 72px;"
                                data-thumb
                                data-src="<?= $this->e($this->url($imageSrc($image))) ?>"
                                data-alt="<?= $this->e($imageAlt($image)) ?>"
                                aria-label="Görseli göster">
                            <img src="<?= $this->e($this->url($imageSrc($image))) ?>"
                                 alt="<?= $this->e($imageAlt($image)) ?>"
                                 class="w-100 h-100 object-fit-contain rounded" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bilgi ve satın alma -->
        <div class="col-12 col-md-6">
            <?php if ($brand !== null): ?>
                <div class="small text-muted mb-1"><?= $this->e($brand['name']) ?></div>
            <?php endif; ?>

            <h1 class="h3 fw-bold mb-2"><?= $this->e($productName) ?></h1>

            <?php if (trim((string) ($product['short_description'] ?? '')) !== ''): ?>
                <p class="text-muted"><?= $multiline((string) $product['short_description']) ?></p>
            <?php endif; ?>

            <!-- Fiyat -->
            <div class="mb-3">
                <div class="d-flex flex-wrap align-items-baseline gap-2">
                    <span class="display-6 fw-bold text-success" id="priceNow">
                        <?php if ($hasVariants): ?>
                            <?= $this->e($money(min(array_map(static fn (array $v): float => (float) $v['price'], $variants) ?: [0]))) ?>
                        <?php else: ?>
                            <?= $this->e($money($currentPrice)) ?>
                        <?php endif; ?>
                    </span>
                    <span class="text-muted text-decoration-line-through<?= (!$hasVariants && $oldPrice !== null) ? '' : ' d-none' ?>"
                          id="priceOld"><?= (!$hasVariants && $oldPrice !== null) ? $this->e($money($oldPrice)) : '' ?></span>
                </div>
                <div class="small text-muted">
                    KDV dahil<?php if ($hasVariants): ?> &middot; <span id="priceHint">Başlayan fiyat; seçeneğe göre değişir</span><?php endif; ?>
                </div>
            </div>

            <!-- Stok -->
            <div class="mb-3" id="stockInfo">
                <?php if ($hasVariants): ?>
                    <span class="badge text-bg-secondary">Bir seçenek seçin</span>
                <?php elseif ($simpleInStock): ?>
                    <span class="badge text-bg-success">Stokta</span>
                <?php else: ?>
                    <span class="badge text-bg-secondary">Tükendi</span>
                <?php endif; ?>
            </div>

            <!-- Varyant seçimi -->
            <?php if ($hasVariants): ?>
                <fieldset class="mb-3">
                    <legend class="form-label fs-6 mb-2">Seçenek</legend>
                    <div class="d-flex flex-wrap gap-2" id="variantList">
                        <?php foreach ($variants as $variant): ?>
                            <?php
                            $vid        = (int) $variant['id'];
                            $radioId    = 'variant' . $vid;
                            $disabled   = empty($variant['purchasable']);
                            ?>
                            <div>
                                <input type="radio" class="btn-check" name="variant" id="<?= $this->e($radioId) ?>"
                                       value="<?= $this->e((string) $vid) ?>"<?= $disabled ? ' disabled' : '' ?>>
                                <label class="btn btn-outline-success" for="<?= $this->e($radioId) ?>">
                                    <?= $this->e($variant['name']) ?><?= $disabled ? ' (Tükendi)' : '' ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endif; ?>

            <!-- Adet ve sepete ekle -->
            <?php
            $canBuyInitially = !$hasVariants && $simpleInStock;
            $initialMax      = $canBuyInitially ? min($availableStock, 1000) : 1;
            ?>
            <div class="d-flex flex-wrap align-items-end gap-3 mb-4">
                <div>
                    <label for="qty" class="form-label small mb-1">Adet</label>
                    <input type="number" class="form-control" id="qty" value="1" min="1"
                           max="<?= $this->e((string) $initialMax) ?>" step="1" inputmode="numeric"
                           style="width: 110px;"<?= $canBuyInitially ? '' : ' disabled' ?>>
                </div>

                <button type="button" class="btn btn-success btn-lg flex-grow-1" id="addButton"
                        data-product-id="<?= $this->e((string) $productId) ?>"<?= $canBuyInitially ? '' : ' disabled' ?>>
                    <?= $hasVariants ? 'Önce seçenek seçin' : ($simpleInStock ? 'Sepete Ekle' : 'Tükendi') ?>
                </button>
            </div>

            <?php if (trim((string) ($product['description'] ?? '')) !== ''): ?>
                <h2 class="h5 fw-semibold">Açıklama</h2>
                <p><?= $multiline((string) $product['description']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ürün ve gıda bilgileri -->
    <?php if ($infoRows !== []): ?>
        <div class="card border-0 shadow-sm mt-5">
            <div class="card-body p-4">
                <h2 class="h5 fw-semibold mb-3">Ürün Bilgileri</h2>
                <dl class="row mb-0">
                    <?php foreach ($infoRows as $label => $value): ?>
                        <dt class="col-12 col-sm-4 col-lg-3 fw-semibold"><?= $this->e($label) ?></dt>
                        <dd class="col-12 col-sm-8 col-lg-9"><?= $multiline($value) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>
    <?php endif; ?>

    <!-- Benzer ürünler -->
    <?php if ($related !== []): ?>
        <h2 class="h5 fw-semibold mt-5 mb-3">Benzer Ürünler</h2>
        <div class="row row-cols-2 row-cols-lg-4 g-3">
            <?php foreach ($related as $item): ?>
                <?php
                $relUrl   = $this->url('/urun/' . (string) $item['slug']);
                $relImage = isset($item['image_path']) ? (string) $item['image_path'] : '';
                $relStock = (int) ($item['in_stock'] ?? 0) === 1;
                ?>
                <div class="col">
                    <a href="<?= $this->e($relUrl) ?>" class="card h-100 border-0 shadow-sm text-decoration-none text-dark">
                        <div class="ratio ratio-4x3 bg-light">
                            <?php if ($relImage !== ''): ?>
                                <img src="<?= $this->e($this->url('/uploads/' . $relImage)) ?>"
                                     alt="<?= $this->e($item['name']) ?>" class="card-img-top object-fit-contain" loading="lazy">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center text-muted small">Görsel yok</div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="fw-semibold small mb-1"><?= $this->e($item['name']) ?></div>
                            <div class="text-success fw-bold"><?= $this->e($money($item['display_price'])) ?></div>
                            <?php if (!$relStock): ?>
                                <span class="badge text-bg-secondary mt-1">Tükendi</span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    (function () {
        'use strict';

        var variants = <?= json_encode($variantData, $jsonFlags) ?>;
        var hasVariants = <?= $hasVariants ? 'true' : 'false' ?>;
        var simpleStock = <?= (int) $availableStock ?>;
        var addUrl = <?= json_encode($this->url('/sepet/ekle'), $jsonFlags) ?>;
        var productId = <?= (int) $productId ?>;

        var alertBox = document.getElementById('productAlert');
        var addButton = document.getElementById('addButton');
        var qtyInput = document.getElementById('qty');
        var stockInfo = document.getElementById('stockInfo');
        var priceNow = document.getElementById('priceNow');
        var priceOld = document.getElementById('priceOld');
        var priceHint = document.getElementById('priceHint');
        var mainImage = document.getElementById('mainImage');

        var tokenMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : '';

        var selectedVariant = null;
        var busy = false;

        if (!alertBox || !addButton || !qtyInput || !stockInfo) {
            return;
        }

        // Bildirim: textContent ile yazılır (XSS güvenli)
        function notify(message, type) {
            alertBox.className = 'alert alert-' + type;
            alertBox.textContent = String(message);
        }

        function setStockBadge(text, color) {
            stockInfo.textContent = '';
            var badge = document.createElement('span');
            badge.className = 'badge text-bg-' + color;
            badge.textContent = text;
            stockInfo.appendChild(badge);
        }

        // --- Galeri ----------------------------------------------------------
        document.querySelectorAll('[data-thumb]').forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                if (mainImage) {
                    mainImage.src = thumb.getAttribute('data-src');
                    mainImage.alt = thumb.getAttribute('data-alt') || '';
                }
            });
        });

        // --- Varyant seçimi --------------------------------------------------
        function findVariant(id) {
            for (var i = 0; i < variants.length; i++) {
                if (variants[i].id === id) {
                    return variants[i];
                }
            }

            return null;
        }

        function applyVariant(variant) {
            selectedVariant = variant;

            if (!variant) {
                return;
            }

            priceNow.textContent = variant.price_text;

            if (variant.old_price !== null) {
                priceOld.textContent = variant.old_text;
                priceOld.classList.remove('d-none');
            } else {
                priceOld.textContent = '';
                priceOld.classList.add('d-none');
            }

            if (priceHint) {
                priceHint.textContent = 'Seçilen seçeneğin fiyatı';
            }

            if (variant.purchasable) {
                setStockBadge('Stokta', 'success');
                qtyInput.disabled = false;
                qtyInput.max = String(Math.min(variant.stock, 1000));

                if ((parseInt(qtyInput.value, 10) || 1) > variant.stock) {
                    qtyInput.value = String(variant.stock);
                }

                addButton.disabled = false;
                addButton.textContent = 'Sepete Ekle';
            } else {
                setStockBadge('Tükendi', 'secondary');
                qtyInput.disabled = true;
                addButton.disabled = true;
                addButton.textContent = 'Tükendi';
            }
        }

        document.querySelectorAll('input[name="variant"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                applyVariant(findVariant(parseInt(radio.value, 10)));
            });
        });

        // --- Sepete ekle -----------------------------------------------------
        addButton.addEventListener('click', function () {
            if (busy) {
                return;
            }

            var quantity = parseInt(qtyInput.value, 10);
            var variantId = null;
            var stock = simpleStock;

            if (hasVariants) {
                if (!selectedVariant) {
                    notify('Lütfen bir seçenek seçin.', 'warning');
                    return;
                }

                variantId = selectedVariant.id;
                stock = selectedVariant.stock;
            }

            if (isNaN(quantity) || quantity < 1) {
                notify('Adet en az 1 olmalıdır.', 'warning');
                return;
            }

            if (quantity > stock) {
                notify('Yetersiz stok. Eklenebilecek en fazla adet: ' + stock + '.', 'warning');
                return;
            }

            busy = true;
            addButton.disabled = true;

            var originalText = addButton.textContent;
            addButton.textContent = 'Ekleniyor...';

            var payload = { product_id: productId, quantity: quantity };

            if (variantId !== null) {
                payload.variant_id = variantId;
            }

            fetch(addUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return null;
                    }).then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.data || !result.ok || result.data.success !== true) {
                        throw new Error((result.data && result.data.message) || 'Ürün sepete eklenemedi.');
                    }

                    notify(result.data.message || 'Ürün sepete eklendi.', 'success');
                })
                .catch(function (error) {
                    notify(error.message || 'Ürün sepete eklenemedi.', 'danger');
                })
                .finally(function () {
                    busy = false;
                    addButton.disabled = false;
                    addButton.textContent = originalText;
                });
        });
    })();
</script>