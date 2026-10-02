<?php

declare(strict_types=1);

use VitrineExpress\Devices;
use VitrineExpress\Groups;
use VitrineExpress\HtmlSanitizer;
use VitrineExpress\Media;
use VitrineExpress\Messages;
use VitrineExpress\Playlist;
use VitrineExpress\ValidationException;

/** Crée un message texte directement (dates au format de stockage). */
function make_message(\VitrineExpress\App $app, string $title, array $groups = [], bool $all = false, string $start = '2026-01-01 00:00:00', ?string $end = null): int
{
    return Messages::create($app, [
        'title' => $title,
        'type' => Messages::TYPE_TEXT,
        'text_html' => '<p>' . $title . '</p>',
        'background_id' => 1,
        'duration_seconds' => 20,
        'start_at' => $start,
        'end_at' => $end,
        'all_devices' => $all,
        'group_ids' => $groups,
    ], null, null);
}

function test_sanitizer_keeps_editor_markup(): void
{
    $html = '<h1 class="ql-align-center">Titre</h1><p class="ql-size-large ql-align-right">Été <strong>gras</strong> <em>it</em> <u>s</u> <s>b</s><br></p>'
        . '<ol><li data-list="bullet" class="ql-indent-1"><span class="ql-ui" contenteditable="false"></span>Un</li><li data-list="ordered">Deux</li></ol>';
    $clean = HtmlSanitizer::clean($html);
    assert_contains('<h1 class="ql-align-center">Titre</h1>', $clean);
    assert_contains('<p class="ql-size-large ql-align-right">Été <strong>gras</strong>', $clean);
    assert_contains('<li data-list="bullet" class="ql-indent-1">Un</li>', $clean);
    assert_false(str_contains($clean, 'ql-ui'), 'Les éléments d’interface de Quill sont retirés');
    assert_false(str_contains($clean, 'contenteditable'));
}

function test_sanitizer_removes_dangerous_content(): void
{
    $html = '<p onclick="x()" style="color:red" class="evil ql-align-center">Salut<script>alert(1)</script></p>'
        . '<img src=x onerror=alert(1)><a href="javascript:alert(1)">lien</a><iframe src="//x"></iframe>'
        . '<div><b>b</b></div><!-- commentaire --><p data-x="1">ok</p>';
    $clean = HtmlSanitizer::clean($html);
    foreach (['script', 'onclick', 'style=', 'evil', '<img', 'onerror', 'javascript', '<a', 'iframe', '<div', '<b>', 'commentaire', 'data-x'] as $bad) {
        assert_false(str_contains($clean, $bad), "« {$bad} » devrait être retiré : {$clean}");
    }
    assert_contains('<p class="ql-align-center">Salut</p>', $clean);
    assert_contains('lien', $clean, 'Le texte des balises inconnues est gardé');
    assert_contains('<p>ok</p>', $clean);
}

function test_sanitizer_blank_detection(): void
{
    assert_true(HtmlSanitizer::isBlank('<p><br></p>'));
    assert_true(HtmlSanitizer::isBlank('<p>&nbsp; </p>'));
    assert_false(HtmlSanitizer::isBlank('<p>a</p>'));
}

