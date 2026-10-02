<div class="page-head">
    <h1>Utilisateurs</h1>
    <a class="button primary" href="<?= e(url('/admin/users/new')) ?>">Ajouter un utilisateur</a>
</div>

<div class="table-wrap">
<table class="table">
    <thead>
    <tr>
        <th>Code usager</th>
        <th>Nom</th>
        <th>Rôle</th>
        <th>Dernière connexion</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $row): ?>
        <?php $href = url('/admin/users/' . $row['id'] . '/edit'); ?>
        <tr data-href="<?= e($href) ?>">
            <td><strong><a class="row-link" href="<?= e($href) ?>"><?= e($row['username']) ?></a></strong></td>
            <td><?= e($row['display_name']) ?></td>
            <td>
                <?php if ($row['role'] === 'admin'): ?>
                    <span class="badge badge-accent">Administrateur</span>
                <?php elseif ($row['group_names']): ?>
                    Gestionnaire de
                    <?php foreach ($row['group_names'] as $groupName): ?>
                        <span class="badge"><?= e($groupName) ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    Gestionnaire <span class="muted">· aucun groupe</span>
                <?php endif; ?>
            </td>
            <td><?= e($row['last_login_at'] ? format_datetime($row['last_login_at']) : 'jamais') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
