<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var array|null $overview */
/** @var string $baseUrl */
/** @var string|null $csrf_shipyard */
/** @var int|null $selectedPlanetId */
$title = $title ?? 'Chantier spatial';
$fleetCount = 0;
if ($overview) {
    $fleetCount = array_sum($overview['fleet']);
}
if (!function_exists('format_duration')) {
    function format_duration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = sprintf('%d h', $hours);
        }
        if ($minutes > 0) {
            $parts[] = sprintf('%d min', $minutes);
        }
        if (($hours === 0 && $minutes === 0) || $remainingSeconds > 0) {
            $parts[] = sprintf('%d s', $remainingSeconds);
        }

        return implode(' ', $parts);
    }
}
ob_start();
?>
<section class="page-header">
    <div>
        <h1>Chantier spatial</h1>
        <?php if ($overview): ?>
            <p class="page-header__subtitle">Organisez la production de vos vaisseaux et renforcez votre flotte orbitale.</p>
        <?php else: ?>
            <p class="page-header__subtitle">Sélectionnez une planète pour accéder à ses hangars.</p>
        <?php endif; ?>
    </div>
    <div class="page-header__actions">
        <?php if (!empty($planets ?? [])): ?>
            <form class="planet-switcher" method="get" action="<?= htmlspecialchars($baseUrl) ?>/shipyard">
                <label class="planet-switcher__label" for="planet-selector-shipyard">Planète</label>
                <select class="planet-switcher__select" id="planet-selector-shipyard" name="planet" data-auto-submit>
                    <?php foreach ($planets as $planetOption): ?>
                        <option value="<?= $planetOption->getId() ?>"<?= ($selectedPlanetId && $planetOption->getId() === $selectedPlanetId) ? ' selected' : '' ?>><?= htmlspecialchars($planetOption->getName()) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <?php if ($overview): ?>
            <a class="button button--ghost" href="<?= htmlspecialchars($baseUrl) ?>/tech-tree?planet=<?= $selectedPlanetId ?>">Voir les prérequis technologiques</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($overview === null): ?>
    <article class="panel">
        <div class="panel__body">
            <p>Aucune planète sélectionnée. Utilisez le sélecteur de planète pour choisir un monde équipé d’un chantier spatial.</p>
        </div>
    </article>
