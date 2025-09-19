<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var array|null $overview */
/** @var string $baseUrl */
/** @var string|null $csrf_shipyard */
/** @var int|null $selectedPlanetId */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary */

$title = $title ?? 'Chantier spatial';
$icon = require __DIR__ . '/../components/_icon.php';
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

$overview = $overview ?? null;
$queue = $overview['queue'] ?? ['count' => 0, 'jobs' => []];
$fleet = $overview['fleet'] ?? [];
$fleetSummary = $overview['fleetSummary'] ?? [];
$categories = $overview['categories'] ?? [];
$shipyardLevel = $overview['shipyardLevel'] ?? 0;
$fleetCount = 0;
if (!empty($fleetSummary)) {
    foreach ($fleetSummary as $ship) {
        $fleetCount += (int) ($ship['quantity'] ?? 0);
    }
} else {
    $fleetCount = array_sum($fleet);
}

$assetBase = rtrim($baseUrl, '/');

ob_start();
?>
<section class="page-header">
    <div>
        <h1>Chantier spatial</h1>
        <?php if ($overview): ?>
            <p class="page-header__subtitle">Organisez la production de vos vaisseaux et renforcez votre flotte orbitale.</p>
        <?php else: ?>
            <p class="page-header__subtitle">Sélectionnez une planète pour accéder à ses hangars.</p>
        <?php endif; ?>
    </div>
    <div class="page-header__actions">
        <?php if (!empty($planets)): ?>
            <form class="planet-switcher" method="get" action="<?= htmlspecialchars($baseUrl) ?>/shipyard">
                <label class="planet-switcher__label" for="planet-selector-shipyard">Planète</label>
                <select class="planet-switcher__select" id="planet-selector-shipyard" name="planet" data-auto-submit>
                    <?php foreach ($planets as $planetOption): ?>
                        <option value="<?= $planetOption->getId() ?>"<?= ($selectedPlanetId && $planetOption->getId() === $selectedPlanetId) ? ' selected' : '' ?>><?= htmlspecialchars($planetOption->getName()) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <?php if ($overview): ?>
            <a class="button button--ghost" href="<?= htmlspecialchars($baseUrl) ?>/fleet?planet=<?= (int) $selectedPlanetId ?>">Voir la flotte</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($overview === null): ?>
    <?= $card([
        'title' => 'Aucun chantier actif',
        'body' => static function (): void {
            echo '<p>Sélectionnez une planète équipée d’un chantier spatial pour lancer la production.</p>';
        },
    ]) ?>
