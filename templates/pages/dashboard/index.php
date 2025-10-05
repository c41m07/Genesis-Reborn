<?php
/**
 * @var array{
 *     movements?: list<array{
 *         id: int,
 *         mission: string,
 *         status: string,
 *         origin: array{name: string, coordinates: array{galaxy: int, system: int, position: int}},
 *         destination: array{name: string, coordinates: array{galaxy: int, system: int, position: int}},
 *         eta: ?\DateTimeImmutable
 *     }>,
 *     planets: array,
 *     empire: array
 * } $dashboard Résumé des données du joueur.
 */
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
$movingFleets = $dashboard['movements'] ?? [];
$layoutBodyClasses = 'is-bootstrapized';
ob_start();
?>
<section class="ui-page container-xxl py-5 d-flex flex-column gap-5">
    <article class="ui-card ui-card--surface dashboard-hero">
        <header class="ui-card__header">
            <div class="ui-card__meta">
                <span class="ui-card__eyebrow">Empire galactique</span>
                <h1 class="ui-card__title">Tableau de bord</h1>
                <p class="ui-card__subtitle">Vue synthétique de l’état de votre empire et de vos priorités en cours.</p>
            </div>
        </header>
        <div class="ui-card__body">
            <dl class="dashboard-hero__stats">
                <div class="dashboard-hero__stat">
                    <dt class="dashboard-hero__label">Planète active</dt>
                    <dd class="dashboard-hero__value"><?= $activePlanet ? htmlspecialchars($activePlanet->getName()) : 'Aucune planète' ?></dd>
                </div>
                <div class="dashboard-hero__stat">
                    <dt class="dashboard-hero__label">Score impérial</dt>
                    <dd class="dashboard-hero__value"><?= format_number($empire['points'] ?? 0) ?></dd>
                </div>
            </dl>
            <section class="dashboard-hero__fleets" aria-labelledby="dashboard-fleet-heading">
                <div class="dashboard-hero__fleets-header">
                    <h2 id="dashboard-fleet-heading" class="dashboard-hero__fleets-title">Flottes en mouvement</h2>
                    <span class="dashboard-hero__fleets-count"><?= format_number(count($movingFleets)) ?></span>
                </div>
                <?php if ($movingFleets === []): ?>
                    <p class="dashboard-hero__fleets-empty">Aucune flotte n’est actuellement en déplacement.</p>
                <?php else: ?>
                    <ul class="dashboard-hero__fleet-list">
                        <?php foreach ($movingFleets as $movement): ?>
                            <?php
                                $origin = $movement['origin'];
                                $destination = $movement['destination'];
                                $missionLabel = match ($movement['mission']) {
                                    'transport' => 'Transport',
                                    default => ucfirst((string)$movement['mission']),
                                };
                                $statusLabel = match ($movement['status']) {
                                    'returning' => 'Retour',
                                    'holding' => 'En attente',
                                    default => 'Aller',
                                };
                                $eta = $movement['eta'] ?? null;
                                $etaTimestamp = $eta instanceof \DateTimeImmutable ? $eta->getTimestamp() : null;
                                $etaLabel = $etaTimestamp !== null
                                    ? format_duration(max(0, $etaTimestamp - $serverNow))
                                    : '—';
                            ?>
                            <li class="dashboard-hero__fleet-item">
                                <div class="dashboard-hero__fleet-path">
                                    <div class="dashboard-hero__fleet-planet">
                                        <strong><?= htmlspecialchars((string)$origin['name'], ENT_QUOTES) ?></strong>
                                        <small><?= htmlspecialchars(sprintf('(%d:%d:%d)', $origin['coordinates']['galaxy'], $origin['coordinates']['system'], $origin['coordinates']['position']), ENT_QUOTES) ?></small>
                                    </div>
                                    <span class="dashboard-hero__fleet-arrow" aria-hidden="true">→</span>
                                    <div class="dashboard-hero__fleet-planet">
                                        <strong><?= htmlspecialchars((string)$destination['name'], ENT_QUOTES) ?></strong>
                                        <small><?= htmlspecialchars(sprintf('(%d:%d:%d)', $destination['coordinates']['galaxy'], $destination['coordinates']['system'], $destination['coordinates']['position']), ENT_QUOTES) ?></small>
                                    </div>
                                </div>
                                <div class="dashboard-hero__fleet-meta">
                                    <span class="dashboard-hero__fleet-mission"><?= htmlspecialchars($missionLabel . ' • ' . $statusLabel, ENT_QUOTES) ?></span>
                                    <?php if ($etaTimestamp !== null): ?>
                                        <p class="dashboard-hero__fleet-countdown" data-countdown-container data-server-now="<?= $serverNow ?>" data-endtime="<?= $etaTimestamp ?>">
                                            Arrivée dans <span class="countdown"><?= htmlspecialchars($etaLabel, ENT_QUOTES) ?></span>
                                        </p>
                                    <?php else: ?>
                                        <p class="dashboard-hero__fleet-countdown">Arrivée inconnue</p>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </article>
    <div class="row g-4 g-xl-5 align-items-start">
        <div class="col-12 col-xl-8 d-flex flex-column gap-4 gap-xl-5">
            <article class="ui-card dashboard-overview">
                <header class="ui-card__header">
                    <div class="ui-card__meta">
                        <h2 class="ui-card__title">Vue d’ensemble</h2>
                        <p class="ui-card__subtitle">Synthèse des forces civiles, scientifiques et militaires.</p>
                    </div>
                </header>
                <div class="ui-card__body">
                    <dl class="metric-grid" role="list">
                        <div class="metric-grid__item" role="listitem">
                            <dt class="metric-grid__label">Points d’infrastructure</dt>
                            <dd class="metric-grid__value"><?= format_number($empire['buildingPoints'] ?? 0) ?></dd>
                        </div>
                        <div class="metric-grid__item" role="listitem">
                            <dt class="metric-grid__label">Points scientifiques</dt>
                            <dd class="metric-grid__value"><?= format_number($empire['sciencePoints'] ?? 0) ?></dd>
                        </div>
                        <div class="metric-grid__item" role="listitem">
                            <dt class="metric-grid__label">Points militaires</dt>
                            <dd class="metric-grid__value"><?= format_number($empire['militaryPoints'] ?? 0) ?></dd>
                        </div>
                    </dl>
                </div>
            </article>
            <article class="ui-card dashboard-production">
                <header class="ui-card__header">
                    <div class="ui-card__meta">
                        <h2 class="ui-card__title">Production en cours</h2>
                        <p class="ui-card__subtitle">Bâtiments, recherches et chantiers spatiaux alignés.</p>
                    </div>
                </header>
                <div class="ui-card__body">
                    <div class="production-groups">
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
                        <article class="ui-card ui-card--compact production-card">
                            <header class="ui-card__header">
                                <div class="ui-card__meta">
                                    <h3 class="ui-card__title">Bâtiments</h3>
                                </div>
                                <span class="production-card__badge"><?= format_number($queues['buildings']['count'] ?? 0) ?> en attente</span>
                            </header>
                            <div class="ui-card__body">
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
                            <footer class="ui-card__footer production-card__footer">
                                <a class="ui-button ui-button--neutral ui-button--sm" href="<?= htmlspecialchars($baseUrl) ?>/colony?planet=<?= $selectedPlanetId ?>">
                                    <span class="ui-button__label">Ouvrir la colonie</span>
                                </a>
                            </footer>
                        </article>
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
                        <article class="ui-card ui-card--compact production-card">
                            <header class="ui-card__header">
                                <div class="ui-card__meta">
                                    <h3 class="ui-card__title">Recherches</h3>
                                </div>
                                <span class="production-card__badge"><?= format_number($queues['research']['count'] ?? 0) ?> prévues</span>
                            </header>
                            <div class="ui-card__body">
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
                            <footer class="ui-card__footer production-card__footer">
                                <a class="ui-button ui-button--neutral ui-button--sm" href="<?= htmlspecialchars($baseUrl) ?>/research?planet=<?= $selectedPlanetId ?>">
                                    <span class="ui-button__label">Accéder au laboratoire</span>
                                </a>
                            </footer>
                        </article>
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
                        <article class="ui-card ui-card--compact production-card">
                            <header class="ui-card__header">
                                <div class="ui-card__meta">
                                    <h3 class="ui-card__title">Chantier spatial</h3>
                                </div>
                                <span class="production-card__badge"><?= format_number($queues['shipyard']['count'] ?? 0) ?> commandes</span>
                            </header>
                            <div class="ui-card__body">
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
                            <footer class="ui-card__footer production-card__footer">
                                <a class="ui-button ui-button--neutral ui-button--sm" href="<?= htmlspecialchars($baseUrl) ?>/shipyard?planet=<?= $selectedPlanetId ?>">
                                    <span class="ui-button__label">Accéder au chantier</span>
                                </a>
                            </footer>
                        </article>
                    </div>
                </div>
            </article>
        </div>
        <aside class="col-12 col-xl-4 d-flex flex-column gap-4 gap-xl-5">
            <article class="ui-card planet-summary">
                <header class="ui-card__header">
                    <div class="ui-card__meta">
                        <h2 class="ui-card__title">Planète sélectionnée</h2>
                        <p class="ui-card__subtitle">Caractéristiques planétaires essentielles.</p>
                    </div>
                </header>
                <div class="ui-card__body planet-summary__body">
                    <div class="planet-summary__preview" aria-hidden="true"></div>
                    <?php if ($activePlanet): ?>
                        <h3 class="planet-summary__name"><?= htmlspecialchars($activePlanet->getName()) ?></h3>
                        <dl class="planet-summary__characteristics">
                            <div class="planet-summary__item">
                                <dt class="planet-summary__term">Taille</dt>
                                <dd class="planet-summary__definition"><?= htmlspecialchars(format_number(max(0, $activePlanet->getDiameter()))) ?> km</dd>
                            </div>
                            <div class="planet-summary__item">
                                <dt class="planet-summary__term">Température maximale</dt>
                                <dd class="planet-summary__definition"><?= htmlspecialchars(format_number($activePlanet->getTemperatureMax())) ?> °C</dd>
                            </div>
                            <div class="planet-summary__item">
                                <dt class="planet-summary__term">Température minimale</dt>
                                <dd class="planet-summary__definition"><?= htmlspecialchars(format_number($activePlanet->getTemperatureMin())) ?> °C</dd>
                            </div>
                        </dl>
                        <p class="planet-summary__update">Mise à jour : <?= $now->format('d/m/Y H:i') ?></p>
                    <?php else: ?>
                        <p class="planet-summary__empty">Aucune planète active sélectionnée.</p>
                    <?php endif; ?>
                </div>
            </article>
        </aside>
    </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/base.php';
