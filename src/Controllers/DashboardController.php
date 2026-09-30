<?php

declare(strict_types=1);

namespace VitrineExpress\Controllers;

use VitrineExpress\Controller;
use VitrineExpress\Response;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        return $this->view('dashboard/index', ['title' => 'Tableau de bord']);
    }
}
