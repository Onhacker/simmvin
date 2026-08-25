<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="card card-style">
    <div class="content mb-0">
        <div class="d-flex align-items-center mb-3">
            <div>
                <p class="font-600 color-highlight mb-n1">Perpindahan dana internal</p>
                <h2 class="mb-0">Tambah Transfer Dana</h2>
            </div>
            <a class="btn btn-s font-13 font-600 bg-theme color-theme border rounded-s ms-auto"
               href="<?= site_url('transfer-dana') ?>">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>

        <?php if (validation_errors()): ?>
            <div class="alert alert-small rounded-s shadow-xl bg-red-dark" role="alert">
                <span><i class="fa fa-times color-white"></i></span>
                <strong class="color-white"><?= validation_errors('<span class="d-block">', '</span>') ?></strong>
                <button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button>
            </div>
        <?php endif; ?>
        <?= form_open_multipart(current_url(), array('class' => 'js-transfer-form')) ?>
            <div class="row mb-0">
                <div class="col-lg-4">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <input class="form-control" id="transfer-date" type="date" name="transfer_date" required value="<?= e(old('transfer_date', date('Y-m-d'))) ?>">
                        <label for="transfer-date" class="color-highlight font-12 font-500">Tanggal</label>
                        <i class="fa fa-check disabled valid me-4 pe-3 font-12 color-green-dark"></i>
                        <i class="fa fa-times disabled invalid me-4 pe-3 font-12 color-red-dark"></i>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <label for="transfer-from-account" class="color-highlight font-12 font-500">Dari Akun</label>
                        <select class="js-from-account" id="transfer-from-account" name="from_account_id" required>
                            <option value="">Pilih sumber</option>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?= $account['id'] ?>" <?= old('from_account_id') == $account['id'] ? 'selected' : '' ?>><?= e($account['name']) ?> — <?= rupiah($account['balance']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span><i class="fa fa-chevron-down"></i></span>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <em>*</em>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <label for="transfer-to-account" class="color-highlight font-12 font-500">Ke Akun</label>
                        <select class="js-to-account" id="transfer-to-account" name="to_account_id" required>
                            <option value="">Pilih tujuan</option>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?= $account['id'] ?>" <?= old('to_account_id') == $account['id'] ? 'selected' : '' ?>><?= e($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span><i class="fa fa-chevron-down"></i></span>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <em>*</em>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <input class="form-control" id="transfer-amount" type="number" data-money min="0.01" step="0.01" name="amount" required value="<?= e(old('amount')) ?>" placeholder="Rp 0">
                        <label for="transfer-amount" class="color-highlight font-12 font-500">Jumlah Transfer</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <em>*</em>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="input-style input-style-always-active has-borders no-icon mb-2">
                        <input class="form-control" id="transfer-admin-fee" type="number" data-money min="0" step="0.01" name="admin_fee" value="<?= e(old('admin_fee', '0')) ?>" placeholder="Rp 0">
                        <label for="transfer-admin-fee" class="color-highlight font-12 font-500">Biaya Transfer</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <em></em>
                    </div>
                    <p class="font-11 opacity-60 mb-4">Biaya hanya mengurangi akun sumber.</p>
                </div>
                <div class="col-lg-4">
                    <div class="input-style input-style-always-active has-borders no-icon mb-2">
                        <label for="transfer-status" class="color-highlight font-12 font-500">Status</label>
                        <select id="transfer-status" name="status">
                            <?php if ($canVerify): ?>
                                <option value="verified" <?= old('status', 'verified') === 'verified' ? 'selected' : '' ?>>Terverifikasi</option>
                            <?php endif; ?>
                            <option value="pending" <?= !$canVerify || old('status') === 'pending' ? 'selected' : '' ?>>Menunggu verifikasi</option>
                        </select>
                        <span><i class="fa fa-chevron-down"></i></span>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <em></em>
                    </div>
                    <?php if (!$canVerify): ?>
                        <p class="font-11 opacity-60 mb-4">Petugas berizin akan memverifikasi transaksi.</p>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label for="transfer-proof" class="font-12 font-600 color-highlight mb-2">Bukti Transfer</label>
                    <input class="form-control mb-1" id="transfer-proof" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" required>
                    <p class="font-11 opacity-60 mb-4">JPG, PNG, atau PDF; maksimal 5 MB *.</p>
                </div>
                <div class="col-md-6">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <textarea id="transfer-note" name="note" rows="2" placeholder="Catatan tambahan"><?= e(old('note')) ?></textarea>
                        <label for="transfer-note" class="color-highlight font-12 font-500">Catatan</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i>
                        <em class="mt-n3"></em>
                    </div>
                </div>
            </div>

            <div class="alert alert-small rounded-s shadow-xl bg-blue-dark mb-4" role="alert">
                <span><i class="fa fa-info-circle color-white"></i></span>
                <strong class="color-white">Saldo sumber berkurang sebesar transfer + biaya admin. Saldo tujuan bertambah sebesar nilai transfer.</strong>
                <button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button>
            </div>

            <button class="btn btn-full btn-m font-13 font-600 gradient-highlight rounded-s mb-3" type="submit">
                <i class="fas fa-exchange-alt me-1"></i> Simpan Transfer
            </button>
        <?= form_close() ?>
    </div>
</div>
