(function ($) {
    'use strict';

    function decodeJsonB64(b64) {
        try {
            return JSON.parse(atob(b64 || '') || '[]');
        } catch (e) {
            return [];
        }
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

    function bindRow($row, hasFeatured) {
        if (hasFeatured) {
            $row.find('.ptprm-board-photo-field').prop('hidden', false);
        }
        $row.find('.ptprm-board-remove').on('click', function () {
            $row.remove();
        });
        $row.find('.ptprm-board-pick-image').on('click', function (e) {
            e.preventDefault();
            if (typeof wp === 'undefined' || !wp.media) {
                return;
            }
            var frame = wp.media({ title: 'Pilih foto pengurus', multiple: false });
            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                $row.find('[data-field="image"]').val(att.id || '');
            });
            frame.open();
        });
    }

    function addRow($list, tpl, data, hasFeatured) {
        var $row = $(tpl).contents().clone();
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
        bindRow($row, hasFeatured);
    }

    $(function () {
        var $list = $('#ptprm-board-admin-list');
        var $form = $('#ptprm-board-admin-form');
        if (!$list.length || !$form.length) {
            return;
        }
        var tpl = $('#ptprm-board-row-tpl').html();
        var hasFeatured = $('#ptprm-board-has-featured').val() === '1';
        var items = decodeJsonB64($list.attr('data-json-b64'));
        if (!items.length) {
            items = [{ role: '', name: '', group: '', image: '', featured: 0 }];
        }
        items.forEach(function (item) {
            addRow($list, tpl, item, hasFeatured);
        });
        $('#ptprm-board-add').on('click', function () {
            addRow($list, tpl, null, hasFeatured);
        });
        $form.on('submit', function () {
            $('#ptprm-board-json').val(JSON.stringify(collectRows($list)));
        });
    });
})(jQuery);
