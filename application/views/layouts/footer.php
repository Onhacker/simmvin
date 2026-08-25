<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="card card-style">
    <h4 class="font-24 text-center color-theme font-800 pt-3 mt-3">MVIN</h4>
    <p class="boxed-text-l mb-4">Sistem Informasi Manajemen Pelatihan dan keuangan dalam satu aplikasi.</p>
    <div class="text-center mb-4">
        <a href="<?= site_url('dashboard') ?>" class="icon icon-xs rounded-sm shadow-l me-1 bg-blue-dark color-white" aria-label="Dashboard"><i class="fa fa-home"></i></a>
        <?php if ($this->Auth_model->can('events.view')): ?><a href="<?= site_url('event') ?>" class="icon icon-xs rounded-sm shadow-l me-1 bg-green-dark color-white" aria-label="Event"><i class="fa fa-calendar-alt"></i></a><?php endif; ?>
        <?php if ($this->Auth_model->can('accounts.view')): ?><a href="<?= site_url('akun-dana') ?>" class="icon icon-xs rounded-sm shadow-l me-1 bg-orange-dark color-white" aria-label="Kas dan rekening"><i class="fa fa-wallet"></i></a><?php endif; ?>
        <a href="#" class="back-to-top icon icon-xs rounded-sm shadow-l bg-highlight color-white" aria-label="Kembali ke atas"><i class="fa fa-arrow-up"></i></a>
    </div>
    <div class="divider mb-3"></div>
    <?php $this->load->view('layouts/pwa_install'); ?>
    <div class="divider mb-3 mt-4"></div>
    <p class="font-11 text-center mb-3 opacity-60">&copy; <?= date('Y') ?> MVIN &middot; Manajemen Pelatihan</p>
</div>
