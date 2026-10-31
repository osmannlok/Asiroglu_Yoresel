<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli kategori listesi view'ı
 *
 * Admin\CategoryController::index() tarafından render edilir ve
 * layouts/admin.php içine yerleştirilir.
 *
 * Değişkenler:
 *   $result (zorunlu) ['items' => list, 'total' => int, 'page' => int,
 *                      'perPage' => int, 'totalPages' => int]
 *   $search (isteğe bağlı) arama metni
 *
 * Silme: her satırda CSRF korumalı gizli bir POST formu bulunur; "Sil" butonu
 * layout'taki ortak onay modalını açar (data-confirm-form).
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url(), $this->csrfField()
 *
 * Gereksinimler: FR-030, FR-031, FR-033, NFR-003, NFR-005.
 */

declare(strict_types=1);

$basePath = '/admin/kategoriler';

$items      = isset($result['items']) && is_array($result['items']) ? $result['items'] : [];
$total      = (int) ($result['total'] ?? 0);
$page       = max(1, (int) ($result['page'] ?? 1));
$totalPages = max(1, (int) ($result['totalPages'] ?? 1));
$search     = isset($search) ? (string) $search : '';

/**
 * Sayfa bağlantısı üretir; arama metni korunur.
 */
$pageUrl = function (int $targetPage) use ($basePath, $search): string {
    $query = ['page' => $targetPage];

    if ($search !== '') {
        $query['q'] = $search;
    }

    return $this->url($basePath . '?' . http_build_query($query));
};

// Sayfalama penceresi: geçerli sayfa ± 2
$windowStart = max(1, $page - 2);
$windowEnd   = min($totalPages, $page + 2);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 fw-bold mb-0">Kategoriler</h1>
        <p class="text-muted small mb-0">
            Toplam <?= $this->e((string) $total) ?> kategori
        </p>
    </div>
    <a href="<?= $this->e($this->url($basePath . '/olustur')) ?>" class="btn btn-success">
        Yeni Kategori
    </a>
</div>

<form method="get" action="<?= $this->e($this->url($basePath)) ?>" class="row g-2 mb-3" role="search">
    <div class="col-12 col-sm-8 col-md-6 col-lg-4">
        <label for="categorySearch" class="visually-hidden">Kategori ara</label>
        <input type="search" class="form-control" id="categorySearch" name="q"
               value="<?= $this->e($search) ?>" maxlength="100" placeholder="Kategori adı ara">
    </div>
    <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-outline-success">Ara</button>
        <?php if ($search !== ''): ?>
            <a href="<?= $this->e($this->url($basePath)) ?>" class="btn btn-outline-secondary">Temizle</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($items === []): ?>
    <div class="alert alert-info mb-0" role="note">
        <?= $search !== ''
            ? 'Aramanızla eşleşen kategori bulunamadı.'
            : 'Henüz kategori eklenmemiş. "Yeni Kategori" ile başlayabilirsiniz.' ?>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Ad</th>
                        <th scope="col">Slug</th>
                        <th scope="col">Üst Kategori</th>
                        <th scope="col" class="text-end">Sıra</th>
                        <th scope="col">Durum</th>
                        <th scope="col" class="text-end">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $row): ?>
                        <?php
                        $id         = (int) $row['id'];
                        $isActive   = (int) $row['is_active'] === 1;
                        $isFeatured = (int) $row['is_featured'] === 1;
                        $parentName = isset($row['parent_name']) ? (string) $row['parent_name'] : '';
                        $formId     = 'sil-' . $id;
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= $this->e($row['name']) ?></td>
                            <td class="text-muted small"><?= $this->e($row['slug']) ?></td>
                            <td><?= $parentName !== '' ? $this->e($parentName) : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-end"><?= $this->e((string) (int) $row['sort_order']) ?></td>
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
                                        data-confirm-message="<?= $this->e('"' . (string) $row['name'] . '" kategorisi silinsin mi?') ?>">
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