/**
 * Service worker racine dédié aux notifications push.
 *
 * Il ne met aucune page ou ressource en cache et ne fournit pas de PWA.
 * Le fichier doit rester à la racine pour conserver la portée nécessaire aux
 * abonnements Web Push existants.
 */
'use strict';

var AJAX          = '/wp-admin/admin-ajax.php';
var ICON          = '/wp-content/uploads/slpwa/icon-192.png';

self.addEventListener('install', function (e) {
    e.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', function (e) {
    e.waitUntil(self.clients.claim());
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
