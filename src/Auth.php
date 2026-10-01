<?php

declare(strict_types=1);

namespace VitrineExpress;

/**
 * Authentification des comptes de la gestion (session PHP). Les droits : voir Access.
 */
final class Auth
{
    private static ?array $user = null;

    public static function user(App $app): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!is_int($id)) {
            return null;
        }
        if (self::$user === null || self::$user['id'] !== $id) {
            $st = $app->db->prepare('SELECT id, username, display_name, role FROM users WHERE id = ?');
            $st->execute([$id]);
            $row = $st->fetch();
            if ($row === false) {
                unset($_SESSION['user_id']);
                return null;
            }
            $row['id'] = (int) $row['id'];
            self::$user = $row;
        }
        return self::$user;
    }

    public static function attempt(App $app, string $username, string $password): bool
    {
        $st = $app->db->prepare('SELECT id, password_hash FROM users WHERE username = ?');
        $st->execute([$username]);
        $row = $st->fetch();

        if ($row === false) {
            // Temps de réponse comparable, que le compte existe ou non.
            password_hash($password, PASSWORD_DEFAULT);
            return false;
        }
        if (!password_verify($password, $row['password_hash'])) {
            return false;
        }

        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            $app->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        }
        $app->db->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')->execute([now(), $row['id']]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = (int) $row['id'];
        self::$user = null;
        return true;
    }

    public static function logout(): void
    {
        self::$user = null;
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
