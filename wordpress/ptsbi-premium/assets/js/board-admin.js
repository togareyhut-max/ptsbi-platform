(function ($) {
    'use strict';

    var CORE_ROLES = ['ketua umum', 'sekretaris umum', 'bendahara umum'];

    function regionHasPhotos() {
        return $('#ptprm-board-has-featured').val() === '1';
    }

    function isCoreRole(role) {
        role = String(role || '').trim().toLowerCase();
        if (!role) {
            return false;
        }
        return CORE_ROLES.some(function (needle) {
            return role === needle || role.indexOf(needle) !== -1;
        });
    }

    function setPhotoFieldVisible($row, visible) {
        var $field = $row.find('.ptprm-board-photo-field');
        if (visible) {
            $field.removeClass('is-hidden');
        } else {
            $field.addClass('is-hidden');
        }
    }

    function syncPhotoVisibility($row) {
        if (!regionHasPhotos()) {
            setPhotoFieldVisible($row, false);
            return;
        }
        if ($row.attr('data-core-photo') === '1' || isCoreRole($row.find('[data-field="role"]').val())) {
            $row.attr('data-core-photo', '1');
            setPhotoFieldVisible($row, true);
            return;
        }
        var featured = $row.find('[data-field="featured"]').is(':checked');
        setPhotoFieldVisible($row, featured);
    }

    function collectRows($list) {
        var items = [];
        $list.find('.ptprm-board-admin-row').each(function () {
            var $row = $(this);
            var name = $.trim($row.find('[data-field="name"]').val());
            if (!name) {
                return;
            }
            var role = $.trim($row.find('[data-field="role"]').val());
            var core = $row.attr('data-core-photo') === '1' || isCoreRole(role);
            items.push({
                role: role,
                name: name,
                group: $.trim($row.find('[data-field="group"]').val()),
                image: $.trim($row.find('[data-field="image"]').val()),
                featured: (core || $row.find('[data-field="featured"]').is(':checked')) ? 1 : 0,
            });
        });
        return items;
    }

    function bindRow($row) {
        syncPhotoVisibility($row);

        $row.find('[data-field="role"]').on('input change', function () {
            syncPhotoVisibility($row);
        });

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
                $row.find('[data-field="image"]').val(att.url || (att.id ? String(att.id) : ''));
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
        $row.removeAttr('data-core-photo');
        $row.find('.ptprm-board-photo-field').addClass('is-hidden');
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
