<?php

declare(strict_types=1);

namespace VitrineExpress;

/**
 * File des messages d'un téléviseur et rotation (spec, section Logique serveur).
 * Aucune file n'est stockée : elle est recalculée à chaque demande.
 */
final class Playlist
{
    /**
     * Messages actifs visant le téléviseur, sans doublon, dans l'ordre de création.
     *
     * @return list<array>
     */
    public static function queue(App $app, int $deviceId, ?string $now = null): array
    {
        $st = $app->db->prepare(
            'SELECT m.*, b.css_value AS background_css, b.text_color AS background_color
             FROM messages m LEFT JOIN backgrounds b ON b.id = m.background_id
             WHERE ' . Messages::activeSql() . ' AND ' . Messages::targetsDeviceSql() . '
             ORDER BY m.id'
        );
        $st->execute(['now' => $now ?? now(), 'device' => $deviceId]);
        return $st->fetchAll();
    }

    /**
     * Choisit le message suivant pour le téléviseur, l'enregistre comme affiché,
     * et retourne aussi celui d'après (pour le préchargement).
     *
     * @return array{current: ?array, next: ?array, count: int}
     */
    public static function advance(App $app, int $deviceId, ?string $now = null): array
    {
        $now ??= now();
        $st = $app->db->prepare('SELECT last_message_id FROM devices WHERE id = ?');
        $st->execute([$deviceId]);
        $lastId = (int) $st->fetchColumn();

        $queue = self::queue($app, $deviceId, $now);
        $count = count($queue);
        $current = null;
        $next = null;

        if ($count > 0) {
            $index = 0;
            foreach ($queue as $i => $message) {
                if ((int) $message['id'] > $lastId) {
                    $index = $i;
                    break;
                }
            }
            $current = $queue[$index];
            $next = $count > 1 ? $queue[($index + 1) % $count] : null;
        }

        $app->db->prepare('UPDATE devices SET last_message_id = COALESCE(?, last_message_id), current_message_id = ?, last_seen_at = ? WHERE id = ?')
            ->execute([$current['id'] ?? null, $current['id'] ?? null, $now, $deviceId]);

        return ['current' => $current, 'next' => $next, 'count' => $count];
    }
}
