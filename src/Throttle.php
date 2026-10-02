<?php

declare(strict_types=1);

namespace VitrineExpress;

/**
 * Limite de tentatives, par adresse IP (règles de Kevin, décision D31).
 *
 * Après un certain nombre d'échecs consécutifs en 15 minutes, l'adresse attend 15 minutes. Le seuil est de
 * 5 échecs, sauf pour les codes de périphérique venant d'une adresse publique d'où un périphérique s'est
 * connecté récemment : 20, pour ne pas bloquer tout un organisme derrière la même adresse parce que
 * quelqu'un se trompe de code. Une réussite remet le compte à zéro ; rien n'est compté pendant le blocage.
 * Pour la connexion des comptes : 5 échecs par compte, et 20 par adresse tous comptes confondus.
 */
final class Throttle
{
    public const KIND_PAIR = 'pair';
    public const KIND_LOGIN = 'login';

    public const WINDOW_SECONDS = 900; // 15 minutes : fenêtre des échecs comptés, et durée de l'attente
    public const LIMIT_STRICT = 5;
    public const LIMIT_TRUSTED = 20;
    private const RECENT_DEVICE_SECONDS = 86400; // périphérique « récent » : vu dans les 24 dernières heures

    public function __construct(private readonly App $app)
    {
    }

    /** Secondes à attendre avant un nouvel essai (0 : permis). */
    public function retryAfter(string $kind, string $ip, string $subject = '', ?string $now = null): int
    {
        $now ??= now();
        $last = $this->lastFailureIfBlocked($kind, $ip, $subject, $now);
        return $last === null ? 0 : max(1, strtotime($last) + self::WINDOW_SECONDS - strtotime($now));
    }

    public function recordFailure(string $kind, string $ip, string $subject = '', ?string $now = null): void
    {
        $now ??= now();
        $this->app->db->prepare('DELETE FROM login_attempts WHERE attempted_at <= ?')
            ->execute([date('Y-m-d H:i:s', strtotime($now) - self::WINDOW_SECONDS)]);
        $this->app->db->prepare('INSERT INTO login_attempts (kind, ip, subject, attempted_at) VALUES (?, ?, ?, ?)')
            ->execute([$kind, $ip, $subject, $now]);
    }

    /** Réussite : les échecs consécutifs repartent à zéro. */
    public function clear(string $kind, string $ip, string $subject = ''): void
    {
        $this->app->db->prepare('DELETE FROM login_attempts WHERE kind = ? AND ip = ? AND subject = ?')
            ->execute([$kind, $ip, $subject]);
    }

    /** Seuil d'échecs pour cette adresse. */
    public function limit(string $kind, string $ip, ?string $now = null): int
    {
        if ($kind === self::KIND_PAIR && !self::isPrivate($ip) && $this->hasRecentDevice($ip, $now ?? now())) {
            return self::LIMIT_TRUSTED;
        }
        return self::LIMIT_STRICT;
    }

    /** Adresse locale (réseau privé, boucle locale, lien local) ou invalide. */
    public static function isPrivate(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    public static function message(int $retryAfter): string
    {
        $minutes = max(1, (int) ceil($retryAfter / 60));
        return 'Trop d’essais. Réessayez dans ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '') . '.';
    }

    private function hasRecentDevice(string $ip, string $now): bool
    {
        $st = $this->app->db->prepare(
            'SELECT 1 FROM devices WHERE ip = ? AND token_hash IS NOT NULL AND last_seen_at >= ? LIMIT 1'
        );
        $st->execute([$ip, date('Y-m-d H:i:s', strtotime($now) - self::RECENT_DEVICE_SECONDS)]);
        return $st->fetchColumn() !== false;
    }

    /** Date du dernier échec si l'adresse est bloquée, sinon null. */
    private function lastFailureIfBlocked(string $kind, string $ip, string $subject, string $now): ?string
    {
        $since = date('Y-m-d H:i:s', strtotime($now) - self::WINDOW_SECONDS);
        $st = $this->app->db->prepare(
            'SELECT COUNT(*) AS n, MAX(attempted_at) AS last FROM login_attempts
             WHERE kind = ? AND ip = ? AND subject = ? AND attempted_at > ?'
        );
        $st->execute([$kind, $ip, $subject, $since]);
        $own = $st->fetch();
        if ((int) $own['n'] >= $this->limit($kind, $ip, $now)) {
            return $own['last'];
        }
        if ($kind === self::KIND_LOGIN) {
            // Tous comptes confondus depuis cette adresse.
            $st = $this->app->db->prepare(
                'SELECT COUNT(*) AS n, MAX(attempted_at) AS last FROM login_attempts WHERE kind = ? AND ip = ? AND attempted_at > ?'
            );
            $st->execute([$kind, $ip, $since]);
            $all = $st->fetch();
            if ((int) $all['n'] >= self::LIMIT_TRUSTED) {
                return $all['last'];
            }
        }
        return null;
    }
}
