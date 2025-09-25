<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets Liste des planètes du joueur. */
/** @var array|null $overview Données de la colonie courante. */
/** @var string $baseUrl URL de base pour générer les liens. */
/** @var string|null $csrf_upgrade Jeton CSRF pour l’amélioration. */
/** @var int|null $selectedPlanetId Identifiant de la planète sélectionnée. */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary Résumé de la planète pour l’en-tête. */

$title = $title ?? 'Bâtiments';
$planet = $overview['planet'] ?? null;
$queue = $overview['queue'] ?? ['count' => 0, 'jobs' => []];
$levels = $overview['levels'] ?? [];
$categories = $overview['categories'] ?? [];

$icon = require __DIR__ . '/../../components/_icon.php';
$card = require __DIR__ . '/../../components/_card.php';
$requirementsPanel = require __DIR__ . '/../../components/_requirements.php';
require_once __DIR__ . '/../../components/helpers.php';

$resourceLabels = [
    'metal' => 'Métal',
    'crystal' => 'Cristal',
    'hydrogen' => 'Hydrogène',
    'energy' => 'Énergie',
    'storage' => 'Capacité',
    'infrastructure' => 'Infrastructure',
];

$assetBase = rtrim($baseUrl, '/');
$queueCount = (int) ($queue['count'] ?? 0);
$queueLimit = (int) ($queue['limit'] ?? 5);
$workerFactorySummary = $overview['workerFactory'] ?? [
    'level' => (int) ($levels['worker_factory'] ?? 0),
    'bonus' => 0.0,
];
$robotFactorySummary = $overview['robotFactory'] ?? [
    'level' => (int) ($levels['robot_factory'] ?? 0),
    'bonus' => 0.0,
];
$workerFactoryBonus = max(0.0, (float) ($workerFactorySummary['bonus'] ?? 0.0));
$robotFactoryBonus = max(0.0, (float) ($robotFactorySummary['bonus'] ?? 0.0));

ob_start();
?>
<section class="page-header">
    <div>
        <h1>Gestion des bâtiments</h1>
        <?php if ($planet): ?>
            <p class="page-header__subtitle">Optimisez les installations de <?= htmlspecialchars($planet->getName()) ?> pour soutenir l’expansion de votre empire.</p>
        <?php else: ?>
            <p class="page-header__subtitle">Sélectionnez une planète depuis l’en-tête pour gérer ses bâtiments et sa production.</p>
        <?php endif; ?>
    </div>
    <div class="page-header__actions">
        <?php if ($planet): ?>
            <a class="button button--ghost" href="<?= htmlspecialchars($baseUrl) ?>/research?planet=<?= $planet->getId() ?>">Accéder à la recherche</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($overview === null): ?>
    <?= $card([
        'title' => 'Aucune colonie active',
        'body' => static function (): void {
            echo '<p>Choisissez une planète via le sélecteur supérieur pour afficher ses infrastructures.</p>';
        },
    ]) ?>
