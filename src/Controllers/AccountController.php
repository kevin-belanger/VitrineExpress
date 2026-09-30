<?php

declare(strict_types=1);

namespace VitrineExpress\Controllers;

use VitrineExpress\Controller;
use VitrineExpress\Response;
use VitrineExpress\Users;

/**
 * Changement de son propre mot de passe.
 */
final class AccountController extends Controller
{
    public function edit(): Response
    {
        return $this->view('account/password', ['title' => 'Mon mot de passe', 'errors' => []]);
    }

    public function update(): Response
    {
        $userId = (int) $this->currentUserId();
        $current = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $st = $this->app->db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([$userId]);
        $hash = (string) $st->fetchColumn();

        $errors = password_verify($current, $hash) ? [] : ['current_password' => 'Mot de passe actuel invalide.'];
        $errors += Users::validatePassword($password, (string) ($_POST['password_confirm'] ?? ''));
        if ($errors) {
            return $this->view('account/password', ['title' => 'Mon mot de passe', 'errors' => $errors], 422);
        }

        Users::setPassword($this->app, $userId, $password);
        flash('success', 'Mot de passe modifié.');
        return $this->redirect('/admin');
    }
}
