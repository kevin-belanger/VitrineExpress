<?php

declare(strict_types=1);

namespace VitrineExpress;

use PDO;

/**
 * Droits d'un compte (docs/specification-gestionnaires.md). Toutes les règles sont ici.
 *
 * Administrateur : tout. Gestionnaire de groupes : son « périmètre » = ses groupes + les périphériques de ces
 * groupes ; il crée des messages dans ce périmètre, modifie et supprime les siens, et sur les messages des
 * autres ne peut qu'ajouter ou retirer des cibles de son périmètre.
 */
final class Access
{
    /** Pages accessibles à un gestionnaire (le reste de /admin est réservé aux administrateurs). */
    private const MANAGER_PATHS = ['/admin', '/admin/devices', '/admin/devices/live', '/admin/account'];
    private const MANAGER_PREFIXES = ['/admin/messages'];

    /** @var list<int>|null */
    private ?array $groupIds = null;
    /** @var list<int>|null */
    private ?array $deviceIds = null;

    public function __construct(private readonly App $app, public readonly array $user)
    {
    }

    /** Droits du compte connecté. */
    public static function current(App $app): self
    {
        $user = Auth::user($app);
        if ($user === null) {
            throw new HttpException(403);
        }
        return new self($app, $user);
    }

    public function isAdmin(): bool
    {
        return ($this->user['role'] ?? Users::ROLE_ADMIN) === Users::ROLE_ADMIN;
    }

    public function userId(): int
    {
        return (int) $this->user['id'];
    }

    /** La page /admin… est-elle accessible ? */
    public function canVisit(string $path): bool
    {
        if ($this->isAdmin() || in_array($path, self::MANAGER_PATHS, true)) {
            return true;
        }
        foreach (self::MANAGER_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }
        return false;
    }

    // ---------- Périmètre ----------

    /** @return list<int> groupes du périmètre (tous pour un administrateur) */
    public function groupIds(): array
    {
        if ($this->groupIds === null) {
            $this->groupIds = $this->isAdmin()
                ? array_map('intval', $this->app->db->query('SELECT id FROM groups ORDER BY id')->fetchAll(PDO::FETCH_COLUMN))
                : Users::groupIds($this->app, $this->userId());
        }
        return $this->groupIds;
    }

    /** @return list<int> périphériques du périmètre (tous pour un administrateur) */
    public function deviceIds(): array
    {
        if ($this->deviceIds === null) {
            if ($this->isAdmin()) {
                $ids = $this->app->db->query('SELECT id FROM devices ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $st = $this->app->db->prepare(
                    'SELECT DISTINCT dg.device_id FROM device_groups dg JOIN user_groups ug ON ug.group_id = dg.group_id
                     WHERE ug.user_id = ? ORDER BY dg.device_id'
                );
                $st->execute([$this->userId()]);
                $ids = $st->fetchAll(PDO::FETCH_COLUMN);
            }
            $this->deviceIds = array_map('intval', $ids);
        }
        return $this->deviceIds;
    }

    /**
     * Périmètre pour Messages::search et le tableau de bord (null pour un administrateur : tout).
     *
     * @return array{user_id: int, group_ids: list<int>, device_ids: list<int>}|null
     */
    public function scope(): ?array
    {
        return $this->isAdmin()
            ? null
            : ['user_id' => $this->userId(), 'group_ids' => $this->groupIds(), 'device_ids' => $this->deviceIds()];
    }

    // ---------- Messages ----------

    public function owns(array $message): bool
    {
        return (int) ($message['created_by'] ?? 0) === $this->userId();
    }

    /** Contenu, dates, durée — et suppression. */
    public function canEditContent(array $message): bool
    {
        return $this->isAdmin() || $this->owns($message);
    }

    public function canDelete(array $message): bool
    {
        return $this->canEditContent($message);
    }

    /** Ajouter ou retirer des cibles (celles de son périmètre pour un gestionnaire). */
    public function canEditTargets(array $message): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        if ($message['all_devices']) {
            return false; // « Tous les périphériques » : réservé aux administrateurs
        }
        return $this->owns($message) || $this->groupIds() !== [] || $this->deviceIds() !== [];
    }

    public function canTargetAll(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Cibles à enregistrer. Administrateur : celles demandées. Gestionnaire : les cibles hors de son périmètre
     * restent telles quelles, seules celles de son périmètre suivent la demande ; jamais « Tous ».
     *
     * @param array|null $message message existant, ou null à la création
     * @return array{all_devices: bool, group_ids: list<int>, device_ids: list<int>}
     */
    public function mergeTargets(?array $message, bool $all, array $groupIds, array $deviceIds): array
    {
        if ($this->isAdmin()) {
            return ['all_devices' => $all, 'group_ids' => $all ? [] : $groupIds, 'device_ids' => $all ? [] : $deviceIds];
        }
        $existingGroups = $message !== null ? Messages::groupIds($this->app, (int) $message['id']) : [];
        $existingDevices = $message !== null ? Messages::deviceIds($this->app, (int) $message['id']) : [];
        if ($message !== null && !$this->canEditTargets($message)) {
            return ['all_devices' => (bool) $message['all_devices'], 'group_ids' => $existingGroups, 'device_ids' => $existingDevices];
        }
        $keep = static fn (array $existing, array $requested, array $scope): array => self::sorted(array_merge(
            array_diff($existing, $scope),
            array_intersect($requested, $scope)
        ));
        return [
            'all_devices' => false,
            'group_ids' => $keep($existingGroups, $groupIds, $this->groupIds()),
            'device_ids' => $keep($existingDevices, $deviceIds, $this->deviceIds()),
        ];
    }

    /**
     * Cibles du message hors du périmètre (pour « Aussi affiché dans … »), et périphériques qu'elles atteignent.
     *
     * @return array{names: list<string>, device_ids: list<int>}
     */
    public function otherTargets(array $message): array
    {
        if ($this->isAdmin()) {
            return ['names' => [], 'device_ids' => []]; // rien n'est hors de son périmètre
        }
        if ($message['all_devices']) {
            $all = array_map('intval', $this->app->db->query('SELECT id FROM devices')->fetchAll(PDO::FETCH_COLUMN));
            return ['names' => ['tous les périphériques d’affichage'], 'device_ids' => $all];
        }
        $groups = array_diff(Messages::groupIds($this->app, (int) $message['id']), $this->groupIds());
        $devices = array_diff(Messages::deviceIds($this->app, (int) $message['id']), $this->deviceIds());
        if (!$groups && !$devices) {
            return ['names' => [], 'device_ids' => []];
        }
        $names = [];
        $reached = $devices;
        $groupNames = Groups::options($this->app);
        foreach ($groups as $groupId) {
            $names[] = $groupNames[$groupId] ?? '';
            $st = $this->app->db->prepare('SELECT device_id FROM device_groups WHERE group_id = ?');
            $st->execute([$groupId]);
            $reached = array_merge($reached, array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
        }
        $deviceNames = Devices::options($this->app);
        foreach ($devices as $deviceId) {
            $names[] = $deviceNames[$deviceId] ?? '';
        }
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        return ['names' => array_values(array_filter($names)), 'device_ids' => self::sorted($reached)];
    }

    /** @return list<int> */
    private static function sorted(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        return $ids;
    }
}
