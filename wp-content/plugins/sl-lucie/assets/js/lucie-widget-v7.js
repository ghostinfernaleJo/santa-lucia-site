(function () {
  'use strict';
  if (typeof slLucie === 'undefined') return;

  var history = [];
  var isOpen = false;
  var busy = false;
  var cartLoaded = false;
  var lastFocus = null;
  var sessionId = '';

  try {
    sessionId = localStorage.getItem('sl_lucie_sid') || '';
    if (!sessionId) {
      sessionId = 'sid-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
      localStorage.setItem('sl_lucie_sid', sessionId);
    }
  } catch (e) {
    sessionId = 'sid-' + Math.random().toString(36).slice(2, 10);
  }

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
    });
  }

  function safeUrl(value) {
    var url = String(value || '').trim();
    return /^https?:\/\//i.test(url) ? url : '';
  }

  var chatIcon = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';
  var cartIcon = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1"/><circle cx="19" cy="20" r="1"/><path d="M3 4h2l2.4 10.2a2 2 0 0 0 2 1.5h7.8a2 2 0 0 0 2-1.6L21 7H6"/></svg>';
  var avatarHtml = slLucie.avatar ? '<img src="' + esc(slLucie.avatar) + '" alt="">' : chatIcon;
  var active = slLucie.active !== false && slLucie.active !== '0';

  var fab = document.createElement('div');
  fab.className = 'sl-lucie-fab';

  var tip = document.createElement('button');
  tip.className = 'sl-lucie-tip';
  tip.type = 'button';
  tip.innerHTML = '<span>' + esc(slLucie.tip || "Besoin d'aide ?") + '</span>';

  var button = document.createElement('button');
  button.className = 'sl-lucie-btn';
  button.type = 'button';
  button.setAttribute('aria-label', 'Discuter avec ' + slLucie.nom);
  button.setAttribute('aria-controls', 'sl-lucie-dialog');
  button.setAttribute('aria-expanded', 'false');
  button.innerHTML =
    '<svg class="sl-lucie-ico-chat" viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>' +
    '<svg class="sl-lucie-ico-close" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>';
  fab.appendChild(tip);
  fab.appendChild(button);

  var panel = document.createElement('section');
  panel.className = 'sl-lucie-panel sl-lucie-panel-commerce';
  panel.id = 'sl-lucie-dialog';
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-labelledby', 'sl-lucie-title');
  panel.setAttribute('aria-hidden', 'true');
  panel.innerHTML =
    '<div class="sl-lucie-head">' +
      '<span class="sl-lucie-ava">' + avatarHtml + '</span>' +
      '<div class="sl-lucie-head-txt"><strong id="sl-lucie-title">' + esc(slLucie.nom) + '</strong>' +
        '<span class="sl-lucie-status"><span class="sl-lucie-dot"></span>' + (active ? 'En ligne' : 'Hors ligne') + '</span></div>' +
      '<button type="button" class="sl-lucie-close" aria-label="Fermer la conversation" title="Fermer">' +
        '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>' +
      '</button>' +
    '</div>' +
    '<div class="sl-lucie-quick" aria-label="Suggestions">' +
      '<button type="button" data-prompt="Quel est le menu du jour ?">🍽️ Menu du jour</button>' +
      '<button type="button" data-prompt="Quelles sont les promotions disponibles en ce moment ?">🏷️ Promotions</button>' +
      '<button type="button" data-prompt="Aide-moi à trouver l’agence Santa Lucia la plus adaptée.">📍 Une agence</button>' +
      '<button type="button" data-prompt="Aide-moi à préparer un panier selon mon budget.">✨ Panier budget</button>' +
      '<button type="button" data-prompt="Je veux préparer une liste de courses.">📝 Liste de courses</button>' +
      '<button type="button" data-prompt="Je veux suivre ma commande.">📦 Suivre ma commande</button>' +
    '</div>' +
    '<div class="sl-lucie-msgs" id="sl-lucie-msgs" role="log" aria-live="polite" aria-relevant="additions"></div>' +
    '<div class="sl-lucie-cart" id="sl-lucie-cart" hidden></div>' +
    '<div class="sl-lucie-note" id="sl-lucie-note" role="status" aria-live="polite" hidden></div>' +
    '<form class="sl-lucie-form" id="sl-lucie-form">' +
      '<label class="screen-reader-text" for="sl-lucie-input">Votre message</label>' +
      '<input type="text" id="sl-lucie-input" autocomplete="off" placeholder="Écrivez votre message…" maxlength="2000">' +
      '<button type="submit" aria-label="Envoyer">' +
        '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>' +
      '</button>' +
    '</form>' +
    (slLucie.privacy ? '<div class="sl-lucie-privacy">Ne partagez jamais de code secret. <a href="' + esc(slLucie.privacy) + '" target="_blank" rel="noopener">Confidentialité</a></div>' : '');

  document.body.appendChild(fab);
  document.body.appendChild(panel);

  var messages = panel.querySelector('#sl-lucie-msgs');
  var form = panel.querySelector('#sl-lucie-form');
  var input = panel.querySelector('#sl-lucie-input');
  var submit = form.querySelector('button[type="submit"]');
  var cartBox = panel.querySelector('#sl-lucie-cart');
  var note = panel.querySelector('#sl-lucie-note');
  var quick = panel.querySelector('.sl-lucie-quick');

  if (!active) {
    quick.hidden = true;
    input.disabled = true;
    submit.disabled = true;
    input.placeholder = 'Lucie est actuellement hors ligne';
    panel.classList.add('is-offline');
  }

  function positionFab() {
    var nav = document.querySelector('.klb-mobile-bottom');
    var navHeight = nav && getComputedStyle(nav).display !== 'none' ? Math.round(nav.getBoundingClientRect().height) : 0;
    fab.style.bottom = navHeight > 0 ? (navHeight + 14) + 'px' : '';
  }
  positionFab();
  window.addEventListener('resize', positionFab);
  window.addEventListener('load', positionFab);
  setTimeout(positionFab, 800);

  function inline(text) {
    var links = [];
    var marker = String.fromCharCode(1);
    function stash(html) { links.push(html); return marker + (links.length - 1) + marker; }
    text = text.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, function (_, label, url) {
      return stash('<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + label + '</a>');
    });
    text = text.replace(/(https?:\/\/[^\s<]+)/g, function (url) {
      var trail = '';
      url = url.replace(/[.,;:!?)\]]+$/, function (part) { trail = part; return ''; });
      return stash('<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + url + '</a>') + trail;
    });
    text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    text = text.replace(/~~([^~]+)~~/g, '<del>$1</del>');
    return text.replace(new RegExp(marker + '(\\d+)' + marker, 'g'), function (_, index) { return links[+index]; });
  }

  function renderMarkdown(text) {
    var lines = esc(text).split('\n');
    var html = '';
    var inList = false;
    lines.forEach(function (line) {
      var item = line.match(/^\s*[-*]\s+(.*)$/);
      if (item) {
        if (!inList) { html += '<ul class="sl-lucie-ul">'; inList = true; }
        html += '<li>' + inline(item[1]) + '</li>';
        return;
      }
      if (inList) { html += '</ul>'; inList = false; }
      var heading = line.match(/^\s*#{1,6}\s+(.*)$/);
      if (heading) { html += '<div class="sl-lucie-h">' + inline(heading[1]) + '</div>'; return; }
      if (!line.trim()) { html += '<div class="sl-lucie-gap"></div>'; return; }
      html += '<div>' + inline(line) + '</div>';
    });
    if (inList) html += '</ul>';
    return html;
  }

  function scrollDown() {
    messages.scrollTop = messages.scrollHeight;
  }

  function addMessage(role, text, cards) {
    var row = document.createElement('div');
    row.className = 'sl-lucie-row sl-lucie-row-' + role;
    var bubble = document.createElement('div');
    bubble.className = 'sl-lucie-msg sl-lucie-' + role;
    bubble.innerHTML = role === 'bot' ? renderMarkdown(text) : esc(text).replace(/\n/g, '<br>');
    row.appendChild(bubble);
    messages.appendChild(row);
    if (role === 'bot' && cards && cards.length) renderCards(cards);
    scrollDown();
    return row;
  }

  function renderCards(cards) {
    var row = document.createElement('div');
    row.className = 'sl-lucie-products';
    row.setAttribute('aria-label', 'Produits proposés');
    cards.slice(0, 12).forEach(function (card) {
      var product = document.createElement('article');
      product.className = 'sl-lucie-product';
      var url = safeUrl(card.url);
      var image = safeUrl(card.image);
      product.innerHTML =
        (image ? '<a class="sl-lucie-product-img" href="' + esc(url || image) + '" target="_blank" rel="noopener"><img src="' + esc(image) + '" alt="" loading="lazy"></a>' : '<div class="sl-lucie-product-img sl-lucie-product-placeholder">🛍️</div>') +
        '<div class="sl-lucie-product-body">' +
          (url ? '<a class="sl-lucie-product-name" href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(card.name) + '</a>' : '<strong class="sl-lucie-product-name">' + esc(card.name) + '</strong>') +
          (card.agency ? '<span class="sl-lucie-product-agency">📍 ' + esc(card.agency) + '</span>' : '') +
          '<div class="sl-lucie-product-price">' + (card.regular_price ? '<del>' + esc(card.regular_price) + '</del>' : '') + '<strong>' + esc(card.price || 'Prix à confirmer') + '</strong></div>' +
          (card.addable && slLucie.cartEnabled !== false ? '<button type="button" class="sl-lucie-add" data-product-id="' + parseInt(card.product_id, 10) + '" data-quantity="' + Math.max(1, parseInt(card.recommended_quantity || 1, 10)) + '">' + cartIcon + '<span>Ajouter</span></button>' : '') +
        '</div>';
      row.appendChild(product);
    });
    messages.appendChild(row);
  }

  function showNote(text, error) {
    note.textContent = text;
    note.className = 'sl-lucie-note' + (error ? ' is-error' : '');
    note.hidden = false;
    clearTimeout(showNote.timer);
    showNote.timer = setTimeout(function () { note.hidden = true; }, error ? 7000 : 3500);
  }

  function renderCart(cart) {
    if (!cart || !cart.available || cart.empty) {
      cartBox.hidden = true;
      cartBox.innerHTML = '';
      return;
    }
    var items = (cart.items || []).map(function (item) {
      return '<div class="sl-lucie-cart-line">' +
        '<div><strong>' + esc(item.name) + '</strong><span>' + esc(item.line_total || item.price) + '</span></div>' +
        '<div class="sl-lucie-cart-qty" aria-label="Quantité de ' + esc(item.name) + '">' +
          '<button type="button" data-cart-update="' + esc(item.cart_item_key) + '" data-quantity="' + Math.max(0, parseInt(item.quantity, 10) - 1) + '" aria-label="Retirer une unité">−</button>' +
          '<span>' + parseInt(item.quantity, 10) + '</span>' +
          '<button type="button" data-cart-update="' + esc(item.cart_item_key) + '" data-quantity="' + (parseInt(item.quantity, 10) + 1) + '" aria-label="Ajouter une unité">+</button>' +
          '<button type="button" class="sl-lucie-cart-remove" data-cart-remove="' + esc(item.cart_item_key) + '" aria-label="Supprimer ' + esc(item.name) + '">×</button>' +
        '</div></div>';
    }).join('');
    cartBox.innerHTML =
      '<button type="button" class="sl-lucie-cart-summary" aria-expanded="false">' + cartIcon +
        '<span><strong>' + parseInt(cart.count || 0, 10) + ' article(s)</strong>' + (cart.agency ? '<small>Retrait : ' + esc(cart.agency) + '</small>' : '') + '</span>' +
        '<b>' + esc(cart.total || '') + '</b><span class="sl-lucie-cart-chevron">⌃</span></button>' +
      '<div class="sl-lucie-cart-details" hidden>' + items +
        '<div class="sl-lucie-cart-actions"><button type="button" class="sl-lucie-cart-clear">Vider</button>' +
        '<a href="' + esc(safeUrl(cart.checkout_url)) + '">Valider la commande</a></div></div>';
    cartBox.hidden = false;
  }

  function postJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (response) {
      return response.json().then(function (data) {
        if (!response.ok && !data) throw new Error('Erreur serveur');
        return data;
      });
    });
  }

  function cartAction(action, data, trigger) {
    if (!slLucie.cartRest) return Promise.reject(new Error('Panier indisponible'));
    var payload = data || {};
    payload.action = action;
    if (trigger) trigger.disabled = true;
    return postJson(slLucie.cartRest, payload).then(function (result) {
      if (result && result.cart) {
        renderCart(result.cart);
        if (action === 'view' && result.ok && !result.cart.empty) remindSavedCart();
      }
      showNote(result && result.message ? result.message : (result && result.ok ? 'Panier mis à jour.' : 'Impossible de modifier le panier.'), !(result && result.ok));
      if (window.jQuery && result && result.ok && action !== 'view') window.jQuery(document.body).trigger('wc_fragment_refresh');
      return result;
    }).catch(function () {
      showNote('Le panier ne répond pas. Réessayez dans un instant.', true);
    }).then(function (result) {
      if (trigger) trigger.disabled = false;
      return result;
    });
  }

  function remindSavedCart() {
    var key = 'sl_lucie_cart_reminder_at';
    var now = Date.now();
    var previous = 0;
    try { previous = parseInt(localStorage.getItem(key) || '0', 10); } catch (ignore) {}
    if (previous && now - previous < 86400000) return;
    try { localStorage.setItem(key, String(now)); } catch (ignore) {}
    showNote('Votre panier est toujours disponible. Vous pouvez le reprendre quand vous voulez.', false);
  }

  function typingRow() {
    var row = document.createElement('div');
    row.className = 'sl-lucie-row sl-lucie-row-bot';
    row.innerHTML = '<div class="sl-lucie-msg sl-lucie-bot sl-lucie-typing"><span></span><span></span><span></span></div>';
    messages.appendChild(row);
    scrollDown();
    return row;
  }

  function sendMessage(text) {
    var question = String(text || '').trim();
    if (!question || busy || !active) return;
    var previousHistory = history.slice(-8);
    addMessage('user', question);
    history.push({ role: 'user', content: question });
    input.value = '';
    busy = true;
    submit.disabled = true;
    var typing = typingRow();

    postJson(slLucie.rest, { message: question, history: previousHistory, session_id: sessionId })
      .then(function (response) {
        typing.remove();
        var reply = response && response.reply ? response.reply : 'Désolé, une erreur est survenue.';
        addMessage('bot', reply, response && response.cards ? response.cards : []);
        if (response && response.cart) renderCart(response.cart);
        history.push({ role: 'assistant', content: reply });
      })
      .catch(function () {
        typing.remove();
        addMessage('bot', "Désolé, je n'arrive pas à répondre pour le moment. Réessayez dans un instant.");
      })
      .then(function () {
        busy = false;
        submit.disabled = false;
        input.focus();
      });
  }

  function toggle(force) {
    isOpen = typeof force === 'boolean' ? force : !isOpen;
    panel.classList.toggle('is-open', isOpen);
    button.classList.toggle('is-open', isOpen);
    fab.classList.toggle('is-open', isOpen);
    document.documentElement.classList.toggle('sl-lucie-locked', isOpen);
    panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    button.setAttribute('aria-label', isOpen ? 'Fermer la conversation' : 'Discuter avec ' + slLucie.nom);
    if (isOpen) {
      lastFocus = document.activeElement;
      if (!messages.children.length) addMessage('bot', active ? slLucie.accueil : slLucie.offline);
      if (active && !cartLoaded && slLucie.cartEnabled !== false) {
        cartLoaded = true;
        cartAction('view', {}, null);
      }
      setTimeout(function () { (active ? input : panel.querySelector('.sl-lucie-close')).focus(); }, 60);
    } else if (lastFocus && typeof lastFocus.focus === 'function') {
      lastFocus.focus();
    }
  }

  button.addEventListener('click', function () { toggle(); });
  tip.addEventListener('click', function () { toggle(true); });
  panel.querySelector('.sl-lucie-close').addEventListener('click', function () { toggle(false); });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && isOpen) toggle(false); });

  quick.addEventListener('click', function (event) {
    var chip = event.target.closest('[data-prompt]');
    if (chip) sendMessage(chip.getAttribute('data-prompt'));
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    sendMessage(input.value);
  });

  panel.addEventListener('click', function (event) {
    var add = event.target.closest('.sl-lucie-add');
    if (add) {
      cartAction('add', { product_id: parseInt(add.getAttribute('data-product-id'), 10), quantity: parseInt(add.getAttribute('data-quantity') || '1', 10) }, add)
        .then(function (result) {
          if (result && result.ok) {
            var label = add.querySelector('span');
            if (label) { label.textContent = 'Ajouté'; setTimeout(function () { label.textContent = 'Ajouter'; }, 1800); }
          }
        });
      return;
    }
    var summary = event.target.closest('.sl-lucie-cart-summary');
    if (summary) {
      var details = cartBox.querySelector('.sl-lucie-cart-details');
      var expanded = summary.getAttribute('aria-expanded') === 'true';
      summary.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      details.hidden = expanded;
      return;
    }
    var update = event.target.closest('[data-cart-update]');
    if (update) {
      cartAction('update', { cart_item_key: update.getAttribute('data-cart-update'), quantity: parseInt(update.getAttribute('data-quantity'), 10) }, update);
      return;
    }
    var remove = event.target.closest('[data-cart-remove]');
    if (remove) {
      cartAction('remove', { cart_item_key: remove.getAttribute('data-cart-remove') }, remove);
      return;
    }
    var clear = event.target.closest('.sl-lucie-cart-clear');
    if (clear && window.confirm('Vider entièrement votre panier ?')) cartAction('clear', {}, clear);
  });
})();
