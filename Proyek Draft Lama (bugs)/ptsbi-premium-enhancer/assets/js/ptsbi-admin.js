/* Section Studio Admin - tab switcher + color picker */
(function ($) {
    'use strict';

    $(function () {
        // Color picker
        if ($.fn && $.fn.wpColorPicker) {
            $('.studio-color').wpColorPicker();
        }

        // Tab switching
        var $tabs   = $('.studio-tabs a');
        var $panels = $('.studio-panel');

        function activate(tab) {
            $tabs.removeClass('is-active');
            $tabs.filter('[data-tab="' + tab + '"]').addClass('is-active');
            $panels.removeClass('is-active');
            $panels.filter('[data-tab-panel="' + tab + '"]').addClass('is-active');
            try {
                window.history.replaceState(null, '', '#tab-' + tab);
            } catch (e) {}
        }

        $tabs.on('click', function (e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            activate(tab);
        });

        // Open tab from hash on load.
        var initialTab = (window.location.hash || '').replace('#tab-', '') || 'global';
        if ($tabs.filter('[data-tab="' + initialTab + '"]').length) {
            activate(initialTab);
        } else {
            activate('global');
        }
    });
})(window.jQuery);
