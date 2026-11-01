<?php

/**
 * Aşıroğlu Yöresel Market - Checkout sayfası view'ı
 *
 * CheckoutController::index() tarafından render edilir ve layouts/main.php
 * içine yerleştirilir.
 *
 * Değişkenler:
 *   $quote          CheckoutService::quote() sonucu: items, quantity, subtotal,
 *                   discount_total, shipping_total, grand_total, methods, selected
 *   $values         (array) form değerleri
 *   $errors         (array) alan => hata mesajı
 *   $paymentMethods (array) kod => görünen ad
 *   $selectedMethod (int) seçili kargo yöntemi kimliği
 *   $isMember       (bool) giriş yapmış üye mi?
 *   $formAction     (string) formun gönderileceği yol
 *   $summaryPath    (string) toplamları veren JSON yolu
 *   $cartPath       (string) sepet sayfasının yolu
 *   $loginPath      (string) giriş sayfasının yolu (next ile)
 *
 * Kargo yöntemi değişince toplamlar Fetch ile (GET, JSON) güncellenir; sayfa
 * yenilenmez. Fiyatlar KDV dahil, TRY. Kart bilgisi bu formda alınmaz; kart
 * ödemesi ödeme sağlayıcısının sayfasında yapılacaktır.
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url(), $this->csrfField()
 *
 * Gereksinimler: FR-043, FR-045, FR-046, FR-060, FR-061, FR-062, FR-063,
 * NFR-003, NFR-004, NFR-005.
 */

declare(strict_types=1);

$quote          = isset($quote) && is_array($quote) ? $quote : [];
$values         = isset($values) && is_array($values) ? $values : [];
$errors         = isset($errors) && is_array($errors) ? $errors : [];
$paymentMethods = isset($paymentMethods) && is_array($paymentMethods) ? $paymentMethods : [];
$selectedMethod = (int) ($selectedMethod ?? 0);
$isMember       = !empty($isMember);
$formAction     = isset($formAction) ? (string) $formAction : '/odeme';
$summaryPath    = isset($summaryPath) ? (string) $summaryPath : '/odeme/ozet';
$cartPath       = isset($cartPath) ? (string) $cartPath : '/sepet';
$loginPath      = isset($loginPath) ? (string) $loginPath : '/giris';

$items   = isset($quote['items']) && is_array($quote['items']) ? $quote['items'] : [];
$methods = isset($quote['methods']) && is_array($quote['methods']) ? $quote['methods'] : [];

/** Form değerini metin olarak döndürür. */
$val = static fn (string $key): string => isset($values[$key]) ? (string) $values[$key] : '';

/** Alan hatalıysa Bootstrap sınıfını döndürür. */
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' is-invalid' : '';

/** Alan hata mesajını (varsa) döndürür. */
$error = static fn (string $key): string => isset($errors[$key]) ? (string) $errors[$key] : '';

/** Tutarı Türkçe biçimde yazar: 1.250,50 ₺ */
$money = static fn (mixed $amount): string => number_format((float) $amount, 2, ',', '.') . ' ₺';

/** Ücret metni: 0 ise "Ücretsiz" */
$feeText = static fn (float $fee): string => $fee <= 0.0 ? 'Ücretsiz' : number_format($fee, 2, ',', '.') . ' ₺';

$selectedPayment = $val('payment_method');

if (!array_key_exists($selectedPayment, $paymentMethods)) {
    $selectedPayment = (string) array_key_first($paymentMethods);
}

