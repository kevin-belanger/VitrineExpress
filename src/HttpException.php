<?php

declare(strict_types=1);

namespace VitrineExpress;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Requête invalide.',
            403 => 'Accès refusé.',
            404 => 'Page introuvable.',
            405 => 'Méthode non permise.',
            419 => 'Le formulaire a expiré. Veuillez réessayer.',
            default => 'Erreur.',
        };
    }
}
