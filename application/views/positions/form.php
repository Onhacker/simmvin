<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$editing = !empty($position);
$positionData = $position ?: array();
$selectedCategory = old('category', isset($positionData['category']) ? $positionData['category'] : 'pemerintah_desa');
$isPost = strtoupper($this->input->method(TRUE)) === 'POST';
$isActive = $isPost
    ? $this->input->post('is_active') !== NULL
    : (!$editing || (int) $positionData['is_active'] === 1);
?>

<div class="card card-style">
    <div class="content mb-3">
        <div class="d-flex align-items-start">
            <div>
                <p class="color-highlight font-600 mb-n1"><?= $editing ? 'Perbarui Data' : 'Referensi Baru' ?></p>
                <h2 class="font-24 font-800 mb-1"><?= $editing ? 'Ubah Jabatan' : 'Tambah Jabatan' ?></h2>
                <p class="mb-0">Jabatan aktif akan tersedia pada form registrasi dan penambahan peserta.</p>
            </div>
            <a href="<?= site_url('jabatan') ?>" class="icon icon-s rounded-xl bg-theme color-theme shadow-xl ms-auto flex-shrink-0" aria-label="Kembali">
                <i class="fa fa-arrow-left"></i>
            </a>
        </div>
    </div>
</div>

<?php if (validation_errors()): ?>
    <div class="mx-3 alert alert-small rounded-s shadow-xl bg-red-dark" role="alert">
        <span><i class="fa fa-exclamation-triangle color-white"></i></span>
        <strong class="color-white"><?= validation_errors('<span class="d-block">', '</span>') ?></strong>
        <button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button>
    </div>
<?php endif; ?>
<?php if (!empty($errorMessage)): ?>
    <div class="mx-3 alert alert-small rounded-s shadow-xl bg-red-dark" role="alert">
        <span><i class="fa fa-times color-white"></i></span>
        <strong class="color-white"><?= e($errorMessage) ?></strong>
        <button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button>
    </div>
<?php endif; ?>

<?= form_open(current_url()) ?>
    <div class="row mb-0 mx-0">
        <div class="col-xl-8">
            <div class="card card-style mx-0">
                <div class="content mb-0">
                    <p class="font-600 color-highlight mb-n1">Informasi Utama</p>
                    <h3 class="font-20 mb-4">Identitas Jabatan</h3>

                    <div class="row mb-0">
                        <div class="col-md-7">
                            <div class="input-style input-style-always-active has-borders has-icon validate-field mb-4">
                                <i class="fa fa-id-badge color-highlight"></i>
                                <input class="form-control" id="position-name" name="name" required maxlength="120"
                                       value="<?= e(old('name', isset($positionData['name']) ? $positionData['name'] : '')) ?>"
                                       placeholder="Contoh: Kepala">
                                <label for="position-name" class="color-highlight">Nama Jabatan</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <em>(wajib)</em>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="input-style input-style-always-active has-borders no-icon mb-4">
                                <label for="position-category" class="color-highlight">Kategori</label>
                                <select class="form-select" id="position-category" name="category" required>
                                    <?php foreach ($categories as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= $selectedCategory === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span><i class="fa fa-chevron-down"></i></span>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <em></em>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-0">
                        <div class="col-md-8">
                            <div class="input-style input-style-always-active has-borders has-icon mb-2">
                                <i class="fa fa-code color-highlight"></i>
                                <input class="form-control" id="position-code" name="code" maxlength="80"
                                       value="<?= e(old('code', isset($positionData['code']) ? $positionData['code'] : '')) ?>"
                                       placeholder="Dibuat otomatis dari nama">
                                <label for="position-code" class="color-highlight">Kode Jabatan</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <em>(opsional)</em>
                            </div>
                            <p class="font-10 opacity-50 mt-n1 mb-4">Kata “Desa” ditambahkan oleh template mailing. Kosongkan agar kode dibuat otomatis.</p>
                        </div>
                        <div class="col-md-4">
                            <div class="input-style input-style-always-active has-borders no-icon mb-4">
                                <input class="form-control" type="number" id="position-sort" name="sort_order" required min="0" step="1"
                                       value="<?= e(old('sort_order', isset($positionData['sort_order']) ? $positionData['sort_order'] : '0')) ?>"
                                       placeholder="0">
                                <label for="position-sort" class="color-highlight">Urutan Tampil</label>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <i class="fa fa-times disabled invalid color-red-dark"></i>
                                <em>(wajib)</em>
                            </div>
                        </div>
                    </div>

                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <textarea class="form-control" id="position-description" name="description" rows="4" maxlength="255"
                                  placeholder="Keterangan singkat mengenai jabatan (opsional)"><?= e(old('description', isset($positionData['description']) ? $positionData['description'] : '')) ?></textarea>
                        <label for="position-description" class="color-highlight">Deskripsi</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <em>(opsional)</em>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card card-style mx-0">
                <div class="content">
                    <p class="font-600 color-highlight mb-n1">Ketersediaan</p>
                    <h3 class="font-20 mb-3">Status Jabatan</h3>
                    <div class="d-flex align-items-center">
                        <span class="icon icon-m rounded-xl bg-green-light color-green-dark me-3"><i class="fa fa-toggle-on"></i></span>
                        <div>
                            <h5 class="font-14 mb-0">Jabatan Aktif</h5>
                            <p class="font-11 opacity-60 mb-0">Tampilkan pada pilihan peserta baru.</p>
                        </div>
                        <div class="custom-control ios-switch scale-switch ms-auto me-2">
                            <input type="checkbox" class="ios-input" id="position-active" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="position-active"></label>
                        </div>
                    </div>
                    <?php if ($editing): ?>
                        <div class="divider my-3"></div>
                        <p class="font-11 opacity-60 mb-0"><i class="fa fa-info-circle color-blue-dark me-1"></i>Menonaktifkan jabatan tidak mengubah data peserta lama.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style">
        <div class="content">
            <div class="row mb-0 justify-content-end">
                <div class="col-5 col-md-3">
                    <a href="<?= site_url('jabatan') ?>" class="btn btn-full btn-m border-highlight color-highlight rounded-s font-600">Batal</a>
                </div>
                <div class="col-7 col-md-4">
                    <button class="btn btn-full btn-m gradient-highlight rounded-s font-600" type="submit">
                        <i class="fa fa-save me-1"></i>Simpan Jabatan
                    </button>
                </div>
            </div>
        </div>
    </div>
<?= form_close() ?>
