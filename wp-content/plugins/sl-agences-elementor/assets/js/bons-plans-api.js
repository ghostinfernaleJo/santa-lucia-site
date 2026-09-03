/**
 * Bons Plans — pagination REST.
 *
 * Le premier écran est rendu côté serveur pour rester visible sans JavaScript.
 * Les pages, recherches et filtres suivants ne demandent ensuite que les
 * offres utiles, au lieu de transporter tout le catalogue dans le DOM.
 */
(function () {
    'use strict';

    function initBonsPlans() {
        document.querySelectorAll('.slbp-wrapper').forEach(function (wrap) {
            if (wrap.dataset.slbpInitialized === '1') return;

            var endpoint = wrap.dataset.endpoint || '';
            var grid     = wrap.querySelector('.slbp-grid');
            var emptyBox = wrap.querySelector('.slbp-empty');
            var pagDiv   = wrap.querySelector('.slbp-pagination');
            if (!endpoint || !grid || !emptyBox || !pagDiv) return;

            wrap.dataset.slbpInitialized = '1';

            var parPage       = parseInt(wrap.dataset.parPage, 10) || 20;
            var page          = parseInt(wrap.dataset.page, 10) || 1;
            var total         = parseInt(wrap.dataset.total, 10) || 0;
            var totalPages    = parseInt(wrap.dataset.totalPages, 10) || Math.ceil(total / parPage);
            var filterCats    = [];
            var filterAgences = [];
            var filterSearch  = '';
            var filterPrixMin = 0;
            var filterPrixMax = Infinity;
            var sortMode      = 'recent';
            var requestId     = 0;
            var controller    = null;
            var searchTimer   = null;

            var catCheckboxes   = wrap.querySelectorAll('[data-filter="cat"] li');
            var agenceMs        = wrap.querySelector('.slbp-agence-ms[data-filter="agence"]') || wrap.querySelector('.slbp-ms[data-filter="agence"]');
            var agenceSelect    = wrap.querySelector('select[data-filter="agence"]');
            var sortSel         = wrap.querySelector('.slbp-sort:not(.slbp-sort-mobile)') || wrap.querySelector('.slbp-sort');
            var perPageSel      = wrap.querySelector('.slbp-per-page-sel');
            var searchInp       = wrap.querySelector('.slbp-search');
            var elTotal         = wrap.querySelector('.slbp-total');
            var elFrom          = wrap.querySelector('.slbp-range-from');
            var elTo            = wrap.querySelector('.slbp-range-to');
            var btnFiltre       = wrap.querySelector('.slbp-btn-filtre');
            var pminInp         = wrap.querySelector('.slbp-pmin');
            var pmaxInp         = wrap.querySelector('.slbp-pmax');
            var rangeInp        = wrap.querySelector('.slbp-price-range');
            var priceLabel      = wrap.querySelector('.slbp-price-label-val');
            var sidebar         = wrap.querySelector('.slbp-sidebar');
            var mobileFilterBtn = wrap.querySelector('.slbp-mobile-filter-btn');
            var closeSidebarBtn = wrap.querySelector('.slbp-close-sidebar');
            var sidebarOverlay  = wrap.querySelector('.slbp-sidebar-overlay');
            var statusEl        = wrap.querySelector('.slbp-load-status');
            var orderAgencySelect = wrap.querySelector('.slbp-order-agency');

            function hasActiveFilters() {
                return filterCats.length > 0 || filterAgences.length > 0 || filterSearch !== ''
                    || filterPrixMin > 0 || filterPrixMax !== Infinity;
            }

            function updateFilterBadge() {
                if (!mobileFilterBtn) return;
                var count = filterCats.length + filterAgences.length + (filterSearch ? 1 : 0)
                    + (filterPrixMin > 0 || filterPrixMax !== Infinity ? 1 : 0);
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

            function showStatus(message, isError) {
                if (!statusEl) return;
                statusEl.textContent = message || '';
                statusEl.classList.toggle('is-error', !!isError);
                statusEl.classList.toggle('is-visible', !!message);
            }

            function setLoading(loading) {
                grid.classList.toggle('is-loading', !!loading);
                grid.setAttribute('aria-busy', loading ? 'true' : 'false');
                if (loading) showStatus('Chargement des offres…');
            }

            function queryUrl() {
                var params = new URLSearchParams();
                params.set('page', String(page));
                params.set('per_page', String(parPage));
                params.set('orderby', sortMode || 'recent');
                params.set('actifs', 'true');
                params.set('render', 'true');
                if (filterCats.length) params.set('categorie', filterCats.join(','));
                if (filterAgences.length) params.set('agence', filterAgences.join(','));
                if (filterSearch) params.set('search', filterSearch);
                if (filterPrixMin > 0) params.set('min_price', String(filterPrixMin));
                if (filterPrixMax !== Infinity) params.set('max_price', String(filterPrixMax));
                return endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + params.toString();
            }

            function updateResults(pagination, visibleCount) {
                pagination = pagination || {};
                total      = Math.max(0, parseInt(pagination.total, 10) || 0);
                page       = Math.max(1, parseInt(pagination.page, 10) || page);
                parPage    = Math.max(1, parseInt(pagination.per_page, 10) || parPage);
                totalPages = Math.max(0, parseInt(pagination.total_pages, 10) || (total ? Math.ceil(total / parPage) : 0));

                var from = total === 0 ? 0 : ((page - 1) * parPage + 1);
                var to   = total === 0 ? 0 : Math.min(total, from + Math.max(0, visibleCount) - 1);
                if (total > 0 && visibleCount === 0) to = Math.min(total, page * parPage);

                if (elTotal) elTotal.textContent = total;
                if (elFrom) elFrom.textContent = from;
                if (elTo) elTo.textContent = to;
                wrap.dataset.total = String(total);
                wrap.dataset.totalPages = String(totalPages);
                wrap.dataset.page = String(page);
                emptyBox.style.display = total === 0 ? 'block' : 'none';
                renderPagination(totalPages);
                updateFilterBadge();
            }

            function loadOffers(targetPage, scrollAfterLoad) {
                page = Math.max(1, parseInt(targetPage, 10) || 1);
                var thisRequest = ++requestId;

                if (controller && typeof controller.abort === 'function') controller.abort();
                controller = typeof window.AbortController === 'function' ? new window.AbortController() : null;
                setLoading(true);

                var options = { credentials: 'same-origin' };
                if (controller) options.signal = controller.signal;

                fetch(queryUrl(), options)
                    .then(function (response) {
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        return response.json();
                    })
                    .then(function (payload) {
                        if (thisRequest !== requestId) return;
                        if (!payload || !Array.isArray(payload.items) || !payload.pagination) {
                            throw new Error('Réponse API invalide');
                        }

                        var cards = payload.items.map(function (item) {
                            return item && item.html ? item.html : '';
                        });
                        if (payload.items.length && cards.some(function (html) { return !html; })) {
                            throw new Error('Carte manquante');
                        }

                        grid.innerHTML = cards.join('');
                        updateResults(payload.pagination, cards.length);
                        showStatus('');
                        if (scrollAfterLoad) {
                            wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    })
                    .catch(function (error) {
                        if (error && error.name === 'AbortError') return;
                        if (thisRequest !== requestId) return;
                        showStatus('Impossible de charger les offres. Vérifiez votre connexion puis réessayez.', true);
                    })
                    .then(function () {
                        if (thisRequest === requestId) setLoading(false);
                    });
            }

            function renderPagination(pages) {
                pagDiv.innerHTML = '';
                if (pages <= 1) return;
                if (page > 1) pagDiv.appendChild(makePageButton('‹', page - 1, false, true));

                var visible = [1];
                for (var index = page - 1; index <= page + 1; index++) {
                    if (index > 1 && index < pages && visible.indexOf(index) === -1) visible.push(index);
                }
                if (pages > 1 && visible.indexOf(pages) === -1) visible.push(pages);
                visible.sort(function (a, b) { return a - b; });

                var previous = 0;
                visible.forEach(function (number) {
                    if (previous && number - previous > 1) {
                        var dots = document.createElement('span');
                        dots.className = 'dots';
                        dots.textContent = '…';
                        dots.setAttribute('aria-hidden', 'true');
                        pagDiv.appendChild(dots);
                    }
                    pagDiv.appendChild(makePageButton(number, number, number === page, false));
                    previous = number;
                });
                if (page < pages) pagDiv.appendChild(makePageButton('›', page + 1, false, true));
            }

            function makePageButton(label, targetPage, isActive, isNav) {
                var button = document.createElement('a');
                button.href = '#';
                button.textContent = label;
                if (isActive) {
                    button.classList.add('active');
                    button.setAttribute('aria-current', 'page');
                }
                if (isNav) button.classList.add('nav');
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    if (targetPage !== page) loadOffers(targetPage, true);
                });
                return button;
            }

            function bindCategoryCheckboxes() {
                catCheckboxes.forEach(function (item) {
                    var checkbox = item.querySelector('input[type="checkbox"]');
                    if (!checkbox) return;
                    checkbox.addEventListener('change', function () {
                        var value = item.dataset.value || '';
                        var position = filterCats.indexOf(value);
                        if (checkbox.checked && position === -1) {
                            filterCats.push(value);
                            item.classList.add('checked');
                        } else if (!checkbox.checked && position !== -1) {
                            filterCats.splice(position, 1);
                            item.classList.remove('checked');
                        }
                        loadOffers(1, false);
                    });
                });
            }
            bindCategoryCheckboxes();

            function setupAgenceMultiselect() {
                if (!agenceMs) return;
                var prefix  = agenceMs.classList.contains('slbp-agence-ms') ? 'slbp-agence-ms' : 'slbp-ms';
                var toggle  = agenceMs.querySelector('.' + prefix + '-toggle');
                var panel   = agenceMs.querySelector('.' + prefix + '-panel') || agenceMs.querySelector('.' + prefix + '-dropdown');
                var label   = agenceMs.querySelector('.' + prefix + '-label');
                var caret   = agenceMs.querySelector('.' + prefix + '-caret');
                var choices = agenceMs.querySelectorAll('.' + prefix + '-choice');
                var all     = agenceMs.querySelector('.' + prefix + '-all');
                if (!toggle || !panel) return;

                document.body.appendChild(panel);
                panel.removeAttribute('hidden');
                panel.style.display = 'none';
                panel.style.position = 'fixed';
                panel.style.zIndex = '1000003';
                panel.style.background = '#fff';
                panel.style.border = '1px solid #e0e0e0';
                panel.style.borderRadius = '5px';
                panel.style.boxShadow = '0 4px 14px rgba(0,0,0,.1)';
                panel.style.padding = '4px 0';
                panel.style.maxHeight = '200px';
                panel.style.overflowY = 'auto';
                panel.querySelectorAll('.slbp-agence-ms-option').forEach(function (option) {
                    option.style.display = 'flex';
                    option.style.alignItems = 'center';
                    option.style.gap = '8px';
                    option.style.padding = '6px 12px';
                    option.style.cursor = 'pointer';
                    option.style.whiteSpace = 'nowrap';
                });

                function positionPanel() {
                    var rect = toggle.getBoundingClientRect();
                    var height = panel.offsetHeight || 200;
                    var obstruction = 0;
                    var bottomBar = document.querySelector('.klb-mobile-bottom');
                    if (bottomBar) {
                        var bottomRect = bottomBar.getBoundingClientRect();
                        if (bottomRect.height > 0 && bottomRect.top < window.innerHeight) {
                            obstruction = window.innerHeight - bottomRect.top;
                        }
                    }
                    var bottom = window.innerHeight - obstruction;
                    var top = (bottom - rect.bottom < height + 8 && rect.top > height + 8)
                        ? rect.top - height - 4 : rect.bottom + 4;
                    top = Math.max(8, Math.min(top, bottom - height - 8));
                    var width = Math.min(rect.width > 120 ? rect.width : 240, window.innerWidth - 16);
                    var left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));
                    panel.style.top = top + 'px';
                    panel.style.left = left + 'px';
                    panel.style.width = width + 'px';
                }

                function closePanel() {
                    panel.style.display = 'none';
                    agenceMs.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                    if (caret) caret.textContent = '▾';
                }
                function openPanel() {
                    panel.style.display = 'block';
                    positionPanel();
                    requestAnimationFrame(positionPanel);
                    setTimeout(positionPanel, 330);
                    agenceMs.classList.add('is-open');
                    toggle.setAttribute('aria-expanded', 'true');
                    if (caret) caret.textContent = '▴';
                }
                function syncAgencies(load) {
                    filterAgences = [];
                    var selected = [];
                    choices.forEach(function (choice) {
                        if (!choice.checked) return;
                        filterAgences.push(choice.value);
                        var valueLabel = choice.dataset.label || choice.value;
                        if (valueLabel) selected.push(valueLabel);
                    });
                    if (label) {
                        label.textContent = selected.length === 0 ? 'Toutes les agences'
                            : (selected.length === 1 ? selected[0] : selected.length + ' agences');
                    }
                    agenceMs.classList.toggle('has-value', selected.length > 0);
                    if (all) all.checked = selected.length === 0;
                    if (load !== false) loadOffers(1, false);
                }

                toggle.addEventListener('click', function (event) {
                    event.stopPropagation();
                    if (agenceMs.classList.contains('is-open')) closePanel(); else openPanel();
                });
                document.addEventListener('click', function (event) {
                    if (!agenceMs.contains(event.target) && !panel.contains(event.target)) closePanel();
                });
                window.addEventListener('scroll', function () {
                    if (agenceMs.classList.contains('is-open')) positionPanel();
                }, true);
                window.addEventListener('resize', function () {
                    if (agenceMs.classList.contains('is-open')) positionPanel();
                });
                if (all) {
                    all.addEventListener('change', function () {
                        if (!all.checked) return;
                        choices.forEach(function (choice) { choice.checked = false; });
                        syncAgencies();
                    });
                }
                choices.forEach(function (choice) {
                    choice.addEventListener('change', function () {
                        if (all && all.checked) all.checked = false;
                        syncAgencies();
                    });
                });

                try {
                    var requested = new URLSearchParams(window.location.search).get('agence');
                    if (requested) {
                        var wanted = requested.split(',').map(function (value) { return value.trim(); }).filter(Boolean);
                        var any = false;
                        choices.forEach(function (choice) {
                            if (wanted.indexOf(choice.value) !== -1) {
                                choice.checked = true;
                                any = true;
                            }
                        });
                        if (any) {
                            if (all) all.checked = false;
                            syncAgencies();
                        }
                    }
                } catch (ignore) {}
            }
            setupAgenceMultiselect();

            function applyOrderAgency(value, shouldLoad) {
                value = value || '';
                filterAgences = value ? [value] : [];

                if (agenceMs) {
                    var all = agenceMs.querySelector('.slbp-agence-ms-all');
                    agenceMs.querySelectorAll('.slbp-agence-ms-choice').forEach(function (choice) {
                        choice.checked = value !== '' && choice.value === value;
                    });
                    if (all) all.checked = value === '';
                    var label = agenceMs.querySelector('.slbp-agence-ms-label');
                    if (label) {
                        var selected = agenceMs.querySelector('.slbp-agence-ms-choice:checked');
                        label.textContent = selected ? (selected.dataset.label || selected.value) : 'Toutes les agences';
                    }
                }
                if (agenceSelect) agenceSelect.value = value;
                updateFilterBadge();
                if (shouldLoad) loadOffers(1, false);
            }

            if (orderAgencySelect) {
                var storedAgency = '';
                try { storedAgency = window.localStorage.getItem('slbp-order-agency') || ''; } catch (ignore) {}
                var initialAgency = orderAgencySelect.dataset.cartAgency || storedAgency;
                if (initialAgency && orderAgencySelect.querySelector('option[value="' + initialAgency.replace(/"/g, '\\"') + '"]')) {
                    orderAgencySelect.value = initialAgency;
                    applyOrderAgency(initialAgency, true);
                }
                orderAgencySelect.addEventListener('change', function () {
                    var value = orderAgencySelect.value || '';
                    try {
                        if (value) window.localStorage.setItem('slbp-order-agency', value);
                        else window.localStorage.removeItem('slbp-order-agency');
                    } catch (ignore) {}
                    applyOrderAgency(value, true);
                });
            }

            if (agenceSelect) {
                agenceSelect.addEventListener('change', function () {
                    filterAgences = agenceSelect.value ? [agenceSelect.value] : [];
                    loadOffers(1, false);
                });
            }

            if (rangeInp) {
                rangeInp.addEventListener('input', function () {
                    var value = parseInt(rangeInp.value, 10) || 0;
                    if (pmaxInp) pmaxInp.value = value;
                    if (priceLabel) priceLabel.textContent = value.toLocaleString('fr-FR');
                });
            }
            function applyPriceFilter() {
                var min = pminInp ? parseFloat(pminInp.value) : 0;
                var max = pmaxInp ? parseFloat(pmaxInp.value) : Infinity;
                filterPrixMin = isNaN(min) ? 0 : Math.max(0, min);
                filterPrixMax = isNaN(max) ? Infinity : Math.max(filterPrixMin, max);
                loadOffers(1, false);
            }
            if (btnFiltre) btnFiltre.addEventListener('click', applyPriceFilter);
            [pminInp, pmaxInp].forEach(function (input) {
                if (!input) return;
                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') applyPriceFilter();
                });
            });

            if (searchInp) {
                searchInp.addEventListener('input', function () {
                    filterSearch = searchInp.value.toLowerCase().trim();
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(function () { loadOffers(1, false); }, 280);
                });
            }
            if (sortSel) {
                sortSel.addEventListener('change', function () {
                    sortMode = sortSel.value || 'recent';
                    loadOffers(1, false);
                });
            }
            if (perPageSel) {
                var option = perPageSel.querySelector('option[value="' + parPage + '"]');
                if (option) perPageSel.value = String(parPage);
                perPageSel.addEventListener('change', function () {
                    parPage = parseInt(perPageSel.value, 10) || 20;
                    loadOffers(1, false);
                });
            }

            wrap.querySelectorAll('.slbp-view-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    wrap.querySelectorAll('.slbp-view-btn').forEach(function (item) { item.classList.remove('active'); });
                    button.classList.add('active');
                    grid.classList.toggle('view-list', button.dataset.view === 'list');
                });
            });

            var savedScrollY = 0;
            var sidebarMarker = null;
            var overlayMarker = null;
            function openSidebar() {
                if (!sidebar) return;
                if (sidebar.parentElement !== document.body) {
                    sidebarMarker = document.createComment('slbp-sidebar');
                    sidebar.before(sidebarMarker);
                    document.body.appendChild(sidebar);
                    if (sidebarOverlay) {
                        overlayMarker = document.createComment('slbp-overlay');
                        sidebarOverlay.before(overlayMarker);
                        document.body.appendChild(sidebarOverlay);
                    }
                }
                sidebar.classList.add('is-open');
                if (sidebarOverlay) sidebarOverlay.classList.add('is-active');
                savedScrollY = window.scrollY || document.documentElement.scrollTop || 0;
                document.body.style.position = 'fixed';
                document.body.style.top = (-savedScrollY) + 'px';
                document.body.style.left = '0';
                document.body.style.right = '0';
                document.body.style.width = '100%';
                document.body.style.overflow = 'hidden';
            }
            function closeSidebar() {
                if (!sidebar || !sidebar.classList.contains('is-open')) return;
                if (sidebar) sidebar.classList.remove('is-open');
                if (sidebarOverlay) sidebarOverlay.classList.remove('is-active');
                document.body.style.position = '';
                document.body.style.top = '';
                document.body.style.left = '';
                document.body.style.right = '';
                document.body.style.width = '';
                document.body.style.overflow = '';
                window.scrollTo(0, savedScrollY);
                if (sidebarMarker && sidebar) {
                    sidebarMarker.replaceWith(sidebar);
                    sidebarMarker = null;
                }
                if (overlayMarker && sidebarOverlay) {
                    overlayMarker.replaceWith(sidebarOverlay);
                    overlayMarker = null;
                }
            }
            if (mobileFilterBtn) mobileFilterBtn.addEventListener('click', function (event) { event.preventDefault(); openSidebar(); });
            if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeSidebar);
            if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

            var sortMobileBtn = wrap.querySelector('.slbp-sort-mobile-btn');
            var sortMobilePanel = wrap.querySelector('.slbp-mobile-sort-panel');
            var sortMobileSel = wrap.querySelector('.slbp-sort-mobile');
            if (sortMobileBtn && sortMobilePanel) {
                sortMobileBtn.addEventListener('click', function (event) {
                    event.preventDefault();
                    sortMobilePanel.classList.toggle('active');
                    sortMobileBtn.style.color = sortMobilePanel.classList.contains('active') ? 'var(--sl-red)' : '';
                });
                document.addEventListener('click', function (event) {
                    if (!sortMobileBtn.contains(event.target) && !sortMobilePanel.contains(event.target)) {
                        sortMobilePanel.classList.remove('active');
                        sortMobileBtn.style.color = '';
                    }
                });
            }
            if (sortMobileSel && sortSel) {
                sortMobileSel.addEventListener('change', function () {
                    sortSel.value = sortMobileSel.value;
                    sortSel.dispatchEvent(new Event('change'));
                    if (sortMobilePanel) sortMobilePanel.classList.remove('active');
                    if (sortMobileBtn) sortMobileBtn.style.color = '';
                });
                sortSel.addEventListener('change', function () { sortMobileSel.value = sortSel.value; });
            }

            wrap.addEventListener('click', function (event) {
                var share = event.target.closest ? event.target.closest('.slbp-share-btn') : null;
                if (!share) return;
                event.preventDefault();
                event.stopPropagation();
                var card = share.closest('.slbp-card');
                var cardLink = card ? card.querySelector('.slbp-card-link[href]') : null;
                var url = cardLink && cardLink.href ? cardLink.href : window.location.href;
                var title = share.dataset.titre || '';
                var price = share.dataset.prix || '';
                var text = 'Bon plan Santa Lucia : ' + title + (price ? ' à ' + price : '') + ' 🔥';
                if (navigator.share) {
                    navigator.share({ title: title, text: text, url: url }).catch(function () {});
                } else {
                    window.open('https://wa.me/?text=' + encodeURIComponent(text + '\n' + url), '_blank', 'noopener');
                }
            });

            var pdfBtn = wrap.querySelector('.slbp-pdf-btn');
            if (pdfBtn) {
                pdfBtn.addEventListener('click', function () {
                    var base = pdfBtn.getAttribute('data-base') || (window.location.origin + '/');
                    var url = base + (base.indexOf('?') > -1 ? '&' : '?') + 'sl_bp_pdf=1';
                    if (filterAgences.length) url += '&agence=' + encodeURIComponent(filterAgences.join(','));
                    pdfBtn.setAttribute('href', url);
                });
            }

            updateResults({ total: total, page: page, per_page: parPage, total_pages: totalPages }, grid.querySelectorAll('.slbp-card').length);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBonsPlans);
    } else {
        window.setTimeout(initBonsPlans, 0);
    }
    window.addEventListener('load', initBonsPlans);
})();
