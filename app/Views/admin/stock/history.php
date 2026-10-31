<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli stok hareket geçmişi view'ı
 *
 * Admin\StockController::history() tarafından render edilir ve
 * layouts/admin.php içine yerleştirilir.
 *
 * Değişkenler:
 *   $result      (zorunlu) ['items' => list, 'total' => int, 'page' => int,
 *                           'perPage' => int, 'totalPages' => int]
 *   $productId   (int|null) ürün filtresi
 *   $variantId   (int|null) varyant filtresi
 *   $type        (string) hareket türü filtresi ('' = tümü)
 *   $types       (list<string>) geçerli hareket türleri
 *   $formPath    (string) /admin/stok/hareket
 *   $listPath    (string) /admin/stok
 *   $historyPath (string) /admin/stok/gecmis
 *
 * Hareket yönü, yeni ve önceki stok farkından bulunur (düzeltme dahil).
 * Kayıtlar yalnızca okunur; hareketler değiştirilemez ve silinemez.
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url()
 *
 * Gereksinimler: FR-023, FR-026, NFR-005.
 */

declare(strict_types=1);

$basePath    = (string) ($historyPath ?? '/admin/stok/gecmis');
$formPath    = (string) ($formPath ?? '/admin/stok/hareket');
$listPath    = (string) ($listPath ?? '/admin/stok');
$types       = isset($types) && is_array($types) ? $types : [];

$items       = isset($result['items']) && is_array($result['items']) ? $result['items'] : [];
$total       = (int) ($result['total'] ?? 0);
$page        = max(1, (int) ($result['page'] ?? 1));
$totalPages  = max(1, (int) ($result['totalPages'] ?? 1));

$productId   = isset($productId) && $productId !== null ? (int) $productId : 0;
$variantId   = isset($variantId) && $variantId !== null ? (int) $variantId : 0;
$type        = isset($type) ? (string) $type : '';

$hasFilter   = $productId > 0 || $variantId > 0 || $type !== '';

/** Hareket türü => [görünen ad, rozet rengi] */
$typeMeta = [
    'incoming'    => ['Giriş', 'success'],
    'outgoing'    => ['Çıkış', 'danger'],
    'return'      => ['İade girişi', 'info'],
    'correction'  => ['Düzeltme', 'warning'],
    'reservation' => ['Rezervasyon', 'secondary'],
];

/**
 * Sayfa bağlantısı üretir; filtreler korunur.
 */
$pageUrl = function (int $targetPage) use ($basePath, $productId, $variantId, $type): string {
    $query = ['page' => $targetPage];

    if ($productId > 0) {
        $query['urun'] = $productId;
    }

    if ($variantId > 0) {
        $query['varyant'] = $variantId;
    }

    if ($type !== '') {
        $query['tur'] = $type;
    }

    return $this->url($basePath . '?' . http_build_query($query));
};

// Sayfalama penceresi: geçerli sayfa ± 2
$windowStart = max(1, $page - 2);
$windowEnd   = min($totalPages, $page + 2);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 fw-bold mb-0">Stok Hareketleri</h1>
        <p class="text-muted small mb-0">
            Toplam <?= $this->e((string) $total) ?> hareket
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= $this->e($this->url($listPath)) ?>" class="btn btn-outline-secondary">Stok Listesi</a>
        <a href="<?= $this->e($this->url($formPath)) ?>" class="btn btn-success">Yeni Stok Hareketi</a>
    </div>
</div>

<form method="get" action="<?= $this->e($this->url($basePath)) ?>" class="row g-2 mb-3">
    <?php if ($productId > 0): ?>
        <input type="hidden" name="urun" value="<?= $this->e((string) $productId) ?>">
    <?php endif; ?>
    <?php if ($variantId > 0): ?>
        <input type="hidden" name="varyant" value="<?= $this->e((string) $variantId) ?>">
    <?php endif; ?>

    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <label for="movementType" class="visually-hidden">Hareket türü</label>
        <select class="form-select" id="movementType" name="tur">
            <option value="">Tüm türler</option>
            <?php foreach ($types as $typeOption): ?>
                <?php $typeValue = (string) $typeOption; ?>
                <option value="<?= $this->e($typeValue) ?>"<?= $type === $typeValue ? ' selected' : '' ?>>
                    <?= $this->e($typeMeta[$typeValue][0] ?? $typeValue) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-outline-success">Filtrele</button>
        <?php if ($hasFilter): ?>
            <a href="<?= $this->e($this->url($basePath)) ?>" class="btn btn-outline-secondary">Filtreyi temizle</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($productId > 0 || $variantId > 0): ?>
    <div class="alert alert-secondary py-2 small" role="note">
        Liste, seçili ürün<?= $variantId > 0 ? '/varyant' : '' ?> için filtrelenmiştir.
    </div>
