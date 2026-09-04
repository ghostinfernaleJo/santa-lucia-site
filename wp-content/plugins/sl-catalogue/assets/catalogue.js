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
  var selectedCategory = '';
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
  function loadProducts() {
    if (!selectedAgency()) { empty(config.emptyCopy); return; }
    empty('Chargement des produits…');
    var body = new URLSearchParams({agency: selectedAgency(), category: selectedCategory, search: search.value.trim()});
    fetch(endpoint('products'), {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body.toString()})
      .then(function (res) { return res.json(); })
      .then(function (res) {
        if (!res.success) throw new Error();
        if (res.data.empty) { empty('Aucun produit disponible pour cette recherche dans cette agence.'); return; }
        results.innerHTML = '<div class="slcat__product-grid">' + res.data.html + '</div>';
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
    reset.hidden = true;
    loadProducts();
  }
  function setCategory(value, activeButton) {
    selectedCategory = value || '';
    root.querySelectorAll('.slcat__category, .slcat__all-cats').forEach(function (button) {
      button.classList.toggle('is-active', button === activeButton);
    });
    reset.hidden = !selectedCategory;
    loadProducts();
  }
  var saved = localStorage.getItem('sl_catalogue_agency');
  if (!agency.value && saved && agency.querySelector('option[value="' + CSS.escape(saved) + '"]')) agency.value = saved;
  updateAgencyState();
  // Une agence déjà pré-remplie (panier ou choix précédent) doit charger
  // immédiatement son catalogue, sans obliger le client à la re-sélectionner.
  if (agency.value) loadProducts();
  agency.addEventListener('change', setAgency);
  search.addEventListener('input', function () { window.clearTimeout(timer); timer = window.setTimeout(loadProducts, 280); });
  root.querySelectorAll('.slcat__category').forEach(function (button) {
    button.addEventListener('click', function () {
      if (!selectedAgency()) { agency.focus(); showToast('Choisissez votre agence avant de consulter ce rayon.'); return; }
      setCategory(button.dataset.category || '', button);
      root.querySelector('.slcat__results').scrollIntoView({behavior:'smooth', block:'start'});
    });
  });
  root.querySelector('.slcat__all-cats').addEventListener('click', function () { setCategory('', root.querySelector('.slcat__all-cats')); });
  reset.addEventListener('click', function () { setCategory('', root.querySelector('.slcat__all-cats')); });
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
