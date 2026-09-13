(function () {
  'use strict';
  var root = document.querySelector('.slcat');
  if (!root) return;
  // La configuration vit aussi dans le HTML : certains optimiseurs WordPress
  // retardent les variables localisées alors que le script est déjà exécuté.
  var config = window.SLCatalogue || {
    ajax: root.dataset.ajax,
    cartUrl: root.dataset.cartUrl,
    emptyCopy: root.dataset.emptyCopy,
    errorCopy: root.dataset.errorCopy
  };
  if (!config.ajax) return;
  var agency = root.querySelector('#slcat-agency');
  var search = root.querySelector('#slcat-search');
  var results = root.querySelector('.slcat__results-content');
  var reset = root.querySelector('.slcat__reset');
  var toast = root.querySelector('.slcat__toast');
  var availability = root.querySelector('.slcat__availability');
  var pagination = root.querySelector('.slcat__pagination');
  var selectedCategory = '';
  var currentPage = 1;
  var totalPages = 1;
  var timer;

  function endpoint(name) { return config.ajax.replace('%endpoint%', name); }
  function selectedAgency() { return agency.value; }
  function showToast(message, cartLink) {
    toast.innerHTML = message + (cartLink ? ' <a href="' + config.cartUrl + '">Voir le panier</a>' : '');
    toast.hidden = false;
    window.clearTimeout(showToast.timeout);
    showToast.timeout = window.setTimeout(function () { toast.hidden = true; }, 4200);
  }
  function empty(message) { results.innerHTML = '<p class="slcat__empty">' + message + '</p>'; }
  function renderPagination() {
    if (!pagination || totalPages <= 1) { if (pagination) pagination.innerHTML = ''; return; }
    var pages = [];
    for (var i = 1; i <= totalPages; i++) if (i === 1 || i === totalPages || Math.abs(i - currentPage) <= 2) pages.push(i);
    var html = '<ul class="slcat-pagination">';
    if (currentPage > 1) html += '<li><button type="button" class="slcat-pagination__prev" data-page="' + (currentPage - 1) + '">← Précédent</button></li>';
    var previous = 0;
    pages.forEach(function (page) {
      if (previous && page - previous > 1) html += '<li class="slcat-pagination__gap" aria-hidden="true">…</li>';
      html += '<li><button type="button" class="' + (page === currentPage ? 'is-current' : '') + '" data-page="' + page + '" aria-current="' + (page === currentPage ? 'page' : 'false') + '">' + page + '</button></li>';
      previous = page;
    });
    if (currentPage < totalPages) html += '<li><button type="button" class="slcat-pagination__next" data-page="' + (currentPage + 1) + '">Suivant →</button></li>';
    pagination.innerHTML = html + '</ul>';
  }
  function loadProducts(page) {
    if (!selectedAgency()) { currentPage = 1; totalPages = 1; renderPagination(); empty(config.emptyCopy); return; }
    currentPage = Math.max(1, parseInt(page || 1, 10));
    empty('Chargement des produits…');
    var body = new URLSearchParams({agency: selectedAgency(), category: selectedCategory, search: search.value.trim(), page: currentPage});
    fetch(endpoint('products'), {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body.toString()})
      .then(function (res) { return res.json(); })
      .then(function (res) {
        if (!res.success) throw new Error();
        totalPages = Math.max(1, parseInt(res.data.pages || 1, 10));
        if (res.data.empty) { empty('Aucun produit disponible pour cette recherche dans cette agence.'); renderPagination(); return; }
        results.innerHTML = '<div class="slcat__product-grid">' + res.data.html + '</div>';
        renderPagination();
      }).catch(function () { empty(config.errorCopy); });
  }
  function updateAgencyState() {
    var value = selectedAgency();
    search.disabled = !value;
    root.dataset.ready = value ? 'true' : 'false';
    if (availability) {
      availability.innerHTML = '<span></span>' + (value
        ? 'Produits, prix et stock affichés pour l’agence « ' + agency.options[agency.selectedIndex].text + ' ».'
        : 'Choisissez votre agence : les prix et le stock peuvent varier selon le magasin.');
    }
  }
  function setAgency() {
    var value = selectedAgency();
    updateAgencyState();
    if (value) localStorage.setItem('sl_catalogue_agency', value);
    selectedCategory = '';
    currentPage = 1;
    reset.hidden = true;
    loadProducts();
  }
  function setCategory(value, activeButton) {
    selectedCategory = value || '';
    root.querySelectorAll('.slcat__category, .slcat__all-cats').forEach(function (button) {
      button.classList.toggle('is-active', button === activeButton);
    });
    reset.hidden = !selectedCategory;
    currentPage = 1;
    loadProducts(1);
  }
  var saved = localStorage.getItem('sl_catalogue_agency');
  if (!agency.value && saved && agency.querySelector('option[value="' + CSS.escape(saved) + '"]')) agency.value = saved;
  updateAgencyState();
  // Une agence déjà pré-remplie (panier ou choix précédent) doit charger
  // immédiatement son catalogue, sans obliger le client à la re-sélectionner.
  if (agency.value) loadProducts();
  agency.addEventListener('change', setAgency);
  search.addEventListener('input', function () { window.clearTimeout(timer); timer = window.setTimeout(function () { currentPage = 1; loadProducts(1); }, 280); });
  root.querySelectorAll('.slcat__category').forEach(function (button) {
    button.addEventListener('click', function () {
      if (!selectedAgency()) { agency.focus(); showToast('Choisissez votre agence avant de consulter ce rayon.'); return; }
      setCategory(button.dataset.category || '', button);
      root.querySelector('.slcat__results').scrollIntoView({behavior:'smooth', block:'start'});
    });
  });
  root.querySelector('.slcat__all-cats').addEventListener('click', function () { setCategory('', root.querySelector('.slcat__all-cats')); });
  reset.addEventListener('click', function () { setCategory('', root.querySelector('.slcat__all-cats')); });
  if (pagination) pagination.addEventListener('click', function (event) {
    var button = event.target.closest('button[data-page]');
    if (!button) return;
    loadProducts(parseInt(button.dataset.page, 10));
    root.querySelector('.slcat__results').scrollIntoView({behavior:'smooth', block:'start'});
  });
  root.addEventListener('click', function (event) {
    var button = event.target.closest('.slcat-product__add');
    if (!button) return;
    button.disabled = true;
    fetch(endpoint('add'), {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({product_id:button.dataset.product, agency:button.dataset.agency}).toString()})
      .then(function (res) { return res.json(); }).then(function (res) {
        if (!res.success) throw new Error(res.data && res.data.message ? res.data.message : config.errorCopy);
        if (res.data.fragments && window.jQuery) window.jQuery(document.body).trigger('added_to_cart', [res.data.fragments, res.data.cart_hash, button]);
        showToast(res.data.message, true);
      }).catch(function (error) { showToast(error.message || config.errorCopy); }).finally(function () { button.disabled = false; });
  });
}());
