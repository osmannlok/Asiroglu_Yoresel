<?php

/**
 * Aşıroğlu Yöresel Market - Ana sayfa view'ı
 *
 * HomeController::index() tarafından render edilir ve layouts/main.php
 * içine yerleştirilir. Şimdilik statik karşılama içeriği gösterir;
 * öne çıkan ürünler ve kategoriler ürün modülüyle eklenecektir.
 *
 * Kullanılabilir yardımcılar: $this->e() (XSS kaçırma), $this->url() (site içi adres)
 */

declare(strict_types=1);

// Karşılama bölümündeki sabit metinler
$siteName = 'Aşıroğlu Yöresel Market';

// Güven kutuları: başlık ve açıklama (sabit metin, yine de çıktıda kaçırılır)
$features = [
    [
        'title' => 'Yöresel ve Doğal',
        'text'  => 'Türkiye\'nin dört bir yanından, üreticisinden seçilmiş yöresel lezzetler.',
    ],
    [
        'title' => 'Güvenli Ödeme',
        'text'  => 'Kart bilgileriniz sitemizde saklanmaz; ödemeler güvenli altyapı üzerinden alınır.',
    ],
    [
        'title' => 'Türkiye Geneli Kargo',
        'text'  => 'Siparişleriniz özenle paketlenir ve Türkiye\'nin her yerine gönderilir.',
    ],
];
?>
<section class="bg-white border-bottom py-5">
    <div class="container text-center py-lg-4">
        <h1 class="display-5 fw-bold text-success mb-3">
            <?= $this->e($siteName) ?>
        </h1>
        <p class="lead text-muted mx-auto mb-0" style="max-width: 640px;">
            Yöresel lezzetler kapınızda. Doğal ve yöresel gıda ürünlerini güvenle sipariş edin.
        </p>
    </div>
</section>

<section class="container py-5">
    <div class="row g-4">
        <?php foreach ($features as $feature): ?>
            <div class="col-12 col-md-4">
                <div class="card h-100 border-0 shadow-sm text-center">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-semibold text-success mb-3">
                            <?= $this->e($feature['title']) ?>
                        </h2>
                        <p class="text-muted mb-0">
                            <?= $this->e($feature['text']) ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>