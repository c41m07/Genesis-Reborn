<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets Liste des planètes. */
/** @var array $tree Données de l’arbre technologique. */
/** @var string $baseUrl URL de base pour les liens. */
/** @var int|null $selectedPlanetId Identifiant de la planète choisie. */
$title = $title ?? 'Arbre technologique';
$categories = $tree['categories'] ?? [];
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
foreach ($categories as $category) {
    foreach ($category['items'] as $item) {
        $nodeId = $category['key'] . ':' . $item['key'];
        $item['state'] = getTechState($item['requires'] ?? []);
        $item['category'] = $category['label'];
        $nodes[$nodeId] = $item;
        if ($initialNodeId === null) {
            $initialNodeId = $nodeId;
        }
    }
}
$nodesJson = json_encode($nodes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$nodesJson = $nodesJson !== false ? $nodesJson : '{}';
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

<?php if (empty($categories)): ?>
    <article class="panel">
        <div class="panel__body">
            <p>Aucune donnée technologique disponible pour cette planète.</p>
        </div>
    </article>
<?php else: ?>
    <section class="tech-tree" data-base-url="<?= htmlspecialchars($baseUrl) ?>">
        <div class="tech-tree__layout">
            <aside class="tech-tree__sidebar">
                <?php foreach ($categories as $category): ?>
                    <details class="tech-section">
                        <summary class="tech-section__summary">
                            <span class="tech-section__title" role="heading" aria-level="2"><?= htmlspecialchars($category['label']) ?></span>
                            <span class="tech-section__icon" aria-hidden="true"></span>
                        </summary>
                        <ul class="tech-section__list">
                            <?php foreach ($category['items'] as $item): ?>
                                <?php $nodeId = $category['key'] . ':' . $item['key']; ?>
                                <?php $state = getTechState($item['requires'] ?? []); ?>
                                <li>
                                    <button class="tech-node-link<?= $state['allMet'] ? ' tech-node-link--ready' : '' ?>" type="button" data-tech-target="<?= htmlspecialchars($nodeId) ?>" data-tech-ready="<?= $state['allMet'] ? '1' : '0' ?>">
                                        <span class="tech-node-link__label"><?= htmlspecialchars($item['label']) ?></span>
                                        <?php if (isset($item['level'])): ?>
                                            <span class="tech-node-link__level">Niveau <?= number_format((int) $item['level']) ?></span>
                                        <?php endif; ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endforeach; ?>
            </aside>
            <section class="tech-tree__details" id="tech-tree-detail" data-initial="<?= htmlspecialchars($initialNodeId ?? '') ?>" data-base-url="<?= htmlspecialchars($baseUrl) ?>">
                <p class="tech-detail__placeholder">Sélectionnez un élément pour afficher ses prérequis.</p>
            </section>
        </div>
    </section>
    <script type="application/json" id="tech-tree-data"><?= $nodesJson ?></script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/base.php';
