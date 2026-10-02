<?php

declare(strict_types=1);

use VitrineExpress\Kernel;
use VitrineExpress\Messages;
use VitrineExpress\Users;

// Pages vues par un gestionnaire, de la requête à la base (jeu de données d'AccessTest.php :
// Julie gère « Comptabilité », Marc gère « Aire commune »).

function manager_kernel(array $f, string $who = 'julie'): Kernel
{
    $_SESSION['user_id'] = $f[$who];
    return new Kernel($f['app']);
}

function message_form(array $fields): array
{
    return $fields + [
        'type' => Messages::TYPE_TEXT,
        'title' => 'Sans titre',
        'text_html' => '<p>Texte</p>',
        'background_id' => '1',
        'start_date' => '2026-01-01',
        'duration_seconds' => '20',
    ];
}

function test_manager_is_refused_administration_pages(): void
{
    $f = access_fixture();
    $kernel = manager_kernel($f);
    foreach (['/admin/users', '/admin/groups', '/admin/settings', '/admin/devices/new', '/admin/devices/' . $f['tv_compta'] . '/edit'] as $path) {
        assert_same(403, $kernel->handle('GET', $path)->status, $path);
    }
    assert_same(403, post_form($kernel, '/admin/devices/' . $f['tv_compta'] . '/disconnect', [])->status);

    $home = $kernel->handle('GET', '/admin');
    assert_same(200, $home->status);
    assert_contains('href="/admin/messages"', $home->body);
    assert_false(str_contains($home->body, 'href="/admin/users"'), 'Menu sans les pages réservées');
}

function test_manager_sees_devices_of_their_groups_read_only(): void
{
    $f = access_fixture();
    $kernel = manager_kernel($f);
    $page = $kernel->handle('GET', '/admin/devices')->body;
    assert_contains('Télé compta', $page);
    assert_false(str_contains($page, 'Télé commune'), 'Pas les périphériques des autres groupes');
    assert_false(str_contains($page, '/admin/devices/new'), 'Pas d’ajout');
    assert_false(str_contains($page, '/admin/devices/' . $f['tv_compta'] . '/edit'), 'Pas de modification');
    assert_false(str_contains($page, 'class="code"'), 'Pas de code de connexion');

    $live = json_decode($kernel->handle('GET', '/admin/devices/live')->body, true)['devices'];
    assert_same([$f['tv_compta']], array_keys($live));
}

function test_manager_creates_messages_in_their_scope_only(): void
{
    $f = access_fixture();
    $kernel = manager_kernel($f);
    $response = post_form($kernel, '/admin/messages', message_form([
        'title' => 'Fête',
        'groups' => [(string) $f['compta'], (string) $f['commune']],
        'devices' => [(string) $f['tv_libre']],
    ]));
    assert_same(303, $response->status);
    $message = $f['app']->db->query("SELECT * FROM messages WHERE title = 'Fête'")->fetch();
    assert_same($f['julie'], (int) $message['created_by']);
    assert_same([$f['compta']], Messages::groupIds($f['app'], (int) $message['id']), 'Seulement son groupe');
    assert_same([], Messages::deviceIds($f['app'], (int) $message['id']), 'Pas de périphérique hors périmètre');

    post_form($kernel, '/admin/messages', message_form(['title' => 'Partout', 'all_devices' => '1']));
    $all = $f['app']->db->query("SELECT all_devices FROM messages WHERE title = 'Partout'")->fetchColumn();
    assert_same(0, (int) $all, '« Tous les périphériques » est refusé');
}

function test_manager_changes_only_the_broadcast_of_someone_elses_message(): void
{
    $f = access_fixture();
    $message = message_by($f, 'marc', [$f['commune']]);
    $id = (int) $message['id'];
    $kernel = manager_kernel($f);

    $page = $kernel->handle('GET', '/admin/messages/' . $id . '/edit');
    assert_same(200, $page->status);
    assert_contains('<h1>Diffusion</h1>', $page->body);
    assert_contains('Créé par Marc', $page->body);
    assert_contains('Aussi affiché dans : Aire commune.', $page->body);
    assert_false(str_contains($page->body, 'name="title"'), 'Contenu en lecture seule');
    assert_false(str_contains($page->body, 'form="delete-form"'), 'Pas de suppression');

    assert_same(303, post_form($kernel, '/admin/messages/' . $id, message_form([
        'title' => 'Titre changé',
        'groups' => [(string) $f['compta']],
    ]))->status);
    assert_same('Message de marc', $f['app']->db->query("SELECT title FROM messages WHERE id = {$id}")->fetchColumn(), 'Titre inchangé');
    assert_same([$f['compta'], $f['commune']], Messages::groupIds($f['app'], $id), 'Groupe ajouté, celui de Marc conservé');

    assert_same(403, post_form($kernel, '/admin/messages/' . $id . '/delete', [])->status);
    assert_same(1, (int) $f['app']->db->query("SELECT COUNT(*) FROM messages WHERE id = {$id}")->fetchColumn());

    // Un message « Tous » s'ouvre aussi, en lecture seule : il est déjà partout.
    $global = message_by($f, 'admin', [], true);
    $page = $kernel->handle('GET', '/admin/messages/' . $global['id'] . '/edit');
    assert_same(200, $page->status);
    assert_contains('<p class="target-fixed">Tous les périphériques d’affichage</p>', $page->body);
    assert_contains('>Retour</a>', $page->body);
    assert_false(str_contains($page->body, 'Enregistrer'), 'Rien à enregistrer');
    assert_same(403, post_form($kernel, '/admin/messages/' . $global['id'], ['groups' => [(string) $f['compta']]])->status);
    assert_same([], Messages::groupIds($f['app'], (int) $global['id']), '« Tous » inchangé');
}

