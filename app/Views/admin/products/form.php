<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli ürün formu view'ı
 *
 * Admin\ProductController::showForm() tarafından render edilir ve
 * layouts/admin.php içine yerleştirilir. Hem oluşturma hem düzenleme için kullanılır.
 *
 * Değişkenler:
 *   $isEdit      (bool) düzenleme mi?
 *   $productId   (int|null) düzenlenen ürün
 *   $hasVariants (bool) ürünün varyantı var mı?
 *   $values      (array) form değerleri
 *   $errors      (array) alan => hata mesajı
 *   $categories  (list) kategori seçenekleri
 *   $brands      (list) marka seçenekleri
 *   $unitTypes   (list<string>) birim türleri
 *   $formAction  (string) formun gönderileceği yol
 *   $listPath    (string) liste sayfasının yolu
 *
 * Stok: yeni üründe başlangıç stoğu girilir. Düzenlemede stok alanı yoktur;
 * stok değişikliği stok modülünden yapılır (FR-026). Varyantlı üründe geçerli
 * stok varyant düzeyindedir.
 *
 * Görsel yükleme ve varyant yönetimi bu formda yoktur (sonraki adımlar).
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url(), $this->csrfField()
 *
 * Gereksinimler: FR-010, FR-011, FR-015, FR-021, FR-033, NFR-003, NFR-004, NFR-005.
 */

declare(strict_types=1);

$isEdit      = !empty($isEdit);
$hasVariants = !empty($hasVariants);
$values      = isset($values) && is_array($values) ? $values : [];
$errors      = isset($errors) && is_array($errors) ? $errors : [];
$categories  = isset($categories) && is_array($categories) ? $categories : [];
$brands      = isset($brands) && is_array($brands) ? $brands : [];
$unitTypes   = isset($unitTypes) && is_array($unitTypes) ? $unitTypes : ['adet'];
$formAction  = isset($formAction) ? (string) $formAction : '/admin/urunler';
$listPath    = isset($listPath) ? (string) $listPath : '/admin/urunler';

/** Form değerini metin olarak döndürür. */
$val = static fn (string $key): string => isset($values[$key]) ? (string) $values[$key] : '';

/** Alan hatalıysa Bootstrap sınıfını döndürür. */
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' is-invalid' : '';

/** Alan hata mesajını (varsa) döndürür. */
$error = static fn (string $key): string => isset($errors[$key]) ? (string) $errors[$key] : '';

$selectedCategory = $val('category_id');
$selectedBrand    = $val('brand_id');
$selectedUnit     = $val('unit_type');
$isActive         = !empty($values['is_active']);
$isFeatured       = !empty($values['is_featured']);
$isNew            = !empty($values['is_new']);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 fw-bold mb-0"><?= $isEdit ? 'Ürünü Düzenle' : 'Yeni Ürün' ?></h1>
    <a href="<?= $this->e($this->url($listPath)) ?>" class="btn btn-outline-secondary">Listeye Dön</a>
</div>

<?php if ($isEdit && !empty($productId)): ?>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="<?= $this->e($this->url($listPath . '/' . (int) $productId . '/gorseller')) ?>"
           class="btn btn-outline-primary btn-sm">Görseller</a>
        <a href="<?= $this->e($this->url($listPath . '/' . (int) $productId . '/varyantlar')) ?>"
           class="btn btn-outline-primary btn-sm">
            Varyantlar<?= $hasVariants ? ' (var)' : '' ?>
        </a>
    </div>
<?php endif; ?>

