<?php

use VitrineExpress\Devices;
use VitrineExpress\View;

/**
 * Cellule « Affiche en ce moment » (liste des périphériques et mise à jour en direct) :
 * toujours une vignette de même taille, pour que le tableau ne bouge pas quand elle change.
 */
?>
<?php if ($current): ?>
    <a class="current-thumb" href="<?= e(url('/admin/messages/' . $current['id'] . '/edit')) ?>"
       title="<?= e($current['title']) ?>" aria-label="<?= e('Modifier « ' . $current['title'] . ' »') ?>">
        <?= View::render('messages/_thumb', ['message' => $current, 'app' => $app], null) ?>
    </a>
<?php elseif ($status === Devices::STATUS_ONLINE): ?>
    <span class="current-thumb current-placeholder" title="Aucun message : affiche l’heure et la date">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>
    </span>
<?php else: ?>
    <span class="current-thumb current-placeholder" title="<?= e(Devices::STATUS_LABELS[$status]) ?>">—</span>
<?php endif; ?>
