<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var array<int, array{planet: \App\Domain\Entity\Planet, coordinateString: string, production: array<string, int>, totalProduction: int, activity: array{key: string, label: string, tone: string}, strength: array{key: string, label: string}, lastActivity: \DateTimeImmutable}> $map */
/** @var array{status: string, query: string} $filters */
/** @var array{totals: array<string, int>, activeCount: int, inactiveCount: int, strongCount: int} $summary */
/** @var array<int, array{coordinates: string, distance: int, potential: int, arrival: \DateTimeImmutable}> $suggestions */
/** @var array<int, array{name: string, score: int, trend: int}> $comparisons */
/** @var string $baseUrl */
/** @var int|null $selectedPlanetId */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary */

$title = $title ?? 'Carte galaxie';
$card = require __DIR__ . '/../components/_card.php';
$icon = require __DIR__ . '/../components/_icon.php';

if (!function_exists('format_relative_time')) {
    function format_relative_time(\DateTimeImmutable $date, \DateTimeImmutable $now): string
    {
        $diff = $now->getTimestamp() - $date->getTimestamp();
        if ($diff <= 0) {
            return 'à l’instant';
        }
        $hours = intdiv($diff, 3600);
        if ($hours > 24) {
            $days = intdiv($hours, 24);
            return $days > 1 ? $days . ' jours' : '1 jour';
        }
        if ($hours >= 1) {
            return $hours > 1 ? $hours . ' heures' : '1 heure';
        }
        $minutes = intdiv($diff, 60);
        if ($minutes >= 1) {
            return $minutes > 1 ? $minutes . ' minutes' : '1 minute';
        }

        return $diff . ' secondes';
    }
}

$filters = $filters ?? ['status' => 'all', 'query' => ''];
$summary = $summary ?? ['totals' => ['metal' => 0, 'crystal' => 0, 'hydrogen' => 0, 'energy' => 0], 'activeCount' => 0, 'inactiveCount' => 0, 'strongCount' => 0];
$map = $map ?? [];
$suggestions = $suggestions ?? [];
$comparisons = $comparisons ?? [];

$statusOptions = [
    'all' => 'Toutes les planètes',
    'active' => 'Actives',
    'calm' => 'Calmes',
    'idle' => 'Veille',
    'inactive' => 'Inactives',
    'strong' => 'Puissantes',
];

$now = new DateTimeImmutable();

ob_start();
?>
<section class="page-header">
    <div>
        <h1>Carte galaxie</h1>
        <p class="page-header__subtitle">Analysez vos colonies, repérez les opportunités de colonisation et évaluez la concurrence.</p>
    </div>
    <form class="page-header__actions galaxy-filters" method="get" action="<?= htmlspecialchars($baseUrl) ?>/galaxy">
        <label class="visually-hidden" for="galaxy-filter-status">Filtrer par statut</label>
        <select id="galaxy-filter-status" name="status">
            <?php foreach ($statusOptions as $value => $label): ?>
                <option value="<?= htmlspecialchars($value) ?>"<?= $filters['status'] === $value ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="visually-hidden" for="galaxy-filter-query">Rechercher une planète</label>
        <input id="galaxy-filter-query" type="search" name="q" placeholder="Nom ou coordonnées" value="<?= htmlspecialchars($filters['query']) ?>">
        <?php if ($selectedPlanetId !== null): ?>
            <input type="hidden" name="planet" value="<?= (int) $selectedPlanetId ?>">
        <?php endif; ?>
        <button class="button button--ghost" type="submit">Appliquer</button>
    </form>
</section>

<?= $card([
    'title' => 'Vue d’ensemble de l’empire',
    'subtitle' => 'Production agrégée et vitalité des colonies',
    'body' => static function () use ($summary): void {
        echo '<div class="metrics metrics--compact">';
        echo '<div class="metric"><span class="metric__label">Production métal/h</span><strong class="metric__value">' . number_format((int) ($summary['totals']['metal'] ?? 0)) . '</strong></div>';
        echo '<div class="metric"><span class="metric__label">Production cristal/h</span><strong class="metric__value">' . number_format((int) ($summary['totals']['crystal'] ?? 0)) . '</strong></div>';
        echo '<div class="metric"><span class="metric__label">Production hydrogène/h</span><strong class="metric__value">' . number_format((int) ($summary['totals']['hydrogen'] ?? 0)) . '</strong></div>';
        echo '<div class="metric"><span class="metric__label">Colonies actives</span><strong class="metric__value">' . number_format((int) ($summary['activeCount'] ?? 0)) . '</strong></div>';
        echo '<div class="metric"><span class="metric__label">Colonies inactives</span><strong class="metric__value">' . number_format((int) ($summary['inactiveCount'] ?? 0)) . '</strong></div>';
        echo '<div class="metric"><span class="metric__label">Colonies puissantes</span><strong class="metric__value">' . number_format((int) ($summary['strongCount'] ?? 0)) . '</strong></div>';
        echo '</div>';
    },
]) ?>

