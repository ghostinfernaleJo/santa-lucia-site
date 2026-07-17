/**
 * Service worker — notifications push Bons Plans (Santa Lucia).
 *
 * Architecture « push sans contenu » : le serveur envoie un signal vide
 * (pas de payload chiffre), et ce worker va chercher le message du moment
 * sur un endpoint public. Avantage : aucune cryptographie de payload cote
 * serveur (aes128gcm), seul le signal VAPID est signe.
 */
'use strict';

var AJAX = '/wp-admin/admin-ajax.php';
var ICON = '/wp-content/uploads/2024/06/logo-santa-1.png';

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
                    tag: d.tag || 'slbp', // les envois rapproches se remplacent au lieu de s'empiler
                    data: { url: d.url || '/bon-plans/' }
                });
            })
            .catch(function () {
                // Endpoint injoignable : notification generique plutot que rien
                // (le navigateur exige d'afficher quelque chose apres un push).
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
