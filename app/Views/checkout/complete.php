<?php

/**
 * Aşıroğlu Yöresel Market - Sipariş sonuç sayfası view'ı
 *
 * CheckoutController::complete() tarafından render edilir ve layouts/main.php
 * içine yerleştirilir.
 *
 * Değişkenler:
 *   $order         (array) orders satırı
 *   $items         (list) order_items satırları
 *   $address       (array) teslimat adresi anlık kopyası (JSON'dan çözülmüş)
 *   $paymentMethod (string) credit_card | bank_transfer | cash_on_delivery
 *   $paymentLabel  (string) ödeme yönteminin görünen adı
 *   $isMember      (bool) giriş yapmış üye mi?
 *
 * Banka hesap bilgileri (FR-072) ve kart ödemesi yönlendirmesi ödeme
 * modülünde eklenecektir; bu sayfa şimdilik yönteme göre bilgilendirme
 * metni gösterir. Fiyatlar KDV dahil, TRY.
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url()
 *
 * Gereksinimler: FR-063, FR-064, FR-069, FR-072, FR-074, NFR-005.
 */

declare(strict_types=1);

$order         = isset($order) && is_array($order) ? $order : [];
$items         = isset($items) && is_array($items) ? $items : [];
$address       = isset($address) && is_array($address) ? $address : [];
$paymentMethod = isset($paymentMethod) ? (string) $paymentMethod : '';
$paymentLabel  = isset($paymentLabel) ? (string) $paymentLabel : '';
$isMember      = !empty($isMember);

/** Tutarı Türkçe biçimde yazar: 1.250,50 ₺ */
$money = static fn (mixed $amount): string => number_format((float) $amount, 2, ',', '.') . ' ₺';

/** Adres alanını metin olarak döndürür; yoksa boş. */
$addr = static fn (string $key): string => isset($address[$key]) && is_scalar($address[$key])
    ? trim((string) $address[$key])
    : '';

$fullName = trim($addr('first_name') . ' ' . $addr('last_name'));

// "Mahalle, İlçe / İl" satırı: boş parçalar atlanır
$locationParts = array_filter(
    [$addr('neighborhood'), $addr('district')],
    static fn (string $part): bool => $part !== ''
);
$location = implode(', ', $locationParts);

if ($addr('city') !== '') {
    $location .= ($location !== '' ? ' / ' : '') . $addr('city');
}

