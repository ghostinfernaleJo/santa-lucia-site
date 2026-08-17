(function () {
    'use strict';

    function getTrack(root) {
        return root.querySelector('.sl-mdt-track');
    }

    function updateArrows(root) {
        var track = getTrack(root);
        var prev = root.querySelector('.sl-mdt-prev');
        var next = root.querySelector('.sl-mdt-next');
        var empty = !track;

        root.classList.toggle('is-empty', empty);
        if (!track) {
            if (prev) prev.disabled = true;
            if (next) next.disabled = true;
            return;
        }

        var atStart = track.scrollLeft <= 2;
        var atEnd = track.scrollLeft >= track.scrollWidth - track.clientWidth - 2;
        if (prev) prev.disabled = atStart;
        if (next) next.disabled = atEnd;
    }

    function bindTrack(root) {
        var track = getTrack(root);
        if (!track || track.dataset.slMdtBound) {
            updateArrows(root);
            return;
        }
        track.dataset.slMdtBound = '1';
        track.addEventListener('scroll', function () {
            window.requestAnimationFrame(function () { updateArrows(root); });
        }, { passive: true });
        updateArrows(root);
    }

    function changeAgency(root, agency) {
        var stage = root.querySelector('.sl-mdt-stage');
        var status = root.querySelector('.sl-mdt-availability');
        if (!stage || !agency || root.dataset.loading === '1') return;

        root.dataset.loading = '1';
        root.classList.add('is-loading');
        if (status) status.textContent = 'Chargement du menu…';

        var body = new URLSearchParams({
            action: 'sl_mdt_load_menu',
            nonce: root.dataset.nonce || '',
            agence: agency,
            limit: root.dataset.limit || '12',
            show_order_button: root.dataset.showOrderButton || '1'
        });

        fetch(root.dataset.ajaxUrl || (window.slMenuDuJour && window.slMenuDuJour.ajaxUrl) || '', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        })
            .then(function (response) { return response.json(); })
            .then(function (response) {
                if (!response || !response.success || !response.data) {
                    throw new Error('Réponse invalide');
                }
                stage.innerHTML = response.data.html || '';
                var count = parseInt(response.data.count, 10) || 0;
                var agencyName = response.data.agency_name || 'cette agence';
                if (status) {
                    status.textContent = count
                        ? count + (count > 1 ? ' repas disponibles aujourd’hui à ' : ' repas disponible aujourd’hui à ') + agencyName
                        : 'Aucun repas disponible aujourd’hui à ' + agencyName;
                }
                bindTrack(root);
            })
            .catch(function () {
                if (status) status.textContent = 'Le menu ne peut pas être chargé pour le moment.';
            })
            .finally(function () {
                root.dataset.loading = '0';
                root.classList.remove('is-loading');
                updateArrows(root);
            });
    }

    function init(root) {
        if (!root || root.dataset.slMdtInit) return;
        root.dataset.slMdtInit = '1';

        var select = root.querySelector('.sl-mdt-agency-select');
        if (select) {
            select.addEventListener('change', function () {
                changeAgency(root, select.value);
            });
        }

        root.addEventListener('click', function (event) {
            var button = event.target.closest('.sl-mdt-arrow');
            if (!button || button.disabled) return;
            var track = getTrack(root);
            if (!track) return;
            var direction = button.classList.contains('sl-mdt-next') ? 1 : -1;
            track.scrollBy({ left: direction * Math.max(220, track.clientWidth * 0.86), behavior: 'smooth' });
        });

        bindTrack(root);
    }

    function boot(scope) {
        (scope || document).querySelectorAll('.sl-mdt').forEach(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { boot(document); });
    } else {
        boot(document);
    }

    if (window.jQuery) {
        window.jQuery(window).on('elementor/frontend/init', function () {
            if (window.elementorFrontend && window.elementorFrontend.hooks) {
                window.elementorFrontend.hooks.addAction('frontend/element_ready/sl_menu_du_jour_carousel.default', function ($scope) {
                    boot($scope[0]);
                });
            }
        });
    }
}());
