<?php

use VitrineExpress\Messages;

$isNew = $message === null;
$isText = $values['type'] === Messages::TYPE_TEXT;
$action = $isNew ? '/admin/messages' : '/admin/messages/' . $message['id'];
?>
<div class="page-head">
    <h1><?= e($title) ?></h1>
</div>

<div class="split split-wide">
    <form method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data" class="card form" id="message-form"
          data-image-url="<?= e($imageUrl ?? '') ?>">
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

        <div data-for-type="image" class="<?= $isText ? 'hidden' : '' ?>">
            <label>
                Image <span class="muted">(JPG, PNG, WebP ou GIF, <?= e($maxUpload) ?> maximum<?= $isNew ? '' : ' ; laisser vide pour garder l’image actuelle' ?>)</span>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                <?= field_error($errors, 'image') ?>
                <?php if ($errors && !$isText && $isNew && !isset($errors['image'])): ?>
                    <p class="hint">Sélectionnez de nouveau l’image avant d’enregistrer.</p>
                <?php endif; ?>
            </label>
            <p class="hint">L’image est affichée au complet, sans être recadrée. Idéalement au format 16:9 (ex. 1920 × 1080).</p>
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

        <fieldset>
            <legend>Période d’affichage</legend>
            <div class="row">
                <label>
                    Début
                    <span class="inline-fields">
                        <input type="date" name="start_date" value="<?= e($values['start_date']) ?>" required>
                        <input type="time" name="start_time" value="<?= e($values['start_time']) ?>">
                    </span>
                    <?= field_error($errors, 'start') ?>
                </label>
                <label>
                    Fin <span class="muted">(vide = sans fin)</span>
                    <span class="inline-fields">
                        <input type="date" name="end_date" value="<?= e($values['end_date']) ?>">
                        <input type="time" name="end_time" value="<?= e($values['end_time']) ?>">
                    </span>
                    <?= field_error($errors, 'end') ?>
                </label>
            </div>
        </fieldset>

        <label class="short">
            Durée à l’écran (secondes)
            <input type="number" name="duration_seconds" value="<?= e($values['duration_seconds']) ?>"
                   min="<?= Messages::MIN_DURATION ?>" max="<?= Messages::MAX_DURATION ?>" required>
            <?= field_error($errors, 'duration_seconds') ?>
        </label>

        <fieldset>
            <legend>Afficher sur</legend>
            <label class="check-strong">
                <input type="checkbox" name="all_devices" value="1" <?= $values['all_devices'] ? 'checked' : '' ?>>
                Tous les téléviseurs
            </label>
            <?php if ($groups): ?>
                <div class="checks">
                    <?php foreach ($groups as $groupId => $groupName): ?>
                        <label>
                            <input type="checkbox" name="groups[]" value="<?= (int) $groupId ?>" <?= in_array($groupId, $values['group_ids'], true) ? 'checked' : '' ?>>
                            <?= e($groupName) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="hint">Aucun groupe. <a href="<?= e(url('/admin/groups/new')) ?>">Créer un groupe</a></p>
            <?php endif; ?>
            <?= field_error($errors, 'targets') ?>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="button primary">Enregistrer</button>
            <a class="button" href="<?= e(url('/admin/messages')) ?>">Annuler</a>
        </div>
    </form>

    <aside class="preview-panel">
        <p class="preview-label">Aperçu sur un écran 16:9</p>
        <div class="preview-frame" id="preview"></div>
    </aside>
</div>
