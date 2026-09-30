<?php

declare(strict_types=1);

namespace VitrineExpress;

use PDO;
use RuntimeException;
use Throwable;

final class Database
{
    public static function connect(string $path): PDO
    {
        if ($path !== ':memory:') {
            $dir = dirname($path);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException("Impossible de créer le dossier de la base : {$dir}");
            }
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        if ($path !== ':memory:') {
            $pdo->exec('PRAGMA journal_mode = WAL');
        }
        return $pdo;
    }

    /**
     * Applique les migrations dont le numéro dépasse PRAGMA user_version.
     * Retourne le nombre de migrations appliquées.
     */
    public static function migrate(PDO $db, string $dir): int
    {
        $current = (int) $db->query('PRAGMA user_version')->fetchColumn();
        $files = glob($dir . '/*.sql') ?: [];
        sort($files);

        $applied = 0;
        foreach ($files as $file) {
            $version = (int) basename($file);
            if ($version <= $current) {
                continue;
            }
            $db->beginTransaction();
            try {
                $db->exec((string) file_get_contents($file));
                $db->exec('PRAGMA user_version = ' . $version);
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw new RuntimeException('Migration ' . basename($file) . ' échouée : ' . $e->getMessage(), 0, $e);
            }
            $applied++;
        }
        return $applied;
    }
}
