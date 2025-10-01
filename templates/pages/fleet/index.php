<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets Liste des planètes disponibles. */
/** @var array $fleetOverview Données agrégées de la flotte. */
/** @var array<int, array{key: string, label: string, quantity: int, attack: int, defense: int, speed: int, category: string, role: string, image: ?string, fuelRate: int}> $availableShips Inventaire des vaisseaux prêts. */
/** @var array $catalogCategories Catégories du catalogue de vaisseaux. */
/** @var array<string, int> $submittedComposition Composition saisie dans le formulaire. */
/** @var array{galaxy: int, system: int, position: int} $submittedDestination Cible choisie par le joueur. */
/** @var array|null $planResult Résultat éventuel du calcul de trajet. */
/** @var array<int, string> $planErrors Liste des erreurs rencontrées. */
/** @var string $baseUrl URL de base pour les liens. */
/** @var string|null $csrf_plan Jeton CSRF du planificateur. */
/** @var string|null $csrf_launch Jeton CSRF de lancement. */
/** @var int|null $selectedPlanetId Identifiant de la planète active. */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary Résumé pour l’entête de page. */
/** @var array<int, array{id: int, mission: string, status: string, destination: array{galaxy: int, system: int, position: int}, arrivalAt: ?string}> $activeMissions Missions en cours. */

$title = $title ?? 'Flotte';
$card = require __DIR__ . '/../../components/_card.php';
require_once __DIR__ . '/../../components/helpers.php';

$fleetShips = $fleetOverview['ships'] ?? [];
$totalShips = $fleetOverview['totalShips'] ?? 0;
$power = $fleetOverview['power'] ?? 0;
$origin = $fleetOverview['origin'] ?? ['galaxy' => 1, 'system' => 1, 'position' => 1];

ob_start();
?>
    <section class="page-header">
        <div>
            <h1>Commandement de flotte</h1>
            <p class="page-header__subtitle">Planifiez vos trajets et visualisez la puissance militaire stationnée.</p>
        </div>
        <div class="page-header__actions">
        </div>
    </section>

