<?php

declare(strict_types=1);

namespace VitrineExpress\Controllers;

use VitrineExpress\Controller;
use VitrineExpress\HttpException;
use VitrineExpress\Response;
use VitrineExpress\Users;

final class UserController extends Controller
{
    public function index(): Response
    {
        $users = $this->app->db->query(
            'SELECT id, username, display_name, created_at, last_login_at FROM users ORDER BY username'
        )->fetchAll();
        return $this->view('users/index', ['title' => 'Utilisateurs', 'users' => $users]);
    }

    public function create(): Response
    {
        return $this->form(['username' => '', 'display_name' => ''], [], null);
    }

    public function store(): Response
    {
        $values = ['username' => input('username'), 'display_name' => input('display_name')];
        $password = (string) ($_POST['password'] ?? '');
        $errors = Users::validate($this->app, $values, $password, (string) ($_POST['password_confirm'] ?? ''), null);
        if ($errors) {
            return $this->form($values, $errors, null, 422);
        }

        Users::create($this->app, $values['username'], $password, $values['display_name']);
        flash('success', 'Utilisateur « ' . $values['username'] . ' » ajouté.');
        return $this->redirect('/admin/users');
    }

    public function edit(string $id): Response
    {
        $row = $this->findOr404('users', (int) $id);
        return $this->form($row, [], (int) $row['id']);
    }

    public function update(string $id): Response
    {
        $row = $this->findOr404('users', (int) $id);
        $userId = (int) $row['id'];
        $values = ['username' => input('username'), 'display_name' => input('display_name')];
        $password = (string) ($_POST['password'] ?? '');
        $errors = Users::validate($this->app, $values, $password, (string) ($_POST['password_confirm'] ?? ''), $userId);
        if ($errors) {
            return $this->form($values, $errors, $userId, 422);
        }

        $this->app->db->prepare('UPDATE users SET username = ?, display_name = ? WHERE id = ?')
            ->execute([$values['username'], $values['display_name'], $userId]);
        if ($password !== '') {
            Users::setPassword($this->app, $userId, $password);
        }
        flash('success', 'Utilisateur « ' . $values['username'] . ' » modifié.');
        return $this->redirect('/admin/users');
    }

    public function delete(string $id): Response
    {
        $row = $this->findOr404('users', (int) $id);
        if ((int) $row['id'] === $this->currentUserId()) {
            throw new HttpException(403, 'Vous ne pouvez pas supprimer votre propre compte.');
        }
        $this->app->db->prepare('DELETE FROM users WHERE id = ?')->execute([$row['id']]);
        flash('success', 'Utilisateur « ' . $row['username'] . ' » supprimé.');
        return $this->redirect('/admin/users');
    }

    private function form(array $values, array $errors, ?int $id, int $status = 200): Response
    {
        return $this->view('users/form', [
            'title' => $id === null ? 'Nouvel utilisateur' : 'Modifier l’utilisateur',
            'values' => $values,
            'errors' => $errors,
            'id' => $id,
            'isSelf' => $id !== null && $id === $this->currentUserId(),
        ], $status);
    }
}
