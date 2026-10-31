<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli stok kalemleri view'ı
 *
 * Admin\StockController::index() tarafından render edilir ve
 * layouts/admin.php içine yerleştirilir.
 *
 * Değişkenler:
 *   $items       (zorunlu) StockMovement::getStockItems() satırları:
 *                product_id, variant_id, product_name, variant_name, sku,
 *                stock_quantity, critical_stock_quantity
 *   $search      (isteğe bağlı) arama metni (ürün adı veya SKU)
 *   $formPath    (zorunlu) /admin/stok/hareket
 *   $historyPath (zorunlu) /admin/stok/gecmis
 *   $listPath    (zorunlu) /admin/stok
 *
 * Varyantlı üründe satırlar varyantlardır; stok varyant düzeyinde yönetilir.
 * Stok bu ekranda değiştirilemez; "Hareket" bağlantısı formu ön seçili açar.
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url()
 *
 * Gereksinimler: FR-024, FR-026, FR-097, NFR-005.
 */

declare(strict_types=1);

/** getStockItems() içindeki en yüksek kalem sayısı */
const STOCK_LIST_LIMIT = 200;

$items       = isset($items) && is_array($items) ? $items : [];
$search      = isset($search) ? (string) $search : '';
$formPath    = (string) ($formPath ?? '/admin/stok/hareket');
$historyPath = (string) ($historyPath ?? '/admin/stok/gecmis');
$listPath    = (string) ($listPath ?? '/admin/stok');

// Özet sayaçları
$outCount      = 0;
$criticalCount = 0;

foreach ($items as $item) {
    $stock    = (int) $item['stock_quantity'];
    $critical = (int) $item['critical_stock_quantity'];

    if ($stock <= 0) {
        $outCount++;
    } elseif ($stock <= $critical) {
        $criticalCount++;
    }
}

$limitReached = count($items) >= STOCK_LIST_LIMIT;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 fw-bold mb-0">Stok</h1>
        <p class="text-muted small mb-0">
            <?= $this->e((string) count($items)) ?> kalem &middot;
            <?= $this->e((string) $outCount) ?> tükendi &middot;
            <?= $this->e((string) $criticalCount) ?> kritik
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= $this->e($this->url($historyPath)) ?>" class="btn btn-outline-secondary">Hareket Geçmişi</a>
        <a href="<?= $this->e($this->url($formPath)) ?>" class="btn btn-success">Yeni Stok Hareketi</a>
    </div>
</div>

<form method="get" action="<?= $this->e($this->url($listPath)) ?>" class="row g-2 mb-3" role="search">
    <div class="col-12 col-sm-8 col-md-6 col-lg-4">
        <label for="stockSearch" class="visually-hidden">Stok ara</label>
        <input type="search" class="form-control" id="stockSearch" name="q"
               value="<?= $this->e($search) ?>" maxlength="100" placeholder="Ürün adı veya SKU ara">
    </div>
    <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-outline-success">Ara</button>
        <?php if ($search !== ''): ?>
            <a href="<?= $this->e($this->url($listPath)) ?>" class="btn btn-outline-secondary">Temizle</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($limitReached): ?>
    <div class="alert alert-warning" role="note">
        İlk <?= $this->e((string) STOCK_LIST_LIMIT) ?> kalem gösteriliyor. Diğerlerine ulaşmak için arama yapın.
    </div>
<?php endif; ?>

<?php if ($items === []): ?>
    <div class="alert alert-info mb-0" role="note">
        <?= $search !== ''
            ? 'Aramanızla eşleşen ürün bulunamadı.'
            : 'Henüz ürün eklenmemiş. Stok yönetimi için önce ürün ekleyin.' ?>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Ürün</th>
                        <th scope="col">SKU</th>
                        <th scope="col" class="text-end">Stok</th>
                        <th scope="col" class="text-end">Kritik Eşik</th>
                        <th scope="col">Durum</th>
                        <th scope="col" class="text-end">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $productId   = (int) $item['product_id'];
                        $variantId   = $item['variant_id'] === null ? 0 : (int) $item['variant_id'];
                        $stock       = (int) $item['stock_quantity'];
                        $critical    = (int) $item['critical_stock_quantity'];
                        $variantName = isset($item['variant_name']) ? (string) $item['variant_name'] : '';

                        // Hareket formu ve geçmiş için sorgu dizesi (yalnızca tamsayılar)
                        $query = ['urun' => $productId];

                        if ($variantId > 0) {
                            $query['varyant'] = $variantId;
                        }

                        $queryString = http_build_query($query);
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= $this->e($item['product_name']) ?></div>
                                <?php if ($variantName !== ''): ?>
                                    <div class="text-muted small">Varyant: <?= $this->e($variantName) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= $this->e($item['sku']) ?></td>
                            <td class="text-end text-nowrap fw-semibold"><?= $this->e((string) $stock) ?></td>
                            <td class="text-end text-nowrap text-muted"><?= $this->e((string) $critical) ?></td>
                            <td>
                                <?php if ($stock <= 0): ?>
                                    <span class="badge text-bg-danger">Tükendi</span>
                                <?php elseif ($stock <= $critical): ?>
                                    <span class="badge text-bg-warning">Kritik</span>
                                <?php else: ?>
                                    <span class="badge text-bg-success">Yeterli</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="<?= $this->e($this->url($formPath . '?' . $queryString)) ?>"
                                   class="btn btn-sm btn-outline-primary">Hareket</a>
                                <a href="<?= $this->e($this->url($historyPath . '?' . $queryString)) ?>"
                                   class="btn btn-sm btn-outline-secondary">Geçmiş</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-muted small mt-3 mb-0">
        Varyantlı ürünlerde stok varyant düzeyinde yönetilir. Kritik eşik ürünün eşiğidir.
    </p>
<?php endif; ?>