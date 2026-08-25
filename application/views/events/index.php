<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php if ($this->Auth_model->can('events.create')): ?>
    <div class="content d-flex justify-content-end mb-3">
        <a class="btn btn-s font-600 gradient-highlight rounded-s" href="<?= site_url('event/tambah') ?>">
            <i class="fas fa-plus me-1"></i> Tambah Event
        </a>
    </div>
<?php endif; ?>

<div class="card card-style">
    <div class="content mb-1">
        <p class="color-highlight font-600 mb-n1">Pencarian dan Status</p>
        <h3 class="font-20 mb-3">Filter Event</h3>
        <form method="get">
            <div class="row mb-0">
                <div class="col-md-8">
                    <div class="input-style input-style-always-active has-borders has-icon validate-field mb-4">
                        <i class="fa fa-search color-highlight"></i>
                        <input class="form-control" id="event-search" name="q" value="<?= e($filters['q']) ?>" placeholder="Nama, kode, lokasi, kabupaten">
                        <label for="event-search" class="color-highlight font-12 font-500">Cari Event</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em></em>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <label for="event-status" class="color-highlight font-12 font-500">Status</label>
                        <select class="form-select" id="event-status" name="status">
                            <option value="">Semua status</option>
                            <?php foreach (array('draft' => 'Draft', 'open' => 'Aktif', 'closed' => 'Ditutup', 'cancelled' => 'Dibatalkan') as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span><i class="fa fa-chevron-down"></i></span>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <em></em>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mb-3">
                <a class="btn btn-m font-600 border-highlight color-highlight rounded-s" href="<?= site_url('event') ?>">Reset</a>
                <button class="btn btn-m font-600 gradient-highlight rounded-s" type="submit"><i class="fa fa-filter me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<?php if (!$events): ?>
    <div class="card card-style">
        <div class="content text-center py-4">
            <i class="fa fa-calendar-times fa-3x color-highlight opacity-30 mb-3"></i>
            <h3>Belum Ada Event</h3>
            <p class="mb-0">Event yang dibuat akan tampil di halaman ini.</p>
        </div>
    </div>
<?php else: ?>
    <div class="content mb-0">
        <div class="d-flex align-items-center mb-3"><div><p class="color-highlight font-600 mb-n1">Data Event</p><h3 class="font-20 mb-0"><?= number_format(count($events)) ?> Event</h3></div></div>
        <div class="row mb-0">
        <?php $eventBackgrounds = array(11, 20, 17, 34); ?>
        <?php foreach ($events as $eventIndex => $event): ?>
            <div class="col-12 col-md-6 col-xl-4">
            <a href="<?= site_url('event/' . $event['id']) ?>" class="card card-style mx-0 bg-<?= $eventBackgrounds[$eventIndex % count($eventBackgrounds)] ?> default-link" data-card-height="240">
                <div class="card-top ps-3 pt-3">
                    <?= status_badge($event['status']) ?>
                </div>
                <div class="card-bottom ps-3 pe-3 pb-3">
                    <p class="color-white opacity-70 font-600 mb-n1"><?= e($event['code']) ?> · <?= tanggal_id($event['start_date']) ?></p>
                    <h2 class="color-white font-24 font-800 mb-1"><?= e($event['name']) ?></h2>
                    <p class="color-white mb-2"><i class="fa fa-map-marker-alt icon-20"></i><?= e($event['location']) ?></p>
                    <div class="d-flex color-white font-11 opacity-80">
                        <span><i class="fa fa-map icon-20"></i><?= number_format($event['regency_count']) ?> kab/kota</span>
                        <span class="ms-auto"><i class="fa fa-users icon-20"></i><?= number_format($event['registration_count']) ?> desa</span>
                    </div>
                </div>
                <div class="card-overlay bg-black opacity-70"></div>
            </a>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
