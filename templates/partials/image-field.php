<?php
/**
 * Champ image : zone « choisir ou glisser ici » (coller fonctionne aussi), puis fiche avec miniature,
 * nom, dimensions et poids. Comportement : public/assets/image-field.js.
 * Le vrai champ de fichier reste dans le formulaire, caché visuellement : sans JavaScript, il fonctionne tel quel.
 *
 * @var string      $id           identifiant du champ de fichier (ex. 'image')
 * @var string      $name         nom du champ de fichier
 * @var string|null $currentUrl   image enregistrée, s'il y en a une
 * @var string      $currentLabel nom affiché pour l'image enregistrée (ex. 'Image actuelle')
 * @var int         $maxBytes     taille maximale acceptée
 * @var string      $maxLabel     taille maximale, lisible (ex. '20 Mo')
 * @var string|null $error        erreur à afficher
 * @var string|null $removeName   si fourni : bouton « Retirer » qui envoie ce champ à 1 (ex. 'remove_logo')
 */
$currentUrl ??= null;
$error ??= null;
$removeName ??= null;
?>
<div class="image-field" id="<?= e($id) ?>-field" data-image-field
     data-max-bytes="<?= (int) $maxBytes ?>" data-max-label="<?= e($maxLabel) ?>"
     data-current-url="<?= e($currentUrl ?? '') ?>" data-current-label="<?= e($currentLabel) ?>">
    <input type="file" id="<?= e($id) ?>" name="<?= e($name) ?>" class="visually-hidden" accept="image/jpeg,image/png,image/webp,image/gif">
    <?php if ($removeName !== null): ?>
        <input type="hidden" name="<?= e($removeName) ?>" value="" data-remove-input>
    <?php endif; ?>
    <label for="<?= e($id) ?>" class="dropzone"<?= $currentUrl ? ' hidden' : '' ?>>
        <svg class="dropzone-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16l4.6-4.6a2 2 0 0 1 2.8 0L16 16m-2-2 1.6-1.6a2 2 0 0 1 2.8 0L20 14M14 8h.01M6 20h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z"/></svg>
        <span><strong>Choisir une image</strong> ou la glisser ici</span>
    </label>
    <div class="image-card"<?= $currentUrl ? '' : ' hidden' ?>>
        <img class="image-card-thumb" src="<?= e($currentUrl ?? '') ?>" alt="">
        <span class="image-card-info">
            <strong class="image-card-name"><?= $currentUrl ? e($currentLabel) : '' ?></strong>
            <span class="image-card-meta muted"></span>
        </span>
        <span class="image-card-actions">
            <label for="<?= e($id) ?>" class="button small">Remplacer</label>
            <button type="button" class="button small" data-image-undo hidden>Annuler</button>
            <?php if ($removeName !== null): ?>
                <button type="button" class="button small danger" data-image-remove hidden>Retirer</button>
            <?php endif; ?>
        </span>
    </div>
    <p class="field-error image-error"<?= $error ? '' : ' hidden' ?>><?= e($error ?? '') ?></p>
</div>
