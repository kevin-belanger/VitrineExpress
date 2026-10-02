<?php
// Données fictives de démonstration (centre communautaire inventé) pour les captures du site.
declare(strict_types=1);

require '/tmp/vx-demo/src/bootstrap.php';

use VitrineExpress\App;
use VitrineExpress\Devices;
use VitrineExpress\Groups;
use VitrineExpress\Media;
use VitrineExpress\Messages;
use VitrineExpress\Users;

$app = App::fromConfig(VitrineExpress\load_config());
$db = $app->db;
$app->setSetting('org_name', 'Centre communautaire des Érables');

$admin = (int) $db->query("SELECT id FROM users WHERE username = 'marie'")->fetchColumn();
$julie = Users::create($app, 'julie', 'demo-demo-1', 'Julie Tremblay', Users::ROLE_MANAGER);
$karim = Users::create($app, 'karim', 'demo-demo-1', 'Karim Haddad', Users::ROLE_MANAGER);

$hall = Groups::create($app, 'Hall d’entrée', 'Les deux écrans près de l’accueil', []);
$cafe = Groups::create($app, 'Cafétéria', '', []);
$gym = Groups::create($app, 'Gymnase', '', []);
$biblio = Groups::create($app, 'Bibliothèque', '', []);
Users::setGroups($app, $julie, [$biblio]);
Users::setGroups($app, $karim, [$gym, $cafe]);

$dHall1 = Devices::create($app, 'Hall — accueil', 'Au-dessus du comptoir', [$hall]);
$dHall2 = Devices::create($app, 'Hall — escalier', '', [$hall]);
$dCafe = Devices::create($app, 'Cafétéria', 'Mur nord', [$cafe]);
$dGym = Devices::create($app, 'Gymnase', 'Près des gradins', [$gym]);
$dBiblio = Devices::create($app, 'Bibliothèque', '', [$biblio]);
$dSalle = Devices::create($app, 'Salle polyvalente', '', []);

$day = static fn (int $offset, string $time = '00:00:00'): string => date('Y-m-d', strtotime("{$offset} days")) . ' ' . $time;

$text = static function (string $title, string $html, int $bg, array $groups, bool $all, int $by, ?string $start = null, ?string $end = null) use ($app, $day): int {
    return Messages::create($app, [
        'title' => $title, 'type' => Messages::TYPE_TEXT, 'text_html' => $html, 'background_id' => $bg,
        'duration_seconds' => 15, 'start_at' => $start ?? $day(-3), 'end_at' => $end,
        'all_devices' => $all, 'group_ids' => $groups, 'device_ids' => [],
    ], null, $by);
};
$image = static function (string $title, string $file, array $groups, bool $all, int $by, ?string $start = null, ?string $end = null) use ($app, $day): int {
    $media = Media::storeImage($app, '/tmp/vx-demo-posters/' . $file);
    return Messages::create($app, [
        'title' => $title, 'type' => Messages::TYPE_IMAGE, 'text_html' => '', 'background_id' => null,
        'duration_seconds' => 20, 'start_at' => $start ?? $day(-5), 'end_at' => $end,
        'all_devices' => $all, 'group_ids' => $groups, 'device_ids' => [],
    ], $media, $by);
};

// Arrière-plans : 1 Nuit, 2 Océan, 3 Forêt, 4 Aurore, 5 Braise, 6 Ardoise, 7 Soleil, 8 Papier
$mBienvenue = $text('Bienvenue', '<h1>Bienvenue au Centre communautaire des Érables</h1><p>Accueil ouvert de 8 h à 21 h, tous les jours</p>', 2, [$hall], false, $admin);
$mYoga = $image('Cours de yoga', 'yoga.png', [$gym, $hall], false, $karim);
$mSang = $image('Collecte de sang', 'sang.png', [], true, $admin, $day(-2), $day(1, '16:00:00'));
$image('Marché de Noël', 'noel.png', [], true, $admin, $day(12), $day(40, '23:59:00'));
$mConte = $text('Heure du conte', '<h1>Heure du conte</h1><p class="ql-size-large">Samedi 10 h 30 · Bibliothèque</p><p>Pour les 3 à 6 ans, avec un parent</p>', 7, [$biblio], false, $julie);
$text('Stationnement nord', '<h1>Stationnement nord fermé</h1><p class="ql-size-large">Du 12 au 14 octobre</p><p>Travaux de pavage · Utilisez celui de la rue des Érables</p>', 5, [], true, $admin, $day(-1), $day(6, '23:59:00'));
$mMenu = $text('Menu de la semaine', '<h1>Menu de la semaine</h1><p>Lundi · Soupe aux légumes et sandwich au poulet</p><p>Mardi · Pâtes à la sauce rosée</p><p>Mercredi · Chili végétarien</p><p>Jeudi · Pizza maison</p><p>Vendredi · Poisson et riz</p>', 3, [$cafe], false, $karim);
$text('Assemblée générale', '<h1>Assemblée générale annuelle</h1><p>Mercredi 17 septembre, 19 h · Salle polyvalente</p>', 6, [], true, $admin, $day(-30), $day(-14, '23:59:00'));
$text('Fête des bénévoles', '<h1>Fête des bénévoles</h1><p>Détails à venir</p>', 4, [], false, $admin, $day(0));

$online = static function (int $deviceId, int $current, string $lastSeen, string $agent) use ($db): void {
    $db->prepare('UPDATE devices SET token_hash = ?, connected_at = ?, last_seen_at = ?, current_message_id = ?, last_message_id = ?, user_agent = ?, ip = ? WHERE id = ?')
        ->execute([hash('sha256', 'demo' . $deviceId), date('Y-m-d H:i:s', strtotime('-9 days')), $lastSeen, $current, $current, $agent, '192.168.1.' . (20 + $deviceId), $deviceId]);
};
$tv = 'Mozilla/5.0 (SMART-TV; Linux; Tizen 6.0) AppleWebKit/537.36';
$pc = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0';
$online($dHall1, $mBienvenue, now(), $tv);
$online($dHall2, $mSang, now(), $tv);
$online($dCafe, $mMenu, now(), $pc);
$online($dBiblio, $mConte, now(), $tv);
$online($dGym, $mYoga, date('Y-m-d H:i:s', strtotime('-2 hours')), $tv); // hors ligne depuis 2 h
// Salle polyvalente : jamais connectée (code à entrer)

echo "Données de démonstration créées.\n";
