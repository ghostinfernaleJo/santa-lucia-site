/**
 * Santa Lucia – Fiche Agence v2
 * Galerie filtrable multi-catégories, Masonry/Grid, Vidéo embed, Lightbox
 */
(function () {
    'use strict';

    function SLFicheAgence(wrapper) {
        this.wrapper = wrapper;
        this.id = wrapper.id.replace('slf-', '');
        this.parPage = parseInt(wrapper.getAttribute('data-medias-par-page') || '8', 10);
        this.layout = wrapper.getAttribute('data-layout') || 'masonry';
        this.colonnes = parseInt(wrapper.getAttribute('data-colonnes') || '4', 10);
        this.pageActuelle = 1;
        this.catActive = 'all';

        this.allMediasEl = document.getElementById('slf-all-medias-' + this.id);
        this.grid = document.getElementById('slf-grid-' + this.id);
        this.pagWrap = document.getElementById('slf-pag-' + this.id);
        this.lightbox = document.getElementById('slf-lightbox-' + this.id);

        // Extraire tous les médias
        this.tousMedias = [];
        if (this.allMediasEl) {
            this.tousMedias = Array.from(this.allMediasEl.querySelectorAll('.slf-media-item'));
        }
        this.mediasFiltres = this.tousMedias.slice();

        this.lbIndex = 0;
        this.lbMedias = [];

        this.init();
        this.wrapper._slfInstance = this;
    }

    SLFicheAgence.prototype.init = function () {
        this.bindMainTabs();
        this.bindTabs();
        this.bindLightbox();
        this.render();
        this.renderPagination();
    };

    /* ---- Onglets principaux : Galerie / Bons plans / Menu ---- */
    SLFicheAgence.prototype.bindMainTabs = function () {
        var self = this;
        var tabs = Array.from(this.wrapper.querySelectorAll('.slf-main-tab'));
        var panels = Array.from(this.wrapper.querySelectorAll('.slf-main-panel'));

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = tab.getAttribute('data-panel');

                tabs.forEach(function (t) { t.classList.remove('active'); });
                panels.forEach(function (panel) {
                    panel.classList.toggle('active', panel.getAttribute('data-panel') === target);
                });

                tab.classList.add('active');

                if (target === 'galerie' && self.layout === 'masonry') {
                    setTimeout(function () { self.applyMasonry(); }, 80);
                }
            });
        });
    };

    /* ---- Tabs filtres ---- */
    SLFicheAgence.prototype.bindTabs = function () {
        var self = this;
        var tabs = this.wrapper.querySelectorAll('.slf-tab');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) { t.classList.remove('active'); });
                tab.classList.add('active');
                self.catActive = tab.getAttribute('data-cat');
                self.pageActuelle = 1;
                self.filtrer();
            });
        });
    };

    /* ---- Filtrer ---- */
    SLFicheAgence.prototype.filtrer = function () {
        var self = this;
        this.mediasFiltres = this.tousMedias.filter(function (m) {
            if (self.catActive === 'all') return true;
            return m.getAttribute('data-cat') === self.catActive;
        });
        this.render();
        this.renderPagination();
    };

    /* ---- Render grille ---- */
    SLFicheAgence.prototype.render = function () {
        if (!this.grid) return;

        var debut = (this.pageActuelle - 1) * this.parPage;
        var fin = debut + this.parPage;
        var page = this.mediasFiltres.slice(debut, fin);

        this.grid.innerHTML = '';

        if (page.length === 0) {
            this.grid.innerHTML = '<p class="slf-no-photos">Aucun média dans cette catégorie.</p>';
            return;
        }

        this.lbMedias = page;
        var self = this;
        var frag = document.createDocumentFragment();

        page.forEach(function (m, i) {
            var el = m.cloneNode(true);
            el.style.display = '';
            el.style.opacity = '0';
            el.style.transform = 'scale(0.95)';

            el.addEventListener('click', function () {
                self.openLightbox(i);
            });

            frag.appendChild(el);
            setTimeout(function () {
                el.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
                el.style.opacity = '1';
                el.style.transform = 'scale(1)';
            }, i * 40);
        });

        this.grid.appendChild(frag);

        // Si masonry, appliquer le layout après le chargement des images
        if (this.layout === 'masonry') {
            this.applyMasonry();
        }
    };

    /* ---- Masonry layout ---- */
    SLFicheAgence.prototype.applyMasonry = function () {
        var grid = this.grid;
        var cols = this.colonnes;

        // Attendre que les images soient chargées
        var images = grid.querySelectorAll('img');
        var loaded = 0;
        var total = images.length;

        if (total === 0) return;

        var doLayout = function () {
            grid.style.position = 'relative';
            var items = Array.from(grid.querySelectorAll('.slf-media-item'));
            var gap = 14;
            var gridWidth = grid.offsetWidth;
            var colWidth = (gridWidth - gap * (cols - 1)) / cols;
            var colHeights = new Array(cols).fill(0);

            // Responsive
            var actualCols = cols;
            if (window.innerWidth <= 520) actualCols = 1;
            else if (window.innerWidth <= 768) actualCols = 2;
            else if (window.innerWidth <= 1024) actualCols = Math.min(3, cols);

            colWidth = (gridWidth - gap * (actualCols - 1)) / actualCols;
            colHeights = new Array(actualCols).fill(0);

            items.forEach(function (item) {
                var shortest = 0;
                for (var c = 1; c < actualCols; c++) {
                    if (colHeights[c] < colHeights[shortest]) shortest = c;
                }

                item.style.position = 'absolute';
                item.style.width = colWidth + 'px';
                item.style.left = (shortest * (colWidth + gap)) + 'px';
                item.style.top = colHeights[shortest] + 'px';

                colHeights[shortest] += item.offsetHeight + gap;
            });

            var maxH = Math.max.apply(null, colHeights);
            grid.style.height = maxH + 'px';
        };

        images.forEach(function (img) {
            if (img.complete) {
                loaded++;
                if (loaded === total) doLayout();
            } else {
                img.addEventListener('load', function () {
                    loaded++;
                    if (loaded === total) doLayout();
                });
                img.addEventListener('error', function () {
                    loaded++;
                    if (loaded === total) doLayout();
                });
            }
        });

        // Fallback
        setTimeout(doLayout, 800);
    };

    /* ---- Pagination ---- */
    SLFicheAgence.prototype.renderPagination = function () {
        var self = this;
        var total = this.mediasFiltres.length;
        var nbPages = Math.ceil(total / this.parPage);

        if (!this.pagWrap) return;
        if (nbPages <= 1) { this.pagWrap.innerHTML = ''; return; }

        var html = '<ul class="slf-pag">';
        if (this.pageActuelle > 1) {
            html += '<li><a data-page="' + (this.pageActuelle - 1) + '" href="#">← Précédent</a></li>';
        }
        for (var i = 1; i <= nbPages; i++) {
            var cls = (i === this.pageActuelle) ? 'current' : '';
            html += '<li><a class="' + cls + '" data-page="' + i + '" href="#">' + i + '</a></li>';
        }
        if (this.pageActuelle < nbPages) {
            html += '<li><a class="slf-pag-next" data-page="' + (this.pageActuelle + 1) + '" href="#">Suivant →</a></li>';
        }
        html += '</ul>';
        this.pagWrap.innerHTML = html;

        this.pagWrap.querySelectorAll('a[data-page]').forEach(function (lien) {
            lien.addEventListener('click', function (e) {
                e.preventDefault();
                self.pageActuelle = parseInt(lien.getAttribute('data-page'), 10);
                self.render();
                self.renderPagination();
                var sec = self.wrapper.querySelector('.slf-galerie-section');
                if (sec) sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    };

    /* ---- Lightbox ---- */
    SLFicheAgence.prototype.bindLightbox = function () {
        var self = this;
        if (!this.lightbox) return;

        this.lightbox.querySelector('.slf-lb-close').addEventListener('click', function () { self.closeLightbox(); });
        this.lightbox.querySelector('.slf-lb-prev').addEventListener('click', function () { self.navigateLB(-1); });
        this.lightbox.querySelector('.slf-lb-next').addEventListener('click', function () { self.navigateLB(1); });

        this.lightbox.addEventListener('click', function (e) {
            if (e.target === self.lightbox) self.closeLightbox();
        });

        document.addEventListener('keydown', function (e) {
            if (!self.lightbox.classList.contains('open')) return;
            if (e.key === 'Escape') self.closeLightbox();
            if (e.key === 'ArrowLeft') self.navigateLB(-1);
            if (e.key === 'ArrowRight') self.navigateLB(1);
        });
    };

    SLFicheAgence.prototype.openLightbox = function (index) {
        this.lbIndex = index;
        this.updateLBContent();
        this.lightbox.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    SLFicheAgence.prototype.closeLightbox = function () {
        this.lightbox.classList.remove('open');
        document.body.style.overflow = '';
        // Stopper la vidéo
        var iframe = this.lightbox.querySelector('.slf-lb-video');
        if (iframe) iframe.src = '';
    };

    SLFicheAgence.prototype.navigateLB = function (dir) {
        this.lbIndex += dir;
        if (this.lbIndex < 0) this.lbIndex = this.lbMedias.length - 1;
        if (this.lbIndex >= this.lbMedias.length) this.lbIndex = 0;
        this.updateLBContent();
    };

    SLFicheAgence.prototype.updateLBContent = function () {
        var media = this.lbMedias[this.lbIndex];
        if (!media) return;

        var lbImg   = this.lightbox.querySelector('.slf-lb-img');
        var lbVideo = this.lightbox.querySelector('.slf-lb-video');
        var type    = media.getAttribute('data-type');

        // Compteur "Photo X / Y"
        var counter = this.lightbox.querySelector('.slf-lb-counter');
        if (counter) {
            counter.textContent = (this.lbIndex + 1) + ' / ' + this.lbMedias.length;
        }

        if (type === 'video') {
            var embed = media.getAttribute('data-embed');
            lbImg.style.display = 'none';
            lbVideo.style.display = 'block';
            lbVideo.src = embed + '?autoplay=1';
        } else {
            var img = media.querySelector('img');
            lbVideo.style.display = 'none';
            lbVideo.src = '';
            lbImg.style.display = 'block';
            if (img) {
                lbImg.src = img.src;
                lbImg.alt = img.alt;
            }
        }
    };

    /* ---- Init ---- */
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

    // Elementor Editor
    var bindElementor = function () {
        if (window.elementorFrontend) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/sl_fiche_agence.default', function ($scope) {
                var el = $scope[0] || $scope;
                var w = el.querySelector ? el.querySelector('.slf-wrapper') : null;
                if (w) { w.removeAttribute('data-slf-init'); new SLFicheAgence(w); }
            });
        }
    };
    bindElementor();
    document.addEventListener('elementor/frontend/init', bindElementor);

    // Re-layout masonry on resize
    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            document.querySelectorAll('.slf-wrapper[data-slf-init]').forEach(function (w) {
                var inst = w._slfInstance;
                if (inst && inst.layout === 'masonry') inst.applyMasonry();
            });
        }, 200);
    });

    /* ---- CTA Flottant : apparaître après le scroll du hero ---- */
    var ctaScrollTimer;
    window.addEventListener('scroll', function () {
        clearTimeout(ctaScrollTimer);
        ctaScrollTimer = setTimeout(function () {
            document.querySelectorAll('.slf-cta-flottant').forEach(function (btn) {
                if (window.scrollY > 120) {
                    btn.classList.add('visible');
                } else {
                    btn.classList.remove('visible');
                }
            });
        }, 30);
    }, { passive: true });

    /* ---- Protection clic-droit sur les images de galerie ---- */
    document.addEventListener('contextmenu', function (e) {
        if (e.target && (e.target.closest('.slf-media-item') || e.target.classList.contains('slf-lb-img'))) {
            e.preventDefault();
        }
    });

})();
