/* Panel pengurus — repeater PDF lightbox (frontend) */
(function ($) {
    'use strict';

    function collectPdfRow(row) {
        var title = (row.querySelector('[data-field="title"]') || {}).value || '';
        var pdf = (row.querySelector('[data-field="pdf"]') || {}).value || '';
        pdf = String(pdf).trim();
        if (!pdf) return null;
        return {
            title: String(title).trim(),
            pdf: pdf,
            cover: String((row.querySelector('[data-field="cover"]') || {}).value || '').trim()
        };
    }

    function syncPdfRepeater(form) {
        var hidden = form.querySelector('#ptprm-pdf-items-json');
        var list = form.querySelector('#ptprm-pdf-repeater .ptprm-repeater-list');
        if (!hidden || !list) return;
        var items = [];
        list.querySelectorAll('.ptprm-repeater-row').forEach(function (row) {
            var item = collectPdfRow(row);
            if (item) items.push(item);
        });
        hidden.value = JSON.stringify(items);
    }

    function addRow(wrap, data, index) {
        var tplEl = document.querySelector(wrap.getAttribute('data-tpl'));
        if (!tplEl) return null;
        var html = tplEl.innerHTML
            .replace(/\{\{index\}\}/g, String(index))
            .replace(/\{\{num\}\}/g, String(index + 1))
            .replace(/\{\{pdf\}\}/g, data.pdf || '')
            .replace(/\{\{cover\}\}/g, data.cover || '')
            .replace(/\{\{cover_preview\}\}/g, data.cover_preview || '')
            .replace(/\{\{pdf_name\}\}/g, data.pdf_name || '');
        var div = document.createElement('div');
        div.innerHTML = html.trim();
        var row = div.firstElementChild;
        wrap.querySelector('.ptprm-repeater-list').appendChild(row);
        return row;
    }

    function initPdfRepeater() {
        var wrap = document.getElementById('ptprm-pdf-repeater');
        var hidden = document.getElementById('ptprm-pdf-items-json');
        if (!wrap || !hidden) return;

        var items = [];
        try {
            var b64 = hidden.getAttribute('data-ptprm-json-b64') || '';
            if (b64) {
                items = JSON.parse(atob(b64));
            }
        } catch (e) {
            items = [];
        }
        if (!items.length) {
            items = [{ title: '', pdf: '', cover: '' }];
        }

        items.forEach(function (item, i) {
            var data = {
                title: item.title || '',
                pdf: item.pdf || '',
                cover: item.cover || '',
                cover_preview: item.cover_url || '',
                pdf_name: item.pdf_url ? item.pdf_url.split('/').pop() : (item.pdf ? ('#' + item.pdf) : '')
            };
            var row = addRow(wrap, data, i);
            if (row) {
                var titleEl = row.querySelector('[data-field="title"]');
                if (titleEl) titleEl.value = data.title;
            }
        });

        wrap.addEventListener('click', function (e) {
            if (e.target.classList.contains('ptprm-repeater-add')) {
                e.preventDefault();
                var list = wrap.querySelector('.ptprm-repeater-list');
                var max = 24;
                if (list.children.length >= max) return;
                addRow(wrap, { title: '', pdf: '', cover: '', cover_preview: '', pdf_name: '' }, list.children.length);
            }
            if (e.target.classList.contains('ptprm-repeater-remove')) {
                e.preventDefault();
                var row = e.target.closest('.ptprm-repeater-row');
                if (row) row.remove();
                var list = wrap.querySelector('.ptprm-repeater-list');
                list.querySelectorAll('.ptprm-repeater-row').forEach(function (r, idx) {
                    var strong = r.querySelector('.ptprm-repeater-row-head strong');
                    if (strong) strong.textContent = 'Dokumen ' + (idx + 1);
                });
            }
        });

        var form = wrap.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                syncPdfRepeater(form);
            });
        }
    }

    function initMediaPickers() {
        $(document).on('click', '.ptprm-image-pick', function (e) {
            e.preventDefault();
            if (!wp.media) return;
            var $wrap = $(this).closest('.ptprm-image-field');
            var $input = $wrap.find('.ptprm-image-value');
            var $prev = $wrap.find('.ptprm-image-preview');
            var frame = wp.media({
                title: 'Pilih gambar',
                button: { text: 'Pakai gambar ini' },
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                $input.val(att.id);
                var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
                $prev.css('background-image', 'url(' + url + ')');
            });
            frame.open();
        });

        $(document).on('click', '.ptprm-image-clear', function (e) {
            e.preventDefault();
            var $wrap = $(this).closest('.ptprm-image-field');
            $wrap.find('.ptprm-image-value').val('');
            $wrap.find('.ptprm-image-preview').css('background-image', '');
        });

        $(document).on('click', '.ptprm-pdf-pick', function (e) {
            e.preventDefault();
            if (!wp.media) return;
            var $wrap = $(this).closest('.ptprm-repeater-pdf-field');
            var $input = $wrap.find('.ptprm-pdf-value');
            var $label = $wrap.find('.ptprm-pdf-filename');
            var frame = wp.media({
                title: 'Pilih PDF',
                button: { text: 'Pakai PDF ini' },
                multiple: false,
                library: { type: 'application' }
            });
            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                if (att && att.mime && att.mime.indexOf('pdf') === -1 && att.url && att.url.indexOf('.pdf') === -1) {
                    window.alert('Pilih file PDF.');
                    return;
                }
                $input.val(att.id);
                if ($label.length) $label.text(att.filename || att.title || att.url || '');
            });
            frame.open();
        });

        $(document).on('click', '.ptprm-pdf-clear', function (e) {
            e.preventDefault();
            var $wrap = $(this).closest('.ptprm-repeater-pdf-field');
            $wrap.find('.ptprm-pdf-value').val('');
            $wrap.find('.ptprm-pdf-filename').text('');
        });
    }

    $(function () {
        initPdfRepeater();
        initMediaPickers();
    });
})(jQuery);
