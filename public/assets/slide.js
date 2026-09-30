/*
 * Rendu d'un message (diapositive), partagé par la page d'affichage et l'aperçu de la gestion.
 * JavaScript ES5 seulement : doit fonctionner dans les navigateurs intégrés aux téléviseurs.
 *
 * Un message texte est mis en page sur une scène fixe de 1920 × 1080 px, puis mis à l'échelle :
 * le rendu est identique quelle que soit la taille de l'écran (et dans l'aperçu).
 */
(function (window, document) {
    'use strict';

    var STAGE_W = 1920;
    var STAGE_H = 1080;
    var PADDING = 120;
    var BASE_FONT = 64;
    var MIN_FONT = 20;

    function setTransform(el, value) {
        el.style.webkitTransform = value;
        el.style.msTransform = value;
        el.style.transform = value;
    }

    function firstColor(css) {
        var match = /#[0-9a-fA-F]{3,8}/.exec(css || '');
        return match ? match[0] : '#000000';
    }

    /**
     * Crée l'élément d'une diapositive. onReady(ok) est appelé quand elle peut être montrée
     * (image chargée, ou tout de suite pour un texte).
     */
    function render(slide, onReady) {
        var el = document.createElement('div');
        el.className = 'vx-slide vx-slide-' + slide.type;

        if (slide.type === 'image') {
            var img = document.createElement('img');
            img.className = 'vx-slide-image';
            img.alt = '';
            img.onload = function () {
                fit(el);
                if (onReady) { onReady(true); }
            };
            img.onerror = function () {
                if (onReady) { onReady(false); }
            };
            el.appendChild(img);
            if (slide.image) {
                img.src = slide.image;
            }
            return el;
        }

        // Couleur unie d'abord : reste en place si le navigateur ne comprend pas le dégradé.
        el.style.backgroundColor = firstColor(slide.background);
        el.style.background = slide.background || '#000000';

        var stage = document.createElement('div');
        stage.className = 'vx-stage';
        var cell = document.createElement('div');
        cell.className = 'vx-stage-cell';
        var text = document.createElement('div');
        text.className = 'vx-text';
        text.style.color = slide.color || '#ffffff';
        text.innerHTML = slide.html || '';
        cell.appendChild(text);
        stage.appendChild(cell);
        el.appendChild(stage);

        if (onReady) {
            window.setTimeout(function () { onReady(true); }, 0);
        }
        return el;
    }

    /** Ajuste une diapositive déjà insérée dans la page à la taille de son conteneur. */
    function fit(el) {
        var width = el.clientWidth;
        var height = el.clientHeight;
        if (!width || !height) {
            return;
        }

        var img = el.getElementsByTagName('img')[0];
        if (img) {
            var nw = img.naturalWidth || img.width;
            var nh = img.naturalHeight || img.height;
            if (!nw || !nh) {
                return;
            }
            var ratio = Math.min(width / nw, height / nh);
            var w = Math.round(nw * ratio);
            var h = Math.round(nh * ratio);
            img.style.width = w + 'px';
            img.style.height = h + 'px';
            img.style.left = Math.round((width - w) / 2) + 'px';
            img.style.top = Math.round((height - h) / 2) + 'px';
            return;
        }

        var stage = el.firstChild;
        if (!stage || stage.className !== 'vx-stage') {
            return;
        }
        var scale = Math.min(width / STAGE_W, height / STAGE_H);
        stage.style.left = Math.round((width - STAGE_W * scale) / 2) + 'px';
        stage.style.top = Math.round((height - STAGE_H * scale) / 2) + 'px';
        setTransform(stage, 'scale(' + scale + ')');

        // Réduit la taille du texte tant qu'il déborde de la scène.
        var text = stage.firstChild.firstChild;
        var size = BASE_FONT;
        text.style.fontSize = size + 'px';
        while (size > MIN_FONT && (text.offsetHeight > STAGE_H - 2 * PADDING || text.scrollWidth > STAGE_W - 2 * PADDING)) {
            size -= 2;
            text.style.fontSize = size + 'px';
        }
    }

    window.VxSlide = { render: render, fit: fit };
})(window, document);
