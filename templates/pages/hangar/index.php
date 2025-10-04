<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var array<int, array{key: string, label: string, quantity: int, description: string, stats: array<string, int>, role: string, image: string|null}> $hangarEntries */
/** @var array<int, string> $transferErrors */
/** @var array<int, string> $renameErrors */
/** @var array<int, string> $mergeErrors */
/** @var array{ship: string, quantity: int, mode: string, fleet_id: string, new_fleet_name: string} $submittedTransfer */
/** @var array{fleet_id: string, new_name: string} $submittedRename */
/** @var array{source_id: string, target_id: string, mode: string, ship_key: string, quantity: int} $submittedMerge */
/** @var int|null $selectedPlanetId */
/** @var string $baseUrl */
/** @var string|null $csrf_transfer */
/** @var int $totalShips */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int, capacity: int}>}|null $activePlanetSummary */
/** @var array<int, array{id: int, label: string, total: int, is_garrison: bool}> $idleFleets */
/** @var array<int, array{id: int, name: string|null, total: int, ships: array<string, int>, is_garrison: bool}> $idleFleetSummaries */
/** @var array<int, string> $availableFleetShipKeys */
/** @var array<int, array{id: int, label: string}> $renameableFleets */

$title = $title ?? 'Hangar planétaire';
$card = require __DIR__ . '/../../components/_card.php';
require_once __DIR__ . '/../../components/helpers.php';

$transferErrors = $transferErrors ?? [];
$renameErrors = $renameErrors ?? [];
$mergeErrors = $mergeErrors ?? [];
$submittedTransfer = $submittedTransfer ?? ['ship' => '', 'quantity' => 0];
$submittedRename = $submittedRename ?? ['fleet_id' => '', 'new_name' => ''];
$submittedMerge = $submittedMerge ?? ['source_id' => '', 'target_id' => '', 'mode' => 'partial', 'ship_key' => '', 'quantity' => 0];
$hangarEntries = $hangarEntries ?? [];
$idleFleets = $idleFleets ?? [];
$idleFleetSummaries = $idleFleetSummaries ?? [];
$availableFleetShipKeys = $availableFleetShipKeys ?? [];
$renameableFleets = $renameableFleets ?? [];
$totalShips = (int)($totalShips ?? 0);
$csrf_transfer = $csrf_transfer ?? null;
$selectedPlanetId = $selectedPlanetId ?? null;
$baseUrl = rtrim($baseUrl ?? '', '/');

ob_start();
?>
<section class="page-header">
    <div>
        <h1>Hangar planétaire</h1>
        <p class="page-header__subtitle">Gérez les vaisseaux stockés en orbite avant de composer vos flottes.</p>
    </div>
    <div class="page-header__actions">
        <?php if ($selectedPlanetId !== null): ?>
            <a class="button button--ghost" href="<?= htmlspecialchars($baseUrl) ?>/fleet?planet=<?= (int)$selectedPlanetId ?>">Voir la flotte</a>
        <?php endif; ?>
    </div>
</section>

<?= $card([
    'title' => 'Inventaire',
    'subtitle' => 'Résumé du stock disponible',
    'body' => static function () use ($totalShips, $idleFleets, $idleFleetSummaries): void {
        echo '<p class="metric-line"><span class="metric-line__label">Vaisseaux en réserve</span><span class="metric-line__value">' . format_number($totalShips) . ' unité(s)</span></p>';
        if ($totalShips === 0) {
            echo '<p class="empty-state">Aucun vaisseau n’est actuellement stocké dans ce hangar.</p>';
        }

        if (!empty($idleFleets)) {
            $fleetShipMap = [];
            foreach ($idleFleetSummaries as $summary) {
                $fleetShipMap[$summary['id']] = $summary['ships'];
            }
        }
    },
]) ?>