function test_message_form_validation(): void
{
    $app = test_app();
    $group = Groups::create($app, 'Local 101', '', []);

    [, $errors] = Messages::fromForm($app, ['title' => '', 'start_date' => 'x', 'duration_seconds' => '1'], Messages::TYPE_TEXT);
    foreach (['title', 'start', 'duration_seconds', 'text_html', 'background_id'] as $field) {
        assert_true(isset($errors[$field]), "Erreur attendue sur {$field}");
    }
    assert_false(isset($errors['targets']), 'Un message sans groupe est permis');

    [$values, $errors] = Messages::fromForm($app, [
        'title' => 'Bienvenue', 'start_date' => '2026-10-01', 'start_time' => '', 'end_date' => '2026-10-05', 'end_time' => '',
        'duration_seconds' => '15', 'groups' => [(string) $group, '999'], 'background_id' => '2', 'text_html' => '<p>Bonjour</p>',
    ], Messages::TYPE_TEXT);
    assert_same([], $errors);
    assert_same('2026-10-01 00:00:00', $values['start_at'], 'Heure de début par défaut : 00:00');
    assert_same('2026-10-05 23:59:00', $values['end_at'], 'Heure de fin par défaut : 23:59');
    assert_same([$group], $values['group_ids']);
    assert_same(15, $values['duration_seconds']);

    [$values, $errors] = Messages::fromForm($app, [
        'title' => 'Permanent', 'start_date' => '2026-10-01', 'duration_seconds' => '20', 'all_devices' => '1',
    ], Messages::TYPE_IMAGE);
    assert_same([], $errors);
    assert_same(null, $values['end_at'], 'Sans date de fin, le message est permanent');

    [$values] = Messages::fromForm($app, [
        'title' => 'Tous', 'start_date' => '2026-10-01', 'duration_seconds' => '20', 'all_devices' => '1',
        'groups' => [(string) $group],
    ], Messages::TYPE_IMAGE);
    assert_same([], $values['group_ids'], 'Avec « Tous », les groupes cochés auparavant sont ignorés');

    [, $errors] = Messages::fromForm($app, [
        'title' => 'X', 'start_date' => '2026-10-05', 'end_date' => '2026-10-01', 'duration_seconds' => '20', 'all_devices' => '1',
    ], Messages::TYPE_IMAGE);
    assert_true(isset($errors['end']), 'La fin doit suivre le début');
}

function test_message_status(): void
{
    $now = '2026-06-15 12:00:00';
    assert_same(Messages::STATUS_ACTIVE, Messages::status(['start_at' => '2026-06-01 00:00:00', 'end_at' => null], $now));
    assert_same(Messages::STATUS_UPCOMING, Messages::status(['start_at' => '2026-07-01 00:00:00', 'end_at' => null], $now));
    assert_same(Messages::STATUS_EXPIRED, Messages::status(['start_at' => '2026-06-01 00:00:00', 'end_at' => '2026-06-10 23:59:00'], $now));
}

function test_queue_contains_active_targeted_messages_without_duplicates(): void
{
    $app = test_app();
    $local = Groups::create($app, 'Local 101', '', []);
    $compta = Groups::create($app, 'Comptabilité', '', []);
    $other = Groups::create($app, 'Autre', '', []);
    $tv = Devices::create($app, 'Télé 101', '', [$local, $compta]);

    $both = make_message($app, 'Deux groupes', [$local, $compta]);
    make_message($app, 'Autre groupe', [$other]);
    make_message($app, 'Sans cible', []);
    $all = make_message($app, 'Tous', [], true);
    make_message($app, 'À venir', [$local], false, '2026-12-01 00:00:00');
    make_message($app, 'Expiré', [$local], false, '2026-01-01 00:00:00', '2026-02-01 23:59:00');
    $permanent = make_message($app, 'Permanent', [$compta]);

    $ids = array_map(static fn (array $m): int => (int) $m['id'], Playlist::queue($app, $tv, '2026-06-15 12:00:00'));
    assert_same([$both, $all, $permanent], $ids);
}

