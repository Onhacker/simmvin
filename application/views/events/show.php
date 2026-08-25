<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$billingLabel = 'Per Desa';
$billingAmount = rupiah($event['village_fee']);
$billingDetail = '';
if ($event['billing_mode'] === 'per_participant') {
    $billingLabel = 'Per Peserta';
    $billingAmount = rupiah($event['participant_fee']) . ' / orang';
} elseif ($event['billing_mode'] === 'per_village_extra') {
    $includedParticipantCount = isset($event['included_participant_count']) ? (int) $event['included_participant_count'] : 0;
    $billingLabel = 'Paket Desa + Peserta Tambahan';
    $billingAmount = rupiah($event['village_fee']) . ' / desa';
    $billingDetail = 'Termasuk ' . number_format($includedParticipantCount) . ' peserta · tambahan ' . rupiah($event['participant_fee']) . ' / orang';
}
?>

<div id="event-detail-content">
<div class="card card-style">
    <div class="card mb-0 rounded-0 bg-24 event-detail-hero" data-card-height="250" style="height:250px;min-height:250px">
        <div class="card-bottom ps-3 pe-3 pb-3">
            <p class="color-white opacity-70 font-600 mb-n1"><?= e($event['code']) ?> · <?= tanggal_id($event['start_date']) ?> s.d. <?= tanggal_id($event['end_date']) ?></p>
            <h1 class="color-white font-30 font-800 mb-1"><?= e($event['name']) ?></h1>
            <p class="color-white mb-0"><i class="fa fa-map-marker-alt icon-20"></i><?= e($event['location']) ?></p>
        </div>
        <div class="card-overlay bg-black opacity-70"></div>
    </div>
    <div class="content mb-3">
        <div class="d-flex align-items-start mb-3 event-detail-summary-head">
            <div class="min-width-zero pe-2">
                <div class="mb-2"><?= status_badge($event['status']) ?></div>
                <p class="font-600 color-highlight mb-n1">Ringkasan Pelaksanaan</p>
                <h2 class="font-22 mb-0">Informasi Event</h2>
            </div>
            <div class="ms-auto d-flex gap-2 flex-shrink-0">
                <a class="icon icon-s rounded-xl bg-theme color-theme shadow-xl" href="<?= site_url('event') ?>" aria-label="Kembali"><i class="fa fa-arrow-left"></i></a>
                <?php if ($this->Auth_model->can('events.edit')): ?>
                    <a class="icon icon-s rounded-xl gradient-highlight color-white shadow-xl" href="<?= site_url('event/' . $event['id'] . '/ubah') ?>" aria-label="Ubah event"><i class="fa fa-edit"></i></a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($this->Auth_model->can('events.activate') && in_array($event['status'], array('draft', 'open'), TRUE)): ?>
            <div class="d-flex align-items-center rounded-s <?= $event['status'] === 'draft' ? 'bg-green-light' : 'bg-yellow-light' ?> px-3 py-3 mb-4">
                <i class="fa <?= $event['status'] === 'draft' ? 'fa-play-circle color-green-dark' : 'fa-stop-circle color-yellow-dark' ?> font-24 me-3"></i>
                <div class="me-3">
                    <h5 class="font-14 mb-0"><?= $event['status'] === 'draft' ? 'Event belum aktif' : 'Event sedang aktif' ?></h5>
                    <p class="font-11 mb-0 opacity-70"><?= $event['status'] === 'draft' ? 'Aktifkan setelah tanggal, biaya, lokasi, dan wilayah telah diperiksa.' : 'Event tampil pada modul registrasi dan dapat menerima peserta baru.' ?></p>
                </div>
                <?php if ($event['status'] === 'draft'): ?>
                    <form method="post" action="<?= site_url('event/' . $event['id'] . '/aktifkan') ?>" class="ms-auto" data-event-status-ajax data-confirm="Aktifkan event ini? Setelah aktif, registrasi peserta dapat dilakukan." data-confirm-button="Ya, Aktifkan" data-confirm-tone="success">
                        <?= csrf_field() ?>
                        <button class="btn btn-s bg-green-dark color-white rounded-s font-600 text-nowrap" type="submit"><i class="fa fa-play me-1"></i> Aktifkan</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= site_url('event/' . $event['id'] . '/tutup') ?>" class="ms-auto" data-event-status-ajax data-confirm="Tutup event ini? Registrasi dan penambahan peserta baru akan dihentikan." data-confirm-button="Ya, Tutup" data-confirm-tone="warning">
                        <?= csrf_field() ?>
                        <button class="btn btn-s bg-yellow-dark color-white rounded-s font-600 text-nowrap" type="submit"><i class="fa fa-stop me-1"></i> Tutup</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php elseif ($event['status'] === 'closed'): ?>
            <div class="d-flex align-items-center rounded-s bg-gray-light px-3 py-3 mb-4">
                <i class="fa fa-lock color-gray-dark font-24 me-3"></i>
                <div class="me-3"><h5 class="font-14 mb-0">Event telah ditutup</h5><p class="font-11 mb-0 opacity-70">Registrasi baru dihentikan; data peserta dan transaksi tetap tersimpan.</p></div>
                <?php if ($this->Auth_model->can('events.activate')): ?>
                    <form method="post" action="<?= site_url('event/' . $event['id'] . '/aktifkan') ?>" class="ms-auto" data-event-status-ajax data-confirm="Buka kembali event ini? Event akan muncul lagi pada registrasi dan dapat menerima peserta." data-confirm-button="Ya, Buka" data-confirm-tone="success">
                        <?= csrf_field() ?>
                        <button class="btn btn-s bg-green-dark color-white rounded-s font-600 text-nowrap" type="submit"><i class="fa fa-redo me-1"></i> Buka Kembali</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php $archiveVillageCount = isset($event['summary']['archive_village_count'])
            ? (int) $event['summary']['archive_village_count']
            : (int) ($event['summary']['village_count'] ?? 0); ?>
        <?php if ($this->Auth_model->can('registrations.view') && $archiveVillageCount > 0): ?>
            <a class="btn btn-full btn-m bg-theme color-blue-dark border-blue-dark rounded-s font-600 mb-4" href="<?= site_url('event/' . (int) $event['id'] . '/registrasi') ?>">
                <i class="fa fa-users me-2"></i>Lihat Arsip Registrasi &amp; Peserta
                <span class="badge bg-blue-dark color-white ms-2"><?= number_format($archiveVillageCount) ?> desa</span>
            </a>
        <?php endif; ?>

        <div class="row mb-0">
            <div class="col-md-4 mb-3">
                <div class="d-flex">
                    <span class="icon icon-m rounded-xl bg-blue-dark color-white me-3 align-self-start flex-shrink-0"><i class="fa fa-calendar-alt"></i></span>
                    <div><p class="font-10 opacity-50 text-uppercase mb-n1">Pelaksanaan</p><h5 class="font-14 mb-0"><?= tanggal_id($event['start_date']) ?><br><span class="font-11 opacity-60">s.d. <?= tanggal_id($event['end_date']) ?></span></h5></div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="d-flex">
                    <span class="icon icon-m rounded-xl bg-green-dark color-white me-3 align-self-start flex-shrink-0"><i class="fa fa-wallet"></i></span>
                    <div>
                        <p class="font-10 opacity-50 text-uppercase mb-n1">Tagihan</p>
                        <h5 class="font-14 mb-0"><?= e($billingLabel) ?><br><span class="font-11 color-highlight"><?= e($billingAmount) ?></span></h5>
                        <?php if ($billingDetail !== ''): ?><p class="font-10 opacity-60 mb-0"><?= e($billingDetail) ?></p><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="d-flex">
                    <span class="icon icon-m rounded-xl bg-yellow-dark color-white me-3 align-self-start flex-shrink-0"><i class="fa fa-map"></i></span>
                    <div><p class="font-10 opacity-50 text-uppercase mb-n1">Kabupaten/Kota</p><h3 class="mb-0"><?= number_format(count($event['regencies'])) ?></h3></div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="d-flex">
                    <span class="icon icon-m rounded-xl bg-brown-dark color-white me-3 align-self-start flex-shrink-0"><i class="fa fa-home"></i></span>
                    <div><p class="font-10 opacity-50 text-uppercase mb-n1">Desa Terdaftar</p><h3 class="mb-0"><?= number_format($event['summary']['village_count']) ?></h3></div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="d-flex">
                    <span class="icon icon-m rounded-xl bg-magenta-dark color-white me-3 align-self-start flex-shrink-0"><i class="fa fa-users"></i></span>
                    <div><p class="font-10 opacity-50 text-uppercase mb-n1">Peserta</p><h3 class="mb-0"><?= number_format($event['summary']['participant_count']) ?></h3></div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="d-flex">
                    <span class="icon icon-m rounded-xl gradient-highlight color-white me-3 align-self-start flex-shrink-0"><i class="fa fa-coins"></i></span>
                    <div><p class="font-10 opacity-50 text-uppercase mb-n1">Dana Masuk</p><h5 class="font-16 mb-0 color-green-dark"><?= rupiah($event['summary']['verified_income']) ?></h5></div>
                </div>
            </div>
        </div>

        <div class="divider mt-2 mb-3"></div>
        <p class="font-600 color-highlight mb-2">Wilayah Sasaran</p>
        <div>
            <?php foreach ($event['regencies'] as $region): ?>
                <span class="chip chip-s bg-gray-light mb-2 me-1"><i class="fa fa-map-marker-alt bg-highlight color-white"></i><strong class="color-black font-400"><?= e($region['province_name'] . ' · ' . $region['regency_name']) ?></strong></span>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($event['address']) || !empty($event['notes'])): ?>
            <div class="divider mt-3 mb-3"></div>
            <?php if (!empty($event['address'])): ?><p class="mb-2"><i class="fa fa-map-pin icon-30 color-highlight"></i><strong>Alamat:</strong> <?= nl2br(e($event['address'])) ?></p><?php endif; ?>
            <?php if (!empty($event['notes'])): ?><p class="mb-0"><i class="fa fa-sticky-note icon-30 color-highlight"></i><strong>Catatan:</strong> <?= nl2br(e($event['notes'])) ?></p><?php endif; ?>
        <?php endif; ?>
    </div>
</div>

</div>
