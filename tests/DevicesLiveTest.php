<?php

declare(strict_types=1);

use VitrineExpress\Devices;
use VitrineExpress\DeviceSession;
use VitrineExpress\Kernel;
use VitrineExpress\Playlist;
use VitrineExpress\Users;

function test_devices_live_state_reports_current_slide(): void
{
    $app = test_app();
    $_SESSION['user_id'] = Users::create($app, 'admin', 'secret123');
    $kernel = new Kernel($app);

    $online = Devices::create($app, 'Télé hall', '', []);
    $offline = Devices::create($app, 'Télé éteinte', '', []);
    DeviceSession::pair($app, (string) $app->db->query("SELECT code FROM devices WHERE id = {$online}")->fetchColumn(), false);
    $message = make_message($app, 'Une diapositive au titre assez long pour être coupé', [], true);
    Playlist::advance($app, $online);

    $response = $kernel->handle('GET', '/admin/devices/live');
    assert_same(200, $response->status);
    assert_contains('application/json', $response->headers['Content-Type']);
    $devices = json_decode($response->body, true)['devices'];

    assert_same('message-' . $message . '-' . $app->db->query("SELECT updated_at FROM messages WHERE id = {$message}")->fetchColumn(), $devices[$online]['key']);
    assert_contains('title="Une diapositive au titre assez long', $devices[$online]['current'], 'Titre en infobulle');
    assert_contains('href="/admin/messages/' . $message . '/edit"', $devices[$online]['current'], 'Miniature cliquable vers la modification');
    assert_contains('En ligne', $devices[$online]['status']);

    assert_same(Devices::STATUS_DISCONNECTED, $devices[$offline]['key'], 'Sans connexion : pas de diapositive');
    assert_contains('—', $devices[$offline]['current']);

    // Sans session : redirection vers la connexion (le script recharge alors la page).
    unset($_SESSION['user_id']);
    assert_same(303, $kernel->handle('GET', '/admin/devices/live')->status);
}
