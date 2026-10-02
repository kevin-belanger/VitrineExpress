<?php

use VitrineExpress\View;

/** Message d'un autre : le gestionnaire ne change que sa diffusion dans son périmètre (MessageController). */
$author = $message['author_name'] ?? 'un compte supprimé';
?>
<div class="page-head">
    <h1>Diffusion</h1>
</div>

<div class="split split-wide">
    <form method="post" action="<?= e(url('/admin/messages/' . $message['id'])) ?>" class="card form">
        <?= csrf_field() ?>
        <div class="message-heading">
            <strong><?= e($message['title']) ?></strong>
            <span class="muted">
                Créé par <?= e($author) ?> ·
                <?= e(format_datetime($message['start_at'])) ?><?= $message['end_at'] ? ' au ' . e(format_datetime($message['end_at'])) : ', sans fin' ?> ·
                <?= (int) $message['duration_seconds'] ?> s
            </span>
        </div>

        <?= View::render('messages/_targets', [
            'values' => $values,
            'groups' => $groups,
            'devices' => $devices,
            'canTargetAll' => false,
            'canEditTargets' => $canEditTargets,
            'otherTargets' => $otherTargets,
        ], null) ?>

        <div class="form-actions">
            <?php if ($canEditTargets): ?>
                <button type="submit" class="button primary">Enregistrer</button>
                <a class="button" href="<?= e(url('/admin/messages')) ?>">Annuler</a>
            <?php else: ?>
                <a class="button" href="<?= e(url('/admin/messages')) ?>">Retour</a>
            <?php endif; ?>
        </div>
    </form>

    <aside class="preview-panel">
        <p class="preview-label">Aperçu sur un écran 16:9</p>
        <div class="preview-frame thumb-slide" data-slide="<?= e(json_encode($slide, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></div>
    </aside>
</div>
