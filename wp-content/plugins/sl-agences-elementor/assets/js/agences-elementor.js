/**
 * Santa Lucia – Widget "Nos Agences" v5
 * + Barre de recherche temps réel
 * + Filtre combiné (ville + statut)
 * + Statut automatique selon horaires
 * + Pagination complète
 */
(function () {
    'use strict';

    function SLAgences(wrapper) {
        this.wrapper   = wrapper;
        this.id        = wrapper.id.replace('sl-agences-', '');
        this.parPage   = parseInt(wrapper.getAttribute('data-par-page') || '9', 10);
        this.vue       = wrapper.getAttribute('data-vue') || 'grid';

        this.pageActuelle = 1;
        this.catActive    = 'all';
        this.statutActif  = 'all';
        this.recherche    = '';

        this.grid       = document.getElementById('sl-grid-' + this.id);
        this.pagination = document.getElementById('sl-pagination-' + this.id);
        this.compteur   = wrapper.querySelector('.sl-compteur-num');
        this.allCardsEl = document.getElementById('sl-all-cards-' + this.id);

        // Extraire toutes les cartes et calculer le statut auto dès maintenant
        this.cartesGrid = [];
        this.cartesList = [];

        if (this.allCardsEl) {
            var allCards = Array.from(this.allCardsEl.querySelectorAll('.sl-agence-card'));
            var gridCards = allCards.filter(function (c) { return !c.classList.contains('sl-list-card'); });
            var listCards = allCards.filter(function (c) { return c.classList.contains('sl-list-card'); });

            for (var i = 0; i < gridCards.length; i++) {
                var gc = gridCards[i];
                var lc = listCards[i] || null;
                var nom = gc.getAttribute('data-nom') || '';
                var statut = gc.getAttribute('data-statut') || 'ferme';

                // Résoudre le statut "auto" selon l'heure actuelle
                if (statut === 'auto') {
                    statut = this.calculerStatut(gc.getAttribute('data-h-ouv'), gc.getAttribute('data-h-ferm'));
                    gc.setAttribute('data-statut-resolu', statut);
                    if (lc) lc.setAttribute('data-statut-resolu', statut);
                } else {
                    gc.setAttribute('data-statut-resolu', statut);
                    if (lc) lc.setAttribute('data-statut-resolu', statut);
                }

                // Mettre à jour les badges "auto"
                this.updateStatutBadges(gc, statut);
                if (lc) this.updateStatutBadges(lc, statut);

                this.cartesGrid.push({
                    el: gc,
                    elList: lc,
                    cat: gc.getAttribute('data-cat') || '',
                    nom: nom,
                    statut: statut,
                    idx: i
                });
            }
        }

        this.cartesFiltrees = this.cartesGrid.slice();
        this.init();
    }

    /* ---- Calculer si ouvert ou fermé selon l'heure ---- */
    SLAgences.prototype.calculerStatut = function (hOuv, hFerm) {
        if (!hOuv || !hFerm) return 'ferme';
        var now = new Date();
        var hNow = now.getHours() * 60 + now.getMinutes();

        var parseH = function (str) {
            var parts = (str || '').split(':');
            return parseInt(parts[0] || 0) * 60 + parseInt(parts[1] || 0);
        };

        var ouv = parseH(hOuv);
        var ferm = parseH(hFerm);

        if (ferm < ouv) {
            // Cas nuit (ex: 22:00 – 06:00)
            return (hNow >= ouv || hNow < ferm) ? 'ouvert' : 'ferme';
        }
        return (hNow >= ouv && hNow < ferm) ? 'ouvert' : 'ferme';
    };

    /* ---- Mettre à jour les badges "auto" ---- */
    SLAgences.prototype.updateStatutBadges = function (card, statut) {
        card.querySelectorAll('.sl-statut-auto, .sl-list-statut.sl-statut-auto').forEach(function (badge) {
            badge.className = badge.className.replace('sl-statut-auto', '').trim();
            if (statut === 'ouvert') {
                badge.classList.add('sl-ouvert');
                badge.textContent = 'Ouverte';
            } else {
                badge.classList.add('sl-ferme');
                badge.textContent = 'Fermée';
            }
        });
    };

    SLAgences.prototype.init = function () {
        this.bindSearch();
        this.bindFiltreBtn();
        this.bindFilterCats();
        this.bindStatutFilter();
        this.bindSort();
        this.bindVueSwitch();
        this.appliquerFiltreTri();
    };

    /* ---- Barre de recherche ---- */
    SLAgences.prototype.bindSearch = function () {
        var self = this;
        var input = document.getElementById('sl-search-' + this.id);
        var clearBtn = document.getElementById('sl-search-clear-' + this.id);
        if (!input) return;

        input.addEventListener('input', function () {
            self.recherche = input.value.trim().toLowerCase();
            clearBtn && (clearBtn.style.display = self.recherche ? 'flex' : 'none');
            self.pageActuelle = 1;
            self.appliquerFiltreTri();
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                input.value = '';
                self.recherche = '';
                clearBtn.style.display = 'none';
                self.pageActuelle = 1;
                self.appliquerFiltreTri();
                input.focus();
            });
        }
    };

    /* ---- Bouton Filtre (ouvrir/fermer) ---- */
    SLAgences.prototype.bindFiltreBtn = function () {
        var btn = document.getElementById('sl-filtre-btn-' + this.id);
        var dd  = document.getElementById('sl-filter-dd-' + this.id);
        if (!btn || !dd) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            dd.classList.toggle('open');
            btn.classList.toggle('active');
        });
        document.addEventListener('click', function () {
            dd.classList.remove('open');
            btn.classList.remove('active');
        });
        dd.addEventListener('click', function (e) { e.stopPropagation(); });
    };

    /* ---- Filtres par catégorie ---- */
    SLAgences.prototype.bindFilterCats = function () {
        var self = this;
        var btns = this.wrapper.querySelectorAll('.sl-filter-cat');
        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                btns.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                self.catActive    = btn.getAttribute('data-cat');
                self.pageActuelle = 1;
                self.appliquerFiltreTri();
                var dd = document.getElementById('sl-filter-dd-' + self.id);
                if (dd) dd.classList.remove('open');
                var filtreBtn = document.getElementById('sl-filtre-btn-' + self.id);
                if (filtreBtn) filtreBtn.classList.remove('active');
            });
        });
    };

    /* ---- Filtre Statut (Toutes / Ouvertes / Fermées) ---- */
    SLAgences.prototype.bindStatutFilter = function () {
        var self = this;
        var btns = this.wrapper.querySelectorAll('.sl-statut-btn');
        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                btns.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                self.statutActif  = btn.getAttribute('data-statut');
                self.pageActuelle = 1;
                self.appliquerFiltreTri();
            });
        });
    };

    /* ---- Tri ---- */
    SLAgences.prototype.bindSort = function () {
        var self = this;
        var sel  = document.getElementById('sl-sort-' + this.id);
        if (!sel) return;
        sel.addEventListener('change', function () {
            self.triActif     = sel.value;
            self.pageActuelle = 1;
            self.appliquerFiltreTri();
        });
    };

    /* ---- Switch Grille / Liste ---- */
    SLAgences.prototype.bindVueSwitch = function () {
        var self = this;
        var btns = this.wrapper.querySelectorAll('.sl-vue-btn');
        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                btns.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                self.vue = btn.getAttribute('data-vue');
                self.grid.classList.remove('grid-view', 'list-view');
                self.grid.classList.add(self.vue + '-view');
                self.render();
            });
        });
    };

    /* ---- Appliquer Filtre + Tri ---- */
    SLAgences.prototype.appliquerFiltreTri = function () {
        var self = this;

        this.cartesFiltrees = this.cartesGrid.filter(function (c) {
            // Filtre ville
            if (self.catActive !== 'all' && c.cat !== self.catActive) return false;
            // Filtre statut
            if (self.statutActif !== 'all' && c.statut !== self.statutActif) return false;
            // Recherche
            if (self.recherche && c.nom.indexOf(self.recherche) === -1) return false;
            return true;
        });

        // Tri
        var tri = this.triActif || 'recent';
        switch (tri) {
            case 'alpha':
                this.cartesFiltrees.sort(function (a, b) { return a.nom.localeCompare(b.nom); });
                break;
            case 'alpha_desc':
                this.cartesFiltrees.sort(function (a, b) { return b.nom.localeCompare(a.nom); });
                break;
            default:
                this.cartesFiltrees.sort(function (a, b) { return a.idx - b.idx; });
        }

        if (this.compteur) {
            this.compteur.textContent = this.cartesFiltrees.length;
        }

        this.render();
        this.renderPagination();
    };

    /* ---- Render les cartes de la page courante ---- */
    SLAgences.prototype.render = function () {
        if (!this.grid) return;

        var debut = (this.pageActuelle - 1) * this.parPage;
        var fin   = debut + this.parPage;
        var page  = this.cartesFiltrees.slice(debut, fin);

        this.grid.innerHTML = '';

        if (page.length === 0) {
            var msg = this.recherche
                ? 'Aucune agence trouvée pour "' + this.recherche + '".'
                : 'Aucune agence trouvée.';
            this.grid.innerHTML = '<p class="sl-no-result">' + msg + '</p>';
            return;
        }

        var isListView = (this.vue === 'list');
        var frag = document.createDocumentFragment();

        page.forEach(function (c, i) {
            var sourceEl = isListView ? (c.elList || c.el) : c.el;
            var el = sourceEl.cloneNode(true);
            el.style.opacity = '0';
            el.style.transform = 'translateY(12px)';
            frag.appendChild(el);
            setTimeout(function () {
                el.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            }, i * 50);
        });

        this.grid.appendChild(frag);
    };

    /* ---- Render Pagination ---- */
    SLAgences.prototype.renderPagination = function () {
        var self       = this;
        var total      = this.cartesFiltrees.length;
        var nbPages    = Math.ceil(total / this.parPage);
        var container  = this.pagination;

        if (!container) return;
        if (nbPages <= 1) { container.innerHTML = ''; return; }

        var html = '<ul class="sl-pagination">';

        if (this.pageActuelle > 1) {
            html += '<li><a class="sl-pagination-prev" data-page="' + (this.pageActuelle - 1) + '" href="#">← Précédent</a></li>';
        }

        var startPage = 1, endPage = nbPages;
        if (nbPages > 7) {
            if (this.pageActuelle <= 4) { endPage = 5; }
            else if (this.pageActuelle >= nbPages - 3) { startPage = nbPages - 4; }
            else { startPage = this.pageActuelle - 2; endPage = this.pageActuelle + 2; }
        }

        if (startPage > 1) {
            html += '<li><a data-page="1" href="#">1</a></li>';
            if (startPage > 2) html += '<li><span class="sl-pagination-dots">…</span></li>';
        }

        for (var i = startPage; i <= endPage; i++) {
            var cls = (i === this.pageActuelle) ? 'current' : '';
            html += '<li><a class="' + cls + '" data-page="' + i + '" href="#">' + i + '</a></li>';
        }

        if (endPage < nbPages) {
            if (endPage < nbPages - 1) html += '<li><span class="sl-pagination-dots">…</span></li>';
            html += '<li><a data-page="' + nbPages + '" href="#">' + nbPages + '</a></li>';
        }

        if (this.pageActuelle < nbPages) {
            html += '<li><a class="sl-pagination-next" data-page="' + (this.pageActuelle + 1) + '" href="#">Suivant →</a></li>';
        }

        html += '</ul>';
        container.innerHTML = html;

        container.querySelectorAll('a[data-page]').forEach(function (lien) {
            lien.addEventListener('click', function (e) {
                e.preventDefault();
                self.pageActuelle = parseInt(lien.getAttribute('data-page'), 10);
                self.render();
                self.renderPagination();
                self.wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    };

    /* ---- Initialisation globale ---- */
    function initAll() {
        document.querySelectorAll('.sl-agences-wrapper').forEach(function (wrapper) {
            if (!wrapper.getAttribute('data-sl-init')) {
                wrapper.setAttribute('data-sl-init', '1');
                new SLAgences(wrapper);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    var bindEl = function () {
        if (window.elementorFrontend) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/sl_agences.default', function ($scope) {
                var el = $scope[0] || $scope;
                var wrapper = el.querySelector ? el.querySelector('.sl-agences-wrapper') : null;
                if (!wrapper && el.classList && el.classList.contains('sl-agences-wrapper')) wrapper = el;
                if (wrapper) { wrapper.removeAttribute('data-sl-init'); new SLAgences(wrapper); }
            });
        }
    };
    bindEl();
    document.addEventListener('elementor/frontend/init', bindEl);

})();
