/**
 * ConsentFlow — Frontend Cookie Consent Manager
 *
 * Renders the consent banner, preference modal, floating button.
 * Exposes window.ConsentFlow API.
 * Integrates with Google Consent Mode v2 via gtag().
 *
 * @author  Flavor
 * @license AFL-3.0
 */
(function () {
    'use strict';

    /* ================================================================
       CONFIG (injected by PHP via inline <script>)
       ================================================================ */
    var cfg = window.__consentFlowConfig;
    if (!cfg || !cfg.enabled) return;

    /* Bot detection — don't show banner to crawlers */
    var BOT_REGEX = /bot|crawl|spider|slurp|googlebot|bingbot|yandex|baidu|duckduck|facebookexternalhit|twitterbot|linkedinbot|embedly|quora|pinterest|semrush|ahrefs|screaming/i;
    var isBot = BOT_REGEX.test(navigator.userAgent);

    var COOKIE_NAME = 'flavor_cookie_consent';
    var COOKIE_PATH = '/';
    var CATEGORIES = ['necessary', 'analytics', 'marketing'];
    if (cfg.showFunctional) CATEGORIES.push('functional');

    /* ================================================================
       APPLY CUSTOM COLORS (CSS Variables override)
       ================================================================ */
    if (cfg.colors) {
        var root = document.documentElement;
        if (cfg.colors.primary) root.style.setProperty('--cf-primary', cfg.colors.primary);
        if (cfg.colors.primaryHover) root.style.setProperty('--cf-primary-hover', cfg.colors.primaryHover);
        if (cfg.colors.bg) root.style.setProperty('--cf-bg', cfg.colors.bg);
        if (cfg.colors.text) root.style.setProperty('--cf-text', cfg.colors.text);
        if (cfg.colors.btnText) root.style.setProperty('--cf-btn-text', cfg.colors.btnText);
        if (cfg.colors.borderRadius !== undefined) root.style.setProperty('--cf-radius', cfg.colors.borderRadius + 'px');
    }

    /* ================================================================
       COOKIE HELPERS
       ================================================================ */
    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function setCookie(name, value, days) {
        var maxAge = days * 86400;
        var parts = [
            name + '=' + encodeURIComponent(value),
            'path=' + COOKIE_PATH,
            'max-age=' + maxAge,
            'SameSite=Lax'
        ];
        if (location.protocol === 'https:') parts.push('Secure');
        document.cookie = parts.join('; ');
    }

    function deleteCookie(name) {
        document.cookie = name + '=; path=' + COOKIE_PATH + '; max-age=0';
    }

    /* ================================================================
       CONSENT STATE
       ================================================================ */
    function readConsent() {
        var raw = getCookie(COOKIE_NAME);
        if (!raw) return null;
        try {
            var data = JSON.parse(raw);
            if (data && typeof data.necessary !== 'undefined') return data;
        } catch (e) {}
        return null;
    }

    function writeConsent(consent) {
        consent.necessary = true;
        consent.version = cfg.policyVersion;
        consent.timestamp = Math.floor(Date.now() / 1000);
        setCookie(COOKIE_NAME, JSON.stringify(consent), cfg.cookieLifetime);
        return consent;
    }

    function defaultDenied() {
        var c = { necessary: true, version: cfg.policyVersion, timestamp: 0 };
        c.analytics = false;
        c.marketing = false;
        c.functional = !cfg.showFunctional; // if not shown separately, functional = granted (bundled with necessary)
        return c;
    }

    function allGranted() {
        return { necessary: true, analytics: true, marketing: true, functional: true };
    }

    function allDenied() {
        return { necessary: true, analytics: false, marketing: false, functional: !cfg.showFunctional };
    }

    /* ================================================================
       GOOGLE CONSENT MODE v2
       ================================================================ */

    /**
     * Build Google consent signals from ConsentFlow consent state.
     */
    function buildGoogleSignals(consent) {
        return {
            'analytics_storage': consent.analytics ? 'granted' : 'denied',
            'ad_storage': consent.marketing ? 'granted' : 'denied',
            'ad_user_data': consent.marketing ? 'granted' : 'denied',
            'ad_personalization': consent.marketing ? 'granted' : 'denied',
            'personalization_storage': consent.functional ? 'granted' : 'denied',
            'functionality_storage': 'granted',
            'security_storage': 'granted'
        };
    }

    /**
     * Update consent after user interaction.
     * Note: consent DEFAULT is emitted server-side in <head> before GTM loads
     * (see ConsentFlow::hookDisplayHeader). This function only fires UPDATE.
     */
    function updateGoogleConsent(consent) {
        if (!cfg.googleConsent) return;

        // gtag should exist from setGoogleConsentDefault, but ensure it does
        if (typeof gtag !== 'function') {
            window.dataLayer = window.dataLayer || [];
            window.gtag = function() { window.dataLayer.push(arguments); };
        }

        gtag('consent', 'update', buildGoogleSignals(consent));
    }

    /* ================================================================
       EVENT DISPATCH & CALLBACKS
       ================================================================ */
    var _callbacks = {};

    function fireConsentEvent(consent) {
        var event = new CustomEvent('consentflow:update', { detail: consent });
        document.dispatchEvent(event);

        // Fire registered callbacks
        CATEGORIES.forEach(function (cat) {
            if (consent[cat] && _callbacks[cat]) {
                _callbacks[cat].forEach(function (fn) {
                    try { fn(); } catch (e) { console.error('ConsentFlow callback error:', e); }
                });
                _callbacks[cat] = []; // fire once
            }
        });
    }

    /* ================================================================
       CONSENT LOGGING (AJAX)
       ================================================================ */
    function logConsent(action, consent) {
        if (!cfg.logConsent || !cfg.consentAjaxUrl) return;

        var data = {
            action: action,
            consent: consent,
            version: consent.version
        };
        // Include A/B variant if testing is active
        if (cfg.abEnabled) {
            data.ab_variant = getAbVariant();
        }
        var payload = JSON.stringify(data);

        // Use sendBeacon for non-blocking, fall back to fetch
        if (navigator.sendBeacon) {
            var blob = new Blob([payload], { type: 'application/json' });
            navigator.sendBeacon(cfg.consentAjaxUrl, blob);
        } else {
            fetch(cfg.consentAjaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload,
                keepalive: true
            }).catch(function () {});
        }
    }

    /* ================================================================
       SCRIPT ACTIVATION (<script type="text/plain" data-consentflow>)
       ================================================================ */
    function activateScripts(consent) {
        var scripts = document.querySelectorAll('script[type="text/plain"][data-consentflow]');
        scripts.forEach(function (el) {
            var cat = el.getAttribute('data-consentflow');
            if (consent[cat]) {
                var newScript = document.createElement('script');
                // Copy attributes
                Array.from(el.attributes).forEach(function (attr) {
                    if (attr.name !== 'type' && attr.name !== 'data-consentflow') {
                        newScript.setAttribute(attr.name, attr.value);
                    }
                });
                newScript.textContent = el.textContent;
                el.parentNode.replaceChild(newScript, el);
            }
        });
    }

    /* ================================================================
       DOM RENDERING — Banner
       ================================================================ */
    function renderBanner() {
        var t = cfg.texts;
        var posClass = 'cf-banner--' + cfg.bannerPosition;

        var privacyLink = '';
        if (cfg.privacyUrl && t.CONSENTFLOW_PRIVACY_LINK_TEXT) {
            privacyLink = ' <a href="' + escHtml(cfg.privacyUrl) + '" class="cf-banner__link" target="_blank" rel="noopener">' + escHtml(t.CONSENTFLOW_PRIVACY_LINK_TEXT) + '</a>';
        }

        var html = '<div id="cf-banner" class="cf-banner ' + posClass + '" role="alertdialog" aria-label="Cookie consent">' +
            '<div class="cf-banner__inner">' +
                '<div class="cf-banner__content">' +
                    '<strong class="cf-banner__title">' + escHtml(t.CONSENTFLOW_BANNER_TITLE) + '</strong>' +
                    '<p class="cf-banner__text">' + escHtml(t.CONSENTFLOW_BANNER_TEXT) + privacyLink + '</p>' +
                '</div>' +
                '<div class="cf-banner__actions">' +
                    '<button type="button" class="cf-btn cf-btn--secondary" data-cf-action="reject">' + escHtml(t.CONSENTFLOW_BTN_REJECT_ALL) + '</button>' +
                    '<button type="button" class="cf-btn cf-btn--outline" data-cf-action="preferences">' + escHtml(t.CONSENTFLOW_BTN_PREFERENCES) + '</button>' +
                    '<button type="button" class="cf-btn cf-btn--primary" data-cf-action="accept">' + escHtml(t.CONSENTFLOW_BTN_ACCEPT_ALL) + '</button>' +
                '</div>' +
            '</div>' +
        '</div>';

        // Full-screen backdrop — blocks interaction with the page until the
        // user makes a choice, regardless of banner position (bottom/top/center).
        var backdropHtml = '<div id="cf-banner-backdrop" class="cf-banner-backdrop"></div>';
        document.body.insertAdjacentHTML('beforeend', backdropHtml);

        document.body.insertAdjacentHTML('beforeend', html);

        // Animate in
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                var el = document.getElementById('cf-banner');
                if (el) el.classList.add('cf-banner--visible');
                var bd = document.getElementById('cf-banner-backdrop');
                if (bd) bd.classList.add('cf-banner-backdrop--visible');
            });
        });

        // Event listeners
        document.getElementById('cf-banner').addEventListener('click', function (e) {
            var action = e.target.getAttribute('data-cf-action');
            if (!action) {
                var btn = e.target.closest('[data-cf-action]');
                if (btn) action = btn.getAttribute('data-cf-action');
            }
            if (action === 'accept') handleAcceptAll();
            else if (action === 'reject') handleRejectAll();
            else if (action === 'preferences') { hideBanner(); showModal(); }
        });
    }

    function hideBanner() {
        var el = document.getElementById('cf-banner');
        if (el) {
            el.classList.remove('cf-banner--visible');
            setTimeout(function () { el.remove(); }, 400);
        }
        var bd = document.getElementById('cf-banner-backdrop');
        if (bd) {
            bd.classList.remove('cf-banner-backdrop--visible');
            setTimeout(function () { bd.remove(); }, 400);
        }
    }

    /* ================================================================
       DOM RENDERING — Preference Modal
       ================================================================ */
    function renderModal() {
        var t = cfg.texts;
        var current = readConsent() || defaultDenied();

        // "Forced" mode: the visitor has not made any choice yet (no consent
        // cookie). In this mode the modal cannot be dismissed by clicking the
        // backdrop, the close button, or pressing Escape — the user must pick
        // Reject / Accept / Save. When reached via the floating button (visitor
        // already decided) the modal stays freely dismissible.
        var forced = (readConsent() === null);

        var closeBtnHtml = forced
            ? ''
            : '<button type="button" class="cf-modal__close" aria-label="Close">&times;</button>';

        var categoriesHtml = '';
        categoriesHtml += buildCategoryHtml('necessary', t.CONSENTFLOW_CAT_NECESSARY_TITLE, t.CONSENTFLOW_CAT_NECESSARY_DESC, true, true);
        categoriesHtml += buildCategoryHtml('analytics', t.CONSENTFLOW_CAT_ANALYTICS_TITLE, t.CONSENTFLOW_CAT_ANALYTICS_DESC, current.analytics, false);
        categoriesHtml += buildCategoryHtml('marketing', t.CONSENTFLOW_CAT_MARKETING_TITLE, t.CONSENTFLOW_CAT_MARKETING_DESC, current.marketing, false);
        if (cfg.showFunctional) {
            categoriesHtml += buildCategoryHtml('functional', t.CONSENTFLOW_CAT_FUNCTIONAL_TITLE, t.CONSENTFLOW_CAT_FUNCTIONAL_DESC, current.functional, false);
        }

        var html = '<div id="cf-modal-overlay" class="cf-modal-overlay" role="dialog" aria-modal="true" aria-label="Cookie preferences">' +
            '<div class="cf-modal-overlay__backdrop"></div>' +
            '<div class="cf-modal">' +
                '<div class="cf-modal__header">' +
                    '<h2>' + escHtml(t.CONSENTFLOW_BTN_PREFERENCES || 'Cookie preferences') + '</h2>' +
                    closeBtnHtml +
                '</div>' +
                '<div class="cf-modal__body">' + categoriesHtml + '</div>' +
                '<div class="cf-modal__footer">' +
                    '<button type="button" class="cf-btn cf-btn--secondary" data-cf-action="reject">' + escHtml(t.CONSENTFLOW_BTN_REJECT_ALL) + '</button>' +
                    '<button type="button" class="cf-btn cf-btn--outline" data-cf-action="accept">' + escHtml(t.CONSENTFLOW_BTN_ACCEPT_ALL) + '</button>' +
                    '<button type="button" class="cf-btn cf-btn--primary" data-cf-action="save">' + escHtml(t.CONSENTFLOW_BTN_SAVE) + '</button>' +
                '</div>' +
            '</div>' +
        '</div>';

        document.body.insertAdjacentHTML('beforeend', html);

        var overlay = document.getElementById('cf-modal-overlay');

        // Animate in
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                overlay.classList.add('cf-modal-overlay--visible');
            });
        });

        // Event listeners
        overlay.addEventListener('click', function (e) {
            // Close on backdrop click or close button — but only when the visitor
            // has already made a choice. In forced mode these are ignored so the
            // banner/modal can't be dismissed without giving or refusing consent.
            if (!forced && (e.target.classList.contains('cf-modal-overlay__backdrop') || e.target.classList.contains('cf-modal__close'))) {
                hideModal();
                return;
            }

            // Action buttons
            var action = e.target.getAttribute('data-cf-action');
            if (!action) {
                var btn = e.target.closest('[data-cf-action]');
                if (btn) action = btn.getAttribute('data-cf-action');
            }

            if (action === 'accept') handleAcceptAll();
            else if (action === 'reject') handleRejectAll();
            else if (action === 'save') handleSavePreferences();

            // Accordion toggle
            if (e.target.closest('.cf-category__header')) {
                var cat = e.target.closest('.cf-category');
                if (cat && !e.target.closest('.cf-category__toggle')) {
                    cat.classList.toggle('cf-category--expanded');
                }
            }
        });

        // Focus trap
        trapFocus(overlay);

        // Escape key — disabled in forced mode (no choice made yet)
        overlay._escHandler = function (e) {
            if (e.key === 'Escape' && !forced) hideModal();
        };
        document.addEventListener('keydown', overlay._escHandler);
    }

    function buildCategoryHtml(id, title, desc, checked, alwaysOn) {
        var t = cfg.texts;
        var toggleHtml;
        if (alwaysOn) {
            toggleHtml = '<span class="cf-category__badge">' + escHtml(t.CONSENTFLOW_CAT_ALWAYS_ACTIVE || 'Always active') + '</span>';
        } else {
            toggleHtml = '<label class="cf-category__toggle">' +
                '<input type="checkbox" data-cf-category="' + id + '"' + (checked ? ' checked' : '') + '>' +
                '<span class="cf-toggle-track"><span class="cf-toggle-thumb"></span></span>' +
            '</label>';
        }

        // Cookie list for this category
        var cookieListHtml = '';
        if (cfg.cookies && cfg.cookies[id] && cfg.cookies[id].length > 0) {
            cookieListHtml = '<table class="cf-category__cookies">' +
                '<thead><tr>' +
                    '<th>' + escHtml(t.CONSENTFLOW_TABLE_COOKIE || 'Cookie') + '</th>' +
                    '<th>' + escHtml(t.CONSENTFLOW_TABLE_PROVIDER || 'Provider') + '</th>' +
                    '<th>' + escHtml(t.CONSENTFLOW_TABLE_DURATION || 'Duration') + '</th>' +
                    '<th>' + escHtml(t.CONSENTFLOW_TABLE_DESCRIPTION || 'Description') + '</th>' +
                '</tr></thead><tbody>';
            cfg.cookies[id].forEach(function (c) {
                cookieListHtml += '<tr>' +
                    '<td><code>' + escHtml(c.cookie_name) + '</code></td>' +
                    '<td>' + escHtml(c.provider || '') + '</td>' +
                    '<td>' + escHtml(c.duration || '') + '</td>' +
                    '<td>' + escHtml(c.description || '') + '</td>' +
                '</tr>';
            });
            cookieListHtml += '</tbody></table>';
        }

        return '<div class="cf-category" data-category="' + id + '">' +
            '<div class="cf-category__header">' +
                '<div class="cf-category__info">' +
                    '<span class="cf-category__title">' + escHtml(title) + '</span>' +
                    '<svg class="cf-category__chevron" viewBox="0 0 24 24" width="18" height="18"><path d="M7 10l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' +
                '</div>' +
                toggleHtml +
            '</div>' +
            '<div class="cf-category__body">' +
                '<p class="cf-category__desc">' + escHtml(desc) + '</p>' +
                cookieListHtml +
            '</div>' +
        '</div>';
    }

    function showModal() {
        var existing = document.getElementById('cf-modal-overlay');
        if (existing) existing.remove();
        renderModal();
    }

    function hideModal() {
        var overlay = document.getElementById('cf-modal-overlay');
        if (overlay) {
            if (overlay._escHandler) document.removeEventListener('keydown', overlay._escHandler);
            overlay.classList.remove('cf-modal-overlay--visible');
            setTimeout(function () { overlay.remove(); }, 300);
        }
    }

    /* ================================================================
       DOM RENDERING — Floating Button
       ================================================================ */
    function renderFloatBtn() {
        if (!cfg.showFloatBtn) return;

        var t = cfg.texts;
        var posClass = 'cf-float-btn--' + cfg.floatBtnPosition;

        var html = '<button id="cf-float-btn" class="cf-float-btn ' + posClass + '" aria-label="' + escHtml(t.CONSENTFLOW_FLOAT_BTN_LABEL) + '" title="' + escHtml(t.CONSENTFLOW_FLOAT_BTN_LABEL) + '">' +
            '<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor">' +
                '<path d="M21.6 11.1a1 1 0 00-.9-.5h-.3a2.5 2.5 0 01-2.4-3.1 1 1 0 00-.4-1 1 1 0 00-1-.1 2.5 2.5 0 01-3.1-2.4V3.4a1 1 0 00-.5-.9A10 10 0 1021.6 11.1z"/>' +
                '<circle cx="7.5" cy="12.5" r="1.5" fill="var(--cf-bg, #fff)"/>' +
                '<circle cx="11" cy="16.5" r="1" fill="var(--cf-bg, #fff)"/>' +
                '<circle cx="12" cy="9" r="1.5" fill="var(--cf-bg, #fff)"/>' +
                '<circle cx="16" cy="13" r="1" fill="var(--cf-bg, #fff)"/>' +
            '</svg>' +
        '</button>';

        document.body.insertAdjacentHTML('beforeend', html);

        // Animate in
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                var btn = document.getElementById('cf-float-btn');
                if (btn) btn.classList.add('cf-float-btn--visible');
            });
        });

        document.getElementById('cf-float-btn').addEventListener('click', function () {
            showModal();
        });
    }

    function hideFloatBtn() {
        var btn = document.getElementById('cf-float-btn');
        if (btn) btn.remove();
    }

    /* ================================================================
       CONSENT ACTIONS
       ================================================================ */
    function handleAcceptAll() {
        var consent = writeConsent(allGranted());
        updateGoogleConsent(consent);
        fireConsentEvent(consent);
        logConsent('accept_all', consent);
        activateScripts(consent);
        restoreAllIframes('analytics');
        restoreAllIframes('marketing');
        restoreAllIframes('functional');
        hideBanner();
        hideModal();
        renderFloatBtn();
    }

    function handleRejectAll() {
        var consent = writeConsent(allDenied());
        updateGoogleConsent(consent);
        fireConsentEvent(consent);
        logConsent('reject_all', consent);
        hideBanner();
        hideModal();
        renderFloatBtn();
    }

    function handleSavePreferences() {
        var consent = { necessary: true };
        var checks = document.querySelectorAll('#cf-modal-overlay [data-cf-category]');
        checks.forEach(function (cb) {
            consent[cb.getAttribute('data-cf-category')] = cb.checked;
        });

        // If functional not shown separately, bundle with necessary
        if (!cfg.showFunctional) {
            consent.functional = true;
        }

        consent = writeConsent(consent);
        updateGoogleConsent(consent);
        fireConsentEvent(consent);
        logConsent('custom', consent);
        activateScripts(consent);
        // Restore iframes for consented categories
        CATEGORIES.forEach(function (cat) {
            if (consent[cat]) restoreAllIframes(cat);
        });
        hideBanner();
        hideModal();
        renderFloatBtn();
    }

    /* ================================================================
       FOCUS TRAP (accessibility)
       ================================================================ */
    function trapFocus(container) {
        var focusable = container.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusable.length === 0) return;

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        container.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab') return;
            if (e.shiftKey) {
                if (document.activeElement === first) { e.preventDefault(); last.focus(); }
            } else {
                if (document.activeElement === last) { e.preventDefault(); first.focus(); }
            }
        });

        first.focus();
    }

    /* ================================================================
       UTILS
       ================================================================ */
    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /* ================================================================
       PUBLIC API — window.ConsentFlow
       ================================================================ */
    window.ConsentFlow = {
        hasConsent: function (category) {
            var c = readConsent();
            return c ? !!c[category] : false;
        },

        getConsent: function () {
            return readConsent();
        },

        onConsent: function (category, callback) {
            if (typeof callback !== 'function') return;
            var c = readConsent();
            if (c && c[category]) {
                callback();
                return;
            }
            if (!_callbacks[category]) _callbacks[category] = [];
            _callbacks[category].push(callback);
        },

        showPreferences: function () {
            showModal();
        },

        revokeConsent: function () {
            deleteCookie(COOKIE_NAME);
            var consent = defaultDenied();
            updateGoogleConsent(consent);
            fireConsentEvent(consent);
            logConsent('revoke', consent);
            hideFloatBtn();
            renderBanner();
        },

        hasInteracted: function () {
            return readConsent() !== null;
        },

        loadScript: function (src, category, attrs) {
            function doLoad() {
                var s = document.createElement('script');
                s.src = src;
                if (attrs) {
                    Object.keys(attrs).forEach(function (k) {
                        if (k === 'async') s.async = true;
                        else if (k === 'defer') s.defer = true;
                        else s.setAttribute(k, attrs[k]);
                    });
                }
                document.head.appendChild(s);
            }

            if (window.ConsentFlow.hasConsent(category)) {
                doLoad();
            } else {
                window.ConsentFlow.onConsent(category, doLoad);
            }
        }
    };

    /* ================================================================
       IFRAME BLOCKING
       ================================================================ */
    function processIframes(consent) {
        if (!cfg.iframeBlocking) return;

        var t = cfg.texts;

        // Find iframes with data-consentflow attribute (not yet processed)
        var iframes = document.querySelectorAll('iframe[data-consentflow]:not([data-cf-processed])');
        iframes.forEach(function (iframe) {
            var category = iframe.getAttribute('data-consentflow');
            iframe.setAttribute('data-cf-processed', '1');

            if (consent && consent[category]) {
                // Consent already granted — restore iframe
                restoreIframe(iframe);
                return;
            }

            // Store original src and dimensions
            var src = iframe.getAttribute('src') || iframe.getAttribute('data-src') || '';
            var width = iframe.getAttribute('width') || iframe.offsetWidth || '100%';
            var height = iframe.getAttribute('height') || iframe.offsetHeight || 300;

            iframe.setAttribute('data-cf-original-src', src);
            iframe.removeAttribute('src');
            iframe.style.display = 'none';

            // Create placeholder
            var placeholder = document.createElement('div');
            placeholder.className = 'cf-iframe-placeholder';
            placeholder.setAttribute('data-cf-iframe-placeholder', category);
            placeholder.style.width = (typeof width === 'number' || /^\d+$/.test(width)) ? width + 'px' : width;
            placeholder.style.minHeight = (typeof height === 'number' || /^\d+$/.test(height)) ? height + 'px' : height;

            // Detect content type from src
            var providerIcon = '&#128274;'; // lock emoji as default
            if (/youtube|youtu\.be/i.test(src)) providerIcon = '&#9654;';
            else if (/google.*maps/i.test(src)) providerIcon = '&#128205;';
            else if (/facebook|fb\./i.test(src)) providerIcon = '&#128101;';
            else if (/twitter|x\.com/i.test(src)) providerIcon = '&#128172;';
            else if (/instagram/i.test(src)) providerIcon = '&#128247;';
            else if (/vimeo/i.test(src)) providerIcon = '&#127909;';

            placeholder.innerHTML =
                '<div class="cf-iframe-placeholder__icon"><span style="font-size:24px">' + providerIcon + '</span></div>' +
                '<p class="cf-iframe-placeholder__text">' + escHtml(t.CONSENTFLOW_IFRAME_PLACEHOLDER_TEXT || 'This content requires cookie consent.') + '</p>' +
                '<button type="button" class="cf-iframe-placeholder__btn" data-cf-accept-category="' + escHtml(category) + '">' +
                    escHtml(t.CONSENTFLOW_IFRAME_PLACEHOLDER_BTN || 'Accept and show') +
                '</button>';

            iframe.parentNode.insertBefore(placeholder, iframe);

            // Button click — accept marketing and restore this iframe
            placeholder.querySelector('.cf-iframe-placeholder__btn').addEventListener('click', function () {
                var cat = this.getAttribute('data-cf-accept-category');
                var currentConsent = readConsent() || defaultDenied();
                currentConsent[cat] = true;
                currentConsent = writeConsent(currentConsent);
                updateGoogleConsent(currentConsent);
                fireConsentEvent(currentConsent);
                logConsent('custom', currentConsent);
                // Restore ALL iframes/scripts for this category
                restoreAllIframes(cat);
                activateScripts(currentConsent);
                hideBanner();
            });
        });

        // Also process iframes with data-src (lazy pattern)
        var lazySrcIframes = document.querySelectorAll('iframe[data-consentflow][data-src]:not([src])');
        lazySrcIframes.forEach(function (iframe) {
            var category = iframe.getAttribute('data-consentflow');
            if (consent && consent[category]) {
                iframe.src = iframe.getAttribute('data-src');
            }
        });
    }

    function restoreIframe(iframe) {
        var originalSrc = iframe.getAttribute('data-cf-original-src');
        if (originalSrc) {
            iframe.src = originalSrc;
        } else if (iframe.getAttribute('data-src')) {
            iframe.src = iframe.getAttribute('data-src');
        }
        iframe.style.display = '';
    }

    function restoreAllIframes(category) {
        // Remove placeholders
        var placeholders = document.querySelectorAll('.cf-iframe-placeholder[data-cf-iframe-placeholder="' + category + '"]');
        placeholders.forEach(function (ph) {
            var iframe = ph.nextElementSibling;
            if (iframe && iframe.tagName === 'IFRAME') {
                restoreIframe(iframe);
            }
            ph.remove();
        });
    }

    /* ================================================================
       A/B TESTING — store variant in consent cookie for tracking
       ================================================================ */
    function getAbVariant() {
        return cfg.abVariant || 'A';
    }

    /* ================================================================
       INITIALIZATION
       ================================================================ */
    function init() {
        // Don't show banner to bots/crawlers — they don't need consent UI
        if (isBot) return;

        var consent = readConsent();

        // Check if policy version changed — force re-consent
        if (consent && consent.version !== cfg.policyVersion) {
            deleteCookie(COOKIE_NAME);
            consent = null;
        }

        if (consent) {
            // Returning visitor with valid consent
            // (consent default already emitted server-side in <head>)
            renderFloatBtn();
            activateScripts(consent);
            processIframes(consent);
        } else {
            // First visit — consent default (all denied) already emitted server-side
            processIframes(null);
            renderBanner();
        }
    }

    // Run on DOMContentLoaded if not already loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
