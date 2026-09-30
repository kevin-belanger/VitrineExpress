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

## À valider par Kevin

- **Cible obligatoire** : un message doit viser au moins un groupe, ou « Tous les téléviseurs ». Un message sans cible ne s'afficherait nulle part ; c'est plus clair de l'empêcher. (La spec ne le précisait pas.)
- **Type fixé à la création** : on ne peut pas transformer une image en texte (ou l'inverse) en modifiant un message ; il faut en créer un nouveau.
- **Alignement du texte** : aligné à gauche par défaut (comportement standard de l'éditeur) et centré verticalement ; l'éditeur permet de centrer. On pourrait centrer par défaut si tu préfères.