function test_manager_without_group_opens_messages_read_only(): void
{
    $f = access_fixture();
    $lea = Users::create($f['app'], 'lea', 'secret123', 'Léa', Users::ROLE_MANAGER);
    $f['lea'] = $lea;
    $message = message_by($f, 'marc', [$f['commune']]);
    $page = manager_kernel($f, 'lea')->handle('GET', '/admin/messages/' . $message['id'] . '/edit');
    assert_same(200, $page->status);
    assert_contains('Aire commune', $page->body, 'Où le message est affiché');
    assert_contains('aucun groupe ne vous est confié', $page->body);
    assert_false(str_contains($page->body, 'Enregistrer'), 'Rien à enregistrer');
}

function test_manager_deletes_their_own_message(): void
{
    $f = access_fixture();
    $own = message_by($f, 'julie', [$f['compta'], $f['commune']]);
    $kernel = manager_kernel($f);

    $page = $kernel->handle('GET', '/admin/messages/' . $own['id'] . '/edit')->body;
    assert_contains('form="delete-form"', $page, '« Supprimer » dans la fiche');
    assert_contains('Il est aussi affiché dans : Aire commune.', $page, 'Avertissement avant de supprimer un message partagé');

    assert_same(303, post_form($kernel, '/admin/messages/' . $own['id'] . '/delete', [])->status);
    assert_same(0, (int) $f['app']->db->query('SELECT COUNT(*) FROM messages')->fetchColumn());
}

function test_manager_message_list_defaults_to_their_groups(): void
{
    $f = access_fixture();
    message_by($f, 'julie', [], false, 'Brouillon de Julie');
    message_by($f, 'marc', [$f['compta']], false, 'Pour la compta');
    $commune = message_by($f, 'marc', [$f['commune']], false, 'Pour l’aire commune');
    $global = message_by($f, 'admin', [], true, 'Pour tous');
    $kernel = manager_kernel($f);

    $mine = $kernel->handle('GET', '/admin/messages')->body;
    assert_contains('Brouillon de Julie', $mine);
    assert_contains('par vous', $mine);
    assert_contains('par Marc', $mine);
    assert_contains('Pour la compta', $mine);
    assert_false(str_contains($mine, 'Pour l’aire commune'), 'Par défaut : ses groupes seulement');
    assert_contains('Pour tous', $mine);
    assert_contains('data-href="/admin/messages/' . $global['id'] . '/edit"', $mine, 'Toutes les lignes s’ouvrent, même « Tous »');

    $_GET = ['group' => 'all'];
    try {
        $all = $kernel->handle('GET', '/admin/messages')->body;
    } finally {
        $_GET = [];
    }
    assert_contains('Pour l’aire commune', $all, '« Tous les messages » : aussi ceux des autres');
    assert_contains('<option value="all" selected>Tous les messages</option>', $all);
    assert_contains('data-href="/admin/messages/' . $commune['id'] . '/edit"', $all, 'Ligne cliquable vers la page Diffusion');
}

