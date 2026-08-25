<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$debts = isset($debts) && is_array($debts) ? $debts : array();
$summary = isset($summary) && is_array($summary) ? $summary : array();
$categories = isset($categories) && is_array($categories) ? $categories : array();
$events = isset($events) && is_array($events) ? $events : array();
$accounts = isset($accounts) && is_array($accounts) ? $accounts : array();
$paymentsByDebt = isset($paymentsByDebt) && is_array($paymentsByDebt) ? $paymentsByDebt : array();
$canCreate = !empty($canCreate);
$canPay = !empty($canPay);
$canVerify = !empty($canVerify);
$canManage = !empty($canManage);
$accountPayload = array();
foreach ($accounts as $account) {
    $accountPayload[] = array('id'=>(int)$account['id'],'name'=>$account['name'],'type'=>$account['type']);
}
$eventPayload = array();
foreach ($events as $event) $eventPayload[] = array('id'=>(int)$event['id'],'name'=>$event['name'],'code'=>$event['code'],'status'=>$event['status']);
$categoryPayload = array();
foreach ($categories as $category) $categoryPayload[] = array('id'=>(int)$category['id'],'name'=>$category['name']);
?>

<div id="debt-content">
    <div class="card card-style">
        <div class="content mb-2">
            <div class="d-flex align-items-start flex-wrap">
                <div class="min-width-zero flex-grow-1 pe-3">
                    <p class="font-600 color-highlight mb-n1">Kewajiban perusahaan</p>
                    <h2 class="mb-0">Hutang Perusahaan</h2>
                </div>
                <a href="<?= site_url('hutang/cetak') ?>" class="btn btn-m border-blue-dark color-blue-dark rounded-s font-600 mt-3 mt-sm-0 me-2 flex-shrink-0" data-report-preview-open="debt-print-modal">
                    <i class="fa fa-print me-1"></i> Cetak
                </a>
                <?php if ($canCreate): ?>
                    <button type="button" class="btn btn-m gradient-highlight rounded-s font-600 mt-3 mt-sm-0 flex-shrink-0" data-debt-create-open>
                        <i class="fa fa-plus me-1"></i> Tambah Hutang
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="debt-summary" class="row mb-0">
        <div class="col-12 col-md-4">
            <div class="card card-style bg-blue-dark mb-3">
                <div class="content py-3">
                    <div class="d-flex align-items-center"><span class="icon icon-s rounded-xl bg-white color-blue-dark me-3"><i class="fa fa-file-invoice-dollar"></i></span><div><p class="font-11 color-white opacity-70 mb-n1">Total Hutang</p><h3 class="color-white mb-0"><?= rupiah($summary['total_principal'] ?? 0) ?></h3></div></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card card-style bg-green-dark mb-3">
                <div class="content py-3">
                    <div class="d-flex align-items-center"><span class="icon icon-s rounded-xl bg-white color-green-dark me-3"><i class="fa fa-check"></i></span><div><p class="font-11 color-white opacity-70 mb-n1">Sudah Dibayar</p><h3 class="color-white mb-0"><?= rupiah($summary['total_paid'] ?? 0) ?></h3></div></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card card-style bg-red-dark mb-3">
                <div class="content py-3">
                    <div class="d-flex align-items-center"><span class="icon icon-s rounded-xl bg-white color-red-dark me-3"><i class="fa fa-hourglass-half"></i></span><div><p class="font-11 color-white opacity-70 mb-n1">Sisa Hutang</p><h3 class="color-white mb-0"><?= rupiah($summary['total_outstanding'] ?? 0) ?></h3></div></div>
                </div>
            </div>
        </div>
    </div>

    <div id="debt-list">
        <?php if (!$debts): ?>
            <div class="card card-style"><div class="content text-center py-5"><i class="fa fa-file-invoice-dollar fa-3x color-highlight opacity-30 mb-3"></i><h3>Belum Ada Hutang</h3><p class="mb-0">Hutang perusahaan yang dicatat akan tampil di sini.</p></div></div>
        <?php endif; ?>
        <?php foreach ($debts as $debt): ?>
            <?php
            $principalCents = simp_money_cents($debt['principal_amount']);
            $paidCents = simp_money_cents($debt['paid_amount'] ?? '0');
            $committedCents = simp_money_cents($debt['committed_amount'] ?? '0');
            if ($principalCents === NULL) $principalCents = 0;
            if ($paidCents === NULL) $paidCents = 0;
            if ($committedCents === NULL) $committedCents = $paidCents;
            $remainingCents = max(0, $principalCents - $paidCents);
            $pendingCents = max(0, $committedCents - $paidCents);
            $availableToPayCents = max(0, $principalCents - $committedCents);
            $principal = simp_money_from_cents($principalCents);
            $paid = simp_money_from_cents($paidCents);
            $committed = simp_money_from_cents($committedCents);
            $remaining = simp_money_from_cents($remainingCents);
            $pending = simp_money_from_cents($pendingCents);
            $availableToPay = simp_money_from_cents($availableToPayCents);
            // The aggregate payment state is authoritative.  The persisted
            // status can be stale after a failed/replayed transaction, so
            // badges and available actions must use the computed value.
            $displayStatus = isset($debt['computed_status']) && in_array($debt['computed_status'], array('open', 'paid', 'cancelled'), TRUE)
                ? $debt['computed_status'] : 'open';
            $statusText = $displayStatus === 'cancelled' ? 'Dibatalkan' : ($displayStatus === 'paid' ? 'Lunas' : ($pendingCents > 0 ? 'Menunggu Verifikasi' : ($paidCents > 0 ? 'Bayar Sebagian' : 'Belum Dibayar')));
            $statusClass = $displayStatus === 'cancelled' ? 'bg-gray-dark' : ($displayStatus === 'paid' ? 'bg-green-dark' : ($pendingCents > 0 ? 'bg-yellow-dark' : ($paidCents > 0 ? 'bg-blue-dark' : 'bg-red-dark')));
            $paymentPayload = array('id'=>(int)$debt['id'],'debt_no'=>$debt['debt_no'],'creditor'=>$debt['creditor'],'remaining'=>$availableToPay,'category_id'=>(int)($debt['category_id'] ?? 0));
            $editPayload = array('id'=>(int)$debt['id'],'creditor'=>$debt['creditor'],'description'=>$debt['description'],'debt_date'=>$debt['debt_date'],'principal_amount'=>$debt['principal_amount'],'event_id'=>$debt['event_id'],'category_id'=>$debt['category_id'],'note'=>$debt['note'],'lock_principal'=>(int)($debt['payment_count'] ?? 0) > 0 ? 1 : 0);
            $payments = isset($paymentsByDebt[(int)$debt['id']]) ? $paymentsByDebt[(int)$debt['id']] : array();
            ?>
            <div class="card card-style debt-card">
                <div class="content">
                    <div class="d-flex align-items-start flex-wrap">
                        <span class="icon icon-m rounded-xl bg-fade-red-light color-red-dark me-3 flex-shrink-0"><i class="fa fa-file-invoice-dollar"></i></span>
                        <div class="min-width-zero flex-grow-1">
                            <p class="font-11 color-highlight font-600 mb-n1"><?= e($debt['debt_no']) ?></p>
                            <h3 class="mb-1 text-break"><?= e($debt['creditor']) ?></h3>
                            <p class="font-12 opacity-70 mb-0 text-break"><?= e($debt['description']) ?></p>
                        </div>
                        <div class="d-flex align-items-center mt-2 mt-sm-0 ms-auto ps-sm-2 flex-shrink-0">
                            <?php if ($canManage && $displayStatus === 'open'): ?><button type="button" class="btn btn-xxs bg-theme color-theme border rounded-s me-2" data-debt-edit-open data-debt="<?= e(json_encode($editPayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>" aria-label="Ubah hutang"><i class="fa fa-edit"></i></button><?php endif; ?>
                            <span class="badge <?= $statusClass ?> color-white"><?= e($statusText) ?></span>
                        </div>
                    </div>

                    <div class="divider mt-3 mb-2"></div>
                    <div class="d-flex py-2 border-bottom"><span class="opacity-60">Tanggal hutang</span><strong class="ms-auto text-end"><?= tanggal_id($debt['debt_date']) ?></strong></div>
                    <?php if (!empty($debt['event_name'])): ?><div class="d-flex py-2 border-bottom"><span class="opacity-60">Event</span><strong class="ms-auto text-end text-break ps-3"><?= e($debt['event_name']) ?></strong></div><?php endif; ?>
                    <div class="d-flex py-2 border-bottom"><span class="opacity-60">Nilai pokok</span><strong class="ms-auto text-end"><?= rupiah($principal) ?></strong></div>
                    <div class="d-flex py-2 border-bottom"><span class="opacity-60">Terverifikasi</span><strong class="ms-auto text-end color-green-dark"><?= rupiah($paid) ?></strong></div>
                    <?php if ($pendingCents > 0): ?><div class="d-flex py-2 border-bottom"><span class="opacity-60">Menunggu verifikasi</span><strong class="ms-auto text-end color-yellow-dark"><?= rupiah($pending) ?></strong></div><?php endif; ?>
                    <div class="d-flex py-2"><span class="font-600">Sisa hutang</span><strong class="ms-auto text-end font-17 <?= $remainingCents > 0 ? 'color-red-dark' : 'color-green-dark' ?>"><?= rupiah($remaining) ?></strong></div>

                    <?php if ($payments): ?>
                        <div class="divider mt-3 mb-2"></div>
                        <p class="font-11 color-highlight font-600 mb-2">Riwayat pembayaran</p>
                        <?php foreach ($payments as $payment): ?>
                            <div class="d-flex align-items-start py-2 border-bottom">
                                <span class="icon icon-xs rounded-xl <?= $payment['status']==='verified' ? 'bg-green-light color-green-dark' : ($payment['status']==='pending' ? 'bg-yellow-light color-yellow-dark' : 'bg-red-light color-red-dark') ?> me-2 flex-shrink-0"><i class="fa fa-money-bill-wave"></i></span>
                                <div class="min-width-zero"><p class="font-12 mb-n1"><?= tanggal_id($payment['expense_date']) ?> · <?= e($payment['account_name'] ?: '-') ?></p><p class="font-10 opacity-60 mb-0"><?= e($payment['method']) ?> · <?= e($payment['expense_no']) ?></p><?php if (!empty($payment['proof_path'])): ?><a class="font-10 color-highlight" target="_blank" rel="noopener" href="<?= site_url('dokumen/hutang/'.(int)$payment['id']) ?>"><i class="fa fa-paperclip me-1"></i>Buka bukti</a><?php endif; ?></div>
                                <strong class="ms-auto text-end font-12"><?= rupiah($payment['amount']) ?><br><?= status_badge($payment['status']) ?></strong>
                            </div>
                            <?php if ($canVerify && $payment['status'] === 'pending'): ?>
                                <div class="row mb-2 mt-2">
                                    <div class="col-6 pe-1"><form method="post" action="<?= site_url('hutang/ajax/pembayaran/'.(int)$payment['id'].'/status') ?>" data-debt-payment-status-form><?= csrf_field() ?><input type="hidden" name="status" value="verified"><button class="btn btn-s btn-full gradient-green rounded-s font-12" type="submit"><i class="fa fa-check me-1"></i> Verifikasi</button></form></div>
                                    <div class="col-6 ps-1"><form method="post" action="<?= site_url('hutang/ajax/pembayaran/'.(int)$payment['id'].'/status') ?>" data-debt-payment-status-form data-confirm="Tolak pembayaran hutang ini? Pembayaran yang ditolak bersifat final." data-confirm-button="Ya, Tolak" data-confirm-tone="danger"><?= csrf_field() ?><input type="hidden" name="status" value="rejected"><button class="btn btn-s btn-full border-red-dark color-red-dark rounded-s font-12" type="submit"><i class="fa fa-times me-1"></i> Tolak</button></form></div>
                                </div>
                            <?php elseif ($canVerify && $payment['status'] === 'verified'): ?>
                                <form class="d-flex mt-2 mb-2" method="post" action="<?= site_url('hutang/ajax/pembayaran/'.(int)$payment['id'].'/status') ?>" data-debt-payment-status-form data-confirm="Batalkan verifikasi pembayaran hutang ini? Jurnal kas akan dibalik." data-confirm-button="Ya, Batalkan" data-confirm-tone="danger">
                                    <?= csrf_field() ?><input type="hidden" name="status" value="rejected"><button class="btn btn-s btn-full border-red-dark color-red-dark rounded-s font-12" type="submit"><i class="fa fa-undo me-1"></i> Batalkan Verifikasi</button>
                                </form>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="row mb-0 mt-3">
                        <?php if ($canPay && $availableToPayCents > 0 && $displayStatus === 'open'): ?>
                            <div class="<?= $canManage && $committedCents <= 0 ? 'col-6 pe-1' : 'col-12' ?>"><button type="button" class="btn btn-full btn-m gradient-highlight rounded-s font-600" data-debt-pay-open data-debt="<?= e(json_encode($paymentPayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>"><i class="fa fa-money-bill-wave me-1"></i> Bayar Hutang</button></div>
                        <?php endif; ?>
                        <?php if ($canManage && $committedCents <= 0 && $displayStatus === 'open'): ?>
                            <div class="<?= ($canPay && $availableToPayCents > 0) ? 'col-6 ps-1' : 'col-12' ?>"><form method="post" action="<?= site_url('hutang/ajax/'.(int)$debt['id'].'/batal') ?>" data-debt-cancel-form data-confirm="Batalkan hutang ini? Hutang tanpa pembayaran akan ditandai sebagai dibatalkan." data-confirm-button="Ya, Batalkan" data-confirm-tone="danger"><?= csrf_field() ?><button type="submit" class="btn btn-full btn-m border-red-dark color-red-dark rounded-s font-600"><i class="fa fa-ban me-1"></i> Batalkan</button></form></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php $this->load->view('reports/print_modal', array(
    'printModalId' => 'debt-print-modal',
    'printModalTitle' => 'Pratinjau Hutang Perusahaan',
    'printPreviewUrl' => site_url('hutang/cetak'),
    'printPdfUrl' => site_url('hutang/pdf'),
    'printExcelUrl' => site_url('hutang/excel')
)); ?>

