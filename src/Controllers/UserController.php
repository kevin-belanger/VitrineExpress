<?php

declare(strict_types=1);

namespace VitrineExpress\Controllers;

use VitrineExpress\Controller;
use VitrineExpress\Groups;
use VitrineExpress\HttpException;
use VitrineExpress\Response;
use VitrineExpress\Users;

final class UserController extends Controller
{
    public function index(): Response
    {
        $users = $this->app->db->query(
            'SELECT id, username, display_name, role, created_at, last_login_at FROM users ORDER BY username COLLATE NOCASE'
        )->fetchAll();
        $groupNames = [];
        foreach ($this->app->db->query(
            'SELECT ug.user_id, g.name FROM user_groups ug JOIN groups g ON g.id = ug.group_id ORDER BY g.name COLLATE NOCASE'
        )->fetchAll() as $row) {
            $groupNames[(int) $row['user_id']][] = $row['name'];
        }
        foreach ($users as &$user) {
            $user['group_names'] = $groupNames[(int) $user['id']] ?? [];
        }
        return $this->view('users/index', ['title' => 'Utilisateurs', 'users' => $users]);
    }

    public function create(): Response
    {
        return $this->form(['username' => '', 'display_name' => '', 'role' => Users::ROLE_MANAGER, 'group_ids' => []], [], null);
    }

    public function store(): Response
    {
        $values = $this->values();
        $password = (string) ($_POST['password'] ?? '');
        $errors = Users::validate($this->app, $values, $password, (string) ($_POST['password_confirm'] ?? ''), null);
        if ($errors) {
            return $this->form($values, $errors, null, 422);
        }

        $this->app->transaction(function () use ($values, $password): void {
            $id = Users::create($this->app, $values['username'], $password, $values['display_name'], $values['role']);
            Users::setGroups($this->app, $id, $values['role'] === Users::ROLE_MANAGER ? $values['group_ids'] : []);
        });
        flash('success', 'Utilisateur « ' . $values['username'] . ' » ajouté.');
        return $this->redirect('/admin/users');
    }

    public function edit(string $id): Response
    {
        $row = $this->findOr404('users', (int) $id);
        $row['group_ids'] = Users::groupIds($this->app, (int) $row['id']);
        return $this->form($row, [], (int) $row['id']);
    }

    public function update(string $id): Response
    {
        $row = $this->findOr404('users', (int) $id);
        $userId = (int) $row['id'];
        $values = $this->values();
        $password = (string) ($_POST['password'] ?? '');
        $errors = Users::validate($this->app, $values, $password, (string) ($_POST['password_confirm'] ?? ''), $userId);
        if ($userId === $this->currentUserId() && $values['role'] !== Users::ROLE_ADMIN) {
            $errors['role'] = 'Vous ne pouvez pas retirer votre propre rôle d’administrateur.';
        }
        if ($errors) {
            return $this->form($values, $errors, $userId, 422);
        }

        $this->app->transaction(function () use ($values, $password, $userId): void {
            $this->app->db->prepare('UPDATE users SET username = ?, display_name = ?, role = ? WHERE id = ?')
                ->execute([$values['username'], $values['display_name'], $values['role'], $userId]);
            Users::setGroups($this->app, $userId, $values['role'] === Users::ROLE_MANAGER ? $values['group_ids'] : []);
            if ($password !== '') {
                Users::setPassword($this->app, $userId, $password);
            }
        });
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

    private function values(): array
    {
        return [
            'username' => input('username'),
            'display_name' => input('display_name'),
            'role' => input('role') === Users::ROLE_ADMIN ? Users::ROLE_ADMIN : Users::ROLE_MANAGER,
            'group_ids' => input_ids('groups'),
        ];
    }

    private function form(array $values, array $errors, ?int $id, int $status = 200): Response
    {
        return $this->view('users/form', [
            'title' => $id === null ? 'Nouvel utilisateur' : 'Modifier l’utilisateur',
            'values' => $values,
            'errors' => $errors,
            'id' => $id,
            'savedName' => $id !== null ? $this->findOr404('users', $id)['username'] : null, // pour la confirmation de suppression
            'isSelf' => $id !== null && $id === $this->currentUserId(),
            'groups' => Groups::pickerItems($this->app),
        ], $status);
    }
}
