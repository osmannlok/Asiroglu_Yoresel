<?php

/**
 * Aşıroğlu Yöresel Market - Hesabım: sipariş listesi view'ı
 *
 * AccountOrderController::index() tarafından render edilir ve layouts/main.php
 * içine yerleştirilir.
 *
 * Değişkenler:
 *   $result              ['items' => list, 'total', 'page', 'perPage', 'totalPages']
 *                        Satır: id, order_number, status, payment_status,
 *                        grand_total, currency, created_at, item_count
 *   $statusLabels        sipariş durumu => görünen ad
 *   $paymentStatusLabels ödeme durumu => görünen ad
 *   $basePath            /hesabim/siparisler
 *
 * Fiyatlar KDV dahil, TRY. Sipariş detayı /hesabim/siparisler/{id} adresindedir.
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url()
 *
 * Gereksinimler: FR-055, FR-066, NFR-005.
 */

declare(strict_types=1);

$basePath = isset($basePath) ? (string) $basePath : '/hesabim/siparisler';

$items      = isset($result['items']) && is_array($result['items']) ? $result['items'] : [];
$total      = (int) ($result['total'] ?? 0);
$page       = max(1, (int) ($result['page'] ?? 1));
$totalPages = max(1, (int) ($result['totalPages'] ?? 1));

$statusLabels        = isset($statusLabels) && is_array($statusLabels) ? $statusLabels : [];
$paymentStatusLabels = isset($paymentStatusLabels) && is_array($paymentStatusLabels) ? $paymentStatusLabels : [];

/** Sipariş durumu => Bootstrap rozet rengi */
$statusColors = [
    'pending'          => 'secondary',
    'awaiting_payment' => 'warning',
    'paid'             => 'info',
    'preparing'        => 'info',
    'shipped'          => 'primary',
    'delivered'        => 'success',
    'cancelled'        => 'dark',
    'payment_failed'   => 'danger',
    'returned'         => 'secondary',
];

/** Tutarı Türkçe biçimde yazar: 1.250,50 ₺ */
$money = static fn (mixed $amount): string => number_format((float) $amount, 2, ',', '.') . ' ₺';

/** "2026-11-01 14:30:00" => "01.11.2026 14:30"; çözülemezse ham metin */
$dateText = static function (mixed $value): string {
    $time = strtotime((string) $value);

    return $time === false ? (string) $value : date('d.m.Y H:i', $time);
};

/**
 * Sayfa bağlantısı üretir.
 */
$pageUrl = function (int $targetPage) use ($basePath): string {
    return $this->url($basePath . '?' . http_build_query(['page' => $targetPage]));
};

// Sayfalama penceresi: geçerli sayfa ± 2
$windowStart = max(1, $page - 2);
$windowEnd   = min($totalPages, $page + 2);
?>
<div class="container py-4 py-lg-5">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Siparişlerim</h1>
            <p class="text-muted small mb-0"><?= $this->e((string) $total) ?> sipariş</p>
        </div>
        <a href="<?= $this->e($this->url('/urunler')) ?>" class="btn btn-outline-success btn-sm">Alışverişe Devam Et</a>
    </div>

    <?php if ($items === []): ?>
        <div class="card border-0 shadow-sm text-center py-5">
            <div class="card-body">
                <h2 class="h5 fw-semibold mb-2">Henüz siparişiniz yok</h2>
                <p class="text-muted mb-4">Verdiğiniz siparişler burada listelenir.</p>
                <a href="<?= $this->e($this->url('/urunler')) ?>" class="btn btn-success px-4">Alışverişe Başla</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Sipariş No</th>
                            <th scope="col">Tarih</th>
                            <th scope="col" class="text-end">Adet</th>
                            <th scope="col" class="text-end">Tutar</th>
                            <th scope="col">Durum</th>
                            <th scope="col">Ödeme</th>
                            <th scope="col" class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $row): ?>
                            <?php
                            $id            = (int) $row['id'];
                            $status        = (string) $row['status'];
                            $paymentStatus = (string) $row['payment_status'];
                            $statusLabel   = $statusLabels[$status] ?? $status;
                            $paymentLabel  = $paymentStatusLabels[$paymentStatus] ?? $paymentStatus;
                            $statusColor   = $statusColors[$status] ?? 'secondary';
                            $detailUrl     = $this->url($basePath . '/' . $id);
                            ?>
                            <tr>
                                <td class="fw-semibold text-nowrap">
                                    <a href="<?= $this->e($detailUrl) ?>" class="text-decoration-none text-success">
                                        <?= $this->e($row['order_number']) ?>
                                    </a>
                                </td>
                                <td class="text-nowrap small"><?= $this->e($dateText($row['created_at'])) ?></td>
                                <td class="text-end"><?= $this->e((string) (int) $row['item_count']) ?></td>
                                <td class="text-end text-nowrap fw-semibold"><?= $this->e($money($row['grand_total'])) ?></td>
                                <td>
                                    <span class="badge text-bg-<?= $this->e($statusColor) ?>">
                                        <?= $this->e($statusLabel) ?>
                                    </span>
                                </td>
                                <td class="small text-nowrap"><?= $this->e($paymentLabel) ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= $this->e($detailUrl) ?>" class="btn btn-sm btn-outline-success">Detay</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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