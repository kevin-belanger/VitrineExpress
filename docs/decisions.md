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

- **Décision** : fichiers `migrations/NNN_description.sql` appliqués dans l'ordre ; version courante dans la table `schema_version`. Appliquées par le script d'installation et au démarrage si nécessaire.

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

## À valider par Kevin

_(aucun point pour l'instant)_
