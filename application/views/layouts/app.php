<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE HTML>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MVIN">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="MVIN">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=10, user-scalable=yes, viewport-fit=cover">
    <meta name="theme-color" content="#4A89DC">
    <title><?= e($pageTitle) ?> | MVIN</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&amp;display=swap">
    <link rel="stylesheet" type="text/css" href="<?= base_url('assets/v22/styles/bootstrap.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('assets/v22/fonts/css/fontawesome-all.min.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('assets/css/simp-v22.css') ?>?v=45">
    <link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= base_url('assets/pwa/icon-192.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= base_url('assets/pwa/icon-180.png') ?>">
</head>
<body class="theme-light" data-highlight="highlight-blue" data-base-url="<?= e(base_url()) ?>" data-csrf-name="<?= e($this->security->get_csrf_token_name()) ?>" data-csrf-hash="<?= e($this->security->get_csrf_hash()) ?>">
<div id="preloader"><div class="spinner-border color-highlight" role="status"><span class="visually-hidden">Memuat</span></div></div>
<div id="page">
    <div class="header header-fixed header-logo-center header-auto-show">
        <a href="<?= site_url('dashboard') ?>" class="header-title"><?= e($pageTitle) ?></a>
        <a href="#" data-back-button class="header-icon header-icon-1" aria-label="Kembali"><i class="fas fa-chevron-left"></i></a>
        <a href="#" data-menu="menu-main" class="header-icon header-icon-4" aria-label="Buka menu"><i class="fas fa-bars"></i></a>
        <a href="#" data-toggle-theme class="header-icon header-icon-3 show-on-theme-dark" aria-label="Mode terang"><i class="fas fa-sun"></i></a>
        <a href="#" data-toggle-theme class="header-icon header-icon-3 show-on-theme-light" aria-label="Mode gelap"><i class="fas fa-moon"></i></a>
    </div>

    <div id="footer-bar" class="footer-bar-6">
        <?php $menuNavActive = nav_is(array('events', 'reports', 'payment_data', 'debts', 'transfers', 'positions', 'users', 'roles', 'audit')); ?>
        <a class="<?= nav_is('dashboard') ? 'active-nav' : '' ?>" href="<?= site_url('dashboard') ?>"><i class="fa fa-chart-pie"></i><span>Beranda</span></a>
        <?php if ($this->Auth_model->can('expenses.view')): ?>
            <a class="<?= nav_is('expenses') ? 'active-nav' : '' ?>" href="<?= site_url('pengeluaran') ?>"><i class="fa fa-receipt"></i><span>Pengeluaran</span></a>
        <?php else: ?>
            <a href="<?= site_url('dashboard') ?>"><i class="fa fa-receipt"></i><span>Pengeluaran</span></a>
        <?php endif; ?>
        <?php if ($this->Auth_model->can('registrations.view')): ?>
            <a class="circle-nav <?= nav_is('registrations') ? 'active-nav' : '' ?>" href="<?= site_url('registrasi') ?>"><i class="fa fa-users"></i><span>Registrasi</span></a>
        <?php elseif ($this->Auth_model->can('registrations.create')): ?>
            <a class="circle-nav <?= nav_is('registrations') ? 'active-nav' : '' ?>" href="<?= site_url('registrasi/tambah') ?>"><i class="fa fa-user-plus"></i><span>Daftar</span></a>
        <?php else: ?>
            <a class="circle-nav" href="<?= site_url('dashboard') ?>"><i class="fa fa-graduation-cap"></i><span>MVIN</span></a>
        <?php endif; ?>
        <?php if ($this->Auth_model->can('accounts.view')): ?>
            <a class="<?= nav_is('accounts') ? 'active-nav' : '' ?>" href="<?= site_url('akun-dana') ?>"><i class="fa fa-wallet"></i><span>Kas</span></a>
        <?php else: ?>
            <a href="<?= site_url('dashboard') ?>"><i class="fa fa-wallet"></i><span>Kas</span></a>
        <?php endif; ?>
        <a class="<?= $menuNavActive ? 'active-nav' : '' ?>" href="#" data-menu="menu-main"><i class="fa fa-bars"></i><span>Menu</span></a>
    </div>

    <div class="page-title page-title-fixed">
        <h1><?= e($pageTitle) ?></h1>
        <a href="#" class="page-title-icon shadow-xl bg-theme color-theme show-on-theme-light" data-toggle-theme aria-label="Mode gelap"><i class="fa fa-moon"></i></a>
        <a href="#" class="page-title-icon shadow-xl bg-theme color-theme show-on-theme-dark" data-toggle-theme aria-label="Mode terang"><i class="fa fa-lightbulb color-yellow-dark"></i></a>
        <a href="#" class="page-title-icon shadow-xl bg-theme color-theme" data-menu="menu-main" aria-label="Buka menu"><i class="fa fa-bars"></i></a>
    </div>
    <div class="page-title-clear"></div>

    <main class="page-content">
        <?php $flashSuccess = $this->session->flashdata('success'); ?>
        <?php $flashError = $this->session->flashdata('error'); ?>
        <?php if ($flashSuccess): ?>
            <div class="ms-3 me-3 alert alert-small rounded-s shadow-xl bg-green-dark" role="alert">
                <span><i class="fa fa-check color-white"></i></span>
                <strong class="color-white"><?= e($flashSuccess) ?></strong>
                <button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button>
            </div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="ms-3 me-3 alert alert-small rounded-s shadow-xl bg-red-dark" role="alert">
                <span><i class="fa fa-times color-white"></i></span>
                <strong class="color-white"><?= e($flashError) ?></strong>
                <button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button>
            </div>
        <?php endif; ?>
        <?php $this->load->view($contentView); ?>
        <?php $this->load->view('layouts/footer', array('currentUser' => $currentUser)); ?>
    </main>

    <div id="menu-main" class="menu menu-box-left rounded-0" data-menu-width="280">
        <?php $this->load->view('layouts/menu', array('currentUser' => $currentUser)); ?>
    </div>

    <a id="simp-dialog-opener" href="#" class="d-none" data-menu="menu-simp-dialog" aria-hidden="true" tabindex="-1"></a>
    <div id="menu-simp-dialog" class="menu menu-box-modal rounded-m" data-menu-height="300" data-menu-width="350" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="simp-dialog-title" aria-describedby="simp-dialog-message">
        <h1 class="text-center mt-4"><i id="simp-dialog-icon" class="fa fa-3x fa-question-circle scale-box color-blue-dark shadow-xl rounded-circle"></i></h1>
        <h3 id="simp-dialog-title" class="text-center mt-3 font-700">Konfirmasi</h3>
        <p id="simp-dialog-message" class="boxed-text-xl opacity-70">Silakan konfirmasi untuk melanjutkan.</p>
        <div id="simp-dialog-confirm-actions" class="row mb-0 me-3 ms-3">
            <div class="col-6">
                <button id="simp-dialog-cancel" type="button" class="btn close-menu btn-full btn-m w-100 color-red-dark border-red-dark font-600 rounded-s">Batal</button>
            </div>
            <div class="col-6">
                <button id="simp-dialog-confirm" type="button" class="btn close-menu btn-full btn-m w-100 color-green-dark border-green-dark font-600 rounded-s">Lanjutkan</button>
            </div>
        </div>
        <div id="simp-dialog-alert-actions" class="me-3 ms-3 d-none">
            <button id="simp-dialog-ok" type="button" class="btn close-menu btn-full btn-m w-100 color-blue-dark border-blue-dark font-600 rounded-s">Mengerti</button>
        </div>
    </div>
    <div class="menu-hider"></div>
</div>
<script src="<?= base_url('assets/v22/scripts/bootstrap.min.js') ?>"></script>
<script src="<?= base_url('assets/v22/scripts/custom.js') ?>?v=3"></script>
<script>window.SIMP={baseUrl:<?= json_encode(base_url()) ?>,csrfName:<?= json_encode($this->security->get_csrf_token_name()) ?>,csrfHash:<?= json_encode($this->security->get_csrf_hash()) ?>,serviceWorkerUrl:<?= json_encode(base_url('service-worker.js')) ?>,serviceWorkerScope:<?= json_encode(base_url()) ?>};</script>
<script src="<?= base_url('assets/js/app.js') ?>?v=14"></script>
<?php
$resolvedPageScripts = array();
if (!empty($pageScript)) $resolvedPageScripts[] = $pageScript;
if (!empty($pageScripts) && is_array($pageScripts)) $resolvedPageScripts = array_merge($resolvedPageScripts, $pageScripts);
$resolvedPageScripts = array_values(array_unique(array_filter($resolvedPageScripts)));
?>
<?php foreach ($resolvedPageScripts as $resolvedPageScript): ?>
    <script src="<?= base_url('assets/js/' . basename($resolvedPageScript)) ?>?v=43"></script>
<?php endforeach; ?>
</body>
</html>
