// Comportements communs de l'interface de gestion.
(function () {
    'use strict';

    // Confirmation avant l'envoi des formulaires marqués data-confirm (suppressions, etc.).
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var message = form.getAttribute && form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // Miniatures des messages texte : rendu réduit du vrai message (slide.js).
    var thumbs = document.querySelectorAll('.thumb-slide[data-slide]');
    if (thumbs.length && window.VxSlide) {
        for (var i = 0; i < thumbs.length; i++) {
            try {
                var slide = window.VxSlide.render(JSON.parse(thumbs[i].getAttribute('data-slide')));
                thumbs[i].appendChild(slide);
                window.VxSlide.fit(slide);
            } catch (e) { /* miniature ignorée */ }
        }
    }

    // Envoi automatique des formulaires de filtres marqués data-autosubmit.
    document.addEventListener('change', function (event) {
        var form = event.target.form;
        if (form && form.hasAttribute('data-autosubmit')) {
            form.submit();
        }
    });
})();
