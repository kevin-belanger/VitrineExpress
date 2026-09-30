<div class="page-head">
    <h1><?= e($title) ?></h1>
</div>

<form method="post" action="<?= e(url($id === null ? '/admin/groups' : '/admin/groups/' . $id)) ?>" class="card form narrow">
    <?= csrf_field() ?>
    <label>
        Nom
        <input type="text" name="name" value="<?= e($values['name']) ?>" required maxlength="100" placeholder="ex. Local 101, Comptabilité, Pavillon A">
        <?= field_error($errors, 'name') ?>
    </label>
    <label>
        Description <span class="muted">(facultatif)</span>
        <input type="text" name="description" value="<?= e($values['description']) ?>" maxlength="500">
        <?= field_error($errors, 'description') ?>
    </label>
    <fieldset>
        <legend>Téléviseurs du groupe</legend>
        <?php if (!$devices): ?>
            <p class="hint">Aucun téléviseur pour l’instant.</p>
        <?php else: ?>
            <div class="checks">
                <?php foreach ($devices as $deviceId => $deviceName): ?>
                    <label>
                        <input type="checkbox" name="devices[]" value="<?= (int) $deviceId ?>" <?= in_array($deviceId, $selected, true) ? 'checked' : '' ?>>
                        <?= e($deviceName) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </fieldset>
    <div class="form-actions">
        <button type="submit" class="button primary">Enregistrer</button>
        <a class="button" href="<?= e(url('/admin/groups')) ?>">Annuler</a>
    </div>
</form>
