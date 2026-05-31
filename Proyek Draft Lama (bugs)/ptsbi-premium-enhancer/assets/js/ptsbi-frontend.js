/* global STUDIO_PE */
/* Section Studio - Frontend (v2.1.1)
 * Mode elementor: section Nilai/Kunjungi/Footer = widget Elementor (drag & drop).
 * Mode auto: sisipan JS legacy.
 * Beranda: kontras hero, CTA, berita, perbaikan duplikat.
 */
(function () {
    'use strict';

    var data = window.STUDIO_PE || {};
    var meta = readMeta();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        document.body.classList.add('studio-ready');

        // Apply CSS variables from JS settings (for things that can't be done from PHP options).
        applyCssVars();

        if (meta.isHome) {
            tagHomeSections();
            fixHomeContrast();
            normaliseHeroCTAs();
            sanitizeNewsCards();

            if (shouldAutoInject()) {
                enhanceValuesHome();
                injectVisitBlock();
                trimContactToMapOnly();
                injectPremiumFooter();
                hideNativeFooter();
            } else {
                applyElementorHomeEnhancements();
            }
        } else if (meta.isPage && meta.slug) {
            injectSubpageHero();
            decorateSubpage();
        }

        styleSelengkapnya();
        applyTextReplacements();

        if (meta.isHome && data.stats && data.stats.animate) {
            animateCounters();
        }

        if (data.showBackToTop) {
            mountBackToTop();
        }

        if (data.stickyHeader) {
            applyStickyHeader();
        }

        mountInquiryModal();
    }

    /* -------------------------------------------------------------------- */
    /* META & helpers                                                        */
    /* -------------------------------------------------------------------- */

    function readMeta() {
        try {
            var el = document.getElementById('studio-pe-page-meta');
            if (el) return JSON.parse(el.textContent.trim());
        } catch (e) {}
        return { isHome: true, isPage: false, slug: '', title: '' };
    }

    function applyCssVars() {
        var root = document.documentElement;
        if (data.news) {
            root.style.setProperty('--studio-news-img-h', (data.news.imgHeight || 200) + 'px');
            root.style.setProperty('--studio-news-overlay', (data.news.overlayOpacity || 0));
        }
        // CTA color overrides (used by .studio-cta variant rules)
        if (data.cta1 && data.cta1.bg) root.style.setProperty('--studio-cta1-bg', data.cta1.bg);
        if (data.cta1 && data.cta1.fg) root.style.setProperty('--studio-cta1-fg', data.cta1.fg);
        if (data.cta2 && data.cta2.bg) root.style.setProperty('--studio-cta2-bg', data.cta2.bg);
        if (data.cta2 && data.cta2.fg) root.style.setProperty('--studio-cta2-fg', data.cta2.fg);
    }

    /** Legacy: plugin menyisipkan section lewat JS. */
    function shouldAutoInject() {
        return (data.layoutMode || 'auto') === 'auto';
    }

    function hasStudioWidget(name) {
        return !!document.querySelector('[data-studio-widget="' + name + '"]');
    }

    /** Mode Elementor: widget sudah di halaman; hanya perbaikan duplikat & footer. */
    function applyElementorHomeEnhancements() {
        if (hasStudioWidget('values')) {
            var nativeSec = document.querySelector('[data-studio-section="values"]:not([data-studio-widget])');
            if (nativeSec) {
                nativeSec.classList.add('studio-native-hidden');
            }
            hideDuplicateValueBlocks();
        }
        if (hasStudioWidget('visit')) {
            var oldVisit = findSectionByHeading('kunjungi kami', 'h1, h2');
            if (oldVisit && !oldVisit.querySelector('iframe') && !oldVisit.querySelector('[data-studio-widget="visit"]')) {
                oldVisit.classList.add('studio-native-hidden');
            }
            trimContactToMapOnly();
        }
        if (hasStudioWidget('footer')) {
            document.body.classList.add('studio-footer-injected');
            hideNativeFooter();
        }
    }

    function textOf(el) {
        return (el && el.textContent ? el.textContent.trim() : '').toLowerCase();
    }

    function findSectionByHeading(needle, level) {
        var headings = document.querySelectorAll(level || 'h1, h2, h3, h4');
        for (var i = 0; i < headings.length; i++) {
            if (textOf(headings[i]).indexOf(needle) !== -1) {
                return closestSection(headings[i]);
            }
        }
        return null;
    }

    function closestSection(el) {
        var n = el;
        while (n && n !== document.body) {
            if (n.tagName === 'SECTION' ||
                (n.classList && (n.classList.contains('elementor-section') ||
                                 n.classList.contains('elementor-top-section') ||
                                 n.classList.contains('elementor-section-wrap')))) {
                return n;
            }
            n = n.parentNode;
        }
        return null;
    }

    function tagSection(node, kind) {
        if (node && !node.hasAttribute('data-studio-section')) {
            node.setAttribute('data-studio-section', kind);
        }
    }

    /* -------------------------------------------------------------------- */
    /* 1. SECTION DETECTION                                                  */
    /* -------------------------------------------------------------------- */

    function tagHomeSections() {
        // Hero = the first Elementor section on the home page.
        var firstSection =
            document.querySelector('.elementor-section.elementor-top-section') ||
            document.querySelector('section.elementor-section') ||
            document.querySelector('main .elementor-element-edit-mode') ||
            document.querySelector('main section');
        if (firstSection) tagSection(firstSection, 'hero');

        // About = section that contains the phrase "Menyatukan Keturunan" or "TENTANG"
        tagSection(findSectionByHeading('menyatukan keturunan', 'h1, h2'), 'about');
        var aboutEyebrow = findEyebrow(['tentang ptsbi', 'tentang kami']);
        if (aboutEyebrow) tagSection(closestSection(aboutEyebrow), 'about');

        // Values = section that contains heading "Nilai-Nilai Kami" / eyebrow text
        tagSection(findSectionByHeading('nilai', 'h1, h2, h3'), 'values');
        var valuesEyebrow = findEyebrow(['nilai-nilai kami']);
        if (valuesEyebrow) tagSection(closestSection(valuesEyebrow), 'values');

        // News = section with The Post Grid OR with the heading "Kegiatan Kami"
        var postGrid = document.querySelector('.rt-tpg-container, .rt-tpg-isotope-buttons, .post-grid-container');
        if (postGrid) tagSection(closestSection(postGrid), 'news');
        tagSection(findSectionByHeading('kegiatan kami', 'h1, h2'), 'news');
        tagSection(findSectionByHeading('kebersamaan dalam setiap momen', 'h1, h2'), 'news');

        // Stats = section that contains digits like 10+/500+ as counter widgets
        var counter = document.querySelector('.elementor-counter, .elementor-counter-number');
        if (counter) tagSection(closestSection(counter), 'stats');
        var statsH = findSectionByHeading('cabang wilayah', 'h2, h3, h4, p, span');
        if (statsH) tagSection(statsH, 'stats');

        // Contact = section with "Kunjungi Kami" or "Address:" or a Google Maps iframe
        tagSection(findSectionByHeading('kunjungi kami', 'h1, h2'), 'contact');
        var iframe = document.querySelector('iframe[src*="google.com/maps"], iframe[src*="maps.google"]');
        if (iframe) tagSection(closestSection(iframe), 'contact');

        // Footer = native theme footer
        var footer = document.querySelector('footer.site-footer, #colophon, footer.elementor-location-footer');
        if (footer) tagSection(footer, 'footer');
    }

    function findEyebrow(targets) {
        var nodes = document.querySelectorAll('span, p, h6, .elementor-heading-title, .elementor-widget-text-editor');
        for (var i = 0; i < nodes.length; i++) {
            var t = textOf(nodes[i]);
            for (var j = 0; j < targets.length; j++) {
                if (t === targets[j]) return nodes[i];
            }
        }
        return null;
    }

    /* -------------------------------------------------------------------- */
    /* 2. HERO CTAs                                                          */
    /* -------------------------------------------------------------------- */

    function normaliseHeroCTAs() {
        var hero = document.querySelector('[data-studio-section="hero"]');
        if (!hero) return;

        // Collect ALL anchor elements inside the hero.
        var anchors = Array.prototype.slice.call(hero.querySelectorAll('a'));

        var primaryEl   = null;
        var secondaryEl = null;

        // Try to find existing CTAs by text.
        anchors.forEach(function (a) {
            var t = textOf(a);
            if (!primaryEl && /bergabung/.test(t)) primaryEl = a;
            else if (!secondaryEl && /(hubungi|kontak|wa\b|whatsapp|pertanyaan|informasi|kirim)/.test(t)) secondaryEl = a;
        });

        // === Primary CTA ===
        if (data.cta1 && data.cta1.show) {
            if (!primaryEl) {
                primaryEl = createCTAAnchor();
                placeCTAInHero(hero, primaryEl);
            }
            decorateCTA(primaryEl, data.cta1, 1);
        } else if (primaryEl) {
            primaryEl.setAttribute('data-studio-cta-hidden', 'true');
        }

        // === Secondary CTA ===
        if (data.cta2 && data.cta2.show) {
            if (!secondaryEl) {
                secondaryEl = createCTAAnchor();
                placeCTAInHero(hero, secondaryEl);
            }
            decorateCTA(secondaryEl, data.cta2, 2);
            if (data.cta2.mode === 'inquiry') {
                bindInquiryCTA(secondaryEl);
            }
        } else if (secondaryEl) {
            secondaryEl.setAttribute('data-studio-cta-hidden', 'true');
        }

        // Wrap the CTAs in a .studio-cta-row if they're sitting in distinct widgets.
        wrapHeroCTAs(hero, primaryEl, secondaryEl);
    }

    function createCTAAnchor() {
        var a = document.createElement('a');
        a.setAttribute('data-studio-created', 'true');
        return a;
    }

    function placeCTAInHero(hero, anchor) {
        // Try to place after the first paragraph / text editor widget.
        var container =
            hero.querySelector('.elementor-widget-text-editor') ||
            hero.querySelector('.elementor-widget-button') ||
            hero.querySelector('.elementor-container') ||
            hero;
        var row = container.querySelector('.studio-cta-row');
        if (!row) {
            row = document.createElement('div');
            row.className = 'studio-cta-row';
            container.appendChild(row);
        }
        row.appendChild(anchor);
    }

    function wrapHeroCTAs(hero, p, s) {
        if (!p && !s) return;
        var row = hero.querySelector('.studio-cta-row');
        if (!row) {
            row = document.createElement('div');
            row.className = 'studio-cta-row';
            // Insert near the primary one's grandparent.
            var anchor = p || s;
            if (anchor && anchor.parentNode) {
                anchor.parentNode.appendChild(row);
            }
        }
        if (p && !row.contains(p)) row.appendChild(p);
        if (s && !row.contains(s)) row.appendChild(s);
    }

    function decorateCTA(el, cfg, idx) {
        if (!el) return;

        // Set href.
        if (cfg.url) {
            el.setAttribute('href', cfg.url);
            if (isExternal(cfg.url)) {
                el.setAttribute('target', '_blank');
                el.setAttribute('rel', 'noopener noreferrer');
            }
        } else if (!el.getAttribute('href')) {
            el.setAttribute('href', '#');
        }

        // Set inner content (icon + label).
        var iconHtml = cfg.icon ? svgIcon(cfg.icon) : '';
        var labelHtml = '<span class="studio-cta-label">' + escapeHtml(cfg.label || '') + '</span>';
        el.innerHTML = iconHtml + labelHtml;

        // Classes.
        el.classList.add('studio-cta');
        el.classList.remove('studio-cta-filled', 'studio-cta-outline', 'studio-cta-ghost', 'studio-cta-ghost-light', 'studio-cta-ghost-dark');
        el.classList.add('studio-cta-' + (cfg.style || 'filled'));

        // Inline style overrides for color.
        if (cfg.bg) el.style.setProperty('--studio-cta-bg', cfg.bg);
        if (cfg.fg) el.style.setProperty('--studio-cta-fg', cfg.fg);

        // Remove conflicting elementor button classes (without nuking the element).
        ['elementor-button-link', 'elementor-button', 'elementor-button-primary', 'elementor-size-md',
         'elementor-size-sm', 'elementor-size-lg', 'elementor-animation-grow', 'elementor-button-icon'].forEach(function (c) {
            el.classList.remove(c);
        });
    }

    function isExternal(url) {
        try {
            return (new URL(url, window.location.origin)).origin !== window.location.origin;
        } catch (e) {
            return false;
        }
    }

    /* -------------------------------------------------------------------- */
    /* 2b. INQUIRY FORM (CTA2 → WhatsApp)                                    */
    /* -------------------------------------------------------------------- */

    function mountInquiryModal() {
        if (document.getElementById('studio-inquiry-modal')) {
            return;
        }
        var cfg = data.inquiry || {};
        var modal = document.createElement('div');
        modal.id = 'studio-inquiry-modal';
        modal.className = 'studio-inquiry-modal';
        modal.setAttribute('hidden', 'hidden');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'studio-inquiry-title');
        var defaultMsg = cfg.defaultMessage || 'Horas. Saya minta informasi lebih lanjut tentang PTSBI';
        modal.innerHTML =
            '<div class="studio-inquiry-backdrop" data-studio-inquiry-close></div>' +
            '<div class="studio-inquiry-panel">' +
            '<button type="button" class="studio-inquiry-close" data-studio-inquiry-close aria-label="Tutup">&times;</button>' +
            '<h3 id="studio-inquiry-title">' + escapeHtml(cfg.title || 'Kirim Pertanyaan') + '</h3>' +
            (cfg.hint ? '<p class="studio-inquiry-hint">' + escapeHtml(cfg.hint) + '</p>' : '') +
            '<label class="studio-inquiry-label" for="studio-inquiry-message">Pesan Anda</label>' +
            '<textarea id="studio-inquiry-message" class="studio-inquiry-message" rows="5">' + escapeHtml(defaultMsg) + '</textarea>' +
            '<div class="studio-inquiry-actions">' +
            '<button type="button" class="studio-inquiry-submit studio-cta studio-cta-filled">' +
            escapeHtml(cfg.submitLabel || 'Kirim via WhatsApp') +
            '</button></div></div>';
        document.body.appendChild(modal);

        modal.addEventListener('click', function (e) {
            if (e.target.closest('[data-studio-inquiry-close]')) {
                closeInquiryModal();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hasAttribute('hidden')) {
                closeInquiryModal();
            }
        });
        modal.querySelector('.studio-inquiry-submit').addEventListener('click', submitInquiryForm);
    }

    function bindInquiryCTA(el) {
        if (!el || el.getAttribute('data-studio-inquiry-bound') === '1') {
            return;
        }
        el.setAttribute('data-studio-inquiry-bound', '1');
        el.setAttribute('href', '#');
        el.setAttribute('role', 'button');
        el.addEventListener('click', function (e) {
            e.preventDefault();
            openInquiryModal();
        });
    }

    function openInquiryModal() {
        mountInquiryModal();
        var modal = document.getElementById('studio-inquiry-modal');
        if (!modal) {
            return;
        }
        modal.removeAttribute('hidden');
        document.body.classList.add('studio-inquiry-open');
        var ta = modal.querySelector('#studio-inquiry-message');
        if (ta) {
            ta.focus();
            ta.setSelectionRange(ta.value.length, ta.value.length);
        }
    }

    function closeInquiryModal() {
        var modal = document.getElementById('studio-inquiry-modal');
        if (!modal) {
            return;
        }
        modal.setAttribute('hidden', 'hidden');
        document.body.classList.remove('studio-inquiry-open');
    }

    function waUrlWithText(number, text) {
        var digits = String(number || '').replace(/\D/g, '');
        if (!digits) {
            return '';
        }
        if (digits.indexOf('62') !== 0 && digits.charAt(0) === '0') {
            digits = '62' + digits.slice(1);
        }
        var url = 'https://wa.me/' + digits;
        if (text) {
            url += '?text=' + encodeURIComponent(text);
        }
        return url;
    }

    function submitInquiryForm() {
        var cfg = data.inquiry || {};
        var ta = document.getElementById('studio-inquiry-message');
        var message = ta ? ta.value.trim() : '';
        if (!message) {
            if (ta) {
                ta.focus();
            }
            return;
        }
        var url = waUrlWithText(cfg.whatsappNumber, message);
        if (!url) {
            window.alert('Nomor WhatsApp belum diatur di Section Studio → Global.');
            return;
        }
        window.open(url, '_blank', 'noopener,noreferrer');
        closeInquiryModal();
    }

    function applyStickyHeader() {
        var selectors = [
            '.site-header',
            'header#masthead',
            '.elementor-location-header',
            '.elementor-location-header .elementor-sticky--active'
        ];
        selectors.forEach(function (sel) {
            document.querySelectorAll(sel).forEach(function (el) {
                el.style.setProperty('position', 'sticky', 'important');
                el.style.setProperty('top', '0', 'important');
                el.style.setProperty('z-index', '99990', 'important');
            });
        });
        document.body.classList.add('studio-sticky-header');
    }

    /* -------------------------------------------------------------------- */
    /* 3. SELENGKAPNYA -> small link                                         */
    /* -------------------------------------------------------------------- */

    function styleSelengkapnya() {
        var anchors = document.querySelectorAll('a');
        for (var i = 0; i < anchors.length; i++) {
            var a = anchors[i];
            if (/selengkapnya/i.test(textOf(a))) {
                a.classList.add('studio-link-cta');
                // Remove elementor button classes so it stops looking like a CTA.
                ['elementor-button-link', 'elementor-button', 'elementor-button-primary', 'elementor-size-md',
                 'elementor-size-sm', 'elementor-size-lg', 'elementor-animation-grow'].forEach(function (c) {
                    a.classList.remove(c);
                });
                break;
            }
        }
    }

    /* -------------------------------------------------------------------- */
    /* 4. TEXT REPLACEMENTS                                                  */
    /* -------------------------------------------------------------------- */

    function applyTextReplacements() {
        var rules = data.textRules || [];
        if (!rules.length) return;
        walkText(document.body, function (node) {
            var t = node.nodeValue;
            for (var i = 0; i < rules.length; i++) {
                if (t.indexOf(rules[i].find) !== -1) {
                    t = t.split(rules[i].find).join(rules[i].replace);
                }
            }
            if (t !== node.nodeValue) node.nodeValue = t;
        });
    }

    function walkText(root, cb) {
        var skip = { SCRIPT: 1, STYLE: 1, TEXTAREA: 1, INPUT: 1, NOSCRIPT: 1 };
        var stack = [root];
        while (stack.length) {
            var n = stack.pop();
            if (!n || !n.childNodes) continue;
            for (var i = 0; i < n.childNodes.length; i++) {
                var c = n.childNodes[i];
                if (c.nodeType === 3) {
                    cb(c);
                } else if (c.nodeType === 1 && !skip[c.tagName]) {
                    stack.push(c);
                }
            }
        }
    }

    /* -------------------------------------------------------------------- */
    /* 5. NEWS CARDS sanitize                                                */
    /* -------------------------------------------------------------------- */

    function sanitizeNewsCards() {
        // Force overlay layer (if rendered) to user-configured opacity.
        var newsSec = document.querySelector('[data-studio-section="news"]');
        if (!newsSec) return;

        var overlays = newsSec.querySelectorAll('.overlay, .post-image .overlay, .rt-img-holder .overlay');
        overlays.forEach(function (o) {
            o.style.background = 'rgba(0, 0, 0, ' + ((data.news.overlayOpacity || 0) / 100) + ')';
        });

        // Some Post Grid templates absolute-position the title over image.
        // Move it after the image element.
        var holders = newsSec.querySelectorAll('.rt-holder, .post-grid-item');
        holders.forEach(function (holder) {
            var img = holder.querySelector('.rt-img-holder, .post-image, img');
            var detail = holder.querySelector('.rt-detail, .post-content');
            if (img && detail && detail.compareDocumentPosition(img) & Node.DOCUMENT_POSITION_FOLLOWING) {
                holder.insertBefore(detail, img.nextSibling);
            }
        });
    }

    /* -------------------------------------------------------------------- */
    /* 6. COUNTERS                                                           */
    /* -------------------------------------------------------------------- */

    function animateCounters() {
        if (!('IntersectionObserver' in window)) return;

        var statsSec = document.querySelector('[data-studio-section="stats"]');
        if (!statsSec) return;

        var candidates = statsSec.querySelectorAll('h2, h3, .elementor-counter-number, .elementor-heading-title');
        var targets = [];
        for (var i = 0; i < candidates.length; i++) {
            var raw = (candidates[i].textContent || '').trim();
            if (/^\d+\+?$/.test(raw) || /^\d+/.test(raw)) {
                targets.push(candidates[i]);
            }
        }

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                var raw = (el.textContent || '').trim();
                var m = raw.match(/(\d+)(.*)/);
                if (!m) return;
                var num = parseInt(m[1], 10);
                var suffix = m[2] || '';
                var start = 0;
                var duration = 1200;
                var startTime = null;
                function step(ts) {
                    if (!startTime) startTime = ts;
                    var p = Math.min(1, (ts - startTime) / duration);
                    var eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = Math.floor(start + (num - start) * eased) + suffix;
                    if (p < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
                io.unobserve(el);
            });
        }, { threshold: 0.4 });

        targets.forEach(function (el) { io.observe(el); });
    }

    /* -------------------------------------------------------------------- */
    /* SUB-PAGES: hero banner (desain plugin — tidak mengubah isi)           */
    /* -------------------------------------------------------------------- */

    function injectSubpageHero() {
        if (document.querySelector('.studio-subhero')) return;

        var photoSlugs = { tarombo: true, 'tentang-kami': true };
        var eyebrows = {
            'program-kerja': 'PROGRAM KERJA',
            'informasi-kegiatan': 'INFORMASI KEGIATAN',
            'tarombo': 'WARISAN BUDAYA',
            'struktur-organisasi': 'STRUKTUR ORGANISASI',
            'tentang-kami': 'PROFIL ORGANISASI'
        };
        var subs = {
            'program-kerja': 'Pedoman kegiatan PTSBI dalam mempererat hubungan kekeluargaan dan melestarikan adat.',
            'informasi-kegiatan': 'Kegiatan rutin keluarga besar: pesta bona taon, pertemuan, sosial, budaya, dan generasi muda.',
            'tarombo': 'Penjelasan makna tarombo (silsilah) dalam budaya Batak dan komitmen PTSBI.',
            'struktur-organisasi': 'Susunan kepengurusan dan bidang kegiatan organisasi.',
            'tentang-kami': 'Profil Punguan Toga Samosir Boru Bere Ibebere.'
        };

        var hero = document.createElement('section');
        hero.className = 'studio-subhero ' + (photoSlugs[meta.slug] ? '-photo' : '-pattern');

        if (photoSlugs[meta.slug]) {
            var og = document.querySelector('meta[property="og:image"]');
            if (og && og.content) hero.style.backgroundImage = "url('" + og.content + "')";
        }

        hero.innerHTML =
            '<div class="studio-subhero-inner">' +
                '<nav class="studio-breadcrumb" aria-label="Breadcrumb">' +
                    '<a href="' + escapeAttr(data.home || '/') + '">Beranda</a>' +
                    '<span class="sep">/</span><span>' + escapeHtml(meta.title || '') + '</span>' +
                '</nav>' +
                '<span class="studio-subhero-eyebrow">' + escapeHtml(eyebrows[meta.slug] || (meta.title || '').toUpperCase()) + '</span>' +
                '<h1>' + escapeHtml(meta.title || '') + '</h1>' +
                '<p class="studio-subhero-sub">' + escapeHtml(subs[meta.slug] || '') + '</p>' +
                '<div class="studio-subhero-ornament" aria-hidden="true">' + svgOrnament() + '</div>' +
            '</div>';

        var mount = document.querySelector('#main') || document.querySelector('main') || document.querySelector('.site-content');
        if (mount && mount.parentNode) {
            mount.parentNode.insertBefore(hero, mount);
            document.body.classList.add('studio-subhero-active');
        }
    }

    function decorateSubpage() {
        var content = document.querySelector('.entry-content') || document.querySelector('main .entry-content');
        if (!content) return;

        var nodes = Array.prototype.slice.call(content.querySelectorAll('h2, h3'));
        nodes.forEach(function (h) {
            var txt = (h.textContent || '').trim();
            var m = txt.match(/^(\d+)\.\s*(.+)/);
            if (!m) return;

            var card = document.createElement('div');
            card.className = 'studio-numbered-card';
            card.setAttribute('data-num', m[1]);

            var newHeading = document.createElement(h.tagName.toLowerCase());
            newHeading.textContent = m[2];
            newHeading.style.marginTop = '8px';
            card.appendChild(newHeading);

            var node = h.nextSibling;
            var collected = [];
            while (node) {
                if (node.nodeType === 1) {
                    var tag = node.tagName.toLowerCase();
                    if (tag === h.tagName.toLowerCase() || tag === 'hr') break;
                    if (tag === 'h2' || tag === 'h3') break;
                }
                collected.push(node);
                node = node.nextSibling;
            }
            collected.forEach(function (n) {
                if (n.parentNode) n.parentNode.removeChild(n);
                card.appendChild(n);
            });

            h.parentNode.insertBefore(card, h);
            h.parentNode.removeChild(h);
        });

        if (meta.slug === 'struktur-organisasi') {
            var cards = content.querySelectorAll('.studio-numbered-card');
            if (cards.length) {
                var grid = document.createElement('div');
                grid.className = 'studio-struktur-grid';
                cards[0].parentNode.insertBefore(grid, cards[0]);
                cards.forEach(function (c) { grid.appendChild(c); });
            }
        }
    }

    /* -------------------------------------------------------------------- */
    /* HOMEPAGE: contrast on dark sections (About, etc.)                     */
    /* -------------------------------------------------------------------- */

    function fixHomeContrast() {
        if (!data.fixes || !data.fixes.heroContrast) {
            /* still fix about section — always on home */
        }
        var about = document.querySelector('[data-studio-section="about"]');
        if (about) {
            about.querySelectorAll('p, li, span, .elementor-widget-text-editor').forEach(function (el) {
                if (el.closest('.studio-cta-row') || el.closest('a.studio-cta')) return;
                el.style.color = 'rgba(255, 255, 255, 0.92)';
                el.style.fontStyle = 'normal';
            });
            about.querySelectorAll('h1, h2, h3, h4').forEach(function (el) {
                el.style.color = '#ffffff';
            });
        }
    }

    /* -------------------------------------------------------------------- */
    /* HOMEPAGE: Nilai-Nilai — plugin grid + hover, hide Elementor duplicate  */
    /* -------------------------------------------------------------------- */

    var VALUE_ITEMS = [
        { key: 'kebersamaan',        title: 'Kebersamaan',        icon: 'users',
          desc: 'Kami percaya bahwa kekuatan terbesar terletak pada persatuan dan kebersamaan dalam keluarga besar.' },
        { key: 'kasih',              title: 'Kasih',              icon: 'heart',
          desc: 'Setiap relasi dibangun di atas kasih yang tulus, saling menerima, dan saling menguatkan.' },
        { key: 'pelestarian budaya', title: 'Pelestarian Budaya', icon: 'feather',
          desc: 'Kami menjaga dan meneruskan nilai serta warisan budaya kepada generasi berikutnya.' },
        { key: 'saling mendukung',   title: 'Saling Mendukung',   icon: 'hands',
          desc: 'Dalam suka maupun duka, kami hadir untuk saling menopang dan menguatkan.' }
    ];

    function enhanceValuesHome() {
        if (document.querySelector('.studio-values.studio-injected')) {
            hideDuplicateValueBlocks();
            return;
        }

        var nativeSec = document.querySelector('[data-studio-section="values"]');
        if (nativeSec) {
            scrapeValueDescriptions(nativeSec);
            nativeSec.classList.add('studio-native-hidden');
            nativeSec.removeAttribute('data-studio-section');
        }

        hideDuplicateValueBlocks();

        var section = document.createElement('section');
        section.className = 'studio-values studio-injected';
        section.setAttribute('data-studio-section', 'values');

        var grid = document.createElement('div');
        grid.className = 'studio-values-grid';

        VALUE_ITEMS.forEach(function (item) {
            var card = document.createElement('article');
            card.className = 'studio-value-card';
            card.setAttribute('tabindex', '0');

            card.innerHTML =
                '<div class="studio-value-icon">' + svgIcon(item.icon) + '</div>' +
                '<h3>' + escapeHtml(item.title) + '</h3>' +
                '<p class="studio-value-teaser">Arahkan kursor untuk penjelasan</p>' +
                '<div class="studio-value-tooltip" role="tooltip">' +
                    '<p>' + escapeHtml(item.desc) + '</p>' +
                '</div>';

            grid.appendChild(card);
        });

        section.innerHTML =
            '<span class="studio-eyebrow">NILAI-NILAI KAMI</span>' +
            '<h2 class="studio-values-title">Fondasi yang Kami Pegang Bersama</h2>' +
            '<p class="studio-values-subtitle">Empat nilai inti yang menuntun setiap langkah organisasi dalam menjaga kebersamaan keluarga besar.</p>';
        section.appendChild(grid);

        var mountBefore =
            document.querySelector('[data-studio-section="stats"]') ||
            document.querySelector('[data-studio-section="news"]') ||
            nativeSec;

        if (mountBefore && mountBefore.parentNode) {
            mountBefore.parentNode.insertBefore(section, mountBefore);
        } else {
            var main = document.querySelector('#main, main, .site-content');
            if (main) main.appendChild(section);
        }

        hideDuplicateValueBlocks();
    }

    function scrapeValueDescriptions(sec) {
        VALUE_ITEMS.forEach(function (item) {
            var headings = sec.querySelectorAll('h2, h3, h4');
            for (var i = 0; i < headings.length; i++) {
                if (textOf(headings[i]) === item.key) {
                    var p = nextParagraph(headings[i]);
                    if (p && p.textContent.trim()) {
                        item.desc = p.textContent.trim();
                    }
                    break;
                }
            }
        });
    }

    function nextParagraph(node) {
        var n = node.nextElementSibling;
        while (n) {
            if (n.tagName && n.tagName.toLowerCase() === 'p') return n;
            var p = n.querySelector && n.querySelector('p');
            if (p) return p;
            n = n.nextElementSibling;
        }
        return null;
    }

    function hideDuplicateValueBlocks() {
        var injected =
            document.querySelector('[data-studio-widget="values"]') ||
            document.querySelector('.studio-values.studio-injected') ||
            document.querySelector('.studio-values.studio-elementor');
        var keys = ['kebersamaan', 'kasih', 'pelestarian budaya', 'saling mendukung', 'nilai-nilai kami', 'fondasi yang kami pegang'];

        document.querySelectorAll('h1, h2, h3, h4, h5, .elementor-heading-title').forEach(function (h) {
            if (injected && injected.contains(h)) return;
            var t = textOf(h);
            if (keys.indexOf(t) === -1) return;
            var sec = closestSection(h);
            if (sec && (!injected || sec !== injected)) {
                sec.classList.add('studio-native-hidden');
            }
        });
    }

    /* -------------------------------------------------------------------- */
    /* HOMEPAGE: Kunjungi — alamat plugin, Elementor hanya maps              */
    /* -------------------------------------------------------------------- */

    function injectVisitBlock() {
        if (document.querySelector('.studio-visit.studio-injected')) return;

        var mapSec = document.querySelector('[data-studio-section="contact"]') ||
            closestSection(document.querySelector('iframe[src*="google"], iframe[src*="maps"]'));

        var addr = data.address || 'Ruko Harapan Mulya Regency Blok BG 1-09, Setia Mulya, Tarumajaya, Kab. Bekasi, Jawa Barat.';
        var waLine = '';
        if (data.cta2 && data.cta2.url) {
            waLine = '<div class="studio-visit-line">' + svgIcon('whatsapp') +
                '<span><strong>WhatsApp:</strong><br><a href="' + escapeAttr(data.cta2.url) + '" target="_blank" rel="noopener">Hubungi kami</a></span></div>';
        }

        var section = document.createElement('section');
        section.className = 'studio-visit studio-injected';
        section.setAttribute('data-studio-section', 'contact-info');
        section.innerHTML =
            '<div class="studio-visit-inner">' +
                '<div class="studio-visit-text">' +
                    '<span class="studio-eyebrow">KUNJUNGI KAMI</span>' +
                    '<h2>Mari Terhubung &amp; Bertemu Bersama Keluarga Besar</h2>' +
                    '<p>Kami terbuka untuk silaturahmi, koordinasi kegiatan, dan masukan dari seluruh anggota.</p>' +
                '</div>' +
                '<div class="studio-visit-card">' +
                    '<div class="studio-visit-line">' + svgIcon('pin') +
                        '<span><strong>Alamat:</strong><br>' + escapeHtml(addr) + '</span></div>' +
                    '<div class="studio-visit-line">' + svgIcon('clock') +
                        '<span><strong>Jam Operasional:</strong><br>Senin&ndash;Jumat, 09.00&ndash;17.00 WIB</span></div>' +
                    waLine +
                '</div>' +
            '</div>';

        if (mapSec && mapSec.parentNode) {
            mapSec.parentNode.insertBefore(section, mapSec);
        } else {
            document.body.appendChild(section);
        }

        var oldVisit = findSectionByHeading('kunjungi kami', 'h1, h2');
        if (oldVisit && !oldVisit.querySelector('iframe')) {
            oldVisit.classList.add('studio-native-hidden');
        }
    }

    function trimContactToMapOnly() {
        var sec = document.querySelector('[data-studio-section="contact"]');
        if (!sec) {
            var iframe = document.querySelector('iframe[src*="google"], iframe[src*="maps.google"]');
            if (iframe) sec = closestSection(iframe);
        }
        if (!sec) return;

        sec.classList.add('studio-map-only');
        sec.setAttribute('data-studio-section', 'contact-map');

        var widgets = sec.querySelectorAll('.elementor-widget, .elementor-column-wrap, .elementor-element');
        widgets.forEach(function (w) {
            if (w.querySelector('iframe[src*="google"], iframe[src*="maps"], .elementor-widget-google_maps')) {
                w.classList.add('studio-map-widget');
                return;
            }
            if (w.querySelector('iframe')) return;
            var hasMap = w.closest && w.closest('.studio-map-widget');
            if (!hasMap && !w.classList.contains('studio-map-widget')) {
                var isHeading = w.querySelector && w.querySelector('.elementor-heading-title');
                var text = (w.textContent || '').trim().toLowerCase();
                if (isHeading || /kunjungi|address|alamat|mon-fri|senin|ruko|harapan/i.test(text)) {
                    w.style.setProperty('display', 'none', 'important');
                }
            }
        });

        sec.querySelectorAll('h1, h2, h3, h4, p, .elementor-heading-title').forEach(function (el) {
            if (el.closest('.studio-map-widget')) return;
            if (el.closest('iframe')) return;
            var t = textOf(el);
            if (/kunjungi kami|address|alamat|mon-fri|ruko|harapan|setia mulya/i.test(t)) {
                var wrap = el.closest('.elementor-widget') || el;
                wrap.style.setProperty('display', 'none', 'important');
            }
        });
    }

    /* -------------------------------------------------------------------- */
    /* HOMEPAGE: Footer plugin, sembunyikan footer/copyright Elementor       */
    /* -------------------------------------------------------------------- */

    function injectPremiumFooter() {
        if (document.querySelector('.studio-footer.studio-injected')) return;

        var social = data.social || {};
        var socialHtml = '';
        if (social.facebook)  socialHtml += '<a href="' + escapeAttr(social.facebook) + '" target="_blank" rel="noopener" aria-label="Facebook">' + svgIcon('facebook') + '</a>';
        if (social.instagram) socialHtml += '<a href="' + escapeAttr(social.instagram) + '" target="_blank" rel="noopener" aria-label="Instagram">' + svgIcon('instagram') + '</a>';
        if (social.youtube)   socialHtml += '<a href="' + escapeAttr(social.youtube) + '" target="_blank" rel="noopener" aria-label="YouTube">' + svgIcon('youtube') + '</a>';
        if (social.tiktok)    socialHtml += '<a href="' + escapeAttr(social.tiktok) + '" target="_blank" rel="noopener" aria-label="TikTok">' + svgIcon('tiktok') + '</a>';
        if (data.cta2 && data.cta2.url) {
            socialHtml += '<a href="' + escapeAttr(data.cta2.url) + '" target="_blank" rel="noopener" aria-label="WhatsApp">' + svgIcon('whatsapp') + '</a>';
        }

        var addr = data.address || '';
        var year = new Date().getFullYear();
        var home = data.home || '/';

        var footer = document.createElement('footer');
        footer.className = 'studio-footer studio-injected';
        footer.setAttribute('role', 'contentinfo');
        footer.innerHTML =
            '<div class="studio-footer-grid">' +
                '<div class="studio-footer-brand">' +
                    '<h4>PTSBI</h4>' +
                    '<p>Wadah kebersamaan keluarga besar untuk mempererat persaudaraan, melestarikan budaya, dan saling mendukung.</p>' +
                    (socialHtml ? '<div class="studio-footer-social">' + socialHtml + '</div>' : '') +
                '</div>' +
                '<div><h4>Tautan Cepat</h4><ul>' +
                    '<li><a href="' + escapeAttr(home) + '">Beranda</a></li>' +
                    '<li><a href="' + escapeAttr(home) + 'tentang-kami/">Tentang Kami</a></li>' +
                    '<li><a href="' + escapeAttr(home) + 'program-kerja/">Program Kerja</a></li>' +
                    '<li><a href="' + escapeAttr(home) + 'informasi-kegiatan/">Informasi Kegiatan</a></li>' +
                '</ul></div>' +
                '<div><h4>Organisasi</h4><ul>' +
                    '<li><a href="' + escapeAttr(home) + 'tarombo/">Tarombo</a></li>' +
                    '<li><a href="' + escapeAttr(home) + 'struktur-organisasi/">Struktur Organisasi</a></li>' +
                '</ul></div>' +
                '<div><h4>Kontak</h4>' +
                    '<p>' + escapeHtml(addr) + '</p>' +
                    '<p>Senin&ndash;Jumat, 09.00&ndash;17.00 WIB</p>' +
                '</div>' +
            '</div>' +
            '<div class="studio-footer-bottom">&copy; ' + year + ' PTSBI. Seluruh hak cipta dilindungi.</div>';

        document.body.appendChild(footer);
        document.body.classList.add('studio-footer-injected');
    }

    function hideNativeFooter() {
        if (data.fixes && !data.fixes.doubleFooter) return;

        var sel = [
            'footer.site-footer',
            '#colophon',
            '.ast-footer-overlay',
            'footer.elementor-location-footer',
            '.site-footer-primary-section',
            '.site-below-footer-wrap',
            '.ast-small-footer',
            '.footer-sml-layout-1',
            '.elementor-location-footer'
        ];
        sel.forEach(function (s) {
            document.querySelectorAll(s).forEach(function (el) {
                if (!el.classList.contains('studio-footer')) {
                    el.classList.add('studio-native-hidden');
                }
            });
        });
        document.querySelectorAll('.site-info, .ast-footer-copyright').forEach(function (el) {
            el.classList.add('studio-native-hidden');
        });
    }

    function escapeAttr(s) {
        return escapeHtml(s);
    }

    /* -------------------------------------------------------------------- */
    /* 7. BACK TO TOP                                                        */
    /* -------------------------------------------------------------------- */

    function mountBackToTop() {
        if (document.querySelector('.studio-totop')) return; // already
        var btn = document.createElement('button');
        btn.className = 'studio-totop';
        btn.setAttribute('aria-label', 'Kembali ke atas');
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>';
        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        document.body.appendChild(btn);
        window.addEventListener('scroll', function () {
            btn.classList.toggle('is-visible', (window.scrollY || window.pageYOffset) > 360);
        }, { passive: true });
    }

    /* -------------------------------------------------------------------- */
    /* UTIL                                                                  */
    /* -------------------------------------------------------------------- */

    function escapeHtml(s) {
        return (s + '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function svgIcon(name) {
        var icons = {
            users:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            heart:    '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>',
            feather:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"/><line x1="16" y1="8" x2="2" y2="22"/><line x1="17.5" y1="15" x2="9" y2="15"/></svg>',
            hands:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11V6a2 2 0 1 1 4 0v5"/><path d="M13 11V4a2 2 0 1 1 4 0v8"/><path d="M17 12v-2a2 2 0 1 1 4 0v5a7 7 0 0 1-7 7H8a7 7 0 0 1-7-7v-2a2 2 0 1 1 4 0v2"/></svg>',
            pin:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
            clock:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            whatsapp: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.82 11.82 0 0 1 8.413 3.488 11.82 11.82 0 0 1 3.48 8.414c-.003 6.554-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.45L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.89 9.884a9.86 9.86 0 0 0 1.51 5.26l-.999 3.648 3.979-1.607zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.521.149-.174.198-.298.297-.497.099-.198.05-.372-.025-.521-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.521.074-.793.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413z"/></svg>',
            phone:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
            mail:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
            arrow:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
            facebook: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M22.675 0H1.325C.593 0 0 .593 0 1.325v21.351C0 23.407.593 24 1.325 24H12.82V14.706h-3.131v-3.622h3.131V8.413c0-3.1 1.894-4.788 4.659-4.788 1.325 0 2.464.099 2.795.143v3.24h-1.918c-1.504 0-1.796.715-1.796 1.762v2.31h3.587l-.467 3.622h-3.12V24h6.116c.731 0 1.324-.593 1.324-1.324V1.325C24 .593 23.407 0 22.675 0z"/></svg>',
            instagram:'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 1.366.062 2.633.336 3.608 1.311.975.975 1.249 2.242 1.311 3.608.058 1.266.07 1.646.07 4.85s-.012 3.584-.07 4.85c-.062 1.366-.336 2.633-1.311 3.608-.975.975-2.242 1.249-3.608 1.311-1.266.058-1.646.07-4.85.07s-3.584-.012-4.85-.07c-1.366-.062-2.633-.336-3.608-1.311-.975-.975-1.249-2.242-1.311-3.608-.058-1.266-.07-1.646-.07-4.85s.012-3.584.07-4.85c.062-1.366.336-2.633 1.311-3.608.975-.975 2.242-1.249 3.608-1.311 1.266-.058 1.646-.07 4.85-.07zM12 0C8.741 0 8.332.014 7.052.072 5.775.13 4.602.405 3.635 1.372 2.668 2.339 2.393 3.512 2.335 4.789 2.277 6.069 2.263 6.478 2.263 9.737c0 3.259.014 3.668.072 4.948.058 1.277.333 2.45 1.3 3.417.967.967 2.14 1.242 3.417 1.3 1.28.058 1.689.072 4.948.072s3.668-.014 4.948-.072c1.277-.058 2.45-.333 3.417-1.3.967-.967 1.242-2.14 1.3-3.417.058-1.28.072-1.689.072-4.948s-.014-3.668-.072-4.948c-.058-1.277-.333-2.45-1.3-3.417C19.398.405 18.225.13 16.948.072 15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>',
            youtube:  '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
            tiktok:   '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5.8 20.1a6.34 6.34 0 0 0 10.86-4.43V8.62a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.84 4.84 0 0 1-1.84-.05z"/></svg>'
        };
        return icons[name] || '';
    }

    function svgOrnament() {
        return '<svg viewBox="0 0 200 24" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet">' +
            '<line x1="0" y1="12" x2="80" y2="12" stroke="#C9A44C" stroke-width="1.2"/>' +
            '<line x1="120" y1="12" x2="200" y2="12" stroke="#C9A44C" stroke-width="1.2"/>' +
            '<path d="M85 12 L100 4 L115 12 L100 20 Z" fill="none" stroke="#C9A44C" stroke-width="1.4"/>' +
            '<circle cx="100" cy="12" r="2" fill="#E0BC65"/>' +
            '</svg>';
    }
})();
