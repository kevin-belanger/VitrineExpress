<?php

declare(strict_types=1);

namespace VitrineExpress\Controllers;

use VitrineExpress\Auth;
use VitrineExpress\Controller;
use VitrineExpress\Installer;
use VitrineExpress\Response;
use VitrineExpress\Users;

final class InstallController extends Controller
{
    public function show(): Response
    {
        if (Installer::isInstalled($this->app)) {
            return $this->redirect('/login');
        }
        return $this->form(['org_name' => '', 'username' => '', 'display_name' => ''], []);
    }

    public function store(): Response
    {
        if (Installer::isInstalled($this->app)) {
            return $this->redirect('/login');
        }

        $values = ['org_name' => input('org_name'), 'username' => input('username'), 'display_name' => input('display_name')];
        $password = (string) ($_POST['password'] ?? '');
        $errors = Users::validate($this->app, $values, $password, (string) ($_POST['password_confirm'] ?? ''), null);
        if (mb_strlen($values['org_name']) > 100) {
            $errors['org_name'] = 'Maximum 100 caractères.';
        }
        if (!Installer::ready(Installer::checks($this->app))) {
            $errors['checks'] = 'Corrigez d’abord les points en rouge.';
        }
        if ($errors) {
            return $this->form($values, $errors, 422);
        }

        if (Installer::install($this->app, $values['org_name'], $values['username'], $password, $values['display_name']) === null) {
            return $this->redirect('/login');
        }
        Auth::attempt($this->app, $values['username'], $password);
        flash('success', 'Installation terminée. Bienvenue ! Commencez par créer vos groupes et vos téléviseurs.');
        return $this->redirect('/admin');
    }

    private function form(array $values, array $errors, int $status = 200): Response
    {
        $checks = Installer::checks($this->app);
        return $this->view('install', [
            'title' => 'Installation',
            'values' => $values,
            'errors' => $errors,
            'checks' => $checks,
            'ready' => Installer::ready($checks),
        ], $status);
    }
}
