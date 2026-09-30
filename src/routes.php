<?php

declare(strict_types=1);

use VitrineExpress\Controllers\AccountController;
use VitrineExpress\Controllers\AuthController;
use VitrineExpress\Controllers\DashboardController;
use VitrineExpress\Controllers\DeviceApiController;
use VitrineExpress\Controllers\DeviceController;
use VitrineExpress\Controllers\DisplayController;
use VitrineExpress\Controllers\GroupController;
use VitrineExpress\Controllers\MediaController;
use VitrineExpress\Controllers\MessageController;
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

    $r->get('/admin/messages', [MessageController::class, 'index']);
    $r->get('/admin/messages/new', [MessageController::class, 'create']);
    $r->post('/admin/messages', [MessageController::class, 'store']);
    $r->get('/admin/messages/{id}/edit', [MessageController::class, 'edit']);
    $r->post('/admin/messages/{id}', [MessageController::class, 'update']);
    $r->post('/admin/messages/{id}/delete', [MessageController::class, 'delete']);

    $r->get('/admin/devices', [DeviceController::class, 'index']);
    $r->get('/admin/devices/new', [DeviceController::class, 'create']);
    $r->post('/admin/devices', [DeviceController::class, 'store']);
    $r->get('/admin/devices/{id}/edit', [DeviceController::class, 'edit']);
    $r->post('/admin/devices/{id}', [DeviceController::class, 'update']);
    $r->post('/admin/devices/{id}/delete', [DeviceController::class, 'delete']);
    $r->post('/admin/devices/{id}/disconnect', [DeviceController::class, 'disconnect']);
    $r->post('/admin/devices/{id}/regenerate', [DeviceController::class, 'regenerate']);

    $r->get('/admin/groups', [GroupController::class, 'index']);
    $r->get('/admin/groups/new', [GroupController::class, 'create']);
    $r->post('/admin/groups', [GroupController::class, 'store']);
    $r->get('/admin/groups/{id}/edit', [GroupController::class, 'edit']);
    $r->post('/admin/groups/{id}', [GroupController::class, 'update']);
    $r->post('/admin/groups/{id}/delete', [GroupController::class, 'delete']);

    $r->get('/admin/users', [UserController::class, 'index']);
    $r->get('/admin/users/new', [UserController::class, 'create']);
    $r->post('/admin/users', [UserController::class, 'store']);
    $r->get('/admin/users/{id}/edit', [UserController::class, 'edit']);
    $r->post('/admin/users/{id}', [UserController::class, 'update']);
    $r->post('/admin/users/{id}/delete', [UserController::class, 'delete']);

    $r->get('/admin/account', [AccountController::class, 'edit']);
    $r->post('/admin/account', [AccountController::class, 'update']);

    // Page des téléviseurs et son API (authentifiée par jeton, sans session)
    $r->get('/display', [DisplayController::class, 'show']);
    $r->post('/api/device/pair', [DeviceApiController::class, 'pair']);
    $r->get('/api/device/next', [DeviceApiController::class, 'next']);
    $r->post('/api/device/heartbeat', [DeviceApiController::class, 'heartbeat']);
    $r->post('/api/device/logout', [DeviceApiController::class, 'logout']);

    // Fichiers téléversés (publics, noms aléatoires)
    $r->get('/media/{name}', [MediaController::class, 'show']);
};
