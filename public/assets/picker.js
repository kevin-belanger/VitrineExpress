// Sélecteurs à choix multiples (templates/partials/picker.php) : recherche, compteur, tout cocher/décocher,
// périphériques inclus par les groupes cochés et résumé des périphériques touchés.
(function () {
    'use strict';

    var SEARCH_FROM = 7; // la recherche n'apparaît qu'à partir de ce nombre d'éléments

    function plural(n, singular, pluralWord) {
        return n + ' ' + (n > 1 ? pluralWord : singular);
    }

    function setup(picker) {
        var rows = Array.prototype.slice.call(picker.querySelectorAll('.picker-list li:not(.picker-section)'));
        var headers = Array.prototype.slice.call(picker.querySelectorAll('.picker-list .picker-section'));
        var boxOf = function (row) { return row.querySelector('input[type="checkbox"]'); };
        var inSection = function (key) {
            return rows.filter(function (row) { return row.getAttribute('data-section') === key; }).map(boxOf);
        };
        var boxes = rows.map(boxOf);
        var groupBoxes = inSection('groups');
        var deviceBoxes = inSection('devices');
        var search = picker.querySelector('.picker-search');
        var count = picker.querySelector('.picker-count');
        var summary = picker.querySelector('.picker-summary');
        var noMatch = picker.querySelector('.picker-nomatch');
        var multi = picker.hasAttribute('data-multi');
        var names = picker.hasAttribute('data-device-names') ? JSON.parse(picker.getAttribute('data-device-names')) : null;

        function labelOf(box) {
            return box.closest('.picker-item').querySelector('.picker-label').textContent;
        }

        // Un périphérique inclus par un groupe coché est montré coché et grisé (« inclus par … »).
        // Il est désactivé, donc pas envoyé : seule la sélection directe est enregistrée.
        function syncIncluded() {
            var via = {};
            groupBoxes.forEach(function (box) {
                if (!box.checked) { return; }
                (box.getAttribute('data-devices') || '').split(',').forEach(function (id) {
                    if (id) { (via[id] = via[id] || []).push(labelOf(box)); }
                });
            });
            deviceBoxes.forEach(function (box) {
                var groups = via[box.value];
                var meta = box.closest('.picker-item').querySelector('.picker-meta');
                if (groups) {
                    if (!box.checked || box.dataset.auto) {
                        box.checked = true;
                        box.disabled = true;
                        box.dataset.auto = '1';
                    }
                    meta.textContent = 'inclus par ' + groups.join(', ');
                } else {
                    if (box.dataset.auto) {
                        box.checked = false;
                        box.disabled = false;
                        delete box.dataset.auto;
                    }
                    meta.textContent = meta.getAttribute('data-meta');
                }
            });
        }

        function update() {
            syncIncluded();
            var chosen = 0;
            var devices = {};
            boxes.forEach(function (box) {
                var item = box.closest('.picker-item');
                item.classList.toggle('is-checked', box.checked && !box.dataset.auto);
                item.classList.toggle('is-included', !!box.dataset.auto);
                if (box.checked) {
                    if (!box.dataset.auto) { chosen++; }
                    (box.getAttribute('data-devices') || '').split(',').forEach(function (id) {
                        if (id) { devices[id] = true; }
                    });
                }
            });
            if (count) {
                count.textContent = multi
                    ? plural(chosen, 'élément choisi', 'éléments choisis')
                    : chosen + ' sur ' + boxes.length + ' coché' + (chosen > 1 ? 's' : '');
            }
            if (summary) {
                renderSummary(Object.keys(devices));
            }
        }

        function renderSummary(ids) {
            if (picker.classList.contains('is-all')) {
                var total = Object.keys(names).length;
                summary.textContent = 'Affiché sur tous les périphériques : les ' + total + ' actuels et ceux ajoutés plus tard.';
                summary.className = 'picker-summary is-ok';
                return;
            }
            if (!ids.length) {
                summary.textContent = 'Affiché sur aucun périphérique : le message est gardé comme brouillon.';
                summary.className = 'picker-summary is-warning';
                return;
            }
            var list = ids.map(function (id) { return names[id]; }).filter(Boolean).sort(function (a, b) {
                return a.localeCompare(b, 'fr');
            });
            var shown = list.slice(0, 6).join(', ') + (list.length > 6 ? ' et ' + (list.length - 6) + ' autre(s)' : '');
            summary.textContent = 'Affiché sur ' + plural(list.length, 'périphérique', 'périphériques') + ' : ' + shown + '.';
            summary.className = 'picker-summary is-ok';
        }

        function setAll(value) {
            rows.forEach(function (row) {
                var box = boxOf(row);
                if (!row.hidden && !box.disabled) { box.checked = value; }
            });
            update();
        }

        // Tout cocher / décocher : seulement pour une liste d'un seul type.
        if (!multi && boxes.length >= 2) {
            picker.querySelector('.picker-all').hidden = false;
            picker.querySelector('.picker-none').hidden = false;
            picker.querySelector('.picker-all').addEventListener('click', function () { setAll(true); });
            picker.querySelector('.picker-none').addEventListener('click', function () { setAll(false); });
        }

        if (search && boxes.length >= SEARCH_FROM) {
            search.hidden = false;
            search.addEventListener('input', function () {
                var term = search.value.trim().toLocaleLowerCase('fr');
                var visible = {};
                rows.forEach(function (row) {
                    var match = row.querySelector('.picker-label').textContent.toLocaleLowerCase('fr').indexOf(term) !== -1;
                    row.hidden = !match;
                    if (match) { visible[row.getAttribute('data-section')] = true; }
                });
                headers.forEach(function (header) { header.hidden = !visible[header.getAttribute('data-section')]; });
                noMatch.hidden = Object.keys(visible).length > 0;
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

    // Choix « Tous les périphériques d'affichage » / « Choisir » (formulaire de message).
    // La liste est seulement estompée en mode « tous » : les choix sont gardés si on revient en arrière.
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
