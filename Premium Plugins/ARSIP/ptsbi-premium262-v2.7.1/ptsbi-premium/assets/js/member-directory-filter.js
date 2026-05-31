/* Filter direktori: wilayah → provinsi → kota → kecamatan */
(function () {
    'use strict';

    function boot() {
        if (!window.PTPRM_AddressRegions) return;
        document.querySelectorAll('[data-ptprm-directory-filter]').forEach(function (form) {
            PTPRM_AddressRegions.initCascade(form, {
                defaultWilayah: 'jabodetabek',
                provinceSelector: 'select[name="provinsi"]',
                citySelector: 'select[name="kota"]',
                districtSelector: 'select[name="kecamatan"]',
                subdistrictSelector: 'select[name="kelurahan"]',
                provincePlaceholder: 'Semua provinsi',
                cityPlaceholder: 'Semua kota',
                districtPlaceholder: 'Semua kecamatan',
                subdistrictPlaceholder: 'Semua kelurahan'
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
