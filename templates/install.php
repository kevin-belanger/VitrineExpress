<div class="install-box">
    <h1 class="brand-large">
        <img src="<?= e(asset('/assets/brand/logo-dark.png')) ?>" alt="VitrineExpress" width="324" height="56">
    </h1>
    <p class="install-intro">Bienvenue ! Encore une étape : créer le premier compte administrateur.</p>

    <section class="card">
        <h2 class="install-step">1. Vérification du serveur</h2>
        <ul class="checklist">
            <?php foreach ($checks as $check): ?>
                <li class="<?= $check['ok'] ? 'is-ok' : ($check['required'] ? 'is-error' : 'is-warning') ?>">
                    <span class="check-icon" aria-hidden="true"><?= $check['ok'] ? '✓' : ($check['required'] ? '✗' : '!') ?></span>
                    <span>
                        <strong><?= e($check['label']) ?></strong><br>
                        <span class="muted"><?= e($check['detail']) ?></span>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$ready): ?>
            <p class="field-error">Corrigez les points en rouge, puis rechargez cette page.</p>
        <?php endif; ?>
    </section>

    <form method="post" action="<?= e(url('/install')) ?>" class="card form">
        <?= csrf_field() ?>
        <h2 class="install-step">2. Votre organisme et votre compte</h2>
        <label>
            Nom de l’organisme <span class="muted">(affiché sur les périphériques d’affichage sans message ; modifiable plus tard)</span>
            <input type="text" name="org_name" value="<?= e($values['org_name']) ?>" maxlength="100" placeholder="ex. Cégep de…">
            <?= field_error($errors, 'org_name') ?>
        </label>
        <label>
            Code usager
            <input type="text" name="username" value="<?= e($values['username']) ?>" autocomplete="username" required maxlength="50" autofocus>
            <?= field_error($errors, 'username') ?>
        </label>
        <label>
            Nom affiché <span class="muted">(facultatif)</span>
            <input type="text" name="display_name" value="<?= e($values['display_name']) ?>" maxlength="100">
            <?= field_error($errors, 'display_name') ?>
        </label>
        <label>
            Mot de passe <span class="muted">(8 caractères minimum)</span>
            <input type="password" name="password" autocomplete="new-password" required>
            <?= field_error($errors, 'password') ?>
        </label>
        <label>
            Confirmation du mot de passe
            <input type="password" name="password_confirm" autocomplete="new-password" required>
            <?= field_error($errors, 'password_confirm') ?>
        </label>
        <?= field_error($errors, 'checks') ?>
        <button type="submit" class="button primary" <?= $ready ? '' : 'disabled' ?>>Installer VitrineExpress</button>
    </form>
</div>
