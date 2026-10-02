<?php

declare(strict_types=1);

namespace VitrineExpress\Controllers;

use VitrineExpress\Auth;
use VitrineExpress\Controller;
use VitrineExpress\Response;
use VitrineExpress\Throttle;

final class AuthController extends Controller
{
    public function home(): Response
    {
        return $this->redirect(Auth::user($this->app) ? '/admin' : '/login');
    }

    public function showLogin(): Response
    {
        if (Auth::user($this->app)) {
            return $this->redirect('/admin');
        }
        return $this->view('auth/login', ['title' => 'Connexion', 'username' => '']);
    }

    public function login(): Response
    {
        $username = input('username');
        $password = (string) ($_POST['password'] ?? '');
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $account = mb_strtolower($username);
        $throttle = new Throttle($this->app);

        $wait = $throttle->retryAfter(Throttle::KIND_LOGIN, $ip, $account);
        if ($wait > 0) {
            flash('error', Throttle::message($wait));
            return $this->view('auth/login', ['title' => 'Connexion', 'username' => $username], 429);
        }

        if ($username !== '' && $password !== '' && Auth::attempt($this->app, $username, $password)) {
            $throttle->clear(Throttle::KIND_LOGIN, $ip, $account);
            return $this->redirect('/admin');
        }

        $throttle->recordFailure(Throttle::KIND_LOGIN, $ip, $account);
        flash('error', 'Code usager ou mot de passe invalide.');
        return $this->view('auth/login', ['title' => 'Connexion', 'username' => $username], 422);
    }

    public function logout(): Response
    {
        Auth::logout();
        flash('success', 'Vous êtes déconnecté.');
        return $this->redirect('/login');
    }
}
