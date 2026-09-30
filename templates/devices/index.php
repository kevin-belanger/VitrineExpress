<?php

use VitrineExpress\Devices;
use VitrineExpress\View;

?>
<div class="page-head">
    <h1>Téléviseurs</h1>
    <a class="button primary" href="<?= e(url('/admin/devices/new')) ?>">Ajouter un téléviseur</a>
</div>

<table class="table">
    <thead>
    <tr>
        <th>Nom</th>
        <th>Code</th>
        <th>Groupes</th>
        <th>État</th>
        <th>Dernière activité</th>
        <th class="actions">Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php if (!$devices): ?>
        <tr><td colspan="6" class="empty">Aucun téléviseur. Ajoutez-en un pour obtenir son code de connexion.</td></tr>
    <?php endif; ?>
    <?php foreach ($devices as $device): ?>
        <tr>
            <td>
                <strong><?= e($device['name']) ?></strong>
                <?php if ($device['description'] !== ''): ?><br><span class="muted"><?= e($device['description']) ?></span><?php endif; ?>
            </td>
            <td><span class="code"><?= e($device['code']) ?></span></td>
            <td>
                <?php foreach ($device['groups'] as $groupName): ?>
                    <span class="badge badge-accent"><?= e($groupName) ?></span>
                <?php endforeach; ?>
                <?php if (!$device['groups']): ?><span class="muted">Aucun</span><?php endif; ?>
            </td>
            <td><?= View::render('devices/_status', ['status' => Devices::status($device, $offlineAfter)], null) ?></td>
            <td class="muted"><?= e(time_ago($device['last_seen_at'])) ?></td>
            <td class="actions">
                <a class="button small" href="<?= e(url('/admin/devices/' . $device['id'] . '/edit')) ?>">Modifier</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
