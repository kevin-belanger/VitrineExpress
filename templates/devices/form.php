<?php

use VitrineExpress\View;

?>
<div class="page-head">
    <h1><?= e($title) ?></h1>
</div>

<div class="split">
    <form method="post" action="<?= e(url($id === null ? '/admin/devices' : '/admin/devices/' . $id)) ?>" class="card form">
        <?= csrf_field() ?>
        <label>
            Nom
            <input type="text" name="name" value="<?= e($values['name']) ?>" required maxlength="100" placeholder="ex. Télé local 101">
            <?= field_error($errors, 'name') ?>
        </label>
        <label>
            Description <span class="muted">(facultatif)</span>
            <input type="text" name="description" value="<?= e($values['description']) ?>" maxlength="500" placeholder="ex. Mur du fond, près de la porte">
            <?= field_error($errors, 'description') ?>
        </label>
        <fieldset>
            <legend>Groupes</legend>
            <?= View::render('partials/picker', [
                'name' => 'groups[]',
                'items' => $groups,
                'selected' => $selected,
                'noun' => 'groupes',
                'emptyText' => 'Aucun groupe pour l’instant. <a href="' . e(url('/admin/groups/new')) . '">Créer un groupe</a>',
            ], null) ?>
        </fieldset>
        <div class="form-actions">
            <button type="submit" class="button primary">Enregistrer</button>
            <a class="button" href="<?= e(url('/admin/devices')) ?>">Annuler</a>
        </div>
    </form>

    <?php if ($device !== null): ?>
        <aside class="card device-code">
            <p class="device-code-label">Code de connexion</p>
            <p class="device-code-value"><?= e($device['code']) ?></p>
            <p class="hint">Sur le périphérique d’affichage, ouvrez <strong><?= e((is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . url('/display')) ?></strong> et entrez ce code.</p>

            <dl class="facts">
                <dt>État</dt>
                <dd><?= View::render('devices/_status', ['status' => $device['status']], null) ?></dd>
                <dt>Dernière activité</dt>
                <dd><?= e(time_ago($device['last_seen_at'])) ?></dd>
                <?php if ($device['connected_at']): ?>
                    <dt>Connecté depuis</dt>
                    <dd><?= e(format_datetime($device['connected_at'])) ?></dd>
                <?php endif; ?>
                <?php if ($device['ip']): ?>
                    <dt>Adresse IP</dt>
                    <dd><?= e($device['ip']) ?></dd>
                <?php endif; ?>
            </dl>

            <div class="stack">
                <?php if ($device['token_hash']): ?>
                    <form method="post" action="<?= e(url('/admin/devices/' . $id . '/disconnect')) ?>"
                          data-confirm="Déconnecter ce périphérique ? Il reviendra à l’écran de code.">
                        <?= csrf_field() ?>
                        <button type="submit" class="button">Déconnecter</button>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/admin/devices/' . $id . '/regenerate')) ?>"
                      data-confirm="Générer un nouveau code ? L’appareil actuellement connecté sera déconnecté.">
                    <?= csrf_field() ?>
                    <button type="submit" class="button">Régénérer le code</button>
                </form>
                <form method="post" action="<?= e(url('/admin/devices/' . $id . '/delete')) ?>"
                      data-confirm="Supprimer le périphérique d’affichage « <?= e($device['name']) ?> » ?">
                    <?= csrf_field() ?>
                    <button type="submit" class="button danger">Supprimer</button>
                </form>
            </div>
        </aside>
    <?php endif; ?>
</div>
