<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli stok hareketi formu view'ı
 *
 * Admin\StockController::create() tarafından render edilir ve
 * layouts/admin.php içine yerleştirilir.
 *
 * Değişkenler:
 *   $values      (array) form değerleri: item, movement_type, quantity, note
 *   $errors      (array) alan => hata mesajı
 *   $items       (list) StockMovement::getStockItems() satırları
 *   $types       (list<string>) formdan kabul edilen hareket türleri
 *   $formAction  (string) formun gönderileceği yol
 *   $listPath    (string) stok kalemleri sayfasının yolu
 *   $historyPath (string) hareket geçmişinin yolu
 *
 * Kalem değeri "p{ürün}" (varyantsız) veya "p{ürün}v{varyant}" biçimindedir.
 * "Düzeltme" türünde miktar, hedef stok adedi anlamına gelir.
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url(), $this->csrfField()
 *
 * Gereksinimler: FR-023, FR-026, NFR-003, NFR-004, NFR-005.
 */

declare(strict_types=1);

$values      = isset($values) && is_array($values) ? $values : [];
$errors      = isset($errors) && is_array($errors) ? $errors : [];
$items       = isset($items) && is_array($items) ? $items : [];
$types       = isset($types) && is_array($types) ? $types : [];
$formAction  = isset($formAction) ? (string) $formAction : '/admin/stok/hareket';
$listPath    = isset($listPath) ? (string) $listPath : '/admin/stok';
$historyPath = isset($historyPath) ? (string) $historyPath : '/admin/stok/gecmis';

/** Form değerini metin olarak döndürür. */
$val = static fn (string $key): string => isset($values[$key]) ? (string) $values[$key] : '';

/** Alan hatalıysa Bootstrap sınıfını döndürür. */
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' is-invalid' : '';

/** Alan hata mesajını (varsa) döndürür. */
$error = static fn (string $key): string => isset($errors[$key]) ? (string) $errors[$key] : '';

/** Hareket türü => görünen ad */
$typeLabels = [
    'incoming'   => 'Stok girişi (+)',
    'outgoing'   => 'Stok çıkışı (−)',
    'return'     => 'İade girişi (+)',
    'correction' => 'Düzeltme (hedef stok)',
];

$selectedItem = $val('item');
$selectedType = $val('movement_type');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 fw-bold mb-0">Yeni Stok Hareketi</h1>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= $this->e($this->url($listPath)) ?>" class="btn btn-outline-secondary">Stok Listesi</a>
        <a href="<?= $this->e($this->url($historyPath)) ?>" class="btn btn-outline-secondary">Hareket Geçmişi</a>
    </div>
</div>

<?php if ($items === []): ?>
    <div class="alert alert-info mb-0" role="note">
        Stok hareketi yapılabilecek ürün bulunamadı. Önce bir ürün ekleyin.
    </div>
