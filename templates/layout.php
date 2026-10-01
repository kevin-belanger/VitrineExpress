<?php
/** @var string $content */
$title = $title ?? '';
$current = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$nav = [
    '/admin' => 'Tableau de bord',
    '/admin/messages' => 'Messages',
    '/admin/devices' => 'Périphériques d’affichage',
    '/admin/groups' => 'Groupes',
    '/admin/users' => 'Utilisateurs',
    '/admin/settings' => 'Paramètres',
];
// Seulement les pages accessibles au compte connecté (un gestionnaire n'a pas Groupes, Utilisateurs, Paramètres).
if (!empty($user)) {
    $navAccess = new \VitrineExpress\Access($app, $user);
    $nav = array_filter($nav, static fn (string $path): bool => $navAccess->canVisit($path), ARRAY_FILTER_USE_KEY);
}
$isActive = static function (string $path) use ($current): bool {
    $full = url($path);
    return $path === '/admin' ? rtrim($current, '/') === $full : str_starts_with($current, $full);
};
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title !== '' ? $title . ' · VitrineExpress' : 'VitrineExpress') ?></title>
    <?php if (!empty($refresh)): ?>
        <meta http-equiv="refresh" content="<?= (int) $refresh ?>">
    <?php endif; ?>
    <?= \VitrineExpress\View::render('partials/icons', [], null) ?>
    <link rel="stylesheet" href="<?= e(asset('/assets/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/slide.css')) ?>">
    <?php foreach ($styles ?? [] as $href): ?>
        <link rel="stylesheet" href="<?= e($href) ?>">
    <?php endforeach; ?>
</head>
<body class="<?= !empty($user) ? 'has-nav' : 'no-nav' ?>">
<?php if (!empty($user)): ?>
    <header class="topbar">
        <a class="brand" href="<?= e(url('/admin')) ?>">
            <img src="<?= e(asset('/assets/brand/logo-light.png')) ?>" alt="VitrineExpress" width="218" height="32">
        </a>
        <?php // Quand tout ne tient plus sur une ligne, le menu se replie derrière ce bouton (admin.js). ?>
        <button type="button" class="menu-toggle" aria-expanded="false" aria-controls="topnav">
            <svg class="icon-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            <svg class="icon-close" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            Menu
        </button>
        <div class="topnav" id="topnav">
            <div class="topnav-inner">
                <nav class="mainnav" aria-label="Navigation principale">
                    <?php foreach ($nav as $path => $label): ?>
                        <a href="<?= e(url($path)) ?>"<?= $isActive($path) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                    <?php endforeach; ?>
                </nav>
                <div class="usermenu">
                    <a href="<?= e(url('/admin/account')) ?>" title="Changer mon mot de passe"><?= e($user['display_name'] !== '' ? $user['display_name'] : $user['username']) ?></a>
                    <form method="post" action="<?= e(url('/logout')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="link">Déconnexion</button>
                    </form>
                </div>
            </div>
        </div>
    </header>
<?php endif; ?>
<main class="page">
    <?php foreach ($flashes ?? [] as $flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
<script src="<?= e(asset('/assets/slide.js')) ?>"></script>
<script src="<?= e(asset('/assets/admin.js')) ?>"></script>
<script src="<?= e(asset('/assets/picker.js')) ?>"></script>
<script src="<?= e(asset('/assets/image-field.js')) ?>"></script>
<?php foreach ($scripts ?? [] as $src): ?>
    <script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
