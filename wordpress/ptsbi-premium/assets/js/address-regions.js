/* Wilayah Indonesia: Kepmendagri + kategori Jabodetabek (lazy-load per provinsi) */
(function (global) {
    'use strict';

    var cfg = global.PTPRM_ADDRESS || {};
    var provinceCache = {};

    function norm(s) {
        return String(s || '').trim().toLowerCase().replace(/\s+/g, ' ');
    }

    function normKey(s) {
        return norm(s).replace(/[^a-z0-9]/g, '');
    }

    function findByName(list, name) {
        var n = norm(name);
        if (!n || !list) return null;
        for (var i = 0; i < list.length; i++) {
            if (norm(list[i].name) === n) return list[i];
        }
        return null;
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function escapeAttr(s) {
        return escapeHtml(s).replace(/"/g, '&quot;');
    }

    function fillSelect(sel, items, placeholder, selected) {
        if (!sel) return;
        var html = '<option value="">' + escapeHtml(placeholder || '— Pilih —') + '</option>';
        (items || []).forEach(function (item) {
            var name = item.name || '';
            if (!name) return;
            var selAttr = norm(selected) === norm(name) ? ' selected' : '';
            var codeAttr = item.code ? ' data-code="' + escapeAttr(item.code) + '"' : '';
            html += '<option value="' + escapeAttr(name) + '"' + codeAttr + selAttr + '>' + escapeHtml(name) + '</option>';
        });
        sel.innerHTML = html;
        if (selected && !sel.value) {
            var opt = document.createElement('option');
            opt.value = selected;
            opt.textContent = selected;
            opt.selected = true;
            sel.appendChild(opt);
        }
    }

    function getIndex() {
        return cfg.index || { regions: [], provinces: [] };
    }

    function getJabodetabekRegion() {
        var regions = getIndex().regions || [];
        for (var i = 0; i < regions.length; i++) {
            if (regions[i].id === 'jabodetabek') return regions[i];
        }
        return null;
    }

    function filterProvinces(wilayahId) {
        var list = getIndex().provinces || [];
        if (!wilayahId || wilayahId === 'all') return list.slice();
        var jabo = getJabodetabekRegion();
        if (!jabo || wilayahId !== 'jabodetabek') return list.slice();
        var codes = {};
        (jabo.province_codes || []).forEach(function (c) { codes[String(c)] = true; });
        return list.filter(function (p) { return codes[String(p.code)]; });
    }

    function filterCities(cities, wilayahId) {
        if (!wilayahId || wilayahId === 'all') return cities || [];
        var jabo = getJabodetabekRegion();
        if (!jabo || wilayahId !== 'jabodetabek') return cities || [];
        var codes = {};
        (jabo.city_codes || []).forEach(function (c) { codes[String(c)] = true; });
        return (cities || []).filter(function (c) {
            return c.region === 'jabodetabek' || codes[String(c.code)];
        });
    }

    function fetchProvince(code) {
        if (!code) return Promise.resolve(null);
        if (provinceCache[code]) return Promise.resolve(provinceCache[code]);
        var url = cfg.ajaxUrl || '';
        if (!url) return Promise.resolve(null);
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        var full = url + sep + 'action=' + encodeURIComponent(cfg.action || 'ptprm_address_regions') + '&province=' + encodeURIComponent(code);
        return fetch(full, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.success || !json.data) return null;
                provinceCache[code] = json.data;
                return json.data;
            })
            .catch(function () { return null; });
    }

    function provinceCodeByName(name) {
        var list = getIndex().provinces || [];
        var n = norm(name);
        for (var i = 0; i < list.length; i++) {
            if (norm(list[i].name) === n) return String(list[i].code);
        }
        return '';
    }

    function initCascade(root, options) {
        options = options || {};
        var wilayahSel = root.querySelector('[data-ptprm-wilayah]');
        var prov = root.querySelector(options.provinceSelector || 'select[name="province"], select[name="provinsi"]');
        var city = root.querySelector(options.citySelector || 'select[name="city"], select[name="kota"]');
        var dist = root.querySelector(options.districtSelector || 'select[name="district"], select[name="kecamatan"]');
        var subd = root.querySelector(options.subdistrictSelector || 'select[name="subdistrict"], select[name="kelurahan"]');
        var postal = root.querySelector('input[name="postal_code"]');
        var overseas = root.querySelector('input[name="is_overseas"]');
        var idBlock = root.querySelector('[data-ptprm-address-id]');
        var intlBlock = root.querySelector('[data-ptprm-address-intl]');

        if (!prov || !city) return;

        var saved = {
            wilayah: (wilayahSel && (wilayahSel.getAttribute('data-current') || wilayahSel.value)) || options.defaultWilayah || 'jabodetabek',
            province: prov.getAttribute('data-current') || prov.value || '',
            city: city.getAttribute('data-current') || city.value || '',
            district: dist ? (dist.getAttribute('data-current') || dist.value || '') : '',
            subdistrict: subd ? (subd.getAttribute('data-current') || subd.value || '') : ''
        };

        var loadedProvince = null;

        function currentWilayah() {
            return wilayahSel ? (wilayahSel.value || 'all') : (options.defaultWilayah || 'all');
        }

        function isOverseasMode() {
            if (wilayahSel && wilayahSel.value === 'overseas') {
                return true;
            }
            if (!overseas) {
                return false;
            }
            if (overseas.type === 'checkbox') {
                return overseas.checked;
            }
            return overseas.value === '1';
        }

        function syncOverseasFromWilayah() {
            if (!overseas || !wilayahSel) {
                return;
            }
            if (wilayahSel.value === 'overseas') {
                overseas.value = '1';
            } else if (overseas.type !== 'checkbox') {
                overseas.value = '0';
            }
        }

        function toggleOverseas() {
            var on = isOverseasMode();
            if (idBlock) {
                idBlock.style.display = on ? 'none' : '';
                idBlock.querySelectorAll('select').forEach(function (sel) {
                    sel.disabled = on;
                    if (on) {
                        sel.removeAttribute('required');
                    }
                });
            }
            if (intlBlock) {
                intlBlock.style.display = on ? '' : 'none';
            }
        }

        function resetBelowCity() {
            if (dist) fillSelect(dist, [], options.districtPlaceholder || '— Pilih kecamatan —', '');
            if (subd) fillSelect(subd, [], options.subdistrictPlaceholder || '— Pilih kelurahan —', '');
            if (postal) postal.value = '';
        }

        function onWilayahChange() {
            syncOverseasFromWilayah();
            toggleOverseas();
            if (isOverseasMode()) {
                return;
            }
            var provinces = filterProvinces(currentWilayah());
            fillSelect(prov, provinces, options.provincePlaceholder || '— Pilih provinsi —', '');
            fillSelect(city, [], options.cityPlaceholder || '— Pilih kota/kabupaten —', '');
            resetBelowCity();
            loadedProvince = null;
        }

        function onProvince() {
            var code = prov.selectedOptions.length ? prov.selectedOptions[0].getAttribute('data-code') : '';
            if (!code) code = provinceCodeByName(prov.value);
            fillSelect(city, [], options.cityPlaceholder || '— Pilih kota/kabupaten —', '');
            resetBelowCity();
            loadedProvince = null;
            if (!code) return;
            fetchProvince(code).then(function (data) {
                loadedProvince = data;
                var cities = filterCities(data && data.cities, currentWilayah());
                fillSelect(city, cities, options.cityPlaceholder || '— Pilih kota/kabupaten —', '');
            });
        }

        function onCity() {
            if (!loadedProvince) return;
            var c = findByName(loadedProvince.cities, city.value);
            if (dist) fillSelect(dist, c ? c.districts : [], options.districtPlaceholder || '— Pilih kecamatan —', '');
            if (subd) fillSelect(subd, [], options.subdistrictPlaceholder || '— Pilih kelurahan —', '');
            if (postal) postal.value = '';
        }

        function onDistrict() {
            if (!loadedProvince) return;
            var c = findByName(loadedProvince.cities, city.value);
            var d = c ? findByName(c.districts, dist.value) : null;
            if (subd) fillSelect(subd, d ? d.subdistricts : [], options.subdistrictPlaceholder || '— Pilih kelurahan —', '');
            if (postal) postal.value = '';
        }

        function onSubdistrict() {
            if (!postal || !loadedProvince) return;
            var c = findByName(loadedProvince.cities, city.value);
            var d = c ? findByName(c.districts, dist.value) : null;
            var s = d ? findByName(d.subdistricts, subd.value) : null;
            if (s && s.postal_code) postal.value = s.postal_code;
        }

        function restoreSaved() {
            if (wilayahSel && saved.wilayah) {
                wilayahSel.value = saved.wilayah;
            }
            syncOverseasFromWilayah();
            toggleOverseas();
            if (isOverseasMode()) {
                return Promise.resolve();
            }
            var provinces = filterProvinces(currentWilayah());
            fillSelect(prov, provinces, options.provincePlaceholder || '— Pilih provinsi —', saved.province);
            var code = provinceCodeByName(saved.province);
            if (!code) return Promise.resolve();
            return fetchProvince(code).then(function (data) {
                loadedProvince = data;
                var cities = filterCities(data && data.cities, currentWilayah());
                fillSelect(city, cities, options.cityPlaceholder || '— Pilih kota/kabupaten —', saved.city);
                var c0 = findByName(data.cities, saved.city);
                if (dist) fillSelect(dist, c0 ? c0.districts : [], options.districtPlaceholder || '— Pilih kecamatan —', saved.district);
                var d0 = c0 ? findByName(c0.districts, saved.district) : null;
                if (subd) fillSelect(subd, d0 ? d0.subdistricts : [], options.subdistrictPlaceholder || '— Pilih kelurahan —', saved.subdistrict);
                onSubdistrict();
            });
        }

        if (wilayahSel) {
            var curWilayah = wilayahSel.getAttribute('data-current') || saved.wilayah || 'jabodetabek';
            wilayahSel.value = curWilayah;
            wilayahSel.addEventListener('change', onWilayahChange);
            syncOverseasFromWilayah();
            toggleOverseas();
            if (!isOverseasMode()) {
                if (!saved.province) {
                    fillSelect(prov, filterProvinces(currentWilayah()), options.provincePlaceholder || '— Pilih provinsi —', '');
                }
            }
        } else {
            fillSelect(prov, filterProvinces(currentWilayah()), options.provincePlaceholder || '— Pilih provinsi —', saved.province);
        }

        prov.addEventListener('change', onProvince);
        city.addEventListener('change', onCity);
        if (dist) dist.addEventListener('change', onDistrict);
        if (subd) subd.addEventListener('change', onSubdistrict);
        if (overseas) {
            overseas.addEventListener('change', toggleOverseas);
            toggleOverseas();
        }

        restoreSaved().then(function () {
            syncOverseasFromWilayah();
            toggleOverseas();
            if (!wilayahSel && saved.province) {
                onProvince();
            }
        });
    }

    global.PTPRM_AddressRegions = {
        initCascade: initCascade,
        fetchProvince: fetchProvince,
        getIndex: getIndex,
        filterProvinces: filterProvinces,
        provinceCodeByName: provinceCodeByName,
        fillSelect: fillSelect,
        findByName: findByName,
        norm: norm
    };
})(typeof window !== 'undefined' ? window : this);
