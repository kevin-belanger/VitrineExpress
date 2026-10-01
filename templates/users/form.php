<?php
$passwordError = isset($errors['password']) || isset($errors['password_confirm']);
?>
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

    <?php if ($isSelf): ?>
        <input type="hidden" name="role" value="admin">
    <?php else: ?>
        <fieldset>
            <legend>Rôle</legend>
            <div class="target-modes">
                <label class="target-mode">
                    <input type="radio" name="role" value="manager" <?= $values['role'] === 'manager' ? 'checked' : '' ?>>
                    <span><strong>Gestionnaire de groupes</strong><small>Publie dans les groupes choisis</small></span>
                </label>
                <label class="target-mode">
                    <input type="radio" name="role" value="admin" <?= $values['role'] === 'admin' ? 'checked' : '' ?>>
                    <span><strong>Administrateur</strong><small>Accès complet</small></span>
                </label>
            </div>
            <?= field_error($errors, 'role') ?>
            <div data-show-if="role=manager">
                <?= \VitrineExpress\View::render('partials/picker', [
                    'name' => 'groups[]',
                    'items' => $groups,
                    'selected' => $values['group_ids'],
                    'noun' => 'groupes',
                    'emptyText' => 'Aucun groupe pour l’instant. <a href="' . e(url('/admin/groups/new')) . '">Créer un groupe</a>',
                ], null) ?>
            </div>
        </fieldset>
    <?php endif; ?>

    <?php if ($id === null): ?>
        <label>
            Mot de passe
            <input type="password" name="password" autocomplete="new-password" required>
            <?= field_error($errors, 'password') ?>
        </label>
        <label>
            Confirmation du mot de passe
            <input type="password" name="password_confirm" autocomplete="new-password" required>
            <?= field_error($errors, 'password_confirm') ?>
        </label>
    <?php else: ?>
        <?php // Mot de passe replié derrière un bouton (admin.js, data-reveal). Sans JavaScript, les champs restent visibles. ?>
        <div>
            <button type="button" class="button" data-reveal="password-fields" hidden>Modifier le mot de passe</button>
            <div id="password-fields" class="form"<?= $passwordError ? ' data-open' : '' ?>>
                <label>
                    Nouveau mot de passe
                    <input type="password" name="password" autocomplete="new-password">
                    <?= field_error($errors, 'password') ?>
                </label>
                <label>
                    Confirmation
                    <input type="password" name="password_confirm" autocomplete="new-password">
                    <?= field_error($errors, 'password_confirm') ?>
                </label>
                <button type="button" class="link-button reveal-cancel" data-conceal>Ne pas changer le mot de passe</button>
            </div>
        </div>
    <?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="button primary">Enregistrer</button>
        <a class="button" href="<?= e(url('/admin/users')) ?>">Annuler</a>
    </div>
</form>
