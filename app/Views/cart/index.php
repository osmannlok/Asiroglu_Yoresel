<?php

/**
 * Aşıroğlu Yöresel Market - Sepet sayfası view'ı
 *
 * CartController::index() tarafından render edilir ve layouts/main.php
 * içine yerleştirilir.
 *
 * Değişkenler:
 *   $items    (zorunlu) CartService::summary()['items'] kalemleri:
 *             key, product_id, variant_id, name, variant_name, sku, slug,
 *             quantity, unit_price, total_price, stock, available
 *   $subtotal (zorunlu) ara toplam (KDV dahil, TRY)
 *   $quantity (zorunlu) toplam adet
 *
 * Adet güncelleme ve kalem çıkarma sayfa yenilenmeden Fetch API ile yapılır
 * (POST /sepet/guncelle, POST /sepet/sil). CSRF token layout'taki
 * csrf-token meta etiketinden okunur ve X-CSRF-TOKEN başlığıyla gönderilir.
 * Sunucu yanıtı (özet) görünümün tek doğruluk kaynağıdır.
 *
 * Fiyatlar bilgilendirme amaçlıdır; sipariş oluşturulurken güncel fiyat ve
 * stokla yeniden kontrol edilir (DECISIONS.md, Misafir Sepeti).
 *
 * Kullanılabilir yardımcılar: $this->e(), $this->url()
 *
 * Gereksinimler: FR-042, FR-043, FR-044, FR-045, NFR-003, NFR-005.
 */

declare(strict_types=1);

$items    = isset($items) && is_array($items) ? $items : [];
$subtotal = (float) ($subtotal ?? 0);
$quantity = (int) ($quantity ?? 0);

/** Tutarı Türkçe biçimde yazar: 1.250,50 ₺ */
$money = static fn (mixed $amount): string => number_format((float) $amount, 2, ',', '.') . ' ₺';

// Satın alınamayan kalem var mı? (Ödemeye Geç butonu kilitlenir)
$hasProblem = false;

foreach ($items as $item) {
    if (empty($item['available'])) {
        $hasProblem = true;
        break;
    }
}

