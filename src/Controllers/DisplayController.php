<?php

declare(strict_types=1);

namespace VitrineExpress\Controllers;

use VitrineExpress\Controller;
use VitrineExpress\Response;
use VitrineExpress\View;

/**
 * Page plein écran des téléviseurs.
 */
final class DisplayController extends Controller
{
    public function show(): Response
    {
        $config = [
            'api' => url('/api/device'),
            'heartbeat' => $this->app->intSetting('heartbeat_interval', 60),
            'emptyRetry' => 60,
            'offlineRetry' => 10,
        ];
        $response = Response::html(View::render('display', ['config' => $config], null));
        return new Response($response->body, 200, $response->headers + ['Cache-Control' => 'no-cache']);
    }
}
