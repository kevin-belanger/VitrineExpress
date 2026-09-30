<div class="login-box">
    <h1 class="brand brand-large">Vitrine<span>Express</span></h1>
    <form method="post" action="<?= e(url('/login')) ?>" class="card form">
        <?= csrf_field() ?>
        <label>
            Code usager
            <input type="text" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus>
        </label>
        <label>
            Mot de passe
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit" class="button primary">Se connecter</button>
    </form>
    <p class="login-device">
        <a href="<?= e(url('/display')) ?>">Connexion d’un périphérique d’affichage</a>
    </p>
</div>
