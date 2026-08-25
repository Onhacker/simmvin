<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$selectedMap = array_fill_keys(array_map('intval', $selectedPermissionIds), TRUE);
$permissionGroups = array();
foreach ($permissions as $permission) $permissionGroups[$permission['module']][] = $permission;
$protectedIdentity = (int) $role['is_system'] === 1;
$isSuperAdmin = $role['slug'] === 'super-admin';
?>

<div class="card card-style">
    <div class="content mb-3">
        <div class="d-flex align-items-center">
            <div>
                <p class="color-highlight font-600 mb-n1">Kontrol Akses</p>
                <h2 class="font-24 font-800 mb-1">Ubah Peran</h2>
                <p class="mb-0">Atur identitas dan izin bawaan <?= e($role['name']) ?>.</p>
            </div>
            <a href="<?= site_url('peran') ?>" class="icon icon-s rounded-xl bg-theme color-theme shadow-xl ms-auto" aria-label="Kembali"><i class="fa fa-arrow-left"></i></a>
        </div>
    </div>
</div>

<?php if (validation_errors()): ?>
    <div class="mx-3 alert alert-small rounded-s shadow-xl bg-red-dark" role="alert"><span><i class="fa fa-exclamation-triangle color-white"></i></span><strong class="color-white"><?= validation_errors('<span class="d-block">', '</span>') ?></strong><button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button></div>
<?php endif; ?>
<?php if (!empty($errorMessage)): ?><div class="mx-3 alert alert-small rounded-s shadow-xl bg-red-dark" role="alert"><span><i class="fa fa-times color-white"></i></span><strong class="color-white"><?= e($errorMessage) ?></strong><button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button></div><?php endif; ?>
<?php if ($isSuperAdmin): ?>
    <div class="mx-3 alert alert-small rounded-s shadow-xl bg-blue-dark" role="alert"><span><i class="fa fa-info-circle color-white"></i></span><strong class="color-white">Super Admin selalu memiliki seluruh izin. Semua izin akan tetap aktif saat disimpan.</strong><button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button></div>
<?php elseif ($protectedIdentity): ?>
    <div class="mx-3 alert alert-small rounded-s shadow-xl bg-yellow-dark" role="alert"><span><i class="fa fa-lock color-white"></i></span><strong class="color-white">Nama dan kode peran sistem dilindungi, tetapi matriks izinnya dapat disesuaikan.</strong><button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button></div>
<?php endif; ?>

