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
            <fieldset class="segmented">
                <legend>Type de message</legend>
                <label><input type="radio" name="type" value="image" <?= !$isText ? 'checked' : '' ?>> Image</label>
                <label><input type="radio" name="type" value="text" <?= $isText ? 'checked' : '' ?>> Texte</label>
            </fieldset>
        <?php else: ?>
            <input type="hidden" name="type" value="<?= e($values['type']) ?>">
            <p class="muted">Type : <?= e(Messages::TYPE_LABELS[$values['type']]) ?></p>
        <?php endif; ?>

        <label>
            Titre <span class="muted">(pour vous retrouver, n’est pas affiché)</span>
            <input type="text" name="title" value="<?= e($values['title']) ?>" required maxlength="150">
            <?= field_error($errors, 'title') ?>
        </label>

        <div data-for-type="image" class="field <?= $isText ? 'hidden' : '' ?>">
            <span class="field-label">Image</span>
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
                <span class="field-label">Texte</span>
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
                            <span class="swatch-name"><?= e($bg['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= field_error($errors, 'background_id') ?>
            </fieldset>
        </div>

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

            <label for="duration_seconds">Durée</label>
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

        <div class="form-actions">
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