function test_messages_can_target_devices_directly(): void
{
    $app = test_app();
    $now = '2026-06-15 12:00:00';
    $group = Groups::create($app, 'G', '', []);
    $inGroup = Devices::create($app, 'Dans le groupe', '', [$group]);
    $alone = Devices::create($app, 'Seule', '', []);
    $other = Devices::create($app, 'Autre', '', []);

    [$values, $errors] = Messages::fromForm($app, [
        'title' => 'Direct', 'start_date' => '2026-06-01', 'duration_seconds' => '20',
        'devices' => [(string) $alone, '999'], 'groups' => [(string) $group],
    ], Messages::TYPE_IMAGE);
    assert_same([], $errors);
    assert_same([$alone], $values['device_ids'], 'Les périphériques inconnus sont ignorés');
    $id = Messages::create($app, $values, ['path' => 'x.png', 'mime' => 'image/png'], null);
    assert_same([$alone], Messages::deviceIds($app, $id));

    $ids = static fn (int $device): array => array_map(static fn (array $m): int => (int) $m['id'], Playlist::queue($app, $device, $now));
    assert_same([$id], $ids($alone), 'Ciblé directement');
    assert_same([$id], $ids($inGroup), 'Ciblé par son groupe');
    assert_same([], $ids($other));

    $row = Messages::search($app, ['device' => $alone])[0];
    assert_same(['Seule'], $row['device_names']);
    assert_same(['G'], $row['group_names']);

    // Seulement des périphériques, sans groupe : le message est bien « en diffusion ».
    $only = make_message($app, 'Périphérique seulement', []);
    Messages::setDevices($app, $only, [$other]);
    $live = array_column(Messages::search($app, ['status' => Messages::FILTER_LIVE], $now), 'title');
    assert_true(in_array('Périphérique seulement', $live, true));
    assert_same(3, \VitrineExpress\Dashboard::messages($app, $now)['reached']);

    // Supprimer le périphérique retire le lien, pas le message.
    $app->db->exec("DELETE FROM devices WHERE id = {$other}");
    assert_same([], Messages::deviceIds($app, $only));
    assert_same('Périphérique seulement', Messages::search($app, ['status' => Messages::FILTER_UNBROADCAST], $now)[0]['title']);
}

function test_rotation_cycles_and_tolerates_changes(): void
{
    $app = test_app();
    $group = Groups::create($app, 'G', '', []);
    $tv = Devices::create($app, 'Télé', '', [$group]);
    $now = '2026-06-15 12:00:00';

    assert_same(null, Playlist::advance($app, $tv, $now)['current'], 'File vide');

    $a = make_message($app, 'A', [$group]);
    $b = make_message($app, 'B', [$group]);
    $c = make_message($app, 'C', [$group]);

    $step = Playlist::advance($app, $tv, $now);
    assert_same([$a, $b, 3], [(int) $step['current']['id'], (int) $step['next']['id'], $step['count']]);
    assert_same($b, (int) Playlist::advance($app, $tv, $now)['current']['id']);

    // Un message ajouté se place en fin de file.
    $d = make_message($app, 'D', [$group]);
    $step = Playlist::advance($app, $tv, $now);
    assert_same([$c, $d], [(int) $step['current']['id'], (int) $step['next']['id']]);

    // Le message courant est supprimé : la rotation continue.
    Messages::delete($app, $c);
    assert_same($d, (int) Playlist::advance($app, $tv, $now)['current']['id']);

    // Retour au début, et le suivant du dernier est le premier.
    $step = Playlist::advance($app, $tv, $now);
    assert_same([$a, $b], [(int) $step['current']['id'], (int) $step['next']['id']]);

    $row = $app->db->query("SELECT last_message_id, current_message_id, last_seen_at FROM devices WHERE id = {$tv}")->fetch();
    assert_same([$a, $a, $now], [(int) $row['last_message_id'], (int) $row['current_message_id'], $row['last_seen_at']]);

    // Un seul message : pas de suivant distinct.
    $single = Devices::create($app, 'Seule', '', []);
    $only = make_message($app, 'Unique', [], true);
    $app->db->exec("DELETE FROM messages WHERE id <> {$only}");
    $step = Playlist::advance($app, $single, $now);
    assert_same([$only, null], [(int) $step['current']['id'], $step['next']]);
}

function test_search_by_title_ignores_case_and_accents(): void
{
    $app = test_app();
    make_message($app, 'Fête de Noël');
    make_message($app, 'Réunion du CA');
    make_message($app, 'Cafétéria fermée');
    $titles = static fn (string $q): array => array_column(Messages::search($app, ['q' => $q]), 'title');

    assert_same(['Fête de Noël'], $titles('NOEL'));
    assert_same(['Fête de Noël', 'Cafétéria fermée'], $titles('fé'));
    assert_same([], $titles('zzz'));
    assert_same(3, count($titles('')), 'Vide : aucun filtre');
}

