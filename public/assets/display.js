/*
 * Page d'affichage des téléviseurs : connexion par code, rotation des messages, file vide,
 * coupure réseau, menu caché. JavaScript ES5 seulement (navigateurs de télé).
 */
(function (window, document) {
    'use strict';

    var config = JSON.parse(document.body.getAttribute('data-config'));
    var TOKEN_KEY = 'vx_device_token';
    var NAME_KEY = 'vx_device_name';
    var RELOAD_AFTER_MS = 24 * 3600 * 1000; // rechargement quotidien : nouvelles versions, mémoire
    var MENU_DELAY_MS = 5000;
    var startedAt = new Date().getTime();

    var state = null;
    var timer = null;
    var heartbeatTimer = null;
    var clockTimer = null;
    var menuTimer = null;
    var currentEl = null;
    var preloaded = [];
    var digits = '';
    var deviceName = load(NAME_KEY) || '';

    // ---------- Utilitaires ----------

    function $(id) { return document.getElementById(id); }

    function hasClass(el, name) { return (' ' + el.className + ' ').indexOf(' ' + name + ' ') !== -1; }
    function addClass(el, name) { if (!hasClass(el, name)) { el.className = (el.className + ' ' + name).replace(/^\s+/, ''); } }
    function removeClass(el, name) { el.className = (' ' + el.className + ' ').replace(' ' + name + ' ', ' ').replace(/^\s+|\s+$/g, ''); }
    function show(el) { removeClass(el, 'hidden'); }
    function hide(el) { addClass(el, 'hidden'); }

    function load(key) {
        try { return window.localStorage.getItem(key); } catch (e) { return null; }
    }
    function store(key, value) {
        try {
            if (value === null) { window.localStorage.removeItem(key); } else { window.localStorage.setItem(key, value); }
        } catch (e) { /* stockage indisponible : le cookie suffit */ }
    }

    function encode(data) {
        var parts = [];
        for (var key in data) {
            if (data.hasOwnProperty(key)) {
                parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
            }
        }
        return parts.join('&');
    }

    /** Requête vers l'API ; callback(status, json). status 0 = réseau injoignable. */
    function request(method, path, data, callback) {
        var xhr = new XMLHttpRequest();
        var url = config.api + path;
        if (method === 'GET') {
            url += (url.indexOf('?') === -1 ? '?' : '&') + '_=' + new Date().getTime();
        }
        var done = false;
        function finish(status, json) {
            if (!done) {
                done = true;
                callback(status, json);
            }
        }
        xhr.open(method, url, true);
        try { xhr.timeout = 15000; } catch (e) { /* ancien navigateur */ }
        var token = load(TOKEN_KEY);
        if (token) {
            xhr.setRequestHeader('X-Device-Token', token);
        }
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) { return; }
            var json = null;
            try { json = JSON.parse(xhr.responseText); } catch (e) { json = null; }
            finish(xhr.status, json);
        };
        xhr.ontimeout = function () { finish(0, null); };
        xhr.onerror = function () { finish(0, null); };
        if (data) {
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send(encode(data));
        } else {
            xhr.send(null);
        }
    }

    function schedule(fn, ms) {
        window.clearTimeout(timer);
        timer = window.setTimeout(fn, ms);
    }

    // ---------- Écrans ----------

    var panels = { pair: $('pair'), conflict: $('conflict'), empty: $('empty'), offline: $('offline') };

    function setScreen(name) {
        state = name;
        for (var key in panels) {
            if (panels.hasOwnProperty(key)) {
                if (key === name) { show(panels[key]); } else { hide(panels[key]); }
            }
        }
        if (name === 'empty') {
            startClock();
        } else {
            window.clearInterval(clockTimer);
        }
        updateMenu();
    }

    // ---------- Rotation ----------

    function tick() {
        if (new Date().getTime() - startedAt > RELOAD_AFTER_MS) {
            window.location.reload();
            return;
        }
        request('GET', '/next', null, function (status, data) {
            if (status === 200 && data) {
                deviceName = data.device.name;
                store(NAME_KEY, deviceName);
                if (!data.current) {
                    showEmpty(data.org);
                    schedule(tick, config.emptyRetry * 1000);
                    return;
                }
                showSlide(data.current, function (ok) {
                    schedule(tick, ok ? data.current.duration * 1000 : 3000);
                });
                if (data.next) {
                    preload(data.next);
                }
                return;
            }
            if (status === 401) {
                // Message « déconnecté » seulement si cet appareil avait un jeton.
                unpaired(!!load(TOKEN_KEY));
                return;
            }
            setScreen('offline');
            schedule(tick, config.offlineRetry * 1000);
        });
    }

    function showSlide(slide, done) {
        var stage = $('stage');
        var el = window.VxSlide.render(slide, function (ok) {
            if (!ok) {
                if (el.parentNode) { el.parentNode.removeChild(el); }
                done(false);
                return;
            }
            setScreen('play');
            window.VxSlide.fit(el);
            void el.offsetWidth; // force le calcul avant la transition
            addClass(el, 'visible');
            var old = currentEl;
            currentEl = el;
            if (old) {
                window.setTimeout(function () {
                    if (old.parentNode) { old.parentNode.removeChild(old); }
                }, 700);
            }
            done(true);
        });
        stage.appendChild(el);
    }

    function preload(slide) {
        if (slide.type === 'image' && slide.image) {
            var img = new Image();
            img.src = slide.image;
            preloaded.push(img);
            if (preloaded.length > 3) { preloaded.shift(); }
        }
    }

    function clearStage() {
        var stage = $('stage');
        while (stage.firstChild) { stage.removeChild(stage.firstChild); }
        currentEl = null;
    }

    // ---------- File vide : logo, heure, date ----------

    var DAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    var MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    function pad2(n) { return (n < 10 ? '0' : '') + n; }

    function updateClock() {
        var d = new Date();
        $('empty-time').innerHTML = pad2(d.getHours()) + ':' + pad2(d.getMinutes());
        $('empty-date').innerHTML = DAYS[d.getDay()] + ' ' + d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear();
    }

    function startClock() {
        window.clearInterval(clockTimer);
        updateClock();
        clockTimer = window.setInterval(updateClock, 1000);
    }

    function showEmpty(org) {
        var logo = $('empty-logo');
        if (org && org.logo) {
            if (logo.getAttribute('src') !== org.logo) { logo.src = org.logo; }
            show(logo);
        } else {
            hide(logo);
        }
        $('empty-org').innerHTML = '';
        $('empty-org').appendChild(document.createTextNode(org && org.name ? org.name : ''));
        setScreen('empty');
    }

    // ---------- Présence ----------

    function startHeartbeat() {
        window.clearInterval(heartbeatTimer);
        heartbeatTimer = window.setInterval(function () {
            request('POST', '/heartbeat', {}, function (status) {
                if (status === 401) { unpaired(true); }
            });
        }, config.heartbeat * 1000);
    }

    function startPlaying() {
        startHeartbeat();
        tick();
    }

    // ---------- Connexion par code ----------

    function unpaired(wasPaired) {
        window.clearTimeout(timer);
        window.clearInterval(heartbeatTimer);
        store(TOKEN_KEY, null);
        clearStage();
        digits = '';
        renderDigits();
        setError('');
        var notice = $('pair-notice');
        if (wasPaired) {
            notice.innerHTML = 'Cet appareil a été déconnecté. Entrez un code pour le reconnecter.';
            show(notice);
        } else {
            hide(notice);
        }
        setScreen('pair');
        focusPad(4);
    }

    function renderDigits() {
        var boxes = $('pair-digits').getElementsByTagName('span');
        for (var i = 0; i < boxes.length; i++) {
            boxes[i].innerHTML = i < digits.length ? digits.charAt(i) : '';
            if (i < digits.length) { addClass(boxes[i], 'filled'); } else { removeClass(boxes[i], 'filled'); }
        }
    }

    function setError(text) {
        $('pair-error').innerHTML = '';
        $('pair-error').appendChild(document.createTextNode(text));
    }

    function pressKey(key) {
        if (state !== 'pair') { return; }
        if (key === 'del') {
            digits = digits.substring(0, digits.length - 1);
        } else if (key === 'ok') {
            submitCode(false);
            return;
        } else if (/^\d$/.test(key) && digits.length < 5) {
            digits += key;
        }
        setError('');
        renderDigits();
        if (digits.length === 5) {
            submitCode(false);
        }
    }

    function submitCode(force) {
        if (digits.length !== 5) {
            setError('Entrez les 5 chiffres du code.');
            return;
        }
        setError('Connexion…');
        request('POST', '/pair', { code: digits, force: force ? '1' : '0' }, function (status, data) {
            if (status === 200 && data && data.token) {
                store(TOKEN_KEY, data.token);
                deviceName = data.device.name;
                store(NAME_KEY, deviceName);
                if (data.heartbeat) { config.heartbeat = data.heartbeat; }
                setError('');
                clearStage();
                startPlaying();
            } else if (status === 409 && data) {
                $('conflict-name').innerHTML = '';
                $('conflict-name').appendChild(document.createTextNode(data.device.name));
                setScreen('conflict');
                $('conflict-no').focus();
            } else if (status === 404) {
                digits = '';
                renderDigits();
                setError('Code inconnu. Vérifiez le code dans l’interface de gestion.');
            } else {
                setError('Serveur injoignable. Vérifiez la connexion réseau et réessayez.');
            }
        });
    }

    var padButtons = $('pad').getElementsByTagName('button');

    function focusPad(index) {
        if (padButtons[index]) {
            try { padButtons[index].focus(); } catch (e) { /* rien */ }
        }
    }

    function padIndex(el) {
        for (var i = 0; i < padButtons.length; i++) {
            if (padButtons[i] === el) { return i; }
        }
        return -1;
    }

    $('pad').onclick = function (event) {
        var target = event.target || event.srcElement;
        var key = target.getAttribute && target.getAttribute('data-key');
        if (key) { pressKey(key); }
    };

    $('conflict-yes').onclick = function () {
        setScreen('pair');
        submitCode(true);
    };
    $('conflict-no').onclick = function () {
        digits = '';
        renderDigits();
        setError('');
        setScreen('pair');
        focusPad(4);
    };

    document.onkeydown = function (event) {
        event = event || window.event;
        var code = event.keyCode || event.which;
        wakeMenu();

        if (state === 'pair') {
            if (code >= 48 && code <= 57) { pressKey(String(code - 48)); return false; }
            if (code >= 96 && code <= 105) { pressKey(String(code - 96)); return false; }
            if (code === 8 || code === 46) { pressKey('del'); return false; }

            // Flèches de la télécommande : déplacement dans le pavé (3 colonnes).
            var index = padIndex(document.activeElement);
            var moves = { 37: -1, 39: 1, 38: -3, 40: 3 };
            if (moves.hasOwnProperty(code)) {
                var target = index === -1 ? 4 : index + moves[code];
                if (target >= 0 && target < padButtons.length) { focusPad(target); }
                return false;
            }
            if (code === 13 && index === -1) { pressKey('ok'); return false; }
        }

        if (state === 'conflict' && (code === 37 || code === 39)) {
            (document.activeElement === $('conflict-yes') ? $('conflict-no') : $('conflict-yes')).focus();
            return false;
        }
        return true;
    };

    // ---------- Menu caché ----------

    var menu = $('menu');

    function updateMenu() {
        var paired = state === 'play' || state === 'empty' || state === 'offline';
        $('menu-name').innerHTML = '';
        $('menu-name').appendChild(document.createTextNode(paired && deviceName ? deviceName : ''));
        if (paired) { show($('menu-logout')); } else { hide($('menu-logout')); }
    }

    function wakeMenu() {
        show(menu);
        removeClass(document.body, 'idle');
        window.clearTimeout(menuTimer);
        menuTimer = window.setTimeout(function () {
            hide(menu);
            addClass(document.body, 'idle');
        }, MENU_DELAY_MS);
    }

    document.onmousemove = wakeMenu;

    $('menu-fullscreen').onclick = function () {
        var root = document.documentElement;
        var fn = root.requestFullscreen || root.webkitRequestFullscreen || root.webkitRequestFullScreen
            || root.mozRequestFullScreen || root.msRequestFullscreen;
        if (fn) {
            try { fn.call(root); } catch (e) { /* refusé */ }
        }
    };

    $('menu-logout').onclick = function () {
        if (!window.confirm('Déconnecter cet appareil ? Il faudra entrer de nouveau un code pour le reconnecter.')) {
            return;
        }
        request('POST', '/logout', {}, function () {
            unpaired(false);
        });
    };

    // ---------- Divers ----------

    window.onresize = function () {
        if (currentEl) { window.VxSlide.fit(currentEl); }
    };

    // Empêche l'écran de se mettre en veille (navigateurs récents seulement).
    function keepAwake() {
        if (navigator.wakeLock && navigator.wakeLock.request) {
            try { navigator.wakeLock.request('screen')['catch'](function () {}); } catch (e) { /* non pris en charge */ }
        }
    }
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { keepAwake(); }
    });
    keepAwake();

    addClass(document.body, 'idle');
    updateMenu();
    startPlaying();
})(window, document);
