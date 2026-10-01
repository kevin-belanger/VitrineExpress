<?php

declare(strict_types=1);

namespace VitrineExpress;

use PDO;

/**
 * Règles et opérations sur les groupes (à plat, sans imbrication).
 */
final class Groups
{
    /** @return array<string, string> erreurs par champ */
    public static function validate(App $app, array $values, ?int $id): array
    {
        $errors = [];
        $name = $values['name'] ?? '';
        if ($name === '') {
            $errors['name'] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Maximum 100 caractères.';
        } else {
            $st = $app->db->prepare('SELECT 1 FROM groups WHERE name = ? AND id IS NOT ?');
            $st->execute([$name, $id]);
            if ($st->fetchColumn() !== false) {
                $errors['name'] = 'Un groupe porte déjà ce nom.';
            }
        }
        if (mb_strlen($values['description'] ?? '') > 500) {
            $errors['description'] = 'Maximum 500 caractères.';
        }
        return $errors;
    }

    /** @param list<int> $deviceIds */
    public static function create(App $app, string $name, string $description, array $deviceIds): int
    {
        $app->db->prepare('INSERT INTO groups (name, description) VALUES (?, ?)')->execute([$name, $description]);
        $id = (int) $app->db->lastInsertId();
        self::setDevices($app, $id, $deviceIds);
        return $id;
    }

    /** @param list<int> $deviceIds */
    public static function update(App $app, int $id, string $name, string $description, array $deviceIds): void
    {
        $app->db->prepare('UPDATE groups SET name = ?, description = ? WHERE id = ?')->execute([$name, $description, $id]);
        self::setDevices($app, $id, $deviceIds);
    }

    public static function setDevices(App $app, int $groupId, array $deviceIds): void
    {
        $app->db->prepare('DELETE FROM device_groups WHERE group_id = ?')->execute([$groupId]);
        $st = $app->db->prepare('INSERT INTO device_groups (device_id, group_id) SELECT id, ? FROM devices WHERE id = ?');
        foreach ($deviceIds as $deviceId) {
            $st->execute([$groupId, $deviceId]);
        }
    }

    /** @return list<int> */
    public static function deviceIds(App $app, int $groupId): array
    {
        $st = $app->db->prepare('SELECT device_id FROM device_groups WHERE group_id = ? ORDER BY device_id');
        $st->execute([$groupId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Liste id => nom, par ordre alphabétique. */
    public static function options(App $app): array
    {
        return $app->db->query('SELECT id, name FROM groups ORDER BY name COLLATE NOCASE')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Éléments du sélecteur de groupes (templates/partials/picker.php) : nom, nombre de télés, télés membres.
     *
     * @return array<int, array{label: string, meta: string, devices: list<int>}>
     */
    public static function pickerItems(App $app): array
    {
        $items = [];
        foreach (self::options($app) as $id => $name) {
            $items[$id] = ['label' => $name, 'meta' => '', 'devices' => []];
        }
        foreach ($app->db->query('SELECT group_id, device_id FROM device_groups')->fetchAll() as $link) {
            $items[(int) $link['group_id']]['devices'][] = (int) $link['device_id'];
        }
        foreach ($items as &$item) {
            $n = count($item['devices']);
            $item['meta'] = $n === 0 ? 'aucun périphérique' : $n . ' périphérique' . ($n > 1 ? 's' : '');
        }
        return $items;
    }

    /** Groupes avec le nombre de téléviseurs et de messages de chacun. */
    public static function allWithCounts(App $app): array
    {
        return $app->db->query(
            'SELECT g.*,
                    (SELECT COUNT(*) FROM device_groups dg WHERE dg.group_id = g.id) AS device_count,
                    (SELECT COUNT(*) FROM message_groups mg WHERE mg.group_id = g.id) AS message_count
             FROM groups g
             ORDER BY g.name COLLATE NOCASE'
        )->fetchAll();
    }
}
