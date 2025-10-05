<?php
/**
 * @var array<int, \App\Domain\Entity\Planet> $planets
 * @var int|null $selectedPlanetId
 * @var array{ships: array<int, array{key: string, label: string, quantity: int, attack: int, defense: int, speedUa: float, category: string, role: string, image: string|null, fuelRate: float, cargo: int}>, totalShips: int, power: int, origin?: array{galaxy: int, system: int, position: int}} $fleetOverview
 * @var array<int, array{key: string, label: string, quantity: int, attack: int, defense: int, speedUa: float, category: string, role: string, image: string|null, fuelRate: float, cargo: int}> $availableShips
 * @var array<int, array{id: int, label: string, name: string|null, total: int, is_garrison: bool, ships: array<int, array{key: string, label: string, quantity: int, role: string, image: string|null}>, ships_raw: array<string, int>}> $idleFleets
 * @var int|null $selectedFleetId
 * @var array{id: int, label: string, name: string|null, total: int, is_garrison: bool, ships: array<int, array{key: string, label: string, quantity: int, role: string, image: string|null}>, ships_raw: array<string, int>}|null $selectedFleet
 * @var int|null $garrisonFleetId
 * @var string $baseUrl
 * @var string|null $csrf_create
 * @var string|null $csrf_transfer
 * @var string|null $csrf_rename
 * @var string|null $csrf_delete
 * @var string|null $csrf_manage_mission
 */

$title = $title ?? 'Flotte orbitale';
$card = require __DIR__ . '/../../components/_card.php';
require_once __DIR__ . '/../../components/helpers.php';

$planets = $planets ?? [];
$selectedPlanetId = $selectedPlanetId ?? null;
$idleFleets = $idleFleets ?? [];
$selectedFleetId = $selectedFleetId ?? null;
$selectedFleet = $selectedFleet ?? null;
$garrisonFleetId = $garrisonFleetId ?? null;
$csrf_create = $csrf_create ?? null;
$csrf_transfer = $csrf_transfer ?? null;
$csrf_rename = $csrf_rename ?? null;
$csrf_delete = $csrf_delete ?? null;
$csrf_manage_mission = $csrf_manage_mission ?? null;
$basePath = rtrim((string)($baseUrl ?? ''), '/');

$originCoordinates = $fleetOverview['origin'] ?? ['galaxy' => 1, 'system' => 1, 'position' => 1];
$submittedDestination = $submittedDestination ?? [
    'galaxy' => (int)($originCoordinates['galaxy'] ?? 1),
    'system' => (int)($originCoordinates['system'] ?? 1),
    'position' => (int)($originCoordinates['position'] ?? 1),
];
$submittedResources = array_merge(
    ['metal' => 0, 'crystal' => 0, 'hydrogen' => 0],
    $submittedResources ?? []
);
$submittedMission = $submittedMission ?? 'transport';
$submittedSpeedFactor = isset($submittedSpeedFactor) ? (float)$submittedSpeedFactor : 1.0;
$submittedSpeedFactor = max(0.1, min(1.0, $submittedSpeedFactor));
$planResult = $planResult ?? null;
$planErrors = $planErrors ?? [];

$planEndpoint = $basePath . '/fleet/plan';
$launchEndpoint = $basePath . '/fleet/launch';

$renderPlanErrors = static function (array $errors): string {
    if ($errors === []) {
        return '';
    }

    $items = array_map(
        static fn (string $message): string => '<li>' . htmlspecialchars($message, ENT_QUOTES) . '</li>',
        $errors
    );

    return '<div class="form-errors" role="alert"><strong>Erreurs de planification</strong><ul>'
        . implode('', $items)
        . '</ul></div>';
};

