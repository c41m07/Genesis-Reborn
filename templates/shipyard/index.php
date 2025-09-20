<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var array|null $overview */
/** @var string $baseUrl */
/** @var string|null $csrf_shipyard */
/** @var int|null $selectedPlanetId */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary */

$title = $title ?? 'Chantier spatial';
$icon = require __DIR__ . '/../components/_icon.php';
$card = require __DIR__ . '/../components/_card.php';
require_once __DIR__ . '/../components/helpers.php';

$overview = $overview ?? null;
$queue = $overview['queue'] ?? ['count' => 0, 'jobs' => []];
$fleet = $overview['fleet'] ?? [];
$fleetSummary = $overview['fleetSummary'] ?? [];
$categories = $overview['categories'] ?? [];
$shipyardLevel = $overview['shipyardLevel'] ?? 0;
$fleetCount = 0;
if (!empty($fleetSummary)) {
    foreach ($fleetSummary as $ship) {
        $fleetCount += (int) ($ship['quantity'] ?? 0);
    }
} else {
    $fleetCount = array_sum($fleet);
}

$assetBase = rtrim($baseUrl, '/');

ob_start();
?>
<section class="page-header">
    <div>
        <h1>Chantier spatial</h1>
        <?php if ($overview): ?>
            <p class="page-header__subtitle">Organisez la production de vos vaisseaux et renforcez votre flotte orbitale.</p>
        <?php else: ?>
            <p class="page-header__subtitle">Sélectionnez une planète depuis l’en-tête pour accéder à ses hangars.</p>
        <?php endif; ?>
    </div>
    <div class="page-header__actions">
        <?php if ($overview): ?>
            <a class="button button--ghost" href="<?= htmlspecialchars($baseUrl) ?>/fleet?planet=<?= (int) $selectedPlanetId ?>">Voir la flotte</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($overview === null): ?>
    <?= $card([
        'title' => 'Aucun chantier actif',
        'body' => static function (): void {
            echo '<p>Sélectionnez une planète équipée d’un chantier spatial pour lancer la production.</p>';
        },
    ]) ?>
<?php else: ?>
    <?= $card([
        'title' => 'Commandes de vaisseaux',
        'subtitle' => 'Suivi des constructions orbitales',
        'body' => static function () use ($queue, $shipyardLevel): void {
            $emptyMessage = 'Aucune commande de vaisseau n’est en file. Lancez une production pour étoffer votre flotte.';
            echo '<p class="metric-line"><span class="metric-line__label">Niveau du chantier</span><span class="metric-line__value">' . number_format((int) $shipyardLevel) . '</span></p>';
            echo '<div class="queue-block" data-queue="shipyard" data-empty="' . htmlspecialchars($emptyMessage, ENT_QUOTES) . '">';
            if (($queue['count'] ?? 0) === 0) {
                echo '<p class="empty-state">' . htmlspecialchars($emptyMessage) . '</p>';
            } else {
                echo '<ul class="queue-list">';
                foreach ($queue['jobs'] as $job) {
                    $label = $job['label'] ?? $job['ship'] ?? '';
                    echo '<li class="queue-list__item">';
                    echo '<div><strong>' . htmlspecialchars((string) $label) . '</strong><span>' . number_format((int) ($job['quantity'] ?? 0)) . ' unité(s)</span></div>';
                    echo '<div class="queue-list__timing">';
                    echo '<span>Termine dans ' . htmlspecialchars(format_duration((int) ($job['remaining'] ?? 0))) . '</span>';
                    if (!empty($job['endsAt']) && $job['endsAt'] instanceof \DateTimeImmutable) {
                        echo '<time datetime="' . $job['endsAt']->format('c') . '">' . $job['endsAt']->format('d/m H:i') . '</time>';
                    }
                    echo '</div>';
                    echo '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        },
    ]) ?>

    <?php foreach ($categories as $category): ?>
        <?php if (empty($category['items'])) { continue; } ?>
        <section class="content-section">
            <header class="content-section__header">
                <h2><?= htmlspecialchars($category['label']) ?></h2>
            </header>
            <div class="card-grid card-grid--quad">
                <?php foreach ($category['items'] as $item): ?>
                    <?php
                    $definition = $item['definition'];
                    $canBuild = (bool) ($item['canBuild'] ?? false);
                    ?>
                    <article class="ship-card<?= $canBuild ? '' : ' is-locked' ?>">
                        <header class="ship-card__header">
                            <div>
                                <h3><?= htmlspecialchars($definition->getLabel()) ?></h3>
                                <p class="ship-card__role"><?= htmlspecialchars($definition->getRole()) ?></p>
                            </div>
                            <?php if ($definition->getImage()): ?>
                                <?php $imageSrc = $assetBase . '/' . ltrim($definition->getImage(), '/'); ?>
                                <img class="ship-card__illustration" src="<?= htmlspecialchars($imageSrc, ENT_QUOTES) ?>" alt="" loading="lazy" decoding="async">
                            <?php endif; ?>
                        </header>
                        <p class="ship-card__description"><?= htmlspecialchars($definition->getDescription()) ?></p>
                        <div class="ship-card__stats">
                            <?php foreach ($definition->getStats() as $label => $value): ?>
                                <div class="mini-stat"><span class="mini-stat__label"><?= htmlspecialchars(ucfirst((string) $label)) ?></span><strong class="mini-stat__value"><?= number_format((int) $value) ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="ship-card__content">
                            <div class="ship-card__costs">
                                <h4>Coût unitaire</h4>
                                <ul class="resource-list">
                                    <?php foreach ($definition->getBaseCost() as $resource => $amount): ?>
                                        <li><?= $icon((string) $resource, ['baseUrl' => $baseUrl, 'class' => 'icon-sm']) ?><span><?= number_format((int) $amount) ?></span></li>
                                    <?php endforeach; ?>
                                    <li><?= $icon('time', ['baseUrl' => $baseUrl, 'class' => 'icon-sm']) ?><span><?= htmlspecialchars(format_duration((int) $definition->getBuildTime())) ?></span></li>
                                </ul>
                            </div>
                            <?php if (!($item['requirements']['ok'] ?? true)): ?>
                                <div class="ship-card__requirements">
                                    <h4>Pré-requis</h4>
                                    <ul>
                                        <?php foreach ($item['requirements']['missing'] as $missing): ?>
                                            <li><?= htmlspecialchars($missing['label']) ?> (<?= number_format((int) ($missing['current'] ?? 0)) ?>/<?= number_format((int) $missing['level']) ?>)</li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                        <footer class="ship-card__footer">
                            <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/shipyard?planet=<?= (int) $selectedPlanetId ?>" data-async="queue" data-queue-target="shipyard">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrf_shipyard) ?>">
                                <input type="hidden" name="ship" value="<?= htmlspecialchars($definition->getKey()) ?>">
                                <label class="ship-card__quantity"><span>Quantité</span><input type="number" name="quantity" min="1" value="1"<?= $canBuild ? '' : ' disabled' ?>></label>
                                <?php $label = $canBuild ? 'Construire' : 'Pré-requis manquants'; ?>
                                <button class="button button--primary" type="submit"<?= $canBuild ? '' : ' disabled' ?>><?= $label ?></button>
                            </form>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';
