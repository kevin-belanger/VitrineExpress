// Sélecteurs à choix multiples (templates/partials/picker.php) : recherche, tout cocher/décocher,
// compteur et résumé des téléviseurs touchés.
(function () {
    'use strict';

    var SEARCH_FROM = 7; // la recherche n'apparaît qu'à partir de ce nombre d'éléments

    function plural(n, singular, pluralWord) {
        return n + ' ' + (n > 1 ? pluralWord : singular);
    }

    function setup(picker) {
        var boxes = picker.querySelectorAll('.picker-list input[type="checkbox"]');
        var rows = picker.querySelectorAll('.picker-list li');
        var search = picker.querySelector('.picker-search');
        var count = picker.querySelector('.picker-count');
        var summary = picker.querySelector('.picker-summary');
        var noMatch = picker.querySelector('.picker-nomatch');
        var noun = picker.getAttribute('data-noun') || 'éléments';
        var names = picker.hasAttribute('data-device-names') ? JSON.parse(picker.getAttribute('data-device-names')) : null;

        function update() {
            var checked = 0;
            var devices = {};
            for (var i = 0; i < boxes.length; i++) {
                boxes[i].closest('.picker-item').classList.toggle('is-checked', boxes[i].checked);
                if (boxes[i].checked) {
                    checked++;
                    (boxes[i].getAttribute('data-devices') || '').split(',').forEach(function (id) {
                        if (id) { devices[id] = true; }
                    });
                }
            }
            if (count) {
                count.textContent = checked + ' sur ' + boxes.length + ' coché' + (checked > 1 ? 's' : '');
            }
            if (summary) {
                renderSummary(Object.keys(devices));
            }
        }

        function renderSummary(ids) {
            if (picker.classList.contains('is-all')) {
                var total = Object.keys(names).length;
                summary.textContent = 'Affiché sur tous les téléviseurs : les ' + total + ' actuels et ceux ajoutés plus tard.';
                summary.className = 'picker-summary is-ok';
                return;
            }
            if (!ids.length) {
                summary.textContent = 'Affiché sur aucun téléviseur : le message est gardé comme brouillon.';
                summary.className = 'picker-summary is-warning';
                return;
            }
            var list = ids.map(function (id) { return names[id]; }).filter(Boolean).sort(function (a, b) {
                return a.localeCompare(b, 'fr');
            });
            var shown = list.slice(0, 6).join(', ') + (list.length > 6 ? ' et ' + (list.length - 6) + ' autre(s)' : '');
            summary.textContent = 'Affiché sur ' + plural(list.length, 'téléviseur', 'téléviseurs') + ' : ' + shown + '.';
            summary.className = 'picker-summary is-ok';
        }

        function visibleBoxes() {
            var result = [];
            for (var i = 0; i < rows.length; i++) {
                if (!rows[i].hidden) { result.push(boxes[i]); }
            }
            return result;
        }

        function setAll(value) {
            visibleBoxes().forEach(function (box) { if (!box.disabled) { box.checked = value; } });
            update();
        }

        if (boxes.length >= 2) {
            picker.querySelector('.picker-all').hidden = false;
            picker.querySelector('.picker-none').hidden = false;
            picker.querySelector('.picker-all').addEventListener('click', function () { setAll(true); });
            picker.querySelector('.picker-none').addEventListener('click', function () { setAll(false); });
        }

        if (search && boxes.length >= SEARCH_FROM) {
            search.hidden = false;
            search.addEventListener('input', function () {
                var term = search.value.trim().toLocaleLowerCase('fr');
                var any = false;
                for (var i = 0; i < rows.length; i++) {
                    var match = rows[i].textContent.toLocaleLowerCase('fr').indexOf(term) !== -1;
                    rows[i].hidden = !match;
                    any = any || match;
                }
                noMatch.hidden = any;
            });
            // Entrée dans la recherche ne doit pas envoyer le formulaire.
            search.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') { event.preventDefault(); }
            });
        }

        picker.addEventListener('change', update);
        picker.refresh = update;
        update();
    }

    var pickers = document.querySelectorAll('[data-picker]');
    for (var i = 0; i < pickers.length; i++) {
        setup(pickers[i]);
    }

    // Choix « Tous les téléviseurs » / « Certains groupes » (formulaire de message).
    // La liste est seulement estompée en mode « tous » : les groupes cochés sont gardés si on revient en arrière.
    var modes = document.querySelectorAll('input[name="all_devices"][type="radio"]');
    if (modes.length) {
        var target = document.getElementById(modes[0].getAttribute('data-picker-target'));
        var syncMode = function () {
            var checkedMode = document.querySelector('input[name="all_devices"]:checked');
            target.classList.toggle('is-all', !!checkedMode && checkedMode.value === '1');
            if (target.refresh) { target.refresh(); }
        };
        for (var k = 0; k < modes.length; k++) {
            modes[k].addEventListener('change', syncMode);
        }
        syncMode();
    }
})();
