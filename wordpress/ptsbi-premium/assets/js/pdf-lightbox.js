/* PDF lightbox — dokumen penuh di popup, halaman tetap di belakang */
(function () {
    'use strict';

    if (!window.PTPRM_PDF || !PTPRM_PDF.items) {
        return;
    }

    var items = PTPRM_PDF.items;
    var box = null;
    var iframe = null;
    var titleEl = null;
    var openTab = null;
    var download = null;
    var currentUrl = '';

    function buildModal() {
        box = document.createElement('div');
        box.className = 'ptprm-pdf-lightbox';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.hidden = true;
        box.innerHTML =
            '<div class="ptprm-pdf-lightbox-panel">' +
            '<header class="ptprm-pdf-lightbox-head">' +
            '<h2 class="ptprm-pdf-lightbox-title"></h2>' +
            '<button type="button" class="ptprm-pdf-lightbox-close" aria-label="' + escapeAttr(PTPRM_PDF.closeLabel || 'Tutup') + '">&times;</button>' +
            '</header>' +
            '<div class="ptprm-pdf-lightbox-body">' +
            '<iframe class="ptprm-pdf-lightbox-frame" title="" loading="lazy"></iframe>' +
            '</div>' +
            '<footer class="ptprm-pdf-lightbox-foot">' +
            '<a class="ptprm-pdf-lightbox-open" href="#" target="_blank" rel="noopener noreferrer"></a>' +
            '<a class="ptprm-pdf-lightbox-download" href="#" download></a>' +
            '</footer>' +
            '</div>';
        document.body.appendChild(box);

        titleEl = box.querySelector('.ptprm-pdf-lightbox-title');
        iframe = box.querySelector('.ptprm-pdf-lightbox-frame');
        openTab = box.querySelector('.ptprm-pdf-lightbox-open');
        download = box.querySelector('.ptprm-pdf-lightbox-download');

        box.querySelector('.ptprm-pdf-lightbox-close').addEventListener('click', close);
        box.addEventListener('click', function (e) {
            if (e.target === box) {
                close();
            }
        });
        document.addEventListener('keydown', onKey);
    }

    function escapeAttr(s) {
        return String(s).replace(/"/g, '&quot;');
    }

    function open(id) {
        var data = items[id];
        if (!data || !data.url) {
            return;
        }
        if (!box) {
            buildModal();
        }

        currentUrl = data.url;
        var title = data.title || 'PDF';

        titleEl.textContent = title;
        iframe.setAttribute('title', title);
        iframe.src = data.url;

        openTab.href = data.url;
        openTab.textContent = PTPRM_PDF.openLabel || 'Buka di tab baru';
        download.href = data.url;
        download.setAttribute('download', '');
        download.textContent = PTPRM_PDF.downloadLabel || 'Unduh PDF';

        box.hidden = false;
        box.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        box.querySelector('.ptprm-pdf-lightbox-close').focus();
    }

    function close() {
        if (!box) {
            return;
        }
        box.classList.remove('is-open');
        box.hidden = true;
        iframe.src = 'about:blank';
        currentUrl = '';
        document.body.style.overflow = '';
    }

    function onKey(e) {
        if (!box || !box.classList.contains('is-open')) {
            return;
        }
        if (e.key === 'Escape') {
            close();
        }
    }

    function onTriggerClick(e) {
        var el = e.target.closest('[data-ptprm-pdf-id]');
        if (!el) {
            return;
        }
        var id = el.getAttribute('data-ptprm-pdf-id');
        if (!id || !items[id]) {
            return;
        }
        e.preventDefault();
        open(id);
    }

    document.addEventListener('click', onTriggerClick);

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        var el = e.target.closest('[data-ptprm-pdf-id]');
        if (!el || el.getAttribute('role') !== 'button') {
            return;
        }
        e.preventDefault();
        open(el.getAttribute('data-ptprm-pdf-id'));
    });
})();
