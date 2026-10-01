// Page « Périphériques d'affichage » : toutes les 5 secondes, met à jour l'état et la diapositive affichée
// de chaque périphérique, sans recharger la page. Quand la diapositive change, la cellule bascule en fondu.
(function () {
    'use strict';

    var table = document.querySelector('[data-live-url]');
    if (!table) {
        return;
    }
    var url = table.getAttribute('data-live-url');
    var INTERVAL_MS = 5000;
    var FADE_MS = 250; // durée du fondu, comme .live-slot dans admin.css
    var lastStatus = {};
    var timer = null;

    function swapCurrent(cell, html, key) {
        var slot = cell.querySelector('.live-slot');
        cell.setAttribute('data-key', key);
        slot.classList.add('is-fading');
        window.setTimeout(function () {
            slot.innerHTML = html;
            if (window.vxRenderThumbs) {
                window.vxRenderThumbs(slot);
            }
            slot.classList.remove('is-fading');
        }, FADE_MS);
    }

    function apply(data) {
        Object.keys(data.devices).forEach(function (id) {
            var row = table.querySelector('tr[data-device-id="' + id + '"]');
            if (!row) {
                return; // périphérique ajouté entre-temps : il apparaîtra au prochain chargement de la page
            }
            var device = data.devices[id];
            if (lastStatus[id] !== device.status) {
                row.querySelector('.live-status').innerHTML = device.status;
                lastStatus[id] = device.status;
            }
            var current = row.querySelector('.live-current');
            if (current.getAttribute('data-key') !== device.key) {
                swapCurrent(current, device.current, device.key);
            }
        });
    }

    function schedule() {
        window.clearTimeout(timer);
        timer = window.setTimeout(poll, INTERVAL_MS);
    }

    function poll() {
        if (document.hidden) {
            schedule(); // onglet en arrière-plan : on attend qu'il redevienne visible
            return;
        }
        fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
            .then(function (response) {
                var type = response.headers.get('Content-Type') || '';
                if (response.redirected || type.indexOf('application/json') === -1) {
                    window.location.reload(); // session expirée : la page mènera à la connexion
                    throw new Error('session');
                }
                return response.json();
            })
            .then(apply)
            .catch(function () { /* réseau indisponible : on réessaie au prochain tour */ })
            .then(schedule);
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            poll();
        }
    });
    schedule();
})();
