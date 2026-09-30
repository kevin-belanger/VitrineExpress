<?php

declare(strict_types=1);

use VitrineExpress\Devices;
use VitrineExpress\DeviceSession;

function device_code(\VitrineExpress\App $app, int $id): string
{
    return (string) $app->db->query("SELECT code FROM devices WHERE id = {$id}")->fetchColumn();
}

function test_pairing_with_unknown_code_fails(): void
{
    $app = test_app();
    assert_same(DeviceSession::PAIR_UNKNOWN, DeviceSession::pair($app, '12345', false)['status']);
    assert_same(DeviceSession::PAIR_UNKNOWN, DeviceSession::pair($app, '', false)['status']);
}

function test_pairing_issues_a_token_and_stores_only_its_hash(): void
{
    $app = test_app();
    $tv = Devices::create($app, 'Télé 101', '', []);
    $result = DeviceSession::pair($app, device_code($app, $tv), false, 'TV-Agent', '10.0.0.5');

    assert_same(DeviceSession::PAIR_OK, $result['status']);
    assert_true((bool) preg_match('/^[a-f0-9]{64}$/', $result['token']));

    $row = $app->db->query("SELECT * FROM devices WHERE id = {$tv}")->fetch();
    assert_true($row['token_hash'] !== $result['token'], 'Le jeton n’est pas stocké en clair');
    assert_same(hash('sha256', $result['token']), $row['token_hash']);
    assert_same(['TV-Agent', '10.0.0.5'], [$row['user_agent'], $row['ip']]);
    assert_same($tv, (int) DeviceSession::find($app, $result['token'])['id']);
    assert_same(null, DeviceSession::find($app, str_repeat('a', 64)));
    assert_same(null, DeviceSession::find($app, 'pas-un-jeton'));
}

function test_second_device_needs_confirmation_while_first_is_online(): void
{
    $app = test_app();
    $tv = Devices::create($app, 'Télé 101', '', []);
    $code = device_code($app, $tv);
    $first = DeviceSession::pair($app, $code, false);

    $conflict = DeviceSession::pair($app, $code, false);
    assert_same(DeviceSession::PAIR_CONFLICT, $conflict['status']);
    assert_same('Télé 101', $conflict['device']['name']);
    assert_true(DeviceSession::find($app, $first['token']) !== null, 'Le premier appareil reste connecté');

    $forced = DeviceSession::pair($app, $code, true);
    assert_same(DeviceSession::PAIR_OK, $forced['status']);
    assert_same(null, DeviceSession::find($app, $first['token']), 'L’ancien jeton est invalidé');
    assert_true(DeviceSession::find($app, $forced['token']) !== null);
}

function test_offline_device_can_be_replaced_without_confirmation(): void
{
    $app = test_app();
    $tv = Devices::create($app, 'Télé 101', '', []);
    $code = device_code($app, $tv);
    DeviceSession::pair($app, $code, false);
    $app->db->exec("UPDATE devices SET last_seen_at = '2020-01-01 00:00:00' WHERE id = {$tv}");

    assert_same(DeviceSession::PAIR_OK, DeviceSession::pair($app, $code, false)['status']);
}

function test_api_next_requires_token_and_returns_slides(): void
{
    $app = test_app();
    $kernel = new \VitrineExpress\Kernel($app);
    unset($_SERVER[DeviceSession::HEADER], $_COOKIE[DeviceSession::COOKIE]);

    $response = $kernel->handle('GET', '/api/device/next');
    assert_same(401, $response->status);
    assert_contains('application/json', $response->headers['Content-Type']);

    $tv = Devices::create($app, 'Télé 101', '', []);
    $token = DeviceSession::pair($app, device_code($app, $tv), false)['token'];
    $_SERVER[DeviceSession::HEADER] = $token;

    $data = json_decode($kernel->handle('GET', '/api/device/next')->body, true);
    assert_same(null, $data['current'], 'File vide');
    assert_same('Télé 101', $data['device']['name']);

    make_message($app, 'Pour tous', [], true);
    $data = json_decode($kernel->handle('GET', '/api/device/next')->body, true);
    assert_same('text', $data['current']['type']);
    assert_same('<p>Pour tous</p>', $data['current']['html']);
    assert_same(20, $data['current']['duration']);
    assert_true(str_starts_with($data['current']['background'], 'linear-gradient'));

    unset($_SERVER[DeviceSession::HEADER]);
}

function test_admin_disconnect_invalidates_token(): void
{
    $app = test_app();
    $tv = Devices::create($app, 'Télé 101', '', []);
    $token = DeviceSession::pair($app, device_code($app, $tv), false)['token'];
    Devices::disconnect($app, $tv);
    assert_same(null, DeviceSession::find($app, $token));
}
