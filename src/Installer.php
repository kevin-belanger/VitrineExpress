<?php

declare(strict_types=1);

namespace VitrineExpress;

/**
 * Installation web (premier compte administrateur), à la manière de WordPress.
 * Disponible seulement tant qu'aucun compte n'existe.
 */
final class Installer
{
    public const REQUIRED_PHP = '8.1.0';
    private const EXTENSIONS = ['pdo_sqlite', 'fileinfo', 'mbstring', 'dom', 'gd'];

    public static function isInstalled(App $app): bool
    {
        return Users::count($app) > 0;
    }

    /**
     * Vérifications de l'environnement.
     *
     * @return list<array{label: string, ok: bool, detail: string, required: bool}>
     */
    public static function checks(App $app): array
    {
        $checks = [[
            'label' => 'PHP ' . self::REQUIRED_PHP . ' ou plus',
            'ok' => version_compare(PHP_VERSION, self::REQUIRED_PHP, '>='),
            'detail' => 'Version actuelle : ' . PHP_VERSION,
            'required' => true,
        ]];
        foreach (self::EXTENSIONS as $extension) {
            $checks[] = [
                'label' => 'Extension PHP « ' . $extension . ' »',
                'ok' => extension_loaded($extension),
                'detail' => extension_loaded($extension) ? 'Présente' : 'Absente : à activer dans la configuration PHP de l’hébergeur',
                'required' => true,
            ];
        }
        foreach (['Base de données' => dirname($app->config['db_path']), 'Fichiers téléversés' => $app->config['uploads_path']] as $label => $dir) {
            $writable = (is_dir($dir) || @mkdir($dir, 0775, true)) && is_writable($dir);
            $checks[] = [
                'label' => $label . ' : dossier accessible en écriture',
                'ok' => $writable,
                'detail' => $dir,
                'required' => true,
            ];
        }
        $uploadLimit = (string) ini_get('upload_max_filesize');
        $checks[] = [
            'label' => 'Taille maximale d’un fichier téléversé (PHP)',
            'ok' => self::iniBytes($uploadLimit) >= 20 * 1024 * 1024,
            'detail' => $uploadLimit . ' (réglage upload_max_filesize ; 20 Mo ou plus recommandé pour les images)',
            'required' => false,
        ];
        $checks[] = [
            'label' => 'Connexion sécurisée (HTTPS)',
            'ok' => is_https(),
            'detail' => is_https() ? 'Oui' : 'Non : recommandé, les mots de passe circulent sur le réseau',
            'required' => false,
        ];
        return $checks;
    }

    /** Convertit une taille de php.ini (« 2M », « 512K », « 1G ») en octets. */
    public static function iniBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;
        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public static function ready(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['required'] && !$check['ok']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Crée le premier compte et enregistre le nom de l'organisme.
     * Retourne null si un compte existe déjà (installation faite entre-temps).
     */
    public static function install(App $app, string $orgName, string $username, string $password, string $displayName): ?int
    {
        return $app->transaction(static function () use ($app, $orgName, $username, $password, $displayName): ?int {
            if (self::isInstalled($app)) {
                return null;
            }
            if ($orgName !== '') {
                $app->setSetting('org_name', $orgName);
            }
            return Users::create($app, $username, $password, $displayName);
        });
    }
}
