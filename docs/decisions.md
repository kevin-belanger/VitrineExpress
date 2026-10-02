# Décisions techniques

Chaque entrée : contexte, décision, alternatives écartées. Les plus récentes en bas.

## D1 — PHP sans framework

- **Décision** : PHP 8.1+ natif, un contrôleur frontal (`public/index.php`) et un petit routeur maison ; chargement automatique des classes PSR-4 maison (`src/`, espace de noms `VitrineExpress\`).
- **Pourquoi** : application petite, déploiement par simple copie, rien à compiler, aucune dépendance obligatoire.
- **Écarté** : Laravel/Symfony (trop lourds pour le besoin), Slim (ajoute Composer pour peu de gain).

## D2 — Structure des dossiers

```
public/            racine web : index.php, assets/, display/ (page d'affichage)
src/               classes PHP (VitrineExpress\...)
templates/         gabarits PHP de l'interface de gestion
migrations/        fichiers SQL numérotés (001_init.sql, ...)
config/            config.php (défauts, versionné) + config.local.php (ignoré)
storage/           database.sqlite, uploads/ (hors racine web, ignorés par Git)
tests/             run.php + tests
docs/              spécification, phases, décisions
```

## D3 — Fichiers téléversés servis par PHP

- **Décision** : stockés dans `storage/uploads/` (hors racine web) sous un nom aléatoire, servis par une route `/media/{nom}` avec les bons en-têtes (type MIME, cache long, `X-Content-Type-Options: nosniff`).
- **Pourquoi** : aucun risque d'exécution de script téléversé, fonctionne pareil sous Apache et Nginx sans configuration particulière.
- **Écarté** : dossier public avec `.htaccess` (dépend d'Apache, facile à mal configurer).

## D4 — Migrations SQL maison

- **Décision** : fichiers `migrations/NNN_description.sql` appliqués dans l'ordre, chacun dans une transaction ; version courante dans `PRAGMA user_version` (plus simple qu'une table dédiée). Appliquées par le script d'installation et à chaque requête si nécessaire (coût négligeable).

## D5 — Nettoyage du texte enrichi sans dépendance

- **Décision** : nettoyeur maison basé sur `DOMDocument`, avec une liste blanche stricte (p, br, strong, em, u, s, h1-h3, ul, ol, li, span ; attributs `class` limités aux classes de l'éditeur, aucun `style`, aucun lien). Couvert par des tests.
- **Pourquoi** : l'éditeur produit un ensemble de balises réduit et connu ; évite d'ajouter Composer.
- **Écarté** : HTML Purifier (excellent, mais impose Composer ou l'inclusion d'une grosse bibliothèque). À reconsidérer si la liste blanche doit s'élargir.

## D6 — Éditeur de texte enrichi : Quill

- **Décision** : Quill 2, fichiers copiés dans `public/assets/vendor/quill/` (pas de CDN : l'application doit fonctionner sur un réseau interne).
- **Pourquoi** : léger, sortie HTML propre et prévisible, taille et alignement par classes.

## D7 — Interface de gestion sans outil de build

- **Décision** : HTML rendu côté serveur, une feuille CSS maison, un peu de JavaScript natif seulement là où c'est utile (aperçu, filtres).

## D8 — Tests sans dépendance

- **Décision** : `tests/run.php`, un mini-exécuteur de tests (fonctions `test_*`, assertions simples), sur une base SQLite en mémoire.
- **Écarté** : PHPUnit (demande Composer). À reconsidérer si les tests grossissent.

## D9 — Fuseau horaire par défaut

- **Décision** : `America/Toronto` (heure de l'Est, Québec), modifiable dans les paramètres. Dates stockées en texte ISO 8601 dans ce fuseau.

## D10 — Développement dans WSL

- **Décision** : développement dans Ubuntu 24.04 (WSL 2) avec PHP 8.3, dépôt cloné dans `~/VitrineExpress` (hors Google Drive). Serveur de développement intégré de PHP via `bin/dev-server.sh`.
- **Pourquoi** : environnement proche d'un serveur Linux de production ; Google Drive risque de corrompre `.git` et la base SQLite.

## D11 — Routes et contrôleurs

- **Décision** : `/login`, `/logout` pour la connexion ; tout l'administration sous `/admin/...` (authentification vérifiée par le noyau) ; `/display` pour la page des télés, `/api/device/...` pour son API JSON (sans session ni CSRF, authentifiée par jeton), `/media/{nom}` pour les fichiers. Jeton CSRF vérifié sur tout POST hors API. Les paramètres de route sont passés aux actions par arguments nommés.

## D12 — Rendu des messages texte sur une scène fixe

- **Décision** : un message texte est mis en page sur une scène de 1920 × 1080 px (marges de 120 px, police de base 64 px), puis mis à l'échelle avec `transform: scale()` pour remplir l'écran en gardant le 16:9 ; l'arrière-plan couvre tout l'écran. Si le texte déborde, sa taille est réduite automatiquement. Les images sont dimensionnées en JavaScript (équivalent de `object-fit: contain`, que les vieux navigateurs ignorent).
- **Pourquoi** : rendu identique sur toutes les tailles d'écran et dans l'aperçu de la gestion ; techniques compatibles avec les vieux navigateurs de télé (pas d'unités `vw` ni de requêtes de conteneur).
- **Code** : `public/assets/slide.js` (ES5) et `slide.css`, partagés par l'aperçu et la page d'affichage.

## D13 — Miniatures

- **Décision** : à chaque téléversement, une miniature JPEG de 480 px de large est créée avec GD (`nom.thumb.jpg`), sur fond noir. Utilisée dans les listes et le tableau de bord ; l'original est gardé tel quel pour l'affichage.

## D14 — Limites de téléversement

- **Décision** : la taille maximale est un paramètre de l'application (`max_upload_mb`, 20 Mo par défaut), mais PHP impose aussi `upload_max_filesize` et `post_max_size`. Le serveur de développement les monte à 64 Mo ; en production, les régler au moins à la valeur du paramètre (voir le guide d'installation). Un envoi trop gros affiche un message clair (erreur 413).

## D15 — En-têtes de sécurité

- **Décision** : toutes les réponses PHP portent `Content-Security-Policy` (scripts et connexions limités au site lui-même, aucun script en ligne ; styles en ligne permis pour l'éditeur et les arrière-plans), `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: same-origin`. Les fichiers `/media` ont en plus `default-src 'none'`.

## D16 — Page d'affichage : comportements ajoutés

- **Décision** : la page des télés se recharge d'elle-même toutes les 24 h (pour prendre les nouvelles versions et libérer la mémoire) ; les fichiers CSS/JS portent leur date de modification dans l'URL (`asset()`) ; la page demande de garder l'écran allumé (Wake Lock) quand le navigateur le permet. Une image qui ne se charge pas est sautée après 3 s.

## D17 — Message sans cible permis

- **Décision** (validée par Kevin) : un message peut n'avoir aucun groupe ni « Tous les téléviseurs ». Il est gardé mais affiché nulle part, comme un brouillon ; la liste l'indique (« Aucune (non affiché) »).
- **Écarté** : exiger au moins une cible (premier choix, jugé trop contraignant).

## D18 — Sélecteur à choix multiples

- **Décision** : un composant unique (`templates/partials/picker.php` + `public/assets/picker.js`) remplace les cases à cocher en ligne pour les groupes d'un message, les groupes d'un téléviseur et les téléviseurs d'un groupe : une ligne par élément avec une information utile (nombre de télés, description), recherche à partir de 7 éléments, tout cocher / décocher, compteur. Pour un message : choix explicite « Tous les téléviseurs » / « Certains groupes » et résumé en direct des téléviseurs touchés.
- **Pourquoi** : c'est une amélioration de présentation seulement ; le formulaire envoie toujours les mêmes cases à cocher et fonctionne sans JavaScript, donc aucun changement côté serveur.
- **Écarté** : liste déroulante à étiquettes (type « select2 ») — plus lourde à coder et à maintenir, et cache les choix au lieu de les montrer.

## D19 — Installation web

- **Décision** (demandée par Kevin) : page `/install` à la manière de WordPress. Tant qu'aucun compte n'existe, `/`, `/login` et `/admin…` y mènent ; elle vérifie l'environnement (PHP, extensions, droits d'écriture ; limite de téléversement et HTTPS en avertissement), demande le nom de l'organisme et le premier compte, puis connecte l'utilisateur. Dès qu'un compte existe, elle redirige vers `/login` ; la création se fait dans une transaction qui revérifie l'absence de compte. Le script `bin/install.php` reste disponible.
- **Risque accepté** : comme WordPress, la première personne qui ouvre un site fraîchement déployé peut créer le compte ; le guide d'installation demande de le faire aussitôt.
- **Écarté** : clé d'installation à copier depuis le serveur (plus sûr, mais complique justement le cas sans terminal).

## D20 — Tableau de bord par exceptions

- **Décision** (définie avec Kevin) : deux cartes, Téléviseurs et Messages. Chacune a un chiffre principal (« 4 sur 6 en ligne », « 7 messages en diffusion ») puis seulement les lignes dont le compte n'est pas zéro, de la plus urgente à la moins urgente, chacune liée à la liste filtrée (ou à la fiche s'il n'y a qu'une télé) :
  - Téléviseurs : hors ligne (nom, dernière activité), non connectés (nom, code), en ligne sans message à afficher.
  - Messages : se terminent dans les 48 h, à venir (le prochain), non diffusés (actifs mais n'atteignant aucune télé : sans cible ou groupes vides), expirés.
- Le détail télé par télé (dont le message affiché en ce moment) est dans la page Téléviseurs, actualisée elle aussi toutes les 30 s.
- **Écarté** : ligne « télés sans groupe » (déjà couverte par « sans message à afficher ») ; compteurs à zéro ; tableau détaillé sur le tableau de bord.
- Code : `src/Dashboard.php` (calculs, testés), nouveaux filtres de la liste des messages (`live`, `ending`, `unbroadcast`).

## D21 — Vocabulaire : « périphérique d'affichage »

- **Décision** (demandée par Kevin) : l'interface dit « périphérique d'affichage » plutôt que « téléviseur » (un écran peut aussi être un PC ou un moniteur). Forme complète dans les titres, la navigation et les boutons ; forme courte « périphérique » là où le contexte est clair (compteurs, colonnes, résumés). Le code garde `device` ; la documentation technique peut encore dire « téléviseur ».

## D22 — Ciblage direct des périphériques

- **Décision** (définie avec Kevin) : un message peut viser des périphériques précis, en plus ou à la place des groupes (table `message_devices`, migration 003). Les groupes deviennent facultatifs.
- **Interface** : « Tous les périphériques d'affichage » ou « Choisir ». Sous « Choisir », un seul sélecteur à deux sections (Groupes, Périphériques ; la section Groupes n'apparaît que s'il en existe). Un périphérique inclus par un groupe coché est montré coché, grisé, « inclus par … » ; il n'est pas enregistré comme cible directe. Le résumé donne toujours la liste des périphériques touchés.
- **Règle** : la condition de ciblage est définie une seule fois (`Messages::targetsDeviceSql()`) et réutilisée par la file, la liste, les filtres et le tableau de bord.

## D23 — Formulaire de message : image et date de fin

- **Image** : zone « Choisir une image ou la glisser ici », qui accepte aussi le collage (Ctrl+V). Une fois l'image choisie, une fiche (miniature, nom, dimensions, poids) avec « Remplacer » ; en modification, « Image actuelle » et « Annuler » pour revenir à l'image enregistrée. Format et taille vérifiés dès le choix, avec les mêmes messages que le serveur ; un fichier refusé n'efface pas le choix précédent. Le vrai `<input type="file">` reste dans le formulaire (caché visuellement mais focalisable), donc l'envoi et la validation serveur sont inchangés.
- **Composant réutilisable** : `templates/partials/image-field.php` + `public/assets/image-field.js`, utilisé pour l'image d'un message et pour le logo (Paramètres, avec un bouton « Retirer »). Il expose `element.imageField` (`url()`, `showError()`) et l'événement `imagechange`, dont se sert l'aperçu du message.
- **Envoi** : le bouton passe à « Enregistrement… » et se désactive (téléversements longs, double clic).
- **Date de fin** : lien « Définir une date de fin » ; les champs n'apparaissent qu'à la demande, avec un bouton pour les retirer. Cachés, ils sont désactivés et non envoyés : pas de fin. Sans JavaScript, les champs restent visibles.
- **Écarté** : case à cocher « Fin » (demandait une interprétation) ; bibliothèque de téléversement (Dropzone, FilePond) — inutile pour un seul fichier.

## D24 — Identité visuelle

- **Logos et icônes** fournis par Kevin : sources dans `resources/brand/`, images d'usage générées par `php bin/build-brand.php resources/brand` (rognées, redimensionnées, en PNG pour les vieux navigateurs de télé) dans `public/assets/brand/`, plus `public/favicon.ico` (16, 32, 48 px).
  - Logo à texte blanc : barre du haut de la gestion, écran de connexion des périphériques.
  - Logo à texte foncé : pages de connexion et d'installation.
  - Icône à contour marine : favicon, icône d'écran d'accueil (iOS, fond blanc).
  - `icon-light.png` (icône claire) gardée en source, inutilisée pour l'instant.
- **Couleurs** alignées sur le logo : accent bleu `#0a56c2` (le bleu de la vitrine, contraste suffisant pour du texte blanc), surlignage cyan dans la barre du haut, fond bleu marine sur les écrans de la télé (code, heure). La barre du haut garde l'ardoise `#10202b`, déjà proche du texte foncé du logo.

## D25 — Menu repliable et pages adaptées aux petits écrans

- **Menu** : quand le logo, la navigation et l'utilisateur ne tiennent plus sur une ligne (mesuré par `admin.js`, pas de largeur d'écran fixe), la navigation se replie derrière un bouton « Menu » qui l'ouvre en panneau vertical sous la barre (fermeture : bouton, clic à côté, Échap). Sans JavaScript, la barre passe sur plusieurs lignes.
- **Tableaux** : chaque liste est dans un cadre qui défile horizontalement au besoin, au lieu de faire défiler toute la page (tablettes, téléphones).
- **Largeurs minimales** plafonnées à la largeur de l'écran (`min(…, 100%)`), `min-width: 0` sur les fieldsets : aucune page ne déborde de 320 à 2560 px.

## D26 — Liste des périphériques en direct

- **Décision** (demandée par Kevin) : la page Périphériques d'affichage ne se recharge plus en entier toutes les 30 s. Elle interroge `/admin/devices/live` (JSON) toutes les 5 s et ne remplace que les cellules « État » et « Affiche en ce moment » ; quand la diapositive affichée change (clé = message + date de modification, ou l'état), la cellule bascule en fondu. Les interrogations s'arrêtent quand l'onglet est caché ; si la session a expiré, la page se recharge (et mène à la connexion).
- Le HTML des deux cellules est produit par les mêmes gabarits (`devices/_status_cell`, `devices/_current`) pour la page et pour le JSON.

