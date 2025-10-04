<?php
/**
 * @var array<int, \App\Domain\Entity\Planet> $planets
 * @var int|null $selectedPlanetId
 * @var array{ships: array<int, array{key: string, label: string, quantity: int, attack: int, defense: int, speed: int, category: string, role: string, image: string|null, fuelRate: int}>, totalShips: int, power: int, origin?: array{galaxy: int, system: int, position: int}} $fleetOverview
 * @var array<int, array{key: string, label: string, quantity: int, attack: int, defense: int, speed: int, category: string, role: string, image: string|null, fuelRate: int}> $availableShips
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

$fleetActionUrl = $selectedPlanetId !== null
    ? $basePath . '/fleet?planet=' . (int)$selectedPlanetId
    : $basePath . '/fleet';
$backToListUrl = $selectedPlanetId !== null
    ? $basePath . '/fleet?planet=' . (int)$selectedPlanetId
    : $basePath . '/fleet';

$availableTargets = array_values(array_filter(
    $idleFleets,
    static fn (array $fleet): bool => $selectedFleetId === null || $fleet['id'] !== $selectedFleetId
));

ob_start();
?>
<section class="page-header">
    <div>
        <h1>Commandement de la flotte</h1>
        <?php if ($selectedPlanetId !== null): ?>
            <p class="page-header__subtitle">Organisez vos flottes en orbite autour de la planète sélectionnée et préparez leurs prochaines opérations.</p>
        <?php else: ?>
            <p class="page-header__subtitle">Sélectionnez une planète depuis l’en-tête pour afficher et gérer ses flottes.</p>
        <?php endif; ?>
    </div>
    <div class="page-header__actions">
        <?php if ($selectedFleet !== null): ?>
            <a class="button button--ghost" href="<?= htmlspecialchars($backToListUrl, ENT_QUOTES) ?>">Retour aux flottes</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($selectedPlanetId === null): ?>
    <?= $card([
        'title' => 'Aucune planète active',
        'body' => static function (): void {
            echo '<p>Choisissez une planète à gérer pour accéder au détail des flottes stationnées en orbite.</p>';
        },
    ]) ?>
<?php else: ?>
    <?= $card([
        'title' => 'Créer une nouvelle flotte',
        'subtitle' => 'Assemblez un nouveau groupe de combat en orbite.',
        'body' => static function () use ($fleetActionUrl, $csrf_create, $selectedPlanetId): void {
            echo '<form class="form form--stack" method="post" action="' . htmlspecialchars($fleetActionUrl, ENT_QUOTES) . '">';
            if ($csrf_create !== null) {
                echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)$csrf_create, ENT_QUOTES) . '">';
            }
            echo '<input type="hidden" name="action" value="create_fleet">';
            echo '<div class="form__field">';
            echo '<label for="fleet-name">Nom de la flotte</label>';
            echo '<input id="fleet-name" type="text" name="fleet_name" placeholder="Flotte d’intervention" maxlength="50" required>';
            echo '<small class="form__hint">Un nom clair facilite le suivi de vos forces.</small>';
            echo '</div>';
            echo '<div class="form__actions">';
            echo '<button class="button button--primary" type="submit">Créer la flotte</button>';
            echo '</div>';
            echo '</form>';
        },
    ]) ?>

    <?= $card([
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
            echo '<th scope="col">Nom</th>';
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
                $statusLabel = $isGarrison ? 'Garnison orbitale' : 'Flotte opérationnelle';
                $totalShips = (int)($fleet['total'] ?? 0);
                $manageQuery = ['fleet' => $fleetId];
                if ($selectedPlanetId !== null) {
                    $manageQuery['planet'] = (int)$selectedPlanetId;
                }
                $manageUrl = $basePath . '/fleet?' . http_build_query($manageQuery);

                echo '<tr>';
                echo '<td>';
                echo '<strong>' . htmlspecialchars($label) . '</strong>';
                if (!empty($fleet['ships'])) {
                    echo '<ul class="fleet-table__composition">';
                    $preview = array_slice($fleet['ships'], 0, 3);
                    foreach ($preview as $ship) {
                        $shipLabel = (string)($ship['label'] ?? $ship['key'] ?? 'Vaisseau');
                        $shipQuantity = (int)($ship['quantity'] ?? 0);
                        echo '<li>' . htmlspecialchars($shipLabel) . ' × ' . format_number($shipQuantity) . '</li>';
                    }
                    if (count($fleet['ships']) > 3) {
                        echo '<li>…</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p class="fleet-table__empty">Aucun vaisseau assigné.</p>';
                }
                echo '</td>';
                echo '<td class="fleet-table__metric">' . format_number($totalShips) . '</td>';
                echo '<td>' . htmlspecialchars($statusLabel) . '</td>';
                echo '<td class="fleet-table__actions">';
                echo '<a class="button button--small" href="' . htmlspecialchars($manageUrl, ENT_QUOTES) . '">Gérer</a>';
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
            'title' => 'Gestion de « ' . htmlspecialchars($selectedFleet['label'], ENT_QUOTES) . ' »',
            'subtitle' => 'Ajustez sa composition et préparez ses prochaines actions.',
            'body' => static function () use (
                $selectedFleet,
                $fleetActionUrl,
                $csrf_transfer,
                $csrf_rename,
                $csrf_delete,
                $csrf_manage_mission,
                $availableTargets,
                $garrisonFleetId,
                $selectedFleetId
            ): void {
                $hasTransferOptions = !empty($availableTargets) || ($garrisonFleetId !== null && $garrisonFleetId !== $selectedFleetId);

                echo '<div class="fleet-manage">';

                echo '<section class="fleet-manage__section">';
                echo '<h3>Composition actuelle</h3>';
                if (empty($selectedFleet['ships'])) {
                    echo '<p class="empty-state">Cette flotte ne contient actuellement aucun vaisseau.</p>';
                } else {
                    echo '<div class="table-wrapper">';
                    echo '<table class="data-table fleet-detail-table">';
                    echo '<thead><tr>';
                    echo '<th scope="col">Vaisseau</th>';
                    echo '<th scope="col">Rôle</th>';
                    echo '<th scope="col">Quantité</th>';
                    echo '</tr></thead>';
                    echo '<tbody>';
                    foreach ($selectedFleet['ships'] as $ship) {
                        $shipLabel = (string)($ship['label'] ?? $ship['key'] ?? 'Vaisseau');
                        $shipRole = (string)($ship['role'] ?? '');
                        $shipQuantity = (int)($ship['quantity'] ?? 0);
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($shipLabel) . '</td>';
                        echo '<td>' . ($shipRole !== '' ? htmlspecialchars($shipRole) : '—') . '</td>';
                        echo '<td class="fleet-detail-table__quantity">' . format_number($shipQuantity) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody>';
                    echo '</table>';
                    echo '</div>';
                }
                echo '</section>';

                echo '<section class="fleet-manage__section">';
                echo '<h3>Transférer des vaisseaux</h3>';
                if (!$hasTransferOptions) {
                    echo '<p class="empty-state">Aucune autre flotte ou hangar disponible pour recevoir des renforts.</p>';
                } else {
                    echo '<form class="fleet-transfer" method="post" action="' . htmlspecialchars($fleetActionUrl, ENT_QUOTES) . '">';
                    if ($csrf_transfer !== null) {
                        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)$csrf_transfer, ENT_QUOTES) . '">';
                    }
                    echo '<input type="hidden" name="action" value="transfer_from_fleet">';
                    echo '<input type="hidden" name="source_fleet_id" value="' . (int)$selectedFleet['id'] . '">';

                    if (!empty($selectedFleet['ships'])) {
                        echo '<div class="table-wrapper">';
                        echo '<table class="data-table fleet-transfer__table">';
                        echo '<thead><tr>';
                        echo '<th scope="col">Vaisseau</th>';
                        echo '<th scope="col">Disponible</th>';
                        echo '<th scope="col">Transférer</th>';
                        echo '</tr></thead>';
                        echo '<tbody>';
                        foreach ($selectedFleet['ships'] as $ship) {
                            $shipKey = (string)($ship['key'] ?? '');
                            $shipLabel = (string)($ship['label'] ?? $shipKey);
                            $shipQuantity = (int)($ship['quantity'] ?? 0);
                            $inputId = 'transfer-' . preg_replace('/[^a-z0-9_-]+/i', '-', $shipKey);

                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($shipLabel) . '</td>';
                            echo '<td class="fleet-transfer__available">' . format_number($shipQuantity) . '</td>';
                            echo '<td>';
                            $maxAttr = $shipQuantity > 0 ? ' max="' . $shipQuantity . '"' : '';
                            $disabledAttr = $shipQuantity === 0 ? ' disabled' : '';
                            echo '<label class="visually-hidden" for="' . htmlspecialchars($inputId, ENT_QUOTES) . '">Quantité à transférer</label>';
                            echo '<input class="fleet-transfer__input" type="number" id="' . htmlspecialchars($inputId, ENT_QUOTES) . '" name="ships[' . htmlspecialchars($shipKey, ENT_QUOTES) . ']" min="0" step="1"' . $maxAttr . $disabledAttr . ' placeholder="0">';
                            if ($shipQuantity > 0) {
                                echo '<small class="fleet-transfer__hint">max ' . format_number($shipQuantity) . '</small>';
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>';
                    } else {
                        echo '<p class="fleet-transfer__empty">Ajoutez d’abord des vaisseaux à cette flotte depuis le hangar.</p>';
                    }

                    echo '<div class="fleet-transfer__destination">';
                    echo '<label for="transfer-target">Destination</label>';
                    echo '<select id="transfer-target" name="target_fleet_id" required>';
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

                    echo '<div class="fleet-transfer__actions">';
                    echo '<button class="button button--primary" type="submit">Transférer les vaisseaux sélectionnés</button>';
                    echo '</div>';
                    echo '</form>';
                }
                echo '</section>';

                echo '<section class="fleet-manage__section">';
                echo '<h3>Renommer la flotte</h3>';
                echo '<form class="form" method="post" action="' . htmlspecialchars($fleetActionUrl, ENT_QUOTES) . '">';
                if ($csrf_rename !== null) {
                    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)$csrf_rename, ENT_QUOTES) . '">';
                }
                echo '<input type="hidden" name="action" value="rename_fleet">';
                echo '<input type="hidden" name="fleet_id" value="' . (int)$selectedFleet['id'] . '">';
                $currentName = $selectedFleet['name'] ?? '';
                echo '<div class="form__field">';
                echo '<label for="rename-fleet">Nouveau nom</label>';
                echo '<input id="rename-fleet" type="text" name="new_name" value="' . htmlspecialchars((string)$currentName, ENT_QUOTES) . '" placeholder="Flotte d’élite" maxlength="50" required>';
                echo '</div>';
                echo '<div class="form__actions">';
                echo '<button class="button" type="submit">Renommer</button>';
                echo '</div>';
                echo '</form>';
                echo '</section>';

                echo '<section class="fleet-manage__section">';
                echo '<h3>Supprimer la flotte</h3>';
                echo '<p class="form__hint">Les vaisseaux restants seront automatiquement renvoyés au hangar planétaire.</p>';
                echo '<form class="form" method="post" action="' . htmlspecialchars($fleetActionUrl, ENT_QUOTES) . '">';
                if ($csrf_delete !== null) {
                    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)$csrf_delete, ENT_QUOTES) . '">';
                }
                echo '<input type="hidden" name="action" value="delete_fleet">';
                echo '<input type="hidden" name="fleet_id" value="' . (int)$selectedFleet['id'] . '">';
                echo '<div class="form__actions">';
                echo '<button class="button button--danger" type="submit">Supprimer cette flotte</button>';
                echo '</div>';
                echo '</form>';
                echo '</section>';

                echo '<section class="fleet-manage__section">';
                echo '<h3>Envoyer en mission</h3>';
                echo '<p class="form__hint">Les types de mission sont en cours de développement. Sélectionnez celui à préparer.</p>';
                echo '<form class="mission-form" method="post" action="' . htmlspecialchars($fleetActionUrl, ENT_QUOTES) . '">';
                if ($csrf_manage_mission !== null) {
                    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)$csrf_manage_mission, ENT_QUOTES) . '">';
                }
                echo '<input type="hidden" name="action" value="plan_fleet_mission">';
                echo '<input type="hidden" name="fleet_id" value="' . (int)$selectedFleet['id'] . '">';
                $missions = [
                    'transport' => 'Transport [WIP]',
                    'stationnement' => 'Stationnement [WIP]',
                    'attaque' => 'Attaque [WIP]',
                    'defense' => 'Défense [WIP]',
                    'recyclage' => 'Recyclage [WIP]',
                    'espionnage' => 'Espionnage [WIP]',
                    'exploration' => 'Exploration [WIP]',
                    'mission_pve' => 'Mission PvE [WIP]',
                ];
                echo '<div class="mission-form__grid">';
                $index = 0;
                foreach ($missions as $value => $label) {
                    $index++;
                    $inputId = 'mission-' . $index;
                    echo '<label class="mission-form__option" for="' . htmlspecialchars($inputId, ENT_QUOTES) . '">';
                    echo '<input type="radio" id="' . htmlspecialchars($inputId, ENT_QUOTES) . '" name="mission" value="' . htmlspecialchars($value, ENT_QUOTES) . '"' . ($index === 1 ? ' checked' : '') . ' disabled>';
                    echo '<span>' . htmlspecialchars($label) . '</span>';
                    echo '</label>';
                }
                echo '</div>';
                echo '<div class="mission-form__actions">';
                echo '<button class="button button--primary" type="submit" disabled>Planifier la mission (à venir)</button>';
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
