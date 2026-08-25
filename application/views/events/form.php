<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$editing = !empty($event);
$billingMode = old('billing_mode', $editing ? $event['billing_mode'] : 'per_village');
$villageFeeValue = old('village_fee', $editing ? $event['village_fee'] : '');
$participantFeeValue = old('participant_fee', $editing ? $event['participant_fee'] : '');
$includedParticipantValue = old(
    'included_participant_count',
    $editing && isset($event['included_participant_count']) && (int) $event['included_participant_count'] > 0
        ? $event['included_participant_count']
        : 1
);
$regionItems = array();
foreach ((array) (isset($selectedRegions) ? $selectedRegions : array()) as $selectedRegion) {
    if (is_string($selectedRegion)) $selectedRegion = json_decode($selectedRegion, TRUE);
    if (is_array($selectedRegion) && !empty($selectedRegion['province_id']) && !empty($selectedRegion['regency_id'])) {
        $regionItems[] = array(
            'province_id' => (string) $selectedRegion['province_id'],
            'province_name' => isset($selectedRegion['province_name']) ? $selectedRegion['province_name'] : '',
            'regency_id' => (string) $selectedRegion['regency_id'],
            'regency_name' => isset($selectedRegion['regency_name']) ? $selectedRegion['regency_name'] : ''
        );
    }
}
?>

<div class="card card-style">
    <div class="content mb-3">
        <div class="d-flex align-items-center">
            <div>
                <p class="color-highlight font-600 mb-n1"><?= $editing ? 'Perbarui Agenda' : 'Agenda Baru' ?></p>
                <h2 class="font-24 font-800 mb-1"><?= $editing ? 'Ubah Event' : 'Tambah Event' ?></h2>
                <p class="mb-0">Lengkapi informasi pelaksanaan dan pilih satu atau beberapa kabupaten/kota sasaran.</p>
            </div>
            <a class="icon icon-s rounded-xl bg-theme color-theme shadow-xl ms-auto" href="<?= site_url('event') ?>" aria-label="Kembali"><i class="fa fa-arrow-left"></i></a>
        </div>
    </div>
</div>

<?php if (validation_errors()): ?>
    <div class="mx-3 alert alert-small rounded-s shadow-xl bg-red-dark" role="alert">
        <span><i class="fa fa-exclamation-triangle color-white"></i></span>
        <strong class="color-white"><?= validation_errors('<span class="d-block">', '</span>') ?></strong>
        <button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="mx-3 alert alert-small rounded-s shadow-xl bg-red-dark" role="alert">
        <span><i class="fa fa-times color-white"></i></span><strong class="color-white"><?= e($error) ?></strong>
        <button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button>
    </div>
<?php endif; ?>

