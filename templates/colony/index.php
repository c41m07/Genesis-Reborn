<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var array|null $overview */
/** @var string $baseUrl */
/** @var string|null $csrf_upgrade */
/** @var int|null $selectedPlanetId */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary */

$title = $title ?? 'Bâtiments';
$planet = $overview['planet'] ?? null;
$queue = $overview['queue'] ?? ['count' => 0, 'jobs' => []];
$buildings = $overview['buildings'] ?? [];

$icon = require __DIR__ . '/../components/_icon.php';
$card = require __DIR__ . '/../components/_card.php';

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

$resourceLabels = [
    'metal' => 'Métal',
    'crystal' => 'Cristal',
    'hydrogen' => 'Hydrogène',
    'energy' => 'Énergie',
];

$assetBase = rtrim($baseUrl, '/');
$buildingTypeMap = [
    'metal_mine' => ['group' => 'production', 'label' => 'Production'],
    'crystal_mine' => ['group' => 'production', 'label' => 'Production'],
    'hydrogen_plant' => ['group' => 'production', 'label' => 'Production'],
    'solar_plant' => ['group' => 'energy', 'label' => 'Énergie'],
    'research_lab' => ['group' => 'science', 'label' => 'Recherche'],
    'shipyard' => ['group' => 'military', 'label' => 'Militaire'],
];
$groupOrder = [
    'production' => 0,
    'energy' => 1,
    'science' => 2,
    'military' => 3,
    'infrastructure' => 9,
];
$buildingGroups = [];
if ($overview !== null) {
    foreach ($buildings as $entry) {
        $definition = $entry['definition'];
        $key = $definition->getKey();
        $map = $buildingTypeMap[$key] ?? ['group' => 'infrastructure', 'label' => 'Infrastructure'];
        $groupKey = $map['group'];
        $label = $map['label'];
        if (!isset($buildingGroups[$groupKey])) {
            $buildingGroups[$groupKey] = [
                'label' => $label,
                'order' => $groupOrder[$groupKey] ?? 9,
                'items' => [],
            ];
        }
        $buildingGroups[$groupKey]['items'][] = $entry;
    }
    uasort($buildingGroups, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);
}

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
        'body' => static function () use ($queue): void {
            $emptyMessage = 'Aucune amélioration n’est programmée. Lancez une construction pour développer votre colonie.';
            echo '<div class="queue-block" data-queue="buildings" data-empty="' . htmlspecialchars($emptyMessage, ENT_QUOTES) . '">';
            if (($queue['count'] ?? 0) === 0) {
                echo '<p class="empty-state">' . htmlspecialchars($emptyMessage) . '</p>';
            } else {
                echo '<ul class="queue-list">';
                foreach ($queue['jobs'] as $job) {
                    $label = $job['label'] ?? $job['building'] ?? '';
                    echo '<li class="queue-list__item">';
                    echo '<div><strong>' . htmlspecialchars((string) $label) . '</strong><span>Niveau ' . number_format((int) ($job['targetLevel'] ?? 0)) . '</span></div>';
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

    <?php foreach ($buildingGroups as $group): ?>
        <section class="content-section">
            <header class="content-section__header">
                <h2><?= htmlspecialchars($group['label']) ?></h2>
            </header>
            <div class="card-grid card-grid--quad">
                <?php foreach ($group['items'] as $building): ?>
                    <?php $definition = $building['definition']; ?>
                    <?php
                    $production = $building['production'];
                    $energy = $building['energy'];
                    $requirements = $building['requirements'];
                    $canUpgrade = (bool) ($building['canUpgrade'] ?? false);
                    $status = $canUpgrade ? '' : 'is-locked';
                    $imagePath = $definition->getImage();
                    ?>
                    <?= $card([
                        'title' => $definition->getLabel(),
                        'subtitle' => 'Niveau actuel ' . number_format((int) $building['level']),
                        'illustration' => $imagePath ? $assetBase . '/' . ltrim($imagePath, '/') : null,
                        'status' => $status,
                        'class' => 'building-card',
                        'body' => static function () use ($building, $production, $energy, $requirements, $baseUrl, $resourceLabels, $icon): void {
                            echo '<div class="building-card__sections">';
                            echo '<div class="building-card__block">';
                            echo '<h3>Coût de la prochaine amélioration</h3>';
                            echo '<ul class="resource-list">';
                            foreach ($building['cost'] as $resource => $amount) {
                                echo '<li>';
                                echo $icon((string) $resource, ['baseUrl' => $baseUrl, 'class' => 'icon-sm']);
                                echo '<span>' . number_format((int) $amount) . '</span>';
                                echo '</li>';
                            }
                            echo '<li>' . $icon('time', ['baseUrl' => $baseUrl, 'class' => 'icon-sm']) . '<span>' . htmlspecialchars(format_duration((int) $building['time'])) . '</span></li>';
                            echo '</ul>';
                            echo '</div>';

                            echo '<div class="building-card__block">';
                            echo '<h3>Effets</h3>';
                            $resourceKey = $production['resource'] ?? '';
                            $resourceLabel = $resourceLabels[$resourceKey] ?? ucfirst((string) $resourceKey);
                            $unitSuffix = $resourceKey === 'energy' ? ' énergie/h' : ' ' . strtolower($resourceLabel) . '/h';
                            $currentValue = (int) ($production['current'] ?? 0);
                            $deltaValue = (int) ($production['delta'] ?? 0);
                            $currentPrefix = $currentValue > 0 ? '+' : '';
                            $deltaPrefix = $deltaValue > 0 ? '+' : '';
                            echo '<p class="metric-line"><span class="metric-line__label">Production</span><span class="metric-line__value">' . $currentPrefix . number_format($currentValue) . htmlspecialchars($unitSuffix) . '</span></p>';
                            echo '<p class="metric-line"><span class="metric-line__label">Gain prochain niveau</span><span class="metric-line__value">' . $deltaPrefix . number_format($deltaValue) . htmlspecialchars($unitSuffix) . '</span></p>';
                            if (($energy['current'] ?? 0) !== 0 || ($energy['delta'] ?? 0) !== 0) {
                                $energyPrefix = $energy['delta'] > 0 ? '+' : '';
                                echo '<p class="metric-line"><span class="metric-line__label">Énergie</span><span class="metric-line__value">' . ($energy['current'] > 0 ? '-' : '') . number_format((int) $energy['current']) . ' énergie/h</span></p>';
                                echo '<p class="metric-line"><span class="metric-line__label">Variation</span><span class="metric-line__value">' . $energyPrefix . number_format((int) $energy['delta']) . ' énergie/h</span></p>';
                            }
                            echo '</div>';

                            if (!($requirements['ok'] ?? true)) {
                                echo '<div class="building-card__block">';
                                echo '<h3>Pré-requis</h3>';
                                echo '<ul class="requirement-list">';
                                foreach ($requirements['missing'] as $missing) {
                                    echo '<li>' . htmlspecialchars($missing['label']) . ' (' . number_format((int) ($missing['current'] ?? 0)) . '/' . number_format((int) $missing['level']) . ')</li>';
                                }
                                echo '</ul>';
                                echo '</div>';
                            }

                            echo '</div>';
                        },
                        'footer' => static function () use ($baseUrl, $planet, $definition, $csrf_upgrade, $canUpgrade): void {
                            if (!$planet) {
                                return;
                            }

                            echo '<form method="post" action="' . htmlspecialchars($baseUrl) . '/colony?planet=' . $planet->getId() . '" data-async="queue" data-queue-target="buildings">';
                            echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string) $csrf_upgrade) . '">';
                            echo '<input type="hidden" name="building" value="' . htmlspecialchars($definition->getKey()) . '">';
                            $label = $canUpgrade ? 'Améliorer' : 'Conditions non remplies';
                            $disabled = $canUpgrade ? '' : ' disabled';
                            echo '<button class="button button--primary" type="submit"' . $disabled . '>' . $label . '</button>';
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
require __DIR__ . '/../layouts/base.php';