$renderPlanSummary = static function (?array $plan): string {
    if ($plan === null) {
        return '<p class="fleet-mission__placeholder">Simulez un trajet pour estimer la durée, la consommation et l’espace cargo requis. Vos résultats apparaîtront ici.</p>';
    }

    $distance = (float)($plan['distance'] ?? 0);
    $speed = (float)($plan['speed'] ?? 0);
    $travelTime = (int)($plan['travel_time'] ?? 0);
    $arrival = $plan['arrival_time'] ?? null;
    if ($arrival instanceof \DateTimeImmutable) {
        $arrivalFormatted = $arrival->format('d/m/Y H:i');
    } elseif (is_string($arrival) && $arrival !== '') {
        try {
            $arrivalFormatted = (new \DateTimeImmutable($arrival))->format('d/m/Y H:i');
        } catch (\Exception) {
            $arrivalFormatted = $arrival;
        }
    } else {
        $arrivalFormatted = null;
    }

    $fuel = (int)($plan['fuel'] ?? 0);
    $cargoCapacity = (int)($plan['cargo_capacity'] ?? 0);
    $cargoUsed = (int)($plan['cargo_used'] ?? 0);
    $remainingCargo = (int)($plan['remaining_cargo'] ?? 0);

    ob_start();
    ?>
    <div class="fleet-mission__result-card">
        <h3 class="fleet-mission__result-title">Résultat de la simulation</h3>
        <ul class="metric-list fleet-mission__metrics">
            <li><span>Distance</span><strong><?= htmlspecialchars(format_number($distance), ENT_QUOTES) ?> u</strong></li>
            <li><span>Vitesse effective</span><strong><?= htmlspecialchars(format_number($speed), ENT_QUOTES) ?> u/h</strong></li>
            <li><span>Durée</span><strong><?= htmlspecialchars(format_duration($travelTime), ENT_QUOTES) ?></strong></li>
            <?php if ($arrivalFormatted !== null): ?>
                <li><span>Arrivée estimée</span><strong><?= htmlspecialchars($arrivalFormatted, ENT_QUOTES) ?></strong></li>
            <?php endif; ?>
            <li><span>Hydrogène requis</span><strong><?= htmlspecialchars(format_number($fuel), ENT_QUOTES) ?></strong></li>
        </ul>
        <div class="fleet-mission__cargo">
            <h4>Gestion du cargo</h4>
            <ul class="metric-list fleet-mission__cargo-list">
                <li><span>Capacité totale</span><strong><?= htmlspecialchars(format_number($cargoCapacity), ENT_QUOTES) ?></strong></li>
                <li><span>Utilisation actuelle</span><strong><?= htmlspecialchars(format_number($cargoUsed), ENT_QUOTES) ?></strong></li>
                <li><span>Soute restante</span><strong><?= htmlspecialchars(format_number($remainingCargo), ENT_QUOTES) ?></strong></li>
            </ul>
        </div>
    </div>
    <?php

    return (string)ob_get_clean();
};

$fleetActionUrl = $selectedPlanetId !== null
    ? $basePath . '/fleet?planet=' . (int)$selectedPlanetId
    : $basePath . '/fleet';
$backToListUrl = $selectedPlanetId !== null
    ? $basePath . '/fleet?planet=' . (int)$selectedPlanetId
    : $basePath . '/fleet';

$availableTargets = array_values(array_filter(
    $idleFleets,
    static function (array $fleet) use ($selectedFleetId, $garrisonFleetId): bool {
        if ($selectedFleetId !== null && $fleet['id'] === $selectedFleetId) {
            return false;
        }

        return $garrisonFleetId === null || $fleet['id'] !== $garrisonFleetId;
    }
));

ob_start();
?>
<header class="ui-page-header">
    <div class="ui-page-header__titles">
        <h1 class="ui-page-header__title">Commandement de la flotte</h1>
        <?php if ($selectedPlanetId !== null): ?>
            <p class="ui-page-header__subtitle">Organisez vos flottes en orbite autour de la planète sélectionnée et préparez leurs prochaines opérations.</p>
        <?php else: ?>
            <p class="ui-page-header__subtitle">Sélectionnez une planète depuis l’en-tête pour afficher et gérer ses flottes.</p>
        <?php endif; ?>
    </div>
    <div class="ui-page-header__actions">
        <?php if ($selectedFleet !== null): ?>
            <a class="ui-button ui-button--ghost ui-button--sm" href="<?= htmlspecialchars($backToListUrl, ENT_QUOTES) ?>">
                <span class="ui-button__label">Retour aux flottes</span>
            </a>
        <?php endif; ?>
    </div>