<form method="post" id="event-form" data-event-form-ajax>
    <?= csrf_field() ?>
    <div class="row mb-0 mx-0">
        <div class="col-xl-7">
            <div class="card card-style mx-0">
                <div class="content mb-0">
                    <p class="color-highlight font-600 mb-n1">Informasi Utama</p>
                    <h3 class="font-22 mb-3">Detail Event</h3>
                    <div class="row mb-0">
                        <div class="col-md-4">
                            <div class="input-style input-style-always-active has-borders no-icon validate-field mb-4">
                                <input class="form-control" id="event-code" name="code" required maxlength="40" value="<?= e(old('code', $editing ? $event['code'] : ('EVT-' . date('Ymd')))) ?>" placeholder="EVT-2026">
                                <label for="event-code" class="color-highlight font-12 font-500">Kode Event</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>*</em>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="input-style input-style-always-active has-borders no-icon validate-field mb-4">
                                <input class="form-control" id="event-name" name="name" required maxlength="180" value="<?= e(old('name', $editing ? $event['name'] : '')) ?>" placeholder="Nama pelatihan">
                                <label for="event-name" class="color-highlight font-12 font-500">Nama Event</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>*</em>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-style input-style-always-active has-borders no-icon mb-4">
                                <input type="date" class="form-control" id="event-start-date" name="start_date" required value="<?= e(old('start_date', $editing ? $event['start_date'] : date('Y-m-d'))) ?>">
                                <label for="event-start-date" class="color-highlight font-12 font-500">Tanggal Mulai</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>*</em>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-style input-style-always-active has-borders no-icon mb-4">
                                <input type="date" class="form-control" id="event-end-date" name="end_date" required value="<?= e(old('end_date', $editing ? $event['end_date'] : date('Y-m-d'))) ?>">
                                <label for="event-end-date" class="color-highlight font-12 font-500">Tanggal Selesai</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>*</em>
                            </div>
                        </div>
                    </div>

                    <div class="divider mt-1 mb-4"></div>
                    <p class="color-highlight font-600 mb-n1">Skema Tagihan</p>
                    <h3 class="font-20 mb-3">Pembayaran</h3>
                    <div class="row mb-0">
                        <div class="col-md-6">
                            <div class="input-style input-style-always-active has-borders no-icon mb-4">
                                <label for="billing-mode" class="color-highlight font-12 font-500">Mode Pembayaran</label>
                                <select class="form-select" name="billing_mode" id="billing-mode">
                                    <option value="per_village" <?= $billingMode === 'per_village' ? 'selected' : '' ?>>Per Desa</option>
                                    <option value="per_participant" <?= $billingMode === 'per_participant' ? 'selected' : '' ?>>Per Peserta</option>
                                    <option value="per_village_extra" <?= $billingMode === 'per_village_extra' ? 'selected' : '' ?>>Paket Desa + Peserta Tambahan</option>
                                </select>
                                <span><i class="fa fa-chevron-down"></i></span>
                                <i class="fa fa-check disabled valid color-green-dark"></i>
                                <i class="fa fa-times disabled invalid color-red-dark"></i><em></em>
                            </div>
                        </div>
                        <div class="col-md-6 fee-village">
                            <div class="input-style input-style-always-active has-borders no-icon validate-field mb-4">
                                <input class="form-control" id="village-fee" type="number" min="1" step="1" name="village_fee" value="<?= e($villageFeeValue) ?>" placeholder="Contoh: 18000000">
                                <label for="village-fee" id="village-fee-label" class="color-highlight font-12 font-500">Biaya Per Desa</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>Rupiah</em>
                            </div>
                        </div>
                        <div class="col-md-6 fee-participant">
                            <div class="input-style input-style-always-active has-borders no-icon validate-field mb-4">
                                <input class="form-control" id="participant-fee" type="number" min="1" step="1" name="participant_fee" value="<?= e($participantFeeValue) ?>" placeholder="Contoh: 3500000">
                                <label for="participant-fee" id="participant-fee-label" class="color-highlight font-12 font-500">Biaya Per Peserta</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>Rupiah</em>
                            </div>
                        </div>
                        <div class="col-md-6 fee-included-participants">
                            <div class="input-style input-style-always-active has-borders no-icon validate-field mb-4">
                                <input class="form-control" id="included-participant-count" type="number" min="1" max="65535" step="1" name="included_participant_count" value="<?= e($includedParticipantValue) ?>" placeholder="Contoh: 4">
                                <label for="included-participant-count" class="color-highlight font-12 font-500">Peserta Dalam Paket Desa</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>Minimal 1 orang</em>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-start rounded-s bg-blue-light px-3 py-3 mb-4">
                                <i class="fa fa-calculator color-blue-dark font-18 me-3 mt-1"></i>
                                <div>
                                    <p class="font-11 color-blue-dark font-600 mb-n1">Cara menghitung tagihan</p>
                                    <p id="billing-formula-preview" class="font-12 mb-0">Isi biaya untuk melihat perhitungan.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="divider mt-1 mb-4"></div>
                    <p class="color-highlight font-600 mb-n1">Tempat Pelaksanaan</p>
                    <h3 class="font-20 mb-3">Lokasi dan Status</h3>
                    <div class="row mb-0">
                        <div class="col-12">
                            <div class="input-style input-style-always-active has-borders no-icon validate-field mb-4">
                                <input class="form-control" id="event-location" name="location" maxlength="180" required value="<?= e(old('location', $editing ? $event['location'] : '')) ?>" placeholder="Nama gedung atau hotel">
                                <label for="event-location" class="color-highlight font-12 font-500">Lokasi / Gedung</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em>*</em>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="input-style input-style-always-active has-borders no-icon mb-4">
                                <textarea class="form-control" id="event-address" name="address" rows="3" placeholder="Alamat lengkap lokasi event"><?= e(old('address', $editing ? $event['address'] : '')) ?></textarea>
                                <label for="event-address" class="color-highlight font-12 font-500">Alamat Lengkap</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em></em>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="input-style input-style-always-active has-borders no-icon mb-4">
                                <textarea class="form-control" id="event-notes" name="notes" rows="3" placeholder="Catatan tambahan bila ada"><?= e(old('notes', $editing ? $event['notes'] : '')) ?></textarea>
                                <label for="event-notes" class="color-highlight font-12 font-500">Catatan</label>
                                <i class="fa fa-times disabled invalid color-red-dark"></i><i class="fa fa-check disabled valid color-green-dark"></i><em></em>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card card-style mx-0">
                <div class="content mb-2">
                    <p class="color-highlight font-600 mb-n1">Multi Wilayah</p>
                    <h3 class="font-22 mb-1">Wilayah Event</h3>
                    <p class="mb-4">Pilih beberapa kabupaten, lalu tambahkan. Ulangi langkah ini untuk provinsi lain.</p>

                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <label for="province" class="color-highlight font-12 font-500">Provinsi</label>
                        <select class="form-select" id="province"><option value="">Memuat provinsi...</option></select>
                        <span><i class="fa fa-chevron-down"></i></span>
                        <i class="fa fa-check disabled valid color-green-dark"></i>
                        <i class="fa fa-times disabled invalid color-red-dark"></i><em></em>
                    </div>
                    <div class="input-style input-style-always-active has-borders no-icon mb-3">
                        <label for="regencies" class="color-highlight font-12 font-500">Kabupaten / Kota</label>
                        <select class="form-select" id="regencies" multiple size="7" style="height:180px"></select>
                        <i class="fa fa-check d-none disabled valid color-green-dark"></i>
                        <i class="fa fa-times d-none disabled invalid color-red-dark"></i>
                        <em>Pilih satu atau beberapa wilayah</em>
                    </div>
                    <button class="btn btn-full btn-m font-600 border-highlight color-highlight rounded-s mb-4" type="button" id="add-regencies">
                        <i class="fas fa-plus me-1"></i> Tambahkan Wilayah Terpilih
                    </button>

                    <p class="color-highlight font-600 mb-n1">Cakupan Terpilih</p>
                    <h4 class="font-18 mb-2">Kabupaten / Kota</h4>
                    <div id="selected-regions" class="mb-3">
                        <div class="text-center py-3 opacity-50"><i class="fa fa-map-marked-alt font-24 d-block mb-2"></i>Belum ada wilayah dipilih.</div>
                    </div>
                </div>
            </div>

            <div class="card card-style mx-0">
                <div class="content">
                    <div class="d-flex align-items-center rounded-s bg-blue-light px-3 py-2 mb-3">
                        <i class="fa fa-info-circle color-blue-dark me-3"></i>
                        <small class="color-blue-dark font-600">
                            <?= $editing
                                ? 'Status saat ini: ' . strip_tags(status_badge($event['status'])) . '. Aktivasi dan penutupan dilakukan dari halaman detail.'
                                : 'Event baru disimpan sebagai Draft. Periksa detailnya, lalu aktifkan agar muncul pada registrasi.' ?>
                        </small>
                    </div>
                    <div class="d-flex gap-2">
                        <a class="btn btn-m font-600 border-highlight color-highlight rounded-s flex-grow-1" href="<?= site_url('event') ?>">Batal</a>
                        <button class="btn btn-m font-600 gradient-highlight rounded-s flex-grow-1" type="submit"><i class="fas fa-save me-1"></i> <?= $editing ? 'Simpan Perubahan' : 'Simpan Draft' ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>window.EVENT_FORM={selectedRegions:<?= json_encode($regionItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>};</script>
