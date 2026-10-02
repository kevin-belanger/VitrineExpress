// Formulaire de message : éditeur Quill, type et arrière-plan, image (via image-field.js),
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

    // ---------- Titre proposé ----------
    // Tant qu'on n'a pas écrit de titre, il reprend le nom de l'image choisie ou la première ligne du texte.

    var titleInput = form.querySelector('[name="title"]');
    var titleIsAuto = titleInput.value === '';
    titleInput.addEventListener('input', function () {
        titleIsAuto = titleInput.value === '';
    });

    function suggestTitle(text) {
        if (titleIsAuto) {
            titleInput.value = text.slice(0, Number(titleInput.getAttribute('maxlength')) || 150);
        }
    }

    function firstLine(text) {
        var lines = text.split('\n');
        for (var i = 0; i < lines.length; i++) {
            var line = lines[i].replace(/\s+/g, ' ').trim();
            if (line) {
                return line;
            }
        }
        return '';
    }

    // « affiche_noel-2026.jpg » → « Affiche noel 2026 » ; rien pour un nom générique (image collée).
    function titleFromFileName(name) {
        var base = name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
        if (!base || /^image( \d+)?$/i.test(base)) {
            return '';
        }
        return base.charAt(0).toUpperCase() + base.slice(1);
    }

    // ---------- Éditeur de texte enrichi ----------

    var editor = document.getElementById('editor');
    var toolbar = document.getElementById('editor-toolbar');
    if (editor && toolbar && window.Quill) {
        // La barre est écrite dans le gabarit (libellés et infobulles en français).
        toolbar.hidden = false;
        quill = new window.Quill(editor, {
            theme: 'snow',
            placeholder: 'Votre message…',
            modules: { toolbar: toolbar }
        });
        var pickerTitles = { 'ql-header': 'Style', 'ql-size': 'Taille', 'ql-align': 'Alignement' };
        Object.keys(pickerTitles).forEach(function (name) {
            var label = toolbar.querySelector('.ql-picker.' + name + ' .ql-picker-label');
            if (label) {
                label.title = pickerTitles[name];
            }
        });
        quill.root.setAttribute('aria-label', 'Texte du message');
        if (htmlField.value) {
            quill.clipboard.dangerouslyPasteHTML(htmlField.value, 'silent');
        }
        quill.on('text-change', function () {
            htmlField.value = quill.getLength() > 1 ? quill.root.innerHTML : '';
            suggestTitle(firstLine(quill.getText()));
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

    // ---------- Image (composant public/assets/image-field.js) ----------

    var imageField = document.getElementById('image-field');
    imageUrl = imageField.imageField.url();
    imageField.addEventListener('imagechange', function (event) {
        imageUrl = event.detail.url;
        var fromName = titleFromFileName(event.detail.name || '');
        if (fromName) {
            suggestTitle(fromName);
        }
        refresh();
    });

    // ---------- Date de fin facultative ----------
    // Pas de fin : la ligne Fin est cachée et le lien « Définir une date de fin » est au bout de la
    // ligne Début. Les champs cachés sont désactivés, donc pas envoyés : le message n'a pas de fin.

    var endFields = document.getElementById('end-fields');
    var endLabel = form.querySelector('.end-label');
    var endAdd = form.querySelector('.end-add');
    var endRemove = endFields.querySelector('.end-remove');
    var endDate = form.querySelector('[name="end_date"]');
    var endTime = form.querySelector('[name="end_time"]');
    var startDate = form.querySelector('[name="start_date"]');

    function setEnd(on, byUser) {
        endAdd.hidden = on;
        endLabel.hidden = endFields.hidden = !on;
        endDate.disabled = endTime.disabled = !on;
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
            imageField.imageField.showError('Choisissez une image.');
            imageField.scrollIntoView({ block: 'center', behavior: 'smooth' });
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
