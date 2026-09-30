<div class="page-head">
    <h1><?= e($title) ?></h1>
</div>

<form method="post" action="<?= e(url($id === null ? '/admin/users' : '/admin/users/' . $id)) ?>" class="card form narrow">
    <?= csrf_field() ?>
    <label>
        Code usager
        <input type="text" name="username" value="<?= e($values['username']) ?>" autocomplete="off" required maxlength="50">
        <?= field_error($errors, 'username') ?>
    </label>
    <label>
        Nom affiché <span class="muted">(facultatif)</span>
        <input type="text" name="display_name" value="<?= e($values['display_name']) ?>" maxlength="100">
        <?= field_error($errors, 'display_name') ?>
    </label>
    <label>
        Mot de passe <?php if ($id !== null): ?><span class="muted">(laisser vide pour ne pas le changer)</span><?php endif; ?>
        <input type="password" name="password" autocomplete="new-password" <?= $id === null ? 'required' : '' ?>>
        <?= field_error($errors, 'password') ?>
    </label>
    <label>
        Confirmation du mot de passe
        <input type="password" name="password_confirm" autocomplete="new-password">
        <?= field_error($errors, 'password_confirm') ?>
    </label>
    <div class="form-actions">
        <button type="submit" class="button primary">Enregistrer</button>
        <a class="button" href="<?= e(url('/admin/users')) ?>">Annuler</a>
    </div>
</form>
