<div class="page-head">
    <h1>Paramètres</h1>
</div>

<form method="post" action="<?= e(url('/admin/settings')) ?>" enctype="multipart/form-data" class="card form narrow">
    <?= csrf_field() ?>

    <fieldset>
        <legend>Organisme</legend>
        <label>
            Nom <span class="muted">(affiché sur les téléviseurs quand ils n’ont aucun message)</span>
            <input type="text" name="org_name" value="<?= e($values['org_name']) ?>" maxlength="100">
            <?= field_error($errors, 'org_name') ?>
        </label>
        <label>
            Logo <span class="muted">(facultatif, affiché avec le nom)</span>
            <?php if ($logoUrl): ?>
                <img class="logo-preview" src="<?= e($logoUrl) ?>" alt="Logo actuel">
            <?php endif; ?>
            <input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif">
            <?= field_error($errors, 'logo') ?>
        </label>
        <?php if ($logoUrl): ?>
            <label class="check-strong"><input type="checkbox" name="remove_logo" value="1"> Retirer le logo</label>
        <?php endif; ?>
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
            <p class="hint">Le serveur PHP accepte actuellement jusqu’à <?= e($serverUploadLimit) ?> par fichier (réglage <code>upload_max_filesize</code>).</p>
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
