# Outils des captures d'écran

Scripts utilisés pour produire les images du site, à partir d'une copie de démonstration de l'application avec des données fictives. Les chemins (`/tmp/vx-demo`, `%TEMP%\vx-site`) sont ceux du poste de développement : à adapter.

1. `posters/*.html` : les affiches des messages de type image, rendues en PNG 1920 × 1080 par Chrome sans interface (`chrome --headless=new --window-size=1920,1080 --screenshot=…`).
2. `demo-setup.sh` : copie du dépôt, base neuve, compte d'administration et sessions prêtes pour le navigateur sans interface (l'identifiant de session est passé dans l'URL : le serveur est lancé avec `-d session.use_only_cookies=0`).
3. `demo-seed.php` : groupes, écrans, messages et états fictifs.
4. `shoot.ps1` : captures des pages de gestion à 1440 px de large, échelle 2.
5. `images.php` : recadrage, réduction et conversion en WebP.