<?php else: ?>
    <form id="stockForm" method="post" action="<?= $this->e($this->url($formAction)) ?>" novalidate>
        <?= $this->csrfField() ?>

        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-semibold mb-3">Hareket Bilgileri</h2>

                        <div class="mb-3">
                            <label for="item" class="form-label">Ürün / varyant <span class="text-danger">*</span></label>
                            <select class="form-select<?= $invalid('item') ?>" id="item" name="item" required autofocus>
                                <option value="">— Seçin —</option>
                                <?php foreach ($items as $item): ?>
                                    <?php
                                    $optionValue = 'p' . (int) $item['product_id']
                                        . ($item['variant_id'] === null ? '' : 'v' . (int) $item['variant_id']);
                                    $variantName = isset($item['variant_name']) ? (string) $item['variant_name'] : '';
                                    $optionLabel = (string) $item['product_name']
                                        . ($variantName !== '' ? ' - ' . $variantName : '')
                                        . ' (' . (string) $item['sku'] . ') · Stok: ' . (int) $item['stock_quantity'];
                                    ?>
                                    <option value="<?= $this->e($optionValue) ?>"<?= $selectedItem === $optionValue ? ' selected' : '' ?>>
                                        <?= $this->e($optionLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($error('item') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('item')) ?></div>
                            <?php endif; ?>
                            <div class="form-text">
                                Varyantlı ürünlerde stok varyant düzeyinde yönetilir.
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label for="movement_type" class="form-label">Hareket türü <span class="text-danger">*</span></label>
                                <select class="form-select<?= $invalid('movement_type') ?>" id="movement_type"
                                        name="movement_type" required>
                                    <?php foreach ($types as $type): ?>
                                        <?php $typeValue = (string) $type; ?>
                                        <option value="<?= $this->e($typeValue) ?>"<?= $selectedType === $typeValue ? ' selected' : '' ?>>
                                            <?= $this->e($typeLabels[$typeValue] ?? $typeValue) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($error('movement_type') !== ''): ?>
                                    <div class="invalid-feedback"><?= $this->e($error('movement_type')) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="quantity" class="form-label" id="quantityLabel">Miktar <span class="text-danger">*</span></label>
                                <input type="number" class="form-control<?= $invalid('quantity') ?>" id="quantity"
                                       name="quantity" value="<?= $this->e($val('quantity')) ?>"
                                       min="0" max="10000000" step="1" inputmode="numeric" required>
                                <?php if ($error('quantity') !== ''): ?>
                                    <div class="invalid-feedback"><?= $this->e($error('quantity')) ?></div>
                                <?php endif; ?>
                                <div class="form-text" id="quantityHelp">Eklenecek veya düşülecek adet.</div>
                            </div>
                        </div>

                        <div>
                            <label for="note" class="form-label">İşlem nedeni <span class="text-danger">*</span></label>
                            <textarea class="form-control<?= $invalid('note') ?>" id="note" name="note"
                                      rows="3" maxlength="500" required><?= $this->e($val('note')) ?></textarea>
                            <?php if ($error('note') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('note')) ?></div>
                            <?php endif; ?>
                            <div class="form-text">
                                Örn. "Tedarikçiden mal kabul", "Sayım farkı", "Fire". Stok geçmişinde saklanır.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-semibold mb-3">Hareket Türleri</h2>
                        <ul class="small text-muted ps-3 mb-0">
                            <li class="mb-2"><strong>Giriş:</strong> stoğa adet ekler.</li>
                            <li class="mb-2"><strong>Çıkış:</strong> stoktan adet düşer; mevcut stoğu aşamaz.</li>
                            <li class="mb-2"><strong>İade girişi:</strong> iade edilen ürünü stoğa ekler.</li>
                            <li><strong>Düzeltme:</strong> girdiğiniz değer yeni stok adedi olur (sayım farkı için).</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
            <button type="submit" class="btn btn-success px-4" id="stockSubmit">Kaydet</button>
            <a href="<?= $this->e($this->url($listPath)) ?>" class="btn btn-outline-secondary">Vazgeç</a>
        </div>
    </form>

    <script>
        (function () {
            'use strict';

            var form = document.getElementById('stockForm');
            var button = document.getElementById('stockSubmit');
            var typeSelect = document.getElementById('movement_type');
            var quantityLabel = document.getElementById('quantityLabel');
            var quantityHelp = document.getElementById('quantityHelp');

            if (!form || !button || !typeSelect || !quantityLabel || !quantityHelp) {
                return;
            }

            // "Düzeltme" türünde miktar, hedef stok adedi anlamına gelir
            function refreshQuantityText() {
                var isCorrection = typeSelect.value === 'correction';

                quantityLabel.firstChild.textContent = isCorrection ? 'Hedef stok adedi ' : 'Miktar ';
                quantityHelp.textContent = isCorrection
                    ? 'Sayım sonrası olması gereken toplam stok adedi.'
                    : 'Eklenecek veya düşülecek adet.';
            }

            typeSelect.addEventListener('change', refreshQuantityText);
            refreshQuantityText();

            var originalText = button.textContent;

            // Gönderim sırasında butonu devre dışı bırakır (çift tıklamayı önler)
            form.addEventListener('submit', function () {
                button.disabled = true;
                button.textContent = 'Kaydediliyor...';
            });

            // Tarayıcının geri tuşuyla dönüldüğünde buton eski haline gelir
            window.addEventListener('pageshow', function () {
                button.disabled = false;
                button.textContent = originalText;
            });
        })();
    </script>
<?php endif; ?>