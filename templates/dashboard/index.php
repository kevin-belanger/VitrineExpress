<?php

use VitrineExpress\Devices;
use VitrineExpress\View;

?>
<div class="page-head">
    <h1>Tableau de bord</h1>
    <span class="muted small">Actualisé automatiquement toutes les 30 secondes</span>
</div>

<div class="stats">
    <div class="stat stat-success"><span class="stat-value"><?= $counts[Devices::STATUS_ONLINE] ?></span><span class="stat-label">en ligne</span></div>
    <div class="stat stat-danger"><span class="stat-value"><?= $counts[Devices::STATUS_OFFLINE] ?></span><span class="stat-label">hors ligne</span></div>
    <div class="stat"><span class="stat-value"><?= $counts[Devices::STATUS_DISCONNECTED] ?></span><span class="stat-label">non connectés</span></div>
    <a class="stat stat-accent" href="<?= e(url('/admin/messages?status=active')) ?>"><span class="stat-value"><?= $activeMessages ?></span><span class="stat-label">messages actifs</span></a>
</div>

<table class="table">
    <thead>
    <tr>
        <th>Téléviseur</th>
        <th>État</th>
        <th>Dernière activité</th>
        <th>Affiche en ce moment</th>
        <th>File</th>
    </tr>
    </thead>
    <tbody>
    <?php if (!$devices): ?>
        <tr><td colspan="5" class="empty">Aucun téléviseur. <a href="<?= e(url('/admin/devices/new')) ?>">Ajouter un téléviseur</a></td></tr>
    <?php endif; ?>
    <?php foreach ($devices as $device): ?>
        <tr>
            <td>
                <a href="<?= e(url('/admin/devices/' . $device['id'] . '/edit')) ?>"><strong><?= e($device['name']) ?></strong></a><br>
                <?php foreach ($device['groups'] as $groupName): ?>
                    <span class="badge"><?= e($groupName) ?></span>
                <?php endforeach; ?>
            </td>
            <td><?= View::render('devices/_status', ['status' => $device['status']], null) ?></td>
            <td class="muted"><?= e(time_ago($device['last_seen_at'])) ?></td>
            <td>
                <?php if ($device['current']): ?>
                    <div class="current-message">
                        <?= View::render('messages/_thumb', ['message' => $device['current'], 'app' => $app], null) ?>
                        <span><?= e($device['current']['title']) ?></span>
                    </div>
                <?php elseif ($device['status'] === Devices::STATUS_ONLINE): ?>
                    <span class="muted">File vide (heure et date)</span>
                <?php else: ?>
                    <span class="muted">—</span>
                <?php endif; ?>
            </td>
            <td>
                <a href="<?= e(url('/admin/messages?status=active&device=' . $device['id'])) ?>">
                    <?= (int) $device['queue_count'] ?> message<?= $device['queue_count'] > 1 ? 's' : '' ?>
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
