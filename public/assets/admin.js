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
})();
