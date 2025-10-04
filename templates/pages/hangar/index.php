<?php
/**
 * @var array<int, \App\Domain\Entity\Planet> $planets
 * @var int|null $selectedPlanetId
 * @var array<int, array{key: string, label: string, quantity: int, description: string, stats: array<string, int>, role: string, image: string|null}> $hangarEntries
 * @var int $totalShips
 * @var array<int, array{id: int, label: string, total: int, is_garrison: bool}> $idleFleets
 * @var string $baseUrl
 * @var string|null $csrf_transfer
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<int, string> $transferErrors
 * @var array{ships: array<string, int>, mode: string, fleet_id: string, new_fleet_name: string} $submittedTransfer
 * @var int|null $currentUserId
 * @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int, capacity: int}>}|null $activePlanetSummary
 */

$title = $title ?? 'Hangar planétaire';
$card = require __DIR__ . '/../../components/_card.php';
require_once __DIR__ . '/../../components/helpers.php';

$planets = $planets ?? [];
$hangarEntries = $hangarEntries ?? [];
$idleFleets = $idleFleets ?? [];
$transferErrors = $transferErrors ?? [];
$submittedTransfer = $submittedTransfer ?? [
    'ships' => [],
    'mode' => 'existing',
    'fleet_id' => '',
    'new_fleet_name' => '',
];
$selectedPlanetId = $selectedPlanetId ?? null;
$csrf_transfer = $csrf_transfer ?? null;
$totalShips = $totalShips ?? 0;
$basePath = rtrim((string) ($baseUrl ?? ''), '/');

ob_start();
?>
<section class="page-header">
    <div>
        <h1>Hangar planétaire</h1>
        <?php if ($selectedPlanetId !== null && !empty($planets)): ?>
            <p class="page-header__subtitle">Organisez les vaisseaux stationnés pour former rapidement vos flottes opérationnelles.</p>
        <?php else: ?>
            <p class="page-header__subtitle">Sélectionnez une planète depuis l’en-tête pour afficher le contenu de son hangar.</p>
        <?php endif; ?>
    </div>
    <div class="page-header__actions">
        <?php if ($selectedPlanetId !== null): ?>
            <a class="button button--ghost" href="<?= htmlspecialchars($baseUrl) ?>/fleet?planet=<?= (int) $selectedPlanetId ?>">Voir la flotte</a>
        <?php endif; ?>
    </div>
</section>