## D27 — Rôles : administrateur et gestionnaire de groupes

- **Décision** (demandée par Kevin, détails dans [specification-gestionnaires.md](specification-gestionnaires.md)) : chaque compte est « Administrateur » (tout) ou « Gestionnaire de groupes » (publie dans les groupes qui lui sont confiés). Colonne `users.role` et table `user_groups` (migration 004) ; les comptes existants deviennent administrateurs.
- **Une seule classe de règles** : `src/Access.php` (pages permises, périmètre, droits sur un message, fusion des cibles). Le Kernel refuse (403) toute page `/admin` hors de la liste permise aux gestionnaires ; les contrôleurs demandent à `Access` pour chaque message. Le menu est filtré par la même règle.
- **Fusion des cibles** : quand un gestionnaire enregistre, seules les cibles de son périmètre suivent le formulaire ; les autres sont relues en base et conservées. Plusieurs gestionnaires partagent donc un message sans s'écraser, quel que soit le contenu envoyé.
- **Message d'un autre** : page « Diffusion » (aperçu en lecture seule + « Afficher sur ») plutôt que le formulaire complet avec des champs désactivés : on montre seulement ce qu'on peut faire.
- **Périmètre des listes** : liste des messages et tableau de bord d'un gestionnaire limités par défaut à son périmètre (mêmes chiffres des deux côtés) ; « Tous les groupes » dans le filtre montre le reste, pour diffuser chez soi le message d'un autre.
- **Sans changement pour un administrateur**, sauf l'auteur affiché sous le titre des messages.