<?= $card([
        'title' => 'Flotte stationnée',
        'subtitle' => 'Origine ' . $origin['galaxy'] . ':' . $origin['system'] . ':' . $origin['position'],
        'body' => static function () use ($fleetShips, $totalShips, $power): void {
            echo '<div class="metrics metrics--compact">';
            echo '<div class="metric"><span class="metric__label">Unités</span><strong class="metric__value">' . format_number((int)$totalShips) . '</strong></div>';
            echo '<div class="metric"><span class="metric__label">Puissance estimée</span><strong class="metric__value">' . format_number((int)$power) . '</strong></div>';
            echo '</div>';
            if ($fleetShips === []) {
                echo '<p class="empty-state">Aucun vaisseau n’est actuellement disponible sur cette orbite.</p>';

                return;
            }

            echo '<table class="data-table">';
            echo '<thead><tr><th>Classe</th><th>Quantité</th><th>Attaque</th><th>Défense</th><th>Vitesse</th></tr></thead>';
            echo '<tbody>';
            foreach ($fleetShips as $ship) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($ship['label']) . '</td>';
                echo '<td>' . format_number((int)$ship['quantity']) . '</td>';
                echo '<td>' . format_number((int)$ship['attack']) . '</td>';
                echo '<td>' . format_number((int)$ship['defense']) . '</td>';
                echo '<td>' . format_number((int)$ship['speed']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        },
]) ?>

<?= $card([
        'title' => 'Missions actives',
        'subtitle' => 'Trajets en cours pour cette planète',
        'body' => static function () use ($activeMissions): void {
            if ($activeMissions === []) {
                echo '<p class="empty-state">Aucune mission en cours. Planifiez un trajet pour lancer votre flotte.</p>';

                return;
            }

            echo '<table class="data-table">';
            echo '<thead><tr><th>Mission</th><th>Destination</th><th>Statut</th><th>Arrivée</th></tr></thead>';
            echo '<tbody>';
            foreach ($activeMissions as $mission) {
                $destination = $mission['destination'];
                $arrival = $mission['arrivalAt'] ?? null;
                echo '<tr>';
                echo '<td>' . htmlspecialchars($mission['mission']) . '</td>';
                echo '<td>' . htmlspecialchars($destination['galaxy'] . ':' . $destination['system'] . ':' . $destination['position']) . '</td>';
                echo '<td>' . htmlspecialchars($mission['status']) . '</td>';
                echo '<td>' . ($arrival ? htmlspecialchars($arrival) : '-') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        },
]) ?>

<?= $card([
        'title' => 'Planification de mission',
        'subtitle' => 'Calculez l’ETA et la consommation d’hydrogène',
        'body' => static function () use ($planErrors, $availableShips, $submittedComposition, $submittedDestination, $planResult, $baseUrl, $csrf_plan, $csrf_launch, $selectedPlanetId): void {
            echo '<form'
                . ' class="fleet-planner"'
                . ' method="post"'
                . ' action="' . htmlspecialchars($baseUrl) . '/fleet?planet=' . (int)$selectedPlanetId . '"'
                . ' data-fleet-planner'
                . ' data-plan-endpoint="' . htmlspecialchars($baseUrl) . '/fleet/plan"'
                . ' data-launch-endpoint="' . htmlspecialchars($baseUrl) . '/fleet/launch"'
                . ' data-csrf-plan="' . htmlspecialchars((string)$csrf_plan) . '"'
                . ' data-csrf-launch="' . htmlspecialchars((string)$csrf_launch) . '"'
                . ' data-origin-planet="' . (int)$selectedPlanetId . '">';
            echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)$csrf_plan) . '">';
            echo '<input type="hidden" name="csrf_launch" value="' . htmlspecialchars((string)$csrf_launch) . '">';
            echo '<input type="hidden" name="origin_planet_id" value="' . (int)$selectedPlanetId . '">';

            echo '<div class="planner-feedback" data-fleet-plan-errors>';
            if (!empty($planErrors)) {
                echo '<ul class="form-errors">';
                foreach ($planErrors as $error) {
                    echo '<li>' . htmlspecialchars($error) . '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';

            echo '<fieldset class="fleet-planner__section">';
            echo '<legend>Destination</legend>';
            echo '<div class="form-grid">';
            echo '<label>Galaxie<input type="number" name="destination_galaxy" min="1" value="' . (int)$submittedDestination['galaxy'] . '"></label>';
            echo '<label>Système<input type="number" name="destination_system" min="1" value="' . (int)$submittedDestination['system'] . '"></label>';
            echo '<label>Position<input type="number" name="destination_position" min="1" value="' . (int)$submittedDestination['position'] . '"></label>';
            echo '</div>';
            echo '</fieldset>';

            echo '<fieldset class="fleet-planner__section">';
            echo '<legend>Composition</legend>';
            if ($availableShips === []) {
                echo '<p class="empty-state">Aucun vaisseau disponible pour lancer une mission.</p>';
            } else {
                echo '<div class="planner-composition">';
                foreach ($availableShips as $ship) {
                    $value = $submittedComposition[$ship['key']] ?? 0;
                    echo '<label class="planner-composition__row">';
                    echo '<span class="planner-composition__label">' . htmlspecialchars($ship['label']) . ' <small>(' . format_number((int)$ship['quantity']) . ')</small></span>';
                    echo '<input type="number" min="0" max="' . (int)$ship['quantity'] . '" name="composition[' . htmlspecialchars($ship['key'], ENT_QUOTES) . ']" value="' . (int)$value . '">';
                    echo '</label>';
                }
                echo '</div>';
            }
            echo '</fieldset>';

            echo '<fieldset class="fleet-planner__section">';
            echo '<legend>Paramètres</legend>';
            echo '<label>Vitesse (% maximum)<input type="number" name="speed_factor" min="10" max="100" step="5" value="100"></label>';
            echo '</fieldset>';

            echo '<div class="planner-actions">';
            echo '<button class="button button--primary" type="submit" data-action="plan">Calculer le trajet</button>';
            echo '<button class="button" type="button" data-action="launch">Lancer la mission</button>';
            echo '</div>';

            $plan = $planResult;
            if ($plan !== null && isset($plan['arrival_time']) && $plan['arrival_time'] instanceof \DateTimeImmutable) {
                $plan['arrival_time'] = $plan['arrival_time']->format('d/m/Y H:i');
            }

            echo '<div class="planner-result" data-fleet-plan-result>';
            if ($plan !== null) {
                echo '<h3>Résultat de la simulation</h3>';
                echo '<ul class="metric-list">';
                echo '<li><span>Distance</span><strong>' . format_number((int)$plan['distance']) . ' u</strong></li>';
                echo '<li><span>Vitesse effective</span><strong>' . format_number((int)$plan['speed']) . ' u/h</strong></li>';
                echo '<li><span>Durée</span><strong>' . htmlspecialchars(format_duration((int)$plan['travel_time'])) . '</strong></li>';
                if (!empty($plan['arrival_time'])) {
                    echo '<li><span>Arrivée estimée</span><strong>' . htmlspecialchars((string)$plan['arrival_time']) . '</strong></li>';
                }
                echo '<li><span>Consommation d’hydrogène</span><strong>' . format_number((int)$plan['fuel']) . '</strong></li>';
                echo '</ul>';
            }
            echo '</div>';

            echo '</form>';
        },
]) ?>

    <div class="grid grid--stacked">
        <?php foreach ($catalogCategories as $category => $data): ?>
            <?= $card([
                    'title' => $category,
                    'subtitle' => 'Référence des classes disponibles',
                    'illustration' => !empty($data['image']) ? htmlspecialchars($baseUrl . '/' . $data['image'], ENT_QUOTES) : null,
                    'body' => static function () use ($data): void {
                        echo '<ul class="catalog-list">';
                        foreach ($data['items'] as $definition) {
                            echo '<li><strong>' . htmlspecialchars($definition->getLabel()) . '</strong><span>' . htmlspecialchars($definition->getRole()) . '</span></li>';
                        }
                        echo '</ul>';
                    },
            ]) ?>
        <?php endforeach; ?>
    </div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/base.php';
