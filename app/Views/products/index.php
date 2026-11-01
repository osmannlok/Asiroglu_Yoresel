<?php

/**
 * Aşıroğlu Yöresel Market - Vitrin ürün listesi view'ı
 *
 * ProductController::index() tarafından render edilir ve layouts/main.php
 * içine yerleştirilir.
 *
 * Değişkenler:
 *   $result       ['items' => list, 'total', 'page', 'perPage', 'totalPages']
 *   $search       arama metni
 *   $categoryId   int|null seçili kategori
 *   $minPrice     float|null
 *   $maxPrice     float|null
 *   $sort         seçili sıralama anahtarı
 *   $sorts        list<string> geçerli sıralama anahtarları
 *   $categories   Category::getActiveTree() (children ile)
 *   $filterErrors list<string> yok sayılan filtre uyarıları
 *
 * Sepete ekleme (varyantsız ve stokta ürün) Fetch ile POST /sepet/ekle
 * yapılır; varyantlı ürünler detay sayfasına yönlendirilir.
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url()
 *
 * Gereksinimler: FR-002, FR-003, FR-004, FR-005, FR-006, FR-043, NFR-005.
 */

declare(strict_types=1);

$basePath = '/urunler';

$items      = isset($result['items']) && is_array($result['items']) ? $result['items'] : [];
$total      = (int) ($result['total'] ?? 0);
$page       = max(1, (int) ($result['page'] ?? 1));
$totalPages = max(1, (int) ($result['totalPages'] ?? 1));

$search       = isset($search) ? (string) $search : '';
$categoryId   = isset($categoryId) ? (int) $categoryId : 0;
$minPrice     = isset($minPrice) && $minPrice !== null ? (float) $minPrice : null;
$maxPrice     = isset($maxPrice) && $maxPrice !== null ? (float) $maxPrice : null;
$sort         = isset($sort) ? (string) $sort : 'new';
$categories   = isset($categories) && is_array($categories) ? $categories : [];
$filterErrors = isset($filterErrors) && is_array($filterErrors) ? $filterErrors : [];

$sortLabels = [
    'new'        => 'En yeniler',
    'price_asc'  => 'Fiyat: düşükten yükseğe',
    'price_desc' => 'Fiyat: yüksekten düşüğe',
    'name'       => 'Ada göre (A-Z)',
];

$hasFilter = $search !== '' || $categoryId > 0 || $minPrice !== null || $maxPrice !== null;

/** Tutarı Türkçe biçimde yazar: 1.250,50 ₺ */
$money = static fn (mixed $amount): string => number_format((float) $amount, 2, ',', '.') . ' ₺';

/** Fiyat alanında gösterilecek değer (nokta ondalık ayırıcı) */
$priceValue = static fn (?float $value): string => $value === null ? '' : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

/**
 * Sayfa bağlantısı üretir; arama ve filtreler korunur.
 */
$pageUrl = function (int $targetPage) use ($basePath, $search, $categoryId, $minPrice, $maxPrice, $sort, $priceValue): string {
    $query = ['page' => $targetPage];

    if ($search !== '') {
        $query['q'] = $search;
    }

    if ($categoryId > 0) {
        $query['kategori'] = $categoryId;
    }

    if ($minPrice !== null) {
        $query['min'] = $priceValue($minPrice);
    }

    if ($maxPrice !== null) {
        $query['max'] = $priceValue($maxPrice);
    }

    if ($sort !== 'new') {
        $query['sirala'] = $sort;
    }

    return $this->url($basePath . '?' . http_build_query($query));
};