<?php else: ?>
    <?php $queue = $overview['queue'] ?? ['count' => 0, 'jobs' => []]; ?>
    <article class="panel">
        <header class="panel__header">
            <h2>Commandes de vaisseaux</h2>
            <p class="panel__subtitle">Suivi des constructions orbitales.</p>
        </header>
        <div class="panel__body">
            <?php if (($queue['count'] ?? 0) === 0): ?>
                <p class="empty-state">Aucune commande de vaisseau n’est en file. Lancez une production pour étoffer votre flotte.</p>
            <?php else: ?>
                <ul class="queue-list">
                    <?php foreach ($queue['jobs'] as $job): ?>
                        <li class="queue-list__item">
                            <div>
                                <strong><?= htmlspecialchars($job['label'] ?? $job['ship']) ?></strong>
                                <span><?= number_format($job['quantity']) ?> unité(s)</span>
                            </div>
                            <div class="queue-list__timing">
                                <span>Termine dans <?= htmlspecialchars(format_duration((int) $job['remaining'])) ?></span>
                                <time datetime="<?= $job['endsAt']->format('c') ?>"><?= $job['endsAt']->format('d/m H:i') ?></time>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </article>
    <article class="panel panel--wide">
        <header class="panel__header">
            <h2>Hangars de production</h2>
            <p class="panel__subtitle">Niveau de chantier : <?= $overview['shipyardLevel'] ?></p>
        </header>
        <div class="panel__body metrics metrics--compact">
            <div class="metric">
                <span class="metric__label">Capacité du chantier</span>
                <strong class="metric__value">Niveau <?= $overview['shipyardLevel'] ?></strong>
                <span class="metric__hint">Augmentez le chantier pour accélérer la production.</span>
            </div>
            <div class="metric">
                <span class="metric__label">Unités stationnées</span>
                <strong class="metric__value"><?= number_format($fleetCount) ?></strong>
                <span class="metric__hint">Total des vaisseaux actuellement disponibles.</span>
            </div>
        </div>
        <div class="panel__body">
            <?php if ($fleetCount === 0): ?>
                <p class="empty-state">Aucun vaisseau n’est encore construit. Lancez la production pour constituer votre flotte.</p>
            <?php else: ?>
                <ul class="fleet-list">
                    <?php foreach ($overview['fleet'] as $shipKey => $quantity): ?>
                        <li><span class="fleet-list__name"><?= htmlspecialchars($shipKey) ?></span><span class="fleet-list__qty">× <?= number_format($quantity) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </article>

    <div class="shipyard-grid">
        <?php foreach ($overview['categories'] as $category): ?>
            <section class="panel shipyard-category">
                <header class="panel__header">
                    <div>
                        <h2><?= htmlspecialchars($category['label']) ?></h2>
                        <p class="panel__subtitle">Modèles disponibles pour cette classe de vaisseaux.</p>
                    </div>
                    <?php if (!empty($category['image'])): ?>
                        <img class="panel__illustration" src="<?= htmlspecialchars($baseUrl . '/' . $category['image']) ?>" alt="">
                    <?php endif; ?>
                </header>
                <div class="panel__body shipyard-list">
                    <?php foreach ($category['items'] as $item): ?>
                        <?php $definition = $item['definition']; ?>
                        <article class="ship-card <?= $item['canBuild'] ? '' : 'is-locked' ?>">
                            <header class="ship-card__header">
                                <div>
                                    <h3><?= htmlspecialchars($definition->getLabel()) ?></h3>
                                    <p class="ship-card__role"><?= htmlspecialchars($definition->getRole()) ?></p>
                                </div>
                                <?php if ($definition->getImage()): ?>
                                    <img class="ship-card__illustration" src="<?= htmlspecialchars($baseUrl . '/' . $definition->getImage()) ?>" alt="">
                                <?php endif; ?>
                            </header>
                            <p class="ship-card__description"><?= htmlspecialchars($definition->getDescription()) ?></p>
                            <div class="ship-card__stats">
                                <?php foreach ($definition->getStats() as $label => $value): ?>
                                    <div class="mini-stat">
                                        <span class="mini-stat__label"><?= htmlspecialchars(ucfirst($label)) ?></span>
                                        <strong class="mini-stat__value"><?= number_format($value) ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="ship-card__content">
                                <div class="ship-card__costs">
                                    <h4>Coût unitaire</h4>
                                    <ul class="resource-list">
                                        <?php foreach ($definition->getBaseCost() as $resource => $amount): ?>
                                            <li>
                                                <svg class="icon icon-sm" aria-hidden="true">
                                                    <use href="<?= htmlspecialchars($baseUrl) ?>/assets/svg/sprite.svg#icon-<?= htmlspecialchars($resource) ?>"></use>
                                                </svg>
                                                <span><?= number_format($amount) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                        <li>
                                            <svg class="icon icon-sm" aria-hidden="true">
                                                <use href="<?= htmlspecialchars($baseUrl) ?>/assets/svg/sprite.svg#icon-time"></use>
                                            </svg>
                                            <span><?= htmlspecialchars(format_duration((int) $definition->getBuildTime())) ?></span>
                                        </li>
                                    </ul>
                                </div>
                                <?php if (!$item['requirements']['ok']): ?>
                                    <div class="ship-card__requirements">
                                        <h4>Pré-requis</h4>
                                        <ul>
                                            <?php foreach ($item['requirements']['missing'] as $missing): ?>
                                                <li><?= htmlspecialchars($missing['label']) ?> (<?= $missing['current'] ?? 0 ?>/<?= $missing['level'] ?>)</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <footer class="ship-card__footer">
                                <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/shipyard?planet=<?= $selectedPlanetId ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_shipyard ?? '') ?>">
                                    <input type="hidden" name="ship" value="<?= htmlspecialchars($definition->getKey()) ?>">
                                    <label class="ship-card__quantity">
                                        <span>Quantité</span>
                                        <input type="number" name="quantity" min="1" value="1" <?= $item['canBuild'] ? '' : 'disabled' ?>>
                                    </label>
                                    <button class="button button--primary" type="submit" <?= $item['canBuild'] ? '' : 'disabled' ?>><?= $item['canBuild'] ? 'Construire' : 'Pré-requis manquants' ?></button>
                                </form>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/base.php';
