# Gestionnaires de groupes — spécification

Validée avec Kevin le 1er octobre 2026. Complète [specification-mvp.md](specification-mvp.md).

## Rôles

Chaque compte a un rôle :

- **Administrateur** : accès complet (comme avant). Les comptes existants deviennent administrateurs.
- **Gestionnaire de groupes** : publie des messages dans les groupes qui lui sont confiés.

Le **périmètre** d'un gestionnaire = ses groupes + les périphériques de ces groupes. Les messages de son périmètre : les siens, ceux qui visent ses groupes, et tout ce qui s'affiche sur ses périphériques (« Tous », un autre groupe qui contient l'un d'eux, ou ciblage direct).

## Permissions

| Action | Administrateur | Gestionnaire de groupes |
|---|---|---|
| Voir la liste des messages | Tous | Ceux de son périmètre par défaut ; « Tous les groupes » montre aussi ceux des autres. Tout message s'ouvre et peut être diffusé dans ses groupes |
| Créer un message | Oui, cibles libres | Oui, cibles dans son périmètre |
| « Tous les périphériques d'affichage » | Oui | Non |
| Modifier **son** message | Tout | Contenu, dates, durée ; cibles de son périmètre |
| Modifier le message **d'un autre** | Tout | Seulement ajouter ou retirer les cibles de son périmètre |
| Supprimer | Tous | Ses propres messages |
| Périphériques | Tout | Lecture seule de ceux de ses groupes (état, ce qui s'affiche) |
| Groupes, utilisateurs, paramètres | Oui | Non |
| Tableau de bord | Global | Limité à son périmètre |

## Règle des cibles

Quand un gestionnaire enregistre un message, **seules les cibles de son périmètre changent ; les autres sont conservées**. Le serveur l'applique quel que soit le contenu du formulaire. Un message « Tous les périphériques » ne peut pas être modifié dans ses cibles par un gestionnaire.

## Cas particuliers

- Message partagé (affiché aussi hors du périmètre de son créateur) : le créateur peut en modifier le contenu (la modification s'applique partout) et le supprimer, avec un avertissement qui nomme les autres groupes.
- Gestionnaire qui perd un groupe : ses messages y restent ; il ne peut plus en retirer ce groupe.
- Créateur supprimé : le message reste ; seul un administrateur en modifie le contenu.
- Gestionnaire sans groupe : il peut créer des brouillons (sans cible).
- Périphérique ciblé directement qui quitte le groupe du gestionnaire : le ciblage reste jusqu'à la prochaine modification par quelqu'un qui le peut.
- Périphérique dans deux groupes : un message de l'autre groupe qui s'y affiche apparaît dans « Vos groupes », et le gestionnaire peut l'étendre à ses groupes, comme tout message d'un autre (comportement confirmé par Kevin).
- Un administrateur ne peut pas retirer son propre rôle (il reste toujours au moins un administrateur).

## Interface

- Fiche utilisateur : rôle (Administrateur / Gestionnaire de groupes) ; pour un gestionnaire, ses groupes.
- Liste des utilisateurs : « Administrateur », ou « Gestionnaire de » suivi de ses groupes.
- Menu d'un gestionnaire : Tableau de bord, Messages, Périphériques d'affichage (et son compte).
- Liste des messages : l'auteur sous le titre (« par vous », « par Julie ») ; filtre Groupe « Vos groupes » par défaut pour un gestionnaire. Toute ligne s'ouvre (validé par Kevin : pas de message qu'on ne peut pas ouvrir) : la fiche complète pour l'administrateur et le créateur, sinon la page Diffusion.
- Message d'un autre (page « Diffusion ») : titre, auteur, période et aperçu en lecture seule, et seulement la partie « Afficher sur », limitée au périmètre ; les autres cibles sont indiquées en une ligne. En lecture seule, avec « Retour », pour un message « Tous » (déjà partout) ou pour un gestionnaire sans groupe.
- Message partagé : une ligne indique où il est aussi affiché (et, pour son créateur, que ses modifications s'y appliquent) ; la confirmation de suppression le rappelle.
- Périphériques : ceux de ses groupes, sans code ni actions ; la miniature affichée ouvre le message, comme partout.
- Tableau de bord : compteurs de son périmètre ; liens vers les listes (jamais vers les fiches réservées).
