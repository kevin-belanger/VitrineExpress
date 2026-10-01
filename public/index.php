<?php

declare(strict_types=1);

// Serveur de développement de PHP : laisser passer les fichiers statiques.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __FILE__ && is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/src/bootstrap.php';

$config = VitrineExpress\load_config();
$GLOBALS['vx_base_path'] = $config['base_path'];

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$base = base_path();
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = '/' . trim($path, '/');
if ($path === '/index.php') {
    $path = '/';
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: SAMEORIGIN');
// Aucun script en ligne ni externe ; styles en ligne permis (éditeur, arrière-plans).
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");

try {
    $app = VitrineExpress\App::fromConfig($config);
} catch (Throwable $e) {
    // Typiquement : dossier storage/ non accessible en écriture, ou extension pdo_sqlite absente.
    http_response_code(500);
    error_log('VitrineExpress : ' . $e->getMessage());
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fr"><meta charset="utf-8"><title>VitrineExpress — Erreur de démarrage</title>'
        . '<body style="font-family:system-ui,sans-serif;max-width:640px;margin:10vh auto;padding:0 1rem;line-height:1.5">'
        . '<h1>VitrineExpress ne peut pas démarrer</h1>'
        . '<p>La base de données n’a pas pu être ouverte ou créée. Vérifiez sur le serveur :</p><ul>'
        . '<li>que le dossier <code>storage/</code> du projet existe et est accessible en écriture par PHP ;</li>'
        . '<li>que l’extension PHP <code>pdo_sqlite</code> est activée ;</li>'
        . '<li>que la version de PHP est 8.1 ou plus.</li></ul>'
        . '<p>Le détail de l’erreur est dans le journal d’erreurs du serveur.</p>'
        . (!empty($config['debug']) ? '<pre>' . e($e->getMessage()) . '</pre>' : '')
        . '</body></html>';
    exit;
}

(new VitrineExpress\Kernel($app))
    ->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $path)
    ->send();
