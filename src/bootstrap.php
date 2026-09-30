<?php

declare(strict_types=1);

namespace VitrineExpress;

spl_autoload_register(static function (string $class): void {
    $prefix = __NAMESPACE__ . '\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/helpers.php';

/**
 * Charge la configuration (défauts + config.local.php + remplacements explicites).
 */
function load_config(array $overrides = []): array
{
    $root = dirname(__DIR__);
    $config = require $root . '/config/config.php';
    $local = $root . '/config/config.local.php';
    if (is_file($local)) {
        $config = array_replace($config, require $local);
    }
    return array_replace($config, $overrides);
}