function test_expired_messages_bulk_delete_respects_rights(): void
{
    $f = access_fixture();
    $mine = message_by($f, 'julie', [$f['compta']], false, 'Vieux (Julie)');
    $theirs = message_by($f, 'marc', [$f['compta']], false, 'Vieux (Marc)');
    $f['app']->db->exec("UPDATE messages SET end_at = '2020-01-01 00:00:00' WHERE id IN ({$mine['id']}, {$theirs['id']})");
    message_by($f, 'marc', [$f['compta']], false, 'Actuel');
    $kernel = manager_kernel($f);
    $titles = static fn (): array => array_column($f['app']->db->query('SELECT title FROM messages ORDER BY id')->fetchAll(), 'title');

    $_GET = ['status' => Messages::STATUS_EXPIRED];
    try {
        $expiredList = $kernel->handle('GET', '/admin/messages')->body;
    } finally {
        $_GET = [];
    }
    assert_contains('Supprimer votre message expiré', $expiredList, 'Gestionnaire : seulement le sien, au singulier');
    assert_false(str_contains($kernel->handle('GET', '/admin/messages')->body, 'delete-expired'), 'Bouton seulement sur la liste « Expiré »');

    assert_same(303, post_form($kernel, '/admin/messages/delete-expired', [])->status);
    assert_same(['Vieux (Marc)', 'Actuel'], $titles(), 'Le message expiré de Marc reste');

    $admin = manager_kernel($f, 'admin');
    $_GET = ['status' => Messages::STATUS_EXPIRED];
    try {
        assert_contains('Supprimer le message expiré', $admin->handle('GET', '/admin/messages')->body);
    } finally {
        $_GET = [];
    }
    post_form($admin, '/admin/messages/delete-expired', []);
    assert_same(['Actuel'], $titles(), 'Administrateur : tous les expirés');
}

function test_manager_dashboard_covers_their_scope(): void
{
    $f = access_fixture();
    message_by($f, 'marc', [$f['commune']], false, 'Pour l’aire commune');
    $page = manager_kernel($f)->handle('GET', '/admin')->body;
    assert_contains('0 sur 1 en ligne', $page, 'Seulement son périphérique');
    assert_contains('Télé compta', $page, 'Périphérique non connecté nommé');
    assert_false(str_contains($page, '/admin/devices/' . $f['tv_compta'] . '/edit'), 'Pas de lien vers la fiche');
    assert_contains('Créez une image ou un texte', $page, 'Le message de l’aire commune ne compte pas');

    // Miniatures en diffusion : toutes s'ouvrent, même celle d'un message « Tous ».
    $global = message_by($f, 'admin', [], true, 'Pour tous');
    $own = message_by($f, 'julie', [$f['compta']], false, 'Pour la compta');
    $page = manager_kernel($f)->handle('GET', '/admin')->body;
    assert_contains('2 messages en diffusion', $page);
    assert_contains('href="/admin/messages/' . $global['id'] . '/edit"', $page);
    assert_contains('href="/admin/messages/' . $own['id'] . '/edit"', $page);
}

function test_new_message_targets_all_devices_by_default_when_allowed(): void
{
    $f = access_fixture();
    $admin = manager_kernel($f, 'admin')->handle('GET', '/admin/messages/new')->body;
    assert_contains('name="all_devices" value="1" data-choice-target="target-choice" checked', $admin, 'Administrateur : « Tous » coché');

    $julie = manager_kernel($f)->handle('GET', '/admin/messages/new')->body;
    assert_false(str_contains($julie, 'name="all_devices"'), 'Gestionnaire : pas de « Tous »');
    assert_false((bool) preg_match('/name="(groups|devices)\[\]"[^>]*checked/', $julie), 'Gestionnaire : aucune cible cochée d’office');
}

function test_admin_edits_any_message_with_every_target(): void
{
    $f = access_fixture();
    $global = message_by($f, 'julie', [], true);
    $page = manager_kernel($f, 'admin')->handle('GET', '/admin/messages/' . $global['id'] . '/edit');
    assert_same(200, $page->status);
    assert_contains('name="title"', $page->body, 'Formulaire complet');
    assert_contains('name="all_devices" value="1"', $page->body, 'Choix « Tous »');
    assert_contains('Télé commune', $page->body, 'Tous les périphériques proposés');
    assert_false(str_contains($page->body, 'Aussi affiché dans'), 'Rien n’est hors du périmètre d’un administrateur');
}

function test_admin_assigns_groups_to_a_manager(): void
{
    $f = access_fixture();
    $kernel = manager_kernel($f, 'admin');
    assert_same(303, post_form($kernel, '/admin/users', [
        'username' => 'lea',
        'password' => 'secret123',
        'password_confirm' => 'secret123',
        'role' => Users::ROLE_MANAGER,
        'groups' => [(string) $f['libre']],
    ])->status);
    $lea = (int) $f['app']->db->query("SELECT id FROM users WHERE username = 'lea'")->fetchColumn();
    assert_same([$f['libre']], Users::groupIds($f['app'], $lea));

    // Devenir administrateur retire les groupes ; on ne peut pas retirer son propre rôle.
    post_form($kernel, '/admin/users/' . $lea, ['username' => 'lea', 'role' => Users::ROLE_ADMIN, 'groups' => [(string) $f['libre']]]);
    assert_same([], Users::groupIds($f['app'], $lea));
    $self = post_form($kernel, '/admin/users/' . $f['admin'], ['username' => 'admin', 'role' => Users::ROLE_MANAGER]);
    assert_same(422, $self->status);
    assert_same(Users::ROLE_ADMIN, $f['app']->db->query("SELECT role FROM users WHERE id = {$f['admin']}")->fetchColumn());
}