$hasSelected = isset($quote['selected']) && $quote['selected'] !== null;
$jsonFlags   = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
?>
<div class="container py-4 py-lg-5">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h3 fw-bold mb-0">Ödeme</h1>
        <a href="<?= $this->e($this->url($cartPath)) ?>" class="btn btn-outline-secondary btn-sm">Sepete Dön</a>
    </div>

    <?php if (!$isMember): ?>
        <div class="alert alert-info" role="note">
            Hesabınız var mı?
            <a href="<?= $this->e($this->url($loginPath)) ?>" class="alert-link">Giriş yapın</a>
            ve siparişinizi hesabınızdan takip edin. Üye olmadan da sipariş verebilirsiniz.
        </div>
    <?php endif; ?>

    <!-- Özet güncelleme hatası (JS yazar) -->
    <div id="checkoutAlert" class="alert alert-danger d-none" role="alert" aria-live="polite"></div>

    <form id="checkoutForm" method="post" action="<?= $this->e($this->url($formAction)) ?>" novalidate>
        <?= $this->csrfField() ?>

        <div class="row g-4">
            <div class="col-12 col-lg-8">

                <!-- İletişim -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-semibold mb-3">1. İletişim Bilgileri</h2>

                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                                <input type="email" class="form-control<?= $invalid('email') ?>" id="email" name="email"
                                       value="<?= $this->e($val('email')) ?>" maxlength="191"
                                       autocomplete="email" required>
                                <?php if ($error('email') !== ''): ?>
                                    <div class="invalid-feedback"><?= $this->e($error('email')) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="phone" class="form-label">Telefon <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control<?= $invalid('phone') ?>" id="phone" name="phone"
                                       value="<?= $this->e($val('phone')) ?>" maxlength="20"
                                       inputmode="tel" autocomplete="tel" placeholder="05XX XXX XX XX" required>
                                <?php if ($error('phone') !== ''): ?>
                                    <div class="invalid-feedback"><?= $this->e($error('phone')) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Teslimat adresi -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-semibold mb-3">2. Teslimat Adresi</h2>

                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label for="first_name" class="form-label">Ad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control<?= $invalid('first_name') ?>" id="first_name"
                                       name="first_name" value="<?= $this->e($val('first_name')) ?>"
                                       maxlength="100" autocomplete="given-name" required>
                                <?php if ($error('first_name') !== ''): ?>
                                    <div class="invalid-feedback"><?= $this->e($error('first_name')) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="last_name" class="form-label">Soyad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control<?= $invalid('last_name') ?>" id="last_name"
                                       name="last_name" value="<?= $this->e($val('last_name')) ?>"
                                       maxlength="100" autocomplete="family-name" required>
                                <?php if ($error('last_name') !== ''): ?>
                                    <div class="invalid-feedback"><?= $this->e($error('last_name')) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label for="city" class="form-label">İl <span class="text-danger">*</span></label>
                                <input type="text" class="form-control<?= $invalid('city') ?>" id="city" name="city"
                                       value="<?= $this->e($val('city')) ?>" maxlength="100"
                                       autocomplete="address-level1" required>
                                <?php if ($error('city') !== ''): ?>
                                    <div class="invalid-feedback"><?= $this->e($error('city')) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="district" class="form-label">İlçe <span class="text-danger">*</span></label>
                                <input type="text" class="form-control<?= $invalid('district') ?>" id="district"
                                       name="district" value="<?= $this->e($val('district')) ?>"
                                       maxlength="100" autocomplete="address-level2" required>
                                <?php if ($error('district') !== ''): ?>
                                    <div class="invalid-feedback"><?= $this->e($error('district')) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12 col-sm-8">
                                <label for="neighborhood" class="form-label">Mahalle</label>
                                <input type="text" class="form-control<?= $invalid('neighborhood') ?>" id="neighborhood"
                                       name="neighborhood" value="<?= $this->e($val('neighborhood')) ?>"
                                       maxlength="150">
                                <?php if ($error('neighborhood') !== ''): ?>
                                    <div class="invalid-feedback"><?= $this->e($error('neighborhood')) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 col-sm-4">
                                <label for="postal_code" class="form-label">Posta kodu</label>
                                <input type="text" class="form-control<?= $invalid('postal_code') ?>" id="postal_code"
                                       name="postal_code" value="<?= $this->e($val('postal_code')) ?>"
                                       maxlength="5" inputmode="numeric" autocomplete="postal-code">
                                <?php if ($error('postal_code') !== ''): ?>
                                    <div class="invalid-feedback"><?= $this->e($error('postal_code')) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12">
                                <label for="address_line" class="form-label">Adres <span class="text-danger">*</span></label>
                                <textarea class="form-control<?= $invalid('address_line') ?>" id="address_line"
                                          name="address_line" rows="3" maxlength="500"
                                          autocomplete="street-address" required><?= $this->e($val('address_line')) ?></textarea>
                                <?php if ($error('address_line') !== ''): ?>
                                    <div class="invalid-feedback"><?= $this->e($error('address_line')) ?></div>
                                <?php endif; ?>
                                <div class="form-text">Sokak, bina ve daire numarasını yazın.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kargo yöntemi -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-semibold mb-3">3. Kargo Yöntemi</h2>

                        <?php if ($methods === []): ?>
                            <div class="alert alert-warning mb-0" role="alert">
                                Şu an seçilebilir bir kargo yöntemi yok. Lütfen daha sonra tekrar deneyin.
                            </div>
                        <?php else: ?>
                            <div class="list-group" id="shippingList">
                                <?php foreach ($methods as $method): ?>
                                    <?php
                                    $methodId  = (int) $method['id'];
                                    $inputId   = 'shipping' . $methodId;
                                    $isChecked = $methodId === $selectedMethod;
                                    $fee       = (float) $method['fee'];
                                    $remaining = $method['remaining'];
                                    ?>
                                    <label class="list-group-item d-flex gap-3 align-items-start" for="<?= $this->e($inputId) ?>">
                                        <input class="form-check-input mt-1 flex-shrink-0" type="radio"
                                               name="shipping_method_id" id="<?= $this->e($inputId) ?>"
                                               value="<?= $this->e((string) $methodId) ?>"
                                               data-shipping-input<?= $isChecked ? ' checked' : '' ?>>
                                        <span class="flex-grow-1">
                                            <span class="d-flex justify-content-between gap-2">
                                                <span class="fw-semibold"><?= $this->e($method['name']) ?></span>
                                                <span class="fw-semibold text-nowrap"
                                                      data-shipping-fee="<?= $this->e((string) $methodId) ?>"><?= $this->e($feeText($fee)) ?></span>
                                            </span>
                                            <?php if ((string) $method['description'] !== ''): ?>
                                                <span class="d-block small text-muted"><?= $this->e($method['description']) ?></span>
                                            <?php endif; ?>
                                            <span class="d-block small text-success<?= $remaining === null ? ' d-none' : '' ?>"
                                                  data-shipping-remaining="<?= $this->e((string) $methodId) ?>"><?php
                                                if ($remaining !== null) {
                                                    echo $this->e($money($remaining) . ' daha ekleyin, kargo ücretsiz olsun.');
                                                }
                                            ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($error('shipping_method_id') !== ''): ?>
                                <div class="text-danger small mt-2"><?= $this->e($error('shipping_method_id')) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Ödeme yöntemi -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-semibold mb-3">4. Ödeme Yöntemi</h2>

                        <div class="list-group">
                            <?php foreach ($paymentMethods as $code => $label): ?>
                                <?php
                                $code      = (string) $code;
                                $inputId   = 'payment_' . $code;
                                $isChecked = $selectedPayment === $code;
                                ?>
                                <label class="list-group-item d-flex gap-3 align-items-start" for="<?= $this->e($inputId) ?>">
                                    <input class="form-check-input mt-1 flex-shrink-0" type="radio"
                                           name="payment_method" id="<?= $this->e($inputId) ?>"
                                           value="<?= $this->e($code) ?>"<?= $isChecked ? ' checked' : '' ?>>
                                    <span>
                                        <span class="fw-semibold d-block"><?= $this->e((string) $label) ?></span>
                                        <?php if ($code === 'credit_card'): ?>
                                            <span class="small text-muted">Kart bilgileriniz sitemizde saklanmaz; ödeme güvenli ödeme sayfasında alınır.</span>
                                        <?php elseif ($code === 'bank_transfer'): ?>
                                            <span class="small text-muted">Sipariş sonrası banka bilgileri ve açıklama kodu gösterilir.</span>
                                        <?php elseif ($code === 'cash_on_delivery'): ?>
                                            <span class="small text-muted">Ödemeyi teslimat sırasında yaparsınız.</span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($error('payment_method') !== ''): ?>
                            <div class="text-danger small mt-2"><?= $this->e($error('payment_method')) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Not -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-semibold mb-3">5. Sipariş Notu</h2>
                        <label for="customer_note" class="visually-hidden">Sipariş notu</label>
                        <textarea class="form-control<?= $invalid('customer_note') ?>" id="customer_note"
                                  name="customer_note" rows="2" maxlength="1000"
                                  placeholder="İsteğe bağlı"><?= $this->e($val('customer_note')) ?></textarea>
                        <?php if ($error('customer_note') !== ''): ?>
                            <div class="invalid-feedback"><?= $this->e($error('customer_note')) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sipariş özeti -->
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-semibold mb-3">Sipariş Özeti</h2>

                        <ul class="list-unstyled mb-3">
                            <?php foreach ($items as $item): ?>
                                <?php $variantName = (string) ($item['variant_name'] ?? ''); ?>
                                <li class="d-flex justify-content-between gap-2 small mb-2">
                                    <span>
                                        <?= $this->e($item['name']) ?>
                                        <?php if ($variantName !== ''): ?>
                                            <span class="text-muted">(<?= $this->e($variantName) ?>)</span>
                                        <?php endif; ?>
                                        <span class="text-muted">&times; <?= $this->e((string) (int) $item['quantity']) ?></span>
                                    </span>
                                    <span class="text-nowrap"><?= $this->e($money($item['total_price'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <hr>

                        <dl class="row mb-3">
                            <dt class="col-6 fw-normal text-muted">Ara toplam</dt>
                            <dd class="col-6 text-end mb-1" data-summary="subtotal"><?= $this->e($money($quote['subtotal'] ?? 0)) ?></dd>

                            <dt class="col-6 fw-normal text-muted">İndirim</dt>
                            <dd class="col-6 text-end mb-1" data-summary="discount"><?= $this->e($money($quote['discount_total'] ?? 0)) ?></dd>

                            <dt class="col-6 fw-normal text-muted">Kargo</dt>
                            <dd class="col-6 text-end mb-1" data-summary="shipping"><?php
                                if (!$hasSelected) {
                                    echo '—';
                                } else {
                                    echo $this->e($feeText((float) ($quote['shipping_total'] ?? 0)));
                                }
                            ?></dd>

                            <dt class="col-6 fs-5 fw-bold border-top pt-2 mt-1">Toplam</dt>
                            <dd class="col-6 fs-5 fw-bold text-success text-end border-top pt-2 mt-1"
                                data-summary="grand"><?= $this->e($money($quote['grand_total'] ?? 0)) ?></dd>
                        </dl>

                        <p class="small text-muted">Fiyatlar KDV dahildir.</p>

                        <button type="submit" class="btn btn-success btn-lg w-100" id="checkoutSubmit"<?= $methods === [] ? ' disabled' : '' ?>>
                            Siparişi Onayla
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    (function () {
        'use strict';

        var form = document.getElementById('checkoutForm');
        var button = document.getElementById('checkoutSubmit');
        var alertBox = document.getElementById('checkoutAlert');

        if (!form || !button || !alertBox) {
            return;
        }

        var summaryUrl = <?= json_encode($this->url($summaryPath), $jsonFlags) ?>;
        var originalText = button.textContent;
        var requestId = 0;

        function showError(message) {
            alertBox.textContent = String(message);
            alertBox.classList.remove('d-none');
        }

        function clearError() {
            alertBox.textContent = '';
            alertBox.classList.add('d-none');
        }

        function setText(selector, text) {
            var el = form.querySelector(selector);

            if (el) {
                el.textContent = String(text);
            }
        }

        // Sunucu özetini ekrana yansıt (yalnızca textContent: XSS güvenli)
        function applySummary(data) {
            setText('[data-summary="subtotal"]', data.subtotal_text);
            setText('[data-summary="discount"]', data.discount_text);
            setText('[data-summary="shipping"]', data.shipping_text);
            setText('[data-summary="grand"]', data.grand_total_text);

            (data.methods || []).forEach(function (method) {
                var id = String(method.id);
                var fee = form.querySelector('[data-shipping-fee="' + id + '"]');
                var remaining = form.querySelector('[data-shipping-remaining="' + id + '"]');

                if (fee) {
                    fee.textContent = method.fee_text;
                }

                if (remaining) {
                    if (method.remaining_text) {
                        remaining.textContent = method.remaining_text + ' daha ekleyin, kargo ücretsiz olsun.';
                        remaining.classList.remove('d-none');
                    } else {
                        remaining.textContent = '';
                        remaining.classList.add('d-none');
                    }
                }
            });
        }

        // Kargo yöntemi değişince toplamları sunucudan al (FR-043)
        function refreshSummary(methodId) {
            var current = ++requestId;

            fetch(summaryUrl + '?kargo=' + encodeURIComponent(methodId), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return null;
                    }).then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    // Daha yeni bir istek başladıysa bu yanıt yok sayılır
                    if (current !== requestId) {
                        return;
                    }

                    if (!result.ok || !result.data || result.data.success !== true) {
                        throw new Error((result.data && result.data.message) || 'Toplamlar güncellenemedi.');
                    }

                    clearError();
                    applySummary(result.data);
                })
                .catch(function (error) {
                    if (current === requestId) {
                        showError(error.message || 'Toplamlar güncellenemedi.');
                    }
                });
        }

        form.querySelectorAll('[data-shipping-input]').forEach(function (input) {
            input.addEventListener('change', function () {
                if (input.checked) {
                    refreshSummary(input.value);
                }
            });
        });

        // Gönderim sırasında butonu devre dışı bırakır (çift tıklamayı önler)
        form.addEventListener('submit', function () {
            button.disabled = true;
            button.textContent = 'Sipariş oluşturuluyor...';
        });

        // Tarayıcının geri tuşuyla dönüldüğünde buton eski haline gelir
        window.addEventListener('pageshow', function () {
            button.disabled = false;
            button.textContent = originalText;

            // Önbellekten dönüşte seçili kargoya göre toplamlar tazelenir
            var checked = form.querySelector('[data-shipping-input]:checked');

            if (checked) {
                refreshSummary(checked.value);
            }
        });
    })();
</script>