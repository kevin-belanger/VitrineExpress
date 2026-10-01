// Formulaire de message : éditeur Quill, type et arrière-plan, image (choisir, glisser, coller),
// date de fin facultative, aperçu 16:9 et envoi.
(function () {
    'use strict';

    var form = document.getElementById('message-form');
    if (!form) {
        return;
    }

    var preview = document.getElementById('preview');
    var htmlField = form.querySelector('[name="text_html"]');
    var quill = null;
    var current = null;
    var timer = null;
    var imageUrl = ''; // image montrée dans l'aperçu (actuelle ou nouvellement choisie)

    function type() {
        var checked = form.querySelector('[name="type"]:checked') || form.querySelector('input[type="hidden"][name="type"]');
        return checked ? checked.value : 'image';
    }

    // ---------- Aperçu ----------

    function background() {
        var checked = form.querySelector('[name="background_id"]:checked');
        return checked
            ? { css: checked.getAttribute('data-css'), color: checked.getAttribute('data-color') }
            : { css: '#000000', color: '#ffffff' };
    }

    function refresh() {
        var slide;
        if (type() === 'image') {
            if (!imageUrl) {
                preview.innerHTML = '<p class="preview-empty">L’aperçu apparaîtra ici.</p>';
                current = null;
                return;
            }
            slide = { type: 'image', image: imageUrl };
        } else {
            var bg = background();
            slide = { type: 'text', html: htmlField.value, background: bg.css, color: bg.color };
        }
        var el = window.VxSlide.render(slide);
        preview.innerHTML = '';
        preview.appendChild(el);
        current = el;
        window.VxSlide.fit(el);
    }

    function refreshSoon() {
        window.clearTimeout(timer);
        timer = window.setTimeout(refresh, 150);
    }

    function toggleType() {
        var isText = type() === 'text';
        var sections = form.querySelectorAll('[data-for-type]');
        for (var i = 0; i < sections.length; i++) {
            sections[i].classList.toggle('hidden', sections[i].getAttribute('data-for-type') !== (isText ? 'text' : 'image'));
        }
        refresh();
    }

    window.addEventListener('resize', function () {
        if (current) {
            window.VxSlide.fit(current);
        }
    });

    // ---------- Éditeur de texte enrichi ----------

    var editor = document.getElementById('editor');
    if (editor && window.Quill) {
        quill = new window.Quill(editor, {
            theme: 'snow',
            placeholder: 'Votre message…',
            modules: {
                toolbar: [
                    [{ header: [1, 2, 3, false] }],
                    [{ size: ['small', false, 'large', 'huge'] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ align: [] }],
                    ['clean']
                ]
            }
        });
        if (htmlField.value) {
            quill.clipboard.dangerouslyPasteHTML(htmlField.value, 'silent');
        }
        quill.on('text-change', function () {
            htmlField.value = quill.getLength() > 1 ? quill.root.innerHTML : '';
            refreshSoon();
        });
    }

    form.addEventListener('change', function (event) {
        if (event.target.name === 'type') {
            toggleType();
        } else if (event.target.name === 'background_id') {
            refresh();
        }
    });

    // ---------- Image : choisir, glisser-déposer ou coller ----------
    // Le vrai champ de fichier reste dans le formulaire (caché) : c'est lui qui est envoyé.
    // Les vérifications reprennent celles du serveur, pour répondre tout de suite.

    var imageField = document.getElementById('image-field');
    var imageInput = document.getElementById('image');
    var TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    var maxBytes = Number(imageField.getAttribute('data-max-bytes'));
    var maxLabel = imageField.getAttribute('data-max-label');
    var currentUrl = imageField.getAttribute('data-current-url') || '';
    var dropzone = imageField.querySelector('.dropzone');
    var card = imageField.querySelector('.image-card');
    var thumb = card.querySelector('.image-card-thumb');
    var undo = card.querySelector('.image-card-undo');
    var imageError = imageField.querySelector('.image-error');
    var accepted = null; // dernier fichier valide (FileList), pour annuler un mauvais choix
    var objectUrl = null;

    function showImageError(text) {
        imageError.textContent = text;
        imageError.hidden = !text;
    }

    function formatSize(bytes) {
        return bytes >= 1048576
            ? (bytes / 1048576).toFixed(1).replace('.', ',') + ' Mo'
            : Math.max(1, Math.round(bytes / 1024)) + ' Ko';
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

    function useFile(file, fromInput) {
        if (TYPES.indexOf(file.type) === -1) {
            showImageError('Format non accepté. Utilisez une image JPG, PNG, WebP ou GIF.');
        } else if (file.size > maxBytes) {
            showImageError('Fichier trop volumineux (' + maxLabel + ' maximum).');
        } else {
            if (!fromInput) {
                var transfer = new DataTransfer();
                transfer.items.add(file);
                imageInput.files = transfer.files;
            }
            accepted = imageInput.files;
            showImageError('');
            if (objectUrl) { URL.revokeObjectURL(objectUrl); }
            objectUrl = URL.createObjectURL(file);
            imageUrl = objectUrl;
            showCard(objectUrl, file.name, file.size);
            undo.hidden = !currentUrl;
            refresh();
            return;
        }
        // Fichier refusé : on garde le choix précédent.
        if (accepted) { imageInput.files = accepted; } else { imageInput.value = ''; }
    }

    imageInput.addEventListener('change', function () {
        if (imageInput.files && imageInput.files[0]) {
            useFile(imageInput.files[0], true);
        } else if (accepted) {
            imageInput.files = accepted; // fenêtre de choix annulée
        }
    });

    undo.addEventListener('click', function () {
        imageInput.value = '';
        accepted = null;
        imageUrl = currentUrl;
        showImageError('');
        showCard(currentUrl, 'Image actuelle');
        undo.hidden = true;
        refresh();
    });

    function hasFiles(event) {
        return event.dataTransfer && Array.prototype.indexOf.call(event.dataTransfer.types, 'Files') !== -1;
    }
    imageField.addEventListener('dragover', function (event) {
        if (hasFiles(event)) {
            event.preventDefault();
            imageField.classList.add('is-dragover');
        }
    });
    imageField.addEventListener('dragleave', function (event) {
        if (!imageField.contains(event.relatedTarget)) {
            imageField.classList.remove('is-dragover');
        }
    });
    imageField.addEventListener('drop', function (event) {
        imageField.classList.remove('is-dragover');
        if (hasFiles(event)) {
            event.preventDefault();
            useFile(event.dataTransfer.files[0], false);
        }
    });

    // Coller une image (Ctrl+V), par exemple une capture d'écran.
    document.addEventListener('paste', function (event) {
        var items = event.clipboardData ? event.clipboardData.files : [];
        if (type() === 'image' && items.length && items[0].type.indexOf('image/') === 0) {
            event.preventDefault();
            useFile(items[0], false);
        }
    });

    if (currentUrl) {
        imageUrl = currentUrl;
        showCard(currentUrl, 'Image actuelle');
    }

    // ---------- Date de fin facultative ----------
    // Pas de fin : seul le lien « Définir une date de fin » est visible. Les champs cachés sont
    // désactivés, donc pas envoyés : le message n'a pas de fin.

    var endFields = document.getElementById('end-fields');
    var endLabel = form.querySelector('.end-label');
    var endAdd = endFields.querySelector('.end-add');
    var endRemove = endFields.querySelector('.end-remove');
    var endDate = form.querySelector('[name="end_date"]');
    var endTime = form.querySelector('[name="end_time"]');
    var startDate = form.querySelector('[name="start_date"]');

    function setEnd(on, byUser) {
        endAdd.hidden = on;
        endDate.hidden = endTime.hidden = endRemove.hidden = !on;
        endDate.disabled = endTime.disabled = !on;
        endLabel.classList.toggle('is-empty', !on);
        if (on && !endDate.value) {
            endDate.value = startDate.value;
        }
        if (!on) {
            endDate.value = '';
        }
        if (byUser) {
            (on ? endDate : endAdd).focus();
        }
    }
    endAdd.addEventListener('click', function () { setEnd(true, true); });
    endRemove.addEventListener('click', function () { setEnd(false, true); });
    setEnd(endDate.value !== '', false);

    // ---------- Envoi ----------

    var submit = form.querySelector('button[type="submit"]');
    var submitLabel = submit.textContent;

    form.addEventListener('submit', function (event) {
        if (quill) {
            htmlField.value = quill.getLength() > 1 ? quill.root.innerHTML : '';
        }
        if (type() === 'image' && !imageUrl) {
            event.preventDefault();
            showImageError('Choisissez une image.');
            dropzone.scrollIntoView({ block: 'center', behavior: 'smooth' });
            return;
        }
        // Une image peut prendre du temps à envoyer : on le montre et on évite le double envoi.
        submit.disabled = true;
        submit.textContent = 'Enregistrement…';
    });

    // Retour arrière vers une page gardée en cache : bouton de nouveau utilisable.
    window.addEventListener('pageshow', function () {
        submit.disabled = false;
        submit.textContent = submitLabel;
    });

    toggleType();
})();
