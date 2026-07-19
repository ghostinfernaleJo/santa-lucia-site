/**
 * Service worker UNIQUE du site (racine, portee /) — PWA + notifications push.
 *
 * Remplace l'ancien sw-push.js (portee limitee au dossier du plugin) : un seul
 * worker racine couvre l'installation PWA ET le push, y compris le push iOS
 * en application installee.
 *
 * Strategie reseau VOLONTAIREMENT minimale : passage direct, aucune mise en
 * cache des pages ni des assets. Ce site a deja deux couches de cache
 * (Varnish, LiteSpeed) qui ont cause assez de bugs de fraicheur — un 3e cache
 * navigateur sur un site marchand (panier, stock, paiement) serait un piege.
 * Seule la page hors-ligne est pre-cachee.
 */
'use strict';

var OFFLINE_CACHE = 'slpwa-v1';
var OFFLINE_URL   = '/offline.html';
var AJAX          = '/wp-admin/admin-ajax.php';
var ICON          = '/wp-content/uploads/slpwa/icon-192.png';

self.addEventListener('install', function (e) {
    e.waitUntil(
        caches.open(OFFLINE_CACHE)
            .then(function (c) { return c.add(OFFLINE_URL); })
            .then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (e) {
    e.waitUntil(self.clients.claim());
});

// Navigations uniquement : reseau d'abord, page hors-ligne en secours.
// Tout le reste (assets, AJAX, API) passe par le reseau sans interception.
self.addEventListener('fetch', function (e) {
    if (e.request.mode === 'navigate') {
        e.respondWith(
            fetch(e.request).catch(function () {
                return caches.match(OFFLINE_URL);
            })
        );
    }
});

/* ---- Push « sans contenu » : le serveur envoie un signal vide signe VAPID,
   le worker va chercher le message du moment (meme logique que sw-push.js). */
self.addEventListener('push', function (e) {
    e.waitUntil(
        fetch(AJAX + '?action=sl_push_latest', { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var d = (res && res.data) || {};
                return self.registration.showNotification(d.title || 'Bons plans Santa Lucia', {
                    body: d.body || 'De nouvelles offres vous attendent.',
                    icon: ICON,
                    badge: ICON,
                    tag: d.tag || 'slbp',
                    data: { url: d.url || '/bon-plans/' }
                });
            })
            .catch(function () {
                return self.registration.showNotification('Bons plans Santa Lucia', {
                    body: 'De nouvelles offres vous attendent.',
                    icon: ICON,
                    data: { url: '/bon-plans/' }
                });
            })
    );
});

self.addEventListener('notificationclick', function (e) {
    e.notification.close();
    var url = (e.notification.data && e.notification.data.url) || '/bon-plans/';
    e.waitUntil(clients.openWindow(url));
});
