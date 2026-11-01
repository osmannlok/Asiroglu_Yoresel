<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli kargo yöntemi formu (oluştur/düzenle)
 *
 * Controller: App\Controllers\Admin\ShippingMethodController::showForm()
 * Layout: layouts/admin
 *
 * Gelen değişkenler:
 *   string                $title
 *   string                $activeMenu
 *   bool                  $isEdit      Düzenleme modu mu?
 *   int|null              $methodId
 *   array<string, mixed>  $values      name, code, description, price,
 *                                      free_shipping_limit, sort_order (string), is_active (bool)
 *   array<string, string> $errors      Alan adı => hata mesajı
 *   string                $formAction  POST hedefi
 *   string                $listPath    Liste sayfası yolu
 *
 * Kararlar:
 * - "code" yalnızca oluştururken girilir; düzenlemede salt okunurdur ve
 *   controller gönderilen değeri zaten yok sayar.
 * - Tutarlar TRY, KDV dahildir; ondalık ayırıcı noktadır (örn. 49.90).
 * - Ücretsiz kargo eşiği boş bırakılırsa eşik uygulanmaz.
 * - Form normal POST ile gönderilir; CSRF alanı her formda bulunur.
 * - Tüm çıktılar htmlspecialchars() ile kaçırılır.
 *
 * Gereksinimler: FR-084, FR-085, NFR-003.
 */

/** @var bool $isEdit */
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
/** @var string $formAction */
/** @var string $listPath */

$esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

/** Alan hatası varsa Bootstrap "is-invalid" sınıfını döndürür */
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';

/** Alan hatası mesajı */
$feedback = static function (string $field) use ($errors, $esc): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block">' . $esc($errors[$field]) . '</div>'
        : '';
};
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?= $isEdit ? 'Kargo Yöntemini Düzenle' : 'Yeni Kargo Yöntemi' ?></h1>
        <p class="text-muted small mb-0">Tutarlar TRY ve KDV dahildir.</p>
    </div>
    <a href="<?= $esc($listPath) ?>" class="btn btn-outline-secondary">Listeye Dön</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= $esc($formAction) ?>" novalidate>
            <?= $this->csrfField() ?>

            <div class="row g-3">
                <div class="col-12 col-md-8">
                    <label for="name" class="form-label">Yöntem adı <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        class="form-control<?= $invalid('name') ?>"
                        id="name"
                        name="name"
                        maxlength="150"
                        required
                        value="<?= $esc($values['name'] ?? '') ?>"
                        placeholder="Örn. Standart Kargo"
                    >
                    <?= $feedback('name') ?>
                </div>

                <div class="col-12 col-md-4">
                    <label for="code" class="form-label">Kod <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        class="form-control<?= $invalid('code') ?>"
                        id="code"
                        name="code"
                        maxlength="50"
                        pattern="[a-z0-9_]{1,50}"
                        autocomplete="off"
                        required
                        <?= $isEdit ? 'readonly' : '' ?>
                        value="<?= $esc($values['code'] ?? '') ?>"
                        placeholder="standard"
                    >
                    <?= $feedback('code') ?>
                    <div class="form-text">
                        <?= $isEdit
                            ? 'Kod oluşturulduktan sonra değiştirilemez.'
                            : 'Küçük harf, rakam ve alt çizgi (_). Sonradan değiştirilemez.' ?>
                    </div>
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Açıklama</label>
                    <textarea
                        class="form-control<?= $invalid('description') ?>"
                        id="description"
                        name="description"
                        rows="2"
                        maxlength="500"
                        placeholder="Örn. 2-4 iş günü içinde teslim"
                    ><?= $esc($values['description'] ?? '') ?></textarea>
                    <?= $feedback('description') ?>
                </div>

                <div class="col-12 col-md-4">
                    <label for="price" class="form-label">Kargo ücreti (₺) <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        inputmode="decimal"
                        class="form-control<?= $invalid('price') ?>"
                        id="price"
                        name="price"
                        autocomplete="off"
                        required
                        value="<?= $esc($values['price'] ?? '') ?>"
                        placeholder="49.90"
                    >
                    <?= $feedback('price') ?>
                    <div class="form-text">Ücretsiz için 0 girin. Ondalık ayırıcı nokta.</div>
                </div>

                <div class="col-12 col-md-4">
                    <label for="free_shipping_limit" class="form-label">Ücretsiz kargo eşiği (₺)</label>
                    <input
                        type="text"
                        inputmode="decimal"
                        class="form-control<?= $invalid('free_shipping_limit') ?>"
                        id="free_shipping_limit"
                        name="free_shipping_limit"
                        autocomplete="off"
                        value="<?= $esc($values['free_shipping_limit'] ?? '') ?>"
                        placeholder="750.00"
                    >
                    <?= $feedback('free_shipping_limit') ?>
                    <div class="form-text">Boş bırakılırsa eşik uygulanmaz.</div>
                </div>

                <div class="col-12 col-md-4">
                    <label for="sort_order" class="form-label">Sıra</label>
                    <input
                        type="text"
                        inputmode="numeric"
                        class="form-control<?= $invalid('sort_order') ?>"
                        id="sort_order"
                        name="sort_order"
                        autocomplete="off"
                        value="<?= $esc($values['sort_order'] ?? '0') ?>"
                    >
                    <?= $feedback('sort_order') ?>
                    <div class="form-text">Küçük sayı önce listelenir.</div>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input
                            class="form-check-input<?= $invalid('is_active') ?>"
                            type="checkbox"
                            role="switch"
                            id="is_active"
                            name="is_active"
                            value="1"
                            <?= !empty($values['is_active']) ? 'checked' : '' ?>
                        >
                        <label class="form-check-label" for="is_active">Satışta (checkout'ta seçilebilir)</label>
                    </div>
                    <?= $feedback('is_active') ?>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <button type="submit" class="btn btn-primary">
                    <?= $isEdit ? 'Değişiklikleri Kaydet' : 'Yöntemi Ekle' ?>
                </button>
                <a href="<?= $esc($listPath) ?>" class="btn btn-outline-secondary">Vazgeç</a>
            </div>
        </form>
    </div>
</div>