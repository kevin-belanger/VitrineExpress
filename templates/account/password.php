<div class="page-head">
    <h1>Mon mot de passe</h1>
</div>

<form method="post" action="<?= e(url('/admin/account')) ?>" class="card form narrow">
    <?= csrf_field() ?>
    <label>
        Mot de passe actuel
        <input type="password" name="current_password" autocomplete="current-password" required>
        <?= field_error($errors, 'current_password') ?>
    </label>
    <label>
        Nouveau mot de passe
        <input type="password" name="password" autocomplete="new-password" required>
        <?= field_error($errors, 'password') ?>
    </label>
    <label>
        Confirmation
        <input type="password" name="password_confirm" autocomplete="new-password" required>
        <?= field_error($errors, 'password_confirm') ?>
    </label>
    <div class="form-actions">
        <button type="submit" class="button primary">Changer le mot de passe</button>
        <a class="button" href="<?= e(url('/admin')) ?>">Annuler</a>
    </div>
</form>
