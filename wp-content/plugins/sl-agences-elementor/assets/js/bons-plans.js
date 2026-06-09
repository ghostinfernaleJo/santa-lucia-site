/**
 * SL Bons Plans — JS
 * Gestion : sidebar checkboxes, filtre prix, recherche, tri, pagination, vue grille/liste
 */
(function () {
    'use strict';

    function slbpUnlockPageScroll() {
        if (document.documentElement) {
            document.documentElement.style.overflow = '';
            document.documentElement.style.overflowY = '';
        }
        if (document.body) {
            document.body.style.overflow = '';
            document.body.style.overflowY = '';
        }
    }

    slbpUnlockPageScroll();
    window.addEventListener('pageshow', slbpUnlockPageScroll);
    window.addEventListener('load', slbpUnlockPageScroll);
    setTimeout(slbpUnlockPageScroll, 250);
    setTimeout(slbpUnlockPageScroll, 1000);

    document.querySelectorAll('.slbp-wrapper').forEach(function (wrap) {

        // ── État global
        var parPage       = parseInt(wrap.dataset.parPage) || 20;
        var page          = 1;
        var filterCats    = [];
        var filterAgences = [];
        var filterSearch  = '';
        var filterPrixMin = 0;
        var filterPrixMax = Infinity;
        var sortMode      = 'recent';

        // ── Éléments DOM
        var allCards   = Array.from(wrap.querySelectorAll('.slbp-all-cards .slbp-card'));
        var grid       = wrap.querySelector('.slbp-grid');
        var emptyBox   = wrap.querySelector('.slbp-empty');
        var pagDiv     = wrap.querySelector('.slbp-pagination');

        // Sidebar checkboxes
        var catCheckboxes    = wrap.querySelectorAll('[data-filter="cat"] li');
        var agenceCheckboxes = wrap.querySelectorAll('[data-filter="agence"] li');
        var agenceSelect     = wrap.querySelector('.slbp-agence-select');
        var agenceMulti      = wrap.querySelector('.slbp-agence-ms');

        // Sort bar
        var sortSel    = wrap.querySelector('.slbp-sort');
        var perPageSel = wrap.querySelector('.slbp-per-page-sel');
        var searchInp  = wrap.querySelector('.slbp-search');
        var elTotal    = wrap.querySelector('.slbp-total');
        var elFrom     = wrap.querySelector('.slbp-range-from');
        var elTo       = wrap.querySelector('.slbp-range-to');

        // Prix
        var btnFiltre  = wrap.querySelector('.slbp-btn-filtre');
        var pminInp    = wrap.querySelector('.slbp-pmin');
        var pmaxInp    = wrap.querySelector('.slbp-pmax');
        var rangeInp   = wrap.querySelector('.slbp-price-range');
        var priceLabel = wrap.querySelector('.slbp-price-label-val');

        // Mobile Sidebar
        var sidebar          = wrap.querySelector('.slbp-sidebar');
        var mobileFilterBtn  = wrap.querySelector('.slbp-mobile-filter-btn');
        var closeSidebarBtn  = wrap.querySelector('.slbp-close-sidebar');
        var sidebarOverlay   = wrap.querySelector('.slbp-sidebar-overlay');

        // ── FILTRAGE
        function getFiltered() {
            return allCards.filter(function (c) {
                if (filterSearch) {
                    var nom = (c.dataset.nom || '').toLowerCase();
                    if (nom.indexOf(filterSearch) === -1) return false;
                }
                if (filterCats.length > 0) {
                    var cardCats = (c.dataset.cat || '').split(',').map(function (x) { return x.trim(); });
                    var match = filterCats.some(function (fc) { return cardCats.indexOf(fc) !== -1; });
                    if (!match) return false;
                }
                if (filterAgences.length > 0) {
                    var ag = c.dataset.agence || '';
                    if (filterAgences.indexOf(ag) === -1) return false;
                }
                var prix = parseFloat(c.dataset.prixAp) || 0;
                if (prix < filterPrixMin) return false;
                if (prix > filterPrixMax) return false;
                return true;
            });
        }

        // ── TRI
        function sortCards(cards) {
            return cards.slice().sort(function (a, b) {
                if (sortMode === 'prix_asc')  return parseFloat(a.dataset.prixAp) - parseFloat(b.dataset.prixAp);
                if (sortMode === 'prix_desc') return parseFloat(b.dataset.prixAp) - parseFloat(a.dataset.prixAp);
                if (sortMode === 'reduc')     return parseFloat(b.dataset.reduc) - parseFloat(a.dataset.reduc);
                return 0;
            });
        }

        // ── BADGE FILTRES ACTIFS (bouton mobile)
        function updateFilterBadge() {
            if (!mobileFilterBtn) return;
            var count = filterCats.length + filterAgences.length
                + (filterSearch ? 1 : 0)
                + (filterPrixMax !== Infinity || filterPrixMin > 0 ? 1 : 0);
            var badge = mobileFilterBtn.querySelector('.slbp-filter-badge');
            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'slbp-filter-badge';
                    mobileFilterBtn.appendChild(badge);
                }
                badge.textContent = count;
                mobileFilterBtn.style.borderColor = 'var(--sl-red)';
                mobileFilterBtn.style.color = 'var(--sl-red)';
            } else {
                if (badge) badge.remove();
                mobileFilterBtn.style.borderColor = '';
                mobileFilterBtn.style.color = '';
            }
        }

        // ── RENDU
        function render() {
            var filtered = getFiltered();
            var sorted   = sortCards(filtered);
            var total    = sorted.length;
            var from     = total === 0 ? 0 : (page - 1) * parPage + 1;
            var to       = Math.min(page * parPage, total);
            var pages    = Math.ceil(total / parPage);

            if (elTotal) elTotal.textContent = total;
            if (elFrom)  elFrom.textContent  = from;
            if (elTo)    elTo.textContent    = to;
            // Synchroniser le compteur mobile chips
            if (chipsTotal) chipsTotal.textContent = total;
            // Afficher la barre résultats après le premier rendu
            var chipsResults = wrap.querySelector(".slbp-chips-results");
            if (chipsResults) chipsResults.classList.add("is-visible");
            var hasActiveFilters = filterCats.length > 0 || filterAgences.length > 0 || filterPrixMax !== Infinity;
            if (chipsReset) chipsReset.classList.toggle('visible', hasActiveFilters);
            updateFilterBadge();

            grid.innerHTML = '';
            if (total === 0) {
                emptyBox.style.display = 'block';
            } else {
                emptyBox.style.display = 'none';
                sorted.slice((page - 1) * parPage, page * parPage).forEach(function (c) {
                    var clone = c.cloneNode(true);
                    clone.style.display = '';
                    grid.appendChild(clone);
                });
            }
            renderPagination(pages);
        }

        function renderPagination(pages) {
            pagDiv.innerHTML = '';
            if (pages <= 1) return;
            if (page > 1) pagDiv.appendChild(mkPageBtn('‹', page - 1, false, true));
            for (var i = 1; i <= pages; i++) pagDiv.appendChild(mkPageBtn(i, i, i === page, false));
            if (page < pages) pagDiv.appendChild(mkPageBtn('›', page + 1, false, true));
        }

        function mkPageBtn(label, targetPage, isActive, isNav) {
            var a = document.createElement('a');
            a.textContent = label;
            if (isActive) a.classList.add('active');
            if (isNav)    a.classList.add('nav');
            a.href = '#';
            a.addEventListener('click', function (e) {
                e.preventDefault();
                page = targetPage;
                render();
                wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            return a;
        }

        // ── SIDEBAR CHECKBOXES
        function bindCheckboxList(listItems, filterArr) {
            listItems.forEach(function (li) {
                var cb = li.querySelector('input[type="checkbox"]');
                if (!cb) return;
                cb.addEventListener('change', function () {
                    var val = li.dataset.value;
                    if (cb.checked) {
                        li.classList.add('checked');
                        if (filterArr.indexOf(val) === -1) filterArr.push(val);
                    } else {
                        li.classList.remove('checked');
                        var idx = filterArr.indexOf(val);
                        if (idx > -1) filterArr.splice(idx, 1);
                    }
                    page = 1;
                    render();
                });
            });
        }

        bindCheckboxList(catCheckboxes,    filterCats);
        bindCheckboxList(agenceCheckboxes, filterAgences);

        function updateAgenceMultiLabel() {
            if (!agenceMulti) return;
            var label = agenceMulti.querySelector('.slbp-agence-ms-label');
            var checked = Array.from(agenceMulti.querySelectorAll('.slbp-agence-ms-choice:checked'));
            if (!label) return;

            if (checked.length === 0) {
                label.textContent = 'Toutes les agences';
            } else if (checked.length === 1) {
                label.textContent = checked[0].dataset.label || checked[0].value;
            } else {
                label.textContent = checked.length + ' agences sélectionnées';
            }
        }

        function applyAgenceMultiFilter() {
            if (!agenceMulti) return;
            var allBox = agenceMulti.querySelector('.slbp-agence-ms-all');
            var choices = Array.from(agenceMulti.querySelectorAll('.slbp-agence-ms-choice'));
            var selected = choices.filter(function (input) { return input.checked; }).map(function (input) { return input.value; });

            if (selected.length === 0 && allBox) {
                allBox.checked = true;
            } else if (allBox) {
                allBox.checked = false;
            }

            filterAgences.length = 0;
            selected.forEach(function (value) {
                if (filterAgences.indexOf(value) === -1) filterAgences.push(value);
            });

            updateAgenceMultiLabel();
            page = 1;
            render();
            updateChipCounts();
        }

        if (agenceMulti) {
            var agenceToggle = agenceMulti.querySelector('.slbp-agence-ms-toggle');
            var agencePanel = agenceMulti.querySelector('.slbp-agence-ms-panel');
            var agenceAll = agenceMulti.querySelector('.slbp-agence-ms-all');
            var agenceChoices = Array.from(agenceMulti.querySelectorAll('.slbp-agence-ms-choice'));

            if (agenceToggle && agencePanel) {
                agenceToggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var isOpen = !agencePanel.hidden;
                    agencePanel.hidden = isOpen;
                    agenceToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                });
            }

            if (agenceAll) {
                agenceAll.addEventListener('change', function () {
                    if (agenceAll.checked) {
                        agenceChoices.forEach(function (input) { input.checked = false; });
                    }
                    applyAgenceMultiFilter();
                });
            }

            agenceChoices.forEach(function (input) {
                input.addEventListener('change', applyAgenceMultiFilter);
            });

            document.addEventListener('click', function (e) {
                if (agencePanel && !agenceMulti.contains(e.target)) {
                    agencePanel.hidden = true;
                    if (agenceToggle) agenceToggle.setAttribute('aria-expanded', 'false');
                }
            });

            updateAgenceMultiLabel();
        }

        if (agenceSelect) {
            agenceSelect.addEventListener('change', function () {
                var options = Array.from(agenceSelect.options || []);
                var allOption = options.filter(function (option) { return option.value === ''; })[0];
                var allSelected = allOption && allOption.selected;

                if (allSelected) {
                    options.forEach(function (option) {
                        option.selected = option.value === '';
                    });
                }

                var selected = Array.from(agenceSelect.selectedOptions || [])
                    .map(function (option) { return option.value; })
                    .filter(function (value) { return value && value !== 'all'; });

                if (selected.length > 0 && allOption) {
                    allOption.selected = false;
                } else if (selected.length === 0 && allOption) {
                    allOption.selected = true;
                }

                filterAgences.length = 0;
                selected.forEach(function (value) {
                    if (filterAgences.indexOf(value) === -1) filterAgences.push(value);
                });

                page = 1;
                render();
                updateChipCounts();
            });
        }

        // ── FILTRE PRIX
        if (rangeInp) {
            rangeInp.addEventListener('input', function () {
                var val = parseInt(this.value);
                if (pmaxInp)    pmaxInp.value = val;
                if (priceLabel) priceLabel.textContent = val.toLocaleString('fr-FR');
            });
        }
        if (btnFiltre) {
            btnFiltre.addEventListener('click', function () {
                var minVal = pminInp ? parseFloat(pminInp.value) : 0;
                var maxVal = pmaxInp ? parseFloat(pmaxInp.value) : Infinity;
                filterPrixMin = isNaN(minVal) ? 0 : minVal;
                filterPrixMax = isNaN(maxVal) ? Infinity : maxVal;
                page = 1;
                render();
            });
        }

        // ── RECHERCHE
        if (searchInp) {
            searchInp.addEventListener('input', function () {
                filterSearch = this.value.toLowerCase().trim();
                page = 1;
                render();
            });
        }

        // ── TRI SELECT
        if (sortSel) {
            sortSel.addEventListener('change', function () {
                sortMode = this.value;
                page = 1;
                render();
            });
        }

        // ── PAR PAGE
        if (perPageSel) {
            perPageSel.addEventListener('change', function () {
                parPage = parseInt(this.value) || 20;
                page = 1;
                render();
            });
        }

        // ── VUE GRILLE / LISTE
        wrap.querySelectorAll('.slbp-view-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                wrap.querySelectorAll('.slbp-view-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                if (btn.dataset.view === 'list') {
                    grid.classList.add('view-list');
                } else {
                    grid.classList.remove('view-list');
                }
            });
        });

        // ── MOBILE SIDEBAR TOGGLE
        function openSidebar() {
            if (sidebar) sidebar.classList.add('is-open');
            if (sidebarOverlay) sidebarOverlay.classList.add('is-active');
        }

        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('is-open');
            if (sidebarOverlay) sidebarOverlay.classList.remove('is-active');
        }

        if (mobileFilterBtn) {
            mobileFilterBtn.addEventListener('click', openSidebar);
        }
        if (closeSidebarBtn) {
            closeSidebarBtn.addEventListener('click', closeSidebar);
        }
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }

        // ── CHIPS FILTER MOBILE
        var chipsWrap    = document.querySelector('.slbp-chips-filter');
        var chipsCats    = wrap.querySelectorAll('.slbp-chip-cat');
        var chipsAgence  = wrap.querySelectorAll('.slbp-d-chip[data-agence]');
        var chipRange    = wrap.querySelector('.slbp-chip-range');
        var chipPrixVal  = wrap.querySelector('.slbp-chip-prix-val');
        var chipPresets  = wrap.querySelectorAll('.slbp-px-preset');
        var chipsReset   = wrap.querySelector('.slbp-chips-reset');
        var chipsTotal   = wrap.querySelector('.slbp-total-chips');
        var pillAgence   = wrap.querySelector('.slbp-filter-pill[id*="pill-ag"]');
        var pillPrix     = wrap.querySelector('.slbp-filter-pill[id*="pill-px"]');
        var ddAgence     = wrap.querySelector('.slbp-dd[id*="dd-ag"]');
        var ddPrix       = wrap.querySelector('.slbp-dd[id*="dd-px"]');
        var chipPrixMax  = chipRange ? parseInt(chipRange.dataset.max) || Infinity : Infinity;
        var activeMobPrix = chipPrixMax;

        function closeAllDd() {
            wrap.querySelectorAll('.slbp-dd').forEach(function(d) { d.classList.remove('open'); });
        }

        function toggleDd(dd, pill) {
            var wasOpen = dd && dd.classList.contains('open');
            closeAllDd();
            if (!wasOpen && dd) {
                dd.classList.add('open');
                if (pill) pill.classList.add('active');
            }
        }

        if (pillAgence) {
            pillAgence.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleDd(ddAgence, pillAgence);
            });
        }
        if (pillPrix) {
            pillPrix.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleDd(ddPrix, pillPrix);
            });
        }

        // Clic catégorie chip
        chipsCats.forEach(function(chip) {
            chip.addEventListener('click', function() {
                chipsCats.forEach(function(c) { c.classList.remove('active'); });
                chip.classList.add('active');
                var cat = chip.dataset.cat;
                filterCats.length = 0;
                if (cat !== 'all') filterCats.push(cat);
                page = 1;
                render();
                updateChipCounts();
            });
        });

        // Clic agence chip
        chipsAgence.forEach(function(chip) {
            chip.addEventListener('click', function() {
                chipsAgence.forEach(function(c) { c.classList.remove('active'); });
                chip.classList.add('active');
                filterAgences.length = 0;
                var ag = chip.dataset.agence;
                if (ag !== 'all') filterAgences.push(ag);
                if (pillAgence) pillAgence.classList.toggle('active', ag !== 'all');
                page = 1;
                render();
                updateChipCounts();
                setTimeout(closeAllDd, 150);
            });
        });

        // Slider prix chips
        if (chipRange) {
            chipRange.addEventListener('input', function() {
                activeMobPrix = parseInt(this.value);
                filterPrixMax = activeMobPrix;
                if (chipPrixVal) chipPrixVal.textContent = activeMobPrix.toLocaleString('fr-FR');
                chipPresets.forEach(function(p) { p.classList.remove('active'); });
                if (pillPrix) pillPrix.classList.toggle('active', activeMobPrix < chipPrixMax);
                page = 1;
                render();
                updateChipCounts();
            });
        }

        // Presets prix
        chipPresets.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var val = parseInt(btn.dataset.val);
                activeMobPrix = val;
                filterPrixMax = val >= chipPrixMax ? Infinity : val;
                if (chipRange) chipRange.value = Math.min(val, chipPrixMax);
                if (chipPrixVal) chipPrixVal.textContent = val >= chipPrixMax ? chipPrixMax.toLocaleString('fr-FR') : val.toLocaleString('fr-FR');
                chipPresets.forEach(function(p) { p.classList.remove('active'); });
                btn.classList.add('active');
                if (pillPrix) pillPrix.classList.toggle('active', val < chipPrixMax);
                page = 1;
                render();
                updateChipCounts();
                if (val < chipPrixMax) setTimeout(closeAllDd, 150);
            });
        });

        // Reset chips
        if (chipsReset) {
            chipsReset.addEventListener('click', function() {
                chipsCats.forEach(function(c) { c.classList.toggle('active', c.dataset.cat === 'all'); });
                chipsAgence.forEach(function(c) { c.classList.toggle('active', c.dataset.agence === 'all'); });
                if (chipRange) chipRange.value = chipPrixMax;
                if (chipPrixVal) chipPrixVal.textContent = chipPrixMax.toLocaleString('fr-FR');
                chipPresets.forEach(function(p) { p.classList.toggle('active', parseInt(p.dataset.val) >= chipPrixMax); });
                if (pillAgence) pillAgence.classList.remove('active');
                if (pillPrix) pillPrix.classList.remove('active');
                filterCats.length = 0;
                filterAgences.length = 0;
                filterPrixMax = Infinity;
                activeMobPrix = chipPrixMax;
                page = 1;
                closeAllDd();
                render();
                updateChipCounts();
            });
        }

        // Fermer dropdowns au clic extérieur
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.slbp-dd-wrap')) closeAllDd();
        });

        // Mettre à jour les compteurs chips
        function updateChipCounts() {
            if (!chipsWrap) return;
            var total = 0;
            chipsCats.forEach(function(chip) {
                var cat = chip.dataset.cat;
                var count = 0;
                allCards.forEach(function(c) {
                    var catOk = cat === 'all' || (c.dataset.cat || '').split(',').some(function(v) { return v.trim() === cat; });
                    var agOk  = filterAgences.length === 0 || filterAgences.indexOf(c.dataset.agence) !== -1;
                    var pxOk  = parseFloat(c.dataset.prixAp) <= (filterPrixMax === Infinity ? 9999999 : filterPrixMax);
                    if (catOk && agOk && pxOk) count++;
                });
                var span = chip.querySelector('.slbp-chip-count');
                if (span) span.textContent = count;
                if (cat === 'all') total = count;
            });
            if (chipsTotal) chipsTotal.textContent = (filterCats.length > 0
                ? allCards.filter(function(c) {
                    var agOk = filterAgences.length === 0 || filterAgences.indexOf(c.dataset.agence) !== -1;
                    var pxOk = parseFloat(c.dataset.prixAp) <= (filterPrixMax === Infinity ? 9999999 : filterPrixMax);
                    var catOk = filterCats.some(function(f) { return (c.dataset.cat||'').split(',').some(function(v){return v.trim()===f;}); });
                    return catOk && agOk && pxOk;
                }).length
                : total);
            var hasFilters = filterCats.length > 0 || filterAgences.length > 0 || filterPrixMax !== Infinity;
            if (chipsReset) chipsReset.classList.toggle('visible', hasFilters);
        }

        // ── INIT
        render();
        updateChipCounts();
    });

})();
