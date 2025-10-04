<?php
/** @var array $dashboard Résumé des données du joueur. */
/** @var string $baseUrl URL de base pour les liens. */
/** @var array $flashes Messages flash affichés. */
/** @var int|null $currentUserId Identifiant de l’utilisateur connecté. */
/** @var string|null $csrf_logout Jeton CSRF pour la déconnexion. */
/** @var array<int, \App\Domain\Entity\Planet> $planets Liste des planètes. */
/** @var int|null $selectedPlanetId Identifiant de la planète sélectionnée. */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary Résumé de la planète pour le layout. */
$title = $title ?? 'Vue d’ensemble';
require_once __DIR__ . '/../../components/helpers.php';

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
$now = new DateTimeImmutable();
$serverNow = time();
$layoutBodyClasses = 'is-bootstrapized';
ob_start();
?>
<section class="dashboard container-xxl">
    <div class="card dashboard-banner card-highlight">
        <div class="card-body">
            <div class="dashboard-banner__heading">
                <span class="dashboard-banner__eyebrow">Empire galactique</span>
                <h1>Tableau de bord</h1>
            </div>
            <ul class="dashboard-banner__stats">
                <li>
                    <span class="dashboard-banner__label">Planète active</span>
                    <strong class="dashboard-banner__value"><?= $activePlanet ? htmlspecialchars($activePlanet->getName()) : 'Aucune planète' ?></strong>
                </li>
                <li>
                    <span class="dashboard-banner__label">Score impérial</span>
                    <strong class="dashboard-banner__value"><?= format_number($empire['points'] ?? 0) ?></strong>
                </li>
            </ul>
        </div>
    </div>
    <div class="row g-4 g-xl-5 align-items-start">
        <div class="col-12 col-xl-8 d-flex flex-column gap-4 gap-xl-5 dashboard-main">
            <article class="card card-highlight h-100">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Vue d’ensemble</h2>
                        <p class="card-subtitle">Synthèse des forces civiles, scientifiques et militaires.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="metrics metrics--compact">
                        <div class="metric mb-3">
                            <span class="metric__label">Points d’infrastructure</span>
                            <strong class="metric__value"><?= format_number($empire['buildingPoints'] ?? 0) ?></strong>
                        </div>
                        <div class="metric mb-3">
                            <span class="metric__label">Points scientifiques</span>
                            <strong class="metric__value"><?= format_number($empire['sciencePoints'] ?? 0) ?></strong>
                        </div>
                        <div class="metric mb-3">
                            <span class="metric__label">Points militaires</span>
                            <strong class="metric__value"><?= format_number($empire['militaryPoints'] ?? 0) ?></strong>
                        </div>
                    </div>
                </div>
            </article>
            <article class="card h-100">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Production en cours</h2>
                        <p class="card-subtitle">Bâtiments, recherches et chantiers spatiaux alignés.</p>
                    </div>
                </div>
                <div class="card-body mb-3">
                    <div class="row row-gap-3">
                        <div class="col-12">
                            <div class="card production-card h-100">
                                <div class="card-header">
                                    <h3 class="card-title">Bâtiments</h3>
                                </div>
                                <div class="card-body">
                                    <?php $buildJob = $queues['buildings']['next'] ?? null; ?>
                                    <?php
$buildEndTime = null;
if ($buildJob) {
    if (!empty($buildJob['endsAt']) && $buildJob['endsAt'] instanceof \DateTimeImmutable) {
        $buildEndTime = $buildJob['endsAt']->getTimestamp();
    } elseif (isset($buildJob['remaining'])) {
        $buildEndTime = $serverNow + max(0, (int) $buildJob['remaining']);
    }
}
?>
                                    <?php if (($queues['buildings']['count'] ?? 0) === 0 || !$buildJob): ?>
                                        <p class="production-card__empty">Aucune amélioration planifiée.</p>
                                    <?php else: ?>
                                        <p class="production-card__title"><?= htmlspecialchars($buildJob['label'] ?? $buildJob['building']) ?> • niveau <?= format_number($buildJob['targetLevel']) ?></p>
                                        <?php if ($buildEndTime !== null): ?>
                                            <p class="production-card__time" data-countdown-container data-server-now="<?= $serverNow ?>" data-endtime="<?= (int) $buildEndTime ?>">
                                                Termine dans <span class="countdown"><?= htmlspecialchars(format_duration((int) ($buildJob['remaining'] ?? 0))) ?></span>
                                            </p>
                                        <?php else: ?>
                                            <p class="production-card__time">Termine dans <?= htmlspecialchars(format_duration((int) ($buildJob['remaining'] ?? 0))) ?></p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer production-card__footer">
                                    <span><?= format_number($queues['buildings']['count'] ?? 0) ?> amélioration(s) en attente</span>
                                    <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars($baseUrl) ?>/colony?planet=<?= $selectedPlanetId ?>">Ouvrir la colonie</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 ">
                            <div class="card production-card h-100">
                                <div class="card-header">
                                    <h3 class="card-title">Recherches</h3>
                                </div>
                                <div class="card-body">
                                    <?php $researchJob = $queues['research']['next'] ?? null; ?>
                                    <?php
