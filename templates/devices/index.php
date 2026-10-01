<?php

use VitrineExpress\Devices;
use VitrineExpress\View;

?>
<div class="page-head">
    <h1>Périphériques d’affichage</h1>
    <a class="button primary" href="<?= e(url('/admin/devices/new')) ?>">Ajouter un périphérique d’affichage</a>
</div>

<table class="table">
    <thead>
    <tr>
        <th>Nom</th>
        <th>Code</th>
        <th>Groupes</th>
        <th>État</th>
        <th>Affiche en ce moment</th>
        <th class="actions">Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php if (!$devices): ?>
        <tr><td colspan="6" class="empty">Aucun périphérique d’affichage. Ajoutez-en un pour obtenir son code de connexion.</td></tr>
    <?php endif; ?>
    <?php foreach ($devices as $device): ?>
        <?php
        $status = Devices::status($device, $offlineAfter);
        $current = $status === Devices::STATUS_ONLINE ? ($messages[(int) $device['current_message_id']] ?? null) : null;
        ?>
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
            <td>
                <?= View::render('devices/_status', ['status' => $status], null) ?><br>
                <span class="muted small"><?= e(time_ago($device['last_seen_at'])) ?></span>
            </td>
            <td>
                <?php if ($current): ?>
                    <div class="current-message">
                        <?= View::render('messages/_thumb', ['message' => $current, 'app' => $app], null) ?>
                        <span><?= e($current['title']) ?></span>
                    </div>
                <?php elseif ($status === Devices::STATUS_ONLINE): ?>
                    <span class="muted">L’heure et la date (aucun message)</span>
                <?php else: ?>
                    <span class="muted">—</span>
                <?php endif; ?>
            </td>
            <td class="actions">
                <a class="button small" href="<?= e(url('/admin/devices/' . $device['id'] . '/edit')) ?>">Modifier</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
