<?php defined('BASEPATH') OR exit('No direct script access allowed');
$incomeBreakdown = isset($breakdown['income']) && is_array($breakdown['income']) ? $breakdown['income'] : array();
$incomeEvents = isset($incomeBreakdown['events']) && is_array($incomeBreakdown['events']) ? $incomeBreakdown['events'] : array();
$modeLabels = array('per_village'=>'Per Desa','per_participant'=>'Per Peserta','per_village_extra'=>'Desa + Peserta Tambahan');
$financeCalculation = function ($event) use ($modeLabels) {
    $mode = isset($event['billing_mode']) ? (string)$event['billing_mode'] : 'per_village';
    $villageCount = (int)($event['villages'] ?? 0);
    $participantCount = (int)($event['participants'] ?? 0);
    $additionalCount = (int)($event['additional_participants'] ?? 0);
    $villageFee = isset($event['village_fee_cents']) ? simp_money_from_cents((int)$event['village_fee_cents']) : '0.00';
    $participantFee = isset($event['participant_fee_cents']) ? simp_money_from_cents((int)$event['participant_fee_cents']) : '0.00';
    $total = isset($event['tariff_total']) ? $event['tariff_total'] : '0.00';
    $lines = array();
    if ($mode === 'per_participant') {
        $lines[] = number_format($participantCount) . ' peserta x ' . rupiah($participantFee) . ' = ' . rupiah($total);
    } elseif ($mode === 'per_village_extra') {
        $villageSubtotal = simp_money_from_cents($villageCount * (int)($event['village_fee_cents'] ?? 0));
        $additionalSubtotal = simp_money_from_cents($additionalCount * (int)($event['participant_fee_cents'] ?? 0));
        $lines[] = number_format($villageCount) . ' desa x ' . rupiah($villageFee) . ' = ' . rupiah($villageSubtotal);
        $lines[] = number_format($additionalCount) . ' peserta tambahan x ' . rupiah($participantFee) . ' = ' . rupiah($additionalSubtotal);
        $lines[] = 'Total berdasarkan tarif = ' . rupiah($total);
    } else {
        $lines[] = number_format($villageCount) . ' desa x ' . rupiah($villageFee) . ' = ' . rupiah($total);
    }
    return array('lines'=>$lines, 'mode'=>isset($modeLabels[$mode]) ? $modeLabels[$mode] : ucfirst($mode));
};
?>
<div id="finance-report-content" data-finance-report-content>
    <div class="content mb-0 mt-n2">
        <div class="row mb-0">
            <div class="col-xl-3 col-6">
                <div class="card card-style mx-0 mb-3 p-3">
                    <i class="fas fa-arrow-down color-green-dark font-24 mb-2"></i>
                    <p class="font-12 opacity-60 mb-n1">Pemasukan akun masuk total</p>
                    <h3 class="color-green-dark mb-0 font-18"><?= rupiah($report['income']) ?></h3>
                </div>
            </div>
            <div class="col-xl-3 col-6">
                <div class="card card-style mx-0 mb-3 p-3">
                    <i class="fas fa-arrow-up color-red-dark font-24 mb-2"></i>
                    <p class="font-12 opacity-60 mb-n1">Pengeluaran akun masuk total</p>
                    <h3 class="color-red-dark mb-0 font-18"><?= rupiah($report['expenses']) ?></h3>
                </div>
            </div>
            <div class="col-xl-3 col-6">
                <div class="card card-style mx-0 mb-3 p-3">
                    <i class="fas fa-percentage color-blue-dark font-24 mb-2"></i>
                    <p class="font-12 opacity-60 mb-n1">Biaya transfer dari akun masuk total</p>
                    <h3 class="color-blue-dark mb-0 font-18"><?= rupiah($report['transfer_fees']) ?></h3>
                </div>
            </div>
            <div class="col-xl-3 col-6">
                <div class="card card-style mx-0 mb-3 p-3">
                    <i class="fas fa-balance-scale <?= (int)$report['net_cents'] >= 0 ? 'color-green-dark' : 'color-red-dark' ?> font-24 mb-2"></i>
                    <p class="font-12 opacity-60 mb-n1">Net transaksi</p>
                    <h3 class="mb-0 font-18 <?= (int)$report['net_cents'] >= 0 ? 'color-green-dark' : 'color-red-dark' ?>"><?= rupiah($report['net']) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style mx-3 mb-3 bg-blue-light shadow-0">
        <div class="content py-2 mb-0">
            <div class="d-flex flex-wrap align-items-center">
                <span class="font-11 color-blue-dark me-3"><i class="fas fa-info-circle me-1"></i>Net transaksi memperhitungkan transfer lintas akun dan jurnal penyesuaian; saldo awal ditampilkan terpisah.</span>
                <span class="font-11 color-blue-dark ms-auto">Saldo awal periode: <strong><?= rupiah($report['period_opening_balance']) ?></strong> · Saldo akhir: <strong><?= rupiah($report['period_ending_balance']) ?></strong></span>
            </div>
            <?php if ((int)$report['transfer_in_cents'] > 0 || (int)$report['transfer_out_cents'] > 0): ?>
                <div class="font-10 color-blue-dark mt-2">Transfer masuk dari akun dikecualikan: <strong><?= rupiah($report['transfer_in']) ?></strong> · Transfer keluar ke akun dikecualikan: <strong><?= rupiah($report['transfer_out']) ?></strong></div>
            <?php endif; ?>
            <?php if (isset($report['adjustment_cents']) && (int)$report['adjustment_cents'] !== 0): ?>
                <div class="font-10 color-blue-dark mt-2">Penyesuaian saldo jurnal: <strong><?= rupiah($report['adjustment']) ?></strong></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card card-style">
        <div class="content mb-2">
            <div class="d-flex align-items-center mb-2">
                <div>
                    <p class="font-600 color-highlight mb-n1">Rincian pemasukan</p>
                    <h2 class="mb-0">Perhitungan Tarif</h2>
                </div>
                <span class="badge bg-green-dark color-white ms-auto"><?= rupiah($incomeBreakdown['tariff_total'] ?? 0) ?></span>
            </div>
            <p class="font-11 opacity-60 mb-2">Total berdasarkan tarif event; penerimaan terverifikasi ditampilkan di sebelah kanan.</p>
            <?php if (!$incomeEvents): ?><div class="text-center py-3 opacity-60">Belum ada event atau registrasi.</div><?php endif; ?>
            <?php foreach ($incomeEvents as $eventIndex => $event): $calculation = $financeCalculation($event); ?>
                <div class="d-flex align-items-start py-3 <?= $eventIndex + 1 < count($incomeEvents) ? 'border-bottom' : '' ?>">
                    <div class="min-width-zero me-2">
                        <p class="font-10 color-highlight text-uppercase font-600 mb-n1"><?= e($calculation['mode']) ?></p>
                        <h5 class="font-14 mb-1 text-break"><?= e($event['name']) ?></h5>
                        <?php foreach ($calculation['lines'] as $calculationIndex => $calculationLine): ?><p class="font-12 <?= $calculationIndex + 1 < count($calculation['lines']) ? 'mb-1' : 'mb-0' ?> text-break<?= $calculationIndex + 1 === count($calculation['lines']) && count($calculation['lines']) > 1 ? ' font-600' : '' ?>"><?= e($calculationLine) ?></p><?php endforeach; ?>
                    </div>
                    <strong class="font-13 color-green-dark ms-auto text-end flex-shrink-0">Masuk<br><?= rupiah($event['income']) ?></strong>
                </div>
            <?php endforeach; ?>
            <?php if ($incomeEvents): ?><div class="d-flex align-items-center pt-3 border-top"><strong class="font-13">Total berdasarkan tarif</strong><strong class="font-15 color-blue-dark ms-auto"><?= rupiah($incomeBreakdown['tariff_total'] ?? 0) ?></strong></div><?php endif; ?>
        </div>
    </div>

    <div class="card card-style">
        <div class="content mb-2">
            <div class="d-flex align-items-center mb-3">
                <div>
                    <p class="font-600 color-highlight mb-n1">Saldo berdasarkan buku besar<?= $filters['date_to'] ? ' per ' . tanggal_id($filters['date_to']) : '' ?></p>
                    <h2 class="mb-0">Posisi Kas &amp; Rekening</h2>
                </div>
                <div class="text-end ms-auto d-none d-sm-block">
                    <p class="font-11 opacity-60 mb-n1">Total dihitung</p>
                    <h4 class="color-blue-dark mb-0"><?= rupiah($report['included_balance']) ?></h4>
                </div>
            </div>

            <div class="card bg-blue-dark rounded-s shadow-0 d-sm-none mb-3">
                <div class="content my-3">
                    <p class="color-white opacity-70 font-11 mb-n1">Total saldo dihitung</p>
                    <h3 class="color-white mb-0"><?= rupiah($report['included_balance']) ?></h3>
                </div>
            </div>

            <?php if(!$report['accounts']): ?><div class="text-center py-5 opacity-60"><i class="fas fa-wallet d-block font-24 mb-2"></i>Belum ada akun dana.</div><?php endif; ?>
            <?php foreach($report['accounts'] as $account): ?>
                <div class="d-flex align-items-start py-3 border-bottom"><span class="icon icon-s rounded-xl <?= $account['type']==='cash'?'bg-green-light color-green-dark':($account['type']==='qris'?'bg-magenta-light color-magenta-dark':'bg-blue-light color-blue-dark') ?> me-3"><i class="fas <?= $account['type']==='cash'?'fa-money-bill-wave':($account['type']==='qris'?'fa-qrcode':'fa-university') ?>"></i></span><div class="min-width-zero"><h5 class="font-15 mb-n1"><?= e($account['name']) ?></h5><p class="font-11 opacity-60 mb-0"><?= e(ucfirst($account['type']).' · '.($account['bank_name']?:'-')) ?><?= $account['account_number']?' · '.e($account['account_number']):'' ?></p><p class="font-10 mt-1 mb-0"><?= (int)$account['include_in_total']?'<span class="badge bg-green-dark color-white">Masuk Total</span>':'<span class="badge bg-gray-dark color-white">Tidak Dihitung</span>' ?></p></div><strong class="color-blue-dark ms-auto text-end simp-balance-value"><?= rupiah($account['balance']) ?></strong></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