## D28 — Listes : lignes cliquables, suppression dans la fiche

- **Décision** (demandée par Kevin) : les listes Messages, Périphériques d'affichage, Groupes et Utilisateurs n'ont plus de colonne Actions. Toute la ligne ouvre la fiche ; « Supprimer » est dans la fiche, à droite d'« Enregistrer » et « Annuler », avec la même confirmation qu'avant.
- **Ligne cliquable** : `tr[data-href]` géré par `admin.js` (Ctrl/Cmd/Maj+clic : nouvel onglet ; pas de navigation après une sélection de texte ; les liens de la ligne, comme la miniature d'un périphérique, gardent leur rôle). Le nom reste un vrai lien, pour le clavier et le clic du milieu. Une ligne qu'on ne peut pas ouvrir (gestionnaire : message « Tous », périphériques) n'est pas cliquable.
- **Suppression** : formulaire séparé (`partials/delete-form.php`), placé hors du formulaire principal puisque les formulaires ne s'imbriquent pas ; le bouton s'y rattache par `form="delete-form"`. Pour un périphérique, « Supprimer » quitte le panneau du code, qui garde les actions de connexion (Déconnecter, Régénérer le code).
- **Écarté** : lien étiré sur toute la ligne en CSS (`position: relative` sur `tr` mal pris en charge par certains navigateurs).

## À valider par Kevin

- **Type fixé à la création** : on ne peut pas transformer une image en texte (ou l'inverse) en modifiant un message ; il faut en créer un nouveau.
- **Alignement du texte** : aligné à gauche par défaut (comportement standard de l'éditeur) et centré verticalement ; l'éditeur permet de centrer. On pourrait centrer par défaut si tu préfères.
