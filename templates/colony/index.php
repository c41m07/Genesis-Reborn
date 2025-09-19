<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var array|null $overview */
/** @var string $baseUrl */
/** @var string|null $csrf_upgrade */
/** @var int|null $selectedPlanetId */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary */

$title = $title ?? 'Colonie';
$planet = $overview['planet'] ?? null;
$queue = $overview['queue'] ?? ['count' => 0, 'jobs' => []];
$buildings = $overview['buildings'] ?? [];

$icon = require __DIR__ . '/../components/_icon.php';
$resourceBar = require __DIR__ . '/../components/_resource_bar.php';
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

$resourceSummaryData = [];
if (is_array($activePlanetSummary)) {
    foreach ($activePlanetSummary['resources'] as $key => $data) {
        $resourceSummaryData[$key] = [
            'label' => $resourceLabels[$key] ?? ucfirst((string) $key),
            'value' => $data['value'] ?? 0,
            'perHour' => $data['perHour'] ?? 0,
        ];
    }
}

$assetBase = rtrim($baseUrl, '/');

ob_start();
?>
<section class="page-header">
    <div>
        <h1>Gestion de la colonie</h1>
        <?php if ($planet): ?>
            <p class="page-header__subtitle">Optimisez les infrastructures de <?= htmlspecialchars($planet->getName()) ?> pour soutenir l’expansion de votre empire.</p>
        <?php else: ?>
            <p class="page-header__subtitle">Sélectionnez une planète pour gérer ses bâtiments et sa production.</p>
        <?php endif; ?>
    </div>
    <div class="page-header__actions">
        <?php if (!empty($planets)): ?>
            <form class="planet-switcher" method="get" action="<?= htmlspecialchars($baseUrl) ?>/colony">
                <label class="planet-switcher__label" for="planet-selector-colony">Planète</label>
                <select class="planet-switcher__select" id="planet-selector-colony" name="planet" data-auto-submit>
                    <?php foreach ($planets as $planetOption): ?>
                        <option value="<?= $planetOption->getId() ?>"<?= ($planet && $planetOption->getId() === $planet->getId()) ? ' selected' : '' ?>><?= htmlspecialchars($planetOption->getName()) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <?php if ($planet): ?>
            <a class="button button--ghost" href="<?= htmlspecialchars($baseUrl) ?>/research?planet=<?= $planet->getId() ?>">Accéder à la recherche</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($overview === null): ?>
    <?= $card([
        'title' => 'Aucune colonie active',
        'body' => static function (): void {
            echo '<p>Choisissez une planète via le sélecteur ci-dessus pour afficher ses infrastructures.</p>';
        },
    ]) ?>
<?php else: ?>
    <?php if ($resourceSummaryData !== []): ?>
        <?= $card([
            'title' => 'Ressources planétaires',
            'subtitle' => 'Production horaire actuelle',
            'body' => static function () use ($resourceBar, $resourceSummaryData, $baseUrl): void {
                echo $resourceBar($resourceSummaryData, ['baseUrl' => $baseUrl]);
            },
        ]) ?>
    <?php endif; ?>

    <?= $card([
        'title' => 'File de construction',
        'subtitle' => 'Suivi des améliorations en cours',
        'body' => static function () use ($queue): void {
            if (($queue['count'] ?? 0) === 0) {
                echo '<p class="empty-state">Aucune amélioration n’est programmée. Lancez une construction pour développer votre colonie.</p>';

                return;
            }

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
        },
    ]) ?>

    <div class="grid grid--cards">
        <?php foreach ($buildings as $building): ?>
            <?php $definition = $building['definition']; ?>
            <?php
            $production = $building['production'];
            $energy = $building['energy'];
            $requirements = $building['requirements'];
            $canUpgrade = (bool) ($building['canUpgrade'] ?? false);
            $status = $canUpgrade ? '' : 'is-locked';
            ?>
            <?php $imagePath = $definition->getImage(); ?>
            <?= $card([
                'title' => $definition->getLabel(),
                'subtitle' => 'Niveau actuel ' . number_format((int) $building['level']),
                'illustration' => $imagePath ? $assetBase . '/' . ltrim($imagePath, '/') : null,
                'status' => $status,
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

                    echo '<form method="post" action="' . htmlspecialchars($baseUrl) . '/colony?planet=' . $planet->getId() . '">';
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
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';