$shippingTotal = (float) ($order['shipping_total'] ?? 0);
$discountTotal = (float) ($order['discount_total'] ?? 0);
?>
<div class="container py-4 py-lg-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9 col-xl-8">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 text-center">
                    <h1 class="h3 fw-bold text-success mb-2">Siparişiniz alındı</h1>
                    <p class="text-muted mb-3">Teşekkür ederiz. Sipariş numaranız:</p>
                    <p class="fs-4 fw-bold mb-3"><?= $this->e($order['order_number'] ?? '') ?></p>
                    <p class="small text-muted mb-0">
                        Bu numarayı sipariş takibi için saklayın.
                        Onay bilgisi <strong><?= $this->e($order['customer_email'] ?? '') ?></strong> adresine gönderilecektir.
                    </p>
                </div>
            </div>

            <!-- Ödeme bilgilendirmesi -->
            <?php if ($paymentMethod === 'cash_on_delivery'): ?>
                <div class="alert alert-info" role="note">
                    <strong><?= $this->e($paymentLabel) ?>:</strong>
                    Ödemeyi ürünler elinize ulaştığında yaparsınız. Sipariş hazırlanmaya alınacaktır.
                </div>
            <?php elseif ($paymentMethod === 'bank_transfer'): ?>
                <div class="alert alert-warning" role="note">
                    <strong><?= $this->e($paymentLabel) ?>:</strong>
                    Siparişiniz ödeme bekliyor. Banka hesap bilgileri ve açıklama kodu
                    ödeme adımı tamamlandığında bu sayfada gösterilecektir.
                    Ödemeniz onaylanınca sipariş hazırlanmaya alınır.
                </div>
            <?php elseif ($paymentMethod === 'credit_card'): ?>
                <div class="alert alert-warning" role="note">
                    <strong><?= $this->e($paymentLabel) ?>:</strong>
                    Siparişiniz ödeme bekliyor. Güvenli ödeme sayfasına yönlendirme
                    ödeme entegrasyonu tamamlandığında etkinleşecektir.
                    Kart bilgileriniz sitemizde saklanmaz.
                </div>
            <?php endif; ?>

            <!-- Sipariş kalemleri -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Sipariş Özeti</h2>

                    <div class="table-responsive">
                        <table class="table align-middle mb-3">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Ürün</th>
                                    <th scope="col" class="text-end">Birim fiyat</th>
                                    <th scope="col" class="text-end">Adet</th>
                                    <th scope="col" class="text-end">Toplam</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= $this->e($item['product_name'] ?? '') ?></div>
                                            <?php if (trim((string) ($item['product_sku'] ?? '')) !== ''): ?>
                                                <div class="small text-muted">SKU: <?= $this->e($item['product_sku']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-nowrap"><?= $this->e($money($item['unit_price'] ?? 0)) ?></td>
                                        <td class="text-end"><?= $this->e((string) (int) ($item['quantity'] ?? 0)) ?></td>
                                        <td class="text-end text-nowrap"><?= $this->e($money($item['line_total'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <dl class="row mb-0 ms-md-auto" style="max-width: 360px;">
                        <dt class="col-6 fw-normal text-muted">Ara toplam</dt>
                        <dd class="col-6 text-end mb-1"><?= $this->e($money($order['subtotal'] ?? 0)) ?></dd>

                        <?php if ($discountTotal > 0): ?>
                            <dt class="col-6 fw-normal text-muted">İndirim</dt>
                            <dd class="col-6 text-end mb-1">-<?= $this->e($money($discountTotal)) ?></dd>
                        <?php endif; ?>

                        <dt class="col-6 fw-normal text-muted">Kargo</dt>
                        <dd class="col-6 text-end mb-1"><?= $shippingTotal <= 0 ? 'Ücretsiz' : $this->e($money($shippingTotal)) ?></dd>

                        <dt class="col-6 fw-normal text-muted">İçerdiği KDV</dt>
                        <dd class="col-6 text-end mb-1 text-muted"><?= $this->e($money($order['vat_total'] ?? 0)) ?></dd>

                        <dt class="col-6 fs-5 fw-bold border-top pt-2 mt-1">Toplam</dt>
                        <dd class="col-6 fs-5 fw-bold text-success text-end border-top pt-2 mt-1"><?= $this->e($money($order['grand_total'] ?? 0)) ?></dd>
                    </dl>
                </div>
            </div>

            <!-- Teslimat ve ödeme -->
            <div class="row g-4 mb-4">
                <div class="col-12 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h2 class="h6 fw-semibold mb-3">Teslimat Adresi</h2>
                            <?php if ($fullName !== '' || $addr('address_line') !== ''): ?>
                                <address class="mb-0 small">
                                    <?php if ($fullName !== ''): ?>
                                        <strong><?= $this->e($fullName) ?></strong><br>
                                    <?php endif; ?>
                                    <?php if ($addr('address_line') !== ''): ?>
                                        <?= nl2br($this->e($addr('address_line')), false) ?><br>
                                    <?php endif; ?>
                                    <?php if ($location !== ''): ?>
                                        <?= $this->e($location) ?><br>
                                    <?php endif; ?>
                                    <?php if ($addr('postal_code') !== ''): ?>
                                        <?= $this->e($addr('postal_code')) ?><br>
                                    <?php endif; ?>
                                    <?php if ($addr('phone') !== ''): ?>
                                        Tel: <?= $this->e($addr('phone')) ?>
                                    <?php endif; ?>
                                </address>
                            <?php else: ?>
                                <p class="small text-muted mb-0">Adres bilgisi okunamadı.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h2 class="h6 fw-semibold mb-3">Ödeme</h2>
                            <p class="small mb-1">
                                Yöntem: <strong><?= $paymentLabel !== '' ? $this->e($paymentLabel) : '—' ?></strong>
                            </p>
                            <p class="small mb-0">
                                Ödeme durumu:
                                <?php if (($order['payment_status'] ?? '') === 'paid'): ?>
                                    <span class="badge text-bg-success">Ödendi</span>
                                <?php else: ?>
                                    <span class="badge text-bg-warning">Ödeme bekleniyor</span>
                                <?php endif; ?>
                            </p>

                            <?php if (trim((string) ($order['customer_note'] ?? '')) !== ''): ?>
                                <h2 class="h6 fw-semibold mt-4 mb-2">Sipariş Notunuz</h2>
                                <p class="small mb-0"><?= nl2br($this->e($order['customer_note']), false) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$isMember): ?>
                <div class="alert alert-info" role="note">
                    Siparişlerinizi kolayca takip etmek için
                    <a href="<?= $this->e($this->url('/kayit')) ?>" class="alert-link">hesap oluşturun</a>.
                </div>
            <?php endif; ?>

            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a href="<?= $this->e($this->url('/urunler')) ?>" class="btn btn-success px-4">Alışverişe Devam Et</a>
                <a href="<?= $this->e($this->url('/')) ?>" class="btn btn-outline-secondary">Ana Sayfa</a>
            </div>

        </div>
    </div>
</div>