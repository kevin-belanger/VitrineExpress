<?php

declare(strict_types=1);

namespace VitrineExpress;

/**
 * Règles et opérations sur les comptes administrateurs.
 */
final class Users
{
    public const MIN_PASSWORD_LENGTH = 8;

    /**
     * Valide les champs d'un compte. Le mot de passe est obligatoire à la création seulement.
     *
     * @return array<string, string> erreurs par champ
     */
    public static function validate(App $app, array $values, string $password, string $confirm, ?int $id): array
    {
        $errors = [];
        $username = $values['username'] ?? '';

        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            $errors['username'] = 'De 3 à 50 caractères : lettres, chiffres, point, tiret ou soulignement.';
        } else {
            $st = $app->db->prepare('SELECT id FROM users WHERE username = ? AND id IS NOT ?');
            $st->execute([$username, $id]);
            if ($st->fetch() !== false) {
                $errors['username'] = 'Ce code usager est déjà utilisé.';
            }
        }

        if (mb_strlen($values['display_name'] ?? '') > 100) {
            $errors['display_name'] = 'Maximum 100 caractères.';
        }

        if ($password !== '' || $id === null) {
            $errors += self::validatePassword($password, $confirm);
        }
        return $errors;
    }

    /** @return array<string, string> */
    public static function validatePassword(string $password, string $confirm): array
    {
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return ['password' => 'Au moins ' . self::MIN_PASSWORD_LENGTH . ' caractères.'];
        }
        if ($password !== $confirm) {
            return ['password_confirm' => 'La confirmation ne correspond pas.'];
        }
        return [];
    }

    public static function create(App $app, string $username, string $password, string $displayName = ''): int
    {
        $app->db->prepare('INSERT INTO users (username, password_hash, display_name, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $displayName, now()]);
        return (int) $app->db->lastInsertId();
    }

    public static function setPassword(App $app, int $id, string $password): void
    {
        $app->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public static function count(App $app): int
    {
        return (int) $app->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }
}
