<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli ürün varyant listesi view'ı
 *
 * Admin\ProductVariantController::index() tarafından render edilir ve
 * layouts/admin.php içine yerleştirilir.
 *
 * Değişkenler:
 *   $product    (zorunlu) ürün satırı (id, name, sku, critical_stock_quantity ...)
 *   $variants   (zorunlu) product_variants satırları
 *   $totalStock (zorunlu) tüm varyantların toplam stoğu
 *   $basePath   (zorunlu) /admin/urunler/{id}/varyantlar
 *   $listPath   (zorunlu) /admin/urunler
 *
 * Aktif/pasif geçişi sayfa yenilenmeden Fetch API ile yapılır (X-CSRF-TOKEN
 * başlığı layout'taki csrf-token meta etiketinden okunur). Varyantlar silinmez,
 * satıştan kaldırılır (FR-025). Stok bu ekranda değiştirilemez (FR-026).
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url(), $this->csrfField()
 *
 * Gereksinimler: FR-020, FR-021, FR-022, FR-025, NFR-003, NFR-005.
 */

declare(strict_types=1);

$variants   = isset($variants) && is_array($variants) ? $variants : [];
$totalStock = (int) ($totalStock ?? 0);
$basePath   = (string) ($basePath ?? '');
$listPath   = (string) ($listPath ?? '/admin/urunler');
$productId  = (int) $product['id'];
$critical   = (int) ($product['critical_stock_quantity'] ?? 5);

/** Tutarı Türkçe biçimde yazar: 1.250,50 ₺ */
$money = static fn (mixed $amount): string => number_format((float) $amount, 2, ',', '.') . ' ₺';
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
                <li class="breadcrumb-item active" aria-current="page">Varyantlar</li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold mb-0">Varyantlar</h1>
        <p class="text-muted small mb-0">
            <?= $this->e((string) count($variants)) ?> varyant &middot;
            Toplam stok: <?= $this->e((string) $totalStock) ?>
        </p>
    </div>
    <a href="<?= $this->e($this->url($basePath . '/olustur')) ?>" class="btn btn-success">
        Yeni Varyant
    </a>
</div>

<?php if ($variants === []): ?>
    <div class="alert alert-info mb-0" role="note">
        Bu ürün için henüz varyant yok. "Yeni Varyant" ile 500 Gr, 1 Kg gibi seçenekler ekleyebilirsiniz.
        Varyant eklendiğinde ürünün geçerli stoğu varyantlardaki stok olur.
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Varyant</th>
                        <th scope="col">SKU</th>
                        <th scope="col" class="text-end">Fiyat</th>
                        <th scope="col" class="text-end">Stok</th>
                        <th scope="col">Satış</th>
                        <th scope="col" class="text-end">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($variants as $variant): ?>
                        <?php
                        $variantId   = (int) $variant['id'];
                        $isActive    = (int) $variant['is_active'] === 1;
                        $stock       = (int) $variant['stock_quantity'];
                        $price       = (float) $variant['price'];
                        $discount    = $variant['discount_price'] ?? null;
                        $hasDiscount = $discount !== null && $discount !== '' && (float) $discount < $price;
                        $barcode     = isset($variant['barcode']) ? (string) $variant['barcode'] : '';
                        $weight      = isset($variant['weight']) ? (string) $variant['weight'] : '';
                        $switchId    = 'variantActive' . $variantId;
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= $this->e($variant['variant_name']) ?></div>
                                <?php if ($barcode !== '' || $weight !== ''): ?>
                                    <div class="text-muted small">
                                        <?php if ($barcode !== ''): ?>Barkod: <?= $this->e($barcode) ?><?php endif; ?>
                                        <?php if ($barcode !== '' && $weight !== ''): ?> &middot; <?php endif; ?>
                                        <?php if ($weight !== ''): ?>Ağırlık: <?= $this->e($weight) ?><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= $this->e($variant['sku']) ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($hasDiscount): ?>
                                    <div class="fw-semibold text-success"><?= $this->e($money($discount)) ?></div>
                                    <div class="text-muted small text-decoration-line-through"><?= $this->e($money($price)) ?></div>
                                <?php else: ?>
                                    <?= $this->e($money($price)) ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if ($stock <= 0): ?>
                                    <span class="badge text-bg-danger">Tükendi</span>
                                <?php elseif ($stock <= $critical): ?>
                                    <?= $this->e((string) $stock) ?>
                                    <span class="badge text-bg-warning">Kritik</span>
                                <?php else: ?>
                                    <?= $this->e((string) $stock) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="<?= $this->e($switchId) ?>"
                                           data-variant-toggle
                                           data-url="<?= $this->e($this->url($basePath . '/' . $variantId . '/durum')) ?>"
                                           <?= $isActive ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="<?= $this->e($switchId) ?>"
                                           data-variant-label>
                                        <?= $isActive ? 'Satışta' : 'Kapalı' ?>
                                    </label>
                                </div>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="<?= $this->e($this->url($basePath . '/' . $variantId . '/duzenle')) ?>"
                                   class="btn btn-sm btn-outline-primary">Düzenle</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-muted small mt-3 mb-0">
        Varyantlar silinmez; satıştan kaldırmak için anahtarı kapatın. Stok değişiklikleri stok hareketleri ekranından yapılır.
    </p>
<?php endif; ?>

<script>
    (function () {
        'use strict';

        var tokenMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : '';

        function notify(message, type) {
            if (typeof window.showToast === 'function') {
                window.showToast(message, type);
            }
        }

        document.querySelectorAll('[data-variant-toggle]').forEach(function (input) {
            input.addEventListener('change', function () {
                var wanted = input.checked;
                var label = input.parentElement.querySelector('[data-variant-label]');
                var body = new URLSearchParams();
                body.set('is_active', wanted ? '1' : '0');

                input.disabled = true;

                fetch(input.getAttribute('data-url'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: body
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, data: data };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok || !result.data || result.data.success !== true) {
                            throw new Error((result.data && result.data.message) || 'İşlem başarısız.');
                        }

                        if (label) {
                            label.textContent = wanted ? 'Satışta' : 'Kapalı';
                        }
                        notify(result.data.message, 'success');
                    })
                    .catch(function (error) {
                        // Başarısızsa anahtarı eski haline döndür
                        input.checked = !wanted;
                        notify(error.message || 'İşlem başarısız.', 'danger');
                    })
                    .finally(function () {
                        input.disabled = false;
                    });
            });
        });
    })();
</script>