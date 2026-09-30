# VitrineExpress — consignes pour Claude

Application d'affichage numérique : des téléviseurs (navigateurs web) affichent en rotation les messages (images, textes) des groupes auxquels ils appartiennent. Un seul organisme, auto-hébergé.

- Spécification fonctionnelle : [docs/specification-mvp.md](docs/specification-mvp.md) — la référence ; la mettre à jour si une décision la change.
- Plan de travail : [docs/phases.md](docs/phases.md) — cocher les critères au fur et à mesure.
- Décisions techniques : [docs/decisions.md](docs/decisions.md) — ajouter une entrée pour chaque choix non trivial.

## Mode de travail : autonome

Le propriétaire (Kevin) veut que tu avances seul.

- Enchaîne les phases dans l'ordre de `docs/phases.md`. Pour chaque phase : relire la spec, préciser le détail si nécessaire, coder, tester, cocher les critères, committer, pousser.
- Tranche toi-même les choix techniques ; consigne-les dans `docs/decisions.md` (contexte, décision, alternatives écartées).
- Si la spec est muette ou ambiguë sur un point fonctionnel, choisis l'option la plus simple compatible avec la spec, note-la dans `docs/decisions.md` sous « À valider par Kevin », et continue.
- Demande avant d'agir seulement pour : ce qui touche la production ou des données réelles, la configuration du serveur web ou du système (vhost, DNS, certificats, paquets, services), la suppression de données, ou toute action hors de ce dépôt.
- Certaines vérifications exigent un humain (tester sur une vraie télé). Prépare-les pour qu'elles prennent quelques minutes, décris exactement quoi faire, et continue le reste en attendant.
- À la fin de chaque phase, écris un court bilan dans `docs/phases.md` (ce qui est fait, ce qui reste, points à valider).

## Pile et conventions

- PHP 8.1+ sans framework, SQLite via PDO, aucune étape de build. Dépendances externes : le moins possible (voir `docs/decisions.md`).
- Racine web = `public/` uniquement. Tout le reste (code, base, téléversements) est hors racine web.
- Code (classes, fonctions, variables, tables) en anglais ; interface, messages et documentation en français.
- `declare(strict_types=1);` partout, requêtes préparées partout, échappement HTML systématique dans les gabarits (`e()`).
- Page d'affichage (`public/display/`) : JavaScript ES5, sans framework, compatible avec les vieux navigateurs de télé (voir résultats de la phase 0).
- Tests : `php tests/run.php` doit passer avant chaque commit.

## Git

- Branche `main`, dépôt `kevin-belanger/VitrineExpress` (privé). Petits commits cohérents, messages en français, poussés régulièrement.
- Identité du dépôt : `Kevin Bélanger <58676407+kevin-belanger@users.noreply.github.com>` (à configurer localement au clonage si absente).
- Ne jamais committer : la base SQLite, `storage/uploads/`, `config/config.local.php`.
