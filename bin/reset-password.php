<?php

// Récupération d'un compte : nouveau mot de passe et, au besoin, rôle d'administrateur.
// Usage : php bin/reset-password.php [--username=admin] [--password=...] [--admin]
// Sans option, le compte et le mot de passe sont demandés. --admin rend le compte administrateur
// (utile quand plus aucun administrateur ne peut se connecter).

declare(strict_types=1);

use VitrineExpress\App;
use VitrineExpress\Users;

if (PHP_SAPI !== 'cli') {
    exit("Ce script s'exécute en ligne de commande.\n");
}

require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/cli-helpers.php';

$app = App::fromConfig(VitrineExpress\load_config());
$options = getopt('', ['username:', 'password:', 'admin']);

$accounts = $app->db->query('SELECT id, username, role FROM users ORDER BY username COLLATE NOCASE')->fetchAll();
if (!$accounts) {
    cli_fail("Aucun compte : lancez d'abord php bin/install.php.");
}

if (!isset($options['username'])) {
    echo "Comptes :\n";
    foreach ($accounts as $account) {
        echo '  ' . $account['username'] . ($account['role'] === Users::ROLE_ADMIN ? ' (administrateur)' : ' (gestionnaire de groupes)') . "\n";
    }
}
$username = $options['username'] ?? cli_ask('Code usager : ');
$user = null;
foreach ($accounts as $account) {
    if (mb_strtolower($account['username']) === mb_strtolower($username)) {
        $user = $account;
    }
}
if ($user === null) {
    cli_fail("Compte « {$username} » introuvable.");
}

if (isset($options['password'])) {
    $password = $confirm = (string) $options['password'];
} else {
    $password = cli_ask('Nouveau mot de passe : ', true);
    $confirm = cli_ask('Confirmation : ', true);
}
$errors = Users::validatePassword($password, $confirm);
if ($errors) {
    cli_fail(implode(' ', $errors));
}

$makeAdmin = isset($options['admin']);
$app->transaction(static function () use ($app, $user, $password, $makeAdmin): void {
    Users::setPassword($app, (int) $user['id'], $password);
    if ($makeAdmin) {
        $app->db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([Users::ROLE_ADMIN, $user['id']]);
        Users::setGroups($app, (int) $user['id'], []);
    }
});
echo "Mot de passe de « {$user['username']} » remplacé." . ($makeAdmin ? ' Le compte est administrateur.' : '') . "\n";