<?php else: ?>
    <?= $card([
        'title' => 'File de construction',
        'subtitle' => 'Suivi des améliorations en cours',
        'body' => static function () use (
            $queue,
            $queueCount,
            $queueLimit,
            $workerFactoryBonus,
            $robotFactoryBonus
        ): void {
            $emptyMessage = 'Aucune amélioration n’est programmée. Lancez une construction pour développer votre colonie.';
            $limitValue = $queueLimit > 0 ? format_number($queueLimit) : '—';
            $formatPercent = static function (float $value): string {
                $percent = $value * 100;
                $formatted = number_format($percent, 1);

                return rtrim(rtrim($formatted, '0'), '.');
            };
            $totalBonus = $workerFactoryBonus + $robotFactoryBonus;

            echo '<div class="queue-card" data-queue-wrapper="buildings" data-queue-limit="' . max(0, (int) $queueLimit) . '">';
            echo '<p class="metric-line"><span class="metric-line__label">Améliorations en file</span>';
            echo '<span class="metric-line__value"><span data-queue-count>' . format_number($queueCount) . '</span> / <span data-queue-limit>' . htmlspecialchars($limitValue) . '</span></span></p>';
            if ($totalBonus > 0.0) {
                $totalBonusDisplay = $formatPercent($totalBonus);
                echo '<p class="metric-line"><span class="metric-line__label">Bonus de vitesse</span><span class="metric-line__value metric-line__value--positive">( +' . htmlspecialchars($totalBonusDisplay) . ' % ) [cumul des 2]</span></p>';
            }
            echo '<div class="queue-block" data-queue="buildings" data-empty="' . htmlspecialchars($emptyMessage, ENT_QUOTES) . '" data-queue-limit="' . max(0, (int) $queueLimit) . '" data-server-now="' . time() . '">';
            if (($queue['count'] ?? 0) === 0) {
                echo '<p class="empty-state">' . htmlspecialchars($emptyMessage) . '</p>';
            } else {
                echo '<ul class="queue-list">';
                foreach ($queue['jobs'] as $job) {
                    $label = $job['label'] ?? $job['building'] ?? '';
                    $endTime = null;
                    if (!empty($job['endsAt']) && $job['endsAt'] instanceof \DateTimeImmutable) {
                        $endTime = $job['endsAt']->getTimestamp();
                    } elseif (isset($job['remaining'])) {
                        $endTime = time() + max(0, (int) $job['remaining']);
                    }
                    echo '<li class="queue-list__item' . ($endTime ? '" data-endtime="' . (int) $endTime . '"' : '"') . '>';
                    echo '<div><strong>' . htmlspecialchars((string) $label) . '</strong><span>Niveau ' . format_number((int) ($job['targetLevel'] ?? 0)) . '</span></div>';
                    echo '<div class="queue-list__timing">';
                    echo '<span><span class="countdown">' . htmlspecialchars(format_duration((int) ($job['remaining'] ?? 0))) . '</span></span>';
                    if (!empty($job['endsAt']) && $job['endsAt'] instanceof \DateTimeImmutable) {
                        echo '<time datetime="' . $job['endsAt']->format('c') . '">' . $job['endsAt']->format('d/m H:i') . '</time>';
                    }
                    echo '</div>';
                    echo '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';
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
                <?php foreach ($category['items'] as $building): ?>
                    <?php $definition = $building['definition']; ?>
                    <?php
                    $production = $building['production'];
                    $consumption = $building['consumption'] ?? [];
                    $requirements = $building['requirements'];
                    $requirementsOk = (bool) ($requirements['ok'] ?? true);
                    $canUpgrade = (bool) ($building['canUpgrade'] ?? false);
                    $affordable = (bool) ($building['affordable'] ?? false);
                    $missingResources = array_map(static fn ($value) => (int) $value, $building['missingResources'] ?? []);
                    $statusClasses = [];
                    if (!$canUpgrade) {
                        $statusClasses[] = 'is-locked';
                    }
                    $status = trim(implode(' ', $statusClasses));
                    $imagePath = $definition->getImage();
                    ?>
                    <?= $card([
                        'title' => $definition->getLabel(),
                        'subtitle' => 'Niveau actuel ' . format_number((int) $building['level']),
                        'illustration' => $imagePath ? $assetBase . '/' . ltrim($imagePath, '/') : null,
                        'status' => $status,
                        'class' => 'building-card',
                        'attributes' => [
                            'data-building-card' => $definition->getKey(),
                        ],
                        'body' => static function () use (
                            $building,
                            $production,
                            $consumption,
                            $requirements,
                            $baseUrl,
                            $resourceLabels,
                            $icon,
                            $requirementsPanel,
                            $missingResources
                        ): void {
                            $bonuses = $building['bonuses'] ?? [];
                            echo '<div class="building-card__sections">';
                            echo '<div class="building-card__block">';
                            echo '<h3>Prochaine amélioration</h3>';
                            echo '<ul class="resource-list">';
                            foreach ($building['cost'] as $resource => $amount) {
                                $resourceKey = (string) $resource;
                                $classes = ['resource-list__item'];
                                if (($missingResources[$resourceKey] ?? 0) > 0) {
                                    $classes[] = 'resource-list__item--missing';
                                }
                                echo '<li class="' . implode(' ', $classes) . '" data-resource="' . htmlspecialchars($resourceKey) . '">';
                                echo $icon($resourceKey, ['baseUrl' => $baseUrl, 'class' => 'icon-sm']);
                                echo '<span>' . format_number((int) $amount) . '</span>';
                                echo '</li>';
                            }
                            $buildTime = (int) ($building['time'] ?? 0);
                            $baseBuildTime = (int) ($building['baseTime'] ?? $buildTime);
                            echo '<li class="resource-list__item resource-list__item--time" data-resource="time">' . $icon('time', ['baseUrl' => $baseUrl, 'class' => 'icon-sm']) . '<span>' . htmlspecialchars(format_duration($buildTime));
                            if ($baseBuildTime !== $buildTime) {
                                echo ' <small>(base ' . htmlspecialchars(format_duration($baseBuildTime)) . ')</small>';
                            }
                            echo '</span></li>';
                            echo '</ul>';
                            echo '</div>';

                            echo '<details class="building-card__details" data-building-effects>';
                            echo '<summary class="building-card__details-summary">';
                            echo '<span class="building-card__details-title">Effets</span>';
                            echo '<span class="building-card__details-chevron" aria-hidden="true"></span>';
                            echo '</summary>';
                            echo '<div class="building-card__details-content">';
                            $resourceKey = $production['resource'] ?? '';
                            $resourceLabel = $resourceLabels[$resourceKey] ?? ucfirst((string) $resourceKey);
                            $hasProduction = !in_array($resourceKey, ['storage', 'infrastructure'], true);
                            $formatPercent = static function (float $value): string {
                                return number_format($value * 100, 1) . ' %';
                            };
                            if ($hasProduction) {
                                $unitSuffix = $resourceKey === 'energy'
                                    ? ' énergie/h'
                                    : ' ' . strtolower($resourceLabel) . '/h';
                                $currentValue = (int) ($production['current'] ?? 0);
                                $nextValue = (int) ($production['next'] ?? 0);
                                $currentDisplay = $currentValue > 0 ? '+' . format_number($currentValue) : format_number($currentValue);
                                $nextDisplay = $nextValue > 0 ? '+' . format_number($nextValue) : format_number($nextValue);
                                $currentClass = $currentValue > 0 ? 'metric-line__value metric-line__value--positive' : ($currentValue < 0 ? 'metric-line__value metric-line__value--negative' : 'metric-line__value metric-line__value--neutral');
                                $nextClass = $nextValue > 0 ? 'metric-line__value metric-line__value--positive' : ($nextValue < 0 ? 'metric-line__value metric-line__value--negative' : 'metric-line__value metric-line__value--neutral');
                                if ($currentValue !== 0 || $nextValue !== 0) {
                                    echo '<p class="metric-line"><span class="metric-line__label">Production actuelle</span><span class="' . $currentClass . '">' . $currentDisplay . htmlspecialchars($unitSuffix) . '</span></p>';
                                    echo '<p class="metric-line"><span class="metric-line__label">Production prochain niveau</span><span class="' . $nextClass . '">' . $nextDisplay . htmlspecialchars($unitSuffix) . '</span></p>';
                                }
                            }

                            $storage = $building['storage'] ?? [];
                            $storageCurrent = $storage['current'] ?? [];
                            $storageNext = $storage['next'] ?? [];
                            if (!empty($storageCurrent) || !empty($storageNext)) {
                                $storageLabel = $resourceLabels['storage'];
                                echo '<div class="metric-section">';
                                echo '<p class="metric-section__title">' . htmlspecialchars($storageLabel) . ' actuelle</p>';
                                echo '<ul class="metric-section__list">';
                                foreach ($storageCurrent as $resource => $value) {
                                    $label = $resourceLabels[$resource] ?? ucfirst((string) $resource);
                                    echo '<li class="metric-line"><span class="metric-line__label">' . htmlspecialchars($label) . '</span><span class="metric-line__value metric-line__value--neutral">' . format_number((int) $value) . '</span></li>';
                                }
                                echo '</ul>';
                                echo '<p class="metric-section__title">' . htmlspecialchars($storageLabel) . ' prochain niveau</p>';
                                echo '<ul class="metric-section__list">';
                                foreach ($storageNext as $resource => $value) {
                                    $label = $resourceLabels[$resource] ?? ucfirst((string) $resource);
                                    echo '<li class="metric-line"><span class="metric-line__label">' . htmlspecialchars($label) . '</span><span class="metric-line__value metric-line__value--positive">' . format_number((int) $value) . '</span></li>';
                                }
                                echo '</ul>';
                                echo '</div>';
                            }

                            if (!empty($bonuses['construction_speed'])) {
                                $bonus = $bonuses['construction_speed'];
                                $currentBonus = max(0.0, (float) ($bonus['current'] ?? 0.0));
                                $nextBonus = max(0.0, (float) ($bonus['next'] ?? 0.0));
                                $currentClass = $currentBonus > 0.0
                                    ? 'metric-line__value metric-line__value--positive'
                                    : 'metric-line__value metric-line__value--neutral';
                                $nextClass = $nextBonus > 0.0
                                    ? 'metric-line__value metric-line__value--positive'
                                    : 'metric-line__value metric-line__value--neutral';
                                echo '<div class="metric-section">';
                                echo '<p class="metric-section__title">Accélération de construction</p>';
                                echo '<p class="metric-line"><span class="metric-line__label">Réduction actuelle</span><span class="' . $currentClass . '">-' . $formatPercent($currentBonus) . '</span></p>';
                                echo '<p class="metric-line"><span class="metric-line__label">Réduction prochain niveau</span><span class="' . $nextClass . '">-' . $formatPercent($nextBonus) . '</span></p>';
                                echo '</div>';
                            }

                            if (!empty($bonuses['research_speed'])) {
                                $bonus = $bonuses['research_speed'];
                                $currentBonus = max(0.0, (float) ($bonus['current'] ?? 0.0));
                                $nextBonus = max(0.0, (float) ($bonus['next'] ?? 0.0));
                                $currentClass = $currentBonus > 0.0
                                    ? 'metric-line__value metric-line__value--positive'
                                    : 'metric-line__value metric-line__value--neutral';
                                $nextClass = $nextBonus > 0.0
                                    ? 'metric-line__value metric-line__value--positive'
                                    : 'metric-line__value metric-line__value--neutral';
                                echo '<div class="metric-section">';
                                echo '<p class="metric-section__title">Accélération de recherche</p>';
                                echo '<p class="metric-line"><span class="metric-line__label">Réduction actuelle</span><span class="' . $currentClass . '">-' . $formatPercent($currentBonus) . '</span></p>';
                                echo '<p class="metric-line"><span class="metric-line__label">Réduction prochain niveau</span><span class="' . $nextClass . '">-' . $formatPercent($nextBonus) . '</span></p>';
                                echo '</div>';
                            }

                            if (!empty($bonuses['ship_build_speed'])) {
                                $bonus = $bonuses['ship_build_speed'];
                                $currentBonus = max(0.0, (float) ($bonus['current'] ?? 0.0));
                                $nextBonus = max(0.0, (float) ($bonus['next'] ?? 0.0));
                                $currentClass = $currentBonus > 0.0
                                    ? 'metric-line__value metric-line__value--positive'
                                    : 'metric-line__value metric-line__value--neutral';
                                $nextClass = $nextBonus > 0.0
                                    ? 'metric-line__value metric-line__value--positive'
                                    : 'metric-line__value metric-line__value--neutral';
                                echo '<div class="metric-section">';
                                echo '<p class="metric-section__title">Accélération du chantier spatial</p>';
                                echo '<p class="metric-line"><span class="metric-line__label">Réduction actuelle</span><span class="' . $currentClass . '">-' . $formatPercent($currentBonus) . '</span></p>';
                                echo '<p class="metric-line"><span class="metric-line__label">Réduction prochain niveau</span><span class="' . $nextClass . '">-' . $formatPercent($nextBonus) . '</span></p>';
                                echo '</div>';
                            }

                            foreach ($consumption as $resource => $values) {
                                $currentConsumption = (int) ($values['current'] ?? 0);
                                $nextConsumption = (int) ($values['next'] ?? 0);
                                if ($currentConsumption === 0 && $nextConsumption === 0) {
                                    continue;
                                }

                                $resourceLabel = $resourceLabels[$resource] ?? ucfirst((string) $resource);
                                $unitSuffix = $resource === 'energy'
                                    ? ' énergie/h'
                                    : ' ' . strtolower($resourceLabel) . '/h';

                                $displayCurrent = $currentConsumption > 0 ? -$currentConsumption : $currentConsumption;
                                $displayNext = $nextConsumption > 0 ? -$nextConsumption : $nextConsumption;

                                $currentClass = $displayCurrent < 0
                                    ? 'metric-line__value metric-line__value--negative'
                                    : ($displayCurrent > 0 ? 'metric-line__value metric-line__value--positive' : 'metric-line__value metric-line__value--neutral');
                                $nextClass = $displayNext < 0
                                    ? 'metric-line__value metric-line__value--negative'
                                    : ($displayNext > 0 ? 'metric-line__value metric-line__value--positive' : 'metric-line__value metric-line__value--neutral');

                                $labelCurrent = 'Consommation actuelle';
                                $labelNext = 'Consommation prochain niveau';
                                if ($resource !== 'energy') {
                                    $labelCurrent .= ' (' . htmlspecialchars($resourceLabel) . ')';
                                    $labelNext .= ' (' . htmlspecialchars($resourceLabel) . ')';
                                }

                                $currentDisplay = format_number($displayCurrent);
                                $nextDisplay = format_number($displayNext);

                                echo '<p class="metric-line"><span class="metric-line__label">' . htmlspecialchars($labelCurrent) . '</span><span class="' . $currentClass . '">' . htmlspecialchars($currentDisplay) . htmlspecialchars($unitSuffix) . '</span></p>';
                                echo '<p class="metric-line"><span class="metric-line__label">' . htmlspecialchars($labelNext) . '</span><span class="' . $nextClass . '">' . htmlspecialchars($nextDisplay) . htmlspecialchars($unitSuffix) . '</span></p>';
                            }
                            echo '</div>';
                            echo '</details>';

                            if (!($requirements['ok'] ?? true)) {
                                $requirementItems = [];
                                foreach ($requirements['missing'] ?? [] as $missing) {
                                    if (!is_array($missing)) {
                                        continue;
                                    }

                                    $requirementItems[] = [
                                        'label' => $missing['label'] ?? $missing['key'] ?? '',
                                        'current' => (int) ($missing['current'] ?? 0),
                                        'required' => (int) ($missing['level'] ?? 0),
                                    ];
                                }

                                if ($requirementItems !== []) {
                                    echo '<div class="building-card__block building-card__block--requirements">';
                                    echo $requirementsPanel([
                                        'title' => 'Pré-requis',
                                        'items' => $requirementItems,
                                        'icon' => $icon('buildings', [
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
                            $planet,
                            $definition,
                            $csrf_upgrade,
                            $canUpgrade,
                            $requirementsOk,
                            $affordable
                        ): void {
                            if (!$planet) {
                                return;
                            }

                            echo '<form method="post" action="' . htmlspecialchars($baseUrl) . '/colony?planet=' . $planet->getId() . '" data-async="queue" data-queue-target="buildings">';
                            echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string) $csrf_upgrade) . '">';
                            echo '<input type="hidden" name="building" value="' . htmlspecialchars($definition->getKey()) . '">';
                            if ($canUpgrade) {
                                $label = 'Améliorer';
                            } elseif ($requirementsOk && !$affordable) {
                                $label = 'Ressources insuffisantes';
                            } else {
                                $label = 'Conditions non remplies';
                            }
                            $disabled = $canUpgrade ? '' : ' disabled';
                            $buttonClasses = 'button button--primary';
                            if ($requirementsOk && !$affordable) {
                                $buttonClasses .= ' button--resource-warning';
                            }
                            echo '<button class="' . $buttonClasses . '" type="submit"' . $disabled . '>' . $label . '</button>';
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