<?= $card([
    'title' => 'Planètes et activités',
    'subtitle' => 'Statut, production et dernière activité de chaque colonie',
    'body' => static function () use ($map, $baseUrl, $now): void {
        if ($map === []) {
            echo '<p class="empty-state">Aucune planète ne correspond aux filtres actuels.</p>';

            return;
        }

        echo '<div class="galaxy-grid">';
        foreach ($map as $entry) {
            $planet = $entry['planet'];
            $activity = $entry['activity'];
            $strength = $entry['strength'];
            $production = $entry['production'];
            echo '<article class="galaxy-card">';
            echo '<header class="galaxy-card__header">';
            echo '<div class="galaxy-card__title">';
            echo '<svg class="icon icon-sm" aria-hidden="true"><use href="' . htmlspecialchars($baseUrl) . '/assets/svg/sprite.svg#icon-planet"></use></svg>';
            echo '<h3>' . htmlspecialchars($planet->getName()) . '</h3>';
            echo '</div>';
            echo '<p class="galaxy-card__coordinates">' . htmlspecialchars($entry['coordinateString']) . '</p>';
            echo '</header>';
            $toneClass = 'status-pill status-pill--' . htmlspecialchars($activity['tone']);
            echo '<div class="galaxy-card__status">';
            echo '<span class="' . $toneClass . '">' . htmlspecialchars($activity['label']) . '</span>';
            echo '<span class="status-pill">' . htmlspecialchars($strength['label']) . '</span>';
            echo '</div>';
            echo '<dl class="galaxy-card__production">';
            echo '<div><dt>Métal</dt><dd>+' . number_format((int) $production['metal']) . '/h</dd></div>';
            echo '<div><dt>Cristal</dt><dd>+' . number_format((int) $production['crystal']) . '/h</dd></div>';
            echo '<div><dt>Hydrogène</dt><dd>+' . number_format((int) $production['hydrogen']) . '/h</dd></div>';
            echo '<div><dt>Énergie</dt><dd>' . number_format((int) $production['energy']) . '</dd></div>';
            echo '</dl>';
            echo '<p class="galaxy-card__activity">Dernière activité il y a ' . htmlspecialchars(format_relative_time($entry['lastActivity'], $now)) . '</p>';
            echo '</article>';
        }
        echo '</div>';
    },
]) ?>

<?= $card([
    'title' => 'Opportunités de colonisation',
    'subtitle' => 'Systèmes proches avec un fort potentiel de développement',
    'body' => static function () use ($suggestions): void {
        if ($suggestions === []) {
            echo '<p class="empty-state">Aucune suggestion pour le moment. Explorez la galaxie pour découvrir de nouvelles positions.</p>';

            return;
        }

        echo '<ul class="suggestion-list">';
        foreach ($suggestions as $suggestion) {
            $arrival = $suggestion['arrival'];
            echo '<li class="suggestion-list__item">';
            echo '<div class="suggestion-list__main">';
            echo '<strong>' . htmlspecialchars($suggestion['coordinates']) . '</strong>';
            echo '<span>Distance ' . number_format((int) $suggestion['distance']) . '</span>';
            echo '</div>';
            echo '<div class="suggestion-list__meta">';
            echo '<span class="suggestion-list__potential">Potentiel ' . number_format((int) $suggestion['potential']) . '%</span>';
            echo '<time datetime="' . $arrival->format('c') . '">Arrivée estimée ' . $arrival->format('d/m H:i') . '</time>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
    },
]) ?>

<?= $card([
    'title' => 'Comparaison des alliances',
    'subtitle' => 'Positionnez votre empire face aux coalitions rivales',
    'body' => static function () use ($comparisons): void {
        if ($comparisons === []) {
            echo '<p class="empty-state">Aucune donnée de comparaison disponible.</p>';

            return;
        }

        echo '<table class="comparison-table">';
        echo '<thead><tr><th>Alliance</th><th>Score production</th><th>Tendance</th></tr></thead>';
        echo '<tbody>';
        foreach ($comparisons as $entry) {
            $trend = (int) $entry['trend'];
            $trendClass = $trend > 0 ? 'trend-positive' : ($trend < 0 ? 'trend-negative' : 'trend-neutral');
            $trendLabel = $trend > 0 ? '+' . $trend . '%' : ($trend < 0 ? $trend . '%' : 'Stable');
            echo '<tr>';
            echo '<td>' . htmlspecialchars($entry['name']) . '</td>';
            echo '<td>' . number_format((int) $entry['score']) . '</td>';
            echo '<td><span class="' . $trendClass . '">' . htmlspecialchars($trendLabel) . '</span></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    },
]) ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';
