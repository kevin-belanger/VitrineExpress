<?php
/**
 * Formulaire de suppression d'une fiche. Il est placé hors du formulaire principal (pas de formulaires
 * imbriqués) ; le bouton « Supprimer » de la rangée d'actions s'y rattache par form="delete-form".
 *
 * @var string $action  chemin de suppression (ex. /admin/groups/3/delete)
 * @var string $confirm question posée avant de supprimer
 */
?>
<form id="delete-form" method="post" action="<?= e(url($action)) ?>" data-confirm="<?= e($confirm) ?>" hidden>
    <?= csrf_field() ?>
</form>
