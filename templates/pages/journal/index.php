<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets Planètes accessibles pour la sélection. */
/** @var array<int, array{type: string, icon: string, title: string, description: string, endsAt: \DateTimeImmutable, remaining: int}> $events Evénements structurés pour l’affichage. */
/** @var array{buildQueue: int, researchQueue: int, shipQueue: int, nextEvent: ?array} $insights Compteurs synthétiques affichés dans la vue. */
/** @var string $baseUrl URL de base pour générer les liens. */
/** @var int|null $selectedPlanetId Identifiant de la planète suivie. */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary Résumé de la planète affichée. */

$title = $title ?? 'Journal de bord';
$icon = require __DIR__ . '/../../components/_icon.php';
$card = require __DIR__ . '/../../components/_card.php';

ob_start();
?>
<section class="page-header">
    <div>
        <h1>Journal de bord</h1>
        <p class="page-header__subtitle">Surveillez les activités majeures de la colonie et anticipez les échéances importantes.</p>
    </div>
    <div class="page-header__actions"></div>
</section>

<?= $card([
    'title' => 'Statistiques des files',
    'subtitle' => 'Aperçu des productions en attente',
    'body' => static function () use ($insights): void {
        echo '<div class="metrics metrics--compact">';
        echo '<div class="metric"><span class="metric__label">Bâtiments</span><strong class="metric__value">' . number_format((int) $insights['buildQueue']) . '</strong></div>';
        echo '<div class="metric"><span class="metric__label">Recherches</span><strong class="metric__value">' . number_format((int) $insights['researchQueue']) . '</strong></div>';
        echo '<div class="metric"><span class="metric__label">Chantier spatial</span><strong class="metric__value">' . number_format((int) $insights['shipQueue']) . '</strong></div>';
        echo '</div>';
        if (!empty($insights['nextEvent']) && is_array($insights['nextEvent'])) {
            $event = $insights['nextEvent'];
            echo '<p class="metric-hint">Prochain événement : <strong>' . htmlspecialchars($event['title']) . '</strong> dans ' . number_format((int) floor($event['remaining'] / 60)) . ' min.</p>';
        }
    },
]) ?>

<?= $card([
    'title' => 'Chronologie des événements',
    'subtitle' => 'Historique des opérations en cours',
    'body' => static function () use ($events, $icon, $baseUrl): void {
        if ($events === []) {
            echo '<p class="empty-state">Aucune activité récente sur cette planète.</p>';

            return;
        }

        echo '<ol class="timeline">';
        foreach ($events as $event) {
            $time = $event['endsAt'];
            echo '<li class="timeline__item">';
            echo '<div class="timeline__icon">' . $icon($event['icon'], ['baseUrl' => $baseUrl, 'class' => 'icon-sm']) . '</div>';
            echo '<div class="timeline__content">';
            echo '<h3>' . htmlspecialchars($event['title']) . '</h3>';
            echo '<p>' . htmlspecialchars($event['description']) . '</p>';
            echo '<time datetime="' . $time->format('c') . '">' . $time->format('d/m/Y H:i') . '</time>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ol>';
    },
]) ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/base.php';
