<?php
// Configuration par défaut. Ne pas modifier ce fichier sur le serveur :
// créer plutôt config/config.local.php qui retourne un tableau des valeurs à remplacer.

declare(strict_types=1);

$root = dirname(__DIR__);

return [
    'root' => $root,
    // Chemin de la base SQLite (hors racine web).
    'db_path' => $root . '/storage/database.sqlite',
    // Dossier des fichiers téléversés (hors racine web).
    'uploads_path' => $root . '/storage/uploads',
    // Journal d'erreurs.
    'log_path' => $root . '/storage/logs/app.log',
    // Fuseau horaire utilisé avant que les paramètres soient lus.
    'timezone' => 'America/Toronto',
    // Affiche le détail des erreurs (jamais en production).
    'debug' => false,
    // Préfixe d'URL si l'application n'est pas à la racine du domaine (ex. '/vitrine'). null = détection automatique.
    'base_path' => null,
];
