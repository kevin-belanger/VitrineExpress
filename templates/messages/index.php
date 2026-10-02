<?php

use VitrineExpress\Messages;
use VitrineExpress\View;

$statusClass = [
    Messages::STATUS_ACTIVE => 'badge-success',
    Messages::STATUS_UPCOMING => 'badge-warning',
    Messages::STATUS_EXPIRED => '',
];
$now = now();
$hasFilters = $filters['group'] || $filters['device'] || $filters['status'] || $filters['all'];
?>
<div class="page-head">
    <h1>Messages</h1>
    <div class="head-actions">
        <a class="button primary" href="<?= e(url('/admin/messages/new')) ?>">Nouvelle image</a>
        <a class="button primary" href="<?= e(url('/admin/messages/new?type=text')) ?>">Nouveau texte</a>
    </div>
</div>

<form method="get" action="<?= e(url('/admin/messages')) ?>" class="filters" data-autosubmit>
    <label>
        Groupe
        <select name="group">
            <?php if ($access->isAdmin()): ?>
                <option value="">Tous les groupes</option>
            <?php else: ?>
                <option value="">Vos groupes</option>
                <option value="all" <?= $filters['all'] ? 'selected' : '' ?>>Tous les groupes</option>
            <?php endif; ?>
            <?php foreach ($groups as $groupId => $groupName): ?>
                <option value="<?= (int) $groupId ?>" <?= $filters['group'] === $groupId ? 'selected' : '' ?>><?= e($groupName) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Périphérique d’affichage
        <select name="device">
            <option value="">Tous les périphériques d’affichage</option>
            <?php foreach ($devices as $deviceId => $deviceName): ?>
                <option value="<?= (int) $deviceId ?>" <?= $filters['device'] === $deviceId ? 'selected' : '' ?>><?= e($deviceName) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        État
        <select name="status">
            <option value="">Tous</option>
            <?php foreach (Messages::FILTER_LABELS as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <noscript><button type="submit" class="button">Filtrer</button></noscript>
    <?php if ($hasFilters): ?>
        <a class="button" href="<?= e(url('/admin/messages')) ?>">Effacer les filtres</a>
    <?php endif; ?>
</form>

<?php if ($filters['device']): ?>
    <p class="hint-box">
        Messages visant <strong><?= e($devices[$filters['device']] ?? '') ?></strong>.
        Sa file actuelle est formée des messages <strong>actifs</strong> ci-dessous, dans cet ordre.
    </p>
<?php endif; ?>

<div class="table-wrap">
<table class="table">
    <thead>
    <tr>
        <th class="thumb-col"></th>
        <th>Titre</th>
        <th>Période</th>
        <th>Durée</th>
        <th>Cibles</th>
        <th>État</th>
    </tr>
    </thead>
    <tbody>
    <?php if (!$messages): ?>
        <tr><td colspan="6" class="empty">
            <?= $hasFilters ? 'Aucun message pour ces filtres.' : ($access->isAdmin() ? 'Aucun message.' : 'Aucun message dans vos groupes.') ?>
        </td></tr>
    <?php endif; ?>
    <?php foreach ($messages as $message): ?>
        <?php
        $status = Messages::status($message, $now);
        // La ligne ouvre la fiche, ou la page Diffusion pour le message d'un autre.
        $href = url('/admin/messages/' . $message['id'] . '/edit');
        ?>
        <tr class="<?= $status === Messages::STATUS_EXPIRED ? 'is-dim' : '' ?>" data-href="<?= e($href) ?>">
            <td class="thumb-col"><?= View::render('messages/_thumb', ['message' => $message, 'app' => $app], null) ?></td>
            <td>
                <strong><a class="row-link" href="<?= e($href) ?>"><?= e($message['title']) ?></a></strong><br>
                <span class="muted">
                    <?= e(Messages::TYPE_LABELS[$message['type']]) ?>
                    <?php if ($access->owns($message)): ?>
                        · par vous
                    <?php elseif ($message['author_name'] !== null): ?>
                        · par <?= e($message['author_name']) ?>
                    <?php endif; ?>
                </span>
            </td>
            <td class="nowrap">
                <?= e(format_datetime($message['start_at'])) ?><br>
                <span class="muted"><?= $message['end_at'] ? 'au ' . e(format_datetime($message['end_at'])) : 'sans fin' ?></span>
            </td>
            <td><?= (int) $message['duration_seconds'] ?> s</td>
            <td>
                <?php if ($message['all_devices']): ?>
                    <span class="badge badge-accent">Tous les périphériques d’affichage</span>
                <?php endif; ?>
                <?php foreach ($message['group_names'] as $groupName): ?>
                    <span class="badge"><?= e($groupName) ?></span>
                <?php endforeach; ?>
                <?php foreach ($message['device_names'] as $deviceName): ?>
                    <span class="badge badge-outline"><?= e($deviceName) ?></span>
                <?php endforeach; ?>
                <?php if (!$message['all_devices'] && !$message['group_names'] && !$message['device_names']): ?>
                    <span class="muted">Aucune (non affiché)</span>
                <?php endif; ?>
            </td>
            <td><span class="badge <?= $statusClass[$status] ?>"><?= e(Messages::STATUS_LABELS[$status]) ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
