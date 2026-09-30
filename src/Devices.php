<?php

declare(strict_types=1);

namespace VitrineExpress;

use RuntimeException;

/**
 * Règles et opérations sur les téléviseurs (stations d'affichage).
 */
final class Devices
{
    public const STATUS_ONLINE = 'online';
    public const STATUS_OFFLINE = 'offline';
    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_LABELS = [
        self::STATUS_ONLINE => 'En ligne',
        self::STATUS_OFFLINE => 'Hors ligne',
        self::STATUS_DISCONNECTED => 'Non connecté',
    ];

    /** Code à 5 chiffres aléatoire, sans zéro initial, absent de la base. */
    public static function generateCode(App $app): string
    {
        $st = $app->db->prepare('SELECT 1 FROM devices WHERE code = ?');
        for ($i = 0; $i < 100; $i++) {
            $code = (string) random_int(10000, 99999);
            $st->execute([$code]);
            if ($st->fetchColumn() === false) {
                return $code;
            }
        }
        throw new RuntimeException('Impossible de générer un code unique.');
    }

    /** @return array<string, string> erreurs par champ */
    public static function validate(array $values): array
    {
        $errors = [];
        $name = $values['name'] ?? '';
        if ($name === '') {
            $errors['name'] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Maximum 100 caractères.';
        }
        if (mb_strlen($values['description'] ?? '') > 500) {
            $errors['description'] = 'Maximum 500 caractères.';
        }
        return $errors;
    }

    /** @param list<int> $groupIds */
    public static function create(App $app, string $name, string $description, array $groupIds): int
    {
        $app->db->prepare('INSERT INTO devices (name, description, code, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$name, $description, self::generateCode($app), now()]);
        $id = (int) $app->db->lastInsertId();
        self::setGroups($app, $id, $groupIds);
        return $id;
    }

    /** @param list<int> $groupIds */
    public static function update(App $app, int $id, string $name, string $description, array $groupIds): void
    {
        $app->db->prepare('UPDATE devices SET name = ?, description = ? WHERE id = ?')->execute([$name, $description, $id]);
        self::setGroups($app, $id, $groupIds);
    }

    /** Remplace l'appartenance aux groupes (les identifiants inexistants sont ignorés). */
    public static function setGroups(App $app, int $deviceId, array $groupIds): void
    {
        $app->db->prepare('DELETE FROM device_groups WHERE device_id = ?')->execute([$deviceId]);
        $st = $app->db->prepare('INSERT INTO device_groups (device_id, group_id) SELECT ?, id FROM groups WHERE id = ?');
        foreach ($groupIds as $groupId) {
            $st->execute([$deviceId, $groupId]);
        }
    }

    /** @return list<int> */
    public static function groupIds(App $app, int $deviceId): array
    {
        $st = $app->db->prepare('SELECT group_id FROM device_groups WHERE device_id = ? ORDER BY group_id');
        $st->execute([$deviceId]);
        return array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Invalide le jeton : l'appareil connecté revient à l'écran de code. */
    public static function disconnect(App $app, int $id): void
    {
        $app->db->prepare('UPDATE devices SET token_hash = NULL, connected_at = NULL, current_message_id = NULL WHERE id = ?')
            ->execute([$id]);
    }

    /** Attribue un nouveau code et déconnecte l'appareil actuel. Retourne le nouveau code. */
    public static function regenerateCode(App $app, int $id): string
    {
        $code = self::generateCode($app);
        $app->db->prepare('UPDATE devices SET code = ? WHERE id = ?')->execute([$code, $id]);
        self::disconnect($app, $id);
        return $code;
    }

    public static function status(array $device, int $offlineAfterSeconds): string
    {
        if (empty($device['token_hash'])) {
            return self::STATUS_DISCONNECTED;
        }
        $lastSeen = $device['last_seen_at'] ? strtotime($device['last_seen_at']) : false;
        if ($lastSeen !== false && time() - $lastSeen < $offlineAfterSeconds) {
            return self::STATUS_ONLINE;
        }
        return self::STATUS_OFFLINE;
    }

    /**
     * Tous les téléviseurs avec la liste de leurs groupes (clé 'groups' : id => nom).
     *
     * @return list<array>
     */
    public static function allWithGroups(App $app): array
    {
        $devices = $app->db->query('SELECT * FROM devices ORDER BY name COLLATE NOCASE')->fetchAll();
        $links = $app->db->query(
            'SELECT dg.device_id, g.id, g.name FROM device_groups dg JOIN groups g ON g.id = dg.group_id ORDER BY g.name COLLATE NOCASE'
        )->fetchAll();
        $byDevice = [];
        foreach ($links as $link) {
            $byDevice[(int) $link['device_id']][(int) $link['id']] = $link['name'];
        }
        foreach ($devices as &$device) {
            $device['groups'] = $byDevice[(int) $device['id']] ?? [];
        }
        return $devices;
    }
}