<?= form_open(current_url()) ?>
    <div class="row mb-0 mx-0">
        <div class="col-xl-4">
            <div class="card card-style mx-0">
                <div class="content mb-0">
                    <p class="color-highlight font-600 mb-n1">Identitas Jabatan</p>
                    <h3 class="font-22 mb-3">Informasi Peran</h3>

                    <div class="input-style input-style-always-active has-borders has-icon validate-field mb-4">
                        <i class="fa fa-user-tag color-highlight"></i>
                        <input class="form-control" id="name" name="name" maxlength="80" required value="<?= e(old('name', $role['name'])) ?>" <?= $protectedIdentity ? 'readonly' : '' ?> placeholder="Nama peran">
                        <label for="name" class="color-highlight font-12 font-500">Nama Peran</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em><?= $protectedIdentity ? '(dilindungi)' : '*' ?></em>
                    </div>
                    <div class="input-style input-style-always-active has-borders has-icon validate-field mb-4">
                        <i class="fa fa-code color-highlight"></i>
                        <input class="form-control" id="slug" name="slug" maxlength="80" required value="<?= e(old('slug', $role['slug'])) ?>" <?= $protectedIdentity ? 'readonly' : '' ?> placeholder="kode-peran">
                        <label for="slug" class="color-highlight font-12 font-500">Kode Peran</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em><?= $protectedIdentity ? '(dilindungi)' : '*' ?></em>
                    </div>
                    <p class="font-10 opacity-50 mt-n3 mb-4">Kode dipakai sistem untuk mengenali peran.</p>
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <textarea class="form-control" id="description" name="description" rows="5" maxlength="255" placeholder="Jelaskan tanggung jawab peran ini"><?= e(old('description', $role['description'])) ?></textarea>
                        <label for="description" class="color-highlight font-12 font-500">Deskripsi</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em></em>
                    </div>

                    <div class="divider mb-3"></div>
                    <div class="d-flex">
                        <div><p class="font-10 opacity-50 mb-n1">PENGGUNA</p><h3 class="mb-0"><?= isset($role['user_count']) ? (int) $role['user_count'] : 0 ?></h3></div>
                        <div class="ms-auto text-end"><p class="font-10 opacity-50 mb-n1">IZIN DIPILIH</p><h3 class="mb-0 color-highlight"><?= number_format(count($selectedPermissionIds)) ?></h3></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card card-style mx-0">
                <div class="content mb-2">
                    <div class="d-flex align-items-start mb-3">
                        <div>
                            <p class="color-highlight font-600 mb-n1">Izin Bawaan Jabatan</p>
                            <h3 class="font-22 mb-1">Matriks Hak Akses</h3>
                            <p class="mb-0">Izin ini menjadi bawaan bagi pengguna dengan peran ini.</p>
                        </div>
                        <?php if (!$isSuperAdmin): ?><button type="button" class="btn btn-s font-600 border-red-dark color-red-dark rounded-s ms-auto" id="clearPermissions"><i class="fa fa-times me-1"></i> Kosongkan</button><?php endif; ?>
                    </div>

                    <div class="accordion" id="role-permission-accordion">
                        <?php $moduleIndex = 0; ?>
                        <?php foreach ($permissionGroups as $module => $items): ?>
                            <?php $moduleIndex++; $moduleId = 'role-permission-module-' . $moduleIndex; ?>
                            <div class="card card-style mx-0 mb-2 shadow-0 permission-module">
                                <div class="d-flex align-items-center">
                                    <button class="btn accordion-btn no-effect color-theme flex-grow-1 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $moduleId ?>" aria-expanded="<?= $moduleIndex === 1 ? 'true' : 'false' ?>">
                                        <i class="fa fa-folder-open color-highlight me-2"></i><?= e($module) ?>
                                        <i class="fa fa-chevron-down font-10 accordion-icon"></i>
                                    </button>
                                    <?php if (!$isSuperAdmin): ?><button type="button" class="btn btn-link btn-sm color-highlight text-decoration-none me-3 toggle-module">Pilih semua</button><?php endif; ?>
                                </div>
                                <div id="<?= $moduleId ?>" class="collapse <?= $moduleIndex === 1 ? 'show' : '' ?>" data-bs-parent="#role-permission-accordion">
                                    <div class="px-3 pb-3">
                                        <?php foreach ($items as $permissionIndex => $permission): ?>
                                            <?php $permissionId = (int) $permission['id']; $inputId = 'role-permission-' . $permissionId; ?>
                                            <div class="d-flex align-items-center py-2 <?= $permissionIndex < count($items) - 1 ? 'border-bottom' : '' ?>">
                                                <div class="pe-3">
                                                    <h6 class="font-14 font-600 mb-0"><?= e($permission['name']) ?></h6>
                                                    <small class="opacity-50"><?= e($permission['code']) ?></small>
                                                </div>
                                                <div class="ms-auto me-3">
                                                    <div class="custom-control ios-switch scale-switch">
                                                        <input class="ios-input permission-checkbox" id="<?= $inputId ?>" type="checkbox" name="permissions[]" value="<?= $permissionId ?>" <?= isset($selectedMap[$permissionId]) ? 'checked' : '' ?> <?= $isSuperAdmin ? 'disabled' : '' ?>>
                                                        <label class="custom-control-label" for="<?= $inputId ?>"></label>
                                                        <?php if ($isSuperAdmin): ?><input type="hidden" name="permissions[]" value="<?= $permissionId ?>"><?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style">
        <div class="content">
            <div class="d-flex gap-2 justify-content-end">
                <a href="<?= site_url('peran') ?>" class="btn btn-m font-600 border-highlight color-highlight rounded-s">Batal</a>
                <button type="submit" class="btn btn-m font-600 gradient-highlight rounded-s"><i class="fa fa-save me-1"></i> Simpan Peran</button>
            </div>
        </div>
    </div>
<?= form_close() ?>

<?php if (!$isSuperAdmin): ?>
<script>
(function () {
    var allBoxes = Array.prototype.slice.call(document.querySelectorAll('.permission-checkbox'));
    document.getElementById('clearPermissions').addEventListener('click', function () {
        allBoxes.forEach(function (box) { box.checked = false; });
    });
    document.querySelectorAll('.toggle-module').forEach(function (button) {
        button.addEventListener('click', function () {
            var boxes = Array.prototype.slice.call(button.closest('.permission-module').querySelectorAll('.permission-checkbox'));
            var selectAll = boxes.some(function (box) { return !box.checked; });
            boxes.forEach(function (box) { box.checked = selectAll; });
        });
    });
})();
</script>
<?php endif; ?>
