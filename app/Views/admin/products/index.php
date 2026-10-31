<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli ürün listesi view'ı
 *
 * Admin\ProductController::index() tarafından render edilir ve
 * layouts/admin.php içine yerleştirilir.
 *
 * Değişkenler:
 *   $result     (zorunlu) ['items' => list, 'total' => int, 'page' => int,
 *                          'perPage' => int, 'totalPages' => int]
 *   $search     (isteğe bağlı) arama metni (ad veya SKU)
 *   $categoryId (isteğe bağlı) kategori filtresi (int|null)
 *   $status     (isteğe bağlı) '' | 'active' | 'passive'
 *   $categories (isteğe bağlı) kategori seçenekleri
 *
 * Silme: her satırda CSRF korumalı gizli bir POST formu bulunur; "Sil" butonu
 * layout'taki ortak onay modalını açar (data-confirm-form).
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url(), $this->csrfField()
 *
 * Gereksinimler: FR-010, FR-011, FR-024, FR-033, NFR-003, NFR-005.
 */

declare(strict_types=1);

$basePath = '/admin/urunler';

$items      = isset($result['items']) && is_array($result['items']) ? $result['items'] : [];
$total      = (int) ($result['total'] ?? 0);
$page       = max(1, (int) ($result['page'] ?? 1));
$totalPages = max(1, (int) ($result['totalPages'] ?? 1));
$search     = isset($search) ? (string) $search : '';
$categoryId = isset($categoryId) ? (int) $categoryId : 0;
$status     = isset($status) ? (string) $status : '';
$categories = isset($categories) && is_array($categories) ? $categories : [];

$hasFilter = $search !== '' || $categoryId > 0 || $status !== '';

/**
 * Sayfa bağlantısı üretir; arama ve filtreler korunur.
 */
$pageUrl = function (int $targetPage) use ($basePath, $search, $categoryId, $status): string {
    $query = ['page' => $targetPage];

    if ($search !== '') {
        $query['q'] = $search;
    }

    if ($categoryId > 0) {
        $query['kategori'] = $categoryId;
    }

    if ($status !== '') {
        $query['durum'] = $status;
    }

    return $this->url($basePath . '?' . http_build_query($query));
};

/** Tutarı Türkçe biçimde yazar: 1.250,50 ₺ */
$money = static fn (mixed $amount): string => number_format((float) $amount, 2, ',', '.') . ' ₺';

// Sayfalama penceresi: geçerli sayfa ± 2
$windowStart = max(1, $page - 2);
$windowEnd   = min($totalPages, $page + 2);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 fw-bold mb-0">Ürünler</h1>
        <p class="text-muted small mb-0">
            Toplam <?= $this->e((string) $total) ?> ürün
        </p>
    </div>
    <a href="<?= $this->e($this->url($basePath . '/olustur')) ?>" class="btn btn-success">
        Yeni Ürün
    </a>
</div>

