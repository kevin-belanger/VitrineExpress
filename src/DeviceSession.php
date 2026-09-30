<?php

declare(strict_types=1);

namespace VitrineExpress;

/**
 * Connexion des téléviseurs : jumelage par code, jeton persistant, présence.
 * Seule l'empreinte SHA-256 du jeton est stockée.
 */
final class DeviceSession
{
    public const COOKIE = 'vx_device';
    public const HEADER = 'HTTP_X_DEVICE_TOKEN';

    public const PAIR_OK = 'ok';
    public const PAIR_UNKNOWN = 'unknown';
    public const PAIR_CONFLICT = 'conflict';

    /**
     * Jumelle l'appareil au téléviseur du code. Refuse si un autre appareil est en ligne, sauf si $force.
     *
     * @return array{status: string, device: ?array, token: ?string}
     */
    public static function pair(App $app, string $code, bool $force, string $userAgent = '', string $ip = ''): array
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        $st = $app->db->prepare('SELECT * FROM devices WHERE code = ?');
        $st->execute([$code]);
        $device = $st->fetch();
        if ($code === '' || $device === false) {
            return ['status' => self::PAIR_UNKNOWN, 'device' => null, 'token' => null];
        }

        $online = Devices::status($device, $app->intSetting('offline_after', 180)) === Devices::STATUS_ONLINE;
        if ($online && !$force) {
            return ['status' => self::PAIR_CONFLICT, 'device' => $device, 'token' => null];
        }

        $token = bin2hex(random_bytes(32));
        $now = now();
        $app->db->prepare(
            'UPDATE devices SET token_hash = ?, connected_at = ?, last_seen_at = ?, current_message_id = NULL, user_agent = ?, ip = ? WHERE id = ?'
        )->execute([self::hash($token), $now, $now, mb_substr($userAgent, 0, 500), mb_substr($ip, 0, 45), $device['id']]);

        return ['status' => self::PAIR_OK, 'device' => $device, 'token' => $token];
    }

    /** Téléviseur correspondant au jeton, ou null. */
    public static function find(App $app, ?string $token): ?array
    {
        if ($token === null || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $st = $app->db->prepare('SELECT * FROM devices WHERE token_hash = ?');
        $st->execute([self::hash($token)]);
        $device = $st->fetch();
        return $device === false ? null : $device;
    }

    /** Jeton de la requête courante : en-tête X-Device-Token, sinon cookie. */
    public static function tokenFromRequest(): ?string
    {
        $token = $_SERVER[self::HEADER] ?? $_COOKIE[self::COOKIE] ?? null;
        return is_string($token) && $token !== '' ? $token : null;
    }

    public static function touch(App $app, int $deviceId): void
    {
        $app->db->prepare('UPDATE devices SET last_seen_at = ? WHERE id = ?')->execute([now(), $deviceId]);
    }

    public static function setCookie(?string $token): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie(self::COOKIE, $token ?? '', [
            'expires' => $token === null ? time() - 3600 : time() + 10 * 365 * 86400,
            'path' => base_path() === '' ? '/' : base_path() . '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
