<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets Planètes gérées par le joueur. */
/** @var array|null $overview Détails du chantier actif. */
/** @var string $baseUrl URL de base pour les actions. */
/** @var string|null $csrf_shipyard Jeton CSRF pour la production. */
/** @var int|null $selectedPlanetId Identifiant de la planète ciblée. */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary Résumé de la planète sélectionnée. */

$title = $title ?? 'Chantier spatial';
$icon = require __DIR__ . '/../../components/_icon.php';
$card = require __DIR__ . '/../../components/_card.php';
$requirementsPanel = require __DIR__ . '/../../components/_requirements.php';
require_once __DIR__ . '/../../components/helpers.php';

$overview = $overview ?? null;
$queue = $overview['queue'] ?? ['count' => 0, 'jobs' => []];
$fleet = $overview['fleet'] ?? [];
$fleetSummary = $overview['fleetSummary'] ?? [];
$categories = $overview['categories'] ?? [];
$shipyardBonus = (float)($overview['shipyardBonus'] ?? 0);
$fleetCount = 0;
if (!empty($fleetSummary)) {
    foreach ($fleetSummary as $ship) {
        $fleetCount += (int)($ship['quantity'] ?? 0);
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
                <p class="page-header__subtitle">Organisez la production de vos vaisseaux et renforcez votre flotte
                    orbitale.</p>
            <?php else: ?>
                <p class="page-header__subtitle">Sélectionnez une planète depuis l’en-tête pour accéder à ses
                    hangars.</p>
            <?php endif; ?>
        </div>
        <div class="page-header__actions">
            <?php if ($overview): ?>
                <a class="button button--ghost"
                   href="<?= htmlspecialchars($baseUrl) ?>/fleet?planet=<?= (int)$selectedPlanetId ?>">Voir la
                    flotte</a>
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
            'body' => static function () use ($queue, $shipyardBonus, $fleetCount): void {
                $emptyMessage = 'Aucune commande de vaisseau n’est en file. Lancez une production pour étoffer votre flotte.';
                echo '<p class="metric-line"><span class="metric-line__label">Flotte stationnée</span><span class="metric-line__value">' . format_number((int)$fleetCount) . ' unité(s)</span></p>';
                if ($shipyardBonus > 0) {
                    $bonusPercent = $shipyardBonus * 100;
                    $bonusDisplay = rtrim(rtrim(number_format($bonusPercent, 1), '0'), '.');
                    echo '<p class="metric-line"><span class="metric-line__label">Bonus de vitesse</span><span class="metric-line__value metric-line__value--positive">+' . htmlspecialchars($bonusDisplay) . ' %</span></p>';
                }
                echo '<div class="queue-block" data-queue="shipyard" data-empty="' . htmlspecialchars($emptyMessage, ENT_QUOTES) . '" data-server-now="' . time() . '">';
                if (($queue['count'] ?? 0) === 0) {
                    echo '<p class="empty-state">' . htmlspecialchars($emptyMessage) . '</p>';
                } else {
                    echo '<ul class="queue-list">';
                    foreach ($queue['jobs'] as $job) {
                        $label = $job['label'] ?? $job['ship'] ?? '';
                        $endTime = null;
                        if (!empty($job['endsAt']) && $job['endsAt'] instanceof \DateTimeImmutable) {
                            $endTime = $job['endsAt']->getTimestamp();
                        } elseif (isset($job['remaining'])) {
                            $endTime = time() + max(0, (int)$job['remaining']);
                        }
                        echo '<li class="queue-list__item' . ($endTime ? '" data-endtime="' . (int)$endTime . '"' : '"') . '>';
                        echo '<div><strong>' . htmlspecialchars((string)$label) . '</strong><span>' . format_number((int)($job['quantity'] ?? 0)) . ' unité(s)</span></div>';
                        echo '<div class="queue-list__timing">';
                        echo '<span><span class="countdown">' . htmlspecialchars(format_duration((int)($job['remaining'] ?? 0))) . '</span></span>';
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
        <?php if (empty($category['items'])) {
            continue;
        } ?>
        <section class="content-section">
            <header class="content-section__header">
                <h2><?= htmlspecialchars($category['label']) ?></h2>
            </header>
            <div class="card-grid card-grid--quad">
                <?php foreach ($category['items'] as $item): ?>
                    <?php
                    $definition = $item['definition'];
                    $canBuild = (bool)($item['canBuild'] ?? false);
                    $buildTime = (int)($item['buildTime'] ?? $definition->getBuildTime());
                    $baseBuildTime = (int)($item['baseBuildTime'] ?? $definition->getBuildTime());
                    $requirements = $item['requirements'] ?? ['ok' => true, 'missing' => []];
                    $requirementsOk = (bool)($requirements['ok'] ?? true);
                    $affordable = (bool)($item['affordable'] ?? false);
                    $missingResources = array_map(static fn ($value) => (int)$value, $item['missingResources'] ?? []);
                    $statusClasses = [];
                    if (!$canBuild) {
                        $statusClasses[] = 'is-locked';
                    }
                    $status = trim(implode(' ', $statusClasses));
                    $imagePath = $definition->getImage();
                    ?>
                    <?= $card([
                            'title' => $definition->getLabel(),
                            'badge' => $definition->getRole(),
                            'status' => $status,
                            'class' => 'ship-card',
                            'attributes' => [
                                    'data-ship-card' => $definition->getKey(),
                            ],
                            'illustration' => $imagePath ? $assetBase . '/' . ltrim($imagePath, '/') : null,
                            'bodyClass' => 'panel__body ship-card__body',
                            'footerClass' => 'panel__footer ship-card__footer',
                            'body' => static function () use (
                                $definition,
                                $item,
                                $buildTime,
                                $baseBuildTime,
                                $baseUrl,
                                $icon,
                                $requirementsPanel,
                                $requirements,
                                $missingResources
                            ): void {
                                echo '<p class="ship-card__description">' . htmlspecialchars($definition->getDescription()) . '</p>';

                                $stats = $definition->getStats();
                                if (!empty($stats)) {
                                    echo '<div class="ship-card__stats">';
                                    foreach ($stats as $label => $value) {
                                        echo '<div class="mini-stat">';
                                        echo '<span class="mini-stat__label">' . htmlspecialchars(ucfirst((string)$label)) . '</span>';
                                        echo '<strong class="mini-stat__value">' . format_number((int)$value) . '</strong>';
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                }

                                echo '<div class="ship-card__content">';
                                echo '<div class="ship-card__section ship-card__section--costs">';
                                echo '<h3>Coût de production</h3>';
                                echo '<ul class="resource-list">';
                                foreach ($definition->getBaseCost() as $resource => $amount) {
                                    $resourceKey = (string)$resource;
                                    $classes = ['resource-list__item'];
                                    if (($missingResources[$resourceKey] ?? 0) > 0) {
                                        $classes[] = 'resource-list__item--missing';
                                    }
                                    echo '<li class="' . implode(' ', $classes) . '" data-resource="' . htmlspecialchars($resourceKey) . '">';
                                    echo $icon($resourceKey, ['baseUrl' => $baseUrl, 'class' => 'icon-sm']);
                                    echo '<span>' . format_number((int)$amount) . '</span>';
                                    echo '</li>';
                                }
                                echo '<li class="resource-list__item resource-list__item--time" data-resource="time">' . $icon('time', ['baseUrl' => $baseUrl, 'class' => 'icon-sm']) . '<span>' . htmlspecialchars(format_duration($buildTime));
                                if ($baseBuildTime !== $buildTime) {
                                    echo ' <small>(base ' . htmlspecialchars(format_duration($baseBuildTime)) . ')</small>';
                                }
                                echo '</span></li>';
                                echo '</ul>';
                                echo '</div>';

                                if (!($requirements['ok'] ?? true)) {
                                    $requirementItems = [];
                                    foreach ($requirements['missing'] as $missing) {
                                        if (!is_array($missing)) {
                                            continue;
                                        }

                                        $requirementItems[] = [
                                                'label' => $missing['label'] ?? $missing['key'] ?? '',
                                                'current' => (int)($missing['current'] ?? 0),
                                                'required' => (int)($missing['level'] ?? 0),
                                        ];
                                    }

                                    if ($requirementItems !== []) {
                                        echo '<div class="ship-card__section ship-card__requirements">';
                                        echo $requirementsPanel([
                                                'title' => 'Pré-requis',
                                                'items' => $requirementItems,
                                                'icon' => $icon('shipyard', [
                                                        'baseUrl' => $baseUrl,
                                                        'class' => 'icon-sm requirements-panel__glyph',
                                                ]),
                                        ]);
                                        echo '</div>';
                                    }
                                }
                                echo '</div>';
                            },
                            'footer' => static function () use (
                                $baseUrl,
                                $definition,
                                $csrf_shipyard,
                                $selectedPlanetId,
                                $canBuild,
                                $requirementsOk,
                                $affordable
                            ): void {
                                echo '<form method="post" action="' . htmlspecialchars($baseUrl) . '/shipyard?planet=' . (int)$selectedPlanetId . '" data-async="queue" data-queue-target="shipyard">';
                                echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)$csrf_shipyard) . '">';
                                echo '<input type="hidden" name="ship" value="' . htmlspecialchars($definition->getKey()) . '">';
                                echo '<label class="ship-card__quantity"><span>Quantité</span><input type="number" name="quantity" min="1" value="1"' . ($canBuild ? '' : ' disabled') . '></label>';
                                if ($canBuild) {
                                    $label = 'Construire';
                                } elseif ($requirementsOk && !$affordable) {
                                    $label = 'Ressources insuffisantes';
                                } else {
                                    $label = 'Pré-requis manquants';
                                }
                                $buttonClasses = 'button button--primary';
                                if ($requirementsOk && !$affordable) {
                                    $buttonClasses .= ' button--resource-warning';
                                }
                                echo '<button class="' . $buttonClasses . '" type="submit"' . ($canBuild ? '' : ' disabled') . '>' . $label . '</button>';
                                echo '</form>';
                            },
                    ]) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>


<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/base.php';
