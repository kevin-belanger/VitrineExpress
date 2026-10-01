<?php

use VitrineExpress\Devices;
use VitrineExpress\View;

/** Cellule « Affiche en ce moment » (liste des périphériques et mise à jour en direct). */
?>
<?php if ($current): ?>
    <div class="current-message" title="<?= e($current['title']) ?>">
        <?= View::render('messages/_thumb', ['message' => $current, 'app' => $app], null) ?>
        <span class="current-message-title"><?= e($current['title']) ?></span>
    </div>
<?php elseif ($status === Devices::STATUS_ONLINE): ?>
    <span class="muted">L’heure et la date (aucun message)</span>
<?php else: ?>
    <span class="muted">—</span>
<?php endif; ?>
