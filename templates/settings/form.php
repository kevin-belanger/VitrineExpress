<div class="page-head">
    <h1>Paramètres</h1>
</div>

<form method="post" action="<?= e(url('/admin/settings')) ?>" enctype="multipart/form-data" class="card form narrow">
    <?= csrf_field() ?>

    <fieldset>
        <legend>Organisme</legend>
        <label>
            Nom <span class="muted">(affiché sur les périphériques d’affichage quand ils n’ont aucun message)</span>
            <input type="text" name="org_name" value="<?= e($values['org_name']) ?>" maxlength="100">
            <?= field_error($errors, 'org_name') ?>
        </label>
        <div class="field">
            <span class="field-label">Logo</span>
            <?= \VitrineExpress\View::render('partials/image-field', [
                'id' => 'logo',
                'name' => 'logo',
                'currentUrl' => $logoUrl,
                'currentLabel' => 'Logo actuel',
                'maxBytes' => $maxBytes,
                'maxLabel' => $maxLabel,
                'error' => $errors['logo'] ?? null,
                'removeName' => 'remove_logo',
            ], null) ?>
        </div>
    </fieldset>

    <fieldset>
        <legend>Messages</legend>
        <label>
            Durée d’affichage par défaut (secondes)
            <input type="number" name="default_duration" value="<?= e($values['default_duration']) ?>" min="3" max="3600" required>
            <?= field_error($errors, 'default_duration') ?>
        </label>
        <label>
            Taille maximale des fichiers (Mo)
            <input type="number" name="max_upload_mb" value="<?= e($values['max_upload_mb']) ?>" min="1" max="500" required>
            <?= field_error($errors, 'max_upload_mb') ?>
            <?php if ((int) $values['max_upload_mb'] > $serverLimitMb): ?>
                <p class="hint hint-warning">Votre hébergement refuse les fichiers de plus de <?= $serverLimitMb ?> Mo. Pour accepter de plus gros fichiers, demandez à votre hébergeur d’augmenter cette limite.</p>
            <?php else: ?>
                <p class="hint">Votre hébergement accepte des fichiers jusqu’à <?= $serverLimitMb ?> Mo.</p>
            <?php endif; ?>
        </label>
    </fieldset>

    <label>
        Fuseau horaire
        <select name="timezone">
            <?php foreach ($timezones as $timezone): ?>
                <option value="<?= e($timezone) ?>" <?= $timezone === $values['timezone'] ? 'selected' : '' ?>><?= e($timezone) ?></option>
            <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'timezone') ?>
    </label>

    <div class="form-actions">
        <button type="submit" class="button primary">Enregistrer</button>
    </div>
</form>

<?php // Limite de tentatives (Throttle) : adresses qui attendent, et déblocage immédiat. ?>
<section class="settings-section">
    <h2>Adresses bloquées</h2>
    <?php if (!$blocked): ?>
        <p class="muted">Aucune adresse n’attend en ce moment.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Adresse IP</th>
                <th>Pour</th>
                <th>Fin de l’attente</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($blocked as $entry): ?>
                <?php
                $reason = $entry['kind'] === \VitrineExpress\Throttle::KIND_PAIR
                    ? 'Codes de périphérique'
                    : 'Connexion' . ($entry['all'] ? ', tous les comptes' : ($entry['subjects'] ? ' : ' . implode(', ', $entry['subjects']) : ''));
                $minutes = max(1, (int) ceil((strtotime($entry['until']) - time()) / 60));
                ?>
                <tr>
                    <td><strong><?= e($entry['ip']) ?></strong></td>
                    <td><?= e($reason) ?></td>
                    <td>dans <?= $minutes ?> min</td>
                    <td class="right">
                        <form method="post" action="<?= e(url('/admin/settings/unblock')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="kind" value="<?= e($entry['kind']) ?>">
                            <input type="hidden" name="ip" value="<?= e($entry['ip']) ?>">
                            <button type="submit" class="button small">Débloquer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
