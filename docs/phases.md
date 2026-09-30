# Plan de développement

Chaque phase se termine quand tous ses critères sont cochés, les tests passent, et le travail est poussé sur `main`. Un bilan court est ajouté sous la phase.

## Démarrage sur le serveur

- [ ] Cloner le dépôt, configurer l'identité Git du dépôt (voir `CLAUDE.md`)
- [ ] Relever l'environnement et le noter dans `docs/decisions.md` : serveur web (Apache/Nginx), version de PHP, extensions (`pdo_sqlite`, `fileinfo`, `mbstring`, `dom`, `gd` ou `imagick`), limites de téléversement (`upload_max_filesize`, `post_max_size`), accès en écriture à `storage/`
- [ ] Déterminer comment servir l'application en développement (`php -S` sur `public/`, ou vhost existant) — demander avant de modifier la configuration du serveur web

## Phase 0 — Test sur une vraie télé

Le plus grand risque du projet est le navigateur des téléviseurs. On le mesure avant d'écrire la page d'affichage.

- [ ] Page `public/tv-test.html` autonome qui affiche, en gros caractères, le résultat de chaque vérification : agent utilisateur, taille d'écran et ratio de pixels, `fetch` / `XMLHttpRequest`, `Promise`, fonctions fléchées, `localStorage`, cookies, API plein écran, transitions et opacités CSS, `object-fit: contain`, flexbox, polices et tailles en `vw`
- [ ] Instructions courtes pour Kevin : quelle adresse ouvrir sur la télé, quoi photographier ou recopier
- [ ] Résultats consignés dans `docs/decisions.md` (niveau de JavaScript et de CSS retenu pour la page d'affichage)

Cette phase attend un humain : prépare la page, puis passe à la phase 1 en parallèle.

## Phase 1 — Fondations

- [ ] Structure des dossiers (D2), chargement automatique, configuration (`config/config.php` + `config.local.php`)
- [ ] Connexion PDO SQLite (clés étrangères activées, mode WAL), système de migrations (D4), migration initiale avec les 8 tables de la spec
- [ ] Routeur, gestion des erreurs (page 404/500 propre, journal d'erreurs), gabarit de base de l'interface de gestion
- [ ] Script d'installation (ligne de commande `php bin/install.php`) : crée la base, les paramètres par défaut, les arrière-plans prédéfinis, et le premier compte ; refuse de tourner deux fois
- [ ] Connexion / déconnexion des administrateurs, sessions sécurisées, protection CSRF
- [ ] Écran Utilisateurs : liste, ajout, modification, suppression (on ne peut pas supprimer son propre compte), changement de son mot de passe
- [ ] Mini-exécuteur de tests (D8) et premiers tests

## Phase 2 — Téléviseurs et groupes

- [ ] Écran Groupes : liste (nombre de télés et de messages), ajout, modification, suppression
- [ ] Écran Téléviseurs : liste, ajout, modification, suppression ; groupes par cases à cocher
- [ ] Génération du code à 5 chiffres aléatoire et unique (`random_int`), affiché bien en vue
- [ ] Boutons Déconnecter et Régénérer le code
- [ ] Tests : unicité des codes, appartenance aux groupes, suppression d'un groupe sans perte de télés ni de messages

## Phase 3 — Messages

- [ ] Arrière-plans prédéfinis (environ 8 dégradés et couleurs lisibles) fournis par la migration ou l'installation
- [ ] Formulaire commun : titre, début (défaut : date de création à 00:00), fin facultative (heure par défaut 23:59), durée (défaut du paramètre), ciblage (groupes ou « Tous les téléviseurs »)
- [ ] Type image : téléversement JPG/PNG/WebP/GIF, validation par contenu (`finfo`), taille maximale, nom aléatoire, route `/media/{nom}` (D3), miniature
- [ ] Type texte : Quill (D6), nettoyage serveur (D5), choix d'arrière-plan
- [ ] Aperçu 16:9 fidèle au rendu de la télé (mêmes styles que la page d'affichage)
- [ ] Liste : miniature, titre, type, période, durée, cibles, état ; filtres par groupe, par téléviseur (file exacte) et par état
- [ ] Service `Playlist` : calcul de la file d'une télé et du message suivant, selon la spec — c'est le cœur, bien testé (doublons, expirés, à venir, sans fin, file vide, message supprimé, retour au début)

## Phase 4 — Page d'affichage

- [ ] API `pair`, `next`, `heartbeat`, `logout` (spec, section Logique serveur) ; jeton aléatoire de 32 octets, seule l'empreinte est stockée
- [ ] Écran de code : pavé numérique utilisable à la télécommande (flèches + OK) ; conflit « déjà connecté » avec confirmation ; lien depuis la page de connexion de la gestion
- [ ] Jeton persistant : cookie longue durée + copie dans `localStorage`
- [ ] Rotation : affichage selon la durée, préchargement du suivant, fondu, image en *contain* sur fond noir, texte proportionnel à la largeur
- [ ] File vide : nom ou logo de l'organisme, heure et date ; nouvel essai toutes les 60 s
- [ ] Coupure réseau : écran d'avertissement avec indicateur, nouvel essai toutes les 10 s, reprise automatique
- [ ] Déconnexion à distance (401) : retour à l'écran de code avec un message
- [ ] Menu caché au mouvement de la souris (5 s) : nom de la télé, Plein écran, Déconnecter cet appareil
- [ ] Signal de présence toutes les 60 s
- [ ] Vérification sur une vraie télé (instructions pour Kevin)

## Phase 5 — Tableau de bord, paramètres, mise en production

- [ ] Tableau de bord : état de chaque télé (en ligne, hors ligne, jamais connectée), dernière activité, message affiché avec miniature, nombre de messages dans sa file ; rafraîchissement automatique
- [ ] Écran Paramètres : durée par défaut, taille maximale des fichiers, nom et logo de l'organisme, fuseau horaire
- [ ] Revue de sécurité (spec, section Sécurité et contraintes)
- [ ] Guide d'installation et de mise à jour dans `docs/installation.md` (exigences, configuration Apache et Nginx, droits sur `storage/`, sauvegarde de la base)
- [ ] Mise en production — **demander à Kevin avant**
