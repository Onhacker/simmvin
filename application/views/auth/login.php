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
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#4A89DC">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&amp;display=swap">
    <link rel="stylesheet" type="text/css" href="<?= base_url('assets/v22/styles/bootstrap.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('assets/v22/fonts/css/fontawesome-all.min.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('assets/css/simp-v22.css') ?>?v=48">
    <link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= base_url('assets/pwa/icon-192.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= base_url('assets/pwa/icon-180.png') ?>">
</head>
<body class="theme-light" data-highlight="highlight-blue" data-base-url="<?= e(base_url()) ?>" data-csrf-name="<?= e($this->security->get_csrf_token_name()) ?>" data-csrf-hash="<?= e($this->security->get_csrf_hash()) ?>">
<div id="preloader"><div class="spinner-border color-highlight" role="status"><span class="visually-hidden">Memuat</span></div></div>
<div id="page">
    <div class="header header-fixed header-logo-center">
        <a href="<?= site_url('login') ?>" class="header-title">MVIN</a>
        <a href="#" data-toggle-theme class="header-icon header-icon-4 show-on-theme-dark" aria-label="Mode terang"><i class="fas fa-sun"></i></a>
        <a href="#" data-toggle-theme class="header-icon header-icon-4 show-on-theme-light" aria-label="Mode gelap"><i class="fas fa-moon"></i></a>
    </div>

    <div id="footer-bar" class="footer-bar-6">
        <a class="active-nav" href="<?= site_url('login') ?>"><i class="fa fa-sign-in-alt"></i><span>Masuk</span></a>
        <a href="<?= site_url('login') ?>#bantuan"><i class="fa fa-layer-group"></i><span>Fitur</span></a>
        <a class="circle-nav" href="<?= site_url('login') ?>"><i class="fa fa-graduation-cap"></i><span>MVIN</span></a>
        <a href="<?= site_url('login') ?>#bantuan"><i class="fa fa-question-circle"></i><span>Bantuan</span></a>
        <a href="#" data-toggle-theme><i class="fa fa-moon"></i><span>Tema</span></a>
    </div>

    <main class="page-content header-clear-medium">
        <div class="card card-style bg-11" data-card-height="170">
            <div class="card-bottom ps-3 pb-3">
                <h1 class="font-24 color-white mb-n1">MVIN</h1>
                <p class="color-white opacity-70 mb-0">Sistem Informasi Manajemen Pelatihan</p>
            </div>
            <div class="card-overlay bg-black opacity-70"></div>
        </div>

        <div class="card card-style">
            <div class="content">
                <p class="font-600 color-highlight mb-n1">Selamat datang</p>
                <h1 class="font-30">Masuk</h1>
                <p>Gunakan akun yang telah diberikan administrator untuk mengakses aplikasi.</p>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-small rounded-s shadow-xl bg-red-dark" role="alert">
                        <span><i class="fa fa-times color-white"></i></span><strong class="color-white"><?= e($error) ?></strong>
                        <button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button>
                    </div>
                <?php endif; ?>
                <?php if (validation_errors()): ?>
                    <div class="alert alert-small rounded-s shadow-xl bg-red-dark" role="alert">
                        <span><i class="fa fa-times color-white"></i></span><strong class="color-white"><?= validation_errors('<span class="d-block">', '</span>') ?></strong>
                        <button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button>
                    </div>
                <?php endif; ?>

                <div id="login-alert" class="alert alert-small rounded-s shadow-xl bg-red-dark d-none" role="alert">
                    <span><i class="fa fa-times color-white"></i></span>
                    <strong id="login-alert-message" class="color-white"></strong>
                    <button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button>
                </div>
                <form method="post" action="<?= site_url('login') ?>" autocomplete="on" id="login-form" data-login-ajax="1">
                    <?= csrf_field() ?>
                    <div class="input-style no-borders has-icon validate-field mb-4">
                        <i class="fa fa-user"></i>
                        <input type="text" class="form-control validate-name" id="login-identity" name="identity" value="<?= e(old('identity')) ?>" placeholder="Username atau Email" required autofocus autocomplete="username">
                        <label for="login-identity" class="color-highlight">Username atau Email</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <em>*</em>
                    </div>
                    <div class="input-style no-borders has-icon validate-field mb-4">
                        <i class="fa fa-lock"></i>
                        <input type="password" class="form-control validate-password" id="login-password" name="password" placeholder="Kata Sandi" required autocomplete="current-password">
                        <label for="login-password" class="color-highlight">Kata Sandi</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <em>*</em>
                    </div>
                    <button class="btn btn-full btn-l font-600 font-13 gradient-highlight mt-4 rounded-s" type="submit" id="login-submit">
                        Masuk <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </form>

                <div id="bantuan" class="divider mt-4"></div>
                <p class="text-center font-12 mb-0 opacity-60">Hubungi administrator apabila Anda belum memiliki akun.</p>
            </div>
        </div>

        <div class="card card-style simp-pwa-footer-card">
            <?php $this->load->view('layouts/pwa_install'); ?>
        </div>

    </main>
    <!-- AppKit dialog used by the AJAX login error state. Keep this markup
         static so custom.js can attach its menu listeners on page load. -->
    <a id="simp-dialog-opener" href="#" class="d-none" data-menu="menu-simp-dialog" aria-hidden="true" tabindex="-1"></a>
    <div id="menu-simp-dialog" class="menu menu-box-modal rounded-m" data-menu-height="300" data-menu-width="350" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="simp-dialog-title" aria-describedby="simp-dialog-message">
        <h1 class="text-center mt-4"><i id="simp-dialog-icon" class="fa fa-3x fa-exclamation-circle scale-box color-red-dark shadow-xl rounded-circle"></i></h1>
        <h3 id="simp-dialog-title" class="text-center mt-3 font-700">Login gagal</h3>
        <p id="simp-dialog-message" class="boxed-text-xl opacity-70">Username/email atau kata sandi tidak sesuai.</p>
        <div id="simp-dialog-confirm-actions" class="row mb-0 me-3 ms-3 d-none">
            <div class="col-6">
                <button id="simp-dialog-cancel" type="button" class="btn close-menu btn-full btn-m w-100 color-red-dark border-red-dark font-600 rounded-s">Batal</button>
            </div>
            <div class="col-6">
                <button id="simp-dialog-confirm" type="button" class="btn close-menu btn-full btn-m w-100 color-green-dark border-green-dark font-600 rounded-s">Lanjutkan</button>
            </div>
        </div>
        <div id="simp-dialog-alert-actions" class="me-3 ms-3">
            <button id="simp-dialog-ok" type="button" class="btn close-menu btn-full btn-m w-100 color-red-dark border-red-dark font-600 rounded-s">Mengerti</button>
        </div>
    </div>
    <div class="menu-hider"></div>
</div>
<script src="<?= base_url('assets/v22/scripts/bootstrap.min.js') ?>"></script>
<script src="<?= base_url('assets/v22/scripts/custom.js') ?>?v=3"></script>
<script>window.SIMP={baseUrl:<?= json_encode(base_url()) ?>,csrfName:<?= json_encode($this->security->get_csrf_token_name()) ?>,csrfHash:<?= json_encode($this->security->get_csrf_hash()) ?>,serviceWorkerUrl:<?= json_encode(base_url('service-worker.js')) ?>,serviceWorkerScope:<?= json_encode(base_url()) ?>};</script>
<script src="<?= base_url('assets/js/app.js') ?>?v=15"></script>
<script src="<?= base_url('assets/js/auth.js') ?>?v=3"></script>
</body>
</html>
