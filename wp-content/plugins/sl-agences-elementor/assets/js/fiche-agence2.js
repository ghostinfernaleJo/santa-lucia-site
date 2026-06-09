/**
 * Santa Lucia - Fiche Agence v3
 * Galerie filtrable + Bons Plans par agence (recherche, categories, tri, pagination)
 */
(function () {
    'use strict';

    function SLFicheAgence(wrapper) {
        this.wrapper  = wrapper;
        this.id       = wrapper.id.replace('slf-', '');
        this.parPage  = parseInt(wrapper.getAttribute('data-medias-par-page') || '8', 10);
        this.layout   = wrapper.getAttribute('data-layout') || 'masonry';
        this.colonnes = parseInt(wrapper.getAttribute('data-colonnes') || '4', 10);

        // ── Galerie
        this.pageActuelle  = 1;
        this.catActive     = 'all';
        this.allMediasEl   = document.getElementById('slf-all-medias-' + this.id);
        this.grid          = document.getElementById('slf-grid-' + this.id);
        this.pagWrap       = document.getElementById('slf-pag-' + this.id);
        this.lightbox      = document.getElementById('slf-lightbox-' + this.id);
        this.galerieContent = document.getElementById('slf-galerie-content-' + this.id);
        this.tousMedias    = this.allMediasEl
            ? Array.from(this.allMediasEl.querySelectorAll('.slf-media-item'))
            : [];
        this.mediasFiltres = this.tousMedias.slice();
        this.lbIndex       = 0;
        this.lbMedias      = [];

        // ── Bons Plans
        this.bpPanel      = document.getElementById('slf-bp-panel-' + this.id);
        this.bpGrid       = document.getElementById('slf-bp-grid-' + this.id);
        this.bpEmpty      = document.getElementById('slf-bp-empty-' + this.id);
        this.bpPag        = document.getElementById('slf-bp-pag-' + this.id);
        this.bpSource     = this.bpPanel ? this.bpPanel.querySelector('.slf-bp-source') : null;
        this.bpCards      = this.bpSource
            ? Array.from(this.bpSource.querySelectorAll('.slf-bp-card'))
            : [];
        this.bpResultCount = this.bpPanel ? this.bpPanel.querySelector('.slf-bp-result-count') : null;
        this.bpPage        = 1;
        this.bpPerPage     = this.parPage;
        this.bpFilterCat   = '';
        this.bpFilterSearch = '';
        this.bpSortMode    = 'recent';

        this.init();
        wrapper._slfInstance = this;
    }

    SLFicheAgence.prototype.init = function () {
        this.bindTabs();
        this.bindLightbox();
        this.bindBPPanel();

        // Demarrer en mode galerie ou bons plans selon l'onglet actif par defaut
        var activeBP = this.wrapper.querySelector('.slf-tab-bp.active');
        if (activeBP) {
            // Pas de galerie: afficher directement les bons plans
            this.renderBP();
        } else if (this.grid) {
            this.render();
            this.renderPagination();
        }
    };

    /* ════════════════════════════════════════════════════════
       ONGLETS
    ════════════════════════════════════════════════════════ */
    SLFicheAgence.prototype.bindTabs = function () {
        var self = this;
        var tabs = Array.from(this.wrapper.querySelectorAll('.slf-tab'));

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) { t.classList.remove('active'); });
                tab.classList.add('active');

                var cat = tab.getAttribute('data-cat');

                if (cat === '__bons-plans__') {
                    // ── Passer en mode Bons Plans
                    if (self.galerieContent) self.galerieContent.style.display = 'none';
                    if (self.bpPanel) {
                        self.bpPanel.style.display = '';
                        // Rendu initial si grille vide
                        if (self.bpGrid && !self.bpGrid.firstChild) {
                            self.renderBP();
                        }
                    }
                } else {
                    // ── Passer en mode Galerie
                    if (self.bpPanel)       self.bpPanel.style.display = 'none';
                    if (self.galerieContent) self.galerieContent.style.display = '';
                    self.catActive    = cat;
                    self.pageActuelle = 1;
                    self.filtrer();
                }
            });
        });
    };

    /* ════════════════════════════════════════════════════════
       GALERIE : filtrage / rendu / masonry / pagination
    ════════════════════════════════════════════════════════ */
    SLFicheAgence.prototype.filtrer = function () {
        var self = this;
        this.mediasFiltres = this.tousMedias.filter(function (m) {
            if (self.catActive === 'all') return true;
            return m.getAttribute('data-cat') === self.catActive;
        });
        this.render();
        this.renderPagination();
    };

    SLFicheAgence.prototype.render = function () {
        if (!this.grid) return;
        var debut = (this.pageActuelle - 1) * this.parPage;
        var page  = this.mediasFiltres.slice(debut, debut + this.parPage);

        this.grid.innerHTML = '';
        if (page.length === 0) {
            this.grid.innerHTML = '<p class="slf-no-photos">Aucun media dans cette categorie.</p>';
            return;
        }

        this.lbMedias = page;
        var self = this;
        var frag = document.createDocumentFragment();
        page.forEach(function (m, i) {
            var el = m.cloneNode(true);
            el.style.display  = '';
            el.style.opacity  = '0';
            el.style.transform = 'scale(0.95)';
            el.addEventListener('click', function () { self.openLightbox(i); });
            frag.appendChild(el);
            setTimeout(function () {
                el.style.transition = 'opacity .35s ease, transform .35s ease';
                el.style.opacity    = '1';
                el.style.transform  = 'scale(1)';
            }, i * 40);
        });
        this.grid.appendChild(frag);
        if (this.layout === 'masonry') this.applyMasonry();
    };

    SLFicheAgence.prototype.applyMasonry = function () {
        var grid = this.grid;
        var cols = this.colonnes;
        var images = grid.querySelectorAll('img');
        var loaded = 0, total = images.length;
        if (total === 0) return;

        var doLayout = function () {
            grid.style.position = 'relative';
            var items = Array.from(grid.querySelectorAll('.slf-media-item'));
            var gap   = 14;
            var w     = grid.offsetWidth;
            var actualCols = cols;
            if (window.innerWidth <= 520)      actualCols = 1;
            else if (window.innerWidth <= 768)  actualCols = 2;
            else if (window.innerWidth <= 1024) actualCols = Math.min(3, cols);
            var colW   = (w - gap * (actualCols - 1)) / actualCols;
            var colH   = new Array(actualCols).fill(0);

            items.forEach(function (item) {
                var shortest = 0;
                for (var c = 1; c < actualCols; c++) {
                    if (colH[c] < colH[shortest]) shortest = c;
                }
                item.style.position = 'absolute';
                item.style.width    = colW + 'px';
                item.style.left     = (shortest * (colW + gap)) + 'px';
                item.style.top      = colH[shortest] + 'px';
                colH[shortest] += item.offsetHeight + gap;
            });
            grid.style.height = Math.max.apply(null, colH) + 'px';
        };

        images.forEach(function (img) {
            if (img.complete) { if (++loaded === total) doLayout(); }
            else {
                img.addEventListener('load',  function () { if (++loaded === total) doLayout(); });
                img.addEventListener('error', function () { if (++loaded === total) doLayout(); });
            }
        });
        setTimeout(doLayout, 800);
    };

    SLFicheAgence.prototype.renderPagination = function () {
        var self    = this;
        var total   = this.mediasFiltres.length;
        var nbPages = Math.ceil(total / this.parPage);
        if (!this.pagWrap) return;
        if (nbPages <= 1) { this.pagWrap.innerHTML = ''; return; }

        var html = '<ul class="slf-pag">';
        if (this.pageActuelle > 1)
            html += '<li><a data-page="' + (this.pageActuelle - 1) + '" href="#">&larr; Precedent</a></li>';
        for (var i = 1; i <= nbPages; i++) {
            html += '<li><a class="' + (i === this.pageActuelle ? 'current' : '') + '" data-page="' + i + '" href="#">' + i + '</a></li>';
        }
        if (this.pageActuelle < nbPages)
            html += '<li><a data-page="' + (this.pageActuelle + 1) + '" href="#">Suivant &rarr;</a></li>';
        html += '</ul>';
        this.pagWrap.innerHTML = html;

        this.pagWrap.querySelectorAll('a[data-page]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                self.pageActuelle = parseInt(a.getAttribute('data-page'), 10);
                self.render();
                self.renderPagination();
                var sec = self.wrapper.querySelector('.slf-galerie-section');
                if (sec) sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    };

    /* ════════════════════════════════════════════════════════
       BONS PLANS
    ════════════════════════════════════════════════════════ */
    SLFicheAgence.prototype.bindBPPanel = function () {
        var self = this;
        if (!this.bpPanel) return;

        var searchInp = this.bpPanel.querySelector('.slf-bp-search');
        var sortSel   = this.bpPanel.querySelector('.slf-bp-sort');
        var catBtns   = Array.from(this.bpPanel.querySelectorAll('.slf-bp-cat-btn'));

        if (searchInp) {
            searchInp.addEventListener('input', function () {
                self.bpFilterSearch = this.value.toLowerCase().trim();
                self.bpPage = 1;
                self.renderBP();
            });
        }

        if (sortSel) {
            sortSel.addEventListener('change', function () {
                self.bpSortMode = this.value;
                self.bpPage = 1;
                self.renderBP();
            });
        }

        catBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                catBtns.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                self.bpFilterCat = btn.dataset.cat || '';
                self.bpPage = 1;
                self.renderBP();
            });
        });
    };

    SLFicheAgence.prototype.getBPFiltered = function () {
        var self = this;
        return this.bpCards.filter(function (c) {
            if (self.bpFilterSearch) {
                if ((c.dataset.nom || '').toLowerCase().indexOf(self.bpFilterSearch) === -1) return false;
            }
            if (self.bpFilterCat) {
                var cats = (c.dataset.cat || '').split(',').map(function (x) { return x.trim(); });
                if (cats.indexOf(self.bpFilterCat) === -1) return false;
            }
            return true;
        });
    };

    SLFicheAgence.prototype.sortBPCards = function (cards) {
        var self = this;
        return cards.slice().sort(function (a, b) {
            if (self.bpSortMode === 'prix_asc')  return parseFloat(a.dataset.prixAp || 0) - parseFloat(b.dataset.prixAp || 0);
            if (self.bpSortMode === 'prix_desc') return parseFloat(b.dataset.prixAp || 0) - parseFloat(a.dataset.prixAp || 0);
            if (self.bpSortMode === 'reduc')     return parseFloat(b.dataset.reduc || 0)  - parseFloat(a.dataset.reduc || 0);
            return 0; // recent: ordre PHP (date DESC)
        });
    };

    SLFicheAgence.prototype.renderBP = function () {
        if (!this.bpGrid) return;

        var filtered = this.getBPFiltered();
        var sorted   = this.sortBPCards(filtered);
        var total    = sorted.length;
        var from     = total === 0 ? 0 : (this.bpPage - 1) * this.bpPerPage + 1;
        var to       = Math.min(this.bpPage * this.bpPerPage, total);
        var pages    = Math.ceil(total / this.bpPerPage);

        // Compteur
        if (this.bpResultCount) {
            this.bpResultCount.textContent = total > 0
                ? from + '–' + to + ' sur ' + total + ' bon' + (total > 1 ? 's' : '') + ' plan' + (total > 1 ? 's' : '')
                : '';
        }

        this.bpGrid.innerHTML = '';

        if (total === 0) {
            if (this.bpEmpty) this.bpEmpty.style.display = 'flex';
        } else {
            if (this.bpEmpty) this.bpEmpty.style.display = 'none';
            var slice = sorted.slice((this.bpPage - 1) * this.bpPerPage, this.bpPage * this.bpPerPage);
            var frag  = document.createDocumentFragment();
            slice.forEach(function (c, i) {
                var clone = c.cloneNode(true);
                clone.style.display  = '';
                clone.style.opacity  = '0';
                clone.style.transform = 'translateY(10px)';
                frag.appendChild(clone);
                setTimeout(function () {
                    clone.style.transition = 'opacity .3s ease, transform .3s ease';
                    clone.style.opacity    = '1';
                    clone.style.transform  = 'translateY(0)';
                }, i * 50);
            });
            this.bpGrid.appendChild(frag);
        }

        this.renderBPPagination(pages);
    };

    SLFicheAgence.prototype.renderBPPagination = function (pages) {
        var self = this;
        if (!this.bpPag) return;
        this.bpPag.innerHTML = '';
        if (pages <= 1) return;

        var mk = function (label, target, isActive) {
            var a = document.createElement('a');
            a.href        = '#';
            a.textContent = label;
            if (isActive) a.classList.add('active');
            a.addEventListener('click', function (e) {
                e.preventDefault();
                self.bpPage = target;
                self.renderBP();
                self.bpPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            return a;
        };

        if (this.bpPage > 1) this.bpPag.appendChild(mk('‹', this.bpPage - 1, false));
        for (var i = 1; i <= pages; i++) this.bpPag.appendChild(mk(i, i, i === this.bpPage));
        if (this.bpPage < pages) this.bpPag.appendChild(mk('›', this.bpPage + 1, false));
    };

    /* ════════════════════════════════════════════════════════
       LIGHTBOX
    ════════════════════════════════════════════════════════ */
    SLFicheAgence.prototype.bindLightbox = function () {
        var self = this;
        if (!this.lightbox) return;

        this.lightbox.querySelector('.slf-lb-close').addEventListener('click', function () { self.closeLightbox(); });
        this.lightbox.querySelector('.slf-lb-prev').addEventListener('click', function () { self.navigateLB(-1); });
        this.lightbox.querySelector('.slf-lb-next').addEventListener('click', function () { self.navigateLB(1); });
        this.lightbox.addEventListener('click', function (e) { if (e.target === self.lightbox) self.closeLightbox(); });

        document.addEventListener('keydown', function (e) {
            if (!self.lightbox.classList.contains('open')) return;
            if (e.key === 'Escape')      self.closeLightbox();
            if (e.key === 'ArrowLeft')   self.navigateLB(-1);
            if (e.key === 'ArrowRight')  self.navigateLB(1);
        });
    };

    SLFicheAgence.prototype.openLightbox = function (i) {
        this.lbIndex = i;
        this.updateLBContent();
        this.lightbox.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    SLFicheAgence.prototype.closeLightbox = function () {
        this.lightbox.classList.remove('open');
        document.body.style.overflow = '';
        var iframe = this.lightbox.querySelector('.slf-lb-video');
        if (iframe) iframe.src = '';
    };

    SLFicheAgence.prototype.navigateLB = function (dir) {
        this.lbIndex = (this.lbIndex + dir + this.lbMedias.length) % this.lbMedias.length;
        this.updateLBContent();
    };

    SLFicheAgence.prototype.updateLBContent = function () {
        var media   = this.lbMedias[this.lbIndex];
        if (!media) return;
        var lbImg   = this.lightbox.querySelector('.slf-lb-img');
        var lbVideo = this.lightbox.querySelector('.slf-lb-video');
        var counter = this.lightbox.querySelector('.slf-lb-counter');
        if (counter) counter.textContent = (this.lbIndex + 1) + ' / ' + this.lbMedias.length;

        if (media.getAttribute('data-type') === 'video') {
            lbImg.style.display   = 'none';
            lbVideo.style.display = 'block';
            lbVideo.src = media.getAttribute('data-embed') + '?autoplay=1';
        } else {
            var img = media.querySelector('img');
            lbVideo.style.display = 'none';
            lbVideo.src           = '';
            lbImg.style.display   = 'block';
            if (img) { lbImg.src = img.src; lbImg.alt = img.alt; }
        }
    };

    /* ════════════════════════════════════════════════════════
       INIT GLOBAL
    ════════════════════════════════════════════════════════ */
    function initAll() {
        document.querySelectorAll('.slf-wrapper').forEach(function (w) {
            if (!w.getAttribute('data-slf-init')) {
                w.setAttribute('data-slf-init', '1');
                new SLFicheAgence(w);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Elementor Editor live preview
    var bindElementor = function () {
        if (window.elementorFrontend) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/sl_fiche_agence.default', function ($scope) {
                var el = $scope[0] || $scope;
                var w  = el.querySelector ? el.querySelector('.slf-wrapper') : null;
                if (w) { w.removeAttribute('data-slf-init'); new SLFicheAgence(w); }
            });
        }
    };
    bindElementor();
    document.addEventListener('elementor/frontend/init', bindElementor);

    // Re-layout masonry au redimensionnement
    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            document.querySelectorAll('.slf-wrapper[data-slf-init]').forEach(function (w) {
                var inst = w._slfInstance;
                if (inst && inst.layout === 'masonry' && inst.galerieContent && inst.galerieContent.style.display !== 'none') {
                    inst.applyMasonry();
                }
            });
        }, 200);
    });

    // CTA flottant : apparaitre apres le scroll du hero
    var ctaTimer;
    window.addEventListener('scroll', function () {
        clearTimeout(ctaTimer);
        ctaTimer = setTimeout(function () {
            document.querySelectorAll('.slf-cta-flottant').forEach(function (btn) {
                btn.classList.toggle('visible', window.scrollY > 120);
            });
        }, 30);
    }, { passive: true });

    // Protection clic-droit sur images
    document.addEventListener('contextmenu', function (e) {
        if (e.target && (e.target.closest('.slf-media-item') || e.target.classList.contains('slf-lb-img'))) {
            e.preventDefault();
        }
    });

})();
