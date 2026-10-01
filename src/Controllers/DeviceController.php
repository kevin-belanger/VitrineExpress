<?php

declare(strict_types=1);

namespace VitrineExpress\Controllers;

use VitrineExpress\Controller;
use VitrineExpress\Devices;
use VitrineExpress\Groups;
use VitrineExpress\Messages;
use VitrineExpress\Response;

final class DeviceController extends Controller
{
    public function index(): Response
    {
        $messages = [];
        foreach (Messages::search($this->app, []) as $message) {
            $messages[(int) $message['id']] = $message;
        }
        return $this->view('devices/index', [
            'title' => 'Téléviseurs',
            'devices' => Devices::allWithGroups($this->app),
            'messages' => $messages,
            'offlineAfter' => $this->app->intSetting('offline_after', 180),
            'refresh' => 30,
        ]);
    }

    public function create(): Response
    {
        return $this->form(['name' => '', 'description' => ''], [], [], null);
    }

    public function store(): Response
    {
        $values = $this->values();
        $groupIds = input_ids('groups');
        $errors = Devices::validate($values);
        if ($errors) {
            return $this->form($values, $groupIds, $errors, null, 422);
        }
        $id = $this->app->transaction(fn () => Devices::create($this->app, $values['name'], $values['description'], $groupIds));
        flash('success', 'Téléviseur « ' . $values['name'] . ' » ajouté. Entrez son code sur le téléviseur pour le connecter.');
        return $this->redirect('/admin/devices/' . $id . '/edit');
    }

    public function edit(string $id): Response
    {
        $device = $this->findOr404('devices', (int) $id);
        return $this->form($device, Devices::groupIds($this->app, (int) $device['id']), [], (int) $device['id']);
    }

    public function update(string $id): Response
    {
        $deviceId = (int) $this->findOr404('devices', (int) $id)['id'];
        $values = $this->values();
        $groupIds = input_ids('groups');
        $errors = Devices::validate($values);
        if ($errors) {
            return $this->form($values, $groupIds, $errors, $deviceId, 422);
        }
        $this->app->transaction(fn () => Devices::update($this->app, $deviceId, $values['name'], $values['description'], $groupIds));
        flash('success', 'Téléviseur « ' . $values['name'] . ' » modifié.');
        return $this->redirect('/admin/devices');
    }

    public function delete(string $id): Response
    {
        $device = $this->findOr404('devices', (int) $id);
        $this->app->db->prepare('DELETE FROM devices WHERE id = ?')->execute([$device['id']]);
        flash('success', 'Téléviseur « ' . $device['name'] . ' » supprimé.');
        return $this->redirect('/admin/devices');
    }

    public function disconnect(string $id): Response
    {
        $device = $this->findOr404('devices', (int) $id);
        Devices::disconnect($this->app, (int) $device['id']);
        flash('success', 'Téléviseur « ' . $device['name'] . ' » déconnecté. Il reviendra à l’écran de code à sa prochaine requête.');
        return $this->redirect('/admin/devices/' . $device['id'] . '/edit');
    }

    public function regenerate(string $id): Response
    {
        $device = $this->findOr404('devices', (int) $id);
        $code = Devices::regenerateCode($this->app, (int) $device['id']);
        flash('success', 'Nouveau code pour « ' . $device['name'] . ' » : ' . $code . '. L’appareil actuel a été déconnecté.');
        return $this->redirect('/admin/devices/' . $device['id'] . '/edit');
    }

    private function values(): array
    {
        return ['name' => input('name'), 'description' => input('description')];
    }

    private function form(array $values, array $groupIds, array $errors, ?int $id, int $status = 200): Response
    {
        $device = null;
        if ($id !== null) {
            $device = $this->findOr404('devices', $id);
            $device['status'] = Devices::status($device, $this->app->intSetting('offline_after', 180));
        }
        return $this->view('devices/form', [
            'title' => $id === null ? 'Nouveau téléviseur' : 'Modifier le téléviseur',
            'values' => $values,
            'groups' => Groups::pickerItems($this->app),
            'selected' => $groupIds,
            'errors' => $errors,
            'id' => $id,
            'device' => $device,
        ], $status);
    }
}
