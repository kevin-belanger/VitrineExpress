<?php

use VitrineExpress\Media;
use VitrineExpress\Messages;

/** @var array $message avec background_css et background_color pour un message texte */
?>
<?php if ($message['type'] === 'image'): ?>
    <img class="thumb" src="<?= e(Media::url($app, $message['media_path'], true)) ?>" alt="" loading="lazy">
<?php else: ?>
    <?php // Rendu réduit du vrai message (public/assets/slide.js), identique à ce qu'affiche la télé. ?>
    <span class="thumb thumb-slide" data-slide="<?= e(json_encode(Messages::toSlide($app, $message), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
          style="background: <?= e($message['background_css'] ?? '#000') ?>"></span>
<?php endif; ?>
