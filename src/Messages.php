<?php

declare(strict_types=1);

namespace VitrineExpress;

use DateTimeImmutable;
use PDO;

/**
 * Règles et opérations sur les messages.
 */
final class Messages
{
    public const TYPE_IMAGE = 'image';
    public const TYPE_TEXT = 'text';

    public const TYPE_LABELS = [self::TYPE_IMAGE => 'Image', self::TYPE_TEXT => 'Texte'];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_UPCOMING = 'upcoming';
    public const STATUS_EXPIRED = 'expired';

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Actif',
        self::STATUS_UPCOMING => 'À venir',
        self::STATUS_EXPIRED => 'Expiré',
    ];

    // Filtres supplémentaires de la liste (pas des états : un message actif peut être diffusé ou non).
    public const FILTER_LIVE = 'live';
    public const FILTER_ENDING = 'ending';
    public const FILTER_UNBROADCAST = 'unbroadcast';

    public const FILTER_LABELS = [
        self::FILTER_LIVE => 'En diffusion',
        self::FILTER_ENDING => 'Se termine dans les 48 h',
        self::FILTER_UNBROADCAST => 'Non diffusé (aucun périphérique visé)',
        self::STATUS_UPCOMING => 'À venir',
        self::STATUS_EXPIRED => 'Expiré',
        self::STATUS_ACTIVE => 'Actif (diffusé ou non)',
    ];

    public const ENDING_SOON_HOURS = 48;

    public const MIN_DURATION = 3;
    public const MAX_DURATION = 3600;

    public static function status(array $message, ?string $now = null): string
    {
        $now ??= now();
        if ($message['start_at'] > $now) {
            return self::STATUS_UPCOMING;
        }
        if ($message['end_at'] !== null && $message['end_at'] < $now) {
            return self::STATUS_EXPIRED;
        }
        return self::STATUS_ACTIVE;
    }

    /** Condition SQL « message actif à :now » sur l'alias m. */
    public static function activeSql(): string
    {
        return 'm.start_at <= :now AND (m.end_at IS NULL OR m.end_at >= :now)';
    }

    /**
     * Condition SQL « message affiché sur le périphérique :device » sur l'alias m :
     * tous les périphériques, ou un groupe qui le contient, ou le périphérique lui-même.
     */
    public static function targetsDeviceSql(): string
    {
        return '(m.all_devices = 1
                 OR EXISTS (SELECT 1 FROM message_groups mg
                            JOIN device_groups dg ON dg.group_id = mg.group_id
                            WHERE mg.message_id = m.id AND dg.device_id = :device)
                 OR EXISTS (SELECT 1 FROM message_devices md
                            WHERE md.message_id = m.id AND md.device_id = :device))';
    }

    /**
     * Lit et valide les champs communs du formulaire (tout sauf le fichier image).
     *
     * @return array{0: array, 1: array<string, string>} valeurs normalisées et erreurs par champ
     */
    public static function fromForm(App $app, array $post, string $type): array
    {
        $errors = [];
        $get = static fn (string $key, string $default = ''): string => is_string($post[$key] ?? null) ? trim($post[$key]) : $default;

        $values = [
            'title' => $get('title'),
            'type' => $type,
            'start_date' => $get('start_date'),
            'start_time' => $get('start_time') ?: '00:00',
            'end_date' => $get('end_date'),
            'end_time' => $get('end_time') ?: '23:59',
            'duration_seconds' => $get('duration_seconds'),
            'all_devices' => !empty($post['all_devices']),
            'group_ids' => [],
            'device_ids' => [],
            'background_id' => (int) $get('background_id'),
            'text_html' => '',
        ];

        if ($values['title'] === '') {
            $errors['title'] = 'Le titre est obligatoire.';
        } elseif (mb_strlen($values['title']) > 150) {
            $errors['title'] = 'Maximum 150 caractères.';
        }

        $start = self::parseDateTime($values['start_date'], $values['start_time']);
        if ($start === null) {
            $errors['start'] = 'Date ou heure de début invalide.';
        }
        $end = null;
        if ($values['end_date'] !== '') {
            $end = self::parseDateTime($values['end_date'], $values['end_time']);
            if ($end === null) {
                $errors['end'] = 'Date ou heure de fin invalide.';
            } elseif ($start !== null && $end <= $start) {
                $errors['end'] = 'La fin doit être après le début.';
            }
        }
        $values['start_at'] = $start;
        $values['end_at'] = $end;

        $duration = filter_var($values['duration_seconds'], FILTER_VALIDATE_INT);
        if ($duration === false || $duration < self::MIN_DURATION || $duration > self::MAX_DURATION) {
            $errors['duration_seconds'] = 'Entre ' . self::MIN_DURATION . ' et ' . self::MAX_DURATION . ' secondes.';
        } else {
            $values['duration_seconds'] = $duration;
        }

        // Cibles : groupes et/ou périphériques. Aucune cible est permis : le message est alors
        // gardé sans être affiché (brouillon). Les identifiants inconnus sont ignorés.
        $values = array_replace($values, self::targetsFromForm($app, $post));

        if ($type === self::TYPE_TEXT) {
            $html = HtmlSanitizer::clean(is_string($post['text_html'] ?? null) ? $post['text_html'] : '');
            $values['text_html'] = $html;
            if (HtmlSanitizer::isBlank($html)) {
                $errors['text_html'] = 'Le texte est obligatoire.';
            } elseif (strlen($html) > HtmlSanitizer::MAX_LENGTH) {
                $errors['text_html'] = 'Le texte est trop long.';
            }
            $st = $app->db->prepare('SELECT 1 FROM backgrounds WHERE id = ?');
            $st->execute([$values['background_id']]);
            if ($st->fetchColumn() === false) {
                $errors['background_id'] = 'Choisissez un arrière-plan.';
            }
        }

        return [$values, $errors];
    }

    /**
     * Cibles demandées dans un formulaire. Avec « Tous les périphériques », les choix de groupes et de
     * périphériques ne s'appliquent pas ; les identifiants inconnus sont ignorés. Aucune cible = brouillon.
     *
     * @return array{all_devices: bool, group_ids: list<int>, device_ids: list<int>}
     */
    public static function targetsFromForm(App $app, array $post): array
    {
        $all = !empty($post['all_devices']);
        $ids = static fn (string $key): array => !$all && is_array($post[$key] ?? null) ? array_map('intval', $post[$key]) : [];
        return [
            'all_devices' => $all,
            'group_ids' => array_values(array_intersect(array_keys(Groups::options($app)), $ids('groups'))),
            'device_ids' => array_values(array_intersect(array_keys(Devices::options($app)), $ids('devices'))),
        ];
    }

    /** Remplace seulement les cibles d'un message (sans toucher au contenu). */
    public static function setTargets(App $app, int $id, array $targets): void
    {
        $app->db->prepare('UPDATE messages SET all_devices = ? WHERE id = ?')->execute([$targets['all_devices'] ? 1 : 0, $id]);
        self::setGroups($app, $id, $targets['group_ids']);
        self::setDevices($app, $id, $targets['device_ids']);
    }

    /** Valeurs du formulaire pour un message existant. */
    public static function toForm(App $app, array $message): array
    {
        return [
            'title' => $message['title'],
            'type' => $message['type'],
            'start_date' => substr($message['start_at'], 0, 10),
            'start_time' => substr($message['start_at'], 11, 5),
            'end_date' => $message['end_at'] !== null ? substr($message['end_at'], 0, 10) : '',
            'end_time' => $message['end_at'] !== null ? substr($message['end_at'], 11, 5) : '23:59',
            'duration_seconds' => (int) $message['duration_seconds'],
            'all_devices' => (bool) $message['all_devices'],
            'group_ids' => self::groupIds($app, (int) $message['id']),
            'device_ids' => self::deviceIds($app, (int) $message['id']),
            'background_id' => (int) $message['background_id'],
            'text_html' => (string) $message['text_html'],
        ];
    }

    /** Valeurs par défaut d'un nouveau message. */
    public static function defaults(App $app): array
    {
        $first = $app->db->query('SELECT id FROM backgrounds ORDER BY sort_order, id LIMIT 1')->fetchColumn();
        return [
            'title' => '',
            'type' => self::TYPE_IMAGE,
            'start_date' => date('Y-m-d'),
            'start_time' => '00:00',
            'end_date' => '',
            'end_time' => '23:59',
            'duration_seconds' => $app->intSetting('default_duration', 20),
            'all_devices' => false,
            'group_ids' => [],
            'device_ids' => [],
            'background_id' => (int) $first,
            'text_html' => '',
        ];
    }

    /** @param array{path: string, mime: string}|null $media */
    public static function create(App $app, array $values, ?array $media, ?int $userId): int
    {
        $now = now();
        $app->db->prepare(
            'INSERT INTO messages (title, type, media_path, media_mime, text_html, background_id, duration_seconds,
                                   start_at, end_at, all_devices, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $values['title'],
            $values['type'],
            $media['path'] ?? null,
            $media['mime'] ?? null,
            $values['type'] === self::TYPE_TEXT ? $values['text_html'] : null,
            $values['type'] === self::TYPE_TEXT ? $values['background_id'] : null,
            $values['duration_seconds'],
            $values['start_at'],
            $values['end_at'],
            $values['all_devices'] ? 1 : 0,
            $userId,
            $now,
            $now,
        ]);
        $id = (int) $app->db->lastInsertId();
        self::setGroups($app, $id, $values['group_ids']);
        self::setDevices($app, $id, $values['device_ids'] ?? []);
        return $id;
    }

    /**
     * Met à jour un message ; $media remplace l'image s'il est fourni.
     * Retourne l'ancien fichier à supprimer, s'il y a lieu.
     */
    public static function update(App $app, int $id, array $values, ?array $media): ?string
    {
        $st = $app->db->prepare('SELECT media_path FROM messages WHERE id = ?');
        $st->execute([$id]);
        $oldMedia = $st->fetchColumn() ?: null;

        $app->db->prepare(
            'UPDATE messages SET title = ?, text_html = ?, background_id = ?, duration_seconds = ?, start_at = ?, end_at = ?,
                                 all_devices = ?, updated_at = ?,
                                 media_path = COALESCE(?, media_path), media_mime = COALESCE(?, media_mime)
             WHERE id = ?'
        )->execute([
            $values['title'],
            $values['type'] === self::TYPE_TEXT ? $values['text_html'] : null,
            $values['type'] === self::TYPE_TEXT ? $values['background_id'] : null,
            $values['duration_seconds'],
            $values['start_at'],
            $values['end_at'],
            $values['all_devices'] ? 1 : 0,
            now(),
            $media['path'] ?? null,
            $media['mime'] ?? null,
            $id,
        ]);
        self::setGroups($app, $id, $values['group_ids']);
        self::setDevices($app, $id, $values['device_ids'] ?? []);

        return $media !== null && $oldMedia !== $media['path'] ? $oldMedia : null;
    }

    public static function delete(App $app, int $id): void
    {
        $st = $app->db->prepare('SELECT media_path FROM messages WHERE id = ?');
        $st->execute([$id]);
        $media = $st->fetchColumn() ?: null;
        $app->db->prepare('DELETE FROM messages WHERE id = ?')->execute([$id]);
        Media::delete($app, $media);
    }

    /**
     * Messages expirés à $now : tous, ou seulement ceux créés par $ownerId (un gestionnaire ne supprime que les siens).
     *
     * @return list<int>
     */
    public static function expiredIds(App $app, ?int $ownerId, ?string $now = null): array
    {
        $st = $app->db->prepare(
            'SELECT id FROM messages WHERE end_at IS NOT NULL AND end_at < :now'
            . ($ownerId !== null ? ' AND created_by = :owner' : '') . ' ORDER BY id'
        );
        $st->execute(['now' => $now ?? now()] + ($ownerId !== null ? ['owner' => $ownerId] : []));
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Supprime ces messages et leurs fichiers ; retourne le nombre supprimé. */
    public static function deleteMany(App $app, array $ids): int
    {
        foreach ($ids as $id) {
            self::delete($app, (int) $id);
        }
        return count($ids);
    }

    /** Texte en minuscules sans accents, pour la recherche (« Fête » → « fete »). */
    public static function searchKey(string $text): string
    {
        return strtr(mb_strtolower($text, 'UTF-8'), [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o', 'ù' => 'u', 'û' => 'u',
            'ü' => 'u', 'ú' => 'u', 'ç' => 'c', 'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
        ]);
    }

    public static function setGroups(App $app, int $messageId, array $groupIds): void
    {
        $app->db->prepare('DELETE FROM message_groups WHERE message_id = ?')->execute([$messageId]);
        $st = $app->db->prepare('INSERT INTO message_groups (message_id, group_id) SELECT ?, id FROM groups WHERE id = ?');
        foreach ($groupIds as $groupId) {
            $st->execute([$messageId, $groupId]);
        }
    }

    /** @return list<int> */
    public static function groupIds(App $app, int $messageId): array
    {
        $st = $app->db->prepare('SELECT group_id FROM message_groups WHERE message_id = ? ORDER BY group_id');
        $st->execute([$messageId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function setDevices(App $app, int $messageId, array $deviceIds): void
    {
        $app->db->prepare('DELETE FROM message_devices WHERE message_id = ?')->execute([$messageId]);
        $st = $app->db->prepare('INSERT INTO message_devices (message_id, device_id) SELECT ?, id FROM devices WHERE id = ?');
        foreach ($deviceIds as $deviceId) {
            $st->execute([$messageId, $deviceId]);
        }
    }

    /** @return list<int> */
    public static function deviceIds(App $app, int $messageId): array
    {
        $st = $app->db->prepare('SELECT device_id FROM message_devices WHERE message_id = ? ORDER BY device_id');
        $st->execute([$messageId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<int, list<string>> noms par message, à partir d'une requête (message_id, name) */
    private static function namesByMessage(App $app, string $sql): array
    {
        $byMessage = [];
        foreach ($app->db->query($sql)->fetchAll() as $row) {
            $byMessage[(int) $row['message_id']][] = $row['name'];
        }
        return $byMessage;
    }

    /** Condition SQL « le message atteint au moins un périphérique existant » sur l'alias m. */
    public static function reachesAnyDeviceSql(): string
    {
        return '((m.all_devices = 1 AND EXISTS (SELECT 1 FROM devices))
                 OR EXISTS (SELECT 1 FROM message_groups mg JOIN device_groups dg ON dg.group_id = mg.group_id
                            WHERE mg.message_id = m.id)
                 OR EXISTS (SELECT 1 FROM message_devices md WHERE md.message_id = m.id))';
    }

    /**
     * Liste filtrée des messages, dans l'ordre de la file (création), avec arrière-plan, auteur et cibles.
     * Filtres d'état : ceux de STATUS_LABELS, plus ceux de FILTER_LABELS (en diffusion, se termine bientôt, non diffusé).
     * « scope » (périmètre d'un gestionnaire) : ses propres messages, ceux qui visent ses groupes et ceux qui
     * s'affichent sur ses périphériques.
     *
     * « q » : recherche dans le titre, sans tenir compte de la casse ni des accents.
     *
     * @param array{group?: int, device?: int, status?: string, q?: string, scope?: array{user_id: int, group_ids: list<int>, device_ids: list<int>}} $filters
     */
    public static function search(App $app, array $filters, ?string $now = null): array
    {
        $where = [];
        $params = [];
        if (isset($filters['id'])) {
            $where[] = 'm.id = :id';
            $params['id'] = (int) $filters['id'];
        }
        if (isset($filters['scope'])) {
            $groupIds = implode(',', array_map('intval', $filters['scope']['group_ids'])) ?: '0';
            $deviceIds = implode(',', array_map('intval', $filters['scope']['device_ids'])) ?: '0';
            // Ses messages, ceux qui visent ses groupes, et tout ce qui s'affiche sur ses périphériques
            // (par « Tous », par un groupe qui en contient un, ou directement).
            $where[] = "(m.created_by = :scope_user
                OR (m.all_devices = 1 AND EXISTS (SELECT 1 FROM devices WHERE id IN ($deviceIds)))
                OR EXISTS (SELECT 1 FROM message_groups mg WHERE mg.message_id = m.id AND mg.group_id IN ($groupIds))
                OR EXISTS (SELECT 1 FROM message_groups mg JOIN device_groups dg ON dg.group_id = mg.group_id
                           WHERE mg.message_id = m.id AND dg.device_id IN ($deviceIds))
                OR EXISTS (SELECT 1 FROM message_devices md WHERE md.message_id = m.id AND md.device_id IN ($deviceIds)))";
            $params['scope_user'] = (int) $filters['scope']['user_id'];
        }
        if (!empty($filters['group'])) {
            $where[] = '(m.all_devices = 1 OR EXISTS (SELECT 1 FROM message_groups mg WHERE mg.message_id = m.id AND mg.group_id = :group))';
            $params['group'] = (int) $filters['group'];
        }
        if (!empty($filters['device'])) {
            $where[] = self::targetsDeviceSql();
            $params['device'] = (int) $filters['device'];
        }
        $status = $filters['status'] ?? '';
        if ($status !== '') {
            $params['now'] = $now ?? now();
            $where[] = match ($status) {
                self::STATUS_ACTIVE => self::activeSql(),
                self::STATUS_UPCOMING => 'm.start_at > :now',
                self::STATUS_EXPIRED => 'm.end_at IS NOT NULL AND m.end_at < :now',
                self::FILTER_LIVE => self::activeSql() . ' AND ' . self::reachesAnyDeviceSql(),
                self::FILTER_ENDING => self::activeSql() . ' AND ' . self::reachesAnyDeviceSql() . ' AND m.end_at <= :soon',
                self::FILTER_UNBROADCAST => self::activeSql() . ' AND NOT ' . self::reachesAnyDeviceSql(),
                default => '1 = 1',
            };
            if ($status === self::FILTER_ENDING) {
                $params['soon'] = date('Y-m-d H:i:s', strtotime($params['now']) + self::ENDING_SOON_HOURS * 3600);
            }
        }

        $sql = 'SELECT m.*, b.css_value AS background_css, b.text_color AS background_color,
                       COALESCE(NULLIF(u.display_name, \'\'), u.username) AS author_name
                FROM messages m
                LEFT JOIN backgrounds b ON b.id = m.background_id
                LEFT JOIN users u ON u.id = m.created_by'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY m.id';
        $st = $app->db->prepare($sql);
        $st->execute($params);
        $messages = $st->fetchAll();

        // Recherche par titre en PHP : le LIKE de SQLite ignore la casse des lettres ASCII seulement, pas les accents.
        if (($filters['q'] ?? '') !== '') {
            $needle = self::searchKey((string) $filters['q']);
            $messages = array_values(array_filter(
                $messages,
                static fn (array $m): bool => str_contains(self::searchKey((string) $m['title']), $needle)
            ));
        }

        $groupNames = self::namesByMessage($app,
            'SELECT mg.message_id, g.name FROM message_groups mg JOIN groups g ON g.id = mg.group_id ORDER BY g.name COLLATE NOCASE');
        $deviceNames = self::namesByMessage($app,
            'SELECT md.message_id, d.name FROM message_devices md JOIN devices d ON d.id = md.device_id ORDER BY d.name COLLATE NOCASE');
        foreach ($messages as &$message) {
            $message['group_names'] = $groupNames[(int) $message['id']] ?? [];
            $message['device_names'] = $deviceNames[(int) $message['id']] ?? [];
        }
        return $messages;
    }

    /** @return list<array> arrière-plans prédéfinis */
    public static function backgrounds(App $app): array
    {
        return $app->db->query('SELECT * FROM backgrounds ORDER BY sort_order, id')->fetchAll();
    }

    /**
     * Représentation d'un message pour l'affichage (page des télés et aperçu).
     * $message doit contenir background_css et background_color pour un message texte.
     */
    public static function toSlide(App $app, array $message): array
    {
        $slide = [
            'id' => (int) $message['id'],
            'type' => $message['type'],
            'title' => $message['title'],
            'duration' => (int) $message['duration_seconds'],
        ];
        if ($message['type'] === self::TYPE_IMAGE) {
            $slide['image'] = Media::url($app, $message['media_path']);
        } else {
            $slide['html'] = (string) $message['text_html'];
            $slide['background'] = (string) ($message['background_css'] ?? '#000000');
            $slide['color'] = (string) ($message['background_color'] ?? '#ffffff');
        }
        return $slide;
    }

    private static function parseDateTime(string $date, string $time): ?string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time);
        if ($parsed === false || $parsed->format('Y-m-d H:i') !== $date . ' ' . $time) {
            return null;
        }
        return $parsed->format('Y-m-d H:i:s');
    }
}
