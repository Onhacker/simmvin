<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$activeAccounts = count(array_filter($accounts, function ($account) {
    return (int) $account['is_active'] === 1;
}));
$canManage = isset($canManage) ? (bool)$canManage : $this->Auth_model->can('accounts.manage');
?>

<div id="account-content">
<div class="card card-style bg-blue-dark">
    <div class="content">
        <div class="d-flex align-items-center">
            <div>
                <p class="color-white opacity-70 font-600 mb-n1">Total Saldo Dihitung</p>
                <h1 class="color-white font-30 mb-0"><?= rupiah($total) ?></h1>
            </div>
            <span class="icon icon-l rounded-xl bg-white color-blue-dark shadow-l ms-auto">
                <i class="fas fa-wallet font-24"></i>
            </span>
        </div>
        <div class="divider bg-white opacity-20 my-3"></div>
        <div class="d-flex color-white">
            <span class="font-12 opacity-70"><i class="fas fa-university me-1"></i> <?= number_format($activeAccounts) ?> akun aktif</span>
            <span class="font-12 opacity-70 ms-auto"><i class="fas fa-calculator me-1"></i> Buku besar otomatis</span>
        </div>
    </div>
</div>

<div class="content mb-0 mt-n2">
    <div class="row mb-0">
        <div class="col-6">
            <div class="card card-style mx-0 mb-3 p-3">
                <p class="font-12 opacity-60 mb-n1">Seluruh akun</p>
                <h3 class="color-blue-dark mb-0"><?= number_format(count($accounts)) ?></h3>
            </div>
        </div>
        <div class="col-6">
            <div class="card card-style mx-0 mb-3 p-3">
                <p class="font-12 opacity-60 mb-n1">Akun aktif</p>
                <h3 class="color-green-dark mb-0"><?= number_format($activeAccounts) ?></h3>
            </div>
        </div>
    </div>
</div>

<?php if (!$selectedId): ?>
<div class="card card-style">
    <div class="content mb-2">
        <div class="d-flex align-items-start flex-wrap mb-3">
            <div class="flex-grow-1 pe-2">
                <p class="font-600 color-highlight mb-n1">Posisi dana terkini</p>
            </div>
            <div class="d-flex align-items-center ms-auto mt-2 mt-sm-0">
                <a href="<?= site_url('akun-dana/cetak') ?>"
                   class="btn btn-s font-13 font-600 border-blue-dark color-blue-dark rounded-s"
                   data-report-preview-open="account-print-modal">
                    <i class="fas fa-print me-1"></i> Cetak
                </a>
                <?php if ($canManage): ?>
                <button type="button" class="btn btn-s font-13 font-600 gradient-highlight rounded-s ms-2"
                        data-account-modal-open data-mode="create" data-action="<?= site_url('akun-dana/tambah') ?>">
                    <i class="fas fa-plus me-1"></i> Akun
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if(!$accounts): ?><div class="text-center py-5 opacity-60"><i class="fas fa-wallet d-block font-24 mb-2"></i>Belum ada akun dana.</div><?php endif; ?>
        <?php foreach($accounts as $account): ?>
            <?php
            $accountModalPayload = array(
                'id'=>(int)$account['id'],
                'name'=>$account['name'],
                'type'=>$account['type'],
                'bank_name'=>$account['bank_name'],
                'account_number'=>$account['account_number'],
                'account_holder'=>$account['account_holder'],
                'opening_balance'=>$account['opening_balance'],
                'include_in_total'=>(int)$account['include_in_total'],
                'is_active'=>(int)$account['is_active'],
                'sort_order'=>(int)$account['sort_order']
            );
            ?>
            <div class="card bg-theme border rounded-s shadow-0 mb-3"><div class="content my-3">
                <div class="d-flex align-items-start"><span class="icon icon-s rounded-xl <?= $account['type']==='cash'?'bg-green-light color-green-dark':($account['type']==='qris'?'bg-magenta-light color-magenta-dark':'bg-blue-light color-blue-dark') ?> me-3"><i class="fas <?= $account['type']==='cash'?'fa-money-bill-wave':($account['type']==='qris'?'fa-qrcode':'fa-university') ?>"></i></span><div class="min-width-zero"><h4 class="font-17 mb-n1"><?= e($account['name']) ?></h4><p class="font-11 <?= (int)$account['is_active']?'color-green-dark':'color-red-dark' ?> mb-0"><i class="fas fa-circle font-8 me-1"></i><?= (int)$account['is_active']?'Aktif':'Nonaktif' ?> · <?= e(ucfirst($account['type'])) ?></p></div><strong class="color-blue-dark ms-auto text-end simp-balance-value"><?= rupiah($account['balance']) ?></strong></div>
                <div class="divider mt-3 mb-2"></div>
                <div class="d-flex py-2 border-bottom"><span class="opacity-60">Bank / Identitas</span><strong class="ms-auto text-end"><?= e($account['bank_name']?:'-') ?></strong></div>
                <div class="d-flex py-2 border-bottom"><span class="opacity-60">Nomor / Pemilik</span><strong class="ms-auto text-end"><?= e(trim(($account['account_number']?:'').' '.($account['account_holder']?:''))?:'-') ?></strong></div>
                <div class="d-flex py-2 border-bottom"><span class="opacity-60">Saldo Awal</span><strong class="ms-auto"><?= rupiah($account['opening_balance']) ?></strong></div>
                <div class="d-flex py-2"><span class="opacity-60">Masuk Total</span><span class="ms-auto"><?= (int)$account['include_in_total']?'<span class="badge bg-green-dark color-white">Ya</span>':'<span class="badge bg-gray-dark color-white">Tidak</span>' ?></span></div>
                <div class="row mb-0 mt-3">
                    <div class="<?= $canManage ? 'col-6 pe-1' : 'col-12' ?>"><a class="btn btn-full btn-m border-blue-dark color-blue-dark rounded-s font-600" href="<?= site_url('akun-dana?account_id='.$account['id']) ?>"><i class="fas fa-list me-1"></i> Mutasi</a></div>
                    <?php if($canManage): ?>
                        <div class="col-6 ps-1">
                            <button type="button" class="btn btn-full btn-m border-yellow-dark color-yellow-dark rounded-s font-600"
                                    data-account-modal-open data-mode="edit"
                                    data-action="<?= site_url('akun-dana/'.(int)$account['id'].'/ubah') ?>"
                                    data-account="<?= e(json_encode($accountModalPayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>">
                                <i class="fas fa-edit me-1"></i> Ubah
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div></div>
        <?php endforeach; ?>
    </div>
