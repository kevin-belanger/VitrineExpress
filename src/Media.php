<?php

declare(strict_types=1);

namespace VitrineExpress;

use finfo;

/**
 * Fichiers téléversés : validation, stockage hors racine web (D3), miniatures.
 */
final class Media
{
    public const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /** Noms acceptés par la route /media/{nom}. */
    public const NAME_PATTERN = '/^[a-f0-9]{32}(\.thumb)?\.(jpg|png|webp|gif)$/';

    private const THUMB_WIDTH = 480;

    /**
     * Valide et range un fichier reçu par formulaire ($_FILES['champ']).
     *
     * @return array{path: string, mime: string}
     */
    public static function storeUploadedImage(App $app, array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new ValidationException(self::uploadErrorMessage($app, $error));
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            throw new ValidationException('Fichier reçu invalide.');
        }
        return self::storeImage($app, $tmp, true);
    }

    /**
     * Valide et range un fichier image local (déplacé s'il vient d'un téléversement, copié sinon).
     *
     * @return array{path: string, mime: string}
     */
    public static function storeImage(App $app, string $source, bool $isUpload = false): array
    {
        $maxBytes = self::maxUploadBytes($app);
        $size = (int) @filesize($source);
        if ($size <= 0) {
            throw new ValidationException('Le fichier est vide.');
        }
        if ($size > $maxBytes) {
            throw new ValidationException(self::tooLargeMessage($app));
        }

        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($source);
        $info = @getimagesize($source);
        if (!isset(self::IMAGE_TYPES[$mime]) || $info === false) {
            throw new ValidationException('Format non accepté. Utilisez une image JPG, PNG, WebP ou GIF.');
        }

        $dir = self::directory($app);
        $name = bin2hex(random_bytes(16)) . '.' . self::IMAGE_TYPES[$mime];
        $target = $dir . '/' . $name;
        $ok = $isUpload ? move_uploaded_file($source, $target) : copy($source, $target);
        if (!$ok) {
            throw new \RuntimeException('Impossible d’enregistrer le fichier dans ' . $dir);
        }
        @chmod($target, 0644);
        // Sans miniature, l'image elle-même sert de vignette : mieux qu'un dépassement de mémoire fatal.
        if (self::fitsInMemory((int) $info[0], (int) $info[1], $size)) {
            self::makeThumbnail($target, $dir . '/' . self::thumbName($name));
        }

        return ['path' => $name, 'mime' => $mime];
    }

    /** Peut-on décoder cette image avec GD sans dépasser memory_limit (environ 5 octets par pixel, plus le fichier) ? */
    private static function fitsInMemory(int $width, int $height, int $fileSize): bool
    {
        $limit = Installer::iniBytes((string) ini_get('memory_limit'));
        if ($limit <= 0) {
            return true; // pas de limite
        }
        $needed = $width * $height * 5 + $fileSize + 8 * 1024 * 1024;
        return memory_get_usage() + $needed < $limit;
    }

    public static function directory(App $app): string
    {
        $dir = $app->config['uploads_path'];
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Impossible de créer le dossier {$dir}");
        }
        return $dir;
    }

    public static function thumbName(string $name): string
    {
        return (string) preg_replace('/\.[a-z]+$/', '.thumb.jpg', $name);
    }

    /** Chemin disque d'un fichier stocké, ou null si le nom est invalide ou le fichier absent. */
    public static function file(App $app, string $name): ?string
    {
        if (!preg_match(self::NAME_PATTERN, $name)) {
            return null;
        }
        $path = $app->config['uploads_path'] . '/' . $name;
        return is_file($path) ? $path : null;
    }

    /** URL publique d'un fichier ; la miniature si demandée et disponible. */
    public static function url(App $app, ?string $name, bool $thumb = false): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }
        if ($thumb && self::file($app, self::thumbName($name)) !== null) {
            $name = self::thumbName($name);
        }
        return url('/media/' . $name);
    }

    public static function delete(App $app, ?string $name): void
    {
        if ($name === null || !preg_match(self::NAME_PATTERN, $name)) {
            return;
        }
        foreach ([$name, self::thumbName($name)] as $file) {
            $path = $app->config['uploads_path'] . '/' . $file;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public static function maxUploadBytes(App $app): int
    {
        return max(1, $app->intSetting('max_upload_mb', 20)) * 1024 * 1024;
    }

    /**
     * Taille réellement acceptée : la plus petite entre le paramètre de l'application
     * et les limites de PHP (upload_max_filesize, post_max_size).
     */
    public static function limitBytes(App $app): int
    {
        return min(
            self::maxUploadBytes($app),
            Installer::iniBytes((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX,
            Installer::iniBytes((string) ini_get('post_max_size')) ?: PHP_INT_MAX,
        );
    }

    public static function tooLargeMessage(App $app): string
    {
        return 'Fichier trop volumineux (' . self::formatBytes(self::limitBytes($app)) . ' maximum).';
    }

    public static function formatBytes(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' Mo' : max(1, (int) round($bytes / 1024)) . ' Ko';
    }

    private static function makeThumbnail(string $source, string $target): void
    {
        if (!function_exists('imagecreatefromstring')) {
            return;
        }
        $image = @imagecreatefromstring((string) file_get_contents($source));
        if ($image === false) {
            return;
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $thumbWidth = min(self::THUMB_WIDTH, $width);
        $thumbHeight = max(1, (int) round($height * $thumbWidth / $width));

        $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
        imagefill($thumb, 0, 0, imagecolorallocate($thumb, 0, 0, 0));
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
        imagejpeg($thumb, $target, 82);
        imagedestroy($thumb);
        imagedestroy($image);
    }

    private static function uploadErrorMessage(App $app, int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => self::tooLargeMessage($app),
            UPLOAD_ERR_PARTIAL => 'Le fichier n’a été reçu qu’en partie. Réessayez.',
            UPLOAD_ERR_NO_FILE => 'Choisissez une image.',
            default => 'Le fichier n’a pas pu être reçu (erreur ' . $error . ').',
        };
    }
}
