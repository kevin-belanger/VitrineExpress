// Formulaire de message : éditeur Quill, choix du type et de l'arrière-plan, cibles, aperçu 16:9.
(function () {
    'use strict';

    var form = document.getElementById('message-form');
    if (!form) {
        return;
    }

    var preview = document.getElementById('preview');
    var htmlField = form.querySelector('[name="text_html"]');
    var imageInput = form.querySelector('[name="image"]');
    var imageUrl = form.getAttribute('data-image-url') || '';
    var quill = null;
    var current = null;
    var timer = null;

    function type() {
        var checked = form.querySelector('[name="type"]:checked') || form.querySelector('input[type="hidden"][name="type"]');
        return checked ? checked.value : 'image';
    }

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
                preview.innerHTML = '<p class="preview-empty">Choisissez une image pour voir l’aperçu.</p>';
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
        if (imageInput) {
            imageInput.required = !isText && !imageUrl;
        }
        refresh();
    }

    // Éditeur de texte enrichi
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
        var target = event.target;
        if (target.name === 'type') {
            toggleType();
        } else if (target.name === 'background_id') {
            refresh();
        } else if (target === imageInput) {
            if (imageInput.files && imageInput.files[0] && window.URL) {
                imageUrl = window.URL.createObjectURL(imageInput.files[0]);
            }
            refresh();
        }
    });

    form.addEventListener('submit', function () {
        if (quill) {
            htmlField.value = quill.getLength() > 1 ? quill.root.innerHTML : '';
        }
    });

    window.addEventListener('resize', function () {
        if (current) {
            window.VxSlide.fit(current);
        }
    });

    toggleType();
})();
