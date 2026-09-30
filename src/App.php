<?php

declare(strict_types=1);

namespace VitrineExpress;

use PDO;

/**
 * Point d'accès à la configuration, à la base et aux paramètres.
 */
final class App
{
    private ?array $settings = null;

    public function __construct(
        public readonly array $config,
        public readonly PDO $db,
    ) {
    }

    public static function fromConfig(array $config): self
    {
        $db = Database::connect($config['db_path']);
        Database::migrate($db, $config['root'] . '/migrations');
        $app = new self($config, $db);
        $timezone = (string) $app->setting('timezone', $config['timezone']);
        date_default_timezone_set(in_array($timezone, timezone_identifiers_list(), true) ? $timezone : $config['timezone']);
        return $app;
    }

    /**
     * Exécute $fn dans une transaction (annulée en cas d'exception) et retourne son résultat.
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function transaction(callable $fn): mixed
    {
        if ($this->db->inTransaction()) {
            return $fn();
        }
        $this->db->beginTransaction();
        try {
            $result = $fn();
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function setting(string $key, ?string $default = null): ?string
    {
        if ($this->settings === null) {
            $this->settings = $this->db->query('SELECT key, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        return $this->settings[$key] ?? $default;
    }

    public function intSetting(string $key, int $default): int
    {
        $value = $this->setting($key);
        return $value !== null && is_numeric($value) ? (int) $value : $default;
    }

    public function setSetting(string $key, string $value): void
    {
        $this->db->prepare(
            'INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT (key) DO UPDATE SET value = excluded.value'
        )->execute([$key, $value]);
        $this->settings = null;
    }
}
