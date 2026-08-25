<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="card card-style">
    <div class="content mb-0">
        <p class="font-600 color-highlight mb-n1">Keamanan &amp; akuntabilitas</p>
        <h2>Filter Aktivitas</h2>
        <p class="font-12 opacity-60">Telusuri perubahan data, proses verifikasi, serta aktivitas pengguna.</p>

        <form method="get">
            <div class="row mb-0">
                <div class="col-lg-3 col-md-6">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <input class="form-control" id="audit-filter-action" name="action" value="<?= e($filters['action']) ?>" placeholder="Contoh: login, create">
                        <label for="audit-filter-action" class="color-highlight font-12 font-500">Aksi</label>
                        <i class="fa fa-times disabled invalid color-red-dark"></i>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <em>(opsional)</em>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <input class="form-control" id="audit-filter-from" type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
                        <label for="audit-filter-from" class="color-highlight font-12 font-500">Dari Tanggal</label>
                        <i class="fa fa-check disabled valid me-4 pe-3 font-12 color-green-dark"></i>
                        <i class="fa fa-times disabled invalid me-4 pe-3 font-12 color-red-dark"></i>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <input class="form-control" id="audit-filter-to" type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
                        <label for="audit-filter-to" class="color-highlight font-12 font-500">Sampai Tanggal</label>
                        <i class="fa fa-check disabled valid me-4 pe-3 font-12 color-green-dark"></i>
                        <i class="fa fa-times disabled invalid me-4 pe-3 font-12 color-red-dark"></i>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="row mb-0">
                        <div class="col-7 pe-1">
                            <button class="btn btn-full btn-m font-13 font-600 gradient-highlight rounded-s mb-4" type="submit">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                        </div>
                        <div class="col-5 ps-1">
                            <a class="btn btn-full btn-m font-13 font-600 bg-theme color-theme border rounded-s mb-4" href="<?= site_url('audit') ?>">Reset</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card card-style">
    <div class="content mb-2">
        <div class="d-flex align-items-center mb-3">
            <span class="icon icon-m rounded-xl gradient-blue color-white shadow-s me-3">
                <i class="fas fa-history"></i>
            </span>
            <div>
                <p class="font-600 color-highlight mb-n1">Jejak aktivitas sistem</p>
                <h2 class="mb-0">Aktivitas Sistem</h2>
            </div>
            <span class="badge bg-blue-dark color-white font-11 ms-auto"><?= number_format(count($logs)) ?> data</span>
        </div>

        <?php if(!$logs): ?><div class="text-center py-5 opacity-60"><i class="fas fa-history d-block font-24 mb-2"></i>Belum ada aktivitas pada filter ini.</div><?php endif; ?>
        <?php foreach($logs as $log): ?>
            <?php $details=$log['details_json']?json_decode($log['details_json'],TRUE):array(); ?>
            <div class="d-flex align-items-start py-3 border-bottom">
                <span class="icon icon-s rounded-xl bg-blue-light color-blue-dark me-3 flex-shrink-0"><i class="fas fa-history"></i></span>
                <div class="min-width-zero flex-grow-1">
                    <div class="d-flex align-items-start"><div class="min-width-zero"><h5 class="font-14 mb-n1"><?= e($log['user_name']?:'Sistem/Tamu') ?></h5><p class="font-11 opacity-60 mb-0"><?= e($log['username']?:'-') ?> · <?= tanggal_id($log['created_at'],TRUE) ?></p></div><span class="badge bg-blue-dark color-white ms-auto"><?= e($log['action']) ?></span></div>
                    <div class="d-flex py-2 mt-2 border-bottom"><span class="font-11 opacity-60">Objek</span><strong class="font-12 ms-auto text-end"><?= e(($log['entity_type']?:'-').($log['entity_id']?' · '.$log['entity_id']:'')) ?></strong></div>
                    <div class="py-2 border-bottom"><p class="font-11 opacity-60 mb-n1">Rincian</p><p class="font-10 text-break mb-0"><?= e($details?json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):'-') ?></p></div>
                    <p class="font-10 opacity-60 mt-2 mb-0"><i class="fa fa-network-wired me-1"></i><?= e($log['ip_address']?:'-') ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
