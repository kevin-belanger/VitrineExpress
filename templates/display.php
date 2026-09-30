<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>VitrineExpress — Affichage</title>
    <link rel="stylesheet" href="<?= e(asset('/assets/slide.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/display.css')) ?>">
</head>
<body data-config="<?= e(json_encode($config, JSON_UNESCAPED_SLASHES)) ?>">

<!-- Diapositives (deux couches pour le fondu) -->
<div id="stage" class="screen"></div>

<!-- Écran de connexion par code -->
<div id="pair" class="screen panel hidden">
    <div class="pair-box">
        <h1 class="pair-brand">Vitrine<span>Express</span></h1>
        <p class="pair-title">Connexion d’un périphérique d’affichage</p>
        <p id="pair-notice" class="pair-notice hidden"></p>
        <div id="pair-digits" class="pair-digits" aria-live="polite">
            <span></span><span></span><span></span><span></span><span></span>
        </div>
        <p id="pair-error" class="pair-error" aria-live="assertive"></p>
        <div id="pad" class="pad">
            <button type="button" data-key="1">1</button><button type="button" data-key="2">2</button><button type="button" data-key="3">3</button>
            <button type="button" data-key="4">4</button><button type="button" data-key="5">5</button><button type="button" data-key="6">6</button>
            <button type="button" data-key="7">7</button><button type="button" data-key="8">8</button><button type="button" data-key="9">9</button>
            <button type="button" data-key="del" class="pad-muted">Effacer</button><button type="button" data-key="0">0</button><button type="button" data-key="ok" class="pad-ok">OK</button>
        </div>
        <p class="pair-help">Entrez le code à 5 chiffres affiché dans l’interface de gestion.</p>
    </div>
</div>

<!-- Confirmation : code déjà utilisé ailleurs -->
<div id="conflict" class="screen panel hidden">
    <div class="pair-box">
        <p class="conflict-text">
            Ce téléviseur (<strong id="conflict-name"></strong>) est déjà connecté sur un autre appareil.<br>
            Voulez-vous le déconnecter et utiliser cet appareil à la place&nbsp;?
        </p>
        <div class="conflict-actions">
            <button type="button" id="conflict-yes" class="pad-ok">Oui, utiliser cet appareil</button>
            <button type="button" id="conflict-no">Annuler</button>
        </div>
    </div>
</div>

<!-- File vide -->
<div id="empty" class="screen panel hidden">
    <div class="empty-box">
        <img id="empty-logo" class="empty-logo hidden" alt="">
        <p id="empty-org" class="empty-org"></p>
        <p id="empty-time" class="empty-time"></p>
        <p id="empty-date" class="empty-date"></p>
    </div>
</div>

<!-- Coupure réseau -->
<div id="offline" class="screen panel hidden">
    <div class="offline-bar">
        <span class="spinner" aria-hidden="true"></span>
        <span>Connexion réseau perdue. Nouvelle tentative en cours…</span>
    </div>
</div>

<!-- Menu caché (apparaît au mouvement de la souris) -->
<div id="menu" class="menu hidden">
    <span id="menu-name" class="menu-name"></span>
    <button type="button" id="menu-fullscreen">Plein écran</button>
    <button type="button" id="menu-logout">Déconnecter cet appareil</button>
</div>

<script src="<?= e(asset('/assets/slide.js')) ?>"></script>
<script src="<?= e(asset('/assets/display.js')) ?>"></script>
</body>
</html>
