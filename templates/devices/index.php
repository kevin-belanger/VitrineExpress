<?php
// L'état et la diapositive affichée sont mis à jour toutes les 5 secondes (devices-live.js, /admin/devices/live).
?>
<div class="page-head">
    <h1>Périphériques d’affichage</h1>
    <a class="button primary" href="<?= e(url('/admin/devices/new')) ?>">Ajouter un périphérique d’affichage</a>
</div>

<div class="table-wrap">
<table class="table" data-live-url="<?= e(url('/admin/devices/live')) ?>">
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
        <?php $state = $live[(int) $device['id']]; ?>
        <tr data-device-id="<?= (int) $device['id'] ?>">
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
            <td class="live-status"><?= $state['status'] ?></td>
            <td class="live-current" data-key="<?= e($state['key']) ?>"><div class="live-slot"><?= $state['current'] ?></div></td>
            <td class="actions">
                <a class="button small" href="<?= e(url('/admin/devices/' . $device['id'] . '/edit')) ?>">Modifier</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
