(function () {
  'use strict';

  /* Crée la lightbox UNE SEULE FOIS dans le DOM */
  function initLightbox() {
    if (document.getElementById('sl-lb')) return;

    var lb = document.createElement('div');
    lb.id = 'sl-lb';
    lb.className = 'sl-lb-overlay';
    lb.innerHTML =
      '<button class="sl-lb-close" aria-label="Fermer">&#x2715;</button>' +
      '<button class="sl-lb-prev"  aria-label="Précédent">&#x2039;</button>' +
      '<img id="sl-lb-img" src="" alt="">' +
      '<button class="sl-lb-next"  aria-label="Suivant">&#x203A;</button>' +
      '<div class="sl-lb-info" id="sl-lb-info"></div>';
    document.body.appendChild(lb);

    var lbImg  = document.getElementById('sl-lb-img');
    var lbInfo = document.getElementById('sl-lb-info');
    var all    = [];
    var cur    = 0;

    function collectLinks() {
      all = Array.from(document.querySelectorAll('.msn-item')).filter(function (a) {
        return a.tagName === 'A' && a.href;
      });
    }

    function show(i) {
      cur = (i + all.length) % all.length;
      lbImg.style.opacity = '0';
      lbImg.src = all[cur].href;
      lbImg.onload = function () { lbImg.style.opacity = '1'; };
      lbInfo.textContent = (cur + 1) + ' / ' + all.length;
      lb.classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function close() {
      lb.classList.remove('open');
      document.body.style.overflow = '';
    }

    lb.querySelector('.sl-lb-close').onclick = close;
    lb.querySelector('.sl-lb-prev').onclick  = function (e) { e.stopPropagation(); show(cur - 1); };
    lb.querySelector('.sl-lb-next').onclick  = function (e) { e.stopPropagation(); show(cur + 1); };
    lb.onclick = close;
    lbImg.onclick = function (e) { e.stopPropagation(); };

    document.addEventListener('keydown', function (e) {
      if (!lb.classList.contains('open')) return;
      if (e.key === 'Escape')     close();
      if (e.key === 'ArrowLeft')  show(cur - 1);
      if (e.key === 'ArrowRight') show(cur + 1);
    });

    /* Délégation : capte les clics sur .msn-item même ajoutés après init */
    document.addEventListener('click', function (e) {
      var a = e.target.closest('.msn-item');
      if (!a || a.tagName !== 'A') return;
      e.preventDefault();
      e.stopPropagation();
      collectLinks();
      show(all.indexOf(a));
    });
  }

  /* Init au chargement + dans l'éditeur Elementor (preview) */
  function boot() { initLightbox(); }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  /* Ré-init après un rendu Elementor en preview */
  if (window.elementorFrontend) {
    window.elementorFrontend.hooks.addAction('frontend/element_ready/sl_section_espace.any', boot);
  }
})();
