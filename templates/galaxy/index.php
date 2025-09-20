<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var array<int, array<string, mixed>> $slots */
/** @var array<string, mixed> $summary */
/** @var array<int, array<string, mixed>> $players */
/** @var array<string, mixed> $filters */
/** @var string $baseUrl */
/** @var int|null $selectedPlanetId */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary */

$title = $title ?? 'Carte galaxie';
$card = require __DIR__ . '/../components/_card.php';
require_once __DIR__ . '/../components/helpers.php';

$spriteIcon = static fn (string $name): string => asset_url('assets/svg/sprite.svg#icon-' . $name, $baseUrl ?? '');

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

$filters = $filters ?? ['galaxy' => 1, 'system' => 1, 'view' => 'all', 'query' => '', 'options' => []];
$summary = $summary ?? ['galaxy' => 1, 'system' => 1, 'occupied' => 0, 'empty' => 0, 'activity' => ['active' => 0, 'calm' => 0, 'idle' => 0, 'inactive' => 0], 'strong' => 0, 'visibleCount' => 0];
$players = $players ?? [];
$slots = $slots ?? [];
$now = new DateTimeImmutable();

ob_start();
?>
<section class="page-header">
    <div>
        <h1>Carte galaxie</h1>
        <p class="page-header__subtitle">Analyse des positions du système <?= htmlspecialchars($summary['galaxy']) ?>:<?= htmlspecialchars($summary['system']) ?>.</p>
    </div>
    <form class="galaxy-controls" method="get" action="<?= htmlspecialchars($baseUrl) ?>/galaxy">
        <div class="galaxy-controls__group">
            <label for="galaxy-input">Galaxie</label>
            <input id="galaxy-input" type="number" name="galaxy" min="1" value="<?= htmlspecialchars((string) ($filters['galaxy'] ?? 1)) ?>">
        </div>
        <div class="galaxy-controls__group">
            <label for="system-input">Système</label>
            <input id="system-input" type="number" name="system" min="1" value="<?= htmlspecialchars((string) ($filters['system'] ?? 1)) ?>">
        </div>
        <div class="galaxy-controls__group">
            <label for="view-select">Filtrer</label>
            <select id="view-select" name="view">
                <?php foreach (($filters['options'] ?? []) as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>"<?= (($filters['view'] ?? 'all') === $value) ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="galaxy-controls__group galaxy-controls__group--search">
            <label for="galaxy-search">Recherche</label>
            <input id="galaxy-search" type="search" name="q" placeholder="Nom, joueur ou coordonnées" value="<?= htmlspecialchars((string) ($filters['query'] ?? '')) ?>">
        </div>
        <?php if ($selectedPlanetId !== null): ?>
            <input type="hidden" name="planet" value="<?= (int) $selectedPlanetId ?>">
        <?php endif; ?>
        <button class="button button--ghost" type="submit">Afficher</button>
    </form>
</section>

<?= $card([
    'title' => 'Synthèse du système',
    'body' => static function () use ($summary): void {
        echo '<div class="metrics metrics--compact">';
        echo '<div class="metric"><span class="metric__label">Positions occupées</span><strong class="metric__value">' . number_format((int) ($summary['occupied'] ?? 0)) . '</strong></div>';
        echo '<div class="metric"><span class="metric__label">Positions libres</span><strong class="metric__value">' . number_format((int) ($summary['empty'] ?? 0)) . '</strong></div>';
        echo '<div class="metric"><span class="metric__label">Colonies actives</span><strong class="metric__value">' . number_format((int) ($summary['activity']['active'] ?? 0)) . '</strong></div>';
        echo '<div class="metric"><span class="metric__label">Colonies inactives</span><strong class="metric__value">' . number_format((int) ($summary['activity']['inactive'] ?? 0)) . '</strong></div>';
        echo '<div class="metric"><span class="metric__label">Colonies puissantes</span><strong class="metric__value">' . number_format((int) ($summary['strong'] ?? 0)) . '</strong></div>';
        echo '<div class="metric"><span class="metric__label">Résultats correspondants</span><strong class="metric__value">' . number_format((int) ($summary['visibleCount'] ?? 0)) . '</strong></div>';
        echo '</div>';
    },
]) ?>

<?= $card([
    'title' => sprintf('Système %d:%d', (int) ($summary['galaxy'] ?? 0), (int) ($summary['system'] ?? 0)),
    'subtitle' => 'Visualisation détaillée des orbites et des dernières activités',
    'body' => static function () use ($slots, $baseUrl, $now, $spriteIcon): void {
        if ($slots === []) {
            echo '<p class="empty-state">Aucune donnée disponible pour ce système.</p>';

            return;
        }

        echo '<ol class="galaxy-system">';
        foreach ($slots as $slot) {
            $classes = ['galaxy-slot'];
            if (!empty($slot['isEmpty'])) {
                $classes[] = 'galaxy-slot--empty';
            }
            if (!empty($slot['isPlayer'])) {
                $classes[] = 'galaxy-slot--self';
            }
            if (!empty($slot['highlight'])) {
                $classes[] = 'galaxy-slot--highlight';
            }
            if (empty($slot['visible'])) {
                $classes[] = 'galaxy-slot--hidden';
            }
            if (!$slot['isEmpty'] && !empty($slot['activity']['key'])) {
                $classes[] = 'galaxy-slot--status-' . preg_replace('/[^a-z0-9_-]/i', '', (string) $slot['activity']['key']);
            }
            $classString = implode(' ', array_map(static fn (string $class): string => htmlspecialchars($class, ENT_QUOTES), $classes));

            echo '<li class="' . $classString . '">';
            echo '<header class="galaxy-slot__header">';
            echo '<span class="galaxy-slot__position">#' . number_format((int) ($slot['position'] ?? 0)) . '</span>';
            echo '<span class="galaxy-slot__coordinates">' . htmlspecialchars((string) ($slot['coordinates'] ?? '')) . '</span>';
            echo '</header>';
            echo '<div class="galaxy-slot__content">';

            if (!empty($slot['isEmpty'])) {
                echo '<p class="galaxy-slot__empty">Position libre — potentiel de colonisation.</p>';
            } else {
                $planet = $slot['planet'];
                echo '<div class="galaxy-slot__title">';
                $planetIconHref = htmlspecialchars($spriteIcon('planet'), ENT_QUOTES);
                echo '<svg class="icon icon-sm" aria-hidden="true"><use href="' . $planetIconHref . '"></use></svg>';
                echo '<h3>' . htmlspecialchars($planet ? $planet->getName() : 'Planète inconnue') . '</h3>';
                echo '</div>';
                echo '<div class="galaxy-slot__meta">';
                if (!empty($slot['owner']['name'])) {
                    echo '<span class="galaxy-slot__owner">' . htmlspecialchars((string) $slot['owner']['name']) . '</span>';
                }
                if (!empty($slot['activity']['label'])) {
                    $tone = htmlspecialchars((string) ($slot['activity']['tone'] ?? 'neutral'));
                    echo '<span class="status-pill status-pill--' . $tone . '">' . htmlspecialchars((string) $slot['activity']['label']) . '</span>';
                }
                if (!empty($slot['strength']['label'])) {
                    echo '<span class="status-pill">' . htmlspecialchars((string) $slot['strength']['label']) . '</span>';
                }
                echo '</div>';

                echo '<dl class="galaxy-slot__stats">';
                $resources = [
                    'metal' => 'Métal',
                    'crystal' => 'Cristal',
                    'hydrogen' => 'Hydrogène',
                    'energy' => 'Énergie',
                ];
                foreach ($resources as $key => $label) {
                    $value = (int) ($slot['production'][$key] ?? 0);
                    $prefix = $key === 'energy' ? '' : ($value >= 0 ? '+' : '');
                    echo '<div><dt>' . htmlspecialchars($label) . '</dt><dd>' . $prefix . number_format($value) . '/h</dd></div>';
                }
                echo '</dl>';

                if (!empty($slot['lastActivity']) && $slot['lastActivity'] instanceof DateTimeImmutable) {
                    echo '<p class="galaxy-slot__activity">Dernière activité ' . htmlspecialchars(format_relative_time($slot['lastActivity'], $now)) . '</p>';
                }
            }

            echo '</div>';
            echo '</li>';
        }
        echo '</ol>';
    },
]) ?>

<?= $card([
    'title' => 'Statistiques des joueurs',
    'subtitle' => 'Profil des commandants présents dans ce système',
    'body' => static function () use ($players, $now): void {
        if ($players === []) {
            echo '<p class="empty-state">Aucune présence détectée dans ce système.</p>';

            return;
        }

        echo '<ul class="galaxy-players">';
        foreach ($players as $player) {
            $status = $player['status'] ?? ['label' => 'Actif', 'tone' => 'neutral'];
            $tone = htmlspecialchars((string) ($status['tone'] ?? 'neutral'));
            echo '<li class="galaxy-players__item">';
            echo '<div class="galaxy-players__header">';
            echo '<strong class="galaxy-players__name">' . htmlspecialchars((string) ($player['name'] ?? 'Inconnu')) . '</strong>';
            echo '<span class="status-pill status-pill--' . $tone . '">' . htmlspecialchars((string) ($status['label'] ?? 'Actif')) . '</span>';
            echo '</div>';
            echo '<div class="galaxy-players__details">';
            echo '<span>' . number_format((int) ($player['planets'] ?? 0)) . ' planètes</span>';
            echo '<span>' . number_format((int) ($player['production'] ?? 0)) . ' production totale</span>';
            echo '<span>' . number_format((int) ($player['inactive'] ?? 0)) . ' inactives</span>';
            echo '</div>';
            if (!empty($player['lastActivity']) && $player['lastActivity'] instanceof DateTimeImmutable) {
                echo '<p class="galaxy-players__activity">Dernière activité ' . htmlspecialchars(format_relative_time($player['lastActivity'], $now)) . '</p>';
            }
            echo '</li>';
        }
        echo '</ul>';
    },
]) ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';
