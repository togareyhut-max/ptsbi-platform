/* Premium Plugin — Admin (tabs, image picker, color picker, live preview) */
(function ($) {
    'use strict';

    $(function () {
        initTabs();
        initSaveForm();
        initColorPickers();
        initImageFields();
        initGalleryFields();
        initRangeReadouts();
        initPresets();
        initHomeOrderSortable();
        initPreviewModes();
        initLivePreview();
        initRepeaters();
        initPdfMediaPickers();
    });

    /** Simpan ditangani script inline ptprm-save (admin-post.php). */
    function initSaveForm() {
        /* noop — lihat inline_save_script() di class-settings.php */
    }

    function initTabs() {
        var $tabs = $('.ptprm-tabs a');
        $tabs.on('click', function (e) {
            e.preventDefault();
            var slug = $(this).data('tab');
            $tabs.removeClass('is-active');
            $(this).addClass('is-active');
            $('.ptprm-panel').attr('hidden', true);
            $('#tab-' + slug).removeAttr('hidden');
            window.scrollTo({ top: 0, behavior: 'instant' });
        });
    }

    function initColorPickers() {
        if (!$.fn.wpColorPicker) return;
        $('.ptprm-color').each(function () {
            var $i = $(this);
            $i.wpColorPicker({
                change: function () {
                    setTimeout(function () { triggerPreview($i.get(0)); }, 20);
                },
                clear: function () {
                    setTimeout(function () { triggerPreview($i.get(0)); }, 20);
                }
            });
        });
    }

    function syncRepeaterFields(form) {
        syncRepeaterType(form, 'values', '#ptprm-values-items-json', collectValuesRow);
        syncRepeaterType(form, 'menu', '#ptprm-header-menu-json', collectMenuRow);
    }

    function syncRepeaterType(form, type, hiddenSel, collector) {
        var hidden = form.querySelector(hiddenSel);
        var list = form.querySelector('#ptprm-' + type + '-repeater .ptprm-repeater-list');
        if (!hidden || !list) return;
        if (!list.children.length) {
            var existing = readRepeaterJson(hidden);
            if (existing.length) {
                hidden.value = JSON.stringify(existing);
                return;
            }
        }
        var items = [];
        list.querySelectorAll('.ptprm-repeater-row').forEach(function (row) {
            var item = collector(row);
            if (item) items.push(item);
        });
        hidden.value = JSON.stringify(items);
    }

    function collectValuesRow(row) {
        var title = (row.querySelector('[data-field="title"]') || {}).value || '';
        title = title.trim();
        if (!title) return null;
        return {
            icon: (row.querySelector('[data-field="icon"]') || {}).value || 'users',
            title: title,
            desc: (row.querySelector('[data-field="desc"]') || {}).value || ''
        };
    }

    function collectTeamRow(row) {
        var name = (row.querySelector('[data-field="name"]') || {}).value || '';
        name = name.trim();
        if (!name) return null;
        return {
            image: (row.querySelector('[data-field="image"]') || {}).value || '',
            name: name,
            role: (row.querySelector('[data-field="role"]') || {}).value || '',
            url: (row.querySelector('[data-field="url"]') || {}).value || '',
            group: (row.querySelector('[data-field="group"]') || {}).value || ''
        };
    }

    function collectMenuChildRow(childRow) {
        var label = (childRow.querySelector('[data-field="label"]') || {}).value || '';
        label = label.trim();
        if (!label) return null;
        return {
            label: label,
            url: (childRow.querySelector('[data-field="url"]') || {}).value || '/',
            target: (childRow.querySelector('[data-field="target"]') || {}).value || '_self',
            highlight: 0,
            children: []
        };
    }

    function collectMenuChildren(parentRow) {
        var items = [];
        parentRow.querySelectorAll('.ptprm-menu-children-list > .ptprm-menu-child-row').forEach(function (childRow) {
            var item = collectMenuChildRow(childRow);
            if (item) items.push(item);
        });
        return items;
    }

    function addMenuChildRow(list, data) {
        var tpl = document.getElementById('ptprm-tpl-menu-child-row');
        if (!tpl || !list) return;
        var wrap = document.createElement('div');
        wrap.innerHTML = tpl.innerHTML.trim();
        var row = wrap.firstElementChild;
        list.appendChild(row);
        data = data || {};
        row.querySelector('[data-field="label"]').value = data.label || '';
        row.querySelector('[data-field="url"]').value = data.url || '';
        row.querySelector('[data-field="target"]').value = data.target || '_self';
        updateMenuChildAddState(row.closest('.ptprm-repeater-row'));
    }

    function renderMenuChildren(parentRow, children) {
        var list = parentRow.querySelector('.ptprm-menu-children-list');
        if (!list) return;
        list.innerHTML = '';
        (children || []).forEach(function (child) {
            addMenuChildRow(list, child);
        });
        updateMenuChildAddState(parentRow);
    }

    function updateMenuChildAddState(parentRow) {
        if (!parentRow) return;
        var list = parentRow.querySelector('.ptprm-menu-children-list');
        var btn = parentRow.querySelector('.ptprm-menu-child-add');
        if (!list || !btn) return;
        var max = getMenuSubmenuMax();
        var full = list.children.length >= max;
        btn.disabled = full;
        btn.style.opacity = full ? '0.5' : '';
    }

    function getMenuSubmenuMax() {
        var rep = document.getElementById('ptprm-menu-repeater');
        var n = rep ? parseInt(rep.getAttribute('data-submenu-max'), 10) : 10;
        return isNaN(n) ? 10 : Math.max(1, Math.min(20, n));
    }

    function collectMenuRow(row) {
        var label = (row.querySelector('[data-field="label"]') || {}).value || '';
        label = label.trim();
        if (!label) return null;
        var children = collectMenuChildren(row);
        return {
            label: label,
            url: (row.querySelector('[data-field="url"]') || {}).value || '/',
            target: (row.querySelector('[data-field="target"]') || {}).value || '_self',
            highlight: (row.querySelector('[data-field="highlight"]') || {}).checked ? 1 : 0,
            children: children
        };
    }

    function initMenuChildrenControls() {
        var rep = document.getElementById('ptprm-menu-repeater');
        if (!rep || rep.dataset.ptprmMenuChildrenReady) return;
        rep.dataset.ptprmMenuChildrenReady = '1';
        rep.addEventListener('click', function (e) {
            if (e.target.classList.contains('ptprm-menu-child-add')) {
                e.preventDefault();
                var parentRow = e.target.closest('.ptprm-repeater-row');
                var list = parentRow && parentRow.querySelector('.ptprm-menu-children-list');
                if (!list || list.children.length >= getMenuSubmenuMax()) {
                    window.alert('Batas maksimal submenu tercapai.');
                    return;
                }
                addMenuChildRow(list, {});
                return;
            }
            if (e.target.classList.contains('ptprm-menu-child-remove')) {
                e.preventDefault();
                var childRow = e.target.closest('.ptprm-menu-child-row');
                var parentRow = e.target.closest('.ptprm-repeater-row');
                if (childRow) childRow.remove();
                updateMenuChildAddState(parentRow);
            }
        });
    }

    function initRepeaters() {
        initRepeater('values', '#ptprm-values-repeater', '#ptprm-tpl-value-row', '#ptprm-values-items-json', function (row, data, index) {
            row.querySelector('[data-field="icon"]').value = data.icon || 'users';
            row.querySelector('[data-field="title"]').value = data.title || '';
            row.querySelector('[data-field="desc"]').value = data.desc || '';
        });
        initRepeater('menu', '#ptprm-menu-repeater', '#ptprm-tpl-menu-row', '#ptprm-header-menu-json', function (row, data) {
            row.querySelector('[data-field="label"]').value = data.label || '';
            row.querySelector('[data-field="url"]').value = data.url || '';
            row.querySelector('[data-field="target"]').value = data.target || '_self';
            var hi = row.querySelector('[data-field="highlight"]');
            if (hi) hi.checked = !!data.highlight;
            renderMenuChildren(row, data.children || []);
        });
        initMenuChildrenControls();
        document.querySelectorAll('.ptprm-repeater').forEach(function (wrap) {
            wrap.addEventListener('click', function (e) {
                if (e.target.classList.contains('ptprm-repeater-add')) {
                    e.preventDefault();
                    var list = wrap.querySelector('.ptprm-repeater-list');
                    var type = wrap.getAttribute('data-type');
                    var max = type === 'menu' ? 12 : 24;
                    if (list.children.length >= max) {
                        window.alert('Batas maksimal item tercapai.');
                        return;
                    }
                    var tplId = wrap.getAttribute('data-tpl') || '#ptprm-tpl-value-row';
                    var fillRow = list.ptprmFillRow;
                    var empty = type === 'menu'
                        ? { label: '', url: '/', target: '_self', highlight: 0, children: [] }
                        : { icon: 'users', title: '', desc: '' };
                    addRepeaterRow(list, tplId, list.children.length, empty, fillRow);
                    if (type === 'menu') {
                        var newRow = list.lastElementChild;
                        if (newRow) renderMenuChildren(newRow, []);
                    }
                    updateRepeaterAddState(wrap, list, max);
                }
            });
            wrap.addEventListener('click', function (e) {
                if (e.target.classList.contains('ptprm-repeater-remove')) {
                    e.preventDefault();
                    var row = e.target.closest('.ptprm-repeater-row');
                    if (row && row.parentNode.children.length > 1) {
                        row.remove();
                    }
                    var list = wrap.querySelector('.ptprm-repeater-list');
                    var typeR = wrap.getAttribute('data-type');
                    var maxR = typeR === 'menu' ? 12 : 24;
                    updateRepeaterAddState(wrap, list, maxR);
                }
            });
            var list0 = wrap.querySelector('.ptprm-repeater-list');
            var type0 = wrap.getAttribute('data-type');
            var max0 = type0 === 'menu' ? 12 : 24;
            updateRepeaterAddState(wrap, list0, max0);
        });
    }

    function updateRepeaterAddState(wrap, list, max) {
        var btn = wrap.querySelector('.ptprm-repeater-add');
        if (!btn || !list) return;
        var full = list.children.length >= max;
        btn.disabled = full;
        btn.style.opacity = full ? '0.5' : '';
    }

    function readRepeaterJson(hidden) {
        if (!hidden) return [];
        var b64 = hidden.getAttribute('data-ptprm-json-b64');
        if (b64) {
            try {
                var raw = atob(b64);
                try { raw = decodeURIComponent(escape(raw)); } catch (e2) { /* legacy */ }
                var parsed = JSON.parse(raw);
                hidden.value = JSON.stringify(parsed);
                return Array.isArray(parsed) ? parsed : [];
            } catch (errB64) { /* fall through */ }
        }
        try {
            return JSON.parse(hidden.value || '[]');
        } catch (err) {
            return [];
        }
    }

    function initRepeater(type, wrapSel, tplSel, hiddenSel, fillRow) {
        var wrap = document.querySelector(wrapSel);
        var hidden = document.querySelector(hiddenSel);
        if (!wrap || !hidden) return;
        var list = wrap.querySelector('.ptprm-repeater-list');
        var items = readRepeaterJson(hidden);
        if (!items.length) {
            if (type === 'values') {
                items = [{ icon: 'users', title: '', desc: '' }];
            } else if (type === 'menu') {
                items = [{ label: '', url: '/', target: '_self', highlight: 0, children: [] }];
            } else {
                items = [{ image: '', name: '', role: '', url: '', group: '' }];
            }
        }
        list.innerHTML = '';
        list.ptprmFillRow = fillRow;
        var max = type === 'menu' ? 12 : 24;
        if (items.length > max) {
            items = items.slice(0, max);
        }
        items.forEach(function (data, i) {
            addRepeaterRow(list, tplSel, i, data, fillRow);
        });
        updateRepeaterAddState(wrap, list, max);
    }

    function addRepeaterRow(list, tplSel, index, data, fillRow) {
        var tpl = document.querySelector(tplSel);
        if (!tpl) return;
        var html = tpl.innerHTML.replace(/\{\{index\}\}/g, String(index)).replace(/\{\{num\}\}/g, String(index + 1));
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        var row = wrap.firstElementChild;
        list.appendChild(row);
        if (typeof fillRow === 'function') {
            fillRow(row, data || {}, index);
        }
    }

    function initImageFields() {
        $(document).on('click', '.ptprm-image-pick', function (e) {
            e.preventDefault();
            var $wrap   = $(this).closest('.ptprm-image-field');
            var $input  = $wrap.find('.ptprm-image-value');
            var $prev   = $wrap.find('.ptprm-image-preview');

            var frame = wp.media({
                title: 'Pilih gambar',
                button: { text: 'Pakai gambar ini' },
                multiple: false,
                library: { type: 'image' },
            });

            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                $input.val(att.id).trigger('change');
                var url = att.sizes && att.sizes.large ? att.sizes.large.url : att.url;
                $prev.css('background-image', 'url(' + url + ')');
                triggerPreview($input.get(0));
            });

            frame.open();
        });

        $(document).on('click', '.ptprm-image-clear', function (e) {
            e.preventDefault();
            var $wrap   = $(this).closest('.ptprm-image-field');
            $wrap.find('.ptprm-image-value').val('').trigger('change');
            $wrap.find('.ptprm-image-preview').css('background-image', '');
            triggerPreview($wrap.find('.ptprm-image-value').get(0));
        });
    }

    function initGalleryFields() {
        $('.ptprm-gallery-field').each(function () {
            var $wrap   = $(this);
            var $input  = $wrap.find('.ptprm-gallery-value');
            var $thumbs = $wrap.find('.ptprm-gallery-thumbs');

            function sync() {
                var ids = $thumbs.find('.ptprm-gallery-thumb').map(function () {
                    return $(this).data('id');
                }).get();
                $input.val(ids.join(',')).trigger('change');
            }

            $wrap.find('.ptprm-gallery-add').on('click', function (e) {
                e.preventDefault();
                var current = ($input.val() || '').split(',').map(function (s) { return parseInt(s, 10); }).filter(Boolean);
                var frame = wp.media({
                    title:   'Pilih foto galeri',
                    button:  { text: 'Pakai foto-foto ini' },
                    multiple: 'add',
                    library:  { type: 'image' }
                });
                frame.on('open', function () {
                    var selection = frame.state().get('selection');
                    current.forEach(function (id) {
                        var att = wp.media.attachment(id);
                        att.fetch();
                        selection.add(att ? [ att ] : []);
                    });
                });
                frame.on('select', function () {
                    var sel = frame.state().get('selection').toJSON();
                    $thumbs.empty();
                    sel.forEach(function (att) {
                        var u = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
                        $thumbs.append(
                            $('<span class="ptprm-gallery-thumb"></span>')
                                .attr('data-id', att.id)
                                .css('background-image', "url('" + u + "')")
                                .append('<button type="button" class="ptprm-gallery-remove" aria-label="Hapus">&times;</button>')
                        );
                    });
                    sync();
                });
                frame.open();
            });

            $wrap.on('click', '.ptprm-gallery-remove', function (e) {
                e.preventDefault();
                $(this).closest('.ptprm-gallery-thumb').remove();
                sync();
            });

            $wrap.find('.ptprm-gallery-clear').on('click', function (e) {
                e.preventDefault();
                $thumbs.empty();
                sync();
            });

            if ($thumbs.sortable) {
                $thumbs.sortable({
                    items: '> .ptprm-gallery-thumb',
                    tolerance: 'pointer',
                    cursor: 'grabbing',
                    placeholder: 'ptprm-gallery-thumb-placeholder',
                    forcePlaceholderSize: true,
                    update: sync,
                });
            }
        });
    }

    function initRangeReadouts() {
        $('.ptprm-range input[type=range]').on('input change', function () {
            $(this).closest('.ptprm-range').find('.ptprm-range-val').text($(this).val());
        });
    }

    function initPresets() {
        $('.ptprm-preset-swatch').on('click', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var b64 = $btn.attr('data-preset-bundle');
            if (b64) {
                try {
                    applyPresetBundle(JSON.parse(atob(b64)));
                } catch (err) { /* ignore */ }
            } else {
                setColor('color_primary', $btn.data('primary'));
                setColor('color_accent', $btn.data('accent'));
                setColor('color_text', $btn.data('text'));
                setColor('color_text_soft', $btn.data('textSoft'));
                setColor('color_cream', $btn.data('cream'));
            }
            $('.ptprm-preset-swatch').removeClass('is-active');
            $btn.addClass('is-active');
        });
        $('.ptprm-preset').on('click', function (e) {
            e.preventDefault();
            setColor('color_primary', $(this).data('primary'));
            setColor('color_accent', $(this).data('accent'));
        });
    }

    function syncHomeSectionsOrder() {
        var $list = $('#ptprm-home-sections-sort');
        var $hidden = $('[data-ptprm="home_sections_order"]');
        if (!$list.length || !$hidden.length) return;
        var order = [];
        $list.find('[data-section]').each(function () {
            order.push($(this).data('section'));
        });
        $hidden.val(JSON.stringify(order)).trigger('change');
    }

    function initHomeOrderSortable() {
        var $list = $('#ptprm-home-sections-sort');
        if (!$list.length || typeof $list.sortable !== 'function') return;
        var $hidden = $('[data-ptprm="home_sections_order"]');
        if (!$hidden.length) return;

        syncHomeSectionsOrder();
        $list.sortable({
            handle: '.ptprm-sortable__handle',
            update: syncHomeSectionsOrder
        });
    }

    window.ptprmSyncHomeSectionsOrder = syncHomeSectionsOrder;

    function initPreviewModes() {
        var $preview = $('#ptprm-hero-preview');
        if (!$preview.length) return;
        $('.ptprm-preview-mode').on('click', function (e) {
            e.preventDefault();
            var mode = $(this).data('ptprmPreviewMode') || $(this).attr('data-ptprm-preview-mode');
            if (!mode) return;
            $('.ptprm-preview-mode').removeClass('is-active');
            $(this).addClass('is-active');
            $preview.removeClass('pv-mode-desktop pv-mode-tablet pv-mode-mobile');
            $preview.addClass('pv-mode-' + mode);
            $('[data-ptprm="hero_height_mode"]').first().trigger('change');
        });
    }

    function setColor(key, value) {
        var $input = $('input[data-ptprm="' + key + '"]');
        if (!$input.length) return;
        if ($input.hasClass('ptprm-color') && $input.wpColorPicker) {
            if (value) {
                $input.iris('color', value);
            }
            $input.val(value).trigger('change');
        } else {
            $input.val(value).trigger('change');
        }
    }

    function setRange(key, value) {
        var $input = $('input[data-ptprm="' + key + '"][type="range"]');
        if (!$input.length) return;
        $input.val(value).trigger('input change');
        $input.closest('.ptprm-range').find('.ptprm-range-val').text(value);
    }

    function applyPresetBundle(bundle) {
        if (!bundle || typeof bundle !== 'object') return;
        Object.keys(bundle).forEach(function (key) {
            var v = bundle[key];
            var $input = $('[data-ptprm="' + key + '"]');
            if (!$input.length) return;
            if ($input.attr('type') === 'range') {
                setRange(key, v);
            } else if ($input.hasClass('ptprm-color')) {
                setColor(key, v);
            } else {
                $input.val(v).trigger('change');
            }
        });
        triggerPreview(document.querySelector('[data-ptprm="color_primary"]'));
    }

    function triggerPreview(el) {
        if (!el) return;
        $(el).trigger('input');
    }

    /* ===================================================================== */
    /* LIVE PREVIEW                                                          */
    /* ===================================================================== */

    function initLivePreview() {
        var $preview = $('#ptprm-hero-preview');
        if (!$preview.length) return;

        function val(key) {
            var $f = $('[data-ptprm="' + key + '"]');
            if (!$f.length) return '';
            if ($f.attr('type') === 'checkbox') return $f.is(':checked') ? 1 : 0;
            return $f.val();
        }

        function update() {
            var pv = $preview.get(0);
            var img = document.getElementById('pv-img');
            var overlay = document.getElementById('pv-overlay');
            var inner = document.getElementById('pv-inner');
            var eyebrow = document.getElementById('pv-eyebrow');
            var title = document.getElementById('pv-title');
            var sub = document.getElementById('pv-sub');
            var cta1 = document.getElementById('pv-cta1');
            var cta1l = document.getElementById('pv-cta1-label');
            var cta2 = document.getElementById('pv-cta2');
            var cta2l = document.getElementById('pv-cta2-label');

            var bgImg = '';
            var d = val('hero_img_desktop');
            if (d) {
                if (/^\d+$/.test(d)) {
                    var prev = $('input[data-ptprm="hero_img_desktop"]').closest('.ptprm-image-field').find('.ptprm-image-preview').css('background-image');
                    if (prev && prev !== 'none') {
                        bgImg = prev.replace(/^url\(["']?/, '').replace(/["']?\)$/, '');
                    }
                } else {
                    bgImg = d;
                }
            }
            if (bgImg) {
                img.src = bgImg;
                $preview.attr('data-noimg', '0');
            } else {
                img.removeAttribute('src');
                $preview.attr('data-noimg', '1');
            }
            var imgPos = val('hero_img_position') || 'center 35%';
            img.style.objectPosition = imgPos;
            if (pv) {
                pv.style.setProperty('--pv-img-pos', imgPos);
            }

            // Tinggi preview (mengikuti mode + px custom)
            var heightMode = val('hero_height_mode') || 'standard';
            var previewH = '80vh';
            var previewMax = '560px';
            if (heightMode === 'small') {
                previewH = '60vh';
                previewMax = '420px';
            } else if (heightMode === 'standard') {
                previewH = '80vh';
                previewMax = '560px';
            } else if (heightMode === 'tall') {
                previewH = '90vh';
                previewMax = '620px';
            } else if (heightMode === 'full') {
                previewH = '100%';
                previewMax = '720px';
            } else if (heightMode === 'custom') {
                var isMobile = $preview.hasClass('pv-mode-mobile');
                var px = parseInt(val(isMobile ? 'hero_height_mobile' : 'hero_height_desktop'), 10);
                if (isNaN(px) || px < 320) {
                    px = isMobile ? 560 : 720;
                }
                previewH = px + 'px';
                previewMax = px + 'px';
            }
            $preview.css({ height: previewH, maxHeight: previewMax, minHeight: '240px' });

            // Overlay
            var oa = parseInt(val('hero_overlay_opacity'), 10);
            if (isNaN(oa)) oa = 55;
            var oc = val('hero_overlay_color') || val('color_primary') || '#0A1F3D';
            overlay.style.opacity = (oa / 100);
            overlay.style.background = 'linear-gradient(90deg, ' + oc + ' 0%, rgba(0,0,0,.15) 70%, transparent 100%)';

            // Alignments
            $preview.attr('class', 'ptprm-preview-frame'); // reset
            var ah = val('hero_text_align_h') || 'left';
            var av = val('hero_text_align_v') || 'middle';
            $preview.addClass('pv-align-h-' + ah).addClass('pv-align-v-' + av);

            // Texts
            eyebrow.textContent = val('hero_eyebrow_text') || '';
            eyebrow.style.display = val('hero_eyebrow_show') ? '' : 'none';
            title.textContent = val('hero_title_text') || '';
            title.style.display = val('hero_title_show') ? '' : 'none';
            sub.textContent = val('hero_sub_text') || '';
            sub.style.display = val('hero_sub_show') ? '' : 'none';

            // Eyebrow styles
            eyebrow.style.color = val('hero_eyebrow_color') || '#C9A44C';
            eyebrow.style.fontWeight = val('hero_eyebrow_weight') || '600';
            eyebrow.style.letterSpacing = ((parseInt(val('hero_eyebrow_letter_spacing'),10)||24) / 100) + 'em';
            applyStyleVariant(eyebrow, val('hero_eyebrow_style'));

            // Title styles
            title.style.color = val('hero_title_color') || '#fff';
            title.style.fontWeight = val('hero_title_weight') || '700';
            title.style.letterSpacing = ((parseInt(val('hero_title_letter_spacing'),10)||0) / 100) + 'em';
            title.style.lineHeight = ((parseInt(val('hero_title_line_height'),10)||112) / 100);
            applyStyleVariant(title, val('hero_title_style'));
            applyFont(title, val('hero_title_font'));

            // Sub
            sub.style.color = val('hero_sub_color') || 'rgba(255,255,255,.9)';
            sub.style.fontWeight = val('hero_sub_weight') || '400';
            sub.style.letterSpacing = ((parseInt(val('hero_sub_letter_spacing'),10)||0) / 100) + 'em';
            sub.style.lineHeight = ((parseInt(val('hero_sub_line_height'),10)||170) / 100);
            applyStyleVariant(sub, val('hero_sub_style'));
            applyFont(sub, val('hero_sub_font'));

            // CTA
            cta1l.textContent = val('cta1_label') || '';
            cta1.style.display = val('cta1_show') ? '' : 'none';
            cta1.style.background = val('cta1_bg') || '#C9A44C';
            cta1.style.color = val('cta1_color') || '#0A1F3D';
            cta1.style.borderRadius = (parseInt(val('cta1_radius'),10)||10) + 'px';

            cta2l.textContent = val('cta2_label') || '';
            cta2.style.display = val('cta2_show') ? '' : 'none';
            cta2.style.color = val('cta2_color') || '#fff';
            cta2.style.borderColor = val('cta2_color') || '#fff';
            cta2.style.background = (val('cta2_bg') && val('cta2_bg') !== 'transparent') ? val('cta2_bg') : 'transparent';
            cta2.style.borderRadius = (parseInt(val('cta2_radius'),10)||10) + 'px';
        }

        function applyStyleVariant(el, v) {
            el.style.fontStyle = (v === 'italic') ? 'italic' : 'normal';
            if (v === 'uppercase') el.style.textTransform = 'uppercase';
            else if (v === 'capitalize') el.style.textTransform = 'capitalize';
            else el.style.textTransform = 'none';
        }
        function applyFont(el, font) {
            if (!font || font === 'global') {
                el.style.fontFamily = '';
            } else {
                el.style.fontFamily = '"' + font + '"';
            }
        }

        $(document).on('input change', '[data-ptprm]', update);
        update();
    }
})(jQuery);