</header>

<?php if ($selectedPlanetId === null): ?>
    <?= $card([
        'baseClass' => 'ui-card',
        'title' => 'Aucune planète active',
        'body' => static function (): void {
            echo '<p>Choisissez une planète à gérer pour accéder au détail des flottes stationnées en orbite.</p>';
        },
    ]) ?>
<?php else: ?>
    <?= $card([
        'baseClass' => 'ui-card',
        'title' => 'Créer une nouvelle flotte',
        'subtitle' => 'Assemblez un nouveau groupe de combat en orbite.',
        'bodyClass' => 'ui-card__body fleet-create__body',
        'body' => static function () use ($fleetActionUrl, $csrf_create, $selectedPlanetId): void {
            echo '<form class="d-flex flex-column gap-4" method="post" action="' . htmlspecialchars($fleetActionUrl, ENT_QUOTES) . '">';
            if ($csrf_create !== null) {
                echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)$csrf_create, ENT_QUOTES) . '">';
            }
            echo '<input type="hidden" name="action" value="create_fleet">';
            echo '<div class="ui-field">';
            echo '<label class="ui-field__label" for="fleet-name">Nom de la flotte</label>';
            echo '<input class="ui-input" id="fleet-name" type="text" name="fleet_name" placeholder="Flotte d’intervention" maxlength="50" required autocomplete="off">';
            echo '</div>';
            echo '<div class="d-flex justify-content-end">';
            echo '<button class="ui-button ui-button--primary ui-button--sm" type="submit">';
            echo '<span class="ui-button__label">Créer la flotte</span>';
            echo '</button>';
            echo '</div>';
            echo '</form>';
        },
    ]) ?>

    <?= $card([
        'baseClass' => 'ui-card',
        'title' => 'Flottes en orbite',
        'subtitle' => 'Vue d’ensemble des groupes disponibles autour de la planète.',
        'body' => static function () use ($idleFleets, $selectedPlanetId, $basePath): void {
            if (empty($idleFleets)) {
                echo '<p class="empty-state">Aucune flotte n’est actuellement stationnée en orbite. Créez une nouvelle flotte pour commencer.</p>';

                return;
            }

            echo '<div class="table-wrapper">';
            echo '<table class="data-table fleet-table">';
            echo '<thead><tr>';
            echo '<th scope="col">Nom de la flotte</th>';
            echo '<th scope="col">Effectif</th>';
            echo '<th scope="col">Statut</th>';
            echo '<th scope="col" class="fleet-table__actions">Actions</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($idleFleets as $fleet) {
                $fleetId = (int)($fleet['id'] ?? 0);
                if ($fleetId <= 0) {
                    continue;
                }

                $label = (string)($fleet['label'] ?? ('Flotte #' . $fleetId));
                $isGarrison = (bool)($fleet['is_garrison'] ?? false);
                $statusLabel = $isGarrison ? 'Platforme orbitale' : 'Flotte opérationnelle';
                $statusCode = 'idle';
                $totalShips = (int)($fleet['total'] ?? 0);
                $manageQuery = ['fleet' => $fleetId];
                if ($selectedPlanetId !== null) {
                    $manageQuery['planet'] = (int)$selectedPlanetId;
                }
                $manageUrl = $basePath . '/fleet?' . http_build_query($manageQuery);

                echo '<tr data-fleet-row data-fleet-id="' . $fleetId . '" data-fleet-state="' . htmlspecialchars($statusCode, ENT_QUOTES) . '"';
                if ($isGarrison) {
                    echo ' data-fleet-garrison="true"';
                }
                echo '>';
                echo '<td>';
                echo '<strong>' . htmlspecialchars($label) . '</strong>';
                echo '</td>';
                echo '<td class="fleet-table__metric">' . format_number($totalShips) . '</td>';
                echo '<td class="fleet-table__status" data-fleet-status="' . htmlspecialchars($statusCode, ENT_QUOTES) . '" data-default-label="' . htmlspecialchars($statusLabel, ENT_QUOTES) . '">';
                echo htmlspecialchars($statusLabel, ENT_QUOTES);
                echo '</td>';
                echo '<td class="fleet-table__actions">';
                echo '<a class="ui-button ui-button--neutral ui-button--sm" data-fleet-action="manage" href="' . htmlspecialchars($manageUrl, ENT_QUOTES) . '">';
                echo '<span class="ui-button__label">Gérer</span>';
                echo '</a>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        },
    ]) ?>

    <?php if ($selectedFleet !== null): ?>
        <?= $card([
            'baseClass' => 'ui-card',
            'title' => 'Gestion de « ' . htmlspecialchars($selectedFleet['label'], ENT_QUOTES) . ' »',
            'subtitle' => 'Ajustez sa composition et préparez ses prochaines actions.',
            'bodyClass' => 'ui-card__body fleet-manage',
            'body' => static function () use (
                $selectedFleet,
                $fleetActionUrl,
                $csrf_transfer,
                $csrf_rename,
                $csrf_delete,
                $availableTargets,
                $garrisonFleetId,
                $selectedFleetId,
                $availableShips,
                $submittedDestination,
                $submittedResources,
                $submittedMission,
                $submittedSpeedFactor,
                $planResult,
                $planErrors,
                $planEndpoint,
                $launchEndpoint,
                $csrf_plan,
                $csrf_launch,
                $renderPlanSummary,
                $renderPlanErrors,
                $selectedPlanetId
            ): void {
                $hasTransferOptions = !empty($availableTargets) || ($garrisonFleetId !== null && $garrisonFleetId !== $selectedFleetId);

                echo '<div class="fleet-manage">';

                echo '<section class="fleet-manage__section fleet-manage__section--mission">';
                echo '<h3>Envoyer en mission</h3>';
                echo '<p class="fleet-manage__hint">Planifiez un transport interplanétaire, estimez la consommation et contrôlez la capacité de soute avant le décollage.</p>';

                $missions = [
                    'transport' => [
                        'label' => 'Transport',
                        'description' => 'Acheminer rapidement des ressources entre deux mondes alliés ou personnels.',
                        'available' => true,
                        'icon' => 'cargo',
                        'symbol' => '🚚',
                    ],
                    'stationnement' => [
                        'label' => 'Stationnement',
                        'description' => 'Déployer temporairement la flotte sur une planète alliée (bientôt disponible).',
                        'available' => false,
                        'icon' => 'garrison',
                        'symbol' => '🛰️',
                        'badge' => 'Bientôt',
                    ],
                    'attaque' => [
                        'label' => 'Attaque',
                        'description' => 'Planifier un assaut coordonné contre une cible ennemie.',
                        'available' => false,
                        'icon' => 'attack',
                        'symbol' => '⚔️',
                        'badge' => 'À venir',
                    ],
                    'defense' => [
                        'label' => 'Défense',
                        'description' => 'Envoyer des renforts défensifs pour soutenir une planète alliée.',
                        'available' => false,
                        'icon' => 'defense',
                        'symbol' => '🛡️',
                        'badge' => 'À venir',
                    ],
                    'recyclage' => [
                        'label' => 'Recyclage',
                        'description' => 'Collecter et retraiter les débris orbitaux laissés après une bataille.',
                        'available' => false,
                        'icon' => 'recycle',
                        'symbol' => '♻️',
                        'badge' => 'À venir',
                    ],
                    'espionnage' => [
                        'label' => 'Espionnage',
                        'description' => 'Infiltrer une cible pour obtenir des renseignements stratégiques.',
                        'available' => false,
                        'icon' => 'intel',
                        'symbol' => '🛰',
                        'badge' => 'À venir',
                    ],
                    'exploration' => [
                        'label' => 'Exploration',
                        'description' => 'Tracer de nouvelles routes commerciales au-delà des frontières connues.',
                        'available' => false,
                        'icon' => 'explore',
                        'symbol' => '🧭',
                        'badge' => 'À venir',
                    ],
                    'mission_pve' => [
                        'label' => 'Mission PvE',
                        'description' => 'Affronter des menaces IA pour sécuriser la zone et obtenir des récompenses.',
                        'available' => false,
                        'icon' => 'pve',
                        'symbol' => '🎯',
                        'badge' => 'À venir',
                    ],
                ];

                ob_start();
                ?>
                <div class="fleet-mission__layout">
                    <form
                        class="mission-form fleet-mission__form"
                        method="post"
                        action="<?= htmlspecialchars($fleetActionUrl, ENT_QUOTES) ?>"
                        data-fleet-planner
                        data-plan-endpoint="<?= htmlspecialchars($planEndpoint, ENT_QUOTES) ?>"
                        data-launch-endpoint="<?= htmlspecialchars($launchEndpoint, ENT_QUOTES) ?>"
                        data-origin-planet="<?= $selectedPlanetId !== null ? (int)$selectedPlanetId : 0 ?>"
                        data-fleet-id="<?= (int)$selectedFleet['id'] ?>"
                        data-csrf-plan="<?= htmlspecialchars((string)($csrf_plan ?? ''), ENT_QUOTES) ?>"
                        data-csrf-launch="<?= htmlspecialchars((string)($csrf_launch ?? ''), ENT_QUOTES) ?>"
                        data-plan-ready="<?= $planResult === null ? 'false' : 'true' ?>"
                    >
                        <input type="hidden" name="action" value="plan_fleet_mission">
                        <input type="hidden" name="origin_planet_id" value="<?= $selectedPlanetId !== null ? (int)$selectedPlanetId : 0 ?>">
                        <input type="hidden" name="fleet_id" value="<?= (int)$selectedFleet['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($csrf_plan ?? ''), ENT_QUOTES) ?>">

                        <fieldset class="mission-form__fieldset">
                            <legend class="mission-form__legend">Type de mission</legend>
                            <p class="mission-form__description">Choisissez la manœuvre souhaitée. Les profils non disponibles apparaissent comme en cours de développement.</p>
                            <div class="mission-form__grid">
                                <?php
                                $index = 0;
                                $hasCheckedMission = false;
                                foreach ($missions as $value => $config):
                                    $index++;
                                    $inputId = 'mission-' . $index;
                                    $isAvailable = $config['available'];
                                    $optionClasses = 'mission-form__option';
                                    if ($isAvailable) {
                                        $optionClasses .= ' mission-form__option--available';
                                    }
                                    $shouldCheck = false;
                                    if ($isAvailable && $value === $submittedMission) {
                                        $shouldCheck = true;
                                    } elseif ($isAvailable && !$hasCheckedMission && $value === 'transport') {
                                        $shouldCheck = true;
                                    }
                                    if ($shouldCheck) {
                                        $hasCheckedMission = true;
                                    }
                                    ?>
                                    <label class="<?= $optionClasses ?>" for="<?= htmlspecialchars($inputId, ENT_QUOTES) ?>">
                                        <input
                                            class="mission-form__radio"
                                            type="radio"
                                            id="<?= htmlspecialchars($inputId, ENT_QUOTES) ?>"
                                            name="mission"
                                            value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"
                                            <?= $isAvailable ? '' : 'disabled' ?>
                                            <?= $shouldCheck ? 'checked' : '' ?>
                                        >
                                        <div class="mission-form__option-body">
                                            <div class="mission-form__option-header">
                                                <span
                                                    class="mission-form__option-icon mission-form__option-icon--<?= htmlspecialchars($config['icon'] ?? 'default', ENT_QUOTES) ?>"
                                                    aria-hidden="true"
                                                ><?= htmlspecialchars($config['symbol'] ?? '✦', ENT_QUOTES) ?></span>
                                                <?php if (!$isAvailable): ?>
                                                    <span class="mission-form__tag"><?= htmlspecialchars($config['badge'] ?? 'WIP', ENT_QUOTES) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mission-form__option-content">
                                                <span class="mission-form__option-title"><?= htmlspecialchars($config['label'], ENT_QUOTES) ?></span>
                                                <?php if (!empty($config['description'])): ?>
                                                    <p class="mission-form__option-hint"><?= htmlspecialchars($config['description'], ENT_QUOTES) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <div class="fleet-mission__section">
                            <h4>Destination</h4>
                            <div class="fleet-mission__coordinates">
                                <div class="ui-field">
                                    <label class="ui-field__label" for="mission-destination-galaxy">Galaxie</label>
                                    <input
                                        class="ui-input"
                                        type="number"
                                        id="mission-destination-galaxy"
                                        name="destination_galaxy"
                                        min="1"
                                        max="9"
                                        value="<?= (int)$submittedDestination['galaxy'] ?>"
                                        required
                                    >
                                </div>
                                <div class="ui-field">
                                    <label class="ui-field__label" for="mission-destination-system">Système</label>
                                    <input
                                        class="ui-input"
                                        type="number"
                                        id="mission-destination-system"
                                        name="destination_system"
                                        min="1"
                                        max="499"
                                        value="<?= (int)$submittedDestination['system'] ?>"
                                        required
                                    >
                                </div>
                                <div class="ui-field">
                                    <label class="ui-field__label" for="mission-destination-position">Position</label>
                                    <input
                                        class="ui-input"
                                        type="number"
                                        id="mission-destination-position"
                                        name="destination_position"
                                        min="1"
                                        max="15"
                                        value="<?= (int)$submittedDestination['position'] ?>"
                                        required
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="fleet-mission__section">
                            <h4>Paramètres de vol</h4>
                            <div class="fleet-mission__speed">
                                <label class="ui-field__label" for="mission-speed">Vitesse de croisière</label>
                                <div class="fleet-mission__speed-control">
                                    <?php $speedPercent = (int)round($submittedSpeedFactor * 100); ?>
                                    <input
                                        class="fleet-mission__speed-range"
                                        type="range"
                                        id="mission-speed"
                                        name="speed_factor"
                                        min="10"
                                        max="100"
                                        step="10"
                                        value="<?= $speedPercent ?>"
                                    >
                                    <span class="fleet-mission__speed-value" data-speed-display><?= $speedPercent ?>%</span>
                                </div>
                                <p class="form__hint">Réduire la vitesse diminue la consommation d’hydrogène mais allonge le trajet.</p>
                            </div>

                            <div class="fleet-mission__resources">
                                <h5>Ressources à embarquer</h5>
                                <div class="fleet-mission__resources-grid">
                                    <?php foreach ($submittedResources as $resourceKey => $amount):
                                        $inputId = 'mission-resource-' . preg_replace('/[^a-z0-9_-]+/i', '-', $resourceKey);
                                        $label = match ($resourceKey) {
                                            'metal' => 'Métal',
                                            'crystal' => 'Cristal',
                                            'hydrogen' => 'Hydrogène',
                                            default => ucfirst((string)$resourceKey),
                                        };
                                        ?>
                                        <div class="ui-field">
                                            <label class="ui-field__label" for="<?= htmlspecialchars($inputId, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></label>
                                            <input
                                                class="ui-input"
                                                type="number"
                                                id="<?= htmlspecialchars($inputId, ENT_QUOTES) ?>"
                                                name="resources[<?= htmlspecialchars((string)$resourceKey, ENT_QUOTES) ?>]"
                                                min="0"
                                                step="1"
                                                value="<?= htmlspecialchars((string)$amount, ENT_QUOTES) ?>"
                                            >
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="fleet-mission__section">
                            <h4>Composition de la flotte</h4>
                            <?php if (empty($availableShips)): ?>
                                <p class="empty-state">Aucun vaisseau n’est disponible pour cette planète.</p>
                            <?php else: ?>
                                <p class="form__hint">Toute la flotte disponible embarque automatiquement pour la mission.</p>
                                <ul class="mission-composition__readonly">
                                    <?php
                                    $totalAvailable = 0;
                                    foreach ($availableShips as $ship):
                                        $quantity = (int)$ship['quantity'];
                                        if ($quantity <= 0) {
                                            continue;
                                        }
                                        $totalAvailable += $quantity;
                                        ?>
                                        <li class="mission-composition__readonly-item">
                                            <div class="mission-composition__readonly-info">
                                                <span class="mission-composition__ship-name"><?= htmlspecialchars((string)$ship['label'], ENT_QUOTES) ?></span>
                                                <?php if (!empty($ship['role'])): ?>
                                                    <span class="mission-composition__ship-role"><?= htmlspecialchars((string)$ship['role'], ENT_QUOTES) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="mission-composition__ship-quantity"><?= htmlspecialchars(format_number($quantity), ENT_QUOTES) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="mission-composition__summary">
                                    <?= htmlspecialchars(format_number($totalAvailable), ENT_QUOTES) ?> vaisseaux engagés dans l’expédition
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="fleet-mission__footer">
                            <div class="fleet-mission__errors" data-fleet-plan-errors>
                                <?= $renderPlanErrors($planErrors) ?>
                            </div>
                            <div class="mission-form__actions">
                                <button class="ui-button ui-button--neutral ui-button--sm" type="submit">
                                    <span class="ui-button__label">Simuler la mission</span>
                                </button>
                                <button
                                    class="ui-button ui-button--primary ui-button--sm"
                                    type="button"
                                    data-action="launch"
                                    <?= $planResult === null ? 'disabled' : '' ?>
                                >
                                    <span class="ui-button__label">Lancer la mission</span>
                                </button>
                            </div>
                        </div>
                    </form>
                    <aside class="fleet-mission__summary">
                        <div class="fleet-mission__result" data-fleet-plan-result>
                            <?= $renderPlanSummary($planResult) ?>
                        </div>
                    </aside>
                </div>
                <?php
                echo ob_get_clean();
                echo '</section>';

                echo '<section class="fleet-manage__section">';
                echo '<h3>Composition & transferts</h3>';
                if (empty($selectedFleet['ships'])) {
                    echo '<p class="empty-state">Cette flotte ne contient actuellement aucun vaisseau.</p>';
                } else {
                    $formOpened = false;
                    if ($hasTransferOptions) {
                        echo '<form class="fleet-transfer d-flex flex-column gap-4" method="post" action="' . htmlspecialchars($fleetActionUrl, ENT_QUOTES) . '">';
                        if ($csrf_transfer !== null) {
                            echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)$csrf_transfer, ENT_QUOTES) . '">';
                        }
                        echo '<input type="hidden" name="action" value="transfer_from_fleet">';
                        echo '<input type="hidden" name="source_fleet_id" value="' . (int)$selectedFleet['id'] . '">';
                        $formOpened = true;
                    }

                    echo '<div class="table-wrapper">';
                    echo '<table class="data-table fleet-transfer__table">';
                    echo '<thead><tr>';
                    echo '<th scope="col">Vaisseau</th>';
                    echo '<th scope="col">Rôle</th>';
                    echo '<th scope="col">Disponible</th>';
                    echo '<th scope="col">À transférer</th>';
                    echo '</tr></thead>';
                    echo '<tbody>';
                    foreach ($selectedFleet['ships'] as $ship) {
                        $shipKey = (string)($ship['key'] ?? '');
                        $shipLabel = (string)($ship['label'] ?? $shipKey);
                        $shipRole = (string)($ship['role'] ?? '');
                        $shipQuantity = (int)($ship['quantity'] ?? 0);
                        $inputId = 'transfer-' . preg_replace('/[^a-z0-9_-]+/i', '-', $shipKey);

                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($shipLabel) . '</td>';
                        echo '<td>' . ($shipRole !== '' ? htmlspecialchars($shipRole) : '—') . '</td>';
                        echo '<td class="fleet-transfer__available">' . format_number($shipQuantity) . '</td>';
                        echo '<td class="fleet-transfer__input-cell">';
                        if ($formOpened && $shipQuantity > 0) {
                            echo '<label class="visually-hidden" for="' . htmlspecialchars($inputId, ENT_QUOTES) . '">Quantité à transférer</label>';
                            echo '<input class="ui-input fleet-transfer__input" type="number" id="' . htmlspecialchars($inputId, ENT_QUOTES) . '" name="ships[' . htmlspecialchars($shipKey, ENT_QUOTES) . ']" min="0" max="' . $shipQuantity . '" step="1" placeholder="0">';
                        } elseif ($formOpened) {
                            echo '<input class="ui-input fleet-transfer__input" type="number" id="' . htmlspecialchars($inputId, ENT_QUOTES) . '" name="ships[' . htmlspecialchars($shipKey, ENT_QUOTES) . ']" min="0" step="1" value="0" disabled>';
                        } else {
                            echo '—';
                        }
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody>';
                    echo '</table>';
                    echo '</div>';

                    if ($formOpened) {
                        echo '<div class="fleet-transfer__destination ui-field">';
                        echo '<label class="ui-field__label" for="transfer-target">Destination</label>';
                        echo '<select class="ui-select" id="transfer-target" name="target_fleet_id" required>';
                        echo '<option value="">Choisissez une destination</option>';
                        if ($garrisonFleetId !== null && $garrisonFleetId !== $selectedFleetId) {
                            echo '<option value="hangar">Hangar planétaire</option>';
                        }
                        foreach ($availableTargets as $targetFleet) {
                            $targetId = (int)($targetFleet['id'] ?? 0);
                            if ($targetId <= 0) {
                                continue;
                            }
                            echo '<option value="' . $targetId . '">' . htmlspecialchars($targetFleet['label'], ENT_QUOTES) . '</option>';
                        }
                        echo '</select>';
                        echo '</div>';

                        echo '<div class="fleet-transfer__actions d-flex justify-content-end">';
                        echo '<button class="ui-button ui-button--primary ui-button--sm" type="submit">';
                        echo '<span class="ui-button__label">Transférer les vaisseaux sélectionnés</span>';
                        echo '</button>';
                        echo '</div>';
                        echo '</form>';
                    } else {
                        echo '<p class="empty-state">Aucune autre flotte ou hangar disponible pour recevoir des renforts.</p>';
                    }
                }
                echo '</section>';

                echo '<section class="fleet-manage__section">';
                echo '<h3>Renommer la flotte</h3>';
                echo '<form class="d-flex flex-column gap-4" method="post" action="' . htmlspecialchars($fleetActionUrl, ENT_QUOTES) . '">';
                if ($csrf_rename !== null) {
                    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)$csrf_rename, ENT_QUOTES) . '">';
                }
                echo '<input type="hidden" name="action" value="rename_fleet">';
                echo '<input type="hidden" name="fleet_id" value="' . (int)$selectedFleet['id'] . '">';
                $currentName = $selectedFleet['name'] ?? '';
                echo '<div class="ui-field">';
                echo '<label class="ui-field__label" for="rename-fleet">Nouveau nom</label>';
                echo '<input class="ui-input" id="rename-fleet" type="text" name="new_name" value="' . htmlspecialchars((string)$currentName, ENT_QUOTES) . '" placeholder="Flotte d’élite" maxlength="50" required autocomplete="off">';
                echo '</div>';
                echo '<div class="d-flex justify-content-end">';
                echo '<button class="ui-button ui-button--primary ui-button--sm" type="submit">';
                echo '<span class="ui-button__label">Renommer</span>';
                echo '</button>';
                echo '</div>';
                echo '</form>';
                echo '</section>';

                echo '<section class="fleet-manage__section">';
                echo '<h3>Supprimer la flotte</h3>';
                echo '<p class="fleet-manage__hint">Les vaisseaux restants seront automatiquement renvoyés au hangar planétaire.</p>';
                echo '<form class="d-flex flex-column gap-4" method="post" action="' . htmlspecialchars($fleetActionUrl, ENT_QUOTES) . '">';
                if ($csrf_delete !== null) {
                    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)$csrf_delete, ENT_QUOTES) . '">';
                }
                echo '<input type="hidden" name="action" value="delete_fleet">';
                echo '<input type="hidden" name="fleet_id" value="' . (int)$selectedFleet['id'] . '">';
                echo '<div class="d-flex justify-content-end">';
                $confirmMessage = htmlspecialchars(sprintf('Êtes-vous certain de vouloir dissoudre définitivement la flotte "%s" ?', (string)($selectedFleet['label'] ?? 'Flotte')), ENT_QUOTES);
                echo '<button class="ui-button ui-button--danger ui-button--sm" type="submit" onclick="return confirm(\'' . $confirmMessage . '\');">';
                echo '<span class="ui-button__label">Supprimer cette flotte</span>';
                echo '</button>';
                echo '</div>';
                echo '</form>';
                echo '</section>';

                echo '</div>';
            },
        ]) ?>
    <?php endif; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/base.php';