</div>

<?php endif; ?>
<?php if ($selectedId): ?>
    <div class="card card-style">
        <div class="content mb-2">
            <div class="d-flex align-items-center mb-3">
                <div>
                    <p class="font-600 color-highlight mb-n1">100 transaksi terakhir</p>
                    <h2 class="mb-0">Mutasi Akun</h2>
                </div>
                <a class="btn btn-s font-13 font-600 bg-theme color-theme border rounded-s ms-auto" href="<?= site_url('akun-dana') ?>">
                    <i class="fas fa-times me-1"></i> Tutup
                </a>
            </div>

            <?php if(!$ledger): ?><div class="text-center py-5 opacity-60">Belum ada mutasi.</div><?php endif; ?>
            <?php foreach($ledger as $entry): ?><div class="d-flex align-items-start py-3 border-bottom"><span class="icon icon-s rounded-xl <?= $entry['direction']==='in'?'bg-green-light color-green-dark':'bg-red-light color-red-dark' ?> me-3"><i class="fa fa-arrow-<?= $entry['direction']==='in'?'down':'up' ?>"></i></span><div class="min-width-zero"><h5 class="font-14 mb-n1"><?= e($entry['description']) ?></h5><p class="font-11 opacity-60 mb-0"><?= tanggal_id($entry['entry_date']) ?> · <?= e(ucfirst($entry['source_type'])) ?></p></div><strong class="<?= $entry['direction']==='in'?'color-green-dark':'color-red-dark' ?> ms-auto text-end simp-balance-value"><?= $entry['direction']==='in'?'+':'-' ?><?= rupiah($entry['amount']) ?></strong></div><?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
</div>

