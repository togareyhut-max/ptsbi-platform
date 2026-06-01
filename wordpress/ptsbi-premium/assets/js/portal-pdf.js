/* Panel Dokumen PDF — frontend portal pengurus (daftar nama, tanpa thumbnail) */
(function ($) {
    'use strict';

    function slugify(title) {
        return String(title || '').trim().toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function readInitial(list) {
        var b64 = list.getAttribute('data-json-b64');
        if (!b64) return [];
        try {
            var raw = atob(b64);
            try { raw = decodeURIComponent(escape(raw)); } catch (e2) {}
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function shortcodesFor(id) {
        var safe = id || '';
        return {
            button: '[ptprm_pdf id="' + safe + '" style="button"]',
            hover: '[ptprm_pdf id="' + safe + '" style="hover" label="Lihat dokumen"]Teks di sini…[/ptprm_pdf]'
        };
    }

    function updateRowPreview(row) {
        var titleEl = row.querySelector('[data-field="title"]');
        var idEl = row.querySelector('[data-field="id"]');
        var title = titleEl ? titleEl.value.trim() : '';
        var id = idEl && idEl.value ? idEl.value.trim() : slugify(title);
        if (idEl && !idEl.dataset.ptprmIdLocked) {
            idEl.value = id;
        }
        var sc = shortcodesFor(id);
        var btnCode = row.querySelector('.ptprm-pdf-sc-button');
        var hoverCode = row.querySelector('.ptprm-pdf-sc-hover');
        if (btnCode) btnCode.textContent = sc.button;
        if (hoverCode) hoverCode.textContent = sc.hover;
    }

    function fillRow(row, data, index) {
        row.setAttribute('data-index', String(index));
        var idEl = row.querySelector('[data-field="id"]');
        if (idEl) {
            idEl.value = data.id || '';
            if (data.id) idEl.dataset.ptprmIdLocked = '1';
        }
        row.querySelector('[data-field="title"]').value = data.title || '';
        row.querySelector('[data-field="file"]').value = data.file || '';
        var nameEl = row.querySelector('.ptprm-pdf-file-name');
        if (nameEl) nameEl.textContent = data.file_name || '';
        updateRowPreview(row);
    }

    function collectRows(list) {
        var items = [];
        list.querySelectorAll('.ptprm-pdf-name-row').forEach(function (row) {
            var title = (row.querySelector('[data-field="title"]') || {}).value || '';
            title = title.trim();
            if (!title) return;
            items.push({
                id: (row.querySelector('[data-field="id"]') || {}).value || '',
                title: title,
                file: (row.querySelector('[data-field="file"]') || {}).value || '',
                link_label: (row.querySelector('[data-field="link_label"]') || {}).value || 'Lihat dokumen'
            });
        });
        return items;
    }

    function addRow(list, tpl, data, index) {
        var html = tpl.innerHTML.replace(/\{\{index\}\}/g, String(index));
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        var row = wrap.firstElementChild;
        list.appendChild(row);
        fillRow(row, data, index);
        return row;
    }

    function copyText(txt, btn) {
        if (!txt) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(txt);
        } else {
            var ta = document.createElement('textarea');
            ta.value = txt;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        if (btn) {
            var prev = btn.textContent;
            btn.textContent = 'Tersalin';
            setTimeout(function () { btn.textContent = prev; }, 1500);
        }
    }

    $(function () {
        var list = document.getElementById('ptprm-portal-pdf-list');
        var tpl = document.getElementById('ptprm-portal-pdf-row-tpl');
        var form = document.getElementById('ptprm-portal-pdf-form');
        if (!list || !tpl || !form) return;

        var items = readInitial(list);
        if (!items.length) {
            items = [{ id: '', title: '', file: '', link_label: 'Lihat dokumen' }];
        }
        list.innerHTML = '';
        items.forEach(function (data, i) {
            addRow(list, tpl, data, i);
        });

        $('#ptprm-portal-pdf-add').on('click', function (e) {
            e.preventDefault();
            if (list.children.length >= 50) {
                window.alert('Maksimal 50 dokumen.');
                return;
            }
            addRow(list, tpl, { id: '', title: '', file: '', link_label: 'Lihat dokumen' }, list.children.length);
        });

        $(list).on('click', '.ptprm-pdf-row-remove', function (e) {
            e.preventDefault();
            var row = $(this).closest('.ptprm-pdf-name-row').get(0);
            if (list.children.length > 1 && row) row.remove();
        });

        $(list).on('input', '[data-field="title"]', function () {
            updateRowPreview($(this).closest('.ptprm-pdf-name-row').get(0));
        });

        $(list).on('click', '.ptprm-pdf-pick', function (e) {
            e.preventDefault();
            if (!wp || !wp.media) return;
            var row = $(this).closest('.ptprm-pdf-name-row').get(0);
            var $input = $(row).find('[data-field="file"]');
            var $name = $(row).find('.ptprm-pdf-file-name');
            var frame = wp.media({
                title: 'Pilih file PDF',
                button: { text: 'Pakai PDF ini' },
                multiple: false,
                library: { type: 'application/pdf' }
            });
            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                $input.val(att.id);
                $name.text(att.filename || att.title || ('#' + att.id));
                updateRowPreview(row);
            });
            frame.open();
        });

        $(list).on('click', '.ptprm-pdf-clear', function (e) {
            e.preventDefault();
            var row = $(this).closest('.ptprm-pdf-name-row').get(0);
            $(row).find('[data-field="file"]').val('');
            $(row).find('.ptprm-pdf-file-name').text('');
        });

        $(list).on('click', '.ptprm-pdf-copy', function (e) {
            e.preventDefault();
            var row = $(this).closest('.ptprm-pdf-name-row').get(0);
            updateRowPreview(row);
            var which = $(this).data('which');
            var el = row.querySelector(which === 'hover' ? '.ptprm-pdf-sc-hover' : '.ptprm-pdf-sc-button');
            copyText(el ? el.textContent : '', this);
        });

        form.addEventListener('submit', function () {
            document.getElementById('ptprm-portal-pdf-json').value = JSON.stringify(collectRows(list));
        });
    });
}(jQuery));
