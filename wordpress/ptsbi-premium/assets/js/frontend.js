/* Premium Plugin — Frontend (minimal) */
(function () {
    'use strict';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        initInquiryCTA();
        initPtprmNav();
        initMenuSubmenus();
        initStickyHeader();
        initValueCardsTouch();
        initHeroSlideshow();
        animateStats();
        initGallerySliders();
        initLightbox();
        initPdfLightbox();
        initPopup();
    }

    /* Hero banner slideshow (hingga 5 gambar) */
    function initHeroSlideshow() {
        var heroes = document.querySelectorAll('[data-ptprm-hero-slideshow]');
        if (!heroes.length) return;
        Array.prototype.forEach.call(heroes, function (hero) {
            initOneHeroSlideshow(hero);
        });
    }

    function initOneHeroSlideshow(hero) {
        var media = hero.querySelector('.ptprm-hero-media');
        if (!media) return;
        var slides = media.querySelectorAll('.ptprm-hero-slide');
        if (slides.length < 2) return;

        var interval = parseInt(hero.dataset.interval, 10) || 5000;
        var pauseHov = hero.dataset.pause === '1';
        var dots = media.querySelectorAll('.ptprm-hero-dot');
        var current = 0;
        var timer = null;

        function goTo(index) {
            current = (index + slides.length) % slides.length;
            Array.prototype.forEach.call(slides, function (slide, i) {
                slide.classList.toggle('is-active', i === current);
            });
            Array.prototype.forEach.call(dots, function (dot, i) {
                dot.classList.toggle('is-active', i === current);
                dot.setAttribute('aria-selected', i === current ? 'true' : 'false');
            });
        }

        function next() {
            goTo(current + 1);
        }

        function start() {
            stop();
            timer = window.setInterval(next, interval);
        }

        function stop() {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        Array.prototype.forEach.call(dots, function (dot) {
            dot.addEventListener('click', function () {
                var idx = parseInt(dot.getAttribute('data-ptprm-hero-dot'), 10);
                if (!isNaN(idx)) {
                    goTo(idx);
                    start();
                }
            });
        });

        if (pauseHov) {
            hero.addEventListener('mouseenter', stop);
            hero.addEventListener('mouseleave', start);
            hero.addEventListener('focusin', stop);
            hero.addEventListener('focusout', start);
        }

        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!reduced) {
            start();
        }
    }

    /** Nilai: hover di desktop; ketuk buka/tutup penjelasan di HP */
    function initValueCardsTouch() {
        var cards = document.querySelectorAll('.ptprm-value-card .ptprm-value-tooltip');
        if (!cards.length) return;
        document.querySelectorAll('.ptprm-value-card').forEach(function (card) {
            if (!card.querySelector('.ptprm-value-tooltip')) return;
            card.addEventListener('click', function () {
                if (window.matchMedia('(hover: hover)').matches) return;
                var open = card.classList.contains('ptprm-value-card--open');
                document.querySelectorAll('.ptprm-value-card--open').forEach(function (c) {
                    c.classList.remove('ptprm-value-card--open');
                });
                if (!open) card.classList.add('ptprm-value-card--open');
            });
        });
    }

    function waDigits(raw) {
        var d = String(raw || '').replace(/\D/g, '');
        if (!d) return '';
        if (d.indexOf('62') !== 0 && d.charAt(0) === '0') {
            d = '62' + d.slice(1);
        }
        return d;
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function initPtprmNav() {
        var header = document.getElementById('ptprm-site-header');
        var navWrap = document.getElementById('ptprm-site-nav');
        if (!header || !navWrap) return;
        var toggle = header.querySelector('.ptprm-site-header__toggle');
        var panel = navWrap.querySelector('.ptprm-mobile-drawer__panel');
        if (!toggle) return;

        function setOpen(open) {
            navWrap.classList.toggle('is-open', open);
            toggle.classList.toggle('is-active', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
            navWrap.setAttribute('aria-hidden', open ? 'false' : 'true');
            document.body.classList.toggle('ptprm-mobile-nav-open', open);
            if (open && panel) {
                var closeBtn = panel.querySelector('.ptprm-mobile-drawer__close');
                if (closeBtn) closeBtn.focus();
            }
        }

        function closeNav() {
            setOpen(false);
        }

        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            setOpen(!navWrap.classList.contains('is-open'));
        });

        navWrap.querySelectorAll('[data-ptprm-drawer-close]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                closeNav();
            });
        });

        navWrap.querySelectorAll('.ptprm-submenu a, .ptprm-menu > li:not(.has-children) > a').forEach(function (link) {
            link.addEventListener('click', function () {
                closeNav();
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && navWrap.classList.contains('is-open')) {
                closeNav();
                toggle.focus();
            }
        });
    }

    function initMenuSubmenus() {
        document.querySelectorAll('.ptprm-submenu-toggle').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var li = btn.closest('li.has-children');
                if (!li) return;
                var open = !li.classList.contains('is-submenu-open');
                var parent = li.parentNode;
                if (parent) {
                    Array.prototype.forEach.call(parent.children, function (other) {
                        if (other === li || !other.classList || !other.classList.contains('has-children')) return;
                        other.classList.remove('is-submenu-open');
                        var ob = other.querySelector('.ptprm-submenu-toggle');
                        if (ob) ob.setAttribute('aria-expanded', 'false');
                    });
                }
                li.classList.toggle('is-submenu-open', open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });
    }

    function initStickyHeader() {
        if (!window.PTPRM || !PTPRM.stickyHeader) return;
        document.body.classList.add('ptprm-sticky-header');
        document.documentElement.classList.add('ptprm-sticky-header');

        var header = findSiteHeader();
        if (!header) return;

        header.classList.add('ptprm-header-fixed');

        function adminTop() {
            if (!document.body.classList.contains('admin-bar')) return 0;
            return window.innerWidth <= 782 ? 46 : 32;
        }

        function lockHeaderPosition() {
            var top = adminTop();
            header.style.setProperty('position', 'fixed', 'important');
            header.style.setProperty('top', top + 'px', 'important');
            header.style.setProperty('left', '0', 'important');
            header.style.setProperty('right', '0', 'important');
            header.style.setProperty('width', '100%', 'important');
            header.style.setProperty('transform', 'none', 'important');
            header.style.setProperty('margin-top', '0', 'important');
        }

        function applyHeaderOffset() {
            lockHeaderPosition();
            var h = Math.ceil(header.getBoundingClientRect().height);
            if (h < 40) h = 80;
            document.documentElement.style.setProperty('--ptprm-header-height', h + 'px');
            document.body.style.setProperty('padding-top', (h + adminTop()) + 'px', 'important');
        }

        applyHeaderOffset();
        window.addEventListener('load', applyHeaderOffset);
        window.addEventListener('resize', applyHeaderOffset);
        window.addEventListener('scroll', lockHeaderPosition, { passive: true });

        if (typeof ResizeObserver !== 'undefined') {
            try {
                new ResizeObserver(applyHeaderOffset).observe(header);
            } catch (e) {}
        }
    }

    function isVisibleHeader(el) {
        if (!el) return false;
        var style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden') return false;
        var rect = el.getBoundingClientRect();
        return rect.height > 0 && rect.width > 0;
    }

    function findSiteHeader() {
        var custom = document.getElementById('ptprm-site-header');
        if (isVisibleHeader(custom)) return custom;

        var masthead = document.querySelector('header#masthead') || document.getElementById('masthead');
        if (isVisibleHeader(masthead)) return masthead;

        var selectors = [
            '.elementor-location-header',
            '.ast-primary-header-bar',
            '.site-header',
            'header.site-header',
            '.ast-main-header-wrap'
        ];
        var best = null;
        var bestH = 0;
        for (var i = 0; i < selectors.length; i++) {
            var nodes = document.querySelectorAll(selectors[i]);
            for (var j = 0; j < nodes.length; j++) {
                var el = nodes[j];
                if (!isVisibleHeader(el)) continue;
                var h = el.offsetHeight;
                if (h > bestH) {
                    bestH = h;
                    best = el;
                }
            }
        }
        return best;
    }

    function initInquiryCTA() {
        if (!window.PTPRM || PTPRM.cta2Mode !== 'inquiry') return;
        mountInquiryModal();
        var triggers = document.querySelectorAll('[data-ptprm-inquiry-cta]');
        Array.prototype.forEach.call(triggers, function (btn) {
            if (btn.getAttribute('data-ptprm-inquiry-bound') === '1') return;
            btn.setAttribute('data-ptprm-inquiry-bound', '1');
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                openInquiryModal();
            });
        });
    }

    function mountInquiryModal() {
        if (document.getElementById('ptprm-inquiry-modal')) return;
        var cfg = (window.PTPRM && PTPRM.inquiry) || {};
        var modal = document.createElement('div');
        modal.id = 'ptprm-inquiry-modal';
        modal.className = 'ptprm-inquiry-modal';
        modal.setAttribute('hidden', '');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'ptprm-inquiry-title');
        var defaultMsg = cfg.defaultMessage || '';
        modal.innerHTML =
            '<div class="ptprm-inquiry-backdrop" data-ptprm-inquiry-close></div>' +
            '<div class="ptprm-inquiry-panel">' +
            '<button type="button" class="ptprm-inquiry-close" data-ptprm-inquiry-close aria-label="Tutup">&times;</button>' +
            '<h3 id="ptprm-inquiry-title">' + escapeHtml(cfg.title || 'Kirim Pertanyaan') + '</h3>' +
            (cfg.hint ? '<p class="ptprm-inquiry-hint">' + escapeHtml(cfg.hint) + '</p>' : '') +
            '<label class="ptprm-inquiry-label" for="ptprm-inquiry-message">Pesan Anda</label>' +
            '<textarea id="ptprm-inquiry-message" class="ptprm-inquiry-message" rows="5">' + escapeHtml(defaultMsg) + '</textarea>' +
            '<div class="ptprm-inquiry-actions">' +
            '<button type="button" class="ptprm-cta ptprm-cta-1 ptprm-cta-solid ptprm-cta-size-medium ptprm-inquiry-submit">' +
            escapeHtml(cfg.submitLabel || 'Kirim via WhatsApp') +
            '</button></div></div>';
        document.body.appendChild(modal);
        modal.addEventListener('click', function (e) {
            if (e.target.closest('[data-ptprm-inquiry-close]')) closeInquiryModal();
        });
        modal.querySelector('.ptprm-inquiry-submit').addEventListener('click', submitInquiryForm);
    }

    function openInquiryModal() {
        var modal = document.getElementById('ptprm-inquiry-modal');
        if (!modal) return;
        modal.removeAttribute('hidden');
        document.body.classList.add('ptprm-inquiry-open');
        var ta = modal.querySelector('#ptprm-inquiry-message');
        if (ta) ta.focus();
    }

    function closeInquiryModal() {
        var modal = document.getElementById('ptprm-inquiry-modal');
        if (!modal) return;
        modal.setAttribute('hidden', '');
        document.body.classList.remove('ptprm-inquiry-open');
    }

    function submitInquiryForm() {
        var cfg = (window.PTPRM && PTPRM.inquiry) || {};
        var ta = document.getElementById('ptprm-inquiry-message');
        var msg = (ta && ta.value.trim()) || '';
        if (!msg) {
            alert('Silakan tulis pesan terlebih dahulu.');
            return;
        }
        var wa = waDigits(window.PTPRM && PTPRM.whatsapp);
        if (!wa) {
            alert('Nomor WhatsApp belum diatur di pengaturan plugin (tab Umum).');
            return;
        }
        window.open('https://wa.me/' + wa + '?text=' + encodeURIComponent(msg), '_blank', 'noopener');
        closeInquiryModal();
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeInquiryModal();
    });

    /* ===================================================================== */
    /* Gallery slider                                                        */
    /* ===================================================================== */

    function initGallerySliders() {
        var sliders = document.querySelectorAll('[data-ptprm-slider]');
        if (!sliders.length) return;
        Array.prototype.forEach.call(sliders, function (slider) {
            initOneSlider(slider);
        });
    }

    function initOneSlider(slider) {
        var track = slider.querySelector('.ptprm-slider-track');
        if (!track) return;
        var slides = track.querySelectorAll('.ptprm-slider-slide');
        if (slides.length < 2) return;

        var perView   = parseInt(slider.dataset.perView, 10) || 4;
        var autoplay  = parseInt(slider.dataset.autoplay, 10) || 0;
        var pauseHov  = slider.dataset.pause === '1';
        var idx       = 0;
        var timer     = null;

        function effectivePerView() {
            var w = window.innerWidth;
            if (w <= 480) return 1;
            if (w <= 768) return 2;
            if (w <= 1024) return 3;
            return perView;
        }

        slider.style.setProperty('--ptprm-pv', perView);
        Array.prototype.forEach.call(slides, function (s) { s.style.setProperty('--ptprm-pv', perView); });

        function maxIdx() { return Math.max(0, slides.length - effectivePerView()); }

        function go(to) {
            idx = Math.max(0, Math.min(to, maxIdx()));
            var slideW = slides[0].getBoundingClientRect().width;
            var gap = parseFloat(getComputedStyle(track).gap) || 14;
            track.style.transform = 'translateX(' + (-1 * idx * (slideW + gap)) + 'px)';
            updateDots();
        }

        function next() { idx >= maxIdx() ? go(0) : go(idx + 1); }
        function prev() { idx <= 0 ? go(maxIdx()) : go(idx - 1); }

        var prevBtn = slider.querySelector('.ptprm-slider-nav.prev');
        var nextBtn = slider.querySelector('.ptprm-slider-nav.next');
        if (prevBtn) prevBtn.addEventListener('click', function () { prev(); resetTimer(); });
        if (nextBtn) nextBtn.addEventListener('click', function () { next(); resetTimer(); });

        // Dots
        var dotsWrap = slider.querySelector('.ptprm-slider-dots');
        var dotEls = [];
        function buildDots() {
            if (!dotsWrap) return;
            dotsWrap.innerHTML = '';
            dotEls = [];
            for (var i = 0; i <= maxIdx(); i++) {
                (function (n) {
                    var b = document.createElement('button');
                    b.className = 'ptprm-slider-dot';
                    b.type = 'button';
                    b.setAttribute('aria-label', 'Slide ' + (n + 1));
                    b.addEventListener('click', function () { go(n); resetTimer(); });
                    dotsWrap.appendChild(b);
                    dotEls.push(b);
                })(i);
            }
            updateDots();
        }
        function updateDots() {
            for (var i = 0; i < dotEls.length; i++) {
                dotEls[i].classList.toggle('is-active', i === idx);
            }
        }

        function start() {
            if (!autoplay) return;
            stop();
            timer = setInterval(next, autoplay);
        }
        function stop() { if (timer) { clearInterval(timer); timer = null; } }
        function resetTimer() { stop(); start(); }

        if (pauseHov) {
            slider.addEventListener('mouseenter', stop);
            slider.addEventListener('mouseleave', start);
            slider.addEventListener('focusin', stop);
            slider.addEventListener('focusout', start);
        }

        var resizeTimer = null;
        window.addEventListener('resize', function () {
            if (resizeTimer) clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                buildDots();
                go(idx);
            }, 120);
        });

        // Touch swipe
        var x0 = null;
        track.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
        track.addEventListener('touchend',   function (e) {
            if (x0 === null) return;
            var dx = e.changedTouches[0].clientX - x0;
            if (Math.abs(dx) > 40) (dx < 0 ? next : prev)();
            x0 = null;
            resetTimer();
        });

        buildDots();
        go(0);
        start();
    }

    /* ===================================================================== */
    /* Pop-up iklan                                                          */
    /* ===================================================================== */

    function initPopup() {
        var popup = document.querySelector('[data-ptprm-popup]');
        if (!popup) return;

        var delay     = parseInt(popup.dataset.delay, 10) || 3000;
        var interval  = parseInt(popup.dataset.interval, 10) || 4500;
        var frequency = popup.dataset.frequency || 'session';

        if (frequency === 'session') {
            try {
                if (sessionStorage.getItem('ptprm_popup_seen') === '1') return;
            } catch (e) {}
        } else if (frequency === 'day') {
            try {
                var ts = parseInt(localStorage.getItem('ptprm_popup_seen_ts'), 10) || 0;
                if (Date.now() - ts < 86400000) return;
            } catch (e) {}
        }

        var slidesWrap = popup.querySelector('[data-ptprm-popup-slides]');
        var slides     = slidesWrap ? slidesWrap.querySelectorAll('.ptprm-popup-slide') : [];
        var dots       = popup.querySelectorAll('.ptprm-popup-dot');
        var idx        = 0;
        var timer      = null;

        function goTo(n) {
            if (!slides.length) return;
            idx = (n + slides.length) % slides.length;
            for (var i = 0; i < slides.length; i++) {
                slides[i].classList.toggle('is-active', i === idx);
            }
            for (var j = 0; j < dots.length; j++) {
                dots[j].classList.toggle('is-active', j === idx);
            }
        }
        function startRotation() {
            if (slides.length <= 1 || timer) return;
            timer = setInterval(function () { goTo(idx + 1); }, interval);
        }
        function stopRotation() {
            if (timer) { clearInterval(timer); timer = null; }
        }

        Array.prototype.forEach.call(dots, function (d) {
            d.addEventListener('click', function () {
                goTo(parseInt(d.dataset.ptprmPopupGoto, 10) || 0);
                stopRotation();
                startRotation();
            });
        });

        function open() {
            popup.removeAttribute('hidden');
            popup.classList.add('is-visible');
            document.body.style.overflow = 'hidden';
            startRotation();
        }
        function close() {
            stopRotation();
            popup.classList.remove('is-visible');
            popup.setAttribute('hidden', '');
            document.body.style.overflow = '';
            try {
                if (frequency === 'session')   sessionStorage.setItem('ptprm_popup_seen', '1');
                else if (frequency === 'day')  localStorage.setItem('ptprm_popup_seen_ts', String(Date.now()));
            } catch (e) {}
        }

        setTimeout(open, delay);

        var closers = popup.querySelectorAll('[data-ptprm-popup-close]');
        Array.prototype.forEach.call(closers, function (el) {
            el.addEventListener('click', close);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && popup.classList.contains('is-visible')) close();
        });
    }

    function initLightbox() {
        var items = document.querySelectorAll('[data-ptprm-lightbox]');
        if (!items.length) return;

        var images = Array.prototype.map.call(items, function (a) { return a.getAttribute('href'); });
        var idx = 0;

        var box = document.createElement('div');
        box.className = 'ptprm-lightbox';
        box.innerHTML =
            '<button class="ptprm-lightbox-close" aria-label="Tutup">&times;</button>' +
            '<button class="ptprm-lightbox-nav prev" aria-label="Sebelumnya">&#8592;</button>' +
            '<img alt="">' +
            '<button class="ptprm-lightbox-nav next" aria-label="Berikutnya">&#8594;</button>';
        document.body.appendChild(box);

        var img    = box.querySelector('img');
        var close  = box.querySelector('.ptprm-lightbox-close');
        var prev   = box.querySelector('.prev');
        var next   = box.querySelector('.next');

        function show(i) {
            idx = (i + images.length) % images.length;
            img.src = images[idx];
            box.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }
        function hide() {
            box.classList.remove('is-open');
            img.src = '';
            document.body.style.overflow = '';
        }

        Array.prototype.forEach.call(items, function (a, i) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                show(i);
            });
        });

        close.addEventListener('click', hide);
        prev.addEventListener('click', function () { show(idx - 1); });
        next.addEventListener('click', function () { show(idx + 1); });
        box.addEventListener('click', function (e) { if (e.target === box) hide(); });
        document.addEventListener('keydown', function (e) {
            if (!box.classList.contains('is-open')) return;
            if (e.key === 'Escape') hide();
            else if (e.key === 'ArrowLeft') show(idx - 1);
            else if (e.key === 'ArrowRight') show(idx + 1);
        });
    }

    function initPdfLightbox() {
        var items = document.querySelectorAll('[data-ptprm-pdf-lightbox]');
        if (!items.length) return;

        var pdfs = Array.prototype.map.call(items, function (a) {
            return {
                url: a.getAttribute('href'),
                title: a.getAttribute('data-title') || a.textContent.trim()
            };
        });
        var idx = 0;

        var box = document.createElement('div');
        box.className = 'ptprm-lightbox ptprm-pdf-lightbox';
        box.innerHTML =
            '<button class="ptprm-lightbox-close" aria-label="Tutup">&times;</button>' +
            '<button class="ptprm-lightbox-nav prev" aria-label="Sebelumnya">&#8592;</button>' +
            '<div class="ptprm-pdf-lightbox-body">' +
            '<p class="ptprm-pdf-lightbox-title"></p>' +
            '<iframe title="PDF" src="" loading="lazy"></iframe>' +
            '</div>' +
            '<button class="ptprm-lightbox-nav next" aria-label="Berikutnya">&#8594;</button>';
        document.body.appendChild(box);

        var iframe = box.querySelector('iframe');
        var titleEl = box.querySelector('.ptprm-pdf-lightbox-title');
        var close  = box.querySelector('.ptprm-lightbox-close');
        var prev   = box.querySelector('.prev');
        var next   = box.querySelector('.next');

        function show(i) {
            idx = (i + pdfs.length) % pdfs.length;
            var item = pdfs[idx];
            if (titleEl) titleEl.textContent = item.title || '';
            if (iframe) iframe.src = item.url + '#view=FitH';
            box.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }
        function hide() {
            box.classList.remove('is-open');
            if (iframe) iframe.src = '';
            document.body.style.overflow = '';
        }

        Array.prototype.forEach.call(items, function (a, i) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                show(i);
            });
        });

        close.addEventListener('click', hide);
        prev.addEventListener('click', function () { show(idx - 1); });
        next.addEventListener('click', function () { show(idx + 1); });
        box.addEventListener('click', function (e) { if (e.target === box) hide(); });
        document.addEventListener('keydown', function (e) {
            if (!box.classList.contains('is-open')) return;
            if (e.key === 'Escape') hide();
            else if (e.key === 'ArrowLeft') show(idx - 1);
            else if (e.key === 'ArrowRight') show(idx + 1);
        });
    }

    function animateStats() {
        if (!window.PTPRM || !PTPRM.animateStats) return;
        if (!('IntersectionObserver' in window)) return;
        var nodes = document.querySelectorAll('[data-ptprm-counter]');
        if (!nodes.length) return;

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                var raw = (el.textContent || '').trim();
                var m = raw.match(/(\d+)(.*)$/);
                if (!m) return;
                var target = parseInt(m[1], 10);
                var suffix = m[2] || '';
                if (!target) return;

                var start = 0;
                var dur = 1200;
                var t0 = null;
                function step(ts) {
                    if (!t0) t0 = ts;
                    var p = Math.min(1, (ts - t0) / dur);
                    var eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = Math.floor(start + (target - start) * eased) + suffix;
                    if (p < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
                io.unobserve(el);
            });
        }, { threshold: 0.4 });

        nodes.forEach(function (n) { io.observe(n); });
    }
})();
