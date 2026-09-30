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
        if (is_string($configured)) {
            $base = rtrim($configured, '/');
        } else {
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
            $dir = rtrim(dirname($script), '/');
            $base = $dir === '.' ? '' : $dir;
        }
    }
    return $base;
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
