<?php

declare(strict_types=1);

namespace VitrineExpress;

/**
 * Journal d'erreurs de l'application (storage/logs/app.log), avec une rotation simple :
 * quand le fichier dépasse MAX_BYTES, il devient app.log.1 (le précédent .1 est écrasé).
 * Le journal n'occupe donc jamais plus de deux fois cette taille.
 */
final class Log
{
    public const MAX_BYTES = 1048576; // 1 Mo

    public static function append(string $path, string $line): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_file($path) && (int) @filesize($path) > self::MAX_BYTES) {
            @rename($path, $path . '.1');
        }
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }
}
