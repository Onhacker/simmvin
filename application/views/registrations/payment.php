<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$billingMode = isset($registration['billing_mode']) ? $registration['billing_mode'] : 'per_village';
$isPerParticipant = $billingMode === 'per_participant';
$isVillageExtra = $billingMode === 'per_village_extra';
?>

<?php if (validation_errors() || !empty($error)): ?>
    <div class="alert me-3 ms-3 rounded-s bg-red-dark shadow-xl" role="alert">
        <span class="alert-icon color-white"><i class="fa fa-times-circle font-18"></i></span>
        <h4 class="color-white">Pembayaran belum dapat disimpan</h4>
        <div class="alert-icon-text color-white">
            <?= validation_errors('<div class="color-white">', '</div>') ?>
            <?php if (!empty($error)): ?><div class="color-white"><?= e($error) ?></div><?php endif; ?>
        </div>
        <button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button>
    </div>
<?php endif; ?>

<div class="row justify-content-center mx-0">
    <div class="col-xl-8 col-lg-10">
        <form method="post" enctype="multipart/form-data" id="payment-form" data-mode="<?= e($registration['billing_mode']) ?>">
            <?= csrf_field() ?>
            <div class="card card-style mx-0">
                <div class="content mb-2">
                    <p class="font-600 color-highlight mb-n1">Penerimaan dana pelatihan</p>
                    <h3><?= e($registration['event_name']) ?></h3>
                    <p class="opacity-60"><i class="fa fa-map-marker-alt me-2 color-highlight"></i><?= e($registration['village_name'].' · '.$registration['district_name']) ?></p>
                    <div class="divider"></div>

                    <div class="row mb-0">
                        <?php if ($isPerParticipant): ?>
                            <div class="col-12">
                                <div class="input-style has-borders no-icon input-style-always-active mb-4">
                                    <label for="payment-participant" class="color-highlight">Peserta tujuan</label>
                                    <select name="participant_id" id="payment-participant" class="form-select" required>
                                        <option value="">Pilih peserta</option>
                                        <?php foreach ($registration['participants'] as $participant): ?>
                                            <?php
                                            $participantCommittedCents = simp_money_cents(isset($participant['committed_amount'])
                                                ? $participant['committed_amount'] : $participant['paid_amount']);
                                            $participantExpectedCents = simp_money_cents($participant['expected_amount']);
                                            if ($participantCommittedCents === NULL) $participantCommittedCents = 0;
                                            if ($participantExpectedCents === NULL) $participantExpectedCents = 0;
                                            $remaining = simp_money_from_cents(max(0, $participantExpectedCents - $participantCommittedCents));
                                            ?>
                                            <option value="<?= (int) $participant['id'] ?>" data-remaining="<?= $remaining ?>" <?= old('participant_id') == $participant['id'] ? 'selected' : '' ?>><?= e($participant['full_name']) ?> · sisa <?= rupiah($remaining) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span><i class="fa fa-chevron-down"></i></span>
                                    <i class="fa fa-check disabled valid color-green-dark"></i>
                                    <i class="fa fa-times disabled invalid color-red-dark"></i>
                                    <em></em>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php
                            $registrationCommittedCents = simp_money_cents(isset($registration['committed_amount'])
                                ? $registration['committed_amount'] : $registration['paid_amount']);
                            $registrationExpectedCents = simp_money_cents($registration['expected_amount']);
                            if ($registrationCommittedCents === NULL) $registrationCommittedCents = 0;
                            if ($registrationExpectedCents === NULL) $registrationExpectedCents = 0;
                            $remaining = simp_money_from_cents(max(0, $registrationExpectedCents - $registrationCommittedCents));
                            ?>
                            <input type="hidden" id="village-payment-remaining" value="<?= $remaining ?>">
                            <div class="col-12">
                                <div class="alert rounded-s bg-blue-dark shadow-s" role="alert">
                                    <span class="alert-icon color-white"><i class="fa fa-info-circle font-18"></i></span>
                                    <h4 class="color-white"><?= $isVillageExtra ? 'Pembayaran paket dan tambahan peserta' : 'Pembayaran tingkat desa' ?></h4>
                                    <strong class="alert-icon-text color-white">Sisa tagihan yang dapat dicatat: <?= rupiah($remaining) ?></strong>
                                    <button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="col-md-6">
                            <div class="input-style has-borders no-icon input-style-always-active mb-4">
                                <input type="date" name="payment_date" id="payment-date" class="form-control" required value="<?= e(old('payment_date', date('Y-m-d'))) ?>">
                                <label for="payment-date" class="color-highlight">Tanggal pembayaran</label>
                                <i class="fa fa-check disabled valid me-4 pe-3 font-12 color-green-dark"></i>
                                <i class="fa fa-times disabled invalid me-4 pe-3 font-12 color-red-dark"></i>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-style has-borders no-icon input-style-always-active mb-4">
                                <label for="payment-method" class="color-highlight">Metode pembayaran</label>
                                <select name="method" id="payment-method" class="form-select" required>
                                    <option value="cash" <?= old('method', 'cash') === 'cash' ? 'selected' : '' ?>>Tunai</option>
                                    <option value="transfer" <?= old('method') === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                                    <option value="qris" <?= old('method') === 'qris' ? 'selected' : '' ?>>QRIS</option>
                                </select>
                                <span><i class="fa fa-chevron-down"></i></span>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <em></em>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-style has-borders no-icon input-style-always-active mb-4">
                                <label for="payment-account" class="color-highlight">Akun penerima</label>
                                <select name="account_id" id="payment-account" class="form-select" required>
                                    <option value="">Pilih akun dana</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?= (int) $account['id'] ?>" <?= old('account_id') == $account['id'] ? 'selected' : '' ?>><?= e($account['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span><i class="fa fa-chevron-down"></i></span>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <em></em>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-style has-borders no-icon input-style-always-active mb-1">
                                <input type="number" min="1" step="1" name="amount" id="payment-amount" class="form-control" required value="<?= e(old('amount')) ?>" placeholder="0">
                                <label for="payment-amount" class="color-highlight">Nominal pembayaran</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <em>*</em>
                            </div>
                            <p id="payment-remaining-label" class="font-10 opacity-60 mb-4 ps-2"></p>
                        </div>
                        <div class="col-12">
                            <div class="input-style has-borders no-icon input-style-always-active mb-1">
                                <input type="file" name="proof" id="payment-proof" class="form-control" accept="image/jpeg,image/png,application/pdf" style="padding-top:13px;">
                                <label for="payment-proof" class="color-highlight">Bukti pembayaran</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <em id="proof-required-label"></em>
                            </div>
                            <p class="font-10 opacity-60 mb-4 ps-2">JPG, PNG, atau PDF maksimal 5 MB. Bukti diperlukan untuk transfer/QRIS.</p>
                        </div>
                        <div class="col-12">
                            <div class="input-style has-borders no-icon input-style-always-active mb-4">
                                <textarea name="note" id="payment-note" class="form-control" rows="3" placeholder="Catatan pembayaran"><?= e(old('note')) ?></textarea>
                                <label for="payment-note" class="color-highlight">Catatan</label>
                                <em class="mt-n3"></em>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content mt-0 mb-4">
                <div class="row mb-0">
                    <div class="col-6 pe-1">
                        <a class="btn btn-full btn-m border-highlight color-highlight rounded-s font-600 font-13" href="<?= site_url('registrasi/'.$registration['id']) ?>">Batal</a>
                    </div>
                    <div class="col-6 ps-1">
                        <button class="btn btn-full btn-m gradient-highlight rounded-s font-600 font-13 shadow-s" type="submit">
                            <i class="fa fa-save me-2"></i>Simpan
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
