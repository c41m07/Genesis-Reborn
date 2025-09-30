<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets Liste des planètes. */
/** @var array $tree Données de l’arbre technologique. */
/** @var string $baseUrl URL de base pour les liens. */
/** @var int|null $selectedPlanetId Identifiant de la planète choisie. */
$title = $title ?? 'Arbre technologique';
$groups = $tree['groups'] ?? [];
if (!is_array($groups)) {
    $groups = [];
}
$nodes = [];
$initialNodeId = null;
if (!function_exists('getTechState')) {
    function getTechState(array $requirements): array
    {
        $total = count($requirements);
        $met = 0;
        foreach ($requirements as $requirement) {
            if (!empty($requirement['met'])) {
                ++$met;
            }
        }

        return [
            'met' => $met,
            'total' => $total,
            'allMet' => $total === 0 ? true : ($met === $total),
        ];
    }
}
$preparedGroups = [];
foreach ($groups as $group) {
    if (!is_array($group)) {
        continue;
    }

    $groupKey = (string) ($group['key'] ?? '');
    $groupLabel = (string) ($group['label'] ?? '');
    $rawCategories = $group['categories'] ?? [];
    if (!is_array($rawCategories)) {
        continue;
    }

    $preparedCategories = [];
    foreach ($rawCategories as $category) {
        if (!is_array($category)) {
            continue;
        }

        $categoryKey = (string) ($category['key'] ?? '');
        if ($categoryKey === '') {
            continue;
        }

        $categoryLabel = (string) ($category['label'] ?? '');
        $rawItems = $category['items'] ?? [];
        if (!is_array($rawItems)) {
            continue;
        }

        $preparedItems = [];
        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemKey = (string) ($item['key'] ?? '');
            if ($itemKey === '') {
                continue;
            }

            $nodeId = $categoryKey . ':' . $itemKey;
            $state = getTechState($item['requires'] ?? []);
            $itemData = $item;
            $itemData['state'] = $state;
            $itemData['category'] = $categoryLabel;
            $itemData['group'] = $groupLabel;
            $itemData['categoryKey'] = $categoryKey;
            $itemData['groupKey'] = $groupKey;
            $nodes[$nodeId] = $itemData;
            if ($initialNodeId === null) {
                $initialNodeId = $nodeId;
            }

            $preparedItems[] = $item;
        }

        if ($preparedItems === []) {
            continue;
        }

        $preparedCategories[] = [
            'key' => $categoryKey,
            'label' => $categoryLabel,
            'items' => $preparedItems,
        ];
    }

    if ($preparedCategories === []) {
        continue;
    }

    $preparedGroups[] = [
        'key' => $groupKey,
        'label' => $groupLabel,
        'categories' => $preparedCategories,
    ];
}
$groups = $preparedGroups;
$nodesJson = json_encode($nodes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$nodesJson = $nodesJson !== false ? $nodesJson : '{}';
$hasNodes = !empty($nodes);
ob_start();
?>
    <section class="page-header">
        <div>
            <h1>Arbre technologique</h1>
            <p class="page-header__subtitle">Visualisez bâtiments, recherches et vaisseaux ainsi que leurs prérequis.</p>
        </div>
        <div class="page-header__actions">
            <?php if ($selectedPlanetId): ?>
                <a class="button button--ghost" href="<?= htmlspecialchars($baseUrl) ?>/research?planet=<?= $selectedPlanetId ?>">Retour au laboratoire</a>
            <?php endif; ?>
        </div>
    </section>

<?php if (!$hasNodes): ?>
    <article class="panel">
        <div class="panel__body">
            <p>Aucune donnée technologique disponible pour cette planète.</p>
        </div>
    </article>
<?php else: ?>
    <section class="tech-tree" data-base-url="<?= htmlspecialchars($baseUrl) ?>">
        <div class="tech-tree__layout">
            <aside class="tech-tree__sidebar">
                <?php foreach ($groups as $group): ?>
                    <?php $groupKey = (string) ($group['key'] ?? ''); ?>
                    <?php $groupLabel = (string) ($group['label'] ?? ''); ?>
                    <?php $categories = $group['categories'] ?? []; ?>
                    <?php if (empty($categories)) {
                        continue;
                    } ?>
                    <details class="tech-section tech-section--group" data-tech-group="<?= htmlspecialchars($groupKey) ?>">
                        <summary class="tech-section__summary">
                            <span class="tech-section__title" role="heading" aria-level="2"><?= htmlspecialchars($groupLabel) ?></span>
                            <span class="tech-section__icon" aria-hidden="true"></span>
                        </summary>
                        <div class="tech-section__groups ">
                            <?php foreach ($categories as $category): ?>
                                <?php $categoryKey = (string) ($category['key'] ?? ''); ?>
                                <?php $categoryLabel = (string) ($category['label'] ?? ''); ?>
                                <?php $items = $category['items'] ?? []; ?>
                                <?php if (empty($items)) {
                                    continue;
                                } ?>
                                <details class="tech-subsection" data-tech-category="<?= htmlspecialchars($categoryKey) ?>">
                                    <summary class="tech-subsection__summary">
                                        <span class="tech-subsection__title" role="heading" aria-level="3"><?= htmlspecialchars($categoryLabel) ?></span>
                                        <span class="tech-subsection__icon" aria-hidden="true"></span>
                                    </summary>
                                    <ul class="tech-section__list tech-section__list--nested">
                                        <?php foreach ($items as $item): ?>
                                            <?php $itemKey = (string) ($item['key'] ?? ''); ?>
                                            <?php if ($itemKey === '') {
                                                continue;
                                            } ?>
                                            <?php $nodeId = $categoryKey . ':' . $itemKey; ?>
                                            <?php $node = $nodes[$nodeId] ?? null; ?>
                                            <?php $state = $node['state'] ?? getTechState($item['requires'] ?? []); ?>
                                            <li>
                                                <button
                                                    class="tech-node-link<?= !empty($state['allMet']) ? ' tech-node-link--ready' : '' ?>"
                                                    type="button"
                                                    data-tech-target="<?= htmlspecialchars($nodeId) ?>"
                                                    data-tech-ready="<?= !empty($state['allMet']) ? '1' : '0' ?>"
                                                    data-tech-group="<?= htmlspecialchars($groupKey) ?>"
                                                    data-tech-category="<?= htmlspecialchars($categoryKey) ?>"
                                                >
                                                    <span class="tech-node-link__label"><?= htmlspecialchars($item['label'] ?? $itemKey) ?></span>
                                                    <?php if (isset($item['level'])): ?>
                                                        <span class="tech-node-link__level">Niveau <?= number_format((int) $item['level']) ?></span>
                                                    <?php endif; ?>
                                                </button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </aside>
            <div class="tech-tree__details-column">
                <section
                        class="tech-tree__details tech-tree__details--sticky"
                        id="tech-tree-detail"
                        data-initial="<?= htmlspecialchars($initialNodeId ?? '') ?>"
                        data-base-url="<?= htmlspecialchars($baseUrl) ?>"
                        data-planet-id="<?= $selectedPlanetId !== null ? (int) $selectedPlanetId : '' ?>"
                >
                    <p class="tech-detail__placeholder">Sélectionnez un élément pour afficher ses prérequis.</p>
                </section>
            </div>
        </div>
    </section>
    <script type="application/json" id="tech-tree-data"><?= $nodesJson ?></script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/base.php';
