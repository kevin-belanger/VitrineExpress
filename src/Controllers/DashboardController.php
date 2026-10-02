<?php

declare(strict_types=1);

namespace VitrineExpress\Controllers;

use VitrineExpress\Controller;
use VitrineExpress\Dashboard;
use VitrineExpress\Response;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        // Un gestionnaire voit le résumé de son périmètre seulement.
        $access = $this->access();
        $scope = $access->scope();

        return $this->view('dashboard/index', [
            'title' => 'Tableau de bord',
            'tv' => Dashboard::devices($this->app, null, $scope['device_ids'] ?? null),
            'msg' => Dashboard::messages($this->app, null, $scope),
            'isAdmin' => $access->isAdmin(),
            'access' => $access,
            'refresh' => 30,
        ]);
    }
}
