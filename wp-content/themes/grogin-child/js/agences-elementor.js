/**
 * Santa Lucia - Widget Elementor "Nos Agences"
 * Gère : Filtres, Tri, Switch Grille/Liste, Pagination
 */
(function () {
    'use strict';

    // Icône boutique SVG (défaut si pas d'avatar)
    var STORE_ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="#888"><path d="M4 4h56v8H4zm2 10h52l-4 22H10zm6 28h36v18H12zm4 4v10h28V46z"/></svg>';

    function SLAgences(wrapper) {
        this.wrapper   = wrapper;
        this.id        = wrapper.id.replace('sl-agences-', '');
        this.agences   = JSON.parse(wrapper.getAttribute('data-agences') || '[]');
        this.parPage   = parseInt(wrapper.getAttribute('data-par-page') || '9', 10);
        this.colonnes  = parseInt(wrapper.getAttribute('data-colonnes') || '3', 10);
        this.vue       = wrapper.getAttribute('data-vue') || 'grid';

        this.pageActuelle = 1;
        this.catActive    = 'all';
        this.triActif     = 'recent';
        this.agencesFiltrees = this.agences.slice();

        this.grid       = document.getElementById('sl-grid-' + this.id);
        this.pagination = document.getElementById('sl-pagination-' + this.id);
        this.compteur   = wrapper.querySelector('.sl-compteur-num');

        this.init();
    }

    SLAgences.prototype.init = function () {
        this.bindFiltreBtn();
        this.bindFilterCats();
        this.bindSort();
        this.bindVueSwitch();
        this.appliquerFiltreTri();
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

                // Fermer le dropdown
                var dd = document.getElementById('sl-filter-dd-' + self.id);
                if (dd) dd.classList.remove('open');
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
            });
        });
    };

    /* ---- Appliquer Filtre + Tri + Render ---- */
    SLAgences.prototype.appliquerFiltreTri = function () {
        var self = this;

        // 1. Filtrer
        this.agencesFiltrees = this.agences.filter(function (a) {
            if (self.catActive === 'all') return true;
            return a.categorie === self.catActive;
        });

        // 2. Trier
        switch (this.triActif) {
            case 'alpha':
                this.agencesFiltrees.sort(function (a, b) { return a.nom.localeCompare(b.nom); });
                break;
            case 'alpha_desc':
                this.agencesFiltrees.sort(function (a, b) { return b.nom.localeCompare(a.nom); });
                break;
            default: // recent : ordre original (id)
                this.agencesFiltrees.sort(function (a, b) { return a.id - b.id; });
        }

        // 3. Mettre à jour compteur
        if (this.compteur) {
            this.compteur.textContent = this.agencesFiltrees.length;
        }

        // 4. Render
        this.render();
        this.renderPagination();
    };

    /* ---- Render les cartes ---- */
    SLAgences.prototype.render = function () {
        var self  = this;
        var debut = (this.pageActuelle - 1) * this.parPage;
        var fin   = debut + this.parPage;
        var page  = this.agencesFiltrees.slice(debut, fin);

        if (!this.grid) return;

        if (page.length === 0) {
            this.grid.innerHTML = '<p class="sl-no-result">Aucune agence trouvée.</p>';
            return;
        }

        this.grid.innerHTML = page.map(function (a) {
            return self.renderCarte(a);
        }).join('');
    };

    SLAgences.prototype.renderCarte = function (a) {
        var banniereStyle = a.banniere
            ? 'background-image: url(' + a.banniere + ');'
            : '';

        var avatarContent = a.avatar
            ? '<img src="' + a.avatar + '" alt="' + a.nom + '" />'
            : STORE_ICON_SVG;

        var vedetteBadge = a.vedette
            ? '<div class="sl-featured-badge">Featured</div>'
            : '';

        var statutBadge = '';
        if (a.statut === 'ouvert') {
            statutBadge = '<span class="sl-statut sl-ouvert">Ouverte</span>';
        } else if (a.statut === 'ferme') {
            statutBadge = '<span class="sl-statut sl-ferme">Fermée</span>';
        }

        var telHtml = a.tel
            ? '<p class="sl-card-tel"><span class="sl-tel-icon">&#9742;</span> ' + a.tel + '</p>'
            : '';

        var adresseHtml = a.adresse
            ? '<p class="sl-card-adresse">' + a.adresse + '</p>'
            : '';

        var target = a.target || '_self';

        return '<div class="sl-agence-card" data-cat="' + a.categorie + '">' +
            '<a href="' + a.lien + '" target="' + target + '" class="sl-card-banner-link">' +
                '<div class="sl-card-banner" style="' + banniereStyle + '">' +
                    vedetteBadge +
                    statutBadge +
                    '<div class="sl-card-info">' +
                        '<h3 class="sl-card-nom">' + a.nom + '</h3>' +
                        adresseHtml +
                        telHtml +
                    '</div>' +
                    '<div class="sl-card-avatar">' +
                        avatarContent +
                    '</div>' +
                '</div>' +
            '</a>' +
            '<div class="sl-card-footer">' +
                '<a href="' + a.lien + '" target="' + target + '" class="sl-btn-arrow" title="Voir l\'agence" aria-label="Voir ' + a.nom + '">' +
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>' +
                '</a>' +
            '</div>' +
        '</div>';
    };

    /* ---- Render Pagination ---- */
    SLAgences.prototype.renderPagination = function () {
        var self       = this;
        var total      = this.agencesFiltrees.length;
        var nbPages    = Math.ceil(total / this.parPage);
        var container  = this.pagination;

        if (!container) return;

        if (nbPages <= 1) {
            container.innerHTML = '';
            return;
        }

        var html = '<ul class="sl-pagination">';

        // Précédent
        if (this.pageActuelle > 1) {
            html += '<li><a class="sl-pagination-prev" data-page="' + (this.pageActuelle - 1) + '" href="#">← Prev</a></li>';
        }

        // Numéros
        for (var i = 1; i <= nbPages; i++) {
            var cls = (i === this.pageActuelle) ? 'current' : '';
            html += '<li><a class="' + cls + '" data-page="' + i + '" href="#">' + i + '</a></li>';
        }

        // Suivant
        if (this.pageActuelle < nbPages) {
            html += '<li><a class="sl-pagination-next" data-page="' + (this.pageActuelle + 1) + '" href="#">Next →</a></li>';
        }

        html += '</ul>';
        container.innerHTML = html;

        // Bind clics pagination
        container.querySelectorAll('a[data-page]').forEach(function (lien) {
            lien.addEventListener('click', function (e) {
                e.preventDefault();
                self.pageActuelle = parseInt(lien.getAttribute('data-page'), 10);
                self.render();
                self.renderPagination();
                // Scroll vers le widget
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

    // Init sur DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Ré-init pour Elementor Editor (aperçu en direct)
    if (window.elementorFrontend) {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/sl_agences.default', function () {
            initAll();
        });
    }
    document.addEventListener('elementor/frontend/init', function () {
        if (window.elementorFrontend) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/sl_agences.default', function () {
                initAll();
            });
        }
    });

})();
