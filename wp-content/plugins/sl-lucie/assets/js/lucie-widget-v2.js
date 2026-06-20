(function () {
  'use strict';
  if (typeof slLucie === 'undefined') return;

  var history = [];          // { role, content }
  var open = false;
  var busy = false;

  // Identifiant de session anonyme (pour regrouper une conversation) — localStorage
  var sessionId = '';
  try {
    sessionId = localStorage.getItem('sl_lucie_sid') || '';
    if (!sessionId) {
      sessionId = 'sid-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
      localStorage.setItem('sl_lucie_sid', sessionId);
    }
  } catch (e) { sessionId = 'sid-' + Math.random().toString(36).slice(2, 10); }

  // ---- Construction du DOM ----
  var btn = document.createElement('button');
  btn.className = 'sl-lucie-btn';
  btn.setAttribute('aria-label', 'Discuter avec ' + slLucie.nom);
  btn.innerHTML = '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';

  var panel = document.createElement('div');
  panel.className = 'sl-lucie-panel';
  panel.innerHTML =
    '<div class="sl-lucie-head"><span class="sl-lucie-dot"></span><strong>' + esc(slLucie.nom) + '</strong>' +
    '<button class="sl-lucie-close" aria-label="Fermer">&times;</button></div>' +
    '<div class="sl-lucie-msgs" id="sl-lucie-msgs"></div>' +
    '<form class="sl-lucie-form" id="sl-lucie-form">' +
    '<input type="text" id="sl-lucie-input" autocomplete="off" placeholder="Votre question..." maxlength="2000">' +
    '<button type="submit" aria-label="Envoyer">&#10148;</button></form>';

  document.body.appendChild(btn);
  document.body.appendChild(panel);

  var msgs = panel.querySelector('#sl-lucie-msgs');
  var form = panel.querySelector('#sl-lucie-form');
  var input = panel.querySelector('#sl-lucie-input');

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }
  function scrollDown() { msgs.scrollTop = msgs.scrollHeight; }

  function addMsg(role, text) {
    var d = document.createElement('div');
    d.className = 'sl-lucie-msg sl-lucie-' + role;
    d.innerHTML = esc(text).replace(/\n/g, '<br>');
    msgs.appendChild(d);
    scrollDown();
    return d;
  }

  function toggle(force) {
    open = (typeof force === 'boolean') ? force : !open;
    panel.classList.toggle('is-open', open);
    btn.classList.toggle('is-open', open);
    if (open) {
      if (!msgs.children.length && slLucie.accueil) addMsg('bot', slLucie.accueil);
      setTimeout(function () { input.focus(); }, 50);
    }
  }

  btn.addEventListener('click', function () { toggle(); });
  panel.querySelector('.sl-lucie-close').addEventListener('click', function () { toggle(false); });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (busy) return;
    var q = input.value.trim();
    if (!q) return;
    input.value = '';
    addMsg('user', q);
    history.push({ role: 'user', content: q });

    busy = true;
    var typing = document.createElement('div');
    typing.className = 'sl-lucie-msg sl-lucie-bot sl-lucie-typing';
    typing.innerHTML = '<span></span><span></span><span></span>';
    msgs.appendChild(typing);
    scrollDown();

    fetch(slLucie.rest, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': slLucie.nonce },
      body: JSON.stringify({ message: q, history: history.slice(-8), session_id: sessionId })
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        typing.remove();
        var reply = (res && res.reply) ? res.reply : 'Desole, une erreur est survenue.';
        addMsg('bot', reply);
        history.push({ role: 'assistant', content: reply });
      })
      .catch(function () {
        typing.remove();
        addMsg('bot', 'Desole, je n\'arrive pas a repondre pour le moment. Reessayez dans un instant.');
      })
      .finally(function () { busy = false; input.focus(); });
  });
})();
