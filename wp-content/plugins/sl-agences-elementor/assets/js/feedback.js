(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('slf-form');
    if (!form || typeof SLF === 'undefined') return;

    var result = document.getElementById('slf-result');
    var btn = form.querySelector('.slf-btn');

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      result.className = 'slf-feedback';
      result.textContent = '';
      btn.disabled = true;
      var oldLabel = btn.textContent;
      btn.textContent = 'Envoi…';

      var data = new FormData(form);
      data.append('action', 'slf_submit');
      data.append('nonce', SLF.nonce);

      fetch(SLF.ajax, { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
          if (res.j && res.j.success) {
            var msg = (res.j.data && res.j.data.message) || 'Merci, votre message a été envoyé.';
            form.reset();
            // Réinitialiser les chips/étoiles
            form.querySelectorAll('input[type=radio]').forEach(function (i) { i.checked = false; });
            result.className = 'slf-feedback slf-ok';
            result.textContent = msg;
            result.scrollIntoView({ behavior: 'smooth', block: 'center' });
          } else {
            result.className = 'slf-feedback slf-err';
            result.textContent = (res.j && res.j.data && res.j.data.message) || 'Une erreur est survenue, réessayez.';
          }
        })
        .catch(function () {
          result.className = 'slf-feedback slf-err';
          result.textContent = 'Erreur réseau, veuillez réessayer.';
        })
        .then(function () { btn.disabled = false; btn.textContent = oldLabel; });
    });
  });
})();