<?php if (!empty($transferErrors)): ?>
    <div class="form-errors" role="alert">
        <ul>
            <?php foreach ($transferErrors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (empty($hangarEntries)): ?>
    <?= $card([
        'title' => 'Stock vide',
        'body' => static function (): void {
            echo '<p class="empty-state">Produisez des vaisseaux depuis le chantier spatial pour alimenter le hangar.</p>';
        },
    ]) ?>
<?php else: ?>
    <div class="card-grid card-grid--triple">
        <?php foreach ($hangarEntries as $entry): ?>
            <?php
            $currentQuantity = (int)$entry['quantity'];
            $prefillQuantity = 1;
            if (($submittedTransfer['ship'] ?? '') === $entry['key']) {
                $prefillQuantity = max(1, (int)$submittedTransfer['quantity']);
            }
            ?>
            <?= $card([
                'title' => $entry['label'],
                'badge' => $entry['role'] ?? '',
                'illustration' => $entry['image'] ? $baseUrl . '/' . ltrim((string)$entry['image'], '/') : null,
                'bodyClass' => 'panel__body ship-card__body',
                'footerClass' => 'panel__footer ship-card__footer',
                'body' => static function () use ($entry, $currentQuantity): void {
                    echo '<p class="ship-card__description">' . htmlspecialchars($entry['description'] ?? '') . '</p>';
                    if (!empty($entry['stats'])) {
                        echo '<div class="ship-card__stats">';
                        foreach ($entry['stats'] as $label => $value) {
                            echo '<div class="mini-stat">';
                            echo '<span class="mini-stat__label">' . htmlspecialchars((string)$label) . '</span>';
                            echo '<strong class="mini-stat__value">' . format_number((int)$value) . '</strong>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    echo '<p class="ship-card__stock">Quantité disponible : <strong>' . format_number($currentQuantity) . '</strong></p>';
                },
                'footer' => static function () use ($entry, $baseUrl, $selectedPlanetId, $csrf_transfer, $prefillQuantity, $currentQuantity, $idleFleets, $submittedTransfer): void {
                    echo '<form class="hangar-transfer" method="post" action="' . htmlspecialchars($baseUrl) . '/hangar?planet=' . (int)$selectedPlanetId . '">';
                    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)$csrf_transfer) . '">';
                    echo '<input type="hidden" name="action" value="transfer">';
                    echo '<input type="hidden" name="ship" value="' . htmlspecialchars($entry['key']) . '">';
                    echo '<label class="hangar-transfer__field">';
                    echo '<span>Quantité à transférer</span>';
                    echo '<input type="number" name="quantity" min="1" max="' . max(1, $currentQuantity) . '" value="' . min($currentQuantity, max(1, $prefillQuantity)) . '">';
                    echo '</label>';
                    echo '<fieldset class="hangar-transfer__destination">';
                    echo '<legend>Destination</legend>';
                    $mode = $submittedTransfer['mode'] ?? 'existing';
                    $fleetId = $submittedTransfer['fleet_id'] ?? '';
                    $newFleetName = trim((string)($submittedTransfer['new_fleet_name'] ?? ''));
                    $isExisting = $mode !== 'new';
                    echo '<label class="hangar-transfer__choice">';
                    echo '<input type="radio" name="target_mode" value="existing"' . ($isExisting ? ' checked' : '') . '>';
                    echo '<span>Renforcer une flotte existante</span>';
                    echo '</label>';
                    echo '<select name="fleet_id" class="hangar-transfer__select">';
                    foreach ($idleFleets as $fleet) {
                        $optionValue = (string)$fleet['id'];
                        $selected = '';
                        if ($isExisting) {
                            if ($fleetId !== '') {
                                $selected = (string)$fleet['id'] === $fleetId ? ' selected' : '';
                            } elseif ($fleet['is_garrison']) {
                                $selected = ' selected';
                            }
                        }
                        $label = $fleet['label'];
                        if ($fleet['is_garrison']) {
                            $label .= ' (garnison)';
                        }
                        $label .= ' — ' . format_number((int)$fleet['total']) . ' unité(s)';
                        echo '<option value="' . htmlspecialchars($optionValue) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
                    }
                    echo '</select>';
//                    echo '<label class="hangar-transfer__choice">';
//                    echo '<input type="radio" name="target_mode" value="new"' . (!$isExisting ? ' checked' : '') . '>';
//                    echo '<span>Créer une nouvelle flotte nommée</span>';
//                    echo '</label>';
//                    echo '<input type="text" name="new_fleet_name" class="hangar-transfer__input" maxlength="100" placeholder="Nom de la flotte" value="' . htmlspecialchars($newFleetName) . '">';
//                    echo '</fieldset>';
                    echo '<button class="button" type="submit"' . ($currentQuantity === 0 ? ' disabled' : '') . '>Vers la flotte</button>';
                    echo '</form>';
                },
            ]) ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();

require __DIR__ . '/../../layouts/base.php';
