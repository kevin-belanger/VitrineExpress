<?php

use VitrineExpress\Media;

/** @var array $message */
?>
<?php if ($message['type'] === 'image'): ?>
    <img class="thumb" src="<?= e(Media::url($app, $message['media_path'], true)) ?>" alt="" loading="lazy">
<?php else: ?>
    <span class="thumb thumb-text" style="background: <?= e($message['background_css'] ?? '#000') ?>; color: <?= e($message['background_color'] ?? '#fff') ?>">Aa</span>
<?php endif; ?>
