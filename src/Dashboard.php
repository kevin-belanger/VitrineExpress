<?php

declare(strict_types=1);

namespace VitrineExpress;

/**
 * Résumé du tableau de bord : seulement ce qui demande de l'attention (voir decisions.md, D20).
 */
final class Dashboard
{
    /**
     * @param list<int>|null $deviceIds périmètre d'un gestionnaire (null = tous les périphériques)
     * @return array{total: int, online: int, offline: list<array>, disconnected: list<array>, idle: list<array>}
     */
    public static function devices(App $app, ?string $now = null, ?array $deviceIds = null): array
    {
        $offlineAfter = $app->intSetting('offline_after', 180);
        $summary = ['total' => 0, 'online' => 0, 'offline' => [], 'disconnected' => [], 'idle' => []];

        foreach (Devices::allWithGroups($app) as $device) {
            if ($deviceIds !== null && !in_array((int) $device['id'], $deviceIds, true)) {
                continue;
            }
            $summary['total']++;
            $status = Devices::status($device, $offlineAfter);
            if ($status === Devices::STATUS_ONLINE) {
                $summary['online']++;
                if (!Playlist::queue($app, (int) $device['id'], $now)) {
                    $summary['idle'][] = $device;
                }
            } elseif ($status === Devices::STATUS_OFFLINE) {
                $summary['offline'][] = $device;
            } else {
                $summary['disconnected'][] = $device;
            }
        }
        // Les pannes les plus récentes d'abord.
        usort($summary['offline'], static fn (array $a, array $b): int => strcmp((string) $b['last_seen_at'], (string) $a['last_seen_at']));
        return $summary;
    }

    /**
     * @param array{user_id: int, group_ids: list<int>, device_ids: list<int>}|null $scope périmètre d'un gestionnaire
     *        (ses messages et ceux qui visent ses groupes ou ses périphériques ; null = tout)
     * @return array{total: int, live: list<array>, reached: int, ending: list<array>, upcoming: list<array>, unbroadcast: list<array>, expired: int}
     */
    public static function messages(App $app, ?string $now = null, ?array $scope = null): array
    {
        $now ??= now();
        $search = static fn (string $status): array => Messages::search($app, ['status' => $status] + ($scope !== null ? ['scope' => $scope] : []), $now);
        $ending = $search(Messages::FILTER_ENDING);
        usort($ending, static fn (array $a, array $b): int => strcmp($a['end_at'], $b['end_at']));
        $upcoming = $search(Messages::STATUS_UPCOMING);
        usort($upcoming, static fn (array $a, array $b): int => strcmp($a['start_at'], $b['start_at']));

        // Périphériques (du périmètre) qui ont au moins un message actif dans leur file
        // (même règle de ciblage que la file, appliquée à chaque périphérique d).
        $inScope = $scope !== null ? ' AND d.id IN (' . (implode(',', array_map('intval', $scope['device_ids'])) ?: '0') . ')' : '';
        $st = $app->db->prepare(
            'SELECT COUNT(*) FROM devices d WHERE EXISTS (
                 SELECT 1 FROM messages m
                 WHERE ' . Messages::activeSql() . ' AND ' . str_replace(':device', 'd.id', Messages::targetsDeviceSql()) . ')' . $inScope
        );
        $st->execute(['now' => $now]);

        return [
            'total' => $scope !== null
                ? count(Messages::search($app, ['scope' => $scope]))
                : (int) $app->db->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
            'live' => $search(Messages::FILTER_LIVE),
            'reached' => (int) $st->fetchColumn(),
            'ending' => $ending,
            'upcoming' => $upcoming,
            'unbroadcast' => $search(Messages::FILTER_UNBROADCAST),
            'expired' => count($search(Messages::STATUS_EXPIRED)),
        ];
    }

    /**
     * « A, B, C et 2 autres » à partir d'une liste de lignes.
     * $label : nom de la clé à afficher, ou fonction qui formate une ligne.
     */
    public static function names(array $rows, string|callable $label = 'name', int $max = 3): string
    {
        $format = is_string($label) ? static fn (array $row): string => (string) $row[$label] : $label;
        $names = array_map($format, array_slice($rows, 0, $max));
        $rest = count($rows) - count($names);
        $text = implode(', ', $names);
        if ($rest > 0) {
            $text .= ' et ' . $rest . ' autre' . ($rest > 1 ? 's' : '');
        }
        return $text;
    }
}
