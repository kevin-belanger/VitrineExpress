<?php

declare(strict_types=1);

namespace VitrineExpress;

/**
 * Résumé du tableau de bord : seulement ce qui demande de l'attention (voir decisions.md, D20).
 */
final class Dashboard
{
    /**
     * @return array{total: int, online: int, offline: list<array>, disconnected: list<array>, idle: list<array>}
     */
    public static function devices(App $app, ?string $now = null): array
    {
        $offlineAfter = $app->intSetting('offline_after', 180);
        $summary = ['total' => 0, 'online' => 0, 'offline' => [], 'disconnected' => [], 'idle' => []];

        foreach (Devices::allWithGroups($app) as $device) {
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
     * @return array{total: int, live: list<array>, reached: int, ending: list<array>, upcoming: list<array>, unbroadcast: list<array>, expired: int}
     */
    public static function messages(App $app, ?string $now = null): array
    {
        $now ??= now();
        $ending = Messages::search($app, ['status' => Messages::FILTER_ENDING], $now);
        usort($ending, static fn (array $a, array $b): int => strcmp($a['end_at'], $b['end_at']));
        $upcoming = Messages::search($app, ['status' => Messages::STATUS_UPCOMING], $now);
        usort($upcoming, static fn (array $a, array $b): int => strcmp($a['start_at'], $b['start_at']));

        // Téléviseurs qui ont au moins un message actif dans leur file.
        $st = $app->db->prepare(
            'SELECT COUNT(*) FROM devices d WHERE EXISTS (
                 SELECT 1 FROM messages m
                 WHERE ' . Messages::activeSql() . '
                   AND (m.all_devices = 1 OR EXISTS (
                        SELECT 1 FROM message_groups mg JOIN device_groups dg ON dg.group_id = mg.group_id
                        WHERE mg.message_id = m.id AND dg.device_id = d.id)))'
        );
        $st->execute(['now' => $now]);

        return [
            'total' => (int) $app->db->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
            'live' => Messages::search($app, ['status' => Messages::FILTER_LIVE], $now),
            'reached' => (int) $st->fetchColumn(),
            'ending' => $ending,
            'upcoming' => $upcoming,
            'unbroadcast' => Messages::search($app, ['status' => Messages::FILTER_UNBROADCAST], $now),
            'expired' => count(Messages::search($app, ['status' => Messages::STATUS_EXPIRED], $now)),
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
