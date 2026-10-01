// Comportements communs de l'interface de gestion.
(function () {
    'use strict';

    // Confirmation avant l'envoi des formulaires marqués data-confirm (suppressions, déconnexions…),
    // dans une fenêtre de l'application plutôt que la boîte du navigateur.
    // Le texte du bouton vient du bouton du formulaire ; il est rouge si ce bouton a la classe « danger ».
    var dialog = document.createElement('dialog');
    dialog.className = 'confirm-dialog';
    dialog.innerHTML = '<p class="confirm-message"></p>'
        + '<div class="confirm-actions">'
        + '<button type="button" class="button" value="cancel">Annuler</button>'
        + '<button type="button" class="button primary" value="ok"></button>'
        + '</div>';
    document.body.appendChild(dialog);
    var pendingForm = null;

    function closeDialog(confirmed) {
        var form = pendingForm;
        pendingForm = null;
        dialog.close();
        if (confirmed && form) {
            form.submit(); // submit() ne redéclenche pas l'événement : pas de boucle
        }
    }

    dialog.addEventListener('click', function (event) {
        if (event.target === dialog) {
            closeDialog(false); // clic sur le fond
        } else if (event.target.value === 'ok' || event.target.value === 'cancel') {
            closeDialog(event.target.value === 'ok');
        }
    });
    dialog.addEventListener('cancel', function () { pendingForm = null; }); // touche Échap

    document.addEventListener('submit', function (event) {
        var form = event.target;
        var message = form.getAttribute && form.getAttribute('data-confirm');
        if (!message) {
            return;
        }
        event.preventDefault();
        var trigger = event.submitter || form.querySelector('button[type="submit"]');
        var ok = dialog.querySelector('[value="ok"]');
        dialog.querySelector('.confirm-message').textContent = message;
        ok.textContent = trigger ? trigger.textContent.trim() : 'Confirmer';
        ok.classList.toggle('danger-solid', !!trigger && trigger.classList.contains('danger'));
        pendingForm = form;
        dialog.showModal();
        dialog.querySelector('[value="cancel"]').focus();
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
