<?php

declare(strict_types=1);

use VitrineExpress\Auth;
use VitrineExpress\Database;
use VitrineExpress\HttpException;
use VitrineExpress\Router;
use VitrineExpress\Users;

function test_migrations_create_schema_and_defaults(): void
{
    $app = test_app();
    $latest = count(glob(dirname(__DIR__) . '/migrations/*.sql'));
    assert_same($latest, (int) $app->db->query('PRAGMA user_version')->fetchColumn(), 'Toutes les migrations sont appliquées');
    assert_same('20', $app->setting('default_duration'));
    assert_same(8, (int) $app->db->query('SELECT COUNT(*) FROM backgrounds')->fetchColumn());
}

function test_migrations_are_idempotent(): void
{
    $app = test_app();
    assert_same(0, Database::migrate($app->db, dirname(__DIR__) . '/migrations'));
}

function test_settings_can_be_updated(): void
{
    $app = test_app();
    $app->setSetting('org_name', 'École test');
    assert_same('École test', $app->setting('org_name'));
    assert_same(20, $app->intSetting('default_duration', 5));
}

function test_router_matches_parameters_and_methods(): void
{
    $router = new Router();
    $router->get('/admin/users/{id}/edit', ['C', 'edit']);
    $router->post('/admin/users/{id}', ['C', 'update']);

    [$handler, $params] = $router->match('GET', '/admin/users/42/edit');
    assert_same(['C', 'edit'], $handler);
    assert_same(['id' => '42'], $params);

    assert_same(405, assert_throws(HttpException::class, fn () => $router->match('GET', '/admin/users/42'))->status);
    assert_same(404, assert_throws(HttpException::class, fn () => $router->match('GET', '/nowhere'))->status);
}

function test_user_validation_rules(): void
{
    $app = test_app();
    Users::create($app, 'admin', 'secret123');

    $errors = Users::validate($app, ['username' => 'Admin', 'display_name' => ''], 'secret123', 'secret123', null);
    assert_true(isset($errors['username']), 'Le code usager doit être unique, sans égard à la casse');

    $errors = Users::validate($app, ['username' => 'a b', 'display_name' => ''], 'secret123', 'secret123', null);
    assert_true(isset($errors['username']), 'Les espaces sont refusés');

    $errors = Users::validate($app, ['username' => 'marie', 'display_name' => ''], 'court', 'court', null);
    assert_true(isset($errors['password']), 'Mot de passe trop court');

    $errors = Users::validate($app, ['username' => 'marie', 'display_name' => ''], 'secret123', 'autre1234', null);
    assert_true(isset($errors['password_confirm']), 'Confirmation différente');

    $errors = Users::validate($app, ['username' => 'marie', 'display_name' => 'Marie'], '', '', 1);
    assert_same([], $errors, 'En modification, le mot de passe est facultatif');
}

function test_login_succeeds_only_with_right_password(): void
{
    $app = test_app();
    Users::create($app, 'admin', 'secret123');

    assert_false(Auth::attempt($app, 'admin', 'mauvais'));
    assert_false(Auth::attempt($app, 'inconnu', 'secret123'));
    assert_true(Auth::attempt($app, 'ADMIN', 'secret123'), 'Le code usager ne tient pas compte de la casse');
    assert_same('admin', Auth::user($app)['username']);

    Auth::logout();
    assert_same(null, Auth::user($app));
}

function test_base_path_hides_public_folder(): void
{
    assert_same('', compute_base_path('/index.php'), 'Racine web = public/');
    assert_same('', compute_base_path('/public/index.php'), 'Racine web = dossier du projet : pas de « /public » dans les adresses');
    assert_same('/vitrine', compute_base_path('/vitrine/public/index.php'), 'Sous-dossier, racine web = dossier du projet');
    assert_same('/vitrine', compute_base_path('/vitrine/index.php'), 'Sous-dossier, racine web = public/');
    assert_same('', compute_base_path('/media/abc.png'), 'Serveur intégré de PHP : SCRIPT_NAME ignoré');
    assert_same('', compute_base_path('tests/run.php'));

    assert_same('/admin', without_public_segment('/public/admin'));
    assert_same('/assets/admin.css', without_public_segment('/public/assets/admin.css'));
    assert_same('/', without_public_segment('/public'));
    assert_same('/', without_public_segment('/public/index.php'));
    assert_same(null, without_public_segment('/admin'));
    assert_same(null, without_public_segment('/publications'), 'Seul le segment exact « public » compte');
}

function test_helpers_escape_and_format(): void
{
    assert_same('&lt;b&gt;&quot;x&quot;&amp;', e('<b>"x"&'));
    assert_same('30 sept. 2026 14:05', format_datetime('2026-09-30 14:05:00'));
    assert_same('30 sept. 2026', format_datetime('2026-09-30 14:05:00', false));
}
