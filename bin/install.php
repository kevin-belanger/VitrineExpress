<?php

// Installation : crée la base, applique les migrations et crée le premier compte administrateur.
// Usage : php bin/install.php [--username=admin] [--password=...] [--name="Nom affiché"]
// Sans options, les valeurs sont demandées de façon interactive.

declare(strict_types=1);

use VitrineExpress\App;
use VitrineExpress\Users;

if (PHP_SAPI !== 'cli') {
    exit("Ce script s'exécute en ligne de commande.\n");
}

require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/cli-helpers.php';

$config = VitrineExpress\load_config();
$app = App::fromConfig($config);
echo "Base de données prête : {$config['db_path']}\n";

foreach ([$config['uploads_path'], dirname($config['log_path'])] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, "Impossible de créer le dossier {$dir}\n");
        exit(1);
    }
}

if (Users::count($app) > 0) {
    echo "L'application est déjà installée (un compte administrateur existe). Rien d'autre à faire.\n";
    exit(0);
}

$options = getopt('', ['username:', 'password:', 'name:']);

$username = $options['username'] ?? cli_ask('Code usager du premier administrateur : ');
$name = $options['name'] ?? '';
if (isset($options['password'])) {
    $password = $confirm = $options['password'];
} else {
    $password = cli_ask('Mot de passe : ', true);
    $confirm = cli_ask('Confirmation : ', true);
}

$errors = Users::validate($app, ['username' => $username, 'display_name' => $name], $password, $confirm, null);
if ($errors) {
    foreach ($errors as $message) {
        fwrite(STDERR, "Erreur : {$message}\n");
    }
    exit(1);
}

Users::create($app, $username, $password, $name);
echo "Compte « {$username} » créé. Installation terminée.\n";
