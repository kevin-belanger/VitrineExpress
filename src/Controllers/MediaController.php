<?php

declare(strict_types=1);

namespace VitrineExpress\Controllers;

use VitrineExpress\Controller;
use VitrineExpress\HttpException;
use VitrineExpress\Media;
use VitrineExpress\Response;

/**
 * Sert les fichiers téléversés (D3). Accès public : les noms sont aléatoires et impossibles à deviner,
 * et les téléviseurs doivent pouvoir les charger.
 */
final class MediaController extends Controller
{
    public function show(string $name): Response
    {
        $path = Media::file($this->app, $name);
        if ($path === null) {
            throw new HttpException(404);
        }
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $mime = array_search($extension, Media::IMAGE_TYPES, true) ?: 'application/octet-stream';
        return Response::file($path, $mime, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
