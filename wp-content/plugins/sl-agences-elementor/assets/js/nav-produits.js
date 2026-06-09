/* Nav Produits — highlight actif au scroll */
(function () {
  'use strict';

  function boot() {
    var links = document.querySelectorAll('.slpm-nav-link');
    if (!links.length) return;

    var sections = [];
    links.forEach(function (link) {
      var href = link.getAttribute('href');
      if (!href || href.charAt(0) !== '#') return;
      var el = document.getElementById(href.slice(1));
      if (el) sections.push({ el: el, link: link });
    });

    function onScroll() {
      var scrollY = window.scrollY || window.pageYOffset;
      var active = null;
      sections.forEach(function (s) {
        var top = s.el.getBoundingClientRect().top + scrollY - 80;
        if (scrollY >= top) active = s;
      });
      links.forEach(function (l) { l.classList.remove('active'); });
      if (active) active.link.classList.add('active');
    }

    /* Smooth scroll */
    links.forEach(function (link) {
      link.addEventListener('click', function (e) {
        var href = link.getAttribute('href');
        if (!href || href.charAt(0) !== '#') return;
        var target = document.getElementById(href.slice(1));
        if (!target) return;
        e.preventDefault();
        var nav = document.querySelector('.slpm-nav');
        var offset = nav ? nav.offsetHeight : 0;
        var top = target.getBoundingClientRect().top + window.scrollY - offset - 8;
        window.scrollTo({ top: top, behavior: 'smooth' });
      });
    });

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  /* Elementor editor re-init */
  if (window.elementorFrontend) {
    elementorFrontend.hooks.addAction('frontend/element_ready/sl_nav_produits.any', boot);
  }
})();
