<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$groupedPositions = array();
foreach ($categories as $categoryKey => $categoryLabel) $groupedPositions[$categoryKey] = array();
foreach ($positions as $position) {
    $categoryKey = isset($groupedPositions[$position['category']]) ? $position['category'] : 'lainnya';
    $groupedPositions[$categoryKey][] = $position;
}
$hasFilters = $filters['q'] !== '' || $filters['category'] !== '' || $filters['status'] !== '';
?>

<div id="position-content">
<div class="card card-style">
    <div class="content mb-3">
        <div class="d-flex align-items-start flex-column flex-sm-row">
            <div>
                <p class="color-highlight font-600 mb-n1">Referensi Peserta</p>
                <h2 class="font-24 font-800 mb-1">Master Jabatan Desa</h2>
                <p class="mb-0">Kelola pilihan jabatan peserta. Nama disimpan tanpa kata “Desa” agar siap digunakan untuk mailing.</p>
            </div>
            <?php if ($canManage): ?>
                <button type="button" class="btn btn-s font-600 gradient-highlight rounded-s mt-3 mt-sm-0 ms-sm-auto text-nowrap"
                        data-position-modal-open data-mode="create" data-action="<?= site_url('jabatan/tambah') ?>">
                    <i class="fa fa-plus me-1"></i> Tambah Jabatan
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="content mt-n2 mb-0">
    <div class="row mb-0">
        <div class="col-4 pe-1">
            <div class="card card-style mx-0 mb-3 p-3 h-100">
                <p class="font-11 opacity-60 mb-n1">Seluruh</p>
                <h3 class="color-blue-dark mb-0"><?= number_format($stats['total']) ?></h3>
            </div>
        </div>
        <div class="col-4 px-1">
            <div class="card card-style mx-0 mb-3 p-3 h-100">
                <p class="font-11 opacity-60 mb-n1">Aktif</p>
                <h3 class="color-green-dark mb-0"><?= number_format($stats['active']) ?></h3>
            </div>
        </div>
        <div class="col-4 ps-1">
            <div class="card card-style mx-0 mb-3 p-3 h-100">
                <p class="font-11 opacity-60 mb-n1">Nonaktif</p>
                <h3 class="color-gray-dark mb-0"><?= number_format($stats['inactive']) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card card-style">
    <div class="content mb-2">
        <div class="d-flex align-items-center mb-3">
            <div>
                <p class="font-600 color-highlight mb-n1">Pencarian Data</p>
                <h3 class="font-20 mb-0">Filter Jabatan</h3>
            </div>
            <i class="fa fa-filter color-highlight font-24 opacity-30 ms-auto"></i>
        </div>

        <form method="get" action="<?= site_url('jabatan') ?>">
            <div class="row mb-0">
                <div class="col-lg-5">
                    <div class="input-style input-style-always-active has-borders has-icon mb-4">
                        <i class="fa fa-search color-highlight"></i>
                        <input class="form-control" type="search" id="position-search" name="q" maxlength="120"
                               value="<?= e($filters['q']) ?>" placeholder="Nama, kode, atau deskripsi">
                        <label for="position-search" class="color-highlight">Cari jabatan</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <em></em>
                    </div>
                </div>
                <div class="col-lg-3 col-md-5">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <label for="position-category" class="color-highlight">Kategori</label>
                        <select class="form-select" id="position-category" name="category">
                            <option value="">Semua kategori</option>
                            <?php foreach ($categories as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $filters['category'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span><i class="fa fa-chevron-down"></i></span>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <em></em>
                    </div>
                </div>
                <div class="col-lg-2 col-md-3">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <label for="position-status" class="color-highlight">Status</label>
                        <select class="form-select" id="position-status" name="status">
                            <option value="">Semua</option>
                            <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                            <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                        <span><i class="fa fa-chevron-down"></i></span>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <em></em>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <div class="row mb-0">
                        <div class="<?= $hasFilters ? 'col-7' : 'col-12' ?>">
                            <button class="btn btn-full btn-m gradient-highlight rounded-s font-600 mb-4" type="submit">Terapkan</button>
                        </div>
                        <?php if ($hasFilters): ?>
                            <div class="col-5 ps-0">
                                <a class="btn btn-full btn-m bg-theme color-theme border rounded-s font-600 mb-4" href="<?= site_url('jabatan') ?>" aria-label="Hapus filter">
                                    <i class="fa fa-times"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!$positions): ?>
    <div class="card card-style">
        <div class="content text-center py-4">
            <span class="icon icon-l rounded-xl bg-blue-light color-blue-dark mb-3"><i class="fa fa-id-badge font-24"></i></span>
            <h4>Jabatan tidak ditemukan</h4>
            <p class="opacity-60 mb-3"><?= $hasFilters ? 'Ubah atau hapus filter untuk melihat data lainnya.' : 'Tambahkan jabatan pertama agar dapat dipilih pada registrasi peserta.' ?></p>
            <?php if ($canManage && !$hasFilters): ?>
                <button type="button" class="btn btn-m gradient-highlight rounded-s font-600"
                        data-position-modal-open data-mode="create" data-action="<?= site_url('jabatan/tambah') ?>">Tambah Jabatan</button>
            <?php elseif ($hasFilters): ?>
                <a href="<?= site_url('jabatan') ?>" class="btn btn-m border-highlight color-highlight rounded-s font-600">Hapus Filter</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php foreach ($groupedPositions as $categoryKey => $items): ?>
    <?php if (!$items) continue; ?>
    <div class="card card-style">
        <div class="content mb-2">
            <div class="d-flex align-items-center mb-2">
                <span class="icon icon-s rounded-xl bg-blue-light color-blue-dark me-3"><i class="fa fa-layer-group"></i></span>
                <div>
                    <p class="font-10 opacity-50 mb-n1 text-uppercase">Kategori Jabatan</p>
                    <h3 class="font-20 mb-0"><?= e(isset($categories[$categoryKey]) ? $categories[$categoryKey] : 'Lainnya') ?></h3>
                </div>
                <span class="badge bg-blue-dark color-white rounded-s ms-auto"><?= number_format(count($items)) ?></span>
            </div>

            <?php foreach ($items as $index => $position): ?>
                <?php
                $positionModalPayload = array(
                    'id'=>(int)$position['id'],
                    'name'=>$position['name'],
                    'code'=>$position['code'],
                    'category'=>$position['category'],
                    'description'=>$position['description'],
                    'sort_order'=>(int)$position['sort_order'],
                    'is_active'=>(int)$position['is_active']
                );
                ?>
                <div class="py-3 <?= $index < count($items) - 1 ? 'border-bottom' : '' ?>">
                    <div class="d-flex align-items-start flex-column flex-md-row">
                        <div class="d-flex align-items-start flex-grow-1 w-100">
                            <span class="icon icon-s rounded-xl <?= (int) $position['is_active'] === 1 ? 'bg-green-light color-green-dark' : 'bg-gray-light color-gray-dark' ?> me-3 flex-shrink-0">
                                <i class="fa fa-user-tag"></i>
                            </span>
                            <div class="flex-grow-1" style="min-width:0;">
                                <div class="d-flex align-items-center flex-wrap">
                                    <h5 class="font-16 mb-0 me-2"><?= e($position['name']) ?></h5>
                                    <span class="badge <?= (int) $position['is_active'] === 1 ? 'bg-green-dark' : 'bg-gray-dark' ?> color-white font-9 rounded-s">
                                        <?= (int) $position['is_active'] === 1 ? 'AKTIF' : 'NONAKTIF' ?>
                                    </span>
                                </div>
                                <p class="font-11 opacity-50 mb-1"><i class="fa fa-code me-1"></i><?= e($position['code']) ?> &middot; Urutan <?= number_format($position['sort_order']) ?></p>
                                <?php if (!empty($position['description'])): ?>
                                    <p class="font-12 opacity-70 mb-0"><?= e($position['description']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($canManage): ?>
                            <div class="d-flex align-items-center mt-3 mt-md-0 ms-md-3 ps-5 ps-md-0 flex-shrink-0">
                                <button type="button" class="btn btn-xxs border-blue-dark color-blue-dark rounded-s font-600 me-2"
                                        data-position-modal-open data-mode="edit"
                                        data-action="<?= site_url('jabatan/' . (int) $position['id'] . '/ubah') ?>"
                                        data-position="<?= e(json_encode($positionModalPayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>">
                                    <i class="fa fa-edit me-1"></i>Ubah
                                </button>
                                <form method="post" action="<?= site_url('jabatan/' . (int) $position['id'] . '/status') ?>" class="d-inline"
                                      data-position-status-ajax
                                      data-confirm="<?= (int) $position['is_active'] === 1 ? 'Nonaktifkan jabatan ini? Jabatan tidak akan muncul pada input peserta baru.' : 'Aktifkan jabatan ini agar dapat dipilih pada input peserta?' ?>"
                                      data-confirm-title="<?= (int) $position['is_active'] === 1 ? 'Nonaktifkan Jabatan?' : 'Aktifkan Jabatan?' ?>"
                                      data-confirm-button="<?= (int) $position['is_active'] === 1 ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' ?>"
                                      data-confirm-tone="<?= (int) $position['is_active'] === 1 ? 'danger' : 'success' ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="is_active" value="<?= (int) $position['is_active'] === 1 ? '0' : '1' ?>">
                                    <button type="submit" class="btn btn-xxs <?= (int) $position['is_active'] === 1 ? 'border-red-dark color-red-dark' : 'border-green-dark color-green-dark' ?> rounded-s font-600">
                                        <i class="fa <?= (int) $position['is_active'] === 1 ? 'fa-toggle-off' : 'fa-toggle-on' ?> me-1"></i><?= (int) $position['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if ($canManage): ?>
    <a id="position-modal-opener" href="#" class="d-none" data-menu="position-modal" aria-hidden="true" tabindex="-1"></a>
    <div id="position-modal" class="menu menu-box-modal rounded-m" data-menu-width="390" data-menu-height="620" role="dialog" aria-modal="true" aria-labelledby="position-modal-title">
        <div class="content mb-0">
            <div class="d-flex align-items-start mb-3">
                <div><p class="font-600 color-highlight mb-n1">Referensi peserta</p><h3 id="position-modal-title" class="font-20 mb-0">Tambah Jabatan</h3></div>
                <button type="button" class="close-menu btn btn-xxs bg-theme color-theme border rounded-s ms-auto" aria-label="Tutup"><i class="fa fa-times"></i></button>
            </div>
            <form id="position-modal-form" method="post" action="<?= site_url('jabatan/tambah') ?>">
                <?= csrf_field() ?>
                <div class="input-style input-style-always-active has-borders has-icon mb-3">
                    <i class="fa fa-id-badge color-highlight"></i>
                    <input class="form-control" id="position-modal-name" name="name" maxlength="120" required placeholder="Contoh: Kepala">
                    <label for="position-modal-name" class="color-highlight">Nama Jabatan</label>
                    <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>*</em>
                </div>
                <p class="font-10 opacity-60 mt-n2 mb-3">Kata “Desa” akan ditambahkan oleh template mailing bila diperlukan.</p>
                <div class="input-style input-style-always-active has-borders no-icon mb-3">
                    <label for="position-modal-category" class="color-highlight">Kategori</label>
                    <select id="position-modal-category" name="category" required>
                        <?php foreach ($categories as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                    <span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>*</em>
                </div>
                <div class="row mb-0">
                    <div class="col-12"><div class="input-style input-style-always-active has-borders has-icon mb-2"><i class="fa fa-code color-highlight"></i><input class="form-control" id="position-modal-code" name="code" maxlength="80" placeholder="Dibuat otomatis"><label for="position-modal-code" class="color-highlight">Kode Jabatan</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em></em></div><p class="font-10 opacity-60 mt-n1 mb-3">Kosongkan untuk membuat kode otomatis.</p></div>
                    <div class="col-12"><div class="input-style input-style-always-active has-borders no-icon mb-3"><input class="form-control" type="number" id="position-modal-sort" name="sort_order" min="0" step="1" value="0" required><label for="position-modal-sort" class="color-highlight">Urutan</label><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>*</em></div></div>
                </div>
                <div class="input-style input-style-always-active has-borders no-icon mb-3">
                    <textarea class="form-control" id="position-modal-description" name="description" rows="3" maxlength="255" placeholder="Keterangan singkat"></textarea>
                    <label for="position-modal-description" class="color-highlight">Deskripsi</label>
                    <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em class="mt-n3"></em>
                </div>
                <div class="d-flex align-items-center rounded-s bg-green-light px-3 py-3 mb-3">
                    <span class="icon icon-s rounded-xl bg-green-dark color-white me-3"><i class="fa fa-toggle-on"></i></span>
                    <div><h5 class="font-14 mb-0">Jabatan Aktif</h5><p class="font-10 opacity-60 mb-0">Tampilkan pada input peserta.</p></div>
                    <div class="custom-control ios-switch scale-switch ms-auto me-2"><input type="checkbox" class="ios-input" id="position-modal-active" name="is_active" value="1" checked><label class="custom-control-label" for="position-modal-active"></label></div>
                </div>
                <div class="row mb-0"><div class="col-5 pe-1"><button type="button" class="close-menu btn btn-full btn-m bg-theme color-theme border rounded-s font-600">Batal</button></div><div class="col-7 ps-1"><button type="submit" class="btn btn-full btn-m gradient-highlight rounded-s font-600" data-position-modal-submit><i class="fa fa-save me-1"></i>Simpan Jabatan</button></div></div>
            </form>
        </div>
    </div>
<?php endif; ?>
