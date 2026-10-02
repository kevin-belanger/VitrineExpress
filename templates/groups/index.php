<div class="page-head">
    <h1>Groupes</h1>
    <a class="button primary" href="<?= e(url('/admin/groups/new')) ?>">Ajouter un groupe</a>
</div>

<div class="table-wrap">
<table class="table">
    <thead>
    <tr>
        <th>Nom</th>
        <th>Description</th>
        <th>Périphériques</th>
        <th>Messages</th>
    </tr>
    </thead>
    <tbody>
    <?php if (!$groups): ?>
        <tr><td colspan="4" class="empty">Aucun groupe. Créez des groupes pour vos locaux, départements, pavillons…</td></tr>
    <?php endif; ?>
    <?php foreach ($groups as $group): ?>
        <?php $href = url('/admin/groups/' . $group['id'] . '/edit'); ?>
        <tr data-href="<?= e($href) ?>">
            <td><strong><a class="row-link" href="<?= e($href) ?>"><?= e($group['name']) ?></a></strong></td>
            <td class="muted"><?= e($group['description']) ?></td>
            <td><?= (int) $group['device_count'] ?></td>
            <td><?= (int) $group['message_count'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