function test_expired_messages_are_deleted_in_bulk_by_owner(): void
{
    $app = test_app();
    $now = '2026-06-15 12:00:00';
    $julie = \VitrineExpress\Users::create($app, 'julie', 'secret123', '', \VitrineExpress\Users::ROLE_MANAGER);
    $marc = \VitrineExpress\Users::create($app, 'marc', 'secret123', '', \VitrineExpress\Users::ROLE_MANAGER);
    $old1 = make_message($app, 'Fini (Julie)', [], false, '2026-01-01 00:00:00', '2026-02-01 00:00:00');
    $old2 = make_message($app, 'Fini (Marc)', [], false, '2026-01-01 00:00:00', '2026-02-01 00:00:00');
    make_message($app, 'En cours', [], false, '2026-01-01 00:00:00', '2026-12-31 23:59:00');
    make_message($app, 'Sans fin');
    $app->db->exec("UPDATE messages SET created_by = {$julie} WHERE id = {$old1}");
    $app->db->exec("UPDATE messages SET created_by = {$marc} WHERE id = {$old2}");

    assert_same([$old1, $old2], Messages::expiredIds($app, null, $now), 'Administrateur : tous les expirés');
    assert_same([$old1], Messages::expiredIds($app, $julie, $now), 'Gestionnaire : les siens');
    assert_same(1, Messages::deleteMany($app, Messages::expiredIds($app, $julie, $now)));
    assert_same([$old2], Messages::expiredIds($app, null, $now), 'Le message de Marc reste');
    assert_same(3, (int) $app->db->query('SELECT COUNT(*) FROM messages')->fetchColumn());
}

function test_search_filters_by_group_device_and_status(): void
{
    $app = test_app();
    $g1 = Groups::create($app, 'G1', '', []);
    $g2 = Groups::create($app, 'G2', '', []);
    $tv = Devices::create($app, 'Télé', '', [$g1]);
    $m1 = make_message($app, 'M1', [$g1]);
    $m2 = make_message($app, 'M2', [$g2]);
    $m3 = make_message($app, 'M3', [], true);
    $m4 = make_message($app, 'M4', [$g1], false, '2999-01-01 00:00:00');

    $ids = static fn (array $rows): array => array_map(static fn (array $m): int => (int) $m['id'], $rows);
    assert_same([$m1, $m2, $m3, $m4], $ids(Messages::search($app, [])));
    assert_same([$m2, $m3], $ids(Messages::search($app, ['group' => $g2])));
    assert_same([$m1, $m3, $m4], $ids(Messages::search($app, ['device' => $tv])));
    assert_same([$m1, $m3], $ids(Messages::search($app, ['device' => $tv, 'status' => Messages::STATUS_ACTIVE])));
    assert_same([$m4], $ids(Messages::search($app, ['status' => Messages::STATUS_UPCOMING])));
    assert_same(['G1'], Messages::search($app, ['group' => $g1])[0]['group_names']);
}

function test_media_accepts_images_and_rejects_others(): void
{
    $app = test_app();
    $png = tempnam(sys_get_temp_dir(), 'vx');
    $image = imagecreatetruecolor(800, 450);
    imagepng($image, $png);

    $stored = Media::storeImage($app, $png);
    assert_same('image/png', $stored['mime']);
    assert_true((bool) preg_match(Media::NAME_PATTERN, $stored['path']));
    assert_true(Media::file($app, $stored['path']) !== null, 'Le fichier est stocké');
    assert_true(Media::file($app, Media::thumbName($stored['path'])) !== null, 'La miniature est générée');
    assert_same(null, Media::file($app, '../config/config.php'), 'Les chemins arbitraires sont refusés');

    Media::delete($app, $stored['path']);
    assert_same(null, Media::file($app, $stored['path']));

    $fake = tempnam(sys_get_temp_dir(), 'vx');
    file_put_contents($fake, '<?php echo "pas une image";');
    assert_throws(ValidationException::class, fn () => Media::storeImage($app, $fake));

    $app->setSetting('max_upload_mb', '1');
    $big = tempnam(sys_get_temp_dir(), 'vx');
    file_put_contents($big, str_repeat('x', 2 * 1024 * 1024));
    $error = assert_throws(ValidationException::class, fn () => Media::storeImage($app, $big));
    assert_same('Fichier trop volumineux (1 Mo maximum).', $error->getMessage());

    @unlink($png);
    @unlink($fake);
    @unlink($big);
}
