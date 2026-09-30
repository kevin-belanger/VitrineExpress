<?php

declare(strict_types=1);

use VitrineExpress\Devices;
use VitrineExpress\Groups;

function test_device_codes_are_five_digits_and_unique(): void
{
    $app = test_app();
    $codes = [];
    for ($i = 0; $i < 50; $i++) {
        $id = Devices::create($app, 'Télé ' . $i, '', []);
        $codes[] = $app->db->query('SELECT code FROM devices WHERE id = ' . $id)->fetchColumn();
    }
    foreach ($codes as $code) {
        assert_true((bool) preg_match('/^[1-9]\d{4}$/', $code), "Code invalide : {$code}");
    }
    assert_same(count($codes), count(array_unique($codes)), 'Les codes doivent être uniques');
}

function test_device_group_membership_both_ways(): void
{
    $app = test_app();
    $local = Groups::create($app, 'Local 101', '', []);
    $compta = Groups::create($app, 'Comptabilité', '', []);
    $tv = Devices::create($app, 'Télé 101', '', [$local, $compta, 999]);

    assert_same([$local, $compta], Devices::groupIds($app, $tv), 'Les groupes inexistants sont ignorés');

    $tv2 = Devices::create($app, 'Télé 102', '', []);
    Groups::update($app, $compta, 'Comptabilité', '', [$tv, $tv2]);
    assert_same([$tv, $tv2], Groups::deviceIds($app, $compta));
    assert_same([$local, $compta], Devices::groupIds($app, $tv));

    Devices::update($app, $tv, 'Télé 101', '', [$local]);
    assert_same([$tv2], Groups::deviceIds($app, $compta));
}

function test_deleting_a_group_keeps_devices_and_messages(): void
{
    $app = test_app();
    $group = Groups::create($app, 'Aire commune', '', []);
    $tv = Devices::create($app, 'Télé cafétéria', '', [$group]);
    $app->db->exec("INSERT INTO messages (title, type, duration_seconds, start_at, created_at, updated_at)
                    VALUES ('Bienvenue', 'text', 20, '2026-01-01 00:00:00', '2026-01-01 00:00:00', '2026-01-01 00:00:00')");
    $messageId = (int) $app->db->lastInsertId();
    $app->db->exec("INSERT INTO message_groups (message_id, group_id) VALUES ({$messageId}, {$group})");

    $app->db->exec("DELETE FROM groups WHERE id = {$group}");

    assert_same(1, (int) $app->db->query('SELECT COUNT(*) FROM devices')->fetchColumn());
    assert_same(1, (int) $app->db->query('SELECT COUNT(*) FROM messages')->fetchColumn());
    assert_same(0, (int) $app->db->query('SELECT COUNT(*) FROM device_groups')->fetchColumn());
    assert_same(0, (int) $app->db->query('SELECT COUNT(*) FROM message_groups')->fetchColumn());
    assert_same([], Devices::groupIds($app, $tv));
}

function test_group_names_are_unique_case_insensitive(): void
{
    $app = test_app();
    $id = Groups::create($app, 'Pavillon A', '', []);
    assert_true(isset(Groups::validate($app, ['name' => 'pavillon a'], null)['name']));
    assert_same([], Groups::validate($app, ['name' => 'Pavillon A'], $id), 'Garder son propre nom est permis');
    assert_true(isset(Groups::validate($app, ['name' => ''], null)['name']));
}

function test_regenerate_code_disconnects_device(): void
{
    $app = test_app();
    $tv = Devices::create($app, 'Télé', '', []);
    $app->db->exec("UPDATE devices SET token_hash = 'abc', connected_at = '2026-01-01 00:00:00', last_seen_at = '2026-01-01 00:00:00' WHERE id = {$tv}");
    $old = $app->db->query("SELECT code FROM devices WHERE id = {$tv}")->fetchColumn();

    $new = Devices::regenerateCode($app, $tv);

    $row = $app->db->query("SELECT * FROM devices WHERE id = {$tv}")->fetch();
    assert_true($new !== $old, 'Le code doit changer');
    assert_same($new, $row['code']);
    assert_same(null, $row['token_hash']);
}

function test_device_status(): void
{
    $device = ['token_hash' => null, 'last_seen_at' => null];
    assert_same(Devices::STATUS_DISCONNECTED, Devices::status($device, 180));

    $device = ['token_hash' => 'x', 'last_seen_at' => date('Y-m-d H:i:s', time() - 60)];
    assert_same(Devices::STATUS_ONLINE, Devices::status($device, 180));

    $device = ['token_hash' => 'x', 'last_seen_at' => date('Y-m-d H:i:s', time() - 600)];
    assert_same(Devices::STATUS_OFFLINE, Devices::status($device, 180));
}
