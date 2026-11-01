<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli genel bakış view'ı
 *
 * Admin\DashboardController::index() tarafından render edilir ve
 * layouts/admin.php içine yerleştirilir.
 *
 * Değişkenler:
 *   $firstName (isteğe bağlı) giriş yapan kullanıcının adı
 *   $stats     (isteğe bağlı) ['categories' => int, 'brands' => int, 'products' => int]
 *   $lowStock  (isteğe bağlı) Product::getLowStock() satırları
 *   $stockPath (isteğe bağlı) stok sayfasının yolu
 *
 * Kritik stok listesi yalnızca varyantsız ürünleri kapsar; varyantlı
 * ürünlerin stoğu varyant düzeyinde izlenir.
 *
 * Kullanılabilir yardımcılar: $this->e() (XSS kaçırma), $this->url() (site içi adres)
 *
 * Gereksinimler: FR-024, FR-090, FR-091, FR-097, NFR-005.
 */

declare(strict_types=1);

$firstName = isset($firstName) ? trim((string) $firstName) : '';
$stockPath = isset($stockPath) ? (string) $stockPath : '/admin/stok';
$lowStock  = isset($lowStock) && is_array($lowStock) ? $lowStock : [];

// Sayaç kartları: etiket => değer (hepsi tamsayıya zorlanır)
$stats     = isset($stats) && is_array($stats) ? $stats : [];
$statCards = [
    ['label' => 'Kategoriler', 'value' => (int) ($stats['categories'] ?? 0)],
    ['label' => 'Markalar',    'value' => (int) ($stats['brands'] ?? 0)],
    ['label' => 'Ürünler',     'value' => (int) ($stats['products'] ?? 0)],
];
?>
<div class="mb-4">
    <h1 class="h3 fw-bold mb-1">
        <?= $firstName !== '' ? 'Hoş geldiniz, ' . $this->e($firstName) : 'Hoş geldiniz' ?>
    </h1>
    <p class="text-muted mb-0">Mağazanızın genel durumuna buradan göz atabilirsiniz.</p>
</div>

<div class="row g-3 g-lg-4 mb-4">
    <?php foreach ($statCards as $card): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1"><?= $this->e($card['label']) ?></p>
                    <p class="display-6 fw-bold text-success mb-0">
                        <?= $this->e(number_format($card['value'], 0, ',', '.')) ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Kritik stok uyarıları (FR-024, FR-097) -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="h5 fw-semibold mb-0">Kritik Stok Uyarıları</h2>
            <a href="<?= $this->e($this->url($stockPath)) ?>" class="btn btn-sm btn-outline-secondary">
                Tüm Stok
            </a>
        </div>

        <?php if ($lowStock === []): ?>
            <div class="alert alert-success mb-0" role="note">
                Kritik seviyede veya tükenmiş ürün yok.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Ürün</th>
                            <th scope="col">SKU</th>
                            <th scope="col" class="text-end">Stok</th>
                            <th scope="col" class="text-end">Eşik</th>
                            <th scope="col">Durum</th>
                            <th scope="col" class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStock as $row): ?>
                            <?php
                            $productId = (int) $row['id'];
                            $stock     = (int) $row['stock_quantity'];
                            $critical  = (int) $row['critical_stock_quantity'];
                            $moveUrl   = $stockPath . '/hareket?' . http_build_query(['urun' => $productId]);
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= $this->e($row['name']) ?></td>
                                <td class="text-nowrap"><?= $this->e($row['sku']) ?></td>
                                <td class="text-end fw-semibold"><?= $this->e((string) $stock) ?></td>
                                <td class="text-end text-muted"><?= $this->e((string) $critical) ?></td>
                                <td>
                                    <?php if ($stock <= 0): ?>
                                        <span class="badge text-bg-danger">Tükendi</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-warning">Kritik</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= $this->e($this->url($moveUrl)) ?>"
                                       class="btn btn-sm btn-outline-primary">Hareket</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <p class="text-muted small mt-3 mb-0">
            Liste varyantsız ürünleri kapsar. Varyantlı ürünlerin stoğu varyant düzeyinde,
            stok sayfasından izlenir.
        </p>
    </div>
</div>

<div class="alert alert-info mt-4 mb-0" role="note">
    Sipariş özetleri, ilgili modüller tamamlandıkça bu sayfada görünecektir.
</div>