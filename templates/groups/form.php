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
        <legend>Périphériques d’affichage du groupe</legend>
        <?= \VitrineExpress\View::render('partials/picker', [
            'name' => 'devices[]',
            'items' => $devices,
            'selected' => $selected,
            'noun' => 'périphériques',
            'emptyText' => 'Aucun périphérique d’affichage pour l’instant. <a href="' . e(url('/admin/devices/new')) . '">Ajouter un périphérique d’affichage</a>',
        ], null) ?>
    </fieldset>
    <div class="form-actions">
        <button type="submit" class="button primary">Enregistrer</button>
        <a class="button" href="<?= e(url('/admin/groups')) ?>">Annuler</a>
        <?php if ($id !== null): ?>
            <button type="submit" form="delete-form" class="button danger">Supprimer</button>
        <?php endif; ?>
    </div>
</form>

<?php if ($id !== null): ?>
    <?= \VitrineExpress\View::render('partials/delete-form', [
        'action' => '/admin/groups/' . $id . '/delete',
        'confirm' => 'Supprimer le groupe « ' . $savedName . ' » ? Les périphériques d’affichage et les messages seront conservés.',
    ], null) ?>
<?php endif; ?>
