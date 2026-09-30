<?php

declare(strict_types=1);

namespace VitrineExpress\Controllers;

use PDO;
use VitrineExpress\Controller;
use VitrineExpress\Groups;
use VitrineExpress\Response;

final class GroupController extends Controller
{
    public function index(): Response
    {
        return $this->view('groups/index', ['title' => 'Groupes', 'groups' => Groups::allWithCounts($this->app)]);
    }

    public function create(): Response
    {
        return $this->form(['name' => '', 'description' => ''], [], [], null);
    }

    public function store(): Response
    {
        $values = $this->values();
        $deviceIds = input_ids('devices');
        $errors = Groups::validate($this->app, $values, null);
        if ($errors) {
            return $this->form($values, $deviceIds, $errors, null, 422);
        }
        $this->app->transaction(fn () => Groups::create($this->app, $values['name'], $values['description'], $deviceIds));
        flash('success', 'Groupe « ' . $values['name'] . ' » ajouté.');
        return $this->redirect('/admin/groups');
    }

    public function edit(string $id): Response
    {
        $group = $this->findOr404('groups', (int) $id);
        return $this->form($group, Groups::deviceIds($this->app, (int) $group['id']), [], (int) $group['id']);
    }

    public function update(string $id): Response
    {
        $groupId = (int) $this->findOr404('groups', (int) $id)['id'];
        $values = $this->values();
        $deviceIds = input_ids('devices');
        $errors = Groups::validate($this->app, $values, $groupId);
        if ($errors) {
            return $this->form($values, $deviceIds, $errors, $groupId, 422);
        }
        $this->app->transaction(fn () => Groups::update($this->app, $groupId, $values['name'], $values['description'], $deviceIds));
        flash('success', 'Groupe « ' . $values['name'] . ' » modifié.');
        return $this->redirect('/admin/groups');
    }

    public function delete(string $id): Response
    {
        $group = $this->findOr404('groups', (int) $id);
        $this->app->db->prepare('DELETE FROM groups WHERE id = ?')->execute([$group['id']]);
        flash('success', 'Groupe « ' . $group['name'] . ' » supprimé. Les téléviseurs et les messages sont conservés.');
        return $this->redirect('/admin/groups');
    }

    private function values(): array
    {
        return ['name' => input('name'), 'description' => input('description')];
    }

    private function form(array $values, array $deviceIds, array $errors, ?int $id, int $status = 200): Response
    {
        $devices = $this->app->db->query('SELECT id, name FROM devices ORDER BY name COLLATE NOCASE')->fetchAll(PDO::FETCH_KEY_PAIR);
        return $this->view('groups/form', [
            'title' => $id === null ? 'Nouveau groupe' : 'Modifier le groupe',
            'values' => $values,
            'devices' => $devices,
            'selected' => $deviceIds,
            'errors' => $errors,
            'id' => $id,
        ], $status);
    }
}
