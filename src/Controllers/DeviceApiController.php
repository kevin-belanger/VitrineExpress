<?php

declare(strict_types=1);

namespace VitrineExpress\Controllers;

use VitrineExpress\Controller;
use VitrineExpress\Devices;
use VitrineExpress\DeviceSession;
use VitrineExpress\HttpException;
use VitrineExpress\Messages;
use VitrineExpress\Playlist;
use VitrineExpress\Response;

/**
 * API JSON de la page d'affichage (spec, section Logique serveur).
 */
final class DeviceApiController extends Controller
{
    public function pair(): Response
    {
        $result = DeviceSession::pair(
            $this->app,
            (string) ($_POST['code'] ?? ''),
            ($_POST['force'] ?? '') === '1',
            (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        );

        return match ($result['status']) {
            DeviceSession::PAIR_UNKNOWN => Response::json(['error' => 'Code inconnu.'], 404),
            DeviceSession::PAIR_CONFLICT => Response::json([
                'error' => 'Ce téléviseur est déjà connecté sur un autre appareil.',
                'device' => ['name' => $result['device']['name']],
            ], 409),
            default => $this->paired($result['token'], $result['device']),
        };
    }

    public function next(): Response
    {
        $device = $this->device();
        $step = Playlist::advance($this->app, (int) $device['id']);
        return Response::json([
            'device' => ['name' => $device['name']],
            'current' => $step['current'] ? Messages::toSlide($this->app, $step['current']) : null,
            'next' => $step['next'] ? Messages::toSlide($this->app, $step['next']) : null,
            'count' => $step['count'],
            'org' => $this->org(),
        ]);
    }

    public function heartbeat(): Response
    {
        $device = $this->device();
        DeviceSession::touch($this->app, (int) $device['id']);
        return Response::json(['ok' => true, 'device' => ['name' => $device['name']]]);
    }

    public function logout(): Response
    {
        $device = DeviceSession::find($this->app, DeviceSession::tokenFromRequest());
        if ($device !== null) {
            Devices::disconnect($this->app, (int) $device['id']);
        }
        DeviceSession::setCookie(null);
        return Response::json(['ok' => true]);
    }

    private function paired(string $token, array $device): Response
    {
        DeviceSession::setCookie($token);
        return Response::json([
            'token' => $token,
            'device' => ['name' => $device['name']],
            'heartbeat' => $this->app->intSetting('heartbeat_interval', 60),
        ]);
    }

    /** Téléviseur authentifié par son jeton, sinon 401. */
    private function device(): array
    {
        $device = DeviceSession::find($this->app, DeviceSession::tokenFromRequest());
        if ($device === null) {
            throw new HttpException(401, 'Appareil non connecté.');
        }
        return $device;
    }

    private function org(): array
    {
        $logo = (string) $this->app->setting('logo_path', '');
        return [
            'name' => (string) $this->app->setting('org_name', ''),
            'logo' => $logo !== '' ? url('/media/' . $logo) : null,
        ];
    }
}
