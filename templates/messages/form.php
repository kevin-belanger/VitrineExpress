<?php

use VitrineExpress\Messages;
use VitrineExpress\View;

$isNew = $message === null;
$isText = $values['type'] === Messages::TYPE_TEXT;
$action = $isNew ? '/admin/messages' : '/admin/messages/' . $message['id'];
?>
<div class="page-head">
    <h1><?= e($title) ?></h1>
</div>

<div class="split split-wide">
    <form method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data" class="card form" id="message-form">
        <?= csrf_field() ?>

        <?php if ($isNew): ?>
            <?php // Le type se choisit à la création ; ensuite, le champ de contenu suffit à le montrer. ?>
            <div class="type-switch" role="radiogroup" aria-label="Type de message">
                <label>
                    <input type="radio" name="type" value="image" <?= !$isText ? 'checked' : '' ?>>
                    <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16l4.6-4.6a2 2 0 0 1 2.8 0L16 16m-2-2 1.6-1.6a2 2 0 0 1 2.8 0L20 14M14 8h.01M6 20h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z"/></svg>Image</span>
                </label>
                <label>
                    <input type="radio" name="type" value="text" <?= $isText ? 'checked' : '' ?>>
                    <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7V5h14v2M12 5v14M9 19h6"/></svg>Texte</span>
                </label>
            </div>
        <?php else: ?>
            <input type="hidden" name="type" value="<?= e($values['type']) ?>">
        <?php endif; ?>

        <div data-for-type="image" class="<?= $isText ? 'hidden' : '' ?>">
            <?= View::render('partials/image-field', [
                'id' => 'image',
                'name' => 'image',
                'currentUrl' => $imageUrl,
                'currentLabel' => 'Image actuelle',
                'maxBytes' => $maxBytes,
                'maxLabel' => $maxLabel,
                // Après une erreur ailleurs, le navigateur a oublié le fichier choisi : il faut le reprendre.
                'error' => $errors['image'] ?? ($errors && !$isText && $isNew ? 'Sélectionnez de nouveau l’image.' : null),
            ], null) ?>
        </div>

        <div data-for-type="text" class="<?= $isText ? '' : 'hidden' ?> form">
            <div class="field">
                <?php // Barre de l'éditeur (Quill) : peu de boutons, libellés en français ; affichée par message-form.js. ?>
                <div id="editor-toolbar" hidden>
                    <span class="ql-formats">
                        <select class="ql-header">
                            <option selected>Texte</option>
                            <option value="1">Titre</option>
                            <option value="2">Sous-titre</option>
                        </select>
                        <select class="ql-size">
                            <option value="small">Petit</option>
                            <option selected></option>
                            <option value="large">Grand</option>
                            <option value="huge">Très grand</option>
                        </select>
                    </span>
                    <span class="ql-formats">
                        <button type="button" class="ql-bold" title="Gras"></button>
                        <button type="button" class="ql-italic" title="Italique"></button>
                        <button type="button" class="ql-underline" title="Souligné"></button>
                        <select class="ql-align">
                            <option selected></option>
                            <option value="center"></option>
                            <option value="right"></option>
                        </select>
                    </span>
                    <span class="ql-formats">
                        <button type="button" class="ql-list" value="bullet" title="Liste à puces"></button>
                        <button type="button" class="ql-list" value="ordered" title="Liste numérotée"></button>
                        <button type="button" class="ql-clean" title="Effacer la mise en forme"></button>
                    </span>
                </div>
                <div id="editor"></div>
                <textarea name="text_html" hidden><?= e($values['text_html']) ?></textarea>
                <?= field_error($errors, 'text_html') ?>
            </div>
            <fieldset>
                <legend>Arrière-plan</legend>
                <div class="swatches">
                    <?php foreach ($backgrounds as $bg): ?>
                        <label class="swatch" title="<?= e($bg['name']) ?>">
                            <input type="radio" name="background_id" value="<?= (int) $bg['id'] ?>"
                                   data-css="<?= e($bg['css_value']) ?>" data-color="<?= e($bg['text_color']) ?>"
                                <?= (int) $values['background_id'] === (int) $bg['id'] ? 'checked' : '' ?>>
                            <span class="swatch-color" style="background: <?= e($bg['css_value']) ?>; color: <?= e($bg['text_color']) ?>">Aa</span>
                            <span class="visually-hidden"><?= e($bg['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= field_error($errors, 'background_id') ?>
            </fieldset>
        </div>

        <label>
            <span>Titre <span class="label-hint">(pour vous retrouver, n’est pas affiché)</span></span>
            <input type="text" name="title" value="<?= e($values['title']) ?>" required maxlength="150">
            <?= field_error($errors, 'title') ?>
        </label>

        <div class="compact-grid">
            <label for="start_date">Début</label>
            <div class="inline-fields">
                <input type="date" id="start_date" name="start_date" value="<?= e($values['start_date']) ?>" required>
                <input type="time" name="start_time" value="<?= e($values['start_time']) ?>" aria-label="Heure de début">
                <button type="button" class="link-button end-add" hidden>Définir une date de fin</button>
                <?= field_error($errors, 'start') ?>
            </div>

            <?php // Sans JavaScript, la ligne Fin reste visible ; message-form.js la cache s'il n'y a pas de fin. ?>
            <label for="end_date" class="end-label">Fin</label>
            <div class="inline-fields" id="end-fields">
                <input type="date" id="end_date" name="end_date" value="<?= e($values['end_date']) ?>">
                <input type="time" name="end_time" value="<?= e($values['end_time']) ?>" aria-label="Heure de fin">
                <button type="button" class="icon-button end-remove" title="Retirer la date de fin" aria-label="Retirer la date de fin">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
                <?= field_error($errors, 'end') ?>
            </div>

            <label for="duration_seconds">Durée à l’écran</label>
            <div class="inline-fields">
                <input type="number" id="duration_seconds" name="duration_seconds" value="<?= e($values['duration_seconds']) ?>"
                       min="<?= Messages::MIN_DURATION ?>" max="<?= Messages::MAX_DURATION ?>" required>
                <span class="muted">secondes</span>
                <?= field_error($errors, 'duration_seconds') ?>
            </div>
        </div>

        <?= View::render('messages/_targets', [
            'values' => $values,
            'groups' => $groups,
            'devices' => $devices,
            'canTargetAll' => $canTargetAll,
            'canEditTargets' => $canEditTargets,
            'otherTargets' => $otherTargets,
            'contentNote' => true,
        ], null) ?>

        <?php // Formulaire long : la rangée d'actions reste au bas de l'écran pendant qu'on le parcourt. ?>
        <div class="form-actions sticky-actions">
            <button type="submit" class="button primary">Enregistrer</button>
            <a class="button" href="<?= e(url('/admin/messages')) ?>">Annuler</a>
            <?php if ($canDelete): ?>
                <button type="submit" form="delete-form" class="button danger">Supprimer</button>
            <?php endif; ?>
        </div>
    </form>

    <aside class="preview-panel">
        <p class="preview-label">Aperçu sur un écran 16:9</p>
        <div class="preview-frame" id="preview"></div>
    </aside>
</div>

<?php if ($canDelete): ?>
    <?php // Message partagé hors du périmètre de son créateur : la suppression l'enlève aussi ailleurs, on le dit. ?>
    <?= View::render('partials/delete-form', [
        'action' => '/admin/messages/' . $message['id'] . '/delete',
        'confirm' => 'Supprimer le message « ' . $message['title'] . ' » ?'
            . ($otherTargets['names'] ? ' Il est aussi affiché dans : ' . implode(', ', $otherTargets['names']) . '.' : ''),
    ], null) ?>
<?php endif; ?>
