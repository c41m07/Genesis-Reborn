<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var array $tree */
/** @var string $baseUrl */
/** @var int|null $selectedPlanetId */
$title = $title ?? 'Arbre technologique';
$categories = $tree['categories'] ?? [];
$nodes = [];
$initialNodeId = null;
foreach ($categories as $category) {
    foreach ($category['items'] as $item) {
        $nodeId = $category['key'] . ':' . $item['key'];
        $item['category'] = $category['label'];
        $nodes[$nodeId] = $item;
        if ($initialNodeId === null) {
            $initialNodeId = $nodeId;
        }
    }
}
$nodesJson = htmlspecialchars(json_encode($nodes, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
ob_start();
?>
<section class="page-header">
    <div>
        <h1>Arbre technologique</h1>
        <p class="page-header__subtitle">Visualisez bâtiments, recherches et vaisseaux ainsi que leurs prérequis.</p>
    </div>
    <div class="page-header__actions">
        <?php if (!empty($planets ?? [])): ?>
            <form class="planet-switcher" method="get" action="<?= htmlspecialchars($baseUrl) ?>/tech-tree">
                <label class="planet-switcher__label" for="planet-selector-tech">Planète</label>
                <select class="planet-switcher__select" id="planet-selector-tech" name="planet" data-auto-submit>
                    <?php foreach ($planets as $planetOption): ?>
                        <option value="<?= $planetOption->getId() ?>"<?= ($selectedPlanetId && $planetOption->getId() === $selectedPlanetId) ? ' selected' : '' ?>><?= htmlspecialchars($planetOption->getName()) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
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
                    <div class="tech-section">
                        <h2 class="tech-section__title"><?= htmlspecialchars($category['label']) ?></h2>
                        <ul class="tech-section__list">
                            <?php foreach ($category['items'] as $item): ?>
                                <?php $nodeId = $category['key'] . ':' . $item['key']; ?>
                                <li>
                                    <button class="tech-node-link" type="button" data-tech-target="<?= htmlspecialchars($nodeId) ?>">
                                        <span class="tech-node-link__label"><?= htmlspecialchars($item['label']) ?></span>
                                        <?php if (isset($item['level'])): ?>
                                            <span class="tech-node-link__level">Niveau <?= number_format((int) $item['level']) ?></span>
                                        <?php endif; ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
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
