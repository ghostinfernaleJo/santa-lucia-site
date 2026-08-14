/**
 * Correctif de compatibilité pour les anciennes versions mises en cache de
 * bons-plans-v3y.js qui génèrent encore tous les numéros de pagination.
 */
(function () {
    'use strict';

    function compactPagination(pagination) {
        var links = Array.from(pagination.querySelectorAll('a'));
        var numbered = links.map(function (link) {
            var label = (link.textContent || '').trim();
            return /^\d+$/.test(label) ? { link: link, page: parseInt(label, 10) } : null;
        }).filter(Boolean);

        pagination.querySelectorAll('.slbp-compact-dots').forEach(function (dots) {
            dots.remove();
        });

        if (numbered.length <= 7) {
            numbered.forEach(function (item) {
                item.link.hidden = false;
                item.link.removeAttribute('aria-hidden');
            });
            return;
        }

        var active = numbered.find(function (item) {
            return item.link.classList.contains('active');
        });
        var current = active ? active.page : numbered[0].page;
        var last = numbered[numbered.length - 1].page;
        var keep = {};

        [1, last, current - 1, current, current + 1].forEach(function (page) {
            if (page >= 1 && page <= last) keep[page] = true;
        });

        numbered.forEach(function (item) {
            item.link.hidden = !keep[item.page];
            if (item.link.hidden) item.link.setAttribute('aria-hidden', 'true');
            else item.link.removeAttribute('aria-hidden');
        });

        var visible = numbered.filter(function (item) { return keep[item.page]; });
        for (var i = 1; i < visible.length; i++) {
            if (visible[i].page - visible[i - 1].page <= 1) continue;

            var dots = document.createElement('span');
            dots.className = 'dots slbp-compact-dots';
            dots.textContent = '…';
            dots.setAttribute('aria-hidden', 'true');
            dots.style.display = 'inline-flex';
            dots.style.alignItems = 'center';
            dots.style.justifyContent = 'center';
            dots.style.minWidth = '20px';
            dots.style.height = '34px';
            dots.style.color = '#777';
            pagination.insertBefore(dots, visible[i].link);
        }
    }

    function watchPagination(pagination) {
        if (pagination.dataset.slbpCompactWatch === '1') return;
        pagination.dataset.slbpCompactWatch = '1';

        var observer = new MutationObserver(function () {
            observer.disconnect();
            compactPagination(pagination);
            observer.observe(pagination, { childList: true });
        });

        compactPagination(pagination);
        observer.observe(pagination, { childList: true });
    }

    function init() {
        document.querySelectorAll('.slbp-pagination').forEach(watchPagination);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

    window.addEventListener('load', init);
})();
