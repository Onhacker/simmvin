<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$accountData = $account ?: array();
$type = old('type', isset($accountData['type']) ? $accountData['type'] : 'bank');
?>

<div class="card card-style">
    <div class="content mb-0">
        <div class="d-flex align-items-center mb-3">
            <div>
                <p class="font-600 color-highlight mb-n1">Sumber dan penyimpanan dana</p>
                <h2 class="mb-0"><?= $account ? 'Ubah' : 'Tambah' ?> Akun</h2>
            </div>
            <a class="btn btn-s font-13 font-600 bg-theme color-theme border rounded-s ms-auto"
               href="<?= site_url('akun-dana') ?>">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>

        <?php if (validation_errors() || !empty($error)): ?>
            <div class="alert alert-small rounded-s shadow-xl bg-red-dark" role="alert">
                <span><i class="fa fa-times color-white"></i></span>
                <strong class="color-white"><?= validation_errors('<span class="d-block">', '</span>') ?><?= !empty($error) ? '<span class="d-block">'.e($error).'</span>' : '' ?></strong>
                <button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button>
            </div>
        <?php endif; ?>
        <?= form_open(current_url()) ?>
            <div class="row mb-0">
                <div class="col-md-7">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <input class="form-control" id="account-name" name="name" maxlength="120" required
                               value="<?= e(old('name', isset($accountData['name']) ? $accountData['name'] : '')) ?>"
                               placeholder="Contoh: Rekening Perusahaan">
                        <label for="account-name" class="color-highlight font-12 font-500">Nama Akun</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <em>*</em>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <label for="account-type" class="color-highlight font-12 font-500">Jenis</label>
                        <select class="js-account-type" id="account-type" name="type" required>
                            <?php foreach (array('cash' => 'Tunai', 'bank' => 'Bank', 'qris' => 'QRIS', 'personal' => 'Rekening Pribadi/Titipan') as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $type === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span><i class="fa fa-chevron-down"></i></span>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <em>*</em>
                    </div>
                </div>

                <div class="col-md-4 js-bank-field">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <input class="form-control" id="account-bank" name="bank_name"
                               value="<?= e(old('bank_name', isset($accountData['bank_name']) ? $accountData['bank_name'] : '')) ?>"
                               placeholder="Bank / penyedia layanan">
                        <label for="account-bank" class="color-highlight font-12 font-500">Nama Bank / Penyedia</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <em></em>
                    </div>
                </div>
                <div class="col-md-4 js-bank-field">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <input class="form-control" id="account-number" name="account_number"
                               value="<?= e(old('account_number', isset($accountData['account_number']) ? $accountData['account_number'] : '')) ?>"
                               placeholder="Nomor rekening atau merchant">
                        <label for="account-number" class="color-highlight font-12 font-500">Nomor Rekening / Merchant</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <em></em>
                    </div>
                </div>
                <div class="col-md-4 js-bank-field">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <input class="form-control" id="account-holder" name="account_holder"
                               value="<?= e(old('account_holder', isset($accountData['account_holder']) ? $accountData['account_holder'] : '')) ?>"
                               placeholder="Nama pemilik rekening">
                        <label for="account-holder" class="color-highlight font-12 font-500">Atas Nama</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <em></em>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="input-style input-style-always-active has-borders no-icon mb-2">
                        <input class="form-control js-money" id="account-opening-balance" type="number" min="0" step="0.01"
                               name="opening_balance" required
                               value="<?= e(old('opening_balance', isset($accountData['opening_balance']) ? $accountData['opening_balance'] : '0')) ?>"
                               placeholder="0">
                        <label for="account-opening-balance" class="color-highlight font-12 font-500">Saldo Awal</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <em>*</em>
                    </div>
                    <p class="font-11 opacity-60 mb-4">Perubahan saldo awal akan mengubah saldo akhir akun.</p>
                </div>
                <div class="col-md-3">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <input class="form-control" id="account-sort" type="number" name="sort_order" required
                               value="<?= e(old('sort_order', isset($accountData['sort_order']) ? $accountData['sort_order'] : '0')) ?>"
                               placeholder="0">
                        <label for="account-sort" class="color-highlight font-12 font-500">Urutan</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <em>*</em>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-theme border rounded-s shadow-0 mb-4">
                        <div class="content my-3">
                            <div class="form-check icon-check mb-3">
                                <input class="form-check-input" type="checkbox" name="include_in_total" value="1" id="included"
                                    <?= old('include_in_total', isset($accountData['include_in_total']) ? $accountData['include_in_total'] : 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="included">Hitung dalam total</label>
                                <i class="icon-check-1 far fa-square color-gray-dark"></i>
                                <i class="icon-check-2 far fa-check-square color-highlight"></i>
                            </div>
                            <div class="form-check icon-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active"
                                    <?= old('is_active', isset($accountData['is_active']) ? $accountData['is_active'] : 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="active">Akun aktif</label>
                                <i class="icon-check-1 far fa-square color-gray-dark"></i>
                                <i class="icon-check-2 far fa-check-square color-highlight"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-5">
                    <a class="btn btn-full btn-m font-13 font-600 bg-theme color-theme border rounded-s" href="<?= site_url('akun-dana') ?>">Batal</a>
                </div>
                <div class="col-7">
                    <button class="btn btn-full btn-m font-13 font-600 gradient-highlight rounded-s" type="submit">
                        <i class="fas fa-save me-1"></i> Simpan Akun
                    </button>
                </div>
            </div>
        <?= form_close() ?>
    </div>
</div>