<?php else: ?>
    <?= $card([
        'title' => 'Commandes de vaisseaux',
        'subtitle' => 'Suivi des constructions orbitales',
        'body' => static function () use ($queue): void {
            $emptyMessage = 'Aucune commande de vaisseau n’est en file. Lancez une production pour étoffer votre flotte.';
            echo '<div class="queue-block" data-queue="shipyard" data-empty="' . htmlspecialchars($emptyMessage, ENT_QUOTES) . '">';
            if (($queue['count'] ?? 0) === 0) {
                echo '<p class="empty-state">' . htmlspecialchars($emptyMessage) . '</p>';
            } else {
                echo '<ul class="queue-list">';
                foreach ($queue['jobs'] as $job) {
                    $label = $job['label'] ?? $job['ship'] ?? '';
                    echo '<li class="queue-list__item">';
                    echo '<div><strong>' . htmlspecialchars((string) $label) . '</strong><span>' . number_format((int) ($job['quantity'] ?? 0)) . ' unité(s)</span></div>';
                    echo '<div class="queue-list__timing">';
                    echo '<span>Termine dans ' . htmlspecialchars(format_duration((int) ($job['remaining'] ?? 0))) . '</span>';
                    if (!empty($job['endsAt']) && $job['endsAt'] instanceof \DateTimeImmutable) {
                        echo '<time datetime="' . $job['endsAt']->format('c') . '">' . $job['endsAt']->format('d/m H:i') . '</time>';
                    }
                    echo '</div>';
                    echo '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        },
    ]) ?>

    <?= $card([
        'title' => 'Hangars de production',
        'subtitle' => 'Niveau de chantier : ' . number_format((int) $shipyardLevel),
        'body' => static function () use ($shipyardLevel, $fleetCount, $fleet, $fleetSummary): void {
            echo '<div class="metrics metrics--compact">';
            echo '<div class="metric"><span class="metric__label">Capacité du chantier</span><strong class="metric__value">Niveau ' . number_format((int) $shipyardLevel) . '</strong></div>';
            echo '<div class="metric"><span class="metric__label">Unités stationnées</span><strong class="metric__value">' . number_format((int) $fleetCount) . '</strong></div>';
            echo '</div>';
            if ($fleetCount === 0) {
                echo '<p class="empty-state">Aucun vaisseau n’est encore construit. Lancez la production pour constituer votre flotte.</p>';
            } else {
                echo '<ul class="fleet-list">';
                $displayFleet = $fleetSummary !== [] ? $fleetSummary : array_map(static fn ($key, $quantity) => ['key' => $key, 'label' => $key, 'quantity' => $quantity], array_keys($fleet), $fleet);
                foreach ($displayFleet as $shipEntry) {
                    $label = $shipEntry['label'] ?? $shipEntry['key'] ?? '';
                    $quantity = (int) ($shipEntry['quantity'] ?? 0);
                    echo '<li><span class="fleet-list__name">' . htmlspecialchars($label) . '</span><span class="fleet-list__qty">× ' . number_format($quantity) . '</span></li>';
                }
                echo '</ul>';
            }
        },
    ]) ?>

    <div class="grid grid--stacked">
        <?php foreach ($categories as $category): ?>
            <?php $categoryImage = $category['image'] ?? null; ?>
            <?= $card([
                'title' => $category['label'],
                'subtitle' => 'Modèles disponibles pour cette classe de vaisseaux',
                'illustration' => !empty($categoryImage) ? $assetBase . '/' . ltrim($categoryImage, '/') : null,
                'body' => static function () use ($category, $baseUrl, $icon, $csrf_shipyard, $selectedPlanetId): void {
                    echo '<div class="shipyard-list">';
                    foreach ($category['items'] as $item) {
                        $definition = $item['definition'];
                        $canBuild = (bool) ($item['canBuild'] ?? false);
                        echo '<article class="ship-card' . ($canBuild ? '' : ' is-locked') . '">';
                        echo '<header class="ship-card__header">';
                        echo '<div>';
                        echo '<h3>' . htmlspecialchars($definition->getLabel()) . '</h3>';
                        echo '<p class="ship-card__role">' . htmlspecialchars($definition->getRole()) . '</p>';
                        echo '</div>';
                        if ($definition->getImage()) {
                            $imageSrc = $assetBase . '/' . ltrim($definition->getImage(), '/');
                            echo '<img class="ship-card__illustration" src="' . htmlspecialchars($imageSrc, ENT_QUOTES) . '" alt="" loading="lazy" decoding="async">';
                        }
                        echo '</header>';
                        echo '<p class="ship-card__description">' . htmlspecialchars($definition->getDescription()) . '</p>';
                        echo '<div class="ship-card__stats">';
                        foreach ($definition->getStats() as $label => $value) {
                            echo '<div class="mini-stat"><span class="mini-stat__label">' . htmlspecialchars(ucfirst((string) $label)) . '</span><strong class="mini-stat__value">' . number_format((int) $value) . '</strong></div>';
                        }
                        echo '</div>';
                        echo '<div class="ship-card__content">';
                        echo '<div class="ship-card__costs">';
                        echo '<h4>Coût unitaire</h4>';
                        echo '<ul class="resource-list">';
                        foreach ($definition->getBaseCost() as $resource => $amount) {
                            echo '<li>' . $icon((string) $resource, ['baseUrl' => $baseUrl, 'class' => 'icon-sm']) . '<span>' . number_format((int) $amount) . '</span></li>';
                        }
                        echo '<li>' . $icon('time', ['baseUrl' => $baseUrl, 'class' => 'icon-sm']) . '<span>' . htmlspecialchars(format_duration((int) $definition->getBuildTime())) . '</span></li>';
                        echo '</ul>';
                        echo '</div>';
                        if (!($item['requirements']['ok'] ?? true)) {
                            echo '<div class="ship-card__requirements">';
                            echo '<h4>Pré-requis</h4>';
                            echo '<ul>';
                            foreach ($item['requirements']['missing'] as $missing) {
                                echo '<li>' . htmlspecialchars($missing['label']) . ' (' . number_format((int) ($missing['current'] ?? 0)) . '/' . number_format((int) $missing['level']) . ')</li>';
                            }
                            echo '</ul>';
                            echo '</div>';
                        }
                        echo '</div>';
                        echo '<footer class="ship-card__footer">';
                        echo '<form method="post" action="' . htmlspecialchars($baseUrl) . '/shipyard?planet=' . (int) $selectedPlanetId . '" data-async="queue" data-queue-target="shipyard">';
                        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string) $csrf_shipyard) . '">';
                        echo '<input type="hidden" name="ship" value="' . htmlspecialchars($definition->getKey()) . '">';
                        echo '<label class="ship-card__quantity"><span>Quantité</span><input type="number" name="quantity" min="1" value="1"' . ($canBuild ? '' : ' disabled') . '></label>';
                        $label = $canBuild ? 'Construire' : 'Pré-requis manquants';
                        $disabled = $canBuild ? '' : ' disabled';
                        echo '<button class="button button--primary" type="submit"' . $disabled . '>' . $label . '</button>';
                        echo '</form>';
                        echo '</footer>';
                        echo '</article>';
                    }
                    echo '</div>';
                },
            ]) ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';
