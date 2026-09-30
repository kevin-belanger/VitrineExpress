<div class="page-head">
    <h1>Utilisateurs</h1>
    <a class="button primary" href="<?= e(url('/admin/users/new')) ?>">Ajouter un utilisateur</a>
</div>

<table class="table">
    <thead>
    <tr>
        <th>Code usager</th>
        <th>Nom</th>
        <th>Dernière connexion</th>
        <th class="actions">Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $row): ?>
        <tr>
            <td><strong><?= e($row['username']) ?></strong></td>
            <td><?= e($row['display_name']) ?></td>
            <td><?= e($row['last_login_at'] ? format_datetime($row['last_login_at']) : 'jamais') ?></td>
            <td class="actions">
                <a class="button small" href="<?= e(url('/admin/users/' . $row['id'] . '/edit')) ?>">Modifier</a>
                <?php if ((int) $row['id'] !== $user['id']): ?>
                    <form method="post" action="<?= e(url('/admin/users/' . $row['id'] . '/delete')) ?>"
                          data-confirm="Supprimer l’utilisateur « <?= e($row['username']) ?> » ?">
                        <?= csrf_field() ?>
                        <button type="submit" class="button small danger">Supprimer</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
