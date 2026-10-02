<?php

declare(strict_types=1);

use VitrineExpress\Devices;
use VitrineExpress\Kernel;
use VitrineExpress\Response;
use VitrineExpress\Throttle;
use VitrineExpress\Users;

function test_throttle_detects_private_addresses(): void
{
    foreach (['192.168.1.10', '10.0.0.5', '172.16.4.2', '127.0.0.1', '::1', 'fd12::1', 'fe80::1', ''] as $ip) {
        assert_true(Throttle::isPrivate($ip), "« {$ip} » devrait être locale");
    }
    foreach (['8.8.8.8', '70.80.90.100', '2607:f8b0::1'] as $ip) {
        assert_false(Throttle::isPrivate($ip), "« {$ip} » devrait être publique");
    }
}

function test_throttle_limit_depends_on_address_and_known_devices(): void
{
    $app = test_app();
    $t = new Throttle($app);
    $now = '2026-06-15 12:00:00';
    assert_same(5, $t->limit(Throttle::KIND_PAIR, '192.168.1.10', $now), 'Adresse locale : 5');
    assert_same(5, $t->limit(Throttle::KIND_PAIR, '70.80.90.100', $now), 'Adresse publique inconnue : 5');

    $device = Devices::create($app, 'Télé', '', []);
    $app->db->exec("UPDATE devices SET token_hash = 'abc', ip = '70.80.90.100', last_seen_at = '2026-06-15 02:00:00' WHERE id = {$device}");
    assert_same(20, $t->limit(Throttle::KIND_PAIR, '70.80.90.100', $now), 'Un périphérique vu il y a 10 h : 20');
    $app->db->exec("UPDATE devices SET last_seen_at = '2026-06-13 02:00:00' WHERE id = {$device}");
    assert_same(5, $t->limit(Throttle::KIND_PAIR, '70.80.90.100', $now), 'Vu il y a plus de 24 h : 5');
    assert_same(5, $t->limit(Throttle::KIND_LOGIN, '70.80.90.100', $now), 'Connexion des comptes : 5 par compte');
}

function test_throttle_blocks_after_consecutive_failures_then_forgets(): void
{
    $app = test_app();
    $t = new Throttle($app);
    $ip = '192.168.1.10';
    for ($i = 0; $i < 4; $i++) {
        $t->recordFailure(Throttle::KIND_PAIR, $ip, '', '2026-06-15 12:00:00');
    }
    assert_same(0, $t->retryAfter(Throttle::KIND_PAIR, $ip, '', '2026-06-15 12:00:30'), 'Quatre échecs : encore permis');

    $t->recordFailure(Throttle::KIND_PAIR, $ip, '', '2026-06-15 12:01:00');
    $wait = $t->retryAfter(Throttle::KIND_PAIR, $ip, '', '2026-06-15 12:01:30');
    assert_same(870, $wait, 'Bloqué 15 minutes après le dernier échec');
    assert_same(0, $t->retryAfter(Throttle::KIND_PAIR, '192.168.1.11', '', '2026-06-15 12:01:30'), 'Une autre adresse n’est pas touchée');
    assert_same(0, $t->retryAfter(Throttle::KIND_PAIR, $ip, '', '2026-06-15 12:17:00'), 'Quinze minutes plus tard : permis');

    $t->recordFailure(Throttle::KIND_PAIR, $ip, '', '2026-06-15 13:00:00');
    $t->clear(Throttle::KIND_PAIR, $ip);
    assert_same(0, (int) $app->db->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn(), 'Une réussite efface les échecs');
    assert_same('Trop d’essais. Réessayez dans 14 minutes.', Throttle::message(810));
    assert_same('Trop d’essais. Réessayez dans 1 minute.', Throttle::message(20));
}

function test_login_throttle_counts_per_account_and_per_address(): void
{
    $app = test_app();
    $t = new Throttle($app);
    $now = '2026-06-15 12:00:00';
    for ($i = 0; $i < 4; $i++) {
        $t->recordFailure(Throttle::KIND_LOGIN, '10.0.0.5', 'marie', $now);
    }
    assert_same(0, $t->retryAfter(Throttle::KIND_LOGIN, '10.0.0.5', 'marie', $now));
    $t->recordFailure(Throttle::KIND_LOGIN, '10.0.0.5', 'marie', $now);
    assert_true($t->retryAfter(Throttle::KIND_LOGIN, '10.0.0.5', 'marie', $now) > 0, 'Cinq échecs sur un compte : bloqué');
    assert_same(0, $t->retryAfter(Throttle::KIND_LOGIN, '10.0.0.5', 'julie', $now), 'Un autre compte depuis la même adresse reste permis');

    for ($i = 0; $i < 15; $i++) {
        $t->recordFailure(Throttle::KIND_LOGIN, '10.0.0.5', 'compte' . $i, $now);
    }
    assert_true($t->retryAfter(Throttle::KIND_LOGIN, '10.0.0.5', 'julie', $now) > 0, 'Vingt échecs depuis l’adresse : bloqué pour tous les comptes');
}

function test_pairing_is_throttled_by_address(): void
{
    $app = test_app();
    $kernel = new Kernel($app);
    $device = Devices::create($app, 'Télé', '', []);
    $code = (string) $app->db->query("SELECT code FROM devices WHERE id = {$device}")->fetchColumn();
    $_SERVER['REMOTE_ADDR'] = '192.168.1.10';
    $pair = static function (string $c) use ($kernel): Response {
        $_POST = ['code' => $c, 'force' => '0'];
        $_SERVER['CONTENT_LENGTH'] = '0';
        $response = $kernel->handle('POST', '/api/device/pair');
        $_POST = [];
        return $response;
    };
    try {
        for ($i = 0; $i < 5; $i++) {
            assert_same(404, $pair('00000')->status);
        }
        $blocked = $pair($code);
        assert_same(429, $blocked->status, 'Même le bon code attend');
        assert_contains('Trop d’essais', json_decode($blocked->body, true)['error']);

        $app->db->exec("UPDATE login_attempts SET attempted_at = '2020-01-01 00:00:00'"); // le temps passe
        assert_same(200, $pair($code)->status);
        assert_same(0, (int) $app->db->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn(), 'Réussite : compteur à zéro');
    } finally {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['CONTENT_LENGTH']);
    }
}

function test_login_is_throttled(): void
{
    $app = test_app();
    Users::create($app, 'marie', 'secret123');
    $kernel = new Kernel($app);
    $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
    try {
        for ($i = 0; $i < 5; $i++) {
            assert_same(422, post_form($kernel, '/login', ['username' => 'marie', 'password' => 'mauvais'])->status);
        }
        $blocked = post_form($kernel, '/login', ['username' => 'marie', 'password' => 'secret123']);
        assert_same(429, $blocked->status, 'Bon mot de passe, mais trop tard');
        assert_contains('Trop d’essais', $blocked->body);
        assert_false(isset($_SESSION['user_id']), 'Pas connecté');
    } finally {
        unset($_SERVER['REMOTE_ADDR']);
    }
}
