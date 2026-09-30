# VitrineExpress

Application web d'affichage numérique : des téléviseurs (simples navigateurs web) affichent en rotation les messages — images ou textes — des groupes auxquels ils appartiennent.

- **Gestion** : téléviseurs, groupes, messages (images, texte enrichi sur arrière-plans prédéfinis), période d'affichage, durée, ciblage, tableau de bord de l'état des téléviseurs.
- **Affichage** : chaque téléviseur se connecte avec un code à 5 chiffres, reste connecté, fait défiler sa file de messages et gère les coupures réseau.
- **Technique** : PHP 8.1+, SQLite, aucune dépendance à installer, JavaScript compatible avec les vieux navigateurs de télé.

## Documentation

- [Spécification du MVP](docs/specification-mvp.md)
- [Installation et mise à jour](docs/installation.md)
- [Plan de développement](docs/phases.md)
- [Décisions techniques](docs/decisions.md)

## Développement

```bash
php bin/install.php          # crée la base et le premier compte
bin/dev-server.sh            # http://localhost:8080
php tests/run.php            # tests
```
