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

## À valider par Kevin

- **Type fixé à la création** : on ne peut pas transformer une image en texte (ou l'inverse) en modifiant un message ; il faut en créer un nouveau.
- **Alignement du texte** : aligné à gauche par défaut (comportement standard de l'éditeur) et centré verticalement ; l'éditeur permet de centrer. On pourrait centrer par défaut si tu préfères.