<?php if ($canCreate || $canManage): ?>
<a id="debt-create-opener" href="#" class="d-none" data-menu="debt-create-modal" aria-hidden="true" tabindex="-1"></a>
<div id="debt-create-modal" class="menu menu-box-modal rounded-m" data-menu-width="390" data-menu-height="700" role="dialog" aria-modal="true" aria-labelledby="debt-create-title">
    <div class="content mb-0">
        <div class="d-flex align-items-start mb-3"><div><p class="font-600 color-highlight mb-n1">Kewajiban perusahaan</p><h3 id="debt-create-title" class="font-20 mb-0">Tambah Hutang</h3></div><button type="button" class="close-menu btn btn-xxs bg-theme color-theme border rounded-s ms-auto" aria-label="Tutup"><i class="fa fa-times"></i></button></div>
        <form id="debt-create-form" method="post" action="<?= site_url('hutang/ajax/tambah') ?>" data-create-action="<?= e(site_url('hutang/ajax/tambah')) ?>" data-update-prefix="<?= e(site_url('hutang/ajax')) ?>" data-events="<?= e(json_encode($eventPayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>" data-categories="<?= e(json_encode($categoryPayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>">
            <?= csrf_field() ?>
            <div class="input-style input-style-always-active has-borders no-icon mb-3"><input class="form-control" id="debt-creditor" name="creditor" maxlength="160" required placeholder="Nama pemasok / pihak pemberi hutang"><label for="debt-creditor" class="color-highlight">Kreditur</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>(wajib)</em></div>
            <div class="input-style input-style-always-active has-borders no-icon mb-3"><input class="form-control" type="date" id="debt-date" name="debt_date" value="<?= date('Y-m-d') ?>" required><label for="debt-date" class="color-highlight">Tanggal Hutang</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>(wajib)</em></div>
            <div class="input-style input-style-always-active has-borders no-icon mb-3"><label for="debt-category" class="color-highlight">Kategori</label><select id="debt-category" name="category_id"><option value="">Pembayaran Hutang (otomatis)</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>(opsional)</em></div>
            <div class="input-style input-style-always-active has-borders no-icon mb-3"><label for="debt-event" class="color-highlight">Event terkait</label><select id="debt-event" name="event_id"><option value="">Hutang umum / tidak terkait event</option><?php foreach ($events as $event): ?><option value="<?= (int)$event['id'] ?>"><?= e($event['name']) ?> · <?= e($event['code']) ?> (<?= e($event['status']) ?>)</option><?php endforeach; ?></select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>(opsional)</em></div>
            <div class="input-style input-style-always-active has-borders no-icon mb-1"><input class="form-control" type="number" min="0.01" max="9999999999999999.99" step="0.01" id="debt-amount" name="principal_amount" required placeholder="0" aria-describedby="debt-amount-help"><label for="debt-amount" class="color-highlight">Nilai Pokok Hutang</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>(wajib)</em></div>
            <p id="debt-amount-help" class="font-10 color-yellow-dark mb-3 d-none"><i class="fa fa-lock me-1"></i>Nominal tidak dapat diubah karena hutang sudah memiliki riwayat pembayaran.</p>
            <div class="input-style input-style-always-active has-borders no-icon mb-3"><textarea class="form-control" id="debt-description" name="description" rows="3" maxlength="3000" required placeholder="Contoh: Sewa kendaraan untuk pelatihan"></textarea><label for="debt-description" class="color-highlight">Uraian</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em class="mt-n3">(wajib)</em></div>
            <div class="input-style input-style-always-active has-borders no-icon mb-3"><textarea class="form-control" id="debt-note" name="note" rows="2" maxlength="2000" placeholder="Catatan tambahan (opsional)"></textarea><label for="debt-note" class="color-highlight">Catatan</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em class="mt-n3">(opsional)</em></div>
            <div class="row mb-0"><div class="col-5 pe-1"><button type="button" class="close-menu btn btn-full btn-m bg-theme color-theme border rounded-s font-600">Batal</button></div><div class="col-7 ps-1"><button id="debt-create-submit" type="submit" class="btn btn-full btn-m gradient-highlight rounded-s font-600" data-debt-create-submit><i class="fa fa-save me-1"></i> Simpan Hutang</button></div></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canPay): ?>