// Sayfalama penceresi: geçerli sayfa ± 2
$windowStart = max(1, $page - 2);
$windowEnd   = min($totalPages, $page + 2);
?>
<div class="container py-4 py-lg-5">

    <div class="mb-3">
        <h1 class="h3 fw-bold mb-1"><?= $search !== '' ? 'Arama sonuçları' : 'Ürünler' ?></h1>
        <p class="text-muted small mb-0"><?= $this->e((string) $total) ?> ürün bulundu</p>
    </div>

    <?php foreach ($filterErrors as $filterError): ?>
        <div class="alert alert-warning py-2 small" role="alert"><?= $this->e((string) $filterError) ?></div>
    <?php endforeach; ?>

    <!-- Sepet işlem bildirimi (JS yazar) -->
    <div id="listAlert" class="alert d-none" role="alert" aria-live="polite"></div>

    <!-- Filtre formu -->
    <form method="get" action="<?= $this->e($this->url($basePath)) ?>" class="card border-0 shadow-sm mb-4" role="search">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-4">
                    <label for="filterQ" class="form-label small mb-1">Ara</label>
                    <input type="search" class="form-control" id="filterQ" name="q" maxlength="100"
                           value="<?= $this->e($search) ?>" placeholder="Ürün adı, açıklama veya SKU">
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="filterCategory" class="form-label small mb-1">Kategori</label>
                    <select class="form-select" id="filterCategory" name="kategori">
                        <option value="">Tüm kategoriler</option>
                        <?php foreach ($categories as $category): ?>
                            <?php $catId = (int) $category['id']; ?>
                            <option value="<?= $this->e((string) $catId) ?>"<?= $categoryId === $catId ? ' selected' : '' ?>>
                                <?= $this->e($category['name']) ?>
                            </option>
                            <?php foreach ($category['children'] ?? [] as $child): ?>
                                <?php $childId = (int) $child['id']; ?>
                                <option value="<?= $this->e((string) $childId) ?>"<?= $categoryId === $childId ? ' selected' : '' ?>>
                                    &nbsp;&nbsp;&mdash; <?= $this->e($child['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-sm-3 col-lg-1">
                    <label for="filterMin" class="form-label small mb-1">En az (₺)</label>
                    <input type="text" class="form-control" id="filterMin" name="min" inputmode="decimal"
                           maxlength="13" value="<?= $this->e($priceValue($minPrice)) ?>">
                </div>

                <div class="col-6 col-sm-3 col-lg-1">
                    <label for="filterMax" class="form-label small mb-1">En çok (₺)</label>
                    <input type="text" class="form-control" id="filterMax" name="max" inputmode="decimal"
                           maxlength="13" value="<?= $this->e($priceValue($maxPrice)) ?>">
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="filterSort" class="form-label small mb-1">Sıralama</label>
                    <select class="form-select" id="filterSort" name="sirala">
                        <?php foreach ($sortLabels as $sortKey => $sortLabel): ?>
                            <option value="<?= $this->e($sortKey) ?>"<?= $sort === $sortKey ? ' selected' : '' ?>>
                                <?= $this->e($sortLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="submit" class="btn btn-success px-4">Filtrele</button>
                <?php if ($hasFilter || $sort !== 'new'): ?>
                    <a href="<?= $this->e($this->url($basePath)) ?>" class="btn btn-outline-secondary">Temizle</a>
                <?php endif; ?>
            </div>
            <div class="form-text">Fiyatları nokta ile yazın (örn. 850.50).</div>
        </div>
    </form>

    <?php if ($items === []): ?>
        <div class="alert alert-info mb-0" role="note">
            <?= $hasFilter
                ? 'Aramanızla eşleşen ürün bulunamadı. Filtreleri değiştirip tekrar deneyin.'
                : 'Henüz satışta ürün bulunmuyor.' ?>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3 g-lg-4">
            <?php foreach ($items as $item): ?>
                <?php
                $productId   = (int) $item['id'];
                $slug        = (string) $item['slug'];
                $detailUrl   = $this->url('/urun/' . $slug);
                $imagePath   = isset($item['image_path']) ? (string) $item['image_path'] : '';
                $hasVariants = (int) ($item['variant_count'] ?? 0) > 0;
                $inStock     = (int) ($item['in_stock'] ?? 0) === 1;
                $display     = (float) $item['display_price'];
                $old         = $item['old_price'] ?? null;
                $hasOld      = $old !== null && (float) $old > $display;
                $discountPct = $hasOld ? (int) round((1 - $display / (float) $old) * 100) : 0;
                ?>
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm">
                        <a href="<?= $this->e($detailUrl) ?>" class="position-relative d-block text-decoration-none">
                            <div class="ratio ratio-4x3 bg-light">
                                <?php if ($imagePath !== ''): ?>
                                    <img src="<?= $this->e($this->url('/uploads/' . $imagePath)) ?>"
                                         alt="<?= $this->e($item['name']) ?>"
                                         class="card-img-top object-fit-contain" loading="lazy">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center text-muted small">
                                        Görsel yok
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($hasOld && $discountPct > 0): ?>
                                <span class="badge text-bg-danger position-absolute top-0 start-0 m-2">
                                    %<?= $this->e((string) $discountPct) ?> indirim
                                </span>
                            <?php endif; ?>
                        </a>

                        <div class="card-body d-flex flex-column">
                            <div class="small text-muted mb-1"><?= $this->e($item['category_name']) ?></div>
                            <h2 class="h6 fw-semibold mb-2">
                                <a href="<?= $this->e($detailUrl) ?>" class="text-decoration-none text-dark">
                                    <?= $this->e($item['name']) ?>
                                </a>
                            </h2>

                            <div class="mb-3">
                                <?php if ($hasVariants): ?>
                                    <span class="small text-muted">Başlayan fiyat</span>
                                <?php endif; ?>
                                <div class="d-flex flex-wrap align-items-baseline gap-2">
                                    <span class="fs-5 fw-bold text-success"><?= $this->e($money($display)) ?></span>
                                    <?php if ($hasOld): ?>
                                        <span class="small text-muted text-decoration-line-through"><?= $this->e($money($old)) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mt-auto">
                                <?php if (!$inStock): ?>
                                    <span class="badge text-bg-secondary fs-6 w-100 py-2">Tükendi</span>
                                <?php elseif ($hasVariants): ?>
                                    <a href="<?= $this->e($detailUrl) ?>" class="btn btn-outline-success w-100">Seçenekleri Gör</a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-success w-100"
                                            data-add-to-cart
                                            data-product-id="<?= $this->e((string) $productId) ?>">
                                        Sepete Ekle
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="Sayfalama">
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
</div>

<script>
    (function () {
        'use strict';

        var alertBox = document.getElementById('listAlert');
        var tokenMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : '';
        var addUrl = <?= json_encode($this->url('/sepet/ekle'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;

        if (!alertBox) {
            return;
        }

        // Bildirim: textContent ile yazılır (XSS güvenli)
        function notify(message, type) {
            alertBox.className = 'alert alert-' + type;
            alertBox.textContent = String(message);
        }

        document.querySelectorAll('[data-add-to-cart]').forEach(function (button) {
            var originalText = button.textContent;

            button.addEventListener('click', function () {
                button.disabled = true;
                button.textContent = 'Ekleniyor...';

                fetch(addUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        product_id: parseInt(button.getAttribute('data-product-id'), 10),
                        quantity: 1
                    })
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
                        button.disabled = false;
                        button.textContent = originalText;
                    });
            });
        });
    })();
</script>