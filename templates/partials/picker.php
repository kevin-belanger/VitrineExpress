<?php
/**
 * Liste de choix multiples lisible : une ligne par élément, recherche, compteur et (facultatif) résumé des
 * périphériques touchés. Peut contenir plusieurs sections (ex. Groupes puis Périphériques) : un groupe coché
 * montre alors les périphériques qu'il inclut. Reste un simple ensemble de cases à cocher : sans JavaScript,
 * le formulaire fonctionne tel quel. Comportement : public/assets/picker.js.
 *
 * Une section : $name, $items, $selected. Plusieurs : $sections = list<array{key, title, name, items, selected}>,
 * key = 'groups' ou 'devices'. Un élément : ['label' => string, 'meta' => string, 'devices' => list<int>].
 *
 * @var string     $noun        nom des éléments au pluriel, pour les textes (ex. 'groupes')
 * @var string     $emptyText   texte si tout est vide (HTML permis, déjà échappé)
 * @var array|null $deviceNames id => nom ; si fourni, affiche le résumé des périphériques touchés
 * @var string|null $id         identifiant HTML du sélecteur
 */
$deviceNames ??= null;
$id ??= null;
$sections ??= [['key' => 'items', 'title' => '', 'name' => $name, 'items' => $items, 'selected' => $selected]];
$sections = array_values(array_filter($sections, static fn (array $s): bool => (bool) $s['items']));
$total = array_sum(array_map(static fn (array $s): int => count($s['items']), $sections));
$multi = count($sections) > 1;
?>
<div class="picker" data-picker data-noun="<?= e($noun) ?>" data-total="<?= $total ?>"<?= $multi ? ' data-multi' : '' ?><?= $id !== null ? ' id="' . e($id) . '"' : '' ?>
    <?php if ($deviceNames !== null): ?> data-device-names="<?= e(json_encode($deviceNames, JSON_UNESCAPED_UNICODE)) ?>"<?php endif; ?>>
    <?php if ($total === 0): ?>
        <p class="hint"><?= $emptyText ?></p>
    <?php else: ?>
        <div class="picker-tools">
            <input type="search" class="picker-search" placeholder="Rechercher parmi les <?= e($noun) ?>…" aria-label="Rechercher parmi les <?= e($noun) ?>" hidden>
            <?php if (!$multi): // avec plusieurs sections, le résumé des périphériques touchés suffit ?>
                <span class="picker-count muted"></span>
            <?php endif; ?>
            <button type="button" class="picker-all" hidden>Tout cocher</button>
            <button type="button" class="picker-none" hidden>Tout décocher</button>
        </div>
        <ul class="picker-list">
            <?php foreach ($sections as $section): ?>
                <?php if ($multi): ?>
                    <li class="picker-section" data-section="<?= e($section['key']) ?>"><?= e($section['title']) ?></li>
                <?php endif; ?>
                <?php foreach ($section['items'] as $itemId => $item): ?>
                    <li data-section="<?= e($section['key']) ?>">
                        <label class="picker-item">
                            <input type="checkbox" name="<?= e($section['name']) ?>" value="<?= (int) $itemId ?>"
                                   data-devices="<?= e(implode(',', $item['devices'] ?? [])) ?>"
                                <?= in_array($itemId, $section['selected'], true) ? 'checked' : '' ?>>
                            <span class="picker-label"><?= e($item['label']) ?></span>
                            <span class="picker-meta" data-meta="<?= e($item['meta'] ?? '') ?>"><?= e($item['meta'] ?? '') ?></span>
                        </label>
                    </li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
        <p class="picker-nomatch muted" hidden>Aucun résultat.</p>
    <?php endif; ?>
    <?php if ($deviceNames !== null): ?>
        <p class="picker-summary" aria-live="polite"></p>
    <?php endif; ?>
</div>
