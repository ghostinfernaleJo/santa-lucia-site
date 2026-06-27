(function () {
  'use strict';
  if (typeof slLucie === 'undefined') return;

  var history = [];          // { role, content }
  var open = false;
  var busy = false;

  // Identifiant de session anonyme (persiste >= 1 mois via localStorage)
  var sessionId = '';
  try {
    sessionId = localStorage.getItem('sl_lucie_sid') || '';
    if (!sessionId) {
      sessionId = 'sid-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
      localStorage.setItem('sl_lucie_sid', sessionId);
    }
  } catch (e) { sessionId = 'sid-' + Math.random().toString(36).slice(2, 10); }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  var chatIcon = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';
  var avatarHtml = slLucie.avatar ? '<img src="' + esc(slLucie.avatar) + '" alt="">' : chatIcon;
  var tipText = slLucie.tip ? esc(slLucie.tip) : "Besoin d'aide ?";

  // ---- Conteneur flottant : etiquette + bulle ----
  var fab = document.createElement('div');
  fab.className = 'sl-lucie-fab';

  var tip = document.createElement('button');
  tip.className = 'sl-lucie-tip';
  tip.type = 'button';
  tip.innerHTML = '<span>' + tipText + '</span>';

  var btn = document.createElement('button');
  btn.className = 'sl-lucie-btn';
  btn.setAttribute('aria-label', 'Discuter avec ' + slLucie.nom);
  btn.innerHTML =
    '<svg class="sl-lucie-ico-chat" viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>' +
    '<svg class="sl-lucie-ico-close" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>';

  fab.appendChild(tip);
  fab.appendChild(btn);

  // ---- Panneau ----
  var panel = document.createElement('div');
  panel.className = 'sl-lucie-panel';
  panel.innerHTML =
    '<div class="sl-lucie-head">' +
      '<span class="sl-lucie-ava">' + avatarHtml + '</span>' +
      '<div class="sl-lucie-head-txt">' +
        '<strong>' + esc(slLucie.nom) + '</strong>' +
        '<span class="sl-lucie-status"><span class="sl-lucie-dot"></span>En ligne</span>' +
      '</div>' +
      '<button class="sl-lucie-close" aria-label="Fermer la conversation" title="Fermer">' +
        '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>' +
      '</button>' +
    '</div>' +
    '<div class="sl-lucie-msgs" id="sl-lucie-msgs"></div>' +
    '<form class="sl-lucie-form" id="sl-lucie-form">' +
      '<input type="text" id="sl-lucie-input" autocomplete="off" placeholder="Écrivez votre message…" maxlength="2000">' +
      '<button type="submit" aria-label="Envoyer">' +
        '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>' +
      '</button>' +
    '</form>';

  document.body.appendChild(fab);
  document.body.appendChild(panel);

  var msgs = panel.querySelector('#sl-lucie-msgs');
  var form = panel.querySelector('#sl-lucie-form');
  var input = panel.querySelector('#sl-lucie-input');

  // ---- Positionne le conteneur AU-DESSUS de la barre de navigation mobile ----
  function positionFab() {
    var nav = document.querySelector('.klb-mobile-bottom');
    var navH = (nav && getComputedStyle(nav).display !== 'none') ? Math.round(nav.getBoundingClientRect().height) : 0;
    fab.style.bottom = navH > 0 ? (navH + 14) + 'px' : '';
  }
  positionFab();
  window.addEventListener('resize', positionFab);
  window.addEventListener('load', positionFab);
  setTimeout(positionFab, 800);

  // Mise en forme inline : liens cliquables, gras, prix barre.
  function inline(s) {
    s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, function (m, t, u) {
      return '<a href="' + u + '" target="_blank" rel="noopener noreferrer">' + t + '</a>';
    });
    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/~~([^~]+)~~/g, '<del>$1</del>');
    return s;
  }

  // Rendu Markdown leger (sur du texte deja echappe).
  function renderMarkdown(text) {
    var lines = esc(text).split('\n');
    var html = '', inList = false;
    for (var i = 0; i < lines.length; i++) {
      var ln = lines[i];
      var li = ln.match(/^\s*[-*]\s+(.*)$/);
      if (li) {
        if (!inList) { html += '<ul class="sl-lucie-ul">'; inList = true; }
        html += '<li>' + inline(li[1]) + '</li>';
        continue;
      }
      if (inList) { html += '</ul>'; inList = false; }
      var h = ln.match(/^\s*#{1,6}\s+(.*)$/);
      if (h) { html += '<div class="sl-lucie-h">' + inline(h[1]) + '</div>'; continue; }
      if (ln.trim() === '') { html += '<div class="sl-lucie-gap"></div>'; continue; }
      html += '<div>' + inline(ln) + '</div>';
    }
    if (inList) html += '</ul>';
    return html;
  }

  function scrollDown() { msgs.scrollTop = msgs.scrollHeight; }

  function addMsg(role, text) {
    var row = document.createElement('div');
    row.className = 'sl-lucie-row sl-lucie-row-' + role;
    var d = document.createElement('div');
    d.className = 'sl-lucie-msg sl-lucie-' + role;
    if (role === 'bot') d.innerHTML = renderMarkdown(text);
    else d.innerHTML = esc(text).replace(/\n/g, '<br>');
    row.appendChild(d);
    msgs.appendChild(row);
    scrollDown();
    return row;
  }

  function toggle(force) {
    open = (typeof force === 'boolean') ? force : !open;
    panel.classList.toggle('is-open', open);
    btn.classList.toggle('is-open', open);
    fab.classList.toggle('is-open', open);   // masque l'etiquette quand le chat est ouvert
    document.documentElement.classList.toggle('sl-lucie-locked', open);
    if (open) {
      if (!msgs.children.length && slLucie.accueil) addMsg('bot', slLucie.accueil);
      setTimeout(function () { input.focus(); }, 60);
    }
  }

  btn.addEventListener('click', function () { toggle(); });
  tip.addEventListener('click', function () { toggle(true); });
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
    var typingRow = document.createElement('div');
    typingRow.className = 'sl-lucie-row sl-lucie-row-bot';
    typingRow.innerHTML = '<div class="sl-lucie-msg sl-lucie-bot sl-lucie-typing"><span></span><span></span><span></span></div>';
    msgs.appendChild(typingRow);
    scrollDown();

    fetch(slLucie.rest, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': slLucie.nonce },
      body: JSON.stringify({ message: q, history: history.slice(-8), session_id: sessionId })
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        typingRow.remove();
        var reply = (res && res.reply) ? res.reply : 'Désolé, une erreur est survenue.';
        addMsg('bot', reply);
        history.push({ role: 'assistant', content: reply });
      })
      .catch(function () {
        typingRow.remove();
        addMsg('bot', "Désolé, je n'arrive pas à répondre pour le moment. Réessayez dans un instant.");
      })
      .finally(function () { busy = false; input.focus(); });
  });
})();
