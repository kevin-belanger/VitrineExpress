# Installation et mise à jour

## Exigences

- PHP 8.1 ou plus, avec les extensions `pdo_sqlite`, `fileinfo`, `mbstring`, `dom` et `gd`
- Un serveur web (Apache ou Nginx) avec PHP-FPM ou mod_php
- HTTPS fortement recommandé (les mots de passe et les jetons des téléviseurs circulent sur le réseau)
- Aucune base de données à installer : SQLite est un simple fichier

Vérifier les extensions :

```bash
php -m | grep -E 'pdo_sqlite|fileinfo|mbstring|dom|gd'
```

## Installation

1. Placer le projet sur le serveur, **hors** de la racine web publique si possible :

   ```bash
   git clone https://github.com/kevin-belanger/VitrineExpress.git /var/www/vitrineexpress
   ```

2. Donner au serveur web le droit d'écrire dans `storage/` (base, fichiers téléversés, journal) :

   ```bash
   sudo chown -R www-data:www-data /var/www/vitrineexpress/storage
   ```

3. Configurer le serveur web pour que la **racine web soit le dossier `public/`** (exemples plus bas).

4. **Ouvrir le site dans un navigateur.** Tant qu'aucun compte n'existe, il affiche la page d'installation : vérification du serveur (version de PHP, extensions, droits d'écriture), puis création du premier compte administrateur. On est ensuite connecté directement.

   > Faites cette étape tout de suite après la mise en ligne : tant qu'elle n'est pas faite, la première personne qui ouvre le site peut créer le compte administrateur. Une fois un compte créé, la page d'installation n'est plus accessible.

   Variante en ligne de commande (SSH ou Terminal de cPanel), équivalente :

   ```bash
   cd /var/www/vitrineexpress
   sudo -u www-data php bin/install.php
   ```

5. Dans **Paramètres** : logo, fuseau horaire, durée par défaut.

## Configuration locale (facultatif)

Créer `config/config.local.php` pour remplacer des valeurs de `config/config.php` :

```php
<?php
return [
    // Base ailleurs que dans storage/ (ex. disque de données)
    'db_path' => '/srv/data/vitrineexpress.sqlite',
    // Application installée dans un sous-dossier : https://exemple.ca/vitrine
    'base_path' => '/vitrine',
];
```

Ne jamais activer `'debug' => true` en production.

## Limites de téléversement

La taille maximale des images se règle dans **Paramètres** (20 Mo par défaut), mais PHP a ses propres limites. Les ajuster dans `php.ini` (ou la configuration du pool PHP-FPM) à au moins cette valeur :

```ini
upload_max_filesize = 25M
post_max_size = 30M
```

L'écran Paramètres affiche la limite actuelle de PHP.

## Apache

```apache
<VirtualHost *:443>
    ServerName affichage.exemple.ca
    DocumentRoot /var/www/vitrineexpress/public

    <Directory /var/www/vitrineexpress/public>
        AllowOverride All
        Require all granted
    </Directory>

    # SSLEngine on, certificats, etc.
</VirtualHost>
```

`mod_rewrite` doit être activé (`sudo a2enmod rewrite`). Le fichier `public/.htaccess` envoie toutes les requêtes vers `index.php`.

Sur un hébergement où la racine web ne peut pas être changée (ex. le domaine principal sur cPanel, qui pointe sur `public_html`), on peut cloner le projet directement dans ce dossier : le `.htaccess` à la racine du projet renvoie tout vers `public/` et empêche l'accès au reste (base, configuration, code). Les adresses restent propres (`/admin`, sans `/public`) ; une ancienne adresse contenant `/public` est redirigée vers la bonne.

## Nginx

```nginx
server {
    listen 443 ssl;
    server_name affichage.exemple.ca;
    root /var/www/vitrineexpress/public;
    index index.php;

    client_max_body_size 30m;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

## Connecter un téléviseur

1. Dans **Téléviseurs**, ajouter le téléviseur et noter son code à 5 chiffres.
2. Sur le téléviseur, ouvrir `https://affichage.exemple.ca/display` (ou la page de connexion, lien « Connexion d’un périphérique d’affichage »).
3. Entrer le code. Le téléviseur reste connecté, même après un redémarrage.
4. Bouger la souris (si le téléviseur en a une) fait apparaître un menu : plein écran, déconnexion.

Pour vérifier ce que le navigateur d'un téléviseur prend en charge : ouvrir `/tv-test.html` sur ce téléviseur.

## Mise à jour

```bash
cd /var/www/vitrineexpress
git pull
```

Les migrations de la base s'appliquent automatiquement à la requête suivante. Les téléviseurs rechargent la page d'eux-mêmes au plus tard 24 heures après ; pour forcer, les déconnecter et reconnecter, ou redémarrer leur navigateur.

## Sauvegarde

Tout l'état de l'application est dans `storage/` : la base `database.sqlite` et le dossier `uploads/`. Pour une copie cohérente de la base pendant que l'application tourne :

```bash
sqlite3 storage/database.sqlite ".backup '/chemin/sauvegarde/vitrine-$(date +%F).sqlite'"
rsync -a storage/uploads/ /chemin/sauvegarde/uploads/
```

## Journal d'erreurs

Les erreurs inattendues sont consignées dans `storage/logs/app.log`. Quand il dépasse 1 Mo, il est renommé `app.log.1` (le précédent `.1` est écrasé) : le journal n'occupe jamais plus de 2 Mo.

## Mot de passe oublié

S'il reste un autre administrateur, il peut changer le mot de passe dans **Utilisateurs**. Sinon, sur le serveur :

```bash
php bin/reset-password.php
```

Le script liste les comptes, demande lequel et le nouveau mot de passe. Options : `--username=`, `--password=`, et `--admin` pour rendre le compte administrateur (quand plus aucun administrateur ne peut se connecter).
