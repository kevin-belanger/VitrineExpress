// Champ image réutilisable (templates/partials/image-field.php) : choisir, glisser-déposer ou coller une image,
// fiche avec miniature, nom, dimensions et poids, format et taille vérifiés dès le choix (mêmes messages que
// le serveur). Le vrai <input type="file"> reste dans le formulaire : c'est lui qui est envoyé.
//
// Chaque champ reçoit une petite API (element.imageField : url(), showError(texte)) et émet l'événement
// « imagechange » (detail.url : image montrée, '' si aucune), par exemple pour un aperçu.
(function () {
    'use strict';

    var TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    function formatSize(bytes) {
        return bytes >= 1048576
            ? (bytes / 1048576).toFixed(1).replace('.', ',') + ' Mo'
            : Math.max(1, Math.round(bytes / 1024)) + ' Ko';
    }

    function setup(field) {
        var input = field.querySelector('input[type="file"]');
        var removeInput = field.querySelector('[data-remove-input]');
        var dropzone = field.querySelector('.dropzone');
        var card = field.querySelector('.image-card');
        var thumb = card.querySelector('.image-card-thumb');
        var undo = card.querySelector('[data-image-undo]');
        var remove = card.querySelector('[data-image-remove]');
        var errorEl = field.querySelector('.image-error');
        var maxBytes = Number(field.getAttribute('data-max-bytes'));
        var maxLabel = field.getAttribute('data-max-label');
        var currentUrl = field.getAttribute('data-current-url') || '';
        var currentLabel = field.getAttribute('data-current-label');
        var base = currentUrl ? 'current' : 'empty'; // état auquel « Annuler » revient
        var accepted = null; // dernier fichier valide, pour annuler un mauvais choix
        var objectUrl = null;
        var shownUrl = '';

        function showError(text) {
            errorEl.textContent = text;
            errorEl.hidden = !text;
        }

        function notify(url) {
            shownUrl = url;
            field.dispatchEvent(new CustomEvent('imagechange', { detail: { url: url } }));
        }

        function showCard(url, name, size) {
            dropzone.hidden = true;
            card.hidden = false;
            card.querySelector('.image-card-name').textContent = name;
            var meta = card.querySelector('.image-card-meta');
            meta.textContent = size ? formatSize(size) : '';
            thumb.onload = function () {
                meta.textContent = thumb.naturalWidth + ' × ' + thumb.naturalHeight + ' px' + (size ? ' · ' + formatSize(size) : '');
            };
            if (thumb.getAttribute('src') !== url) {
                thumb.src = url;
            } else if (thumb.complete && thumb.naturalWidth) {
                thumb.onload();
            }
        }

        // Image enregistrée (« current ») ou aucune (« empty »).
        function showBase() {
            if (base === 'current') {
                showCard(currentUrl, currentLabel);
                notify(currentUrl);
            } else {
                card.hidden = true;
                dropzone.hidden = false;
                notify('');
            }
            undo.hidden = true;
            if (remove) { remove.hidden = base !== 'current'; }
        }

        function useFile(file, fromInput) {
            if (TYPES.indexOf(file.type) === -1) {
                showError('Format non accepté. Utilisez une image JPG, PNG, WebP ou GIF.');
            } else if (file.size > maxBytes) {
                showError('Fichier trop volumineux (' + maxLabel + ' maximum).');
            } else {
                if (!fromInput) {
                    var transfer = new DataTransfer();
                    transfer.items.add(file);
                    input.files = transfer.files;
                }
                accepted = input.files;
                showError('');
                if (objectUrl) { URL.revokeObjectURL(objectUrl); }
                objectUrl = URL.createObjectURL(file);
                showCard(objectUrl, file.name, file.size);
                undo.hidden = false;
                if (remove) { remove.hidden = true; }
                if (removeInput) { removeInput.value = ''; } // la nouvelle image remplace l'ancienne
                notify(objectUrl);
                return;
            }
            // Fichier refusé : on garde le choix précédent.
            if (accepted) { input.files = accepted; } else { input.value = ''; }
        }

        input.addEventListener('change', function () {
            if (input.files && input.files[0]) {
                useFile(input.files[0], true);
            } else if (accepted) {
                input.files = accepted; // fenêtre de choix annulée
            }
        });

        undo.addEventListener('click', function () {
            input.value = '';
            accepted = null;
            showError('');
            if (removeInput) { removeInput.value = base === 'empty' && currentUrl ? '1' : ''; }
            showBase();
        });

        if (remove) {
            remove.addEventListener('click', function () {
                removeInput.value = '1';
                base = 'empty';
                showBase();
            });
        }

        function hasFiles(event) {
            return event.dataTransfer && Array.prototype.indexOf.call(event.dataTransfer.types, 'Files') !== -1;
        }
        field.addEventListener('dragover', function (event) {
            if (hasFiles(event)) {
                event.preventDefault();
                field.classList.add('is-dragover');
            }
        });
        field.addEventListener('dragleave', function (event) {
            if (!field.contains(event.relatedTarget)) {
                field.classList.remove('is-dragover');
            }
        });
        field.addEventListener('drop', function (event) {
            field.classList.remove('is-dragover');
            if (hasFiles(event)) {
                event.preventDefault();
                useFile(event.dataTransfer.files[0], false);
            }
        });

        field.imageField = {
            url: function () { return shownUrl; },
            showError: showError,
            useFile: function (file) { useFile(file, false); }
        };
        showBase(); // une erreur venue du serveur, déjà affichée par le gabarit, reste visible
    }

    var fields = document.querySelectorAll('[data-image-field]');
    for (var i = 0; i < fields.length; i++) {
        setup(fields[i]);
    }

    // Coller une image (Ctrl+V), par exemple une capture d'écran : elle va au champ image visible.
    document.addEventListener('paste', function (event) {
        var files = event.clipboardData ? event.clipboardData.files : [];
        if (!files.length || files[0].type.indexOf('image/') !== 0) {
            return;
        }
        for (var j = 0; j < fields.length; j++) {
            if (fields[j].offsetParent !== null) {
                event.preventDefault();
                fields[j].imageField.useFile(files[0]);
                return;
            }
        }
    });
})();
