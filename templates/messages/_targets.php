<?php

use VitrineExpress\View;

/**
 * Partie « Afficher sur » d'un message, selon les droits du compte connecté :
 * administrateur = « Tous » ou un choix libre ; gestionnaire = son périmètre seulement,
 * les autres cibles étant rappelées en une ligne.
 *
 * @var array $values         group_ids, device_ids, all_devices
 * @var array $groups         groupes proposés (périmètre)
 * @var array $devices        périphériques proposés (périmètre)
 * @var bool  $canTargetAll
 * @var bool  $canEditTargets
 * @var array $otherTargets   ['names' => …, 'device_ids' => …] hors périmètre
 * @var bool  $contentNote    rappeler que les modifications du contenu s'appliquent aussi ailleurs
 */
$contentNote ??= false;
$others = $otherTargets['names'];
?>
<fieldset>
    <legend>Afficher sur</legend>
    <?php if (!$canEditTargets): ?>
        <p class="target-fixed">Tous les périphériques d’affichage <span class="muted">· choisi par un administrateur</span></p>
    <?php else: ?>
        <?php if ($canTargetAll): ?>
            <div class="target-modes">
                <label class="target-mode">
                    <input type="radio" name="all_devices" value="1" data-choice-target="target-choice" <?= $values['all_devices'] ? 'checked' : '' ?>>
                    <span><strong>Tous les périphériques d’affichage</strong><small>Y compris ceux ajoutés plus tard</small></span>
                </label>
                <label class="target-mode">
                    <input type="radio" name="all_devices" value="0" data-choice-target="target-choice" <?= $values['all_devices'] ? '' : 'checked' ?>>
                    <span><strong>Choisir</strong><small><?= $groups ? 'Des groupes, des périphériques, ou les deux' : 'Les périphériques cochés' ?></small></span>
                </label>
            </div>
        <?php endif; ?>
        <?php // Caché en mode « Tous » (picker.js) ; les cases restent cochées si on revient à « Choisir ». ?>
        <div id="target-choice" class="form">
            <?= View::render('partials/picker', [
                'id' => 'target-picker',
                'sections' => [
                    ['key' => 'groups', 'title' => 'Groupes', 'name' => 'groups[]', 'items' => $groups, 'selected' => $values['group_ids']],
                    ['key' => 'devices', 'title' => 'Périphériques d’affichage', 'name' => 'devices[]', 'items' => $devices, 'selected' => $values['device_ids']],
                ],
                'noun' => $groups ? 'groupes et périphériques' : 'périphériques',
                'emptyText' => $canTargetAll
                    ? 'Aucun périphérique d’affichage pour l’instant. <a href="' . e(url('/admin/devices/new')) . '">Ajouter un périphérique d’affichage</a>'
                    : 'Aucun groupe ne vous est confié : le message sera gardé comme brouillon.',
                'summary' => true,
                'fixedDevices' => $otherTargets['device_ids'],
            ], null) ?>
            <?php if ($groups): ?>
                <p class="hint">Un groupe coché inclut aussi les périphériques qu’on y ajoutera plus tard.</p>
            <?php endif; ?>
        </div>
        <?php if ($others): ?>
            <p class="hint">Aussi affiché dans : <?= e(implode(', ', $others)) ?>.<?= $contentNote ? ' Vos modifications s’y appliqueront aussi.' : '' ?></p>
        <?php endif; ?>
    <?php endif; ?>
</fieldset>
