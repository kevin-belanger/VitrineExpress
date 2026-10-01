<?php

declare(strict_types=1);

use VitrineExpress\Auth;
use VitrineExpress\Installer;
use VitrineExpress\Kernel;
use VitrineExpress\Users;

/** Simule un envoi de formulaire avec un jeton CSRF valide. */
function post_form(Kernel $kernel, string $path, array $fields): \VitrineExpress\Response
{
    $_POST = $fields + ['_csrf' => csrf_token()];
    $_SERVER['CONTENT_LENGTH'] = '0';
    $response = $kernel->handle('POST', $path);
    $_POST = [];
    return $response;
}

function test_fresh_install_redirects_to_installer(): void
{
    $app = test_app();
    $kernel = new Kernel($app);
    foreach (['/', '/login', '/admin', '/admin/messages'] as $path) {
        $response = $kernel->handle('GET', $path);
        assert_same(303, $response->status, $path);
        assert_same(url('/install'), $response->headers['Location'], $path);
    }
    $page = $kernel->handle('GET', '/install');
    assert_same(200, $page->status);
    assert_contains('Vérification du serveur', $page->body);
    assert_same(401, $kernel->handle('GET', '/api/device/next')->status, 'L’API des télés n’est pas redirigée');
}

function test_web_install_creates_first_account_and_logs_in(): void
{
    $app = test_app();
    $kernel = new Kernel($app);

    $bad = post_form($kernel, '/install', ['username' => 'directeur', 'password' => 'court', 'password_confirm' => 'court']);
    assert_same(422, $bad->status);
    assert_same(0, Users::count($app));

    $ok = post_form($kernel, '/install', [
        'org_name' => 'Cégep test', 'username' => 'directeur', 'display_name' => 'Direction',
        'password' => 'motdepasse1', 'password_confirm' => 'motdepasse1',
    ]);
    assert_same(303, $ok->status);
    assert_same(url('/admin'), $ok->headers['Location']);
    assert_same(1, Users::count($app));
    assert_same('Cégep test', $app->setting('org_name'));
    assert_same('directeur', Auth::user($app)['username'], 'Connecté automatiquement');

    // Une fois installé, la page d'installation n'est plus accessible.
    assert_same(url('/login'), $kernel->handle('GET', '/install')->headers['Location']);
    post_form($kernel, '/install', ['username' => 'pirate', 'password' => 'motdepasse2', 'password_confirm' => 'motdepasse2']);
    assert_same(1, Users::count($app), 'Impossible de créer un second compte par l’installation');
    Auth::logout();
}

function test_ini_sizes_are_parsed(): void
{
    assert_same(2 * 1024 * 1024, Installer::iniBytes('2M'));
    assert_same(512 * 1024, Installer::iniBytes('512K'));
    assert_same(1024 ** 3, Installer::iniBytes('1G'));
    assert_same(1000, Installer::iniBytes('1000'));
}

function test_installer_refuses_when_already_installed(): void
{
    $app = test_app();
    Users::create($app, 'admin', 'secret123');
    assert_same(null, Installer::install($app, '', 'autre', 'secret456', ''));
    assert_same(1, Users::count($app));
}
