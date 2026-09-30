<?php

declare(strict_types=1);

namespace VitrineExpress\Controllers;

use VitrineExpress\Controller;
use VitrineExpress\Devices;
use VitrineExpress\Messages;
use VitrineExpress\Playlist;
use VitrineExpress\Response;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        $offlineAfter = $this->app->intSetting('offline_after', 180);
        $devices = Devices::allWithGroups($this->app);

        $messages = [];
        foreach (Messages::search($this->app, []) as $message) {
            $messages[(int) $message['id']] = $message;
        }

        $counts = array_fill_keys(array_keys(Devices::STATUS_LABELS), 0);
        foreach ($devices as &$device) {
            $device['status'] = Devices::status($device, $offlineAfter);
            $device['queue_count'] = count(Playlist::queue($this->app, (int) $device['id']));
            $currentId = (int) $device['current_message_id'];
            $device['current'] = $device['status'] === Devices::STATUS_ONLINE ? ($messages[$currentId] ?? null) : null;
            $counts[$device['status']]++;
        }
        unset($device);

        $activeMessages = count(array_filter(
            $messages,
            static fn (array $m): bool => Messages::status($m) === Messages::STATUS_ACTIVE
        ));

        return $this->view('dashboard/index', [
            'title' => 'Tableau de bord',
            'devices' => $devices,
            'counts' => $counts,
            'activeMessages' => $activeMessages,
            'refresh' => 30,
        ]);
    }
}
