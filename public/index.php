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

try {
    $app = VitrineExpress\App::fromConfig($config);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('VitrineExpress : ' . $e->getMessage());
    echo 'Erreur de démarrage de l’application.' . (!empty($config['debug']) ? ' ' . e($e->getMessage()) : '');
    exit;
}

(new VitrineExpress\Kernel($app))
    ->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $path)
    ->send();
