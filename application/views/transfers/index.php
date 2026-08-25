<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$rows=isset($rows)&&is_array($rows)?$rows:array();
$filters=isset($filters)&&is_array($filters)?$filters:array('date_from'=>'','date_to'=>'','status'=>'');
$accounts=isset($accounts)&&is_array($accounts)?$accounts:array();
$canCreate=!empty($canCreate);
$canVerify=!empty($canVerify);
?>

<div id="transfer-index-content">
    <div class="card card-style">
        <div class="content mb-0">
            <div class="d-flex align-items-start mb-3">
                <div class="min-width-zero pe-3">
                    <p class="font-600 color-highlight mb-n1">Perpindahan antar akun</p>
                    <h2 class="mb-0">Transfer Dana</h2>
                    <p class="font-12 opacity-60 mb-0">Pindahkan saldo perusahaan, kas, atau rekening pribadi secara tercatat.</p>
                </div>
                <?php if($canCreate): ?>
                    <button type="button" class="btn btn-s font-13 font-600 gradient-highlight rounded-s ms-auto flex-shrink-0" data-transfer-add-open><i class="fas fa-plus me-1"></i> Transfer</button>
                <?php endif; ?>
            </div>

            <form method="get"><div class="row mb-0">
                <div class="col-lg-3 col-md-6"><div class="input-style input-style-always-active has-borders no-icon mb-4"><input class="form-control" id="transfer-filter-from" type="date" name="date_from" value="<?= e(isset($filters['date_from'])?$filters['date_from']:'') ?>"><label for="transfer-filter-from" class="color-highlight">Dari Tanggal</label><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em></em></div></div>
                <div class="col-lg-3 col-md-6"><div class="input-style input-style-always-active has-borders no-icon mb-4"><input class="form-control" id="transfer-filter-to" type="date" name="date_to" value="<?= e(isset($filters['date_to'])?$filters['date_to']:'') ?>"><label for="transfer-filter-to" class="color-highlight">Sampai Tanggal</label><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em></em></div></div>
                <div class="col-lg-3 col-md-6"><div class="input-style input-style-always-active has-borders no-icon mb-4"><label for="transfer-filter-status" class="color-highlight">Status</label><select id="transfer-filter-status" name="status"><option value="">Semua status</option><?php foreach(array('pending'=>'Menunggu','verified'=>'Terverifikasi','rejected'=>'Ditolak') as $key=>$label): ?><option value="<?= $key ?>" <?= (isset($filters['status'])&&$filters['status']===$key)?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em></em></div></div>
                <div class="col-lg-3 col-md-6"><button class="btn btn-full btn-m font-13 font-600 gradient-highlight rounded-s mb-4" type="submit"><i class="fas fa-filter me-1"></i> Terapkan</button></div>
            </div></form>
        </div>
    </div>

    <div class="content mb-0">
        <div class="d-flex align-items-center mb-3"><div><p class="font-600 color-highlight mb-n1">Mutasi internal</p><h3 class="font-20 mb-0">Daftar Transfer</h3></div><span class="badge bg-blue-dark color-white ms-auto"><?= number_format(count($rows)) ?> data</span></div>

        <?php if(!$rows): ?><div class="card card-style mx-0"><div class="content text-center py-5"><span class="icon icon-l rounded-xl bg-blue-light color-blue-dark mb-3"><i class="fas fa-exchange-alt"></i></span><h4>Belum Ada Transfer</h4><p class="font-12 opacity-60 mb-0">Transfer antar akun akan tampil di sini.</p></div></div><?php endif; ?>

        <?php foreach($rows as $row): ?>
            <div class="card card-style mx-0 mb-3"><div class="content mb-3">
                <div class="d-flex align-items-start"><span class="icon icon-s rounded-xl bg-blue-light color-blue-dark me-3 flex-shrink-0"><i class="fas fa-exchange-alt"></i></span><div class="min-width-zero"><p class="font-10 color-highlight font-600 mb-n1"><?= e($row['transfer_no']) ?></p><h4 class="font-17 mb-0 simp-balance-value"><?= rupiah($row['amount']) ?></h4><p class="font-11 opacity-60 mb-0"><?= tanggal_id($row['transfer_date']) ?></p></div><div class="ms-auto flex-shrink-0"><?= status_badge($row['status']) ?></div></div>
                <div class="divider mt-3 mb-2"></div>
                <div class="d-flex align-items-center py-2 border-bottom"><i class="fas fa-wallet color-red-dark icon-30"></i><span class="opacity-60">Dari</span><strong class="ms-auto text-end text-break"><?= e($row['from_account_name']) ?></strong></div>
                <div class="d-flex align-items-center py-2 border-bottom"><i class="fas fa-wallet color-green-dark icon-30"></i><span class="opacity-60">Ke</span><strong class="ms-auto text-end text-break"><?= e($row['to_account_name']) ?></strong></div>
                <div class="d-flex py-2 border-bottom"><span class="opacity-60">Biaya Admin</span><strong class="ms-auto simp-balance-value"><?= rupiah($row['admin_fee']) ?></strong></div>
                <div class="d-flex align-items-center py-2"><span class="opacity-60">Bukti</span><div class="ms-auto"><?php if($row['proof_path']): ?><a class="btn btn-xxs border-blue-dark color-blue-dark rounded-s font-600" target="_blank" rel="noopener" href="<?= site_url('dokumen/transfer/'.$row['id']) ?>"><i class="fas fa-paperclip me-1"></i>Lihat Bukti</a><?php else: ?><span class="opacity-50">Belum ada</span><?php endif; ?></div></div>
                <?php if($canVerify && $row['status']!=='rejected'): ?>
                    <div class="divider mt-2 mb-3"></div>
                    <form method="post" action="<?= site_url('transfer-dana/'.$row['id'].'/status') ?>" data-transfer-status-form>
                        <?= csrf_field() ?>
                        <div class="d-flex flex-column flex-md-row align-items-stretch">
                            <div class="input-style input-style-always-active has-borders no-icon mb-3 mb-md-0 flex-grow-1"><label for="transfer-status-<?= (int)$row['id'] ?>" class="color-highlight">Status</label><select id="transfer-status-<?= (int)$row['id'] ?>" name="status"><?php if($row['status']==='pending'): ?><option value="pending" selected>Menunggu</option><?php endif; ?><option value="verified" <?= $row['status']==='verified'?'selected':'' ?>>Terverifikasi</option><option value="rejected">Ditolak</option></select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em></em></div>
                            <button class="btn btn-m gradient-highlight rounded-s font-600 ms-md-3" type="submit"><i class="fa fa-save me-1"></i>Simpan Status</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div></div>
        <?php endforeach; ?>
    </div>
