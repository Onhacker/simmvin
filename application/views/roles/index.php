<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$permissions = isset($permissions) ? $permissions : array();
$rolePermissionMap = isset($rolePermissionMap) ? $rolePermissionMap : array();
$permissionGroups = array();
foreach ($permissions as $permission) $permissionGroups[$permission['module']][] = $permission;
?>

<div id="role-content">
    <div class="card card-style">
        <div class="content mb-3">
            <div class="d-flex align-items-start flex-column flex-sm-row">
                <div>
                    <p class="color-highlight font-600 mb-n1">Kontrol Akses</p>
                    <h2 class="font-24 font-800 mb-1">Peran &amp; Hak Akses</h2>
                    <p class="mb-0">Atur izin bawaan untuk setiap jabatan dalam aplikasi.</p>
                </div>
                <a href="<?= site_url('pengguna') ?>" class="btn btn-s font-600 border-highlight color-highlight rounded-s mt-3 mt-sm-0 ms-sm-auto"><i class="fa fa-users me-1"></i> Pengguna</a>
            </div>
        </div>
    </div>

    <div class="row mb-0 mx-0">
        <?php $roleColors = array('gradient-blue', 'gradient-green', 'gradient-orange', 'gradient-red', 'gradient-magenta', 'gradient-teal'); ?>
        <?php $roleIcons = array('fa-crown', 'fa-user-tie', 'fa-briefcase', 'fa-user-shield', 'fa-users-cog', 'fa-user'); ?>
        <?php foreach ($roles as $roleIndex => $role): ?>
            <?php
            $rolePayload = array(
                'id' => (int) $role['id'],
                'name' => $role['name'],
                'slug' => $role['slug'],
                'description' => $role['description'],
                'is_system' => (int) $role['is_system'],
                'permission_ids' => isset($rolePermissionMap[(int) $role['id']]) ? array_map('intval', $rolePermissionMap[(int) $role['id']]) : array()
            );
            ?>
            <div class="col-md-6 col-xl-4">
                <div class="card card-style mx-0">
                    <div class="content">
                        <div class="d-flex align-items-start mb-3">
                            <span class="icon icon-l rounded-xl <?= $roleColors[$roleIndex % count($roleColors)] ?> color-white shadow-l me-3"><i class="fa <?= $roleIcons[$roleIndex % count($roleIcons)] ?> font-20"></i></span>
                            <div><h3 class="font-20 mb-n1"><?= e($role['name']) ?></h3><span class="font-10 color-highlight"><?= e($role['slug']) ?></span></div>
                            <?php if ((int) $role['is_system'] === 1): ?><span class="badge bg-gray-dark color-white ms-auto">Sistem</span><?php endif; ?>
                        </div>
                        <p class="opacity-60"><?= e($role['description'] ?: 'Tanpa deskripsi.') ?></p>
                        <div class="divider mb-3"></div>
                        <div class="d-flex mb-3"><div><p class="font-10 opacity-50 mb-n1">PENGGUNA</p><h3 class="mb-0"><?= (int) $role['user_count'] ?></h3></div><div class="ms-auto text-end"><p class="font-10 opacity-50 mb-n1">HAK AKSES</p><h3 class="mb-0 color-highlight"><?= (int) $role['permission_count'] ?></h3></div></div>
                        <button type="button" class="btn btn-full btn-m font-600 gradient-highlight rounded-s" data-role-modal-open data-action="<?= site_url('peran/' . (int) $role['id'] . '/ajax/ubah') ?>" data-role="<?= e(json_encode($rolePayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>"><i class="fa fa-shield-alt me-1"></i> Atur Hak Akses</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<a id="role-modal-opener" href="#" class="d-none" data-menu="role-modal" aria-hidden="true" tabindex="-1"></a>
<div id="role-modal" class="menu menu-box-modal rounded-m" data-menu-width="430" data-menu-height="760" role="dialog" aria-modal="true" aria-labelledby="role-modal-title">
    <div class="content mb-0">
        <div class="d-flex align-items-start mb-3">
            <div><p class="font-600 color-highlight mb-n1">Kontrol akses</p><h3 id="role-modal-title" class="font-20 mb-0">Atur Hak Akses</h3></div>
            <button type="button" class="close-menu btn btn-xxs bg-theme color-theme border rounded-s ms-auto" aria-label="Tutup"><i class="fa fa-times"></i></button>
        </div>
        <form id="role-modal-form" method="post" action="<?= site_url('peran/0/ajax/ubah') ?>">
            <?= csrf_field() ?>
            <div class="input-style input-style-always-active has-borders has-icon mb-3"><i class="fa fa-user-tag color-highlight"></i><input class="form-control" id="role-modal-name" name="name" maxlength="80" required><label for="role-modal-name" class="color-highlight">Nama Peran</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em data-role-identity-note>*</em></div>
            <div class="input-style input-style-always-active has-borders has-icon mb-3"><i class="fa fa-code color-highlight"></i><input class="form-control" id="role-modal-slug" name="slug" maxlength="80" required><label for="role-modal-slug" class="color-highlight">Kode Peran</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em data-role-identity-note>*</em></div>
            <div class="input-style input-style-always-active has-borders no-icon mb-3"><textarea class="form-control" id="role-modal-description" name="description" maxlength="255" rows="3" placeholder="Deskripsi singkat"></textarea><label for="role-modal-description" class="color-highlight">Deskripsi</label><i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em></em></div>
            <div class="d-flex align-items-center mb-2"><div><p class="font-600 color-highlight mb-n1">Izin bawaan</p><h4 class="font-16 mb-0">Matriks Hak Akses</h4></div><button type="button" class="btn btn-xxs border-highlight color-highlight rounded-s ms-auto" id="role-modal-clear">Kosongkan</button></div>
            <p class="font-10 opacity-60 mb-2" id="role-modal-system-note">Izin akan menjadi bawaan bagi semua pengguna dengan peran ini.</p>
            <div id="role-modal-permissions" class="mb-3">
                <?php foreach ($permissionGroups as $module => $items): ?>
                    <div class="card card-style mx-0 mb-2 shadow-0 border rounded-s"><div class="content my-2"><div class="d-flex align-items-center"><strong class="font-12"><?= e($module) ?></strong><button type="button" class="btn btn-link btn-xs color-highlight ms-auto p-0 role-modal-toggle-module">Pilih semua</button></div>
                        <?php foreach ($items as $permission): ?><div class="d-flex align-items-center py-1 border-top"><span class="font-11"><?= e($permission['name']) ?></span><div class="custom-control ios-switch scale-switch ms-auto me-1"><input class="ios-input role-modal-permission" type="checkbox" name="permissions[]" value="<?= (int) $permission['id'] ?>" data-permission-id="<?= (int) $permission['id'] ?>"><label class="custom-control-label"></label></div></div><?php endforeach; ?>
                    </div></div>
                <?php endforeach; ?>
            </div>
            <div class="row mb-0"><div class="col-5 pe-1"><button type="button" class="close-menu btn btn-full btn-m bg-theme color-theme border rounded-s font-600">Batal</button></div><div class="col-7 ps-1"><button type="submit" class="btn btn-full btn-m gradient-highlight rounded-s font-600" data-role-modal-submit><i class="fa fa-save me-1"></i>Simpan Peran</button></div></div>
        </form>
    </div>
</div>
<script>window.ROLE_MODAL_CONFIG={allPermissionIds:<?= json_encode(array_map('intval', array_column($permissions, 'id'))) ?>};</script>
