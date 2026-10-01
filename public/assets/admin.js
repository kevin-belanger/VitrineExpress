// Comportements communs de l'interface de gestion.
(function () {
    'use strict';

    // Barre du haut : quand le logo, le menu et l'utilisateur ne tiennent plus sur une ligne,
    // le menu se replie derrière le bouton « Menu » (mesuré, pas de largeur d'écran fixe).
    var topbar = document.querySelector('.topbar');
    if (topbar) {
        var menuToggle = topbar.querySelector('.menu-toggle');
        var setMenuOpen = function (open) {
            topbar.classList.toggle('is-open', open);
            menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        var fitMenu = function () {
            // Mesure dans la disposition normale, sur une ligne, sans déclencher les animations du panneau.
            topbar.classList.add('is-measuring');
            topbar.classList.remove('is-compact');
            var compact = topbar.scrollWidth > topbar.clientWidth;
            topbar.classList.toggle('is-compact', compact);
            if (!compact) {
                setMenuOpen(false);
            }
            void topbar.offsetHeight; // applique l'état final avant de réactiver les animations
            topbar.classList.remove('is-measuring');
        };
        topbar.classList.add('has-menu-js');
        menuToggle.addEventListener('click', function () {
            setMenuOpen(!topbar.classList.contains('is-open'));
        });
        document.addEventListener('click', function (event) {
            if (topbar.classList.contains('is-open') && !topbar.contains(event.target)) {
                setMenuOpen(false);
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && topbar.classList.contains('is-open')) {
                setMenuOpen(false);
                menuToggle.focus();
            }
        });
        window.addEventListener('resize', fitMenu);
        window.addEventListener('load', fitMenu); // polices et logo chargés
        fitMenu();
    }

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
    // Aussi appelé sur le HTML inséré plus tard (ex. mise à jour en direct des périphériques).
    window.vxRenderThumbs = function (root) {
        var thumbs = root.querySelectorAll('.thumb-slide[data-slide]');
        if (!window.VxSlide) {
            return;
        }
        for (var i = 0; i < thumbs.length; i++) {
            try {
                var slide = window.VxSlide.render(JSON.parse(thumbs[i].getAttribute('data-slide')));
                thumbs[i].appendChild(slide);
                window.VxSlide.fit(slide);
            } catch (e) { /* miniature ignorée */ }
        }
    };
    window.vxRenderThumbs(document);

    // Parties de formulaire repliées derrière un bouton : <button data-reveal="id"> affiche l'élément id,
    // un bouton [data-conceal] à l'intérieur le referme et vide ses champs (rien n'est alors modifié).
    // L'élément reste ouvert au chargement s'il porte data-open (ex. une erreur à montrer).
    var reveals = document.querySelectorAll('[data-reveal]');
    Array.prototype.forEach.call(reveals, function (button) {
        var target = document.getElementById(button.getAttribute('data-reveal'));
        if (!target) {
            return;
        }
        function setOpen(open, byUser) {
            target.hidden = !open;
            button.hidden = open;
            if (!open) {
                Array.prototype.forEach.call(target.querySelectorAll('input'), function (input) { input.value = ''; });
            }
            if (byUser) {
                (open ? target.querySelector('input') : button).focus();
            }
        }
        button.addEventListener('click', function () { setOpen(true, true); });
        var cancel = target.querySelector('[data-conceal]');
        if (cancel) {
            cancel.addEventListener('click', function () { setOpen(false, true); });
        }
        setOpen(target.hasAttribute('data-open'), false);
    });

    // Envoi automatique des formulaires de filtres marqués data-autosubmit.
    document.addEventListener('change', function (event) {
        var form = event.target.form;
        if (form && form.hasAttribute('data-autosubmit')) {
            form.submit();
        }
    });
})();