</div>

<?php if($canCreate): ?>
    <a id="transfer-add-opener" href="#" class="d-none" data-menu="transfer-add-modal" aria-hidden="true" tabindex="-1"></a>
    <div id="transfer-add-modal" class="menu menu-box-modal rounded-m" data-menu-width="390" data-menu-height="650" role="dialog" aria-modal="true" aria-labelledby="transfer-add-title">
        <div class="content mb-0">
            <div class="d-flex align-items-start mb-3"><div><p class="font-600 color-highlight mb-n1">Mutasi internal</p><h3 id="transfer-add-title" class="font-20 mb-0">Transfer Dana</h3></div><button type="button" class="close-menu btn btn-xxs bg-theme color-theme border rounded-s ms-auto" aria-label="Tutup"><i class="fa fa-times"></i></button></div>
            <form id="transfer-add-form" method="post" enctype="multipart/form-data" action="<?= site_url('transfer-dana/ajax/tambah') ?>">
                <?= csrf_field() ?>
                <div class="input-style input-style-always-active has-borders no-icon mb-3"><input class="form-control" id="transfer-modal-date" type="date" name="transfer_date" value="<?= date('Y-m-d') ?>" required><label for="transfer-modal-date" class="color-highlight">Tanggal</label><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>(wajib)</em></div>
                <div class="row mb-0">
                    <div class="col-12"><div class="input-style input-style-always-active has-borders no-icon mb-3"><label for="transfer-modal-from" class="color-highlight">Dari akun</label><select id="transfer-modal-from" name="from_account_id" required><option value="">Pilih sumber</option><?php foreach($accounts as $account): ?><option value="<?= (int)$account['id'] ?>"><?= e($account['name']) ?> · <?= rupiah($account['balance']) ?></option><?php endforeach; ?></select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>(wajib)</em></div></div>
                    <div class="col-12"><div class="input-style input-style-always-active has-borders no-icon mb-3"><label for="transfer-modal-to" class="color-highlight">Ke akun</label><select id="transfer-modal-to" name="to_account_id" required><option value="">Pilih tujuan</option><?php foreach($accounts as $account): ?><option value="<?= (int)$account['id'] ?>"><?= e($account['name']) ?></option><?php endforeach; ?></select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>(wajib)</em></div></div>
                </div>
                <div class="row mb-0"><div class="col-12"><div class="input-style input-style-always-active has-borders no-icon mb-3"><input class="form-control" id="transfer-modal-amount" type="number" min="0.01" max="9999999999999999.99" step="0.01" name="amount" required placeholder="0"><label for="transfer-modal-amount" class="color-highlight">Jumlah transfer</label><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>(wajib)</em></div></div><div class="col-12"><div class="input-style input-style-always-active has-borders no-icon mb-3"><input class="form-control" id="transfer-modal-fee" type="number" min="0" max="9999999999999999.99" step="0.01" name="admin_fee" value="0" required><label for="transfer-modal-fee" class="color-highlight">Biaya admin</label><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>(wajib)</em></div></div></div>
                <div class="input-style input-style-always-active has-borders no-icon mb-3"><label for="transfer-modal-status" class="color-highlight">Status</label><select id="transfer-modal-status" name="status"><?php if($canVerify): ?><option value="verified">Terverifikasi</option><?php endif; ?><option value="pending">Menunggu verifikasi</option></select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em></em></div>
                <div class="input-style input-style-always-active has-borders no-icon mb-3"><input class="form-control" id="transfer-modal-proof" type="file" name="proof" accept="image/jpeg,image/png,application/pdf" style="padding-top:13px"><label for="transfer-modal-proof" class="color-highlight">Bukti transfer</label><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>(opsional)</em></div>
                <div class="input-style input-style-always-active has-borders no-icon mb-3"><textarea id="transfer-modal-note" name="note" rows="2" maxlength="2000" placeholder="Catatan (opsional)"></textarea><label for="transfer-modal-note" class="color-highlight">Catatan</label><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em class="mt-n3">(opsional)</em></div>
                <div class="rounded-s bg-blue-light px-3 py-2 mb-3"><p class="font-11 color-blue-dark mb-0"><i class="fa fa-info-circle me-1"></i>Saldo sumber berkurang sebesar transfer + biaya admin.</p></div>
                <button type="submit" class="btn btn-full btn-m gradient-highlight rounded-s font-600" data-transfer-submit><i class="fas fa-exchange-alt me-1"></i>Simpan Transfer</button>
            </form>
        </div>
    </div>
<?php endif; ?>