<?php if ($canManage): ?>
    <a id="account-modal-opener" href="#" class="d-none" data-menu="account-modal" aria-hidden="true" tabindex="-1"></a>
    <div id="account-modal" class="menu menu-box-modal rounded-m" data-menu-width="390" data-menu-height="680" role="dialog" aria-modal="true" aria-labelledby="account-modal-title">
        <div class="content mb-0">
            <div class="d-flex align-items-start mb-3">
                <div><p class="font-600 color-highlight mb-n1">Kas dan rekening</p><h3 id="account-modal-title" class="font-20 mb-0">Tambah Akun Dana</h3></div>
                <button type="button" class="close-menu btn btn-xxs bg-theme color-theme border rounded-s ms-auto" aria-label="Tutup"><i class="fa fa-times"></i></button>
            </div>
            <form id="account-modal-form" method="post" action="<?= site_url('akun-dana/tambah') ?>">
                <?= csrf_field() ?>
                <div class="input-style input-style-always-active has-borders no-icon mb-3">
                    <input class="form-control" id="account-modal-name" name="name" maxlength="120" required placeholder="Contoh: Rekening Perusahaan">
                    <label for="account-modal-name" class="color-highlight">Nama Akun</label>
                    <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>*</em>
                </div>
                <div class="input-style input-style-always-active has-borders no-icon mb-3">
                    <label for="account-modal-type" class="color-highlight">Jenis Akun</label>
                    <select id="account-modal-type" name="type" required>
                        <option value="cash">Tunai</option><option value="bank" selected>Bank</option><option value="qris">QRIS</option><option value="personal">Rekening Pribadi / Titipan</option>
                    </select>
                    <span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>*</em>
                </div>
                <div class="account-modal-bank-field">
                    <div class="input-style input-style-always-active has-borders no-icon mb-3">
                        <input class="form-control" id="account-modal-bank" name="bank_name" maxlength="120" placeholder="Bank / penyedia layanan">
                        <label for="account-modal-bank" class="color-highlight">Nama Bank / Penyedia</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em></em>
                    </div>
                </div>
                <div class="row mb-0 account-modal-bank-field">
                    <div class="col-12"><div class="input-style input-style-always-active has-borders no-icon mb-3"><input class="form-control" id="account-modal-number" name="account_number" maxlength="120" placeholder="Nomor rekening"><label for="account-modal-number" class="color-highlight">Nomor Rekening</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em></em></div></div>
                    <div class="col-12"><div class="input-style input-style-always-active has-borders no-icon mb-3"><input class="form-control" id="account-modal-holder" name="account_holder" maxlength="120" placeholder="Nama pemilik"><label for="account-modal-holder" class="color-highlight">Atas Nama</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em></em></div></div>
                </div>
                <div class="row mb-0">
                    <div class="col-12"><div class="input-style input-style-always-active has-borders no-icon mb-2"><input class="form-control" id="account-modal-opening" type="number" min="0" step="0.01" name="opening_balance" value="0" required><label for="account-modal-opening" class="color-highlight">Saldo Awal</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>*</em></div><p class="font-10 opacity-60 mt-n1 mb-3">Perubahan saldo awal memengaruhi saldo akhir.</p></div>
                    <div class="col-12"><div class="input-style input-style-always-active has-borders no-icon mb-3"><input class="form-control" id="account-modal-sort" type="number" name="sort_order" value="0" required><label for="account-modal-sort" class="color-highlight">Urutan</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>*</em></div></div>
                </div>
                <div class="card bg-theme border rounded-s shadow-0 mb-3"><div class="content my-3">
                    <div class="form-check icon-check mb-3"><input class="form-check-input" type="checkbox" name="include_in_total" value="1" id="account-modal-included" checked><label class="form-check-label" for="account-modal-included">Hitung dalam total saldo</label><i class="icon-check-1 far fa-square color-gray-dark"></i><i class="icon-check-2 far fa-check-square color-highlight"></i></div>
                    <div class="form-check icon-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="account-modal-active" checked><label class="form-check-label" for="account-modal-active">Akun aktif</label><i class="icon-check-1 far fa-square color-gray-dark"></i><i class="icon-check-2 far fa-check-square color-highlight"></i></div>
                </div></div>
                <div class="row mb-0"><div class="col-5 pe-1"><button type="button" class="close-menu btn btn-full btn-m bg-theme color-theme border rounded-s font-600">Batal</button></div><div class="col-7 ps-1"><button type="submit" class="btn btn-full btn-m gradient-highlight rounded-s font-600" data-account-modal-submit><i class="fa fa-save me-1"></i>Simpan Akun</button></div></div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php $this->load->view('reports/print_modal', array(
    'printModalId' => 'account-print-modal',
    'printModalTitle' => 'Cetak Saldo Kas & Rekening',
    'printPreviewUrl' => site_url('akun-dana/cetak'),
    'printPdfUrl' => site_url('akun-dana/pdf'),
    'printExcelUrl' => site_url('akun-dana/excel')
)); ?>