$researchEndTime = null;
if ($researchJob) {
    if (!empty($researchJob['endsAt']) && $researchJob['endsAt'] instanceof \DateTimeImmutable) {
        $researchEndTime = $researchJob['endsAt']->getTimestamp();
    } elseif (isset($researchJob['remaining'])) {
        $researchEndTime = $serverNow + max(0, (int) $researchJob['remaining']);
    }
}
?>
                                    <?php if (($queues['research']['count'] ?? 0) === 0 || !$researchJob): ?>
                                        <p class="production-card__empty">Aucune étude active pour le moment.</p>
                                    <?php else: ?>
                                        <p class="production-card__title"><?= htmlspecialchars($researchJob['label'] ?? $researchJob['research']) ?> • niveau <?= format_number($researchJob['targetLevel'] ?? 0) ?></p>
                                        <?php if ($researchEndTime !== null): ?>
                                            <p class="production-card__time" data-countdown-container data-server-now="<?= $serverNow ?>" data-endtime="<?= (int) $researchEndTime ?>">
                                                Termine dans <span class="countdown"><?= htmlspecialchars(format_duration((int) ($researchJob['remaining'] ?? 0))) ?></span>
                                            </p>
                                        <?php else: ?>
                                            <p class="production-card__time">Termine dans <?= htmlspecialchars(format_duration((int) ($researchJob['remaining'] ?? 0))) ?></p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer production-card__footer">
                                    <span><?= format_number($queues['research']['count'] ?? 0) ?> programme(s) planifié(s)</span>
                                    <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars($baseUrl) ?>/research?planet=<?= $selectedPlanetId ?>">Accéder au laboratoire</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card production-card h-100">
                                <div class="card-header">
                                    <h3 class="card-title">Chantier spatial</h3>
                                </div>
                                <div class="card-body">
                                    <?php $shipJob = $queues['shipyard']['next'] ?? null; ?>
                                    <?php
$shipEndTime = null;
if ($shipJob) {
    if (!empty($shipJob['endsAt']) && $shipJob['endsAt'] instanceof \DateTimeImmutable) {
        $shipEndTime = $shipJob['endsAt']->getTimestamp();
    } elseif (isset($shipJob['remaining'])) {
        $shipEndTime = $serverNow + max(0, (int) $shipJob['remaining']);
    }
}
?>
                                    <?php if (($queues['shipyard']['count'] ?? 0) === 0 || !$shipJob): ?>
                                        <p class="production-card__empty">Aucune commande de vaisseau en file.</p>
                                    <?php else: ?>
                                        <p class="production-card__title"><?= htmlspecialchars($shipJob['label'] ?? $shipJob['ship']) ?> × <?= format_number($shipJob['quantity'] ?? 0) ?></p>
                                        <?php if ($shipEndTime !== null): ?>
                                            <p class="production-card__time" data-countdown-container data-server-now="<?= $serverNow ?>" data-endtime="<?= (int) $shipEndTime ?>">
                                                Livraison dans <span class="countdown"><?= htmlspecialchars(format_duration((int) ($shipJob['remaining'] ?? 0))) ?></span>
                                            </p>
                                        <?php else: ?>
                                            <p class="production-card__time">Livraison dans <?= htmlspecialchars(format_duration((int) ($shipJob['remaining'] ?? 0))) ?></p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer production-card__footer">
                                    <span><?= format_number($queues['shipyard']['count'] ?? 0) ?> commande(s) actives</span>
                                    <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars($baseUrl) ?>/shipyard?planet=<?= $selectedPlanetId ?>">Accéder au chantier</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </div>
        <aside class="col-12 col-xl-4 d-flex flex-column gap-4 gap-xl-5 dashboard-side">
            <article class="card planet-summary h-100">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Planète sélectionnée</h2>
                        <p class="card-subtitle">Caractéristiques planétaires essentielles.</p>
                    </div>
                </div>
                <div class="card-body planet-summary__body">
                    <div class="planet-summary__preview"></div>
                    <?php if ($activePlanet): ?>
                        <h3 class="planet-summary__name"><?= htmlspecialchars($activePlanet->getName()) ?></h3>
                        <ul class="planet-summary__resources planet-summary__characteristics">
                            <li>
                                <div class="planet-summary__resource-label">
                                    <span>Taille</span>
                                </div>
                                <div class="planet-summary__resource-values">
                                    <strong><?= htmlspecialchars(format_number(max(0, $activePlanet->getDiameter()))) ?> km</strong>
                                </div>
                            </li>
                            <li>
                                <div class="planet-summary__resource-label">
                                    <span>Température maximale</span>
                                </div>
                                <div class="planet-summary__resource-values">
                                    <strong><?= htmlspecialchars(format_number($activePlanet->getTemperatureMax())) ?> °C</strong>
                                </div>
                            </li>
                            <li>
                                <div class="planet-summary__resource-label">
                                    <span>Température minimale</span>
                                </div>
                                <div class="planet-summary__resource-values">
                                    <strong><?= htmlspecialchars(format_number($activePlanet->getTemperatureMin())) ?> °C</strong>
                                </div>
                            </li>
                        </ul>
                        <p class="planet-summary__update mb-2">Mise à jour : <?= $now->format('d/m/Y H:i') ?></p>
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