<?php endif; ?>

<?php if ($items === []): ?>
    <div class="alert alert-info mb-0" role="note">
        <?= $hasFilter
            ? 'Filtrenizle eşleşen stok hareketi bulunamadı.'
            : 'Henüz stok hareketi kaydedilmemiş.' ?>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Tarih</th>
                        <th scope="col">Ürün</th>
                        <th scope="col">Tür</th>
                        <th scope="col" class="text-end">Miktar</th>
                        <th scope="col" class="text-end">Stok</th>
                        <th scope="col">Neden</th>
                        <th scope="col">Kullanıcı</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $row): ?>
                        <?php
                        $movementType = (string) $row['movement_type'];
                        $meta         = $typeMeta[$movementType] ?? [$movementType, 'secondary'];
                        $previous     = (int) $row['previous_quantity'];
                        $new          = (int) $row['new_quantity'];
                        $moved        = (int) $row['quantity'];
                        $delta        = $new - $previous;
                        $sign         = $delta > 0 ? '+' : ($delta < 0 ? '−' : '');
                        $variantName  = isset($row['variant_name']) ? (string) $row['variant_name'] : '';
                        $note         = isset($row['note']) ? (string) $row['note'] : '';
                        $userName     = isset($row['user_name']) ? trim((string) $row['user_name']) : '';
                        $refType      = isset($row['reference_type']) ? (string) $row['reference_type'] : '';
                        $refId        = isset($row['reference_id']) ? (int) $row['reference_id'] : 0;
                        $amountClass  = $delta > 0 ? 'text-success' : ($delta < 0 ? 'text-danger' : 'text-muted');
                        ?>
                        <tr>
                            <td class="text-nowrap small"><?= $this->e((string) $row['created_at']) ?></td>
                            <td>
                                <div class="fw-semibold"><?= $this->e($row['product_name']) ?></div>
                                <div class="text-muted small">
                                    <?php if ($variantName !== ''): ?>
                                        <?= $this->e($variantName) ?> &middot; <?= $this->e($row['variant_sku'] ?? '') ?>
                                    <?php else: ?>
                                        SKU: <?= $this->e($row['product_sku']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= $this->e($meta[1]) ?>"><?= $this->e($meta[0]) ?></span>
                            </td>
                            <td class="text-end text-nowrap fw-semibold <?= $this->e($amountClass) ?>">
                                <?= $this->e($sign . (string) $moved) ?>
                            </td>
                            <td class="text-end text-nowrap small">
                                <?= $this->e((string) $previous) ?> &rarr; <strong><?= $this->e((string) $new) ?></strong>
                            </td>
                            <td class="small">
                                <?= $note !== '' ? $this->e($note) : '<span class="text-muted">—</span>' ?>
                                <?php if ($refType !== ''): ?>
                                    <div class="text-muted">
                                        Kaynak: <?= $this->e($refType) ?><?= $refId > 0 ? ' #' . $this->e((string) $refId) : '' ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-nowrap">
                                <?= $userName !== '' ? $this->e($userName) : '<span class="text-muted">Sistem</span>' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-3" aria-label="Sayfalama">
            <ul class="pagination justify-content-center flex-wrap mb-0">
                <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                    <a class="page-link" href="<?= $this->e($pageUrl(max(1, $page - 1))) ?>">Önceki</a>
                </li>

                <?php if ($windowStart > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= $this->e($pageUrl(1)) ?>">1</a></li>
                    <?php if ($windowStart > 2): ?>
                        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $windowStart; $i <= $windowEnd; $i++): ?>
                    <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                        <a class="page-link" href="<?= $this->e($pageUrl($i)) ?>"
                           <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $this->e((string) $i) ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($windowEnd < $totalPages): ?>
                    <?php if ($windowEnd < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= $this->e($pageUrl($totalPages)) ?>"><?= $this->e((string) $totalPages) ?></a>
                    </li>
                <?php endif; ?>

                <li class="page-item<?= $page >= $totalPages ? ' disabled' : '' ?>">
                    <a class="page-link" href="<?= $this->e($pageUrl(min($totalPages, $page + 1))) ?>">Sonraki</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>