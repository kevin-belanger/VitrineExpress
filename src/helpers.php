<?php

declare(strict_types=1);

// Petites fonctions globales utilisées par les contrôleurs et les gabarits.

/** Échappe une valeur pour l'insérer dans du HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Date et heure courantes au format de stockage. */
function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Préfixe d'URL de l'application (vide si elle est à la racine du domaine). */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $configured = $GLOBALS['vx_base_path'] ?? null;
        $base = is_string($configured)
            ? rtrim($configured, '/')
            : compute_base_path((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    }
    return $base;
}

/**
 * Préfixe d'URL déduit du chemin de index.php :
 * - racine web = public/ : « /index.php » → « » ;
 * - racine web = dossier du projet (hébergement mutualisé) : le .htaccess racine renvoie vers public/,
 *   mais l'adresse publique ne contient pas « /public » : « /public/index.php » → « » ;
 * - installation dans un sous-dossier : « /vitrine/public/index.php » → « /vitrine ».
 * Le serveur intégré de PHP met parfois l'URL demandée dans SCRIPT_NAME : on ne s'y fie que s'il désigne index.php.
 */
function compute_base_path(string $scriptName): string
{
    $script = str_replace('\\', '/', $scriptName);
    if (!str_ends_with($script, '/index.php')) {
        return '';
    }
    $dir = rtrim(dirname($script), '/');
    return str_ends_with($dir, '/public') ? substr($dir, 0, -strlen('/public')) : $dir;
}

/** Chemin sans son premier segment « /public » (« /public/admin » → « /admin »), ou null s'il n'en a pas. */
function without_public_segment(string $path): ?string
{
    if ($path === '/public' || $path === '/public/index.php') {
        return '/';
    }
    return str_starts_with($path, '/public/') ? substr($path, strlen('/public')) : null;
}

/** Vrai si index.php est atteint par le dossier « public » (racine web = dossier du projet). */
function served_through_public_folder(): bool
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    return str_ends_with($script, '/public/index.php');
}

/** URL absolue (depuis la racine du domaine) d'un chemin de l'application. */
function url(string $path = '/'): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** La requête courante est-elle en HTTPS (directement ou derrière un mandataire) ? */
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** URL d'un fichier de public/ avec sa date de modification (force le rechargement après une mise à jour). */
function asset(string $path): string
{
    $file = dirname(__DIR__) . '/public/' . ltrim($path, '/');
    return url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/** Jeton anti-CSRF de la session courante. */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/** Champ caché anti-CSRF à placer dans chaque formulaire POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Ajoute un message à afficher à la prochaine page (success, error, info). */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** Retire et retourne les messages en attente. */
function take_flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

/** Valeur d'un champ du formulaire soumis, nettoyée des espaces. */
function input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Liste d'entiers d'un champ multiple (ex. cases à cocher groups[]). */
function input_ids(string $key): array
{
    $values = $_POST[$key] ?? [];
    if (!is_array($values)) {
        return [];
    }
    $ids = array_map('intval', array_filter($values, 'is_numeric'));
    return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
}

/** Message d'erreur d'un champ de formulaire, prêt à afficher. */
function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<p class="field-error">' . e($errors[$field]) . '</p>' : '';
}

/** Formate une date de stockage pour l'affichage (ex. 30 sept. 2026 14:05). */
function format_datetime(?string $value, bool $withTime = true): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }
    $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $text = (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
    return $withTime ? $text . ' ' . date('H:i', $ts) : $text;
}

/** Durée écoulée lisible (« il y a 2 min »). */
function time_ago(?string $value): string
{
    if ($value === null || $value === '') {
        return 'jamais';
    }
    $seconds = time() - (int) strtotime($value);
    if ($seconds < 60) {
        return 'à l’instant';
    }
    if ($seconds < 3600) {
        return 'il y a ' . intdiv($seconds, 60) . ' min';
    }
    if ($seconds < 86400) {
        return 'il y a ' . intdiv($seconds, 3600) . ' h';
    }
    return 'le ' . format_datetime($value);
}
