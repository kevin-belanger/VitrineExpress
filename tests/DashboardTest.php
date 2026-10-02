<?php

declare(strict_types=1);

use VitrineExpress\Dashboard;
use VitrineExpress\Devices;
use VitrineExpress\Groups;
use VitrineExpress\Messages;

function test_dashboard_device_summary(): void
{
    $app = test_app();
    $group = Groups::create($app, 'G', '', []);
    $online = Devices::create($app, 'En ligne', '', [$group]);
    $idle = Devices::create($app, 'Sans message', '', []);
    $offline = Devices::create($app, 'Éteinte', '', [$group]);
    Devices::create($app, 'Jamais', '', []);
    $recent = date('Y-m-d H:i:s');
    $app->db->exec("UPDATE devices SET token_hash = 'a' || id, last_seen_at = '{$recent}' WHERE id IN ({$online}, {$idle})");
    $app->db->exec("UPDATE devices SET token_hash = 'b', last_seen_at = '2020-01-01 00:00:00' WHERE id = {$offline}");
    make_message($app, 'Pour G', [$group]);

    $tv = Dashboard::devices($app);
    assert_same(4, $tv['total']);
    assert_same(2, $tv['online']);
    assert_same(['Éteinte'], array_column($tv['offline'], 'name'));
    assert_same(['Jamais'], array_column($tv['disconnected'], 'name'));
    assert_same(['Sans message'], array_column($tv['idle'], 'name'));
}

function test_dashboard_message_summary(): void
{
    $app = test_app();
    $now = '2026-06-15 12:00:00';
    $group = Groups::create($app, 'G', '', []);
    $emptyGroup = Groups::create($app, 'Vide', '', []);
    Devices::create($app, 'Télé', '', [$group]);
    Devices::create($app, 'Autre télé', '', []);

    make_message($app, 'Diffusé', [$group]);
    make_message($app, 'Pour tous', [], true);
    make_message($app, 'Finit demain', [$group], false, '2026-06-01 00:00:00', '2026-06-16 10:00:00');
    make_message($app, 'Finit dans 5 jours', [$group], false, '2026-06-01 00:00:00', '2026-06-20 10:00:00');
    make_message($app, 'Brouillon', []);
    make_message($app, 'Groupe vide', [$emptyGroup]);
    make_message($app, 'Plus tard', [$group], false, '2026-07-01 00:00:00');
    make_message($app, 'Bientôt', [$group], false, '2026-06-20 00:00:00');
    make_message($app, 'Fini', [$group], false, '2026-01-01 00:00:00', '2026-02-01 00:00:00');

    $msg = Dashboard::messages($app, $now);
    assert_same(9, $msg['total']);
    assert_same(['Diffusé', 'Pour tous', 'Finit demain', 'Finit dans 5 jours'], array_column($msg['live'], 'title'));
    assert_same(2, $msg['reached'], '« Pour tous » atteint aussi la télé sans groupe');
    assert_same(['Finit demain'], array_column($msg['ending'], 'title'));
    assert_same(['Bientôt', 'Plus tard'], array_column($msg['upcoming'], 'title'), 'Le prochain en premier');
    assert_same(['Brouillon', 'Groupe vide'], array_column($msg['unbroadcast'], 'title'));
    assert_same(1, $msg['expired']);
}

function test_dashboard_shows_live_message_thumbnails(): void
{
    $app = test_app();
    $_SESSION['user_id'] = \VitrineExpress\Users::create($app, 'admin', 'secret123');
    $group = Groups::create($app, 'G', '', []);
    Devices::create($app, 'Télé', '', [$group]);
    $ids = [];
    foreach (range(1, 7) as $n) {
        $ids[] = make_message($app, 'Diapo ' . $n, [$group]);
    }
    make_message($app, 'Brouillon', []);

    $page = (new \VitrineExpress\Kernel($app))->handle('GET', '/admin')->body;
    assert_contains('7 messages en diffusion', $page);
    assert_contains('href="/admin/messages/' . $ids[0] . '/edit"', $page, 'Miniature cliquable vers le message');
    assert_contains('title="Diapo 6"', $page);
    assert_false(str_contains($page, 'title="Diapo 7"'), 'Six miniatures au plus');
    assert_false(str_contains($page, 'title="Brouillon"'), 'Seulement les messages en diffusion');
    assert_contains('href="/admin/messages?status=live">destinés à 1 périphérique</a>', $page);
    assert_contains('href="/admin/messages">Détails</a>', $page, '« Détails » remplace « Voir les messages »');
    assert_false(str_contains($page, 'Voir les messages'));
}

function test_dashboard_names(): void
{
    $rows = [['name' => 'A'], ['name' => 'B'], ['name' => 'C'], ['name' => 'D'], ['name' => 'E']];
    assert_same('A, B, C et 2 autres', Dashboard::names($rows));
    assert_same('A, B, C et 1 autre', Dashboard::names(array_slice($rows, 0, 4)));
    assert_same('A', Dashboard::names([['name' => 'A']]));
    assert_same('A!, B!', Dashboard::names(array_slice($rows, 0, 2), static fn (array $r): string => $r['name'] . '!'));
}
