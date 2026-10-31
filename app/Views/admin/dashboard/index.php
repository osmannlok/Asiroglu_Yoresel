<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli genel bakış view'ı
 *
 * Admin\DashboardController::index() tarafından render edilir ve
 * layouts/admin.php içine yerleştirilir.
 *
 * Değişkenler:
 *   $firstName (isteğe bağlı) giriş yapan kullanıcının adı
 *   $stats     (isteğe bağlı) ['categories' => int, 'brands' => int]
 *
 * Şimdilik yalnızca kategori ve marka sayıları gösterilir. Ürün, sipariş ve
 * kritik stok kartları ilgili modeller yazıldığında eklenecektir.
 *
 * Kullanılabilir yardımcılar: $this->e() (XSS kaçırma), $this->url() (site içi adres)
 *
 * Gereksinimler: FR-090, FR-091, NFR-005.
 */

declare(strict_types=1);

$firstName = isset($firstName) ? trim((string) $firstName) : '';

// Sayaç kartları: etiket => değer (hepsi tamsayıya zorlanır)
$stats      = isset($stats) && is_array($stats) ? $stats : [];
$statCards  = [
    ['label' => 'Kategoriler', 'value' => (int) ($stats['categories'] ?? 0)],
    ['label' => 'Markalar',    'value' => (int) ($stats['brands'] ?? 0)],
];
?>
<div class="mb-4">
    <h1 class="h3 fw-bold mb-1">
        <?= $firstName !== '' ? 'Hoş geldiniz, ' . $this->e($firstName) : 'Hoş geldiniz' ?>
    </h1>
    <p class="text-muted mb-0">Mağazanızın genel durumuna buradan göz atabilirsiniz.</p>
</div>

<div class="row g-3 g-lg-4">
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

<div class="alert alert-info mt-4 mb-0" role="note">
    Ürün, sipariş ve stok özetleri, ilgili modüller tamamlandıkça bu sayfada görünecektir.
</div>