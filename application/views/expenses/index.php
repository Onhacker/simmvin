<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$activeEvents = isset($activeEvents) && is_array($activeEvents) ? $activeEvents : array();
$rows = isset($rows) && is_array($rows) ? $rows : array();
$verifiedTotalCents = 0;

foreach ($rows as $row) {
    if (isset($row['status']) && $row['status'] === 'verified') {
        $rowAmountCents = simp_money_cents($row['amount']);
        $rowFeeCents = simp_money_cents($row['admin_fee']);
        $verifiedTotalCents += ($rowAmountCents === NULL ? 0 : $rowAmountCents) + ($rowFeeCents === NULL ? 0 : $rowFeeCents);
    }
}
$verifiedTotal = simp_money_from_cents($verifiedTotalCents);

$methodLabels = array(
    'cash' => 'Tunai',
    'transfer' => 'Transfer',
    'qris' => 'QRIS'
);
$categories = isset($categories) && is_array($categories) ? $categories : array();
$accounts = isset($accounts) && is_array($accounts) ? $accounts : array();
$canVerify = !empty($canVerify);
?>

<?php if (!$activeEvents): ?>
    <div class="card card-style">
        <div class="content text-center py-4">
            <i class="fa fa-calendar-times fa-3x color-highlight opacity-30 mb-3"></i>
            <h3>Belum Ada Event Aktif</h3>
            <p class="mb-0">Aktifkan event terlebih dahulu untuk mencatat dan melihat pengeluaran event tersebut.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card card-style">
        <div class="content mb-3">
            <div class="d-flex align-items-start">
                <div class="min-width-zero flex-grow-1 pe-3">
                    <p class="font-600 color-highlight mb-n1"><?= count($activeEvents) > 1 ? 'Event Aktif' : 'Event Aktif Saat Ini' ?></p>
                    <?php if (count($activeEvents) === 1): ?>
                        <?php $activeEvent = $activeEvents[0]; ?>
                        <h4 class="mb-1"><?= e($activeEvent['name']) ?></h4>
                        <p class="font-11 opacity-60 mb-0">
                            <?= e($activeEvent['code']) ?>
                            <?php if (!empty($activeEvent['start_date']) && !empty($activeEvent['end_date'])): ?>
                                &middot; <?= tanggal_id($activeEvent['start_date']) ?> s.d. <?= tanggal_id($activeEvent['end_date']) ?>
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <h4 class="mb-1"><?= number_format(count($activeEvents)) ?> Event Sedang Aktif</h4>
                        <p class="font-11 opacity-60 mb-0">Daftar pengeluaran otomatis menampilkan transaksi dari seluruh event aktif.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row mt-3 mb-0">
                <div class="<?= $this->Auth_model->can('expenses.create') ? 'col-6 pe-1' : 'col-12' ?>">
                    <a class="btn btn-full btn-m font-600 bg-theme color-theme border rounded-s" href="<?= site_url('pengeluaran/cetak') ?>" data-report-preview-open="expense-print-modal"><i class="fa fa-print me-1 color-highlight"></i> Cetak</a>
                </div>
                <?php if ($this->Auth_model->can('expenses.create')): ?>
                    <div class="col-6 ps-1">
                        <button type="button" class="btn btn-full btn-m font-600 gradient-highlight rounded-s" data-expense-add-open><i class="fas fa-plus me-1"></i> Tambah</button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (count($activeEvents) > 1): ?>
                <div class="divider mt-3 mb-3"></div>
                <?php foreach ($activeEvents as $eventIndex => $activeEvent): ?>
                    <div class="d-flex align-items-start <?= $eventIndex + 1 < count($activeEvents) ? 'mb-3' : '' ?>">
                        <span class="icon icon-s rounded-xl bg-fade-blue-light color-blue-dark me-3 flex-shrink-0">
                            <i class="fa fa-calendar-check"></i>
                        </span>
                        <div class="min-width-zero">
                            <h5 class="font-14 mb-n1"><?= e($activeEvent['name']) ?></h5>
                            <p class="font-11 opacity-60 mb-0">
                                <?= e($activeEvent['code']) ?>
                                <?php if (!empty($activeEvent['start_date']) && !empty($activeEvent['end_date'])): ?>
                                    &middot; <?= tanggal_id($activeEvent['start_date']) ?> s.d. <?= tanggal_id($activeEvent['end_date']) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="expense-summary" class="card card-style">
        <div class="content mb-3">
            <div class="d-flex align-items-center">
                <div class="min-width-zero pe-3">
                    <p class="font-600 color-highlight mb-n1">Dana Keluar Event Aktif</p>
                    <h2 class="mb-0">Daftar Pengeluaran</h2>
                </div>
                <span class="badge bg-blue-dark color-white ms-auto flex-shrink-0"><?= number_format(count($rows)) ?> data</span>
            </div>
            <div class="divider mt-3 mb-3"></div>
            <div class="expense-summary-total">
                <p class="font-11 opacity-60 mb-n1">Total pengeluaran terverifikasi</p>
                <h3 class="color-green-dark mb-0"><?= rupiah($verifiedTotal) ?></h3>
            </div>
        </div>
    </div>

    <div id="expense-list">
    <?php if (!$rows): ?>
        <div class="card card-style">
            <div class="content text-center py-4">
                <h3>Belum Ada Pengeluaran</h3>
                <p class="mb-0">Pengeluaran untuk event aktif akan tampil di halaman ini.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($rows as $row): ?>
        <?php
        $amountCents = simp_money_cents($row['amount']);
        $adminFeeCents = simp_money_cents($row['admin_fee']);
        if ($amountCents === NULL) $amountCents = 0;
        if ($adminFeeCents === NULL) $adminFeeCents = 0;
        $amount = simp_money_from_cents($amountCents);
        $adminFee = simp_money_from_cents($adminFeeCents);
        $total = simp_money_from_cents($amountCents + $adminFeeCents);
        $method = isset($methodLabels[$row['method']]) ? $methodLabels[$row['method']] : ucfirst((string) $row['method']);
        ?>
        <div class="card card-style expense-card">
            <div class="content">
                <div class="expense-card-details" aria-label="Rincian pengeluaran">
                    <div class="expense-card-detail-row">
                        <span class="expense-card-label">Nomor</span><span class="expense-card-separator" aria-hidden="true">|</span>
                        <strong class="expense-card-value color-highlight"><?= e($row['expense_no']) ?></strong>
                    </div>
                    <div class="expense-card-detail-row">
                        <span class="expense-card-label">Tanggal</span><span class="expense-card-separator" aria-hidden="true">|</span>
                        <span class="expense-card-value"><?= tanggal_id($row['expense_date']) ?></span>
                    </div>
                    <div class="expense-card-detail-row">
                        <span class="expense-card-label">Status</span><span class="expense-card-separator" aria-hidden="true">|</span>
                        <span class="expense-card-value"><?= status_badge($row['status']) ?></span>
                    </div>
                    <div class="expense-card-detail-row">
                        <span class="expense-card-label">Kategori</span><span class="expense-card-separator" aria-hidden="true">|</span>
                        <strong class="expense-card-value"><?= e($row['category_name']) ?></strong>
                    </div>
                    <div class="expense-card-detail-row">
                        <span class="expense-card-label">Uraian</span><span class="expense-card-separator" aria-hidden="true">|</span>
                        <span class="expense-card-value"><?= nl2br(e($row['description'])) ?></span>
                    </div>
                    <div class="expense-card-detail-row">
                        <span class="expense-card-label">Event</span><span class="expense-card-separator" aria-hidden="true">|</span>
                        <strong class="expense-card-value"><?= e($row['event_name'] ?: 'Pengeluaran umum') ?></strong>
                    </div>

                    <?php if (!empty($row['debt_id'])): ?>
                        <div class="expense-card-detail-row">
                            <span class="expense-card-label">Sumber transaksi</span><span class="expense-card-separator" aria-hidden="true">|</span>
                            <strong class="expense-card-value color-red-dark">Pembayaran <?= e($row['debt_no']) ?> · <?= e($row['debt_creditor']) ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="expense-card-detail-row">
                        <span class="expense-card-label">Akun Dana</span><span class="expense-card-separator" aria-hidden="true">|</span>
                        <span class="expense-card-value"><strong><?= e($row['account_name']) ?></strong><small class="expense-card-subvalue color-highlight"><?= e($method) ?></small></span>
                    </div>
                    <div class="expense-card-detail-row expense-card-money-row">
                        <span class="expense-card-label">Nilai pengeluaran</span><span class="expense-card-separator" aria-hidden="true">|</span>
                        <strong class="expense-card-value"><?= rupiah($amount) ?></strong>
                    </div>
                    <div class="expense-card-detail-row expense-card-money-row">
                        <span class="expense-card-label">Biaya admin</span><span class="expense-card-separator" aria-hidden="true">|</span>
                        <strong class="expense-card-value"><?= rupiah($adminFee) ?></strong>
                    </div>
                    <div class="expense-card-detail-row expense-card-total-row">
                        <span class="expense-card-label">Total Dana Keluar</span><span class="expense-card-separator" aria-hidden="true">|</span>
                        <strong class="expense-card-value color-red-dark"><?= rupiah($total) ?></strong>
                    </div>

                    <?php if (!empty($row['note'])): ?>
                        <div class="expense-card-detail-row expense-card-note-row">
                            <span class="expense-card-label">Catatan</span><span class="expense-card-separator" aria-hidden="true">|</span>
                            <span class="expense-card-value"><?= nl2br(e($row['note'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="divider mt-2 mb-2"></div>

                <div class="expense-card-proof-row">
                    <span class="expense-card-label">Bukti Pembayaran</span><span class="expense-card-separator" aria-hidden="true">|</span>
                    <span class="expense-card-value">
                        <?php if ($row['proof_path']): ?>
                            <a class="btn btn-s font-12 font-600 bg-fade-blue-light color-blue-dark rounded-s"
                               target="_blank" rel="noopener"
                               href="<?= site_url('dokumen/pengeluaran/' . $row['id']) ?>">
                                Buka Bukti
                            </a>
                        <?php else: ?>
                            <span class="opacity-50">Belum ada bukti pembayaran.</span>
                        <?php endif; ?>
                    </span>
                </div>

                <?php if ($row['status'] !== 'rejected' && $this->Auth_model->can('expenses.verify') && (empty($row['debt_id']) || $this->Auth_model->can('debts.verify'))): ?>
                    <div class="divider mt-2 mb-2"></div>
                    <form method="post" action="<?= site_url('pengeluaran/' . $row['id'] . '/status') ?>" data-expense-status-form>
                        <?= csrf_field() ?>
                        <p class="font-11 color-highlight font-600 mb-1">Verifikasi Pengeluaran</p>
                        <div class="d-flex flex-column flex-md-row align-items-stretch">
                            <div class="input-style input-style-always-active has-borders no-icon mb-3 mb-md-0 flex-grow-1">
                                <label for="expense-status-<?= (int) $row['id'] ?>" class="color-highlight font-12 font-500">Status</label>
                                <select id="expense-status-<?= (int) $row['id'] ?>" name="status" aria-label="Status pengeluaran <?= e($row['expense_no']) ?>">
                                    <?php if ($row['status'] === 'pending'): ?><option value="pending" selected>Menunggu</option><?php endif; ?>
                                    <option value="verified" <?= $row['status'] === 'verified' ? 'selected' : '' ?>>Terverifikasi</option>
                                    <option value="rejected" <?= $row['status'] === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
                                </select>
                                <span><i class="fa fa-chevron-down"></i></span>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <em></em>
                            </div>
                            <button class="btn btn-m font-13 font-600 gradient-highlight rounded-s ms-md-3" type="submit" data-expense-status-submit>
                                <i class="fas fa-check me-1"></i> Simpan Status
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($activeEvents): ?>
    <?php $this->load->view('reports/print_modal', array(
        'printModalId' => 'expense-print-modal',
        'printModalTitle' => 'Pratinjau Pengeluaran',
        'printPreviewUrl' => site_url('pengeluaran/cetak'),
        'printPdfUrl' => site_url('pengeluaran/pdf'),
        'printExcelUrl' => site_url('pengeluaran/excel')
    )); ?>
<?php endif; ?>

<?php if ($this->Auth_model->can('expenses.create') && $activeEvents): ?>
    <?php
    $expenseEventPayload = array();
    foreach ($activeEvents as $event) $expenseEventPayload[] = array('id'=>(int)$event['id'],'name'=>$event['name'],'code'=>$event['code']);
    $expenseAccountPayload = array();
    foreach ($accounts as $account) $expenseAccountPayload[] = array('id'=>(int)$account['id'],'name'=>$account['name'],'type'=>$account['type']);
    ?>
    <a id="expense-add-opener" href="#" class="d-none" data-menu="expense-add-modal" aria-hidden="true" tabindex="-1"></a>
    <div id="expense-add-modal" class="menu menu-box-modal rounded-m" data-menu-width="390" data-menu-height="680" role="dialog" aria-modal="true" aria-labelledby="expense-add-title">
        <div class="content mb-0">
            <div class="d-flex align-items-start mb-3"><div><p class="font-600 color-highlight mb-n1">Dana keluar</p><h3 id="expense-add-title" class="font-20 mb-0">Tambah Pengeluaran</h3></div><button type="button" class="close-menu btn btn-xxs bg-theme color-theme border rounded-s ms-auto" aria-label="Tutup"><i class="fa fa-times"></i></button></div>
            <form id="expense-add-form" method="post" enctype="multipart/form-data" action="<?= site_url('pengeluaran/ajax/tambah') ?>" data-events="<?= e(json_encode($expenseEventPayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>" data-accounts="<?= e(json_encode($expenseAccountPayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>">
                <?= csrf_field() ?>
                <?php if (count($activeEvents) === 1): $expenseOnlyEvent = $activeEvents[0]; ?><input type="hidden" name="event_id" value="<?= (int)$expenseOnlyEvent['id'] ?>"><div class="d-flex align-items-center rounded-s bg-blue-light px-3 py-2 mb-3"><span class="icon icon-s rounded-xl bg-blue-dark color-white me-3"><i class="fa fa-calendar-check"></i></span><div class="min-width-zero"><p class="font-10 color-blue-dark font-600 mb-n1">Event aktif</p><p class="font-12 font-600 mb-0 text-break"><?= e($expenseOnlyEvent['name']) ?></p></div></div><?php else: ?><div class="input-style has-borders no-icon input-style-always-active mb-3"><label for="expense-modal-event" class="color-highlight">Event aktif</label><select id="expense-modal-event" name="event_id" required><option value="">Pilih event aktif</option><?php foreach ($activeEvents as $event): ?><option value="<?= (int)$event['id'] ?>"><?= e($event['name']) ?></option><?php endforeach; ?></select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>*</em></div><?php endif; ?>
                <div class="row mb-0"><div class="col-12"><div class="input-style has-borders no-icon input-style-always-active mb-3"><label for="expense-modal-category" class="color-highlight">Kategori</label><select id="expense-modal-category" name="category_id" required><option value="">Pilih kategori</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>*</em></div></div><div class="col-12"><div class="input-style has-borders no-icon input-style-always-active mb-3"><input class="form-control" type="date" id="expense-modal-date" name="expense_date" value="<?= date('Y-m-d') ?>" required><label for="expense-modal-date" class="color-highlight">Tanggal</label><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>*</em></div></div></div>
                <div class="input-style has-borders no-icon input-style-always-active mb-3"><textarea id="expense-modal-description" name="description" rows="3" maxlength="3000" placeholder="Tujuan pengeluaran"></textarea><label for="expense-modal-description" class="color-highlight">Tujuan Pengeluaran</label><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em class="mt-n3"></em></div>
                <div class="row mb-0"><div class="col-12"><div class="input-style has-borders no-icon input-style-always-active mb-3"><input class="form-control" type="number" data-money inputmode="decimal" min="0.01" max="9999999999999999.99" step="0.01" id="expense-modal-amount" name="amount" required placeholder="Rp 0"><label for="expense-modal-amount" class="color-highlight">Jumlah</label><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>*</em></div></div><div class="col-12"><div class="input-style has-borders no-icon input-style-always-active mb-3"><input class="form-control" type="number" data-money min="0" max="9999999999999999.99" step="0.01" id="expense-modal-fee" name="admin_fee" value="0" placeholder="Rp 0"><label for="expense-modal-fee" class="color-highlight">Biaya admin</label><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>(khusus Transfer)</em></div></div></div>
                <div class="row mb-0"><div class="col-12"><div class="input-style has-borders no-icon input-style-always-active mb-3"><label for="expense-modal-method" class="color-highlight">Metode</label><select id="expense-modal-method" name="method" required><option value="cash">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option></select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>*</em></div></div><div class="col-12"><div class="input-style has-borders no-icon input-style-always-active mb-3"><label for="expense-modal-account" class="color-highlight">Keluar dari akun</label><select id="expense-modal-account" name="account_id" required><option value="">Pilih akun</option></select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>*</em></div></div></div>
                <div class="input-style has-borders no-icon input-style-always-active mb-3"><label for="expense-modal-status" class="color-highlight">Status</label><select id="expense-modal-status" name="status"><?php if ($canVerify): ?><option value="verified" selected>Terverifikasi (langsung mengurangi saldo)</option><?php endif; ?><option value="pending" <?= $canVerify ? '' : 'selected' ?>>Menunggu verifikasi</option></select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em></em></div>
                <div class="input-style has-borders no-icon input-style-always-active mb-3"><input class="form-control" type="file" id="expense-modal-proof" name="proof" accept="image/jpeg,image/png,application/pdf" style="padding-top:13px"><label for="expense-modal-proof" class="color-highlight">Bukti pembayaran</label><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em id="expense-modal-proof-label"></em></div>
                <div class="input-style has-borders no-icon input-style-always-active mb-3"><textarea id="expense-modal-note" name="note" rows="2" maxlength="2000" placeholder="Catatan"></textarea><label for="expense-modal-note" class="color-highlight">Catatan</label><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em class="mt-n3"></em></div>
                <button type="submit" class="btn btn-full btn-m gradient-highlight rounded-s font-600" data-expense-add-submit><i class="fa fa-save me-1"></i> Simpan Pengeluaran</button>
            </form>
        </div>
    </div>
<?php endif; ?>
