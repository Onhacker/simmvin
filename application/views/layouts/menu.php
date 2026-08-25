<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="card rounded-0 bg-6" data-card-height="150">
    <div class="card-top">
        <a href="#" class="close-menu float-end me-2 text-center mt-3 icon-40 notch-clear" aria-label="Tutup menu"><i class="fa fa-times color-white"></i></a>
    </div>
    <div class="card-bottom">
        <h1 class="color-white ps-3 mb-n1 font-28">MVIN</h1>
        <p class="mb-2 ps-3 font-12 color-white opacity-60">Manajemen Pelatihan Terpadu</p>
    </div>
    <div class="card-overlay bg-gradient"></div>
</div>

<div class="mt-4"></div>
<h6 class="menu-divider">Utama</h6>
<div class="list-group list-custom-small list-menu">
    <?php if ($this->Auth_model->can('dashboard.view')): ?>
        <a class="<?= nav_is('dashboard') ? 'active-nav' : '' ?>" href="<?= site_url('dashboard') ?>">
            <i class="fa fa-chart-pie gradient-blue color-white"></i><span>Dashboard</span><i class="fa fa-angle-right"></i>
        </a>
    <?php endif; ?>
    <?php if ($this->Auth_model->can('events.view')): ?>
        <a class="<?= nav_is('events') ? 'active-nav' : '' ?>" href="<?= site_url('event') ?>">
            <i class="fa fa-calendar-alt gradient-green color-white"></i><span>Event Pelatihan</span><i class="fa fa-angle-right"></i>
        </a>
    <?php endif; ?>
    <?php if ($this->Auth_model->can('registrations.view')): ?>
        <a class="<?= nav_is('registrations') ? 'active-nav' : '' ?>" href="<?= site_url('registrasi') ?>">
            <i class="fa fa-user-plus gradient-magenta color-white"></i><span>Registrasi</span><i class="fa fa-angle-right"></i>
        </a>
    <?php endif; ?>
</div>

<h6 class="menu-divider mt-4">Keuangan</h6>
<div class="list-group list-custom-small list-menu">
    <?php if ($this->Auth_model->can('reports.income')): ?>
        <a class="<?= nav_is('reports') && $this->router->fetch_method() === 'income' ? 'active-nav' : '' ?>" href="<?= site_url('laporan/pemasukan') ?>">
            <i class="fa fa-chart-line gradient-green color-white"></i><span>Laporan Pemasukan</span><i class="fa fa-angle-right"></i>
        </a>
    <?php endif; ?>
    <?php if ($this->Auth_model->can('reports.finance')): ?>
        <a class="<?= nav_is('reports') && $this->router->fetch_method() === 'finance' ? 'active-nav' : '' ?>" href="<?= site_url('laporan/keuangan') ?>">
            <i class="fa fa-chart-bar gradient-blue color-white"></i><span>Laporan Keuangan</span><i class="fa fa-angle-right"></i>
        </a>
    <?php endif; ?>
    <?php if ($this->Auth_model->can('expenses.view')): ?>
        <a class="<?= nav_is('expenses') ? 'active-nav' : '' ?>" href="<?= site_url('pengeluaran') ?>">
            <i class="fa fa-receipt gradient-red color-white"></i><span>Pengeluaran</span><i class="fa fa-angle-right"></i>
        </a>
    <?php endif; ?>
    <?php if ($this->Auth_model->can('debts.view')): ?>
        <a class="<?= nav_is('debts') ? 'active-nav' : '' ?>" href="<?= site_url('hutang') ?>">
            <i class="fa fa-file-invoice-dollar gradient-brown color-white"></i><span>Hutang Perusahaan</span><i class="fa fa-angle-right"></i>
        </a>
    <?php endif; ?>
    <?php if ($this->Auth_model->can('transfers.view')): ?>
        <a class="<?= nav_is('transfers') ? 'active-nav' : '' ?>" href="<?= site_url('transfer-dana') ?>">
            <i class="fa fa-exchange-alt gradient-orange color-white"></i><span>Transfer Dana</span><i class="fa fa-angle-right"></i>
        </a>
    <?php endif; ?>
    <?php if ($this->Auth_model->can('accounts.view')): ?>
        <a class="<?= nav_is('accounts') ? 'active-nav' : '' ?>" href="<?= site_url('akun-dana') ?>">
            <i class="fa fa-wallet gradient-teal color-white"></i><span>Kas & Rekening</span><i class="fa fa-angle-right"></i>
        </a>
    <?php endif; ?>
</div>

<?php if ($this->Auth_model->can('positions.view') || $this->Auth_model->can('users.view') || $this->Auth_model->can('roles.manage') || $this->Auth_model->can('audit.view')): ?>
    <h6 class="menu-divider mt-4">Administrasi</h6>
    <div class="list-group list-custom-small list-menu">
        <?php if ($this->Auth_model->can('positions.view')): ?>
            <a class="<?= nav_is('positions') ? 'active-nav' : '' ?>" href="<?= site_url('jabatan') ?>">
                <i class="fa fa-id-badge gradient-teal color-white"></i><span>Master Jabatan</span><i class="fa fa-angle-right"></i>
            </a>
        <?php endif; ?>
        <?php if ($this->Auth_model->can('users.view')): ?>
            <a class="<?= nav_is('users') ? 'active-nav' : '' ?>" href="<?= site_url('pengguna') ?>">
                <i class="fa fa-users-cog gradient-blue color-white"></i><span>Pengguna</span><i class="fa fa-angle-right"></i>
            </a>
        <?php endif; ?>
        <?php if ($this->Auth_model->can('roles.manage')): ?>
            <a class="<?= nav_is('roles') ? 'active-nav' : '' ?>" href="<?= site_url('peran') ?>">
                <i class="fa fa-user-shield gradient-brown color-white"></i><span>Peran & Hak Akses</span><i class="fa fa-angle-right"></i>
            </a>
        <?php endif; ?>
        <?php if ($this->Auth_model->can('audit.view')): ?>
            <a class="<?= nav_is('audit') ? 'active-nav' : '' ?>" href="<?= site_url('audit') ?>">
                <i class="fa fa-history gradient-magenta color-white"></i><span>Audit Trail</span><i class="fa fa-angle-right"></i>
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<h6 class="menu-divider mt-4">Akun</h6>
<div class="list-group list-custom-small list-menu">
    <a href="#" data-toggle-theme data-trigger-switch="switch-dark-mode">
        <i class="fa fa-moon gradient-dark color-white"></i><span>Mode Gelap</span>
        <div class="custom-control small-switch ios-switch">
            <input data-toggle-theme type="checkbox" class="ios-input" id="switch-dark-mode">
            <label class="custom-control-label" for="switch-dark-mode"></label>
        </div>
    </a>
    <form method="post" action="<?= site_url('logout') ?>" class="logout-form" data-confirm="Keluar dari aplikasi sekarang?" data-confirm-button="Ya, Keluar" data-confirm-tone="danger">
        <?= csrf_field() ?>
        <button type="submit" class="logout-menu-item">
            <i class="fa fa-sign-out-alt gradient-red color-white"></i><span>Keluar</span><i class="fa fa-angle-right"></i>
        </button>
    </form>
</div>

<h6 class="menu-divider font-10 mt-4">
    Masuk sebagai <?= e($currentUser['name']) ?> &middot; <?= e($currentUser['role_name']) ?>
</h6>
