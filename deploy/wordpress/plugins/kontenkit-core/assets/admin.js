(function ($) {
  'use strict';

  function initTabs() {
    var $btns = $('.kk-tabs__btn');
    var $panels = $('.kk-panel');
    $btns.on('click', function () {
      var tab = $(this).data('tab');
      $btns.removeClass('is-active');
      $(this).addClass('is-active');
      $panels.removeClass('is-active');
      $('#tab-' + tab).addClass('is-active');
    });
  }

  function initColors() {
    if ($.fn.wpColorPicker) {
      $('.kk-color').wpColorPicker();
    }
  }

  $(function () {
    initTabs();
    initColors();
  });
})(jQuery);
