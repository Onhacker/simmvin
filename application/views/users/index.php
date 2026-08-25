<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$roles = isset($roles) ? $roles : array();
$permissions = isset($permissions) ? $permissions : array();
$rolePermissionMap = isset($rolePermissionMap) ? $rolePermissionMap : array();
$userPermissionMap = isset($userPermissionMap) ? $userPermissionMap : array();
$permissionGroups = array();
foreach ($permissions as $permission) $permissionGroups[$permission['module']][] = $permission;
?>

<div id="user-content">
    <div class="card card-style">
        <div class="content mb-3">
            <div class="d-flex align-items-start flex-column flex-sm-row">
                <div>
                    <p class="color-highlight font-600 mb-n1">Administrasi Akun</p>
                    <h2 class="font-24 font-800 mb-1">Pengguna</h2>
                    <p class="mb-0">Kelola akun, peran, dan akses khusus setiap pengguna.</p>
                </div>
                <?php if ($canManage): ?>
                    <button type="button" class="btn btn-s font-600 gradient-highlight rounded-s mt-3 mt-sm-0 ms-sm-auto"
                            data-user-modal-open data-mode="create" data-action="<?= site_url('pengguna/ajax/tambah') ?>">
                        <i class="fa fa-plus me-1"></i> Tambah Pengguna
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card card-style">
        <div class="content mb-2">
            <div class="d-flex align-items-center mb-3">
                <div>
                    <p class="color-highlight font-600 mb-n1">Daftar Aktif dan Nonaktif</p>
                    <h3 class="font-20 mb-0"><?= number_format(count($users)) ?> Pengguna</h3>
                </div>
                <i class="fa fa-users color-highlight font-30 opacity-30 ms-auto"></i>
            </div>

            <?php if (!$users): ?>
                <div class="text-center py-4 opacity-50"><i class="fa fa-user-slash fa-3x mb-3"></i><h4>Belum ada pengguna.</h4></div>
            <?php else: ?>
                <div class="list-group list-custom-large">
                    <?php foreach ($users as $userIndex => $item): ?>
                        <?php
                        $userPayload = array(
                            'id' => (int) $item['id'],
                            'role_id' => (int) $item['role_id'],
                            'name' => $item['name'],
                            'username' => $item['username'],
                            'email' => $item['email'],
                            'phone' => $item['phone'],
                            'is_active' => (int) $item['is_active'],
                            'permission_ids' => isset($userPermissionMap[(int) $item['id']]) ? array_map('intval', $userPermissionMap[(int) $item['id']]) : array()
                        );
                        ?>
                        <a href="<?= $canManage ? site_url('pengguna/' . (int) $item['id'] . '/ubah') : '#' ?>"
                           class="<?= $userIndex === count($users) - 1 ? 'border-0' : '' ?>"
                           <?php if ($canManage): ?>
                               data-user-modal-open data-mode="edit"
                               data-action="<?= site_url('pengguna/' . (int) $item['id'] . '/ajax/ubah') ?>"
                               data-user="<?= e(json_encode($userPayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>"
                           <?php endif; ?> >
                            <i class="fa fa-user rounded-xl shadow-s <?= (int) $item['is_active'] === 1 ? 'bg-green-dark' : 'bg-gray-dark' ?> color-white"></i>
                            <span><?= e($item['name']) ?></span>
                            <strong>
                                @<?= e($item['username']) ?> · <?= e($item['role_name']) ?><br>
                                <?= e($item['email'] ?: ($item['phone'] ?: 'Tanpa kontak')) ?> · Login <?= e(tanggal_id($item['last_login_at'], TRUE)) ?>
                            </strong>
                            <span class="badge <?= (int) $item['is_active'] === 1 ? 'bg-green-dark' : 'bg-gray-dark' ?> color-white font-10"><?= (int) $item['is_active'] === 1 ? 'AKTIF' : 'NONAKTIF' ?></span>
                            <?php if ($canManage): ?><i class="fa fa-angle-right"></i><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
    <a id="user-modal-opener" href="#" class="d-none" data-menu="user-modal" aria-hidden="true" tabindex="-1"></a>
    <div id="user-modal" class="menu menu-box-modal rounded-m" data-menu-width="430" data-menu-height="760" role="dialog" aria-modal="true" aria-labelledby="user-modal-title">
        <div class="content mb-0">
            <div class="d-flex align-items-start mb-3">
                <div><p class="font-600 color-highlight mb-n1">Administrasi akun</p><h3 id="user-modal-title" class="font-20 mb-0">Tambah Pengguna</h3></div>
                <button type="button" class="close-menu btn btn-xxs bg-theme color-theme border rounded-s ms-auto" aria-label="Tutup"><i class="fa fa-times"></i></button>
            </div>
            <form id="user-modal-form" method="post" action="<?= site_url('pengguna/ajax/tambah') ?>" autocomplete="off">
                <?= csrf_field() ?>
                <div class="input-style input-style-always-active has-borders has-icon mb-3">
                    <i class="fa fa-user color-highlight"></i><input class="form-control" id="user-modal-name" name="name" maxlength="120" required placeholder="Nama lengkap">
                    <label for="user-modal-name" class="color-highlight">Nama Lengkap</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>(wajib)</em>
                </div>
                <div class="row mb-0">
                    <div class="col-12"><div class="input-style input-style-always-active has-borders has-icon mb-3">
                        <i class="fa fa-at color-highlight"></i><input class="form-control" id="user-modal-username" name="username" maxlength="80" required placeholder="username">
                        <label for="user-modal-username" class="color-highlight">Username</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>(wajib)</em>
                    </div></div>
                    <div class="col-12"><div class="input-style input-style-always-active has-borders no-icon mb-3">
                        <input class="form-control" id="user-modal-phone" name="phone" maxlength="30" inputmode="tel" placeholder="08xxxxxxxxxx">
                        <label for="user-modal-phone" class="color-highlight">No. HP</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>(opsional)</em>
                    </div></div>
                </div>
                <div class="input-style input-style-always-active has-borders no-icon mb-3">
                    <label for="user-modal-email" class="color-highlight">Email</label><input type="email" class="form-control" id="user-modal-email" name="email" maxlength="160" placeholder="nama@email.com">
                    <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>(opsional)</em>
                </div>
                <div class="input-style input-style-always-active has-borders no-icon mb-3">
                    <label for="user-modal-role" class="color-highlight">Peran</label>
                    <select class="form-select" id="user-modal-role" name="role_id" required>
                        <?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>" data-role-slug="<?= e($role['slug']) ?>"><?= e($role['name']) ?></option><?php endforeach; ?>
                    </select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>(wajib)</em>
                </div>
                <div class="row mb-0">
                    <div class="col-12"><div class="input-style input-style-always-active has-borders no-icon mb-3">
                        <input type="password" class="form-control" id="user-modal-password" name="password" minlength="8" maxlength="200" autocomplete="new-password" placeholder="Minimal 8 karakter">
                        <label for="user-modal-password" class="color-highlight">Kata Sandi</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em data-user-password-required>(wajib)</em>
                    </div></div>
                    <div class="col-12"><div class="input-style input-style-always-active has-borders no-icon mb-3">
                        <input type="password" class="form-control" id="user-modal-confirmation" name="password_confirmation" minlength="8" maxlength="200" autocomplete="new-password" placeholder="Ulangi kata sandi">
                        <label for="user-modal-confirmation" class="color-highlight">Konfirmasi</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em data-user-password-required>(wajib)</em>
                    </div></div>
                </div>
                <div class="d-flex align-items-center rounded-s bg-blue-light px-3 py-2 mb-3">
                    <div><strong class="font-13 color-blue-dark">Akun aktif</strong><p class="font-10 color-blue-dark mb-0">Pengguna dapat masuk ke aplikasi.</p></div>
                    <div class="custom-control ios-switch scale-switch ms-auto me-2"><input type="checkbox" class="ios-input" id="user-modal-active" name="is_active" value="1" checked><label class="custom-control-label" for="user-modal-active"></label></div>
                </div>
                <div class="d-flex align-items-center mb-2"><div><p class="font-600 color-highlight mb-n1">Izin khusus</p><h4 class="font-16 mb-0">Hak Akses Efektif</h4></div><button type="button" class="btn btn-xxs border-highlight color-highlight rounded-s ms-auto" id="user-modal-role-defaults">Bawaan Peran</button></div>
                <p class="font-10 opacity-60 mb-2">Izin ini disimpan khusus untuk pengguna. Super Admin otomatis mendapat semua izin.</p>
                <div id="user-modal-permissions" class="mb-3">
                    <?php foreach ($permissionGroups as $module => $items): ?>
                        <div class="card card-style mx-0 mb-2 shadow-0 border rounded-s">
                            <div class="content my-2"><div class="d-flex align-items-center"><strong class="font-12"><?= e($module) ?></strong><button type="button" class="btn btn-link btn-xs color-highlight ms-auto p-0 user-modal-toggle-module">Pilih semua</button></div>
                                <?php foreach ($items as $permission): ?><div class="d-flex align-items-center py-1 border-top user-modal-permission-row"><span class="font-11"><?= e($permission['name']) ?></span><div class="custom-control ios-switch scale-switch ms-auto me-1"><input class="ios-input user-modal-permission" type="checkbox" name="permissions[]" value="<?= (int) $permission['id'] ?>" data-permission-id="<?= (int) $permission['id'] ?>"><label class="custom-control-label"></label></div></div><?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="row mb-0"><div class="col-5 pe-1"><button type="button" class="close-menu btn btn-full btn-m bg-theme color-theme border rounded-s font-600">Batal</button></div><div class="col-7 ps-1"><button type="submit" class="btn btn-full btn-m gradient-highlight rounded-s font-600" data-user-modal-submit><i class="fa fa-save me-1"></i>Simpan Pengguna</button></div></div>
            </form>
        </div>
    </div>
    <script>window.USER_MODAL_CONFIG={roleDefaults:<?= json_encode($rolePermissionMap, JSON_UNESCAPED_SLASHES) ?>,permissionIds:<?= json_encode(array_map('intval', array_column($permissions, 'id'))) ?>};</script>
<?php endif; ?>
