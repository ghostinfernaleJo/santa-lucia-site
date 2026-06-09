/**
 * Santa Lucia Fast Food — Frontend JS
 * Toggle vue cartes/liste, partage (Web Share / WhatsApp / clipboard), browser shortcode
 */
(function () {
    'use strict';

    var LS_VIEW_KEY = 'sl_ff_view';

    /* ===================================================================
       UTILITAIRES
    =================================================================== */
    function getStoredView() {
        try { return localStorage.getItem(LS_VIEW_KEY) || 'cards'; } catch(e) { return 'cards'; }
    }
    function setStoredView(v) {
        try { localStorage.setItem(LS_VIEW_KEY, v); } catch(e) {}
    }

    function applyView(contentEl, view) {
        contentEl.classList.remove('sl-ff-view-cards', 'sl-ff-view-list');
        contentEl.classList.add('sl-ff-view-' + view);
    }

    /* ===================================================================
       TOGGLE VUE (cartes / liste)
    =================================================================== */
    function initViewToggles(root) {
        var btns = root.querySelectorAll('.sl-ff-btn-view');
        if (!btns.length) return;

        var contentEl = root.querySelector('.sl-ff-content, .sl-ff-browser-content');
        if (!contentEl) return;

        var currentView = getStoredView();
        applyView(contentEl, currentView);

        btns.forEach(function(btn) {
            var v = btn.dataset.view;
            btn.setAttribute('aria-pressed', v === currentView ? 'true' : 'false');
            btn.classList.toggle('active', v === currentView);

            btn.addEventListener('click', function() {
                var view = btn.dataset.view;
                applyView(contentEl, view);
                setStoredView(view);
                btns.forEach(function(b) {
                    var active = b.dataset.view === view;
                    b.classList.toggle('active', active);
                    b.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            });
        });
    }

    /* ===================================================================
       PARTAGE
    =================================================================== */
    function initShareButtons(root) {
        root.querySelectorAll('.sl-ff-share-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var url  = btn.dataset.url  || window.location.href;
                var text = btn.dataset.text || 'Menu Fast Food Santa Lucia';
                var full = text + '\n' + url;

                if (navigator.share) {
                    navigator.share({ title: text, url: url }).catch(function(){});
                    return;
                }
                // Fallback : mini-popup
                showSharePopup(btn, url, full);
            });
        });
    }

    function showSharePopup(anchor, url, full) {
        // Supprimer popup existant
        var old = document.getElementById('sl-ff-share-popup');
        if (old) { old.remove(); }

        var pop = document.createElement('div');
        pop.id = 'sl-ff-share-popup';
        pop.className = 'sl-ff-share-popup';
        pop.innerHTML =
            '<button class="sl-ff-share-wa" data-url="' + encodeURIComponent(full) + '">' +
            '<svg viewBox="0 0 24 24" width="18" height="18" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>' +
            ' WhatsApp</button>' +
            '<button class="sl-ff-share-copy">' +
            '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>' +
            ' Copier le lien</button>';

        document.body.appendChild(pop);

        // Positionner pres du bouton
        var rect = anchor.getBoundingClientRect();
        pop.style.position = 'fixed';
        pop.style.top      = (rect.bottom + 6) + 'px';
        pop.style.left     = Math.max(4, rect.left - pop.offsetWidth + rect.width) + 'px';
        pop.style.zIndex   = '99999';

        pop.querySelector('.sl-ff-share-wa').addEventListener('click', function() {
            var waUrl = 'https://api.whatsapp.com/send?text=' + this.dataset.url;
            window.open(waUrl, '_blank');
            pop.remove();
        });

        pop.querySelector('.sl-ff-share-copy').addEventListener('click', function() {
            navigator.clipboard.writeText(url).then(function() {
                pop.querySelector('.sl-ff-share-copy').textContent = '✓ Lien copie !';
                setTimeout(function() { pop.remove(); }, 1200);
            }).catch(function() {
                // Fallback sans clipboard API
                var ta = document.createElement('textarea');
                ta.value = url;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                ta.remove();
                pop.querySelector('.sl-ff-share-copy').textContent = '✓ Lien copie !';
                setTimeout(function() { pop.remove(); }, 1200);
            });
        });

        // Fermer si clic ailleurs
        setTimeout(function() {
            document.addEventListener('click', function closer(ev) {
                if (!pop.contains(ev.target) && ev.target !== anchor) {
                    pop.remove();
                    document.removeEventListener('click', closer);
                }
            });
        }, 0);
    }

    /* ===================================================================
       BROWSER SHORTCODE — chargement AJAX par agence
    =================================================================== */
    function initBrowser(browser) {
        var ajaxurl     = browser.dataset.ajaxurl;
        var nonce       = browser.dataset.nonce;
        var btns        = browser.querySelectorAll('.sl-ff-agency-list button');
        var content     = browser.querySelector('#sl-ff-browser-content');
        var loading     = browser.querySelector('#sl-ff-browser-loading');
        var nomEl       = browser.querySelector('.sl-ff-browser-agence-nom');
        var shareBtn    = browser.querySelector('.sl-ff-browser-share');
        var menuSearch  = browser.querySelector('#sl-ff-menu-search');

        if (!btns.length || !content) return;

        /* --- Recherche dans les plats du menu --- */
        function initMenuSearch() {
            if (!menuSearch) return;
            menuSearch.addEventListener('input', function() {
                var q = this.value.toLowerCase().trim();
                content.querySelectorAll('.sl-ff-item').forEach(function(item) {
                    var titre = (item.querySelector('.sl-ff-item-titre') || {}).textContent || '';
                    var desc  = (item.querySelector('.sl-ff-item-desc')  || {}).textContent || '';
                    var match = !q || titre.toLowerCase().indexOf(q) !== -1 || desc.toLowerCase().indexOf(q) !== -1;
                    item.style.display = match ? '' : 'none';
                });
                // Masquer les sections catégorie vides
                content.querySelectorAll('.sl-ff-cat-section').forEach(function(sec) {
                    var visible = sec.querySelectorAll('.sl-ff-item:not([style*="none"])').length;
                    sec.style.display = visible > 0 ? '' : 'none';
                });
            });
        }
        initMenuSearch();

        btns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var agence = btn.dataset.agence;
                var nom    = btn.dataset.nom;

                // Activer le bouton
                btns.forEach(function(b) { b.classList.remove('active'); });
                btn.classList.add('active');

                // Mettre a jour le titre
                if (nomEl) nomEl.textContent = nom;

                // Mettre a jour le share
                if (shareBtn) {
                    var newUrl = window.location.origin + window.location.pathname +
                        '?agence=' + encodeURIComponent(agence);
                    shareBtn.dataset.url  = newUrl;
                    shareBtn.dataset.text = shareBtn.dataset.text.split(' —')[0] +
                        ' — ' + nom;
                }

                // Charger les repas
                if (loading) loading.style.display = 'flex';
                content.style.opacity = '0.4';

                var formData = new FormData();
                formData.append('action', 'sl_ff_get_menu');
                formData.append('nonce',  nonce);
                formData.append('agence', agence);

                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            content.innerHTML = data.data.html;
                            // Re-appliquer la vue courante
                            var view = getStoredView();
                            applyView(content, view);
                            // Réinitialiser la barre de recherche plats
                            if (menuSearch) menuSearch.value = '';
                        }
                    })
                    .catch(function(e) { console.error('sl-ff:', e); })
                    .finally(function() {
                        if (loading) loading.style.display = 'none';
                        content.style.opacity = '1';
                        // Scroll vers le contenu sur mobile
                        if (window.innerWidth < 768) {
                            content.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    });
            });
        });
    }

    /* ===================================================================
       PANEL FAST FOOD dans la fiche agence (initialise toggle + share)
    =================================================================== */
    function initPanel(panel) {
        initViewToggles(panel);
        initShareButtons(panel);
    }

    // Exposer pour fiche-agence.js
    window.slFFInitPanel = initPanel;

    function initFicheFF() {
        // Initialiser les panels FF qui sont deja visibles au chargement
        document.querySelectorAll('.slf-ff-panel').forEach(function(panel) {
            if (panel.style.display !== 'none') {
                initPanel(panel);
                panel._slffInit = true;
            }
        });
    }

    /* ===================================================================
       INIT GLOBAL
    =================================================================== */
    function init() {
        // Shortcodes [sl_fastfood_menu]
        document.querySelectorAll('.sl-ff-wrap').forEach(function(wrap) {
            initViewToggles(wrap);
            initShareButtons(wrap);
        });

        // Shortcode [sl_fastfood_browser]
        document.querySelectorAll('.sl-ff-browser').forEach(function(browser) {
            initViewToggles(browser);
            initShareButtons(browser);
            initBrowser(browser);
        });

        // Widget fiche agence (Fast Food tab)
        initFicheFF();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
