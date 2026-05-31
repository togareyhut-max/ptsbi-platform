/* Profil anggota: wilayah → provinsi → kota → kecamatan → kelurahan */
(function () {
    'use strict';

    function boot() {
        if (!window.PTPRM_AddressRegions) return;
        document.querySelectorAll('[data-ptprm-address-form]').forEach(function (wrap) {
            PTPRM_AddressRegions.initCascade(wrap, {
                defaultWilayah: 'jabodetabek',
                provincePlaceholder: '— Pilih provinsi —',
                cityPlaceholder: '— Pilih kota/kabupaten —',
                districtPlaceholder: '— Pilih kecamatan —',
                subdistrictPlaceholder: '— Pilih kelurahan —'
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