<form id="productForm" method="post" action="<?= $this->e($this->url($formAction)) ?>" novalidate>
    <?= $this->csrfField() ?>

    <div class="row g-4">
        <div class="col-12 col-lg-8">

            <!-- Genel bilgiler -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Genel Bilgiler</h2>

                    <div class="mb-3">
                        <label for="name" class="form-label">Ürün adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control<?= $invalid('name') ?>" id="name" name="name"
                               value="<?= $this->e($val('name')) ?>" maxlength="255" required autofocus>
                        <?php if ($error('name') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('name')) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="slug" class="form-label">Slug</label>
                        <input type="text" class="form-control<?= $invalid('slug') ?>" id="slug" name="slug"
                               value="<?= $this->e($val('slug')) ?>" maxlength="255"
                               aria-describedby="slugHelp">
                        <?php if ($error('slug') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('slug')) ?></div>
                        <?php endif; ?>
                        <div id="slugHelp" class="form-text">
                            Ürün adresidir (örn. <code>antep-fistigi</code>). Boş bırakırsanız ürün adından üretilir.
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= $invalid('sku') ?>" id="sku" name="sku"
                                   value="<?= $this->e($val('sku')) ?>" maxlength="64" required>
                            <?php if ($error('sku') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('sku')) ?></div>
                            <?php endif; ?>
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

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label for="category_id" class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select<?= $invalid('category_id') ?>" id="category_id" name="category_id" required>
                                <option value="">— Kategori seçin —</option>
                                <?php foreach ($categories as $category): ?>
                                    <?php $categoryOption = (string) (int) $category['id']; ?>
                                    <option value="<?= $this->e($categoryOption) ?>"<?= $selectedCategory === $categoryOption ? ' selected' : '' ?>>
                                        <?= $this->e($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($error('category_id') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('category_id')) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="brand_id" class="form-label">Marka</label>
                            <select class="form-select<?= $invalid('brand_id') ?>" id="brand_id" name="brand_id">
                                <option value="">— Markasız —</option>
                                <?php foreach ($brands as $brand): ?>
                                    <?php $brandOption = (string) (int) $brand['id']; ?>
                                    <option value="<?= $this->e($brandOption) ?>"<?= $selectedBrand === $brandOption ? ' selected' : '' ?>>
                                        <?= $this->e($brand['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($error('brand_id') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('brand_id')) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="short_description" class="form-label">Kısa açıklama</label>
                        <textarea class="form-control<?= $invalid('short_description') ?>" id="short_description"
                                  name="short_description" rows="2" maxlength="500"><?= $this->e($val('short_description')) ?></textarea>
                        <?php if ($error('short_description') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('short_description')) ?></div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label for="description" class="form-label">Açıklama</label>
                        <textarea class="form-control<?= $invalid('description') ?>" id="description"
                                  name="description" rows="6" maxlength="20000"><?= $this->e($val('description')) ?></textarea>
                        <?php if ($error('description') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('description')) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Fiyat ve indirim -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-1">Fiyat ve İndirim</h2>
                    <p class="text-muted small mb-3">
                        Fiyatlar TRY ve KDV dahildir. Ondalık ayırıcı olarak nokta kullanın (örn. <code>850.50</code>).
                    </p>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-4">
                            <label for="price" class="form-label">Satış fiyatı (₺) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= $invalid('price') ?>" id="price" name="price"
                                   value="<?= $this->e($val('price')) ?>" maxlength="13" inputmode="decimal" required>
                            <?php if ($error('price') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('price')) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label for="discount_price" class="form-label">İndirimli fiyat (₺)</label>
                            <input type="text" class="form-control<?= $invalid('discount_price') ?>" id="discount_price"
                                   name="discount_price" value="<?= $this->e($val('discount_price')) ?>"
                                   maxlength="13" inputmode="decimal">
                            <?php if ($error('discount_price') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('discount_price')) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label for="vat_rate" class="form-label">KDV oranı (%) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= $invalid('vat_rate') ?>" id="vat_rate" name="vat_rate"
                                   value="<?= $this->e($val('vat_rate')) ?>" maxlength="6" inputmode="decimal" required>
                            <?php if ($error('vat_rate') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('vat_rate')) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label for="discount_start_date" class="form-label">İndirim başlangıcı</label>
                            <input type="date" class="form-control<?= $invalid('discount_start_date') ?>"
                                   id="discount_start_date" name="discount_start_date"
                                   value="<?= $this->e($val('discount_start_date')) ?>">
                            <?php if ($error('discount_start_date') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('discount_start_date')) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="discount_end_date" class="form-label">İndirim bitişi</label>
                            <input type="date" class="form-control<?= $invalid('discount_end_date') ?>"
                                   id="discount_end_date" name="discount_end_date"
                                   value="<?= $this->e($val('discount_end_date')) ?>">
                            <?php if ($error('discount_end_date') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('discount_end_date')) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-text mt-2">
                        Tarihler boşsa indirim süresizdir. Tarihler yalnızca indirimli fiyat girildiyse saklanır.
                    </div>
                </div>
            </div>

            <!-- Gıda bilgileri -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Ürün ve Gıda Bilgileri</h2>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label for="unit_type" class="form-label">Birim <span class="text-danger">*</span></label>
                            <select class="form-select<?= $invalid('unit_type') ?>" id="unit_type" name="unit_type" required>
                                <?php foreach ($unitTypes as $unit): ?>
                                    <?php $unitOption = (string) $unit; ?>
                                    <option value="<?= $this->e($unitOption) ?>"<?= $selectedUnit === $unitOption ? ' selected' : '' ?>>
                                        <?= $this->e($unitOption) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($error('unit_type') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('unit_type')) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="weight" class="form-label">Ağırlık</label>
                            <input type="text" class="form-control<?= $invalid('weight') ?>" id="weight" name="weight"
                                   value="<?= $this->e($val('weight')) ?>" maxlength="11" inputmode="decimal">
                            <?php if ($error('weight') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('weight')) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label for="origin" class="form-label">Menşe</label>
                            <input type="text" class="form-control<?= $invalid('origin') ?>" id="origin" name="origin"
                                   value="<?= $this->e($val('origin')) ?>" maxlength="255">
                            <?php if ($error('origin') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('origin')) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="producer" class="form-label">Üretici</label>
                            <input type="text" class="form-control<?= $invalid('producer') ?>" id="producer" name="producer"
                                   value="<?= $this->e($val('producer')) ?>" maxlength="255">
                            <?php if ($error('producer') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('producer')) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-8">
                            <label for="storage_conditions" class="form-label">Saklama koşulları</label>
                            <input type="text" class="form-control<?= $invalid('storage_conditions') ?>"
                                   id="storage_conditions" name="storage_conditions"
                                   value="<?= $this->e($val('storage_conditions')) ?>" maxlength="500">
                            <?php if ($error('storage_conditions') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('storage_conditions')) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label for="shelf_life" class="form-label">Raf ömrü</label>
                            <input type="text" class="form-control<?= $invalid('shelf_life') ?>" id="shelf_life" name="shelf_life"
                                   value="<?= $this->e($val('shelf_life')) ?>" maxlength="150" placeholder="örn. 12 ay">
                            <?php if ($error('shelf_life') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('shelf_life')) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="allergens" class="form-label">Alerjen bilgisi</label>
                        <textarea class="form-control<?= $invalid('allergens') ?>" id="allergens" name="allergens"
                                  rows="2" maxlength="5000"><?= $this->e($val('allergens')) ?></textarea>
                        <?php if ($error('allergens') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('allergens')) ?></div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label for="ingredients" class="form-label">İçindekiler</label>
                        <textarea class="form-control<?= $invalid('ingredients') ?>" id="ingredients" name="ingredients"
                                  rows="3" maxlength="5000"><?= $this->e($val('ingredients')) ?></textarea>
                        <?php if ($error('ingredients') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('ingredients')) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">

            <!-- Stok -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Stok</h2>

                    <?php if (!$isEdit): ?>
                        <div class="mb-3">
                            <label for="stock_quantity" class="form-label">Başlangıç stoğu</label>
                            <input type="number" class="form-control<?= $invalid('stock_quantity') ?>" id="stock_quantity"
                                   name="stock_quantity" value="<?= $this->e($val('stock_quantity')) ?>"
                                   min="0" max="10000000" step="1" inputmode="numeric">
                            <?php if ($error('stock_quantity') !== ''): ?>
                                <div class="invalid-feedback"><?= $this->e($error('stock_quantity')) ?></div>
                            <?php endif; ?>
                            <div class="form-text">
                                Varyant eklerseniz stok varyant düzeyinde yönetilir.
                            </div>
                        </div>
                    <?php elseif ($hasVariants): ?>
                        <div class="alert alert-info small mb-3" role="note">
                            Bu ürünün varyantları var; stok varyant düzeyinde yönetilir.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info small mb-3" role="note">
                            Stok adedi bu formdan değiştirilemez. Stok hareketleri stok yönetimi modülünden yapılır.
                        </div>
                    <?php endif; ?>

                    <div>
                        <label for="critical_stock_quantity" class="form-label">Kritik stok eşiği</label>
                        <input type="number" class="form-control<?= $invalid('critical_stock_quantity') ?>"
                               id="critical_stock_quantity" name="critical_stock_quantity"
                               value="<?= $this->e($val('critical_stock_quantity')) ?>"
                               min="0" max="10000000" step="1" inputmode="numeric">
                        <?php if ($error('critical_stock_quantity') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('critical_stock_quantity')) ?></div>
                        <?php endif; ?>
                        <div class="form-text">Stok bu değere indiğinde panelde uyarı görünür.</div>
                    </div>
                </div>
            </div>

            <!-- Yayın -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Yayın</h2>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active"
                               name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Aktif</label>
                    </div>
                    <div class="form-text mb-3">
                        Pasif ürün vitrinde listelenmez; sipariş geçmişi etkilenmez.
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_featured"
                               name="is_featured" value="1"<?= $isFeatured ? ' checked' : '' ?>>
                        <label class="form-check-label" for="is_featured">Öne çıkan</label>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_new"
                               name="is_new" value="1"<?= $isNew ? ' checked' : '' ?>>
                        <label class="form-check-label" for="is_new">Yeni ürün</label>
                    </div>
                </div>
            </div>

            <!-- SEO -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">SEO</h2>

                    <div class="mb-3">
                        <label for="meta_title" class="form-label">SEO başlığı</label>
                        <input type="text" class="form-control<?= $invalid('meta_title') ?>" id="meta_title"
                               name="meta_title" value="<?= $this->e($val('meta_title')) ?>" maxlength="255">
                        <?php if ($error('meta_title') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('meta_title')) ?></div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label for="meta_description" class="form-label">SEO açıklaması</label>
                        <textarea class="form-control<?= $invalid('meta_description') ?>" id="meta_description"
                                  name="meta_description" rows="3" maxlength="500"><?= $this->e($val('meta_description')) ?></textarea>
                        <?php if ($error('meta_description') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('meta_description')) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="submit" class="btn btn-success px-4" id="productSubmit">
            <?= $isEdit ? 'Güncelle' : 'Kaydet' ?>
        </button>
        <a href="<?= $this->e($this->url($listPath)) ?>" class="btn btn-outline-secondary">Vazgeç</a>
    </div>
</form>

<script>
    // Gönderim sırasında butonu devre dışı bırakır (çift tıklamayı önler)
    (function () {
        'use strict';

        var form = document.getElementById('productForm');
        var button = document.getElementById('productSubmit');

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