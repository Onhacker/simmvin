<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$activeEventCount = count($activeEvents);
$singleActiveEvent = $activeEventCount === 1 ? reset($activeEvents) : NULL;
$selectedEventId = is_array($selectedEvent) && isset($selectedEvent['id']) ? (int) $selectedEvent['id'] : 0;
$selectedEventId = (int) old('event_id', $selectedEventId);
?>

<div class="row mb-0 mx-0">
    <div class="col-xl-8">
        <div class="card card-style mx-0">
            <div class="content mb-0">
                <div class="d-flex align-items-center mb-3">
                    <div>
                        <p class="font-600 color-highlight mb-n1">Transaksi dana keluar</p>
                        <h2 class="mb-0">Tambah Pengeluaran</h2>
                    </div>
                    <a class="btn btn-s font-13 font-600 bg-theme color-theme border rounded-s ms-auto"
                       href="<?= site_url('pengeluaran') ?>">
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
                <?= form_open_multipart(current_url()) ?>
                    <div class="row mb-0">
                        <div class="col-md-6">
                            <div class="input-style input-style-always-active has-borders no-icon mb-4">
                                <label for="expense-category" class="color-highlight font-12 font-500">Kategori</label>
                                <select id="expense-category" name="category_id" required>
                                    <option value="">Pilih kategori</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" <?= old('category_id') == $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span><i class="fa fa-chevron-down"></i></span>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <em>*</em>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <?php if ($singleActiveEvent): ?>
                                <input type="hidden" name="event_id" value="<?= (int) $singleActiveEvent['id'] ?>">
                                <div class="d-flex align-items-center rounded-s bg-blue-light px-3 py-2 mb-4">
                                    <span class="icon icon-s rounded-xl bg-blue-dark color-white me-3 flex-shrink-0">
                                        <i class="fa fa-calendar-check"></i>
                                    </span>
                                    <div class="overflow-hidden me-2">
                                        <p class="font-10 color-blue-dark text-uppercase font-600 mb-n1">Event Aktif</p>
                                        <h5 class="font-14 text-truncate mb-n1"><?= e($singleActiveEvent['name']) ?></h5>
                                        <p class="font-10 opacity-60 text-truncate mb-0">
                                            <?= e($singleActiveEvent['code']) ?> · <?= tanggal_id($singleActiveEvent['start_date']) ?> s.d. <?= tanggal_id($singleActiveEvent['end_date']) ?>
                                        </p>
                                    </div>
                                    <span class="badge bg-green-dark color-white ms-auto">Aktif</span>
                                </div>
                            <?php else: ?>
                                <div class="input-style input-style-always-active has-borders no-icon mb-4">
                                    <label for="expense-event" class="color-highlight font-12 font-500">Event Aktif</label>
                                    <select id="expense-event" name="event_id" required>
                                        <option value="">Pilih event aktif</option>
                                        <?php foreach ($activeEvents as $event): ?>
                                            <option value="<?= (int) $event['id'] ?>" <?= $selectedEventId === (int) $event['id'] ? 'selected' : '' ?>><?= e($event['name'] . ' · ' . $event['code']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span><i class="fa fa-chevron-down"></i></span>
                                    <i class="fa fa-check disabled valid color-green-dark"></i>
                                    <i class="fa fa-times disabled invalid color-red-dark"></i>
                                    <em>*</em>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-12">
                            <div class="input-style input-style-always-active has-borders no-icon mb-4">
                                <input class="form-control" id="expense-date" type="date" name="expense_date" required value="<?= e(old('expense_date', date('Y-m-d'))) ?>">
                                <label for="expense-date" class="color-highlight font-12 font-500">Tanggal</label>
                                <i class="fa fa-check disabled valid me-4 pe-3 font-12 color-green-dark"></i>
                                <i class="fa fa-times disabled invalid me-4 pe-3 font-12 color-red-dark"></i>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="input-style input-style-always-active has-borders no-icon mb-4">
                                <textarea id="expense-description" name="description" rows="3" maxlength="3000" placeholder="Tujuan pengeluaran"><?= e(old('description')) ?></textarea>
                                <label for="expense-description" class="color-highlight font-12 font-500">Tujuan Pengeluaran</label>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <em class="mt-n3"></em>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="input-style input-style-always-active has-borders no-icon mb-4">
                                <input class="form-control js-money" id="expense-amount" type="number" data-money inputmode="decimal" min="0.01" max="9999999999999999.99" step="0.01" name="amount" required value="<?= e(old('amount')) ?>" placeholder="Rp 0">
                                <label for="expense-amount" class="color-highlight font-12 font-500">Jumlah</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <em>*</em>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="input-style input-style-always-active has-borders no-icon mb-4">
                                <label for="expense-method" class="color-highlight font-12 font-500">Metode</label>
                                <select class="js-payment-method" id="expense-method" name="method" required>
                                    <?php foreach (array('cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS') as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= old('method') === $key ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span><i class="fa fa-chevron-down"></i></span>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <em>*</em>
                            </div>
                        </div>
                        <div class="col-md-3 js-admin-fee">
                            <div class="input-style input-style-always-active has-borders no-icon mb-4">
                                <input class="form-control" id="expense-admin-fee" type="number" data-money min="0" step="0.01" name="admin_fee" value="<?= e(old('admin_fee', '0')) ?>" placeholder="Rp 0">
                                <label for="expense-admin-fee" class="color-highlight font-12 font-500">Biaya Admin</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <em></em>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="input-style input-style-always-active has-borders no-icon mb-4">
                                <label for="expense-account" class="color-highlight font-12 font-500">Keluar dari Akun</label>
                                <select id="expense-account" name="account_id" required>
                                    <option value="">Pilih akun</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?= $account['id'] ?>" <?= old('account_id') == $account['id'] ? 'selected' : '' ?>><?= e($account['name']) ?> — <?= rupiah($account['balance']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span><i class="fa fa-chevron-down"></i></span>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <em>*</em>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-style input-style-always-active has-borders no-icon mb-2">
                                <label for="expense-status" class="color-highlight font-12 font-500">Status</label>
                                <select id="expense-status" name="status">
                                    <?php if ($canVerify): ?>
                                        <option value="verified" <?= old('status', 'verified') === 'verified' ? 'selected' : '' ?>>Terverifikasi (langsung mengurangi saldo)</option>
                                    <?php endif; ?>
                                    <option value="pending" <?= !$canVerify || old('status') === 'pending' ? 'selected' : '' ?>>Menunggu verifikasi</option>
                                </select>
                                <span><i class="fa fa-chevron-down"></i></span>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <em></em>
                            </div>
                            <?php if (!$canVerify): ?>
                                <p class="font-11 opacity-60 mb-4">Transaksi akan menunggu verifikasi petugas keuangan.</p>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="expense-proof" class="font-12 font-600 color-highlight mb-2">Bukti Bayar</label>
                            <input class="form-control mb-1 js-expense-proof" id="expense-proof" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf">
                            <p class="font-11 opacity-60 mb-4" id="expense-proof-help">JPG, PNG, atau PDF; maksimal 5 MB.</p>
                        </div>
                        <div class="col-md-6">
                            <div class="input-style input-style-always-active has-borders no-icon mb-4">
                                <textarea id="expense-note" name="note" rows="2" placeholder="Catatan tambahan"><?= e(old('note')) ?></textarea>
                                <label for="expense-note" class="color-highlight font-12 font-500">Catatan</label>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <em class="mt-n3"></em>
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-full btn-m font-13 font-600 gradient-highlight rounded-s mb-3" type="submit">
                        <i class="fas fa-save me-1"></i> Simpan Pengeluaran
                    </button>
                <?= form_close() ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card card-style mx-0">
            <div class="content mb-0">
                <p class="font-600 color-highlight mb-n1">Master transaksi</p>
                <h2>Tambah Kategori</h2>
                <p class="font-12 opacity-60">Tambahkan kategori baru tanpa mengubah struktur sistem.</p>

                <?= form_open('pengeluaran/kategori/tambah') ?>
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <input class="form-control" id="expense-category-name" name="category_name" maxlength="100" required placeholder="Contoh: Narasumber">
                        <label for="expense-category-name" class="color-highlight font-12 font-500">Nama Kategori</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <em>*</em>
                    </div>
                    <button class="btn btn-full btn-m font-13 font-600 bg-theme color-theme border rounded-s mb-3" type="submit">
                        <i class="fas fa-plus me-1 color-highlight"></i> Tambahkan Kategori
                    </button>
                <?= form_close() ?>
            </div>
        </div>

        <div class="card card-style mx-0 bg-blue-dark">
            <div class="content">
                <i class="fas fa-info-circle color-white font-24 mb-3"></i>
                <h4 class="color-white">Pencatatan Saldo</h4>
                <p class="color-white opacity-70 mb-0 font-12">Pengeluaran terverifikasi mengurangi akun sebesar jumlah transaksi ditambah biaya admin.</p>
            </div>
        </div>
    </div>
</div>
