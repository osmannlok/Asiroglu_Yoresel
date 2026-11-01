<?php

/**
 * Aşıroğlu Yöresel Market - Yönetim paneli kargo yöntemi listesi
 *
 * Controller: App\Controllers\Admin\ShippingMethodController::index()
 * Layout: layouts/admin
 *
 * Gelen değişkenler:
 *   string                          $title
 *   string                          $activeMenu
 *   array<int, array<string,mixed>> $methods   Pasifler dahil tüm yöntemler
 *   string                          $basePath  /admin/kargo
 *
 * Kararlar:
 * - Silme yoktur; yöntem satıştan aktif/pasif anahtarıyla kaldırılır.
 * - Aktif/pasif anahtarı Fetch ile çalışır (sayfa yenilenmez). JavaScript
 *   kapalıysa aynı form normal POST olarak gönderilir (controller ikisini de
 *   destekler). CSRF alanı her satırdaki formda bulunur; Fetch aynı formu
 *   FormData ile gönderir.
 * - Tutarlar TRY ve KDV dahildir. Eşik boşsa "Eşik yok" gösterilir.
 * - Tüm çıktılar htmlspecialchars() ile kaçırılır.
 *
 * Gereksinimler: FR-084, FR-085, NFR-003.
 */

/** @var array<int, array<string, mixed>> $methods */
/** @var string $basePath */

$esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$money = static fn (mixed $value): string => number_format((float) $value, 2, ',', '.') . ' ₺';

$activeCount = 0;

foreach ($methods as $item) {
    if ((int) $item['is_active'] === 1) {
        $activeCount++;
    }
}
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1">Kargo Yöntemleri</h1>
        <p class="text-muted small mb-0">
            Ücretler TRY ve KDV dahildir. Yöntemler silinmez; satıştan kaldırmak için pasif yapın.
        </p>
    </div>
    <a href="<?= $esc($basePath) ?>/olustur" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Yeni Yöntem
    </a>
</div>

<div id="shipping-feedback" class="alert alert-danger d-none" role="alert" aria-live="polite"></div>

<?php if ($methods === []): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            Henüz kargo yöntemi eklenmemiş. Sipariş verilebilmesi için en az bir aktif yöntem gerekir.
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Yöntem</th>
                        <th scope="col">Kod</th>
                        <th scope="col" class="text-end">Ücret</th>
                        <th scope="col" class="text-end">Ücretsiz Kargo Eşiği</th>
                        <th scope="col" class="text-center">Sıra</th>
                        <th scope="col" class="text-center">Durum</th>
                        <th scope="col" class="text-end">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($methods as $method): ?>
                        <?php
                        $id       = (int) $method['id'];
                        $isActive = (int) $method['is_active'] === 1;
                        $isLast   = $isActive && $activeCount <= 1;
                        $limit    = $method['free_shipping_limit'] ?? null;
                        ?>
                        <tr data-method-row="<?= $id ?>">
                            <td>
                                <div class="fw-semibold"><?= $esc($method['name']) ?></div>
                                <?php if (!empty($method['description'])): ?>
                                    <div class="text-muted small"><?= $esc($method['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><code><?= $esc($method['code']) ?></code></td>
                            <td class="text-end">
                                <?= (float) $method['price'] <= 0 ? '<span class="text-success">Ücretsiz</span>' : $esc($money($method['price'])) ?>
                            </td>
                            <td class="text-end">
                                <?php if ($limit === null || $limit === ''): ?>
                                    <span class="text-muted">Eşik yok</span>
                                <?php else: ?>
                                    <?= $esc($money($limit)) ?> ve üzeri
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= (int) $method['sort_order'] ?></td>
                            <td class="text-center">
                                <form
                                    method="post"
                                    action="<?= $esc($basePath) ?>/<?= $id ?>/durum"
                                    class="d-inline-flex align-items-center gap-2 js-shipping-toggle"
                                    data-method-id="<?= $id ?>"
                                >
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="is_active" value="<?= $isActive ? '0' : '1' ?>">
                                    <div class="form-check form-switch m-0">
                                        <input
                                            class="form-check-input js-shipping-switch"
                                            type="checkbox"
                                            role="switch"
                                            id="shipping-switch-<?= $id ?>"
                                            <?= $isActive ? 'checked' : '' ?>
                                            aria-label="<?= $esc($method['name']) ?> yöntemini aktif/pasif yap"
                                        >
                                    </div>
                                    <span
                                        class="badge js-shipping-badge <?= $isActive ? 'text-bg-success' : 'text-bg-secondary' ?>"
                                    ><?= $isActive ? 'Aktif' : 'Pasif' ?></span>
                                    <noscript>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <?= $isActive ? 'Kapat' : 'Aç' ?>
                                        </button>
                                    </noscript>
                                </form>
                                <?php if ($isLast): ?>
                                    <div class="text-muted small mt-1">Son aktif yöntem</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a
                                    href="<?= $esc($basePath) ?>/<?= $id ?>/duzenle"
                                    class="btn btn-sm btn-outline-primary"
                                >Düzenle</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
(function () {
    'use strict';

    var feedback = document.getElementById('shipping-feedback');

    function showError(message) {
        if (!feedback) {
            return;
        }
        feedback.textContent = message;
        feedback.classList.remove('d-none');
    }

    function clearError() {
        if (feedback) {
            feedback.classList.add('d-none');
            feedback.textContent = '';
        }
    }

    function render(form, isActive) {
        var badge = form.querySelector('.js-shipping-badge');
        var hidden = form.querySelector('input[name="is_active"]');
        var toggle = form.querySelector('.js-shipping-switch');

        if (badge) {
            badge.textContent = isActive ? 'Aktif' : 'Pasif';
            badge.classList.toggle('text-bg-success', isActive);
            badge.classList.toggle('text-bg-secondary', !isActive);
        }
        // Sonraki gönderim mevcut durumun tersini istemeli
        if (hidden) {
            hidden.value = isActive ? '0' : '1';
        }
        if (toggle) {
            toggle.checked = isActive;
        }
    }

    document.querySelectorAll('.js-shipping-toggle').forEach(function (form) {
        var toggle = form.querySelector('.js-shipping-switch');

        if (!toggle) {
            return;
        }

        toggle.addEventListener('change', function () {
            var wanted = toggle.checked;
            var previous = !wanted;

            clearError();
            toggle.disabled = true;

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.data || result.data.success !== true) {
                        throw new Error(
                            result.data && result.data.message
                                ? result.data.message
                                : 'İşlem tamamlanamadı.'
                        );
                    }
                    render(form, result.data.is_active === true);
                })
                .catch(function (error) {
                    // Başarısızsa anahtarı eski konumuna döndür
                    render(form, previous);
                    showError(error.message || 'İşlem sırasında bir hata oluştu. Lütfen tekrar deneyin.');
                })
                .finally(function () {
                    toggle.disabled = false;
                });
        });
    });
})();
</script>