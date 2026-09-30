<?php
/**
 * Liste de choix multiples lisible : une ligne par élément, recherche, tout cocher / décocher,
 * compteur, et (facultatif) résumé des téléviseurs touchés. Reste un simple groupe de cases à cocher :
 * sans JavaScript, le formulaire fonctionne tel quel. Comportement : public/assets/picker.js.
 *
 * @var string $name        nom du champ (ex. 'groups[]')
 * @var array  $items       id => ['label' => string, 'meta' => string, 'devices' => list<int>]
 * @var array  $selected    identifiants cochés
 * @var string $noun        nom des éléments au pluriel, pour les textes (ex. 'groupes')
 * @var string $emptyText   texte si la liste est vide (HTML permis, déjà échappé)
 * @var array|null $deviceNames id => nom ; si fourni, affiche le résumé des téléviseurs touchés
 */
$deviceNames ??= null;
$id ??= null;
?>
<div class="picker" data-picker data-noun="<?= e($noun) ?>"<?= $id !== null ? ' id="' . e($id) . '"' : '' ?>
    <?php if ($deviceNames !== null): ?> data-device-names="<?= e(json_encode($deviceNames, JSON_UNESCAPED_UNICODE)) ?>"<?php endif; ?>>
    <?php if (!$items): ?>
        <p class="hint"><?= $emptyText ?></p>
    <?php else: ?>
        <div class="picker-tools">
            <input type="search" class="picker-search" placeholder="Rechercher parmi les <?= e($noun) ?>…" aria-label="Rechercher parmi les <?= e($noun) ?>" hidden>
            <span class="picker-count muted"></span>
            <button type="button" class="picker-all" hidden>Tout cocher</button>
            <button type="button" class="picker-none" hidden>Tout décocher</button>
        </div>
        <ul class="picker-list">
            <?php foreach ($items as $id => $item): ?>
                <li>
                    <label class="picker-item">
                        <input type="checkbox" name="<?= e($name) ?>" value="<?= (int) $id ?>"
                               data-devices="<?= e(implode(',', $item['devices'] ?? [])) ?>"
                            <?= in_array($id, $selected, true) ? 'checked' : '' ?>>
                        <span class="picker-label"><?= e($item['label']) ?></span>
                        <?php if (($item['meta'] ?? '') !== ''): ?>
                            <span class="picker-meta"><?= e($item['meta']) ?></span>
                        <?php endif; ?>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="picker-nomatch muted" hidden>Aucun résultat.</p>
    <?php endif; ?>
    <?php if ($deviceNames !== null): ?>
        <p class="picker-summary" aria-live="polite"></p>
    <?php endif; ?>
</div>
