(function ($) {
    'use strict';

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
        var auto = $row.attr('data-auto-featured') === '1';
        setPhotoFieldVisible($row, featured || auto);
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

    function bindRow($row) {
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
    }

    function cloneRowFromTemplate($list) {
        var $tpl = $('#ptprm-board-row-tpl .ptprm-board-admin-row').first();
        if (!$tpl.length) {
            return null;
        }
        var $row = $tpl.clone();
        $row.find('input[type="text"], input[type="url"]').val('');
        $row.find('input[type="checkbox"]').prop('checked', false);
        $row.removeAttr('data-auto-featured');
        $list.append($row);
        bindRow($row);
        return $row;
    }

    function syncJson($list) {
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

        $list.find('.ptprm-board-admin-row').each(function () {
            bindRow($(this));
        });

        if (!$list.find('.ptprm-board-admin-row').length) {
            cloneRowFromTemplate($list);
        }

        $('#ptprm-board-add').on('click', function () {
            cloneRowFromTemplate($list);
        });

        $form.on('submit', function (e) {
            if (!syncJson($list)) {
                e.preventDefault();
                window.alert('Setiap baris pengurus harus memiliki nama sebelum disimpan.');
            }
        });

        $form.find('button[type="submit"]').on('click', function () {
            syncJson($list);
        });
    });
})(jQuery);
