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

        $week = 7 * 24 * 3600;
        $day = 24 * 3600;
        $hour = 3600;
        $minute = 60;

        $parts = [];

        if ($seconds >= $week) {
            $weeks = intdiv($seconds, $week);
            $parts[] = sprintf('%d sem', $weeks);
            $seconds %= $week;

            $days = intdiv($seconds, $day);
            if ($days > 0) {
                $parts[] = sprintf('%d j', $days);
            }
            $seconds %= $day;

            $hours = intdiv($seconds, $hour);
            if ($hours > 0) {
                $parts[] = sprintf('%d h', $hours);
            }
            $seconds %= $hour;

            $minutes = intdiv($seconds, $minute);
            if ($minutes > 0) {
                $parts[] = sprintf('%d min', $minutes);
            }
            $seconds %= $minute;

            if ($minutes === 0 && $hours === 0 && $seconds > 0) {
                $parts[] = sprintf('%d s', $seconds);
            }
        } elseif ($seconds >= $day) {
            $days = intdiv($seconds, $day);
            $parts[] = sprintf('%d j', $days);
            $seconds %= $day;

            $hours = intdiv($seconds, $hour);
            if ($hours > 0) {
                $parts[] = sprintf('%d h', $hours);
            }
            $seconds %= $hour;

            $minutes = intdiv($seconds, $minute);
            if ($minutes > 0) {
                $parts[] = sprintf('%d min', $minutes);
            }
            $seconds %= $minute;

            if ($minutes === 0 && $hours === 0 && $seconds > 0) {
                $parts[] = sprintf('%d s', $seconds);
            }
        } else {
            $hours = intdiv($seconds, $hour);
            if ($hours > 0) {
                $parts[] = sprintf('%d h', $hours);
            }
            $seconds %= $hour;

            $minutes = intdiv($seconds, $minute);
            if ($minutes > 0) {
                $parts[] = sprintf('%d min', $minutes);
            }
            $seconds %= $minute;

            if (($hours === 0 && $minutes === 0) || $seconds > 0) {
                $parts[] = sprintf('%d s', $seconds);
            }
        }

        return implode(' ', $parts);
    }
}

$resourceLabels = [
    'metal' => 'Métal',
    'crystal' => 'Cristal',
    'hydrogen' => 'Hydrogène',
    'energy' => 'Énergie',
    'storage' => 'Capacité',
];

$assetBase = rtrim($baseUrl, '/');
$buildingTypeMap = [
    'metal_mine' => ['group' => 'production', 'label' => 'Production'],
    'crystal_mine' => ['group' => 'production', 'label' => 'Production'],
    'hydrogen_plant' => ['group' => 'production', 'label' => 'Production'],
    'solar_plant' => ['group' => 'energy', 'label' => 'Énergie'],
    'fusion_reactor' => ['group' => 'energy', 'label' => 'Énergie'],
    'antimatter_reactor' => ['group' => 'energy', 'label' => 'Énergie'],
    'research_lab' => ['group' => 'science', 'label' => 'Recherche'],
    'shipyard' => ['group' => 'military', 'label' => 'Militaire'],
    'storage_depot' => ['group' => 'infrastructure', 'label' => 'Infrastructure'],
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
                    $consumption = $building['consumption'] ?? [];
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
                        'body' => static function () use ($building, $production, $consumption, $requirements, $baseUrl,
                                $resourceLabels, $icon): void {
                            echo '<div class="building-card__sections">';
                            echo '<div class="building-card__block">';
                            echo '<h3>Prochaine amélioration</h3>';
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
                            $hasProduction = $resourceKey !== 'storage';
                            if ($hasProduction) {
                                $unitSuffix = $resourceKey === 'energy'
                                    ? ' énergie/h'
                                    : ' ' . strtolower($resourceLabel) . '/h';
                                $currentValue = (int) ($production['current'] ?? 0);
                                $nextValue = (int) ($production['next'] ?? 0);
                                $currentDisplay = $currentValue > 0 ? '+' . number_format($currentValue) : number_format($currentValue);
                                $nextDisplay = $nextValue > 0 ? '+' . number_format($nextValue) : number_format($nextValue);
                                $currentClass = $currentValue > 0 ? 'metric-line__value metric-line__value--positive' : ($currentValue < 0 ? 'metric-line__value metric-line__value--negative' : 'metric-line__value metric-line__value--neutral');
                                $nextClass = $nextValue > 0 ? 'metric-line__value metric-line__value--positive' : ($nextValue < 0 ? 'metric-line__value metric-line__value--negative' : 'metric-line__value metric-line__value--neutral');
                                echo '<p class="metric-line"><span class="metric-line__label">Production actuelle</span><span class="' . $currentClass . '">' . $currentDisplay . htmlspecialchars($unitSuffix) . '</span></p>';
                                echo '<p class="metric-line"><span class="metric-line__label">Production prochain niveau</span><span class="' . $nextClass . '">' . $nextDisplay . htmlspecialchars($unitSuffix) . '</span></p>';
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
                                    echo '<li class="metric-line"><span class="metric-line__label">' . htmlspecialchars($label) . '</span><span class="metric-line__value metric-line__value--neutral">' . number_format((int) $value) . '</span></li>';
                                }
                                echo '</ul>';
                                echo '<p class="metric-section__title">' . htmlspecialchars($storageLabel) . ' prochain niveau</p>';
                                echo '<ul class="metric-section__list">';
                                foreach ($storageNext as $resource => $value) {
                                    $label = $resourceLabels[$resource] ?? ucfirst((string) $resource);
                                    echo '<li class="metric-line"><span class="metric-line__label">' . htmlspecialchars($label) . '</span><span class="metric-line__value metric-line__value--positive">' . number_format((int) $value) . '</span></li>';
                                }
                                echo '</ul>';
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

                                $currentDisplay = number_format($displayCurrent);
                                $nextDisplay = number_format($displayNext);

                                echo '<p class="metric-line"><span class="metric-line__label">' . htmlspecialchars($labelCurrent) . '</span><span class="' . $currentClass . '">' . htmlspecialchars($currentDisplay) . htmlspecialchars($unitSuffix) . '</span></p>';
                                echo '<p class="metric-line"><span class="metric-line__label">' . htmlspecialchars($labelNext) . '</span><span class="' . $nextClass . '">' . htmlspecialchars($nextDisplay) . htmlspecialchars($unitSuffix) . '</span></p>';
                            }
                            echo '</div>';

                            if (!($requirements['ok'] ?? true)) {
                                echo '<div class="building-card__block building-card__block--requirements">';
                                echo '<h3>Pré-requis</h3>';
                                echo '<ul class="building-card__requirements">';
                                foreach ($requirements['missing'] as $missing) {
                                    $label = htmlspecialchars((string) ($missing['label'] ?? $missing['key'] ?? ''));
                                    $current = number_format((int) ($missing['current'] ?? 0));
                                    $required = number_format((int) ($missing['level'] ?? 0));
                                    echo '<li><span class="building-card__requirement-name">' . $label . '</span><span class="building-card__requirement-progress">(' . $current . '/' . $required . ')</span></li>';
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
