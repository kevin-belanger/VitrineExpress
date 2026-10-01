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
        <th class="actions">Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php if (!$groups): ?>
        <tr><td colspan="5" class="empty">Aucun groupe. Créez des groupes pour vos locaux, départements, pavillons…</td></tr>
    <?php endif; ?>
    <?php foreach ($groups as $group): ?>
        <tr>
            <td><strong><?= e($group['name']) ?></strong></td>
            <td class="muted"><?= e($group['description']) ?></td>
            <td><?= (int) $group['device_count'] ?></td>
            <td><?= (int) $group['message_count'] ?></td>
            <td class="actions">
                <a class="button small" href="<?= e(url('/admin/groups/' . $group['id'] . '/edit')) ?>">Modifier</a>
                <form method="post" action="<?= e(url('/admin/groups/' . $group['id'] . '/delete')) ?>"
                      data-confirm="Supprimer le groupe « <?= e($group['name']) ?> » ? Les périphériques d’affichage et les messages seront conservés.">
                    <?= csrf_field() ?>
                    <button type="submit" class="button small danger">Supprimer</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