<form method="get" action="<?= $this->e($this->url($basePath)) ?>" class="row g-2 mb-3" role="search">
    <div class="col-12 col-md-5 col-lg-4">
        <label for="productSearch" class="visually-hidden">Ürün ara</label>
        <input type="search" class="form-control" id="productSearch" name="q"
               value="<?= $this->e($search) ?>" maxlength="100" placeholder="Ürün adı veya SKU ara">
    </div>
    <div class="col-6 col-md-3 col-lg-3">
        <label for="productCategory" class="visually-hidden">Kategori</label>
        <select class="form-select" id="productCategory" name="kategori">
            <option value="">Tüm kategoriler</option>
            <?php foreach ($categories as $category): ?>
                <?php $optionId = (int) $category['id']; ?>
                <option value="<?= $this->e((string) $optionId) ?>"<?= $categoryId === $optionId ? ' selected' : '' ?>>
                    <?= $this->e($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-2 col-lg-2">
        <label for="productStatus" class="visually-hidden">Durum</label>
        <select class="form-select" id="productStatus" name="durum">
            <option value="">Tüm durumlar</option>
            <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Aktif</option>
            <option value="passive"<?= $status === 'passive' ? ' selected' : '' ?>>Pasif</option>
        </select>
    </div>
    <div class="col-12 col-md-auto d-flex gap-2">
        <button type="submit" class="btn btn-outline-success">Filtrele</button>
        <?php if ($hasFilter): ?>
            <a href="<?= $this->e($this->url($basePath)) ?>" class="btn btn-outline-secondary">Temizle</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($items === []): ?>
    <div class="alert alert-info mb-0" role="note">
        <?= $hasFilter
            ? 'Aramanızla eşleşen ürün bulunamadı.'
            : 'Henüz ürün eklenmemiş. "Yeni Ürün" ile başlayabilirsiniz.' ?>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Ürün</th>
                        <th scope="col">Kategori</th>
                        <th scope="col">Marka</th>
                        <th scope="col" class="text-end">Fiyat</th>
                        <th scope="col" class="text-end">Stok</th>
                        <th scope="col">Durum</th>
                        <th scope="col" class="text-end">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $row): ?>
                        <?php
                        $id           = (int) $row['id'];
                        $isActive     = (int) $row['is_active'] === 1;
                        $isFeatured   = (int) $row['is_featured'] === 1;
                        $variantCount = (int) ($row['variant_count'] ?? 0);
                        $stock        = (int) $row['stock_quantity'];
                        $critical     = (int) $row['critical_stock_quantity'];
                        $brandName    = isset($row['brand_name']) ? (string) $row['brand_name'] : '';
                        $price        = (float) $row['price'];
                        $discount     = $row['discount_price'] ?? null;
                        $hasDiscount  = $discount !== null && $discount !== '' && (float) $discount < $price;
                        $formId       = 'sil-' . $id;
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= $this->e($row['name']) ?></div>
                                <div class="text-muted small">SKU: <?= $this->e($row['sku']) ?></div>
                            </td>
                            <td><?= $this->e($row['category_name']) ?></td>
                            <td><?= $brandName !== '' ? $this->e($brandName) : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($hasDiscount): ?>
                                    <div class="fw-semibold text-success"><?= $this->e($money($discount)) ?></div>
                                    <div class="text-muted small text-decoration-line-through"><?= $this->e($money($price)) ?></div>
                                <?php else: ?>
                                    <?= $this->e($money($price)) ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if ($variantCount > 0): ?>
                                    <span class="badge text-bg-light border">Varyantlı (<?= $this->e((string) $variantCount) ?>)</span>
                                <?php elseif ($stock <= 0): ?>
                                    <span class="badge text-bg-danger">Tükendi</span>
                                <?php elseif ($stock <= $critical): ?>
                                    <?= $this->e((string) $stock) ?>
                                    <span class="badge text-bg-warning">Kritik</span>
                                <?php else: ?>
                                    <?= $this->e((string) $stock) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= $isActive ? 'success' : 'secondary' ?>">
                                    <?= $isActive ? 'Aktif' : 'Pasif' ?>
                                </span>
                                <?php if ($isFeatured): ?>
                                    <span class="badge text-bg-warning">Öne çıkan</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="<?= $this->e($this->url($basePath . '/' . $id . '/duzenle')) ?>"
                                   class="btn btn-sm btn-outline-primary">Düzenle</a>

                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        data-confirm-form="<?= $this->e($formId) ?>"
                                        data-confirm-message="<?= $this->e('"' . (string) $row['name'] . '" ürünü silinsin mi?') ?>">
                                    Sil
                                </button>

                                <form id="<?= $this->e($formId) ?>" method="post" class="d-none"
                                      action="<?= $this->e($this->url($basePath . '/' . $id . '/sil')) ?>">
                                    <?= $this->csrfField() ?>
                                </form>
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