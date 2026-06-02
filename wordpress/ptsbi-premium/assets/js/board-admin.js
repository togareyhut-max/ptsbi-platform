(function ($) {
    'use strict';

    function decodeJsonB64(b64) {
        try {
            return JSON.parse(atob(b64 || '') || '[]');
        } catch (e) {
            return [];
        }
    }

    function rowFromTemplate(tpl) {
        var $parsed = $($.trim(tpl));
        if ($parsed.length && $parsed.first().prop('tagName') === 'LI') {
            return $parsed.first();
        }
        return $parsed.filter('.ptprm-board-admin-row').first();
    }

    function collectRows($list) {
        var items = [];
        $list.find('.ptprm-board-admin-row').each(function () {
            var $row = $(this);
            var name = $.trim($row.find('[data-field="name"]').val());
            if (!name) {
                return;
            }
            items.push({
                role: $.trim($row.find('[data-field="role"]').val()),
                name: name,
                group: $.trim($row.find('[data-field="group"]').val()),
                image: $.trim($row.find('[data-field="image"]').val()),
                featured: $row.find('[data-field="featured"]').is(':checked') ? 1 : 0,
            });
        });
        return items;
    }

    function regionHasPhotos() {
        return $('#ptprm-board-has-featured').val() === '1';
    }

    function setPhotoFieldVisible($row, visible) {
        var $field = $row.find('.ptprm-board-photo-field');
        if (visible) {
            $field.removeAttr('hidden').show();
        } else {
            $field.attr('hidden', 'hidden').hide();
        }
    }

    function syncPhotoVisibility($row) {
        if (!regionHasPhotos()) {
            setPhotoFieldVisible($row, false);
            return;
        }
        var featured = $row.find('[data-field="featured"]').is(':checked');
        setPhotoFieldVisible($row, featured);
    }

    function bindRow($row) {
        var hasPhotos = regionHasPhotos();

        syncPhotoVisibility($row);

        $row.find('[data-field="featured"]').on('change', function () {
            syncPhotoVisibility($row);
        });

        $row.find('.ptprm-board-remove').on('click', function () {
            $row.remove();
        });

        $row.find('.ptprm-board-pick-image').on('click', function (e) {
            e.preventDefault();
            if (typeof wp === 'undefined' || !wp.media) {
                window.alert('Perpustakaan media tidak tersedia. Tempel URL foto secara manual.');
                return;
            }
            var frame = wp.media({ title: 'Pilih foto pengurus', multiple: false });
            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                var value = att.url || (att.id ? String(att.id) : '');
                $row.find('[data-field="image"]').val(value);
            });
            frame.open();
        });

        if (hasPhotos && $row.find('[data-field="featured"]').is(':checked')) {
            setPhotoFieldVisible($row, true);
        }
    }

    function addRow($list, tpl, data) {
        var $row = rowFromTemplate(tpl);
        if (!$row.length) {
            return;
        }
        if (data) {
            $row.find('[data-field="role"]').val(data.role || '');
            $row.find('[data-field="name"]').val(data.name || '');
            $row.find('[data-field="group"]').val(data.group || '');
            $row.find('[data-field="image"]').val(data.image || '');
            if (data.featured) {
                $row.find('[data-field="featured"]').prop('checked', true);
            }
        }
        $list.append($row);
        bindRow($row);
    }

    function syncJson($form, $list) {
        var items = collectRows($list);
        $('#ptprm-board-json').val(JSON.stringify(items));
        return items.length;
    }

    $(function () {
        var $list = $('#ptprm-board-admin-list');
        var $form = $('#ptprm-board-admin-form');
        if (!$list.length || !$form.length) {
            return;
        }
        var tpl = $('#ptprm-board-row-tpl').html();
        var items = decodeJsonB64($list.attr('data-json-b64'));
        if (!items.length) {
            items = [{ role: '', name: '', group: '', image: '', featured: 0 }];
        }
        items.forEach(function (item) {
            addRow($list, tpl, item);
        });
        $('#ptprm-board-add').on('click', function () {
            addRow($list, tpl, null);
        });
        $form.on('submit', function (e) {
            if (!syncJson($form, $list)) {
                e.preventDefault();
                window.alert('Setiap baris pengurus harus memiliki nama sebelum disimpan.');
            }
        });
        $form.find('button[type="submit"]').on('click', function () {
            syncJson($form, $list);
        });
    });
})(jQuery);
