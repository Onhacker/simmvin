<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$editing = !empty($user);
$selectedMap = array_fill_keys(array_map('intval', $selectedPermissionIds), TRUE);
$permissionGroups = array();
foreach ($permissions as $permission) $permissionGroups[$permission['module']][] = $permission;
$selectedRole = (int) old('role_id', $editing ? $user['role_id'] : (isset($roles[0]) ? $roles[0]['id'] : 0));
$isPost = strtoupper($this->input->method(TRUE)) === 'POST';
$isActive = $isPost ? $this->input->post('is_active') !== NULL : (!$editing || (int) $user['is_active'] === 1);
?>

<div class="card card-style">
    <div class="content mb-3">
        <div class="d-flex align-items-center">
            <div>
                <p class="color-highlight font-600 mb-n1"><?= $editing ? 'Perbarui Akun' : 'Akun Baru' ?></p>
                <h2 class="font-24 font-800 mb-1"><?= $editing ? 'Ubah Pengguna' : 'Tambah Pengguna' ?></h2>
                <p class="mb-0">Atur data akun dan hak akses efektif pengguna.</p>
            </div>
            <a href="<?= site_url('pengguna') ?>" class="icon icon-s rounded-xl bg-theme color-theme shadow-xl ms-auto" aria-label="Kembali"><i class="fa fa-arrow-left"></i></a>
        </div>
    </div>
</div>

<?php if (validation_errors()): ?>
    <div class="mx-3 alert alert-small rounded-s shadow-xl bg-red-dark" role="alert"><span><i class="fa fa-exclamation-triangle color-white"></i></span><strong class="color-white"><?= validation_errors('<span class="d-block">', '</span>') ?></strong><button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button></div>
<?php endif; ?>
<?php if (!empty($errorMessage)): ?>
    <div class="mx-3 alert alert-small rounded-s shadow-xl bg-red-dark" role="alert"><span><i class="fa fa-times color-white"></i></span><strong class="color-white"><?= e($errorMessage) ?></strong><button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button></div>
<?php endif; ?>

