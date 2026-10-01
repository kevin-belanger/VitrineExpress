<?php

declare(strict_types=1);

use VitrineExpress\Access;
use VitrineExpress\Devices;
use VitrineExpress\Groups;
use VitrineExpress\Messages;
use VitrineExpress\Users;

/**
 * Jeu de données : deux groupes gérés par deux gestionnaires différents, un groupe sans gestionnaire,
 * un périphérique dans chaque groupe, et un administrateur.
 */
function access_fixture(): array
{
    $app = test_app();
    $f = ['app' => $app];
    $f['compta'] = Groups::create($app, 'Comptabilité', '', []);
    $f['commune'] = Groups::create($app, 'Aire commune', '', []);
    $f['libre'] = Groups::create($app, 'Sans gestionnaire', '', []);
    $f['tv_compta'] = Devices::create($app, 'Télé compta', '', [$f['compta']]);
    $f['tv_commune'] = Devices::create($app, 'Télé commune', '', [$f['commune']]);
    $f['tv_libre'] = Devices::create($app, 'Télé libre', '', [$f['libre']]);
    $f['admin'] = Users::create($app, 'admin', 'secret123');
    $f['julie'] = Users::create($app, 'julie', 'secret123', 'Julie', Users::ROLE_MANAGER);
    $f['marc'] = Users::create($app, 'marc', 'secret123', 'Marc', Users::ROLE_MANAGER);
    Users::setGroups($app, $f['julie'], [$f['compta']]);
    Users::setGroups($app, $f['marc'], [$f['commune']]);
    return $f;
}

function access_for(array $f, string $who): Access
{
    $st = $f['app']->db->prepare('SELECT id, username, display_name, role FROM users WHERE id = ?');
    $st->execute([$f[$who]]);
    $user = $st->fetch();
    $user['id'] = (int) $user['id'];
    return new Access($f['app'], $user);
}

function message_by(array $f, string $who, array $groups = [], bool $all = false, ?string $title = null): array
{
    $id = make_message($f['app'], $title ?? 'Message de ' . $who, $groups, $all);
    $f['app']->db->exec("UPDATE messages SET created_by = {$f[$who]} WHERE id = {$id}");
    return $f['app']->db->query("SELECT * FROM messages WHERE id = {$id}")->fetch();
}

function test_manager_can_only_visit_publishing_pages(): void
{
    $f = access_fixture();
    $julie = access_for($f, 'julie');
    foreach (['/admin', '/admin/messages', '/admin/messages/new', '/admin/messages/3/edit', '/admin/devices', '/admin/devices/live', '/admin/account'] as $path) {
        assert_true($julie->canVisit($path), "Gestionnaire : $path devrait être permis");
    }
    foreach (['/admin/users', '/admin/groups', '/admin/settings', '/admin/devices/new', '/admin/devices/1/edit', '/admin/messagesX'] as $path) {
        assert_false($julie->canVisit($path), "Gestionnaire : $path devrait être refusé");
    }
    assert_true(access_for($f, 'admin')->canVisit('/admin/users'));
}

function test_manager_scope_is_their_groups_and_their_devices(): void
{
    $f = access_fixture();
    $julie = access_for($f, 'julie');
    assert_same([$f['compta']], $julie->groupIds());
    assert_same([$f['tv_compta']], $julie->deviceIds());
    assert_same(3, count(access_for($f, 'admin')->groupIds()), 'Administrateur : tous les groupes');
}

function test_manager_creates_messages_only_in_their_scope(): void
{
    $f = access_fixture();
    $targets = access_for($f, 'julie')->mergeTargets(null, true, [$f['compta'], $f['commune']], [$f['tv_compta'], $f['tv_libre']]);
    assert_same(['all_devices' => false, 'group_ids' => [$f['compta']], 'device_ids' => [$f['tv_compta']]], $targets);

    $admin = access_for($f, 'admin')->mergeTargets(null, false, [$f['commune']], [$f['tv_libre']]);
    assert_same(['all_devices' => false, 'group_ids' => [$f['commune']], 'device_ids' => [$f['tv_libre']]], $admin);
}

