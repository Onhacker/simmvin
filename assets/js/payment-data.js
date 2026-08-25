(function () {
  'use strict';

  var form = document.querySelector('[data-payment-data-filter]');
  if (!form) return;

  var district = form.querySelector('[data-payment-district]');
  var village = form.querySelector('[data-payment-village]');
  if (!district || !village) return;

  var villages = [];
  try { villages = JSON.parse(form.getAttribute('data-villages') || '[]'); }
  catch (error) { villages = []; }

  function escapeHtml(value) {
    return String(value === null || typeof value === 'undefined' ? '' : value).replace(/[&<>'"]/g, function (character) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character];
    });
  }

  function rebuildVillages(preserveSelected) {
    var districtId = district.value;
    var selected = preserveSelected ? (village.value || village.getAttribute('data-selected') || '') : '';
    if (!districtId) {
      village.innerHTML = '<option value="">Pilih kecamatan dahulu</option>';
      village.value = '';
      village.disabled = true;
      return;
    }

    var matching = villages.filter(function (item) {
      return String(item.district_id) === String(districtId);
    });
    village.innerHTML = '<option value="">Semua desa</option>' + matching.map(function (item) {
      return '<option value="' + escapeHtml(item.id) + '">' + escapeHtml(item.name) + '</option>';
    }).join('');
    village.disabled = false;
    if (selected && matching.some(function (item) { return String(item.id) === String(selected); })) village.value = selected;
    else village.value = '';
    village.setAttribute('data-selected', village.value);
  }

  district.addEventListener('change', function () { rebuildVillages(false); });
  village.addEventListener('change', function () { village.setAttribute('data-selected', village.value); });
  rebuildVillages(true);
}());