<?= form_open(current_url(), array('autocomplete' => 'off')) ?>
    <div class="row mb-0 mx-0">
        <div class="col-xl-5">
            <div class="card card-style mx-0">
                <div class="content mb-0">
                    <p class="color-highlight font-600 mb-n1">Identitas dan Login</p>
                    <h3 class="font-22 mb-3">Informasi Akun</h3>

                    <div class="input-style input-style-always-active has-borders has-icon validate-field mb-4">
                        <i class="fa fa-user color-highlight"></i>
                        <input class="form-control" id="name" name="name" maxlength="120" required value="<?= e(old('name', $editing ? $user['name'] : '')) ?>" placeholder="Nama lengkap">
                        <label for="name" class="color-highlight font-12 font-500">Nama Lengkap</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>*</em>
                    </div>
                    <div class="input-style input-style-always-active has-borders has-icon validate-field mb-4">
                        <i class="fa fa-at color-highlight"></i>
                        <input class="form-control" id="username" name="username" maxlength="80" required autocomplete="off" value="<?= e(old('username', $editing ? $user['username'] : '')) ?>" placeholder="username">
                        <label for="username" class="color-highlight font-12 font-500">Username</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>*</em>
                    </div>
                    <p class="font-10 opacity-50 mt-n3 mb-4">Gunakan huruf, angka, garis bawah, atau tanda hubung.</p>

                    <div class="row mb-0">
                        <div class="col-md-7">
                            <div class="input-style input-style-always-active has-borders no-icon validate-field mb-4">
                                <input type="email" class="form-control" id="email" name="email" maxlength="160" value="<?= e(old('email', $editing ? $user['email'] : '')) ?>" placeholder="nama@email.com">
                                <label for="email" class="color-highlight font-12 font-500">Email</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em></em>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="input-style input-style-always-active has-borders no-icon validate-field mb-4">
                                <input class="form-control" id="phone" name="phone" maxlength="30" inputmode="tel" value="<?= e(old('phone', $editing ? $user['phone'] : '')) ?>" placeholder="08xxxxxxxxxx">
                                <label for="phone" class="color-highlight font-12 font-500">Nomor HP</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em></em>
                            </div>
                        </div>
                    </div>

                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <label for="role_id" class="color-highlight font-12 font-500">Peran</label>
                        <select class="form-select" id="role_id" name="role_id" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= (int) $role['id'] ?>" data-role-slug="<?= e($role['slug']) ?>" <?= $selectedRole === (int) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span><i class="fa fa-chevron-down"></i></span>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <i class="fa fa-times disabled invalid color-red-dark"></i><em>*</em>
                    </div>
                    <p class="font-10 opacity-50 mt-n3 mb-4">Mengganti peran akan menawarkan pilihan izin bawaan peran tersebut.</p>

                    <div class="row mb-0">
                        <div class="col-md-6">
                            <div class="input-style input-style-always-active has-borders no-icon validate-field mb-4">
                                <input type="password" class="form-control" id="password" name="password" minlength="8" maxlength="200" autocomplete="new-password" <?= $editing ? '' : 'required' ?> placeholder="Minimal 8 karakter">
                                <label for="password" class="color-highlight font-12 font-500">Kata Sandi</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em><?= $editing ? '' : '*' ?></em>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-style input-style-always-active has-borders no-icon validate-field mb-4">
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" maxlength="200" autocomplete="new-password" <?= $editing ? '' : 'required' ?> placeholder="Ulangi kata sandi">
                                <label for="password_confirmation" class="color-highlight font-12 font-500">Konfirmasi</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em><?= $editing ? '' : '*' ?></em>
                            </div>
                        </div>
                    </div>
                    <?php if ($editing): ?><p class="font-10 opacity-50 mt-n3 mb-4">Kosongkan kata sandi bila tidak ingin menggantinya.</p><?php endif; ?>

                    <div class="divider mb-3"></div>
                    <div class="d-flex align-items-center mb-3">
                        <div>
                            <h5 class="font-600 font-14 mb-0">Akun Aktif</h5>
                            <p class="font-10 opacity-50 mb-0">Pengguna dapat masuk ke aplikasi.</p>
                        </div>
                        <div class="ms-auto me-3">
                            <div class="custom-control ios-switch scale-switch">
                                <input type="checkbox" class="ios-input" id="is_active" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?> <?= $editing && (int) $user['id'] === (int) $currentUser['id'] ? 'data-own-account="1"' : '' ?>>
                                <label class="custom-control-label" for="is_active"></label>
                            </div>
                        </div>
                    </div>
                    <?php if ($editing && (int) $user['id'] === (int) $currentUser['id']): ?>
                        <p class="color-yellow-dark font-11 mb-0"><i class="fa fa-exclamation-triangle me-1"></i>Akun yang sedang digunakan tidak dapat dinonaktifkan.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card card-style mx-0">
                <div class="content mb-2">
                    <p class="color-highlight font-600 mb-n1">Izin Khusus Pengguna</p>
                    <h3 class="font-22 mb-1">Hak Akses Efektif</h3>
                    <p class="mb-1">Pilihan ini disimpan khusus untuk pengguna dan mengesampingkan perannya.</p>
                    <p class="color-blue-dark font-11 mb-3 d-none" id="superAdminNotice"><i class="fa fa-info-circle me-1"></i>Super Admin selalu memiliki seluruh hak akses.</p>

                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <button type="button" class="btn btn-s font-600 gradient-highlight rounded-s" id="applyRoleDefaults"><i class="fa fa-magic me-1"></i> Bawaan Peran</button>
                        <button type="button" class="btn btn-s font-600 border-red-dark color-red-dark rounded-s" id="clearPermissions"><i class="fa fa-times me-1"></i> Kosongkan</button>
                    </div>

                    <div class="accordion" id="user-permission-accordion">
                        <?php $moduleIndex = 0; ?>
                        <?php foreach ($permissionGroups as $module => $items): ?>
                            <?php $moduleIndex++; $moduleId = 'user-permission-module-' . $moduleIndex; ?>
                            <div class="card card-style mx-0 mb-2 shadow-0 permission-module">
                                <div class="d-flex align-items-center">
                                    <button class="btn accordion-btn no-effect color-theme flex-grow-1 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $moduleId ?>" aria-expanded="<?= $moduleIndex === 1 ? 'true' : 'false' ?>">
                                        <i class="fa fa-shield-alt color-highlight me-2"></i><?= e($module) ?>
                                        <i class="fa fa-chevron-down font-10 accordion-icon"></i>
                                    </button>
                                    <button type="button" class="btn btn-link btn-sm color-highlight text-decoration-none me-3 toggle-module">Pilih semua</button>
                                </div>
                                <div id="<?= $moduleId ?>" class="collapse <?= $moduleIndex === 1 ? 'show' : '' ?>" data-bs-parent="#user-permission-accordion">
                                    <div class="px-3 pb-3">
                                        <?php foreach ($items as $permissionIndex => $permission): ?>
                                            <?php $permissionId = (int) $permission['id']; $inputId = 'user-permission-' . $permissionId; ?>
                                            <div class="d-flex align-items-center py-2 <?= $permissionIndex < count($items) - 1 ? 'border-bottom' : '' ?>">
                                                <div class="pe-3">
                                                    <h6 class="font-14 font-600 mb-0"><?= e($permission['name']) ?></h6>
                                                    <small class="role-default-label opacity-50" data-permission-id="<?= $permissionId ?>"></small>
                                                </div>
                                                <div class="ms-auto me-3">
                                                    <div class="custom-control ios-switch scale-switch">
                                                        <input class="ios-input permission-checkbox" id="<?= $inputId ?>" type="checkbox" name="permissions[]" value="<?= $permissionId ?>" data-permission-id="<?= $permissionId ?>" <?= isset($selectedMap[$permissionId]) ? 'checked' : '' ?>>
                                                        <label class="custom-control-label" for="<?= $inputId ?>"></label>
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
                <a href="<?= site_url('pengguna') ?>" class="btn btn-m font-600 border-highlight color-highlight rounded-s">Batal</a>
                <button type="submit" class="btn btn-m font-600 gradient-highlight rounded-s"><i class="fa fa-save me-1"></i> Simpan Pengguna</button>
            </div>
        </div>
    </div>
