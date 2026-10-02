<?php

use VitrineExpress\Dashboard;
use VitrineExpress\Messages;
use VitrineExpress\View;

/**
 * Une ligne du tableau de bord (affichée seulement si $count > 0).
 * $tone : danger, warning, accent ou muted.
 */
$line = static function (int $count, string $tone, string $href, string $title, string $detail): string {
    if ($count === 0) {
        return '';
    }
    return '<a class="dash-line" href="' . e($href) . '">'
        . '<span class="dash-dot dot-' . e($tone) . '" aria-hidden="true"></span>'
        . '<span class="dash-text">' . e($title) . '<small>' . e($detail) . '</small></span>'
        . '<span class="dash-chevron" aria-hidden="true">›</span></a>';
};
$plural = static fn (int $n, string $one, string $many): string => $n . ' ' . ($n > 1 ? $many : $one);
// Lien vers la fiche si un seul périphérique est concerné, sinon vers la liste
// (toujours la liste pour un gestionnaire : les fiches sont réservées aux administrateurs).
$deviceLink = static fn (array $rows): string => $isAdmin && count($rows) === 1
    ? url('/admin/devices/' . $rows[0]['id'] . '/edit')
    : url('/admin/devices');

$offlineDetail = Dashboard::names($tv['offline'], static fn (array $d): string => $d['name'] . ' (vu ' . time_ago($d['last_seen_at']) . ')');

$tvAllGood = $tv['total'] > 0 && $tv['online'] === $tv['total'] && !$tv['idle'];
$live = count($msg['live']);
$thumbsShown = 6;
$next = $msg['upcoming'][0] ?? null;
?>
<div class="page-head">
    <h1>Tableau de bord</h1>
</div>

<div class="dash">
    <section class="dash-card" aria-labelledby="dash-tv">
        <p class="dash-label" id="dash-tv">Périphériques d’affichage</p>
        <?php if ($tv['total'] === 0 && !$isAdmin): ?>
            <p class="dash-big">Aucun périphérique d’affichage</p>
            <p class="dash-sub">Vos groupes n’ont pas encore de périphérique d’affichage.</p>
        <?php elseif ($tv['total'] === 0): ?>
            <p class="dash-big">Aucun périphérique d’affichage</p>
            <p class="dash-sub">Ajoutez un périphérique d’affichage pour obtenir son code de connexion.</p>
            <a class="button primary" href="<?= e(url('/admin/devices/new')) ?>">Ajouter un périphérique d’affichage</a>
        <?php else: ?>
            <p class="dash-big">
                <?php if ($tvAllGood): ?><span class="dash-ok" aria-hidden="true">✓</span><?php endif; ?>
                <?= $tv['online'] ?> sur <?= $tv['total'] ?> en ligne
            </p>
            <?php if ($tvAllGood): ?><p class="dash-sub">Tout fonctionne.</p><?php else: ?><div class="dash-gap"></div><?php endif; ?>

            <?= $line(count($tv['offline']), 'danger', $deviceLink($tv['offline']),
                $plural(count($tv['offline']), 'hors ligne', 'hors ligne'), $offlineDetail) ?>
            <?= $line(count($tv['disconnected']), 'warning', $deviceLink($tv['disconnected']),
                count($tv['disconnected']) > 1
                    ? count($tv['disconnected']) . ' périphériques ne sont pas connectés'
                    : '1 périphérique n’est pas connecté',
                // Le code de connexion n'est visible que par les administrateurs.
                !$isAdmin
                    ? Dashboard::names($tv['disconnected'])
                    : (count($tv['disconnected']) > 1
                        ? 'Entrez leur code sur chaque périphérique pour les connecter.'
                        : 'Entrez son code sur le périphérique pour le connecter.')) ?>
            <?= $line(count($tv['idle']), 'muted', $deviceLink($tv['idle']),
                $plural(count($tv['idle']), 'en ligne sans message à afficher', 'en ligne sans message à afficher'),
                Dashboard::names($tv['idle']) . (count($tv['idle']) > 1 ? ' affichent' : ' affiche') . ' seulement l’heure') ?>

            <a class="dash-more" href="<?= e(url('/admin/devices')) ?>">Voir les périphériques d’affichage →</a>
        <?php endif; ?>
    </section>

    <section class="dash-card" aria-labelledby="dash-msg">
        <p class="dash-label" id="dash-msg">Messages</p>
        <?php if ($msg['total'] === 0): ?>
            <p class="dash-big">Aucun message</p>
            <p class="dash-sub">Créez une image ou un texte à diffuser sur vos périphériques d’affichage.</p>
            <a class="button primary" href="<?= e(url('/admin/messages/new')) ?>">Créer un message</a>
        <?php else: ?>
            <p class="dash-big"><?= $live === 0 ? 'Aucun message' : $plural($live, 'message', 'messages') ?> en diffusion</p>
            <p class="dash-sub">
                <?= $live > 0
                    ? 'destinés à ' . $plural($msg['reached'], 'périphérique', 'périphériques')
                    : 'Les périphériques connectés affichent l’heure et la date.' ?>
            </p>
            <?php if ($live > 0): ?>
                <?php // Miniatures des premiers messages en diffusion (le total est dans le titre), puis la liste complète. ?>
                <div class="dash-thumbs">
                    <?php foreach (array_slice($msg['live'], 0, $thumbsShown) as $message): ?>
                        <?php if ($access->canEdit($message)): ?>
                            <a class="current-thumb" href="<?= e(url('/admin/messages/' . $message['id'] . '/edit')) ?>"
                               title="<?= e($message['title']) ?>" aria-label="<?= e($message['title']) ?>">
                                <?= View::render('messages/_thumb', ['message' => $message, 'app' => $app], null) ?>
                            </a>
                        <?php else: ?>
                            <span class="current-thumb" title="<?= e($message['title']) ?>">
                                <?= View::render('messages/_thumb', ['message' => $message, 'app' => $app], null) ?>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <a class="button small" href="<?= e(url('/admin/messages?status=' . Messages::FILTER_LIVE)) ?>">Détails</a>
                </div>
            <?php endif; ?>

            <?= $line(count($msg['ending']), 'warning', url('/admin/messages?status=' . Messages::FILTER_ENDING),
                $plural(count($msg['ending']), 'se termine', 'se terminent') . ' dans les ' . Messages::ENDING_SOON_HOURS . ' h',
                Dashboard::names($msg['ending'], 'title')) ?>
            <?= $line(count($msg['upcoming']), 'accent', url('/admin/messages?status=' . Messages::STATUS_UPCOMING),
                $plural(count($msg['upcoming']), 'à venir', 'à venir'),
                $next ? 'Prochain : ' . $next['title'] . ', ' . format_datetime($next['start_at']) : '') ?>
            <?= $line(count($msg['unbroadcast']), 'muted', url('/admin/messages?status=' . Messages::FILTER_UNBROADCAST),
                $plural(count($msg['unbroadcast']), 'non diffusé', 'non diffusés'),
                'Aucun périphérique visé : ' . Dashboard::names($msg['unbroadcast'], 'title')) ?>
            <?= $line($msg['expired'], 'muted', url('/admin/messages?status=' . Messages::STATUS_EXPIRED),
                $plural($msg['expired'], 'expiré', 'expirés'), $isAdmin ? 'À supprimer quand vous voulez' : '') ?>

            <a class="dash-more" href="<?= e(url('/admin/messages')) ?>">Voir les messages →</a>
        <?php endif; ?>
    </section>
</div>
