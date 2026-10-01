<?php

use VitrineExpress\View;

/** Cellule « État » (liste des périphériques et mise à jour en direct). */
?>
<?= View::render('devices/_status', ['status' => $status], null) ?><br>
<span class="muted small"><?= e(time_ago($lastSeen)) ?></span>