$isEmpty = $items === [];
?>
<div class="container py-4 py-lg-5">
    <h1 class="h3 fw-bold mb-4">Sepetim</h1>

    <!-- İşlem sonucu bildirimi (JS yazar) -->
    <div id="cartAlert" class="alert d-none" role="alert" aria-live="polite"></div>

    <!-- Boş sepet -->
    <div id="cartEmpty" class="card border-0 shadow-sm text-center py-5<?= $isEmpty ? '' : ' d-none' ?>">
        <div class="card-body">
            <h2 class="h5 fw-semibold mb-2">Sepetiniz boş</h2>
            <p class="text-muted mb-4">Henüz sepetinize ürün eklemediniz.</p>
            <a href="<?= $this->e($this->url('/')) ?>" class="btn btn-success px-4">Alışverişe Başla</a>
        </div>
    </div>

    <!-- Dolu sepet -->
    <div id="cartContent" class="row g-4<?= $isEmpty ? ' d-none' : '' ?>"
         data-update-url="<?= $this->e($this->url('/sepet/guncelle')) ?>"
         data-remove-url="<?= $this->e($this->url('/sepet/sil')) ?>">

        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <ul class="list-group list-group-flush" id="cartList">
                    <?php foreach ($items as $item): ?>
                        <?php
                        $key         = (string) $item['key'];
                        $qty         = (int) $item['quantity'];
                        $stock       = (int) $item['stock'];
                        $available   = !empty($item['available']);
                        $variantName = (string) ($item['variant_name'] ?? '');
                        $maxQty      = $stock > 0 ? min($stock, 1000) : 1000;

                        if ($available) {
                            $note = '';
                        } elseif ($stock <= 0) {
                            $note = 'Bu ürün şu an satın alınamıyor.';
                        } else {
                            $note = 'Stokta yalnızca ' . $stock . ' adet var.';
                        }
                        ?>
                        <li class="list-group-item p-3 p-md-4" data-cart-row
                            data-key="<?= $this->e($key) ?>">
                            <div class="row g-3 align-items-center">

                                <div class="col-12 col-md-5">
                                    <a href="<?= $this->e($this->url('/urun/' . (string) $item['slug'])) ?>"
                                       class="fw-semibold text-decoration-none text-success">
                                        <?= $this->e($item['name']) ?>
                                    </a>
                                    <?php if ($variantName !== ''): ?>
                                        <div class="small text-muted"><?= $this->e($variantName) ?></div>
                                    <?php endif; ?>
                                    <div class="small text-muted">SKU: <?= $this->e($item['sku']) ?></div>
                                    <div class="small text-danger mt-1<?= $note === '' ? ' d-none' : '' ?>"
                                         data-stock-note><?= $this->e($note) ?></div>
                                </div>

                                <div class="col-7 col-md-4">
                                    <div class="small text-muted mb-1">
                                        Birim: <span data-unit-price><?= $this->e($money($item['unit_price'])) ?></span>
                                    </div>
                                    <div class="input-group input-group-sm" style="max-width: 150px;">
                                        <button type="button" class="btn btn-outline-secondary"
                                                data-qty-minus aria-label="Adedi azalt"<?= $qty <= 1 ? ' disabled' : '' ?>>&minus;</button>
                                        <input type="number" class="form-control text-center"
                                               data-qty-input value="<?= $this->e((string) $qty) ?>"
                                               min="1" max="<?= $this->e((string) $maxQty) ?>" step="1"
                                               inputmode="numeric" aria-label="Adet">
                                        <button type="button" class="btn btn-outline-secondary"
                                                data-qty-plus aria-label="Adedi artır">+</button>
                                    </div>
                                </div>

                                <div class="col-5 col-md-3 text-end">
                                    <div class="fw-bold text-nowrap" data-line-total><?= $this->e($money($item['total_price'])) ?></div>
                                    <button type="button" class="btn btn-link btn-sm text-danger p-0 mt-1"
                                            data-remove>Çıkar</button>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Sipariş Özeti</h2>

                    <dl class="row mb-3">
                        <dt class="col-7 fw-normal text-muted">Ürün adedi</dt>
                        <dd class="col-5 text-end mb-1" data-count><?= $this->e((string) $quantity) ?></dd>

                        <dt class="col-7 fw-normal text-muted">Ara toplam</dt>
                        <dd class="col-5 text-end mb-1 fw-semibold" data-subtotal><?= $this->e($money($subtotal)) ?></dd>
                    </dl>

                    <p class="small text-muted">
                        Fiyatlar KDV dahildir. Kargo ücreti ve kupon indirimi ödeme adımında hesaplanır.
                    </p>

                    <div id="cartProblem" class="alert alert-warning small<?= $hasProblem ? '' : ' d-none' ?>" role="note">
                        Sepetinizde satın alınamayan ürün var. Devam etmek için o ürünü çıkarın veya adedini düşürün.
                    </div>

                    <!-- Ödeme modülü yazılınca bağlantıya çevrilecek -->
                    <button type="button" class="btn btn-success w-100" id="checkoutButton" disabled
                            title="Ödeme adımı henüz hazır değil">
                        Ödemeye Geç
                    </button>

                    <a href="<?= $this->e($this->url('/')) ?>"
                       class="btn btn-link w-100 mt-2 text-decoration-none">Alışverişe Devam Et</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        'use strict';

        var content = document.getElementById('cartContent');
        var emptyBox = document.getElementById('cartEmpty');
        var alertBox = document.getElementById('cartAlert');
        var problemBox = document.getElementById('cartProblem');
        var list = document.getElementById('cartList');

        if (!content || !emptyBox || !alertBox || !list) {
            return;
        }

        var tokenMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : '';
        var updateUrl = content.getAttribute('data-update-url');
        var removeUrl = content.getAttribute('data-remove-url');
        var busy = false;

        // --- Bildirim (textContent: XSS güvenli) -----------------------------
        function notify(message, type) {
            alertBox.className = 'alert alert-' + type;
            alertBox.textContent = String(message);
        }

        function clearNotice() {
            alertBox.className = 'alert d-none';
            alertBox.textContent = '';
        }

        // --- Fetch yardımcısı ------------------------------------------------
        function post(url, payload) {
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            }).then(function (response) {
                return response.json().catch(function () {
                    return null;
                }).then(function (data) {
                    return { ok: response.ok, status: response.status, data: data };
                });
            });
        }

        // --- Satır yardımcıları ----------------------------------------------
        function rows() {
            return Array.prototype.slice.call(list.querySelectorAll('[data-cart-row]'));
        }

        function findRow(key) {
            var all = rows();

            for (var i = 0; i < all.length; i++) {
                if (all[i].getAttribute('data-key') === key) {
                    return all[i];
                }
            }

            return null;
        }

        function setRowBusy(row, isBusy) {
            row.querySelectorAll('button, input').forEach(function (el) {
                el.disabled = isBusy;
            });

            // Meşgul değilse "azalt" butonu adede göre yeniden ayarlanır
            if (!isBusy) {
                refreshMinus(row);
            }
        }

        function refreshMinus(row) {
            var input = row.querySelector('[data-qty-input]');
            var minus = row.querySelector('[data-qty-minus]');

            if (input && minus) {
                minus.disabled = (parseInt(input.value, 10) || 1) <= 1;
            }
        }

        // --- Sunucu özetini ekrana yansıt ------------------------------------
        function applySummary(data) {
            var seen = {};
            var problem = false;

            (data.items || []).forEach(function (item) {
                var row = findRow(String(item.key));

                if (!row) {
                    return;
                }

                seen[item.key] = true;

                var input = row.querySelector('[data-qty-input]');
                var line = row.querySelector('[data-line-total]');
                var unit = row.querySelector('[data-unit-price]');
                var note = row.querySelector('[data-stock-note]');

                if (input) {
                    input.value = String(item.quantity);
                    input.max = String(item.stock > 0 ? Math.min(item.stock, 1000) : 1000);
                }

                if (line) {
                    line.textContent = item.total_price_text;
                }

                if (unit) {
                    unit.textContent = item.unit_price_text;
                }

                if (note) {
                    if (item.available) {
                        note.textContent = '';
                        note.classList.add('d-none');
                    } else {
                        note.textContent = item.stock > 0
                            ? 'Stokta yalnızca ' + item.stock + ' adet var.'
                            : 'Bu ürün şu an satın alınamıyor.';
                        note.classList.remove('d-none');
                        problem = true;
                    }
                }

                refreshMinus(row);
            });

            // Yanıtta olmayan kalemler sepetten kalkmıştır
            rows().forEach(function (row) {
                if (!seen[row.getAttribute('data-key')]) {
                    row.remove();
                }
            });

            var countEl = content.querySelector('[data-count]');
            var subtotalEl = content.querySelector('[data-subtotal]');

            if (countEl) {
                countEl.textContent = String(data.count);
            }

            if (subtotalEl) {
                subtotalEl.textContent = data.subtotal_text;
            }

            if (problemBox) {
                problemBox.classList.toggle('d-none', !problem);
            }

            var isEmpty = rows().length === 0;
            content.classList.toggle('d-none', isEmpty);
            emptyBox.classList.toggle('d-none', !isEmpty);
        }

        // --- İşlemler --------------------------------------------------------
        function run(row, url, payload, fallbackMessage) {
            if (busy) {
                return;
            }

            busy = true;
            clearNotice();
            setRowBusy(row, true);

            post(url, payload)
                .then(function (result) {
                    if (!result.data) {
                        throw new Error(fallbackMessage);
                    }

                    if (!result.ok || result.data.success !== true) {
                        throw new Error(result.data.message || fallbackMessage);
                    }

                    applySummary(result.data);
                    notify(result.data.message || 'Sepet güncellendi.', 'success');
                })
                .catch(function (error) {
                    // Hatada sunucudaki gerçek durumu yeniden al
                    notify(error.message || fallbackMessage, 'danger');
                    return refreshFromServer();
                })
                .finally(function () {
                    busy = false;

                    if (row.isConnected) {
                        setRowBusy(row, false);
                    }
                });
        }

        function refreshFromServer() {
            return fetch(content.getAttribute('data-update-url').replace(/guncelle$/, 'ozet'), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data && data.success === true) {
                        applySummary(data);
                    }
                })
                .catch(function () {
                    // Özet alınamazsa mevcut ekran korunur
                });
        }

        function changeQuantity(row, quantity) {
            if (isNaN(quantity) || quantity < 1) {
                refreshMinus(row);
                return;
            }

            run(
                row,
                updateUrl,
                { key: row.getAttribute('data-key'), quantity: quantity },
                'Sepet güncellenemedi.'
            );
        }

        list.addEventListener('click', function (event) {
            var row = event.target.closest('[data-cart-row]');

            if (!row) {
                return;
            }

            var input = row.querySelector('[data-qty-input]');
            var current = parseInt(input ? input.value : '1', 10) || 1;

            if (event.target.closest('[data-qty-minus]')) {
                changeQuantity(row, current - 1);
            } else if (event.target.closest('[data-qty-plus]')) {
                changeQuantity(row, current + 1);
            } else if (event.target.closest('[data-remove]')) {
                run(row, removeUrl, { key: row.getAttribute('data-key') }, 'Ürün çıkarılamadı.');
            }
        });

        list.addEventListener('change', function (event) {
            var input = event.target.closest('[data-qty-input]');

            if (!input) {
                return;
            }

            var row = input.closest('[data-cart-row]');
            changeQuantity(row, parseInt(input.value, 10));
        });
    })();
</script>