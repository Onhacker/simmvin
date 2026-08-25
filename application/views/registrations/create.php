<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$events = isset($activeEvents) && is_array($activeEvents) ? $activeEvents : (isset($events) ? $events : array());
$singleActiveEvent = count($events) === 1 ? $events[0] : NULL;
$selectedEventId = $selectedEvent && isset($selectedEvent['id']) ? (int) $selectedEvent['id'] : 0;
$accounts = isset($accounts) && is_array($accounts) ? $accounts : array();
$canRecordPayment = !empty($canRecordPayment);
$oldVillages = isset($oldVillages) ? $oldVillages : array();
$oldParticipantGroups = isset($oldParticipantGroups) ? $oldParticipantGroups : array();
$oldNotes = isset($oldNotes) ? $oldNotes : array();
$oldPaymentGroups = isset($oldPaymentGroups) ? $oldPaymentGroups : array();
?>

<?php if (validation_errors() || !empty($error)): ?>
    <div class="alert me-3 ms-3 rounded-s bg-red-dark shadow-xl" role="alert">
        <span class="alert-icon color-white"><i class="fa fa-times-circle font-18"></i></span>
        <h4 class="color-white">Registrasi belum dapat disimpan</h4>
        <div class="alert-icon-text color-white">
            <?= validation_errors('<div class="color-white">', '</div>') ?>
            <?php if (!empty($error)): ?><div class="color-white"><?= e($error) ?></div><?php endif; ?>
        </div>
        <button type="button" class="close color-white opacity-60 font-16" data-bs-dismiss="alert" aria-label="Tutup">&times;</button>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" id="registration-create-form"
      data-events='<?= e(json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
      data-positions='<?= e(json_encode($positions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
      data-accounts='<?= e(json_encode($accounts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
      data-can-record-payment="<?= $canRecordPayment ? '1' : '0' ?>"
      data-old-villages='<?= e(json_encode($oldVillages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
      data-old-participant-groups='<?= e(json_encode($oldParticipantGroups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
      data-old-notes='<?= e(json_encode($oldNotes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
      data-old-payment-groups='<?= e(json_encode($oldPaymentGroups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
      data-districts-url="<?= site_url('wilayah/kecamatan') ?>"
      data-villages-url="<?= site_url('wilayah/desa') ?>">
    <?= csrf_field() ?>

    <div class="card card-style">
        <div class="content mb-2">
            <p class="font-600 color-highlight mb-n1">Langkah 1 dari 2</p>
            <h3>Pilih Event & Wilayah</h3>
            <p class="opacity-60">Pilih event, satu atau beberapa kecamatan, lalu desa yang akan diregistrasikan.</p>

            <div class="row mb-0 mt-4">
                <div class="col-lg-5">
                    <?php if ($singleActiveEvent): ?>
                        <input type="hidden" name="event_id" id="registration-event" value="<?= (int) $singleActiveEvent['id'] ?>">
                        <div class="d-flex align-items-center rounded-s bg-blue-light px-3 py-3 mb-4">
                            <span class="icon icon-s rounded-xl bg-blue-dark color-white me-3 flex-shrink-0"><i class="fa fa-calendar-check"></i></span>
                            <div class="min-width-zero me-2">
                                <p class="font-10 color-blue-dark text-uppercase font-600 mb-n1">Event Aktif</p>
                                <h5 class="font-14 text-truncate mb-n1"><?= e($singleActiveEvent['name']) ?></h5>
                                <p class="font-10 opacity-60 text-truncate mb-0"><?= e($singleActiveEvent['code']) ?></p>
                            </div>
                            <span class="badge bg-green-dark color-white ms-auto">Aktif</span>
                        </div>
                    <?php else: ?>
                        <div class="input-style has-borders no-icon input-style-always-active mb-4">
                            <label for="registration-event" class="color-highlight">Event aktif</label>
                            <select name="event_id" id="registration-event" class="form-select" required>
                                <option value="">Pilih event aktif</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?= (int) $event['id'] ?>" <?= $selectedEventId === (int) $event['id'] ? 'selected' : '' ?>><?= e($event['name'].' · '.$event['code']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span><i class="fa fa-chevron-down"></i></span>
                            <i class="fa fa-check disabled valid color-green-dark"></i>
                            <i class="fa fa-times disabled invalid color-red-dark"></i>
                            <em>(wajib)</em>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex align-items-center rounded-s bg-blue-light px-3 py-2 mb-4">
                        <i class="fa fa-money-bill-wave color-blue-dark me-3"></i>
                        <small id="registration-billing-info" class="color-blue-dark font-600">Pilih event untuk melihat biaya.</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="input-style has-borders no-icon input-style-always-active mb-4">
                        <label for="registration-districts" class="color-highlight">Kecamatan (bisa banyak)</label>
                        <select id="registration-districts" class="form-select" multiple size="6" disabled style="height:190px;padding-top:24px;"></select>
                        <i class="fa fa-check d-none disabled valid color-green-dark"></i>
                        <i class="fa fa-times d-none disabled invalid color-red-dark"></i>
                        <em></em>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="input-style has-borders no-icon input-style-always-active mb-4">
                        <label for="registration-villages" class="color-highlight">Desa (bisa banyak)</label>
                        <select id="registration-villages" class="form-select" multiple size="6" disabled style="height:190px;padding-top:24px;"></select>
                        <i class="fa fa-check d-none disabled valid color-green-dark"></i>
                        <i class="fa fa-times d-none disabled invalid color-red-dark"></i>
                        <em></em>
                    </div>
                </div>
            </div>

            <button type="button" id="prepare-villages" class="btn btn-full btn-m gradient-highlight rounded-s font-600 font-13 shadow-s">
                <i class="fa fa-arrow-down me-2"></i>Siapkan Form Peserta
            </button>
        </div>
    </div>

    <div class="card card-style">
        <div class="content mb-2">
            <p class="font-600 color-highlight mb-n1">Langkah 2 dari 2</p>
            <h3>Peserta per Desa</h3>
            <p class="opacity-60 mb-3">Tambahkan satu atau beberapa peserta untuk setiap desa yang dipilih.</p>

            <?php if ($canRecordPayment): ?>
                <div class="d-flex align-items-start rounded-s bg-blue-light px-3 py-3 mb-4">
                    <span class="icon icon-s rounded-xl bg-blue-dark color-white me-3 flex-shrink-0"><i class="fa fa-wallet"></i></span>
                    <div>
                        <h5 class="font-13 mb-n1">Pembayaran dapat langsung dicatat</h5>
                        <p class="font-11 color-blue-dark mb-0">Aktifkan Pembayaran Awal pada desa atau peserta yang membayar. Jika belum membayar, biarkan tetap nonaktif.</p>
                    </div>
                </div>
            <?php endif; ?>

            <div id="village-registration-cards">
                <div class="d-flex align-items-center py-3">
                    <span class="icon icon-m rounded-xl bg-blue-light color-blue-dark me-3"><i class="fa fa-info"></i></span>
                    <div>
                        <h5 class="font-14 mb-0">Form peserta belum disiapkan</h5>
                        <p class="font-11 opacity-60 mb-0">Pilih desa pada langkah pertama, lalu tekan “Siapkan Form Peserta”.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content mt-0 mb-4">
        <div class="row mb-0">
            <div class="col-6 pe-1">
                <a href="<?= site_url('registrasi') ?>" class="btn btn-full btn-m border-highlight color-highlight rounded-s font-600 font-13">Batal</a>
            </div>
            <div class="col-6 ps-1">
                <button class="btn btn-full btn-m gradient-highlight rounded-s font-600 font-13 shadow-s" type="submit" id="registration-submit">
                    <i class="fa fa-save me-2"></i>Simpan
                </button>
            </div>
        </div>
    </div>
</form>