<?php $accountJson = json_encode($accountPayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>
<a id="debt-pay-opener" href="#" class="d-none" data-menu="debt-pay-modal" aria-hidden="true" tabindex="-1"></a>
<div id="debt-pay-modal" class="menu menu-box-modal rounded-m" data-menu-width="390" data-menu-height="700" role="dialog" aria-modal="true" aria-labelledby="debt-pay-title">
    <div class="content mb-0">
        <div class="d-flex align-items-start mb-3"><div><p class="font-600 color-highlight mb-n1">Transaksi keluar</p><h3 id="debt-pay-title" class="font-20 mb-0">Bayar Hutang</h3></div><button type="button" class="close-menu btn btn-xxs bg-theme color-theme border rounded-s ms-auto" aria-label="Tutup"><i class="fa fa-times"></i></button></div>
        <div id="debt-pay-context" class="card bg-fade-red-light border-0 rounded-s shadow-0 mb-3"><div class="content py-3"><p class="font-11 color-red-dark font-600 mb-n1">Untuk</p><h4 id="debt-pay-creditor" class="mb-1">-</h4><p id="debt-pay-remaining" class="mb-0">Sisa hutang: <strong>Rp 0</strong></p></div></div>
        <form id="debt-pay-form" method="post" enctype="multipart/form-data" action="" data-action-prefix="<?= e(site_url('hutang/ajax')) ?>" data-accounts="<?= e($accountJson) ?>" data-can-verify="<?= $canVerify ? '1' : '0' ?>">
            <?= csrf_field() ?><input type="hidden" name="debt_id" id="debt-pay-id" value="">
            <div class="input-style input-style-always-active has-borders no-icon mb-3"><input class="form-control" type="date" id="debt-pay-date" name="expense_date" value="<?= date('Y-m-d') ?>" required><label for="debt-pay-date" class="color-highlight">Tanggal Bayar</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>(wajib)</em></div>
            <input type="hidden" id="debt-pay-method" name="method" value="cash">
            <div class="input-style input-style-always-active has-borders no-icon mb-1"><label for="debt-pay-account" class="color-highlight">Akun Pembayaran</label><select id="debt-pay-account" name="account_id" required><option value="">Pilih Kas Tunai</option></select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>(wajib)</em></div>
            <p class="font-10 opacity-60 mb-3 ps-2">Pembayaran hutang dicatat melalui Kas Tunai.</p>
            <input type="hidden" name="admin_fee" value="0">
            <div class="input-style input-style-always-active has-borders no-icon mb-3"><input class="form-control" type="number" min="0.01" max="9999999999999999.99" step="0.01" id="debt-pay-amount" name="amount" required><label for="debt-pay-amount" class="color-highlight">Nominal Pembayaran</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>(wajib)</em></div>
            <div class="input-style input-style-always-active has-borders no-icon mb-3"><label for="debt-pay-status" class="color-highlight">Status</label><select id="debt-pay-status" name="status"><?php if ($canVerify): ?><option value="verified" selected>Terverifikasi (langsung mengurangi saldo)</option><?php endif; ?><option value="pending" <?= $canVerify ? '' : 'selected' ?>>Menunggu verifikasi</option></select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em></em></div>
            <div id="debt-pay-proof-wrap" class="input-style input-style-always-active has-borders no-icon mb-3"><input class="form-control" type="file" id="debt-pay-proof" name="proof" accept="image/jpeg,image/png,application/pdf" style="padding-top:13px"><label for="debt-pay-proof" class="color-highlight">Bukti Pembayaran</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>(opsional untuk tunai)</em></div>
            <div class="input-style input-style-always-active has-borders no-icon mb-3"><textarea class="form-control" id="debt-pay-note" name="note" rows="2" maxlength="2000" placeholder="Catatan pembayaran (opsional)"></textarea><label for="debt-pay-note" class="color-highlight">Catatan</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em class="mt-n3">(opsional)</em></div>
            <div class="row mb-0"><div class="col-5 pe-1"><button type="button" class="close-menu btn btn-full btn-m bg-theme color-theme border rounded-s font-600">Batal</button></div><div class="col-7 ps-1"><button id="debt-pay-submit" type="submit" class="btn btn-full btn-m gradient-highlight rounded-s font-600" data-debt-pay-submit><i class="fa fa-save me-1"></i> Simpan Pembayaran</button></div></div>
        </form>
    </div>
</div>
<?php endif; ?>
