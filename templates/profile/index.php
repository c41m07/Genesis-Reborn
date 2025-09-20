<?php
/** @var array{email: string, username: string} $account */
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var array $dashboard */
/** @var string $baseUrl */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary */

$title = $title ?? 'Profil commandant';
$icon = require __DIR__ . '/../components/_icon.php';
$resourceBar = require __DIR__ . '/../components/_resource_bar.php';
$card = require __DIR__ . '/../components/_card.php';
require_once __DIR__ . '/../components/helpers.php';
$planets = $planets ?? [];

$dashboard = $dashboard ?? [];
$empire = $dashboard['empire'] ?? ['points' => 0, 'militaryPower' => 0, 'planetCount' => count($planets)];
$researchTotals = $dashboard['researchTotals'] ?? ['sumLevels' => 0, 'unlocked' => 0, 'best' => ['label' => 'Aucune technologie', 'level' => 0]];
$resourceTotals = $dashboard['totals'] ?? ['metal' => 0, 'crystal' => 0, 'hydrogen' => 0, 'energy' => 0];
$planetSummaries = $dashboard['planets'] ?? [];

$resourceSummaryData = [];
$resourceLabels = ['metal' => 'Métal', 'crystal' => 'Cristal', 'hydrogen' => 'Hydrogène', 'energy' => 'Énergie'];
foreach ($resourceTotals as $key => $value) {
    $resourceSummaryData[$key] = [
        'label' => $resourceLabels[$key] ?? ucfirst((string) $key),
        'value' => $value,
        'perHour' => null,
    ];
}

$account = $account ?? ['email' => '', 'username' => ''];

ob_start();
?>
<section class="page-header">
    <div>
        <h1>Profil du commandant</h1>
        <p class="page-header__subtitle">Retrouvez les informations clés de votre compte et l’état global de votre empire.</p>
    </div>
</section>

<div class="card-grid card-grid--quad">
    <?= $card([
        'title' => 'Identité du compte',
        'subtitle' => 'Informations personnelles et de connexion',
        'body' => static function () use ($account): void {
            echo '<dl class="definition-list">';
            echo '<div><dt>Nom de commandant</dt><dd>' . htmlspecialchars($account['username']) . '</dd></div>';
            echo '<div><dt>Adresse e-mail</dt><dd>' . htmlspecialchars($account['email']) . '</dd></div>';
            echo '</dl>';
        },
    ]) ?>

    <?= $card([
        'title' => 'Statistiques impériales',
        'subtitle' => 'Vue d’ensemble de votre progression solo',
        'body' => static function () use ($empire): void {
            echo '<div class="metrics metrics--compact">';
            echo '<div class="metric"><span class="metric__label">Points d’empire</span><strong class="metric__value">' . number_format((int) ($empire['points'] ?? 0)) . '</strong></div>';
            echo '<div class="metric"><span class="metric__label">Puissance militaire</span><strong class="metric__value">' . number_format((int) ($empire['militaryPower'] ?? 0)) . '</strong></div>';
            echo '<div class="metric"><span class="metric__label">Colonies actives</span><strong class="metric__value">' . number_format((int) ($empire['planetCount'] ?? 0)) . '</strong></div>';
            echo '</div>';
        },
    ]) ?>
</div>

<?php if ($resourceSummaryData !== []): ?>
    <?= $card([
        'title' => 'Ressources totales',
        'subtitle' => 'Inventaire agrégé de vos colonies',
        'body' => static function () use ($resourceBar, $resourceSummaryData, $baseUrl): void {
            echo $resourceBar($resourceSummaryData, ['baseUrl' => $baseUrl, 'showRates' => false]);
        },
    ]) ?>
<?php endif; ?>

<?= $card([
    'title' => 'Progression scientifique',
    'subtitle' => 'Suivi de vos découvertes majeures',
    'body' => static function () use ($researchTotals): void {
        $best = $researchTotals['best'] ?? ['label' => 'Aucune technologie', 'level' => 0];
        echo '<div class="metrics metrics--compact">';
        echo '<div class="metric"><span class="metric__label">Niveaux cumulés</span><strong class="metric__value">' . number_format((int) ($researchTotals['sumLevels'] ?? 0)) . '</strong></div>';
        echo '<div class="metric"><span class="metric__label">Découvertes actives</span><strong class="metric__value">' . number_format((int) ($researchTotals['unlocked'] ?? 0)) . '</strong></div>';
        echo '<div class="metric"><span class="metric__label">Technologie phare</span><strong class="metric__value">' . htmlspecialchars(($best['label'] ?? 'Aucune technologie') . ' • niveau ' . ($best['level'] ?? 0)) . '</strong></div>';
        echo '</div>';
    },
]) ?>

<?php if ($planetSummaries === []): ?>
    <?= $card([
        'title' => 'Aucune colonie enregistrée',
        'body' => static function () use ($baseUrl): void {
            echo '<p>Colonisez une planète via le <a href="' . htmlspecialchars($baseUrl) . '/dashboard">tableau de bord</a> pour afficher ici son résumé.</p>';
        },
    ]) ?>
<?php else: ?>
    <div class="grid grid--stacked">
        <?php foreach ($planetSummaries as $summary): ?>
            <?php
            $planet = $summary['planet'];
            $coordinates = $planet->getCoordinates();
            $production = $summary['production'] ?? ['metal' => 0, 'crystal' => 0, 'hydrogen' => 0, 'energy' => 0];
            $queues = $summary['queues'] ?? ['buildings' => ['count' => 0], 'research' => ['count' => 0], 'shipyard' => ['count' => 0]];
            $energyBalance = $summary['energyBalance'] ?? ['production' => 0, 'consumption' => 0, 'net' => 0];
            $fleet = $summary['fleet']['ships'] ?? [];
            ?>
            <?= $card([
                'title' => $planet->getName(),
                'subtitle' => sprintf('Coordonnées %d:%d:%d', $coordinates['galaxy'], $coordinates['system'], $coordinates['position']),
                'body' => static function () use ($production, $queues, $energyBalance, $fleet, $baseUrl, $icon): void {
                    echo '<div class="planet-profile">';
                    echo '<div class="planet-profile__section">';
                    echo '<h3>Production horaire</h3>';
                    echo '<ul class="resource-list">';
                    $labels = ['metal' => 'Métal', 'crystal' => 'Cristal', 'hydrogen' => 'Hydrogène', 'energy' => 'Énergie'];
                    foreach ($production as $resource => $amount) {
                        $label = $labels[$resource] ?? ucfirst((string) $resource);
                        echo '<li>' . $icon((string) $resource, ['baseUrl' => $baseUrl, 'class' => 'icon-sm']) . '<span>' . htmlspecialchars($label) . '</span><span>' . number_format((int) $amount) . '/h</span></li>';
                    }
                    echo '</ul>';
                    $net = (int) ($energyBalance['net'] ?? 0);
                    $netDisplay = ($net > 0 ? '+' : '') . number_format($net);
                    echo '<p class="planet-profile__energy">Énergie : ' . number_format((int) ($energyBalance['production'] ?? 0)) . ' générée / ' . number_format((int) ($energyBalance['consumption'] ?? 0)) . ' consommée (solde ' . $netDisplay . ').</p>';
                    echo '</div>';

                    echo '<div class="planet-profile__section">';
                    echo '<h3>Files d’attente</h3>';
                    echo '<ul class="queue-summary">';
                    $queueMeta = [
                        'buildings' => 'Bâtiments',
                        'research' => 'Recherche',
                        'shipyard' => 'Chantier spatial',
                    ];
                    foreach ($queueMeta as $key => $label) {
                        $count = (int) ($queues[$key]['count'] ?? 0);
                        $next = $queues[$key]['next'] ?? null;
                        echo '<li>';
                        echo '<strong>' . htmlspecialchars($label) . '</strong>';
                        if ($count === 0 || !$next) {
                            echo '<span>Aucune action programmée</span>';
                        } else {
                            $name = $next['label'] ?? ($next[$key === 'shipyard' ? 'ship' : ($key === 'research' ? 'research' : 'building')] ?? '');
                            $time = format_duration((int) ($next['remaining'] ?? 0));
                            echo '<span>' . htmlspecialchars((string) $name) . ' • fin dans ' . htmlspecialchars($time) . '</span>';
                        }
                        echo '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';

                    echo '<div class="planet-profile__section">';
                    echo '<h3>Flotte stationnée</h3>';
                    if ($fleet === []) {
                        echo '<p class="empty-state">Aucun vaisseau recensé en orbite.</p>';
                    } else {
                        echo '<ul class="fleet-panel__list">';
                        foreach (array_slice($fleet, 0, 4) as $ship) {
                            echo '<li><span class="fleet-panel__ship">' . htmlspecialchars($ship['label'] ?? $ship['key'] ?? 'Vaisseau') . '</span><span class="fleet-panel__qty">× ' . number_format((int) ($ship['quantity'] ?? 0)) . '</span></li>';
                        }
                        echo '</ul>';
                    }
                    echo '</div>';
                    echo '</div>';
                },
            ]) ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';