<?= $card([
    'title' => 'Garnison planétaire',
    'subtitle' => 'Gestion des vaisseaux disponibles',
    'body' => static function () use (
        $hangarEntries,
        $idleFleets,
        $submittedTransfer,
        $transferErrors,
        $csrf_transfer,
        $selectedPlanetId,
        $totalShips,
        $basePath
    ): void {
        $formId = 'hangar-transfer-form';
        $actionUrl = $selectedPlanetId !== null
            ? $basePath . '/hangar?planet=' . (int) $selectedPlanetId
            : $basePath . '/hangar';

        echo '<div class="metric-line">';
        echo '<span class="metric-line__label">Total en garnison</span>';
        echo '<span class="metric-line__value">' . format_number((int) $totalShips) . ' unité(s)</span>';
        echo '</div>';

        if (!empty($transferErrors)) {
            echo '<div class="hangar-transfer__errors" role="alert">';
            echo '<ul>';
            foreach ($transferErrors as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        if (empty($hangarEntries)) {
            echo '<p class="empty-state">Aucun vaisseau n’est actuellement stationné dans le hangar.</p>';

            return;
        }

        echo '<form id="' . htmlspecialchars($formId) . '" class="hangar-transfer" method="post" action="' . htmlspecialchars($actionUrl, ENT_QUOTES) . '">';
        if ($csrf_transfer !== null) {
            echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string) $csrf_transfer, ENT_QUOTES) . '">';
        }
        echo '<input type="hidden" name="action" value="transfer">';
        echo '<input type="hidden" name="target_mode" value="existing">';

        echo '<div class="table-wrapper">';
        echo '<table class="data-table hangar-table">';
        echo '<thead><tr>';
        echo '<th scope="col">Vaisseau</th>';
        echo '<th scope="col">Rôle</th>';
        echo '<th scope="col">Disponible</th>';
        echo '<th scope="col">Quantité</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        $submittedQuantities = is_array($submittedTransfer['ships'] ?? null) ? $submittedTransfer['ships'] : [];
        foreach ($hangarEntries as $entry) {
            $shipKey = (string) ($entry['key'] ?? '');
            $shipLabel = (string) ($entry['label'] ?? $shipKey);
            $shipRole = (string) ($entry['role'] ?? '');
            $shipQuantity = max(0, (int) ($entry['quantity'] ?? 0));
            $inputId = 'ship-select-' . preg_replace('/[^a-z0-9_-]+/i', '-', $shipKey);
            $hasSubmittedValue = array_key_exists($shipKey, $submittedQuantities);
            $suggestedQuantity = (int) ($submittedQuantities[$shipKey] ?? 0);
            if ($suggestedQuantity > $shipQuantity) {
                $suggestedQuantity = $shipQuantity;
            } elseif ($suggestedQuantity < 0) {
                $suggestedQuantity = 0;
            }

            echo '<tr class="hangar-table__row">';
            echo '<td class="hangar-table__cell hangar-table__cell--ship">';
            echo '<div class="hangar-table__ship">';
            echo '<strong>' . htmlspecialchars($shipLabel) . '</strong>';
            echo '</div>';
            echo '</td>';

            echo '<td class="hangar-table__cell">';
            echo $shipRole !== '' ? '<span>' . htmlspecialchars($shipRole) . '</span>' : '<span>—</span>';
            echo '</td>';

            echo '<td class="hangar-table__cell hangar-table__cell--quantity">' . format_number($shipQuantity) . '</td>';

            echo '<td class="hangar-table__cell hangar-table__cell--action">';
            $maxAttr = $shipQuantity > 0 ? ' max="' . $shipQuantity . '"' : '';
            $disabled = $shipQuantity === 0 ? ' disabled' : '';
            $valueAttr = $hasSubmittedValue ? ' value="' . $suggestedQuantity . '"' : '';
            echo '<div class="hangar-table__input">';
            echo '<label for="' . htmlspecialchars($inputId, ENT_QUOTES) . '-qty" class="visually-hidden">Quantité à retirer</label>';
            echo '<input class="hangar-table__quantity-input" type="number" id="' . htmlspecialchars($inputId, ENT_QUOTES) . '-qty" name="ships[' . htmlspecialchars($shipKey, ENT_QUOTES) . ']" min="0" step="1"' . $maxAttr . $valueAttr . $disabled . ' placeholder="0">';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        echo '<div class="hangar-transfer__footer">';
        if (!empty($idleFleets)) {
            echo '<div class="hangar-transfer__field topbar__selector">';
            echo '<label for="' . htmlspecialchars($formId, ENT_QUOTES) . '-fleet">Ajouter à la flotte</label>';
            echo '<select id="' . htmlspecialchars($formId, ENT_QUOTES) . '-fleet" name="fleet_id" required>';
            echo '<option value="">Sélectionnez une flotte</option>';
            foreach ($idleFleets as $fleet) {
                $fleetId = (int) ($fleet['id'] ?? 0);
                if ($fleetId <= 0) {
                    continue;
                }
                $label = (string) ($fleet['label'] ?? ('Flotte #' . $fleetId));
                $total = (int) ($fleet['total'] ?? 0);
                $display = $label . ' (' . format_number($total) . ')';
                $isSelectedFleet = $submittedTransfer['fleet_id'] !== '' && (int) $submittedTransfer['fleet_id'] === $fleetId;
                echo '<option value="' . htmlspecialchars((string) $fleetId, ENT_QUOTES) . '"' . ($isSelectedFleet ? ' selected' : '') . '>' . htmlspecialchars($display) . '</option>';
            }
            echo '</select>';
            echo '</div>';
        } else {
            echo '<p class="hangar-transfer__empty">Aucune flotte disponible pour recevoir des renforts.</p>';
        }

        echo '<div class="hangar-transfer__actions">';
        echo '<button class="button button--primary" type="submit">Ajouter à la flotte sélectionnée</button>';
        echo '</div>';
        echo '</div>';

        echo '</form>';
    },
]) ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/base.php';