<?= form_close() ?>

<script>
(function () {
    var roleDefaults = <?= json_encode($rolePermissionMap, JSON_UNESCAPED_SLASHES) ?>;
    var roleSelect = document.getElementById('role_id');
    var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.permission-checkbox'));

    function defaultsForRole() {
        return (roleDefaults[String(roleSelect.value)] || roleDefaults[roleSelect.value] || []).map(Number);
    }
    function updateDefaultLabels() {
        var defaults = defaultsForRole();
        document.querySelectorAll('.role-default-label').forEach(function (label) {
            var isDefault = defaults.indexOf(Number(label.dataset.permissionId)) !== -1;
            label.textContent = isDefault ? 'Bawaan peran: diizinkan' : 'Bawaan peran: tidak diizinkan';
            label.classList.toggle('text-success', isDefault);
            label.classList.toggle('text-muted', !isDefault);
        });
    }
    function isSuperAdmin() {
        return roleSelect.options[roleSelect.selectedIndex].dataset.roleSlug === 'super-admin';
    }
    function syncSuperAdminState() {
        var locked = isSuperAdmin();
        if (locked) checkboxes.forEach(function (box) { box.checked = true; });
        checkboxes.forEach(function (box) { box.disabled = locked; });
        document.getElementById('clearPermissions').disabled = locked;
        document.getElementById('superAdminNotice').classList.toggle('d-none', !locked);
    }
    function applyDefaults() {
        var defaults = defaultsForRole();
        checkboxes.forEach(function (box) { box.checked = defaults.indexOf(Number(box.value)) !== -1; });
    }
    roleSelect.addEventListener('change', function () {
        updateDefaultLabels();
        applyDefaults();
        syncSuperAdminState();
    });
    document.getElementById('applyRoleDefaults').addEventListener('click', applyDefaults);
    document.getElementById('clearPermissions').addEventListener('click', function () {
        checkboxes.forEach(function (box) { box.checked = false; });
    });
    document.querySelectorAll('.toggle-module').forEach(function (button) {
        button.addEventListener('click', function () {
            if (isSuperAdmin()) return;
            var moduleBoxes = Array.prototype.slice.call(button.closest('.permission-module').querySelectorAll('.permission-checkbox'));
            var selectAll = moduleBoxes.some(function (box) { return !box.checked; });
            moduleBoxes.forEach(function (box) { box.checked = selectAll; });
        });
    });
    var ownAccount = document.querySelector('[data-own-account="1"]');
    if (ownAccount) ownAccount.addEventListener('change', function () { if (!ownAccount.checked) ownAccount.checked = true; });
    updateDefaultLabels();
    syncSuperAdminState();
})();
</script>
