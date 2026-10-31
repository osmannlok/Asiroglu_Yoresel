<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli varyant formu view'ı
 *
 * Admin\ProductVariantController::showForm() tarafından render edilir ve
 * layouts/admin.php içine yerleştirilir. Hem oluşturma hem düzenleme için kullanılır.
 *
 * Değişkenler:
 *   $isEdit     (bool) düzenleme mi?
 *   $product    (array) varyantın bağlı olduğu ürün satırı
 *   $variantId  (int|null) düzenlenen varyant
 *   $values     (array) form değerleri
 *   $errors     (array) alan => hata mesajı
 *   $formAction (string) formun gönderileceği yol
 *   $listPath   (string) varyant listesinin yolu
 *
 * Stok: başlangıç stoğu yalnızca oluşturmada girilir. Düzenlemede salt
 * okunur gösterilir ve forma gönderilmez; stok değişikliği stok modülünden
 * yapılır (FR-026). Varyant ürün değiştiremez.
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url(), $this->csrfField()
 *
 * Gereksinimler: FR-020, FR-021, FR-022, FR-025, NFR-003, NFR-004, NFR-005.
 */

declare(strict_types=1);

$isEdit     = !empty($isEdit);
$values     = isset($values) && is_array($values) ? $values : [];
$errors     = isset($errors) && is_array($errors) ? $errors : [];
$formAction = isset($formAction) ? (string) $formAction : '';
$listPath   = isset($listPath) ? (string) $listPath : '/admin/urunler';
$productId  = (int) $product['id'];

/** Form değerini metin olarak döndürür. */
$val = static fn (string $key): string => isset($values[$key]) ? (string) $values[$key] : '';

/** Alan hatalıysa Bootstrap sınıfını döndürür. */
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' is-invalid' : '';

/** Alan hata mesajını (varsa) döndürür. */
$error = static fn (string $key): string => isset($errors[$key]) ? (string) $errors[$key] : '';

$isActive = !empty($values['is_active']);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item">
                    <a href="<?= $this->e($this->url('/admin/urunler')) ?>">Ürünler</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?= $this->e($this->url('/admin/urunler/' . $productId . '/duzenle')) ?>"><?= $this->e($product['name']) ?></a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?= $this->e($this->url($listPath)) ?>">Varyantlar</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?= $isEdit ? 'Düzenle' : 'Yeni' ?>
                </li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold mb-0"><?= $isEdit ? 'Varyantı Düzenle' : 'Yeni Varyant' ?></h1>
    </div>
    <a href="<?= $this->e($this->url($listPath)) ?>" class="btn btn-outline-secondary">Listeye Dön</a>
</div>

<form id="variantForm" method="post" action="<?= $this->e($this->url($formAction)) ?>" novalidate>
    <?= $this->csrfField() ?>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Varyant Bilgileri</h2>

                    <div class="mb-3">
                        <label for="variant_name" class="form-label">Varyant adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control<?= $invalid('variant_name') ?>" id="variant_name"
                               name="variant_name" value="<?= $this->e($val('variant_name')) ?>"
                               maxlength="150" placeholder="örn. 500 Gr" required autofocus>
                        <?php if ($error('variant_name') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('variant_name')) ?></div>
                        <?php endif; ?>
                        <div class="form-text">Aynı üründe aynı ad iki kez kullanılamaz.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= $invalid('sku') ?>" id="sku" name="sku"
                                   value="<?= $this->e($val('sku')) ?>" maxlength="64" required>
                            <?php if ($error('sku') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('sku')) ?></div>
                            <?php endif; ?>
                            <div class="form-text">Ürün ve diğer varyantlardan farklı olmalıdır.</div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="barcode" class="form-label">Barkod</label>
                            <input type="text" class="form-control<?= $invalid('barcode') ?>" id="barcode" name="barcode"
                                   value="<?= $this->e($val('barcode')) ?>" maxlength="64">
                            <?php if ($error('barcode') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('barcode')) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <label for="weight" class="form-label">Ağırlık</label>
                        <input type="text" class="form-control<?= $invalid('weight') ?>" id="weight" name="weight"
                               value="<?= $this->e($val('weight')) ?>" maxlength="11" inputmode="decimal">
                        <?php if ($error('weight') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('weight')) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-1">Fiyat</h2>
                    <p class="text-muted small mb-3">
                        Fiyatlar TRY ve KDV dahildir. KDV oranı ürünün KDV oranıdır.
                        Ondalık ayırıcı olarak nokta kullanın (örn. <code>250.50</code>).
                    </p>

                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label for="price" class="form-label">Satış fiyatı (₺) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= $invalid('price') ?>" id="price" name="price"
                                   value="<?= $this->e($val('price')) ?>" maxlength="13" inputmode="decimal" required>
                            <?php if ($error('price') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('price')) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="discount_price" class="form-label">İndirimli fiyat (₺)</label>
                            <input type="text" class="form-control<?= $invalid('discount_price') ?>" id="discount_price"
                                   name="discount_price" value="<?= $this->e($val('discount_price')) ?>"
                                   maxlength="13" inputmode="decimal">
                            <?php if ($error('discount_price') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('discount_price')) ?></div>
                            <?php endif; ?>
                            <div class="form-text">Satış fiyatından düşük olmalıdır.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Stok</h2>

                    <?php if (!$isEdit): ?>
                        <label for="stock_quantity" class="form-label">Başlangıç stoğu</label>
                        <input type="number" class="form-control<?= $invalid('stock_quantity') ?>" id="stock_quantity"
                               name="stock_quantity" value="<?= $this->e($val('stock_quantity')) ?>"
                               min="0" max="10000000" step="1" inputmode="numeric">
                        <?php if ($error('stock_quantity') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('stock_quantity')) ?></div>
                        <?php endif; ?>
                        <div class="form-text">
                            Sonraki stok değişiklikleri stok yönetimi modülünden yapılır.
                        </div>
                    <?php else: ?>
                        <label for="stock_display" class="form-label">Mevcut stok</label>
                        <input type="text" class="form-control" id="stock_display"
                               value="<?= $this->e($val('stock_quantity')) ?>" disabled readonly>
                        <div class="form-text">
                            Stok bu formdan değiştirilemez. Stok hareketleri stok yönetimi modülünden yapılır.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Satış Durumu</h2>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active"
                               name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Satışta</label>
                    </div>
                    <div class="form-text">
                        Kapalı varyant satın alınamaz. Varyantlar silinmez; sipariş geçmişi etkilenmez.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="submit" class="btn btn-success px-4" id="variantSubmit">
            <?= $isEdit ? 'Güncelle' : 'Kaydet' ?>
        </button>
        <a href="<?= $this->e($this->url($listPath)) ?>" class="btn btn-outline-secondary">Vazgeç</a>
    </div>
</form>

<script>
    // Gönderim sırasında butonu devre dışı bırakır (çift tıklamayı önler)
    (function () {
        'use strict';

        var form = document.getElementById('variantForm');
        var button = document.getElementById('variantSubmit');

        if (!form || !button) {
            return;
        }

        var originalText = button.textContent;

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