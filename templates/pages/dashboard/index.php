<?php
/** @var array $dashboard */
/** @var string $baseUrl */
/** @var array $flashes */
/** @var int|null $currentUserId */
/** @var string|null $csrf_logout */
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var int|null $selectedPlanetId */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary */
$title = $title ?? 'Vue d’ensemble';
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

$empire = $dashboard['empire'] ?? [
    'points' => 0,
    'buildingPoints' => 0,
    'sciencePoints' => 0,
    'militaryPoints' => 0,
    'militaryPower' => 0,
    'planetCount' => 0,
];
$planetSummaries = $dashboard['planets'] ?? [];
$selectedPlanetId = $selectedPlanetId ?? null;
if ($selectedPlanetId === null && $activePlanetSummary) {
    $selectedPlanetId = $activePlanetSummary['planet']->getId();
}
$activeSummary = null;
foreach ($planetSummaries as $summary) {
    if ($summary['planet']->getId() === $selectedPlanetId) {
        $activeSummary = $summary;
        break;
    }
}
if ($activeSummary === null && !empty($planetSummaries)) {
    $activeSummary = $planetSummaries[0];
    $selectedPlanetId = $activeSummary['planet']->getId();
}
$queues = $activeSummary['queues'] ?? [
    'buildings' => ['count' => 0, 'next' => null],
    'research' => ['count' => 0, 'next' => null],
    'shipyard' => ['count' => 0, 'next' => null],
];
$activePlanet = $activeSummary['planet'] ?? null;
$activeProduction = $activeSummary['production'] ?? ['metal' => 0, 'crystal' => 0, 'hydrogen' => 0, 'energy' => 0];
$now = new DateTimeImmutable();
$resourceMeta = [
    'metal' => ['label' => 'Métal', 'icon' => 'metal'],
    'crystal' => ['label' => 'Cristal', 'icon' => 'crystal'],
    'hydrogen' => ['label' => 'Hydrogène', 'icon' => 'hydrogen'],
    'energy' => ['label' => 'Énergie', 'icon' => 'energy'],
];
ob_start();
?>
<section class="dashboard">
    <div class="dashboard-banner">
        <div class="dashboard-banner__heading">
            <span class="dashboard-banner__eyebrow">Empire galactique</span>
            <h1>Tableau de bord</h1>
        </div>
        <ul class="dashboard-banner__stats">
            <li>
                <span class="dashboard-banner__label">Planète active</span>
                <strong class="dashboard-banner__value"><?= $activePlanet ? htmlspecialchars($activePlanet->getName()) : 'Aucune planète' ?></strong>
            </li>
        </ul>
    </div>
    <div class="dashboard-layout">
        <div class="dashboard-main">
            <article class="panel panel--highlight">
                <header class="panel__header">
                    <h2>Vue d’ensemble</h2>
                    <p class="panel__subtitle">Synthèse des forces civiles, scientifiques et militaires.</p>
                </header>
                <div class="panel__body metrics metrics--compact">
                    <div class="metric">
                        <span class="metric__label">Points d’infrastructure</span>
                        <strong class="metric__value"><?= number_format($empire['buildingPoints'] ?? 0) ?></strong>
                        <span class="metric__hint">Total des niveaux de bâtiments développés.</span>
                    </div>
                    <div class="metric">
                        <span class="metric__label">Points scientifiques</span>
                        <strong class="metric__value"><?= number_format($empire['sciencePoints'] ?? 0) ?></strong>
                        <span class="metric__hint">Somme des niveaux de recherches actives.</span>
                    </div>
                    <div class="metric">
                        <span class="metric__label">Puissance militaire</span>
                        <strong class="metric__value"><?= number_format($empire['militaryPoints'] ?? ($empire['militaryPower'] ?? 0)) ?></strong>
                        <span class="metric__hint">Valeur combinée d’attaque et de défense de la flotte.</span>
                    </div>
                </div>
            </article>
            <article class="panel">
                <header class="panel__header">
                    <h2>Production en cours</h2>
                    <p class="panel__subtitle">Bâtiments, recherches et chantiers spatiaux alignés.</p>
                </header>
                <div class="panel__body production-grid">
                    <div class="production-card">
                        <h3>Bâtiments</h3>
                        <?php $buildJob = $queues['buildings']['next'] ?? null; ?>
                        <?php if (($queues['buildings']['count'] ?? 0) === 0 || !$buildJob): ?>
                            <p class="production-card__empty">Aucune amélioration planifiée.</p>
                        <?php else: ?>
                            <p class="production-card__title"><?= htmlspecialchars($buildJob['label'] ?? $buildJob['building']) ?> • niveau <?= number_format($buildJob['targetLevel']) ?></p>
                            <p class="production-card__time">Termine dans <?= htmlspecialchars(format_duration((int) $buildJob['remaining'])) ?></p>
                        <?php endif; ?>
                        <footer class="production-card__footer">
                            <span><?= number_format($queues['buildings']['count'] ?? 0) ?> amélioration(s) en attente</span>
                            <a class="link-button" href="<?= htmlspecialchars($baseUrl) ?>/colony?planet=<?= $selectedPlanetId ?>">Ouvrir la colonie</a>
                        </footer>
                    </div>
                    <div class="production-card">
                        <h3>Recherches</h3>
                        <?php $researchJob = $queues['research']['next'] ?? null; ?>
                        <?php if (($queues['research']['count'] ?? 0) === 0 || !$researchJob): ?>
                            <p class="production-card__empty">Aucune étude active pour le moment.</p>
                        <?php else: ?>
                            <p class="production-card__title"><?= htmlspecialchars($researchJob['label'] ?? $researchJob['research']) ?> • niveau <?= number_format($researchJob['targetLevel'] ?? 0) ?></p>
                            <p class="production-card__time">Termine dans <?= htmlspecialchars(format_duration((int) $researchJob['remaining'])) ?></p>
                        <?php endif; ?>
                        <footer class="production-card__footer">
                            <span><?= number_format($queues['research']['count'] ?? 0) ?> programme(s) planifié(s)</span>
                            <a class="link-button" href="<?= htmlspecialchars($baseUrl) ?>/research?planet=<?= $selectedPlanetId ?>">Accéder au laboratoire</a>
                        </footer>
                    </div>
                    <div class="production-card">
                        <h3>Chantier spatial</h3>
                        <?php $shipJob = $queues['shipyard']['next'] ?? null; ?>
                        <?php if (($queues['shipyard']['count'] ?? 0) === 0 || !$shipJob): ?>
                            <p class="production-card__empty">Aucune commande de vaisseau en file.</p>
                        <?php else: ?>
                            <p class="production-card__title"><?= htmlspecialchars($shipJob['label'] ?? $shipJob['ship']) ?> × <?= number_format($shipJob['quantity'] ?? 0) ?></p>
                            <p class="production-card__time">Livraison dans <?= htmlspecialchars(format_duration((int) $shipJob['remaining'])) ?></p>
                        <?php endif; ?>
                        <footer class="production-card__footer">
                            <span><?= number_format($queues['shipyard']['count'] ?? 0) ?> commande(s) actives</span>
                            <a class="link-button" href="<?= htmlspecialchars($baseUrl) ?>/shipyard?planet=<?= $selectedPlanetId ?>">Accéder au chantier</a>
                        </footer>
                    </div>
                </div>
            </article>
        </div>
        <aside class="dashboard-side">
            <article class="panel planet-summary">
                <header class="panel__header">
                    <h2>Planète sélectionnée</h2>
                    <p class="panel__subtitle">Ressources stockées et rythme de production.</p>
                </header>
                <div class="panel__body planet-summary__body">
                    <div class="planet-summary__preview"></div>
                    <?php if ($activePlanet): ?>
                        <h3 class="planet-summary__name"><?= htmlspecialchars($activePlanet->getName()) ?></h3>
                        <ul class="planet-summary__resources">
                            <?php foreach ($resourceMeta as $key => $meta): ?>
                                <?php
                                $currentValue = match ($key) {
                                    'metal' => $activePlanet->getMetal(),
                                    'crystal' => $activePlanet->getCrystal(),
                                    'hydrogen' => $activePlanet->getHydrogen(),
                                    'energy' => $activePlanet->getEnergy(),
                                };
                                $perHour = $activeProduction[$key] ?? 0;
                                $ratePrefix = $key === 'energy' ? '' : ($perHour > 0 ? '+' : '');
                                ?>
                                <li>
                                    <div class="planet-summary__resource-label">
                                        <svg class="icon icon-sm" aria-hidden="true">
                                            <use href="<?= htmlspecialchars($baseUrl) ?>/assets/svg/sprite.svg#icon-<?= htmlspecialchars($meta['icon']) ?>"></use>
                                        </svg>
                                        <span><?= htmlspecialchars($meta['label']) ?></span>
                                    </div>
                                    <div class="planet-summary__resource-values">
                                        <strong><?= number_format($currentValue) ?></strong>
                                        <span><?= $ratePrefix . number_format($perHour) ?><?= $key === 'energy' ? '' : '/h' ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="planet-summary__update">Mise à jour : <?= $now->format('d/m/Y H:i') ?></p>
                        <div class="planet-summary__actions">
                            <a class="button button--primary" href="<?= htmlspecialchars($baseUrl) ?>/colony?planet=<?= $selectedPlanetId ?>">Gérer la colonie</a>
                            <a class="button button--ghost" href="<?= htmlspecialchars($baseUrl) ?>/research?planet=<?= $selectedPlanetId ?>">Programme scientifique</a>
                        </div>
                    <?php else: ?>
                        <p>Aucune planète active.</p>
                    <?php endif; ?>
                </div>
            </article>
        </aside>
    </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/base.php';
