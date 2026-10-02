<?php
// L'état et la diapositive affichée sont mis à jour toutes les 5 secondes (devices-live.js, /admin/devices/live).
// Un administrateur ouvre la fiche en cliquant sur la ligne ; un gestionnaire voit seulement les périphériques
// de ses groupes, sans code et sans fiche.
$columns = $isAdmin ? 5 : 4;
?>
<div class="page-head">
    <h1>Périphériques d’affichage</h1>
    <?php if ($isAdmin): ?>
        <a class="button primary" href="<?= e(url('/admin/devices/new')) ?>">Ajouter un périphérique d’affichage</a>
    <?php endif; ?>
</div>

<div class="table-wrap">
<table class="table" data-live-url="<?= e(url('/admin/devices/live')) ?>">
    <thead>
    <tr>
        <th>Nom</th>
        <?php if ($isAdmin): ?><th>Code</th><?php endif; ?>
        <th>Groupes</th>
        <th>État</th>
        <th>Affiche en ce moment</th>
    </tr>
    </thead>
    <tbody>
    <?php if (!$devices): ?>
        <tr><td colspan="<?= $columns ?>" class="empty">
            <?= $isAdmin ? 'Aucun périphérique d’affichage. Ajoutez-en un pour obtenir son code de connexion.' : 'Aucun périphérique d’affichage dans vos groupes.' ?>
        </td></tr>
    <?php endif; ?>
    <?php foreach ($devices as $device): ?>
        <?php
        $state = $live[(int) $device['id']];
        $href = $isAdmin ? url('/admin/devices/' . $device['id'] . '/edit') : null;
        ?>
        <tr data-device-id="<?= (int) $device['id'] ?>"<?= $href !== null ? ' data-href="' . e($href) . '"' : '' ?>>
            <td>
                <strong>
                    <?php if ($href !== null): ?>
                        <a class="row-link" href="<?= e($href) ?>"><?= e($device['name']) ?></a>
                    <?php else: ?>
                        <?= e($device['name']) ?>
                    <?php endif; ?>
                </strong>
                <?php if ($device['description'] !== ''): ?><br><span class="muted"><?= e($device['description']) ?></span><?php endif; ?>
            </td>
            <?php if ($isAdmin): ?><td><span class="code"><?= e($device['code']) ?></span></td><?php endif; ?>
            <td>
                <?php foreach ($device['groups'] as $groupName): ?>
                    <span class="badge badge-accent"><?= e($groupName) ?></span>
                <?php endforeach; ?>
                <?php if (!$device['groups']): ?><span class="muted">Aucun</span><?php endif; ?>
            </td>
            <td class="live-status"><?= $state['status'] ?></td>
            <td class="live-current" data-key="<?= e($state['key']) ?>"><div class="live-slot"><?= $state['current'] ?></div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
