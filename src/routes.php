<?php

declare(strict_types=1);

use VitrineExpress\Controllers\AccountController;
use VitrineExpress\Controllers\AuthController;
use VitrineExpress\Controllers\DashboardController;
use VitrineExpress\Controllers\UserController;
use VitrineExpress\Router;

return static function (Router $r): void {
    // Accueil et connexion des administrateurs
    $r->get('/', [AuthController::class, 'home']);
    $r->get('/login', [AuthController::class, 'showLogin']);
    $r->post('/login', [AuthController::class, 'login']);
    $r->post('/logout', [AuthController::class, 'logout']);

    // Interface de gestion (authentification exigée pour /admin)
    $r->get('/admin', [DashboardController::class, 'index']);

    $r->get('/admin/users', [UserController::class, 'index']);
    $r->get('/admin/users/new', [UserController::class, 'create']);
    $r->post('/admin/users', [UserController::class, 'store']);
    $r->get('/admin/users/{id}/edit', [UserController::class, 'edit']);
    $r->post('/admin/users/{id}', [UserController::class, 'update']);
    $r->post('/admin/users/{id}/delete', [UserController::class, 'delete']);

    $r->get('/admin/account', [AccountController::class, 'edit']);
    $r->post('/admin/account', [AccountController::class, 'update']);
};