function test_manager_only_toggles_their_targets_on_someone_elses_message(): void
{
    $f = access_fixture();
    $message = message_by($f, 'marc', [$f['commune']]);
    Messages::setDevices($f['app'], (int) $message['id'], [$f['tv_libre']]);
    $julie = access_for($f, 'julie');

    assert_false($julie->canEditContent($message));
    assert_false($julie->canDelete($message));
    assert_true($julie->canEditTargets($message));

    // Julie ajoute son groupe, et tente de retirer celui de Marc et le périphérique libre : seuls ses choix comptent.
    $targets = $julie->mergeTargets($message, false, [$f['compta']], []);
    assert_same([$f['compta'], $f['commune']], $targets['group_ids'], 'Le groupe de Marc reste');
    assert_same([$f['tv_libre']], $targets['device_ids'], 'Le périphérique hors périmètre reste');

    Messages::setTargets($f['app'], (int) $message['id'], $targets);
    $removed = $julie->mergeTargets($message, false, [], []);
    assert_same([$f['commune']], $removed['group_ids'], 'Julie retire seulement son groupe');
}

function test_manager_owns_their_messages_and_cannot_touch_all_devices(): void
{
    $f = access_fixture();
    $julie = access_for($f, 'julie');
    $own = message_by($f, 'julie', [$f['compta']]);
    assert_true($julie->canEditContent($own));
    assert_true($julie->canDelete($own));

    $global = message_by($f, 'admin', [], true);
    assert_false($julie->canEditTargets($global), '« Tous » est réservé aux administrateurs');
    assert_false($julie->canEdit($global));
    $kept = $julie->mergeTargets($global, false, [$f['compta']], []);
    assert_true($kept['all_devices'], 'Les cibles d’un message « Tous » ne changent pas');

    $lonely = new Access($f['app'], ['id' => 999, 'role' => Users::ROLE_MANAGER]);
    assert_false($lonely->canEditTargets(message_by($f, 'marc', [$f['commune']])), 'Sans groupe : rien à ajouter');
}

function test_other_targets_are_named_for_shared_messages(): void
{
    $f = access_fixture();
    $message = message_by($f, 'julie', [$f['compta'], $f['commune']]);
    $other = access_for($f, 'julie')->otherTargets($message);
    assert_same(['Aire commune'], $other['names']);
    assert_same([$f['tv_commune']], $other['device_ids']);
    assert_same([], access_for($f, 'admin')->otherTargets($message)['names'], 'Rien hors du périmètre d’un administrateur');
    $global = message_by($f, 'admin', [], true);
    assert_same(['names' => [], 'device_ids' => []], access_for($f, 'admin')->otherTargets($global), 'Même pour « Tous »');
    assert_same(['tous les périphériques d’affichage'], access_for($f, 'julie')->otherTargets($global)['names']);
}

function test_scope_filter_lists_own_targeted_and_displayed_messages(): void
{
    $f = access_fixture();
    // Un périphérique de Julie est aussi dans le groupe sans gestionnaire : ce qui vise ce groupe s'affiche chez elle.
    Devices::create($f['app'], 'Télé partagée', '', [$f['compta'], $f['libre']]);
    $mine = message_by($f, 'julie');
    $inGroup = message_by($f, 'marc', [$f['compta']]);
    $elsewhere = message_by($f, 'marc', [$f['commune']]);
    $everywhere = message_by($f, 'admin', [], true);
    $viaShared = message_by($f, 'admin', [$f['libre']]);
    $julie = access_for($f, 'julie');

    $ids = array_map(static fn (array $m): int => (int) $m['id'], Messages::search($f['app'], ['scope' => $julie->scope()]));
    assert_same([(int) $mine['id'], (int) $inGroup['id'], (int) $everywhere['id'], (int) $viaShared['id']], $ids);
    assert_false(in_array((int) $elsewhere['id'], $ids, true));
    assert_same(null, access_for($f, 'admin')->scope(), 'Administrateur : aucun filtre');
}
