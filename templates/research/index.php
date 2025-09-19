<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var array|null $overview */
/** @var string $baseUrl */
/** @var string|null $csrf_start */
/** @var int|null $selectedPlanetId */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary */

$title = $title ?? 'Recherche';
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
$categories = $overview['categories'] ?? [];
$totals = $overview['totals'] ?? ['completedLevels' => 0, 'unlockedResearch' => 0, 'highestLevel' => 0];
$labLevel = $overview['labLevel'] ?? 0;
$assetBase = rtrim($baseUrl, '/');

ob_start();
?>
<section class="page-header">
    <div>
        <h1>Programme scientifique</h1>
        <?php if ($overview): ?>
            <p class="page-header__subtitle">Développez les technologies clés pour soutenir votre expansion interstellaire.</p>
        <?php else: ?>
            <p class="page-header__subtitle">Sélectionnez une planète pour accéder à ses laboratoires.</p>
        <?php endif; ?>
    </div>
    <div class="page-header__actions">
        <?php if (!empty($planets)): ?>
            <form class="planet-switcher" method="get" action="<?= htmlspecialchars($baseUrl) ?>/research">
                <label class="planet-switcher__label" for="planet-selector-research">Planète</label>
                <select class="planet-switcher__select" id="planet-selector-research" name="planet" data-auto-submit>
                    <?php foreach ($planets as $planetOption): ?>
                        <option value="<?= $planetOption->getId() ?>"<?= ($selectedPlanetId && $planetOption->getId() === $selectedPlanetId) ? ' selected' : '' ?>><?= htmlspecialchars($planetOption->getName()) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <?php if ($overview): ?>
            <a class="button button--ghost" href="<?= htmlspecialchars($baseUrl) ?>/tech-tree?planet=<?= (int) $selectedPlanetId ?>">Voir l’arbre technologique</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($overview === null): ?>
    <?= $card([
        'title' => 'Aucune recherche active',
        'body' => static function (): void {
            echo '<p>Sélectionnez une planète pour planifier vos programmes scientifiques.</p>';
        },
    ]) ?>
<?php else: ?>
    <?= $card([
        'title' => 'Recherches en cours',
        'subtitle' => 'Suivi des programmes scientifiques actifs',
        'body' => static function () use ($queue): void {
            $emptyMessage = 'Aucune recherche n’est en cours. Lancez une étude pour étendre vos connaissances.';
            echo '<div class="queue-block" data-queue="research" data-empty="' . htmlspecialchars($emptyMessage, ENT_QUOTES) . '">';
            if (($queue['count'] ?? 0) === 0) {
                echo '<p class="empty-state">' . htmlspecialchars($emptyMessage) . '</p>';
            } else {
                echo '<ul class="queue-list">';
                foreach ($queue['jobs'] as $job) {
                    $label = $job['label'] ?? $job['research'] ?? '';
                    echo '<li class="queue-list__item">';
                    echo '<div><strong>' . htmlspecialchars((string) $label) . '</strong><span>Niveau ' . number_format((int) ($job['targetLevel'] ?? 0)) . '</span></div>';
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
        'title' => 'Laboratoire Helios',
        'subtitle' => 'Niveau actuel : ' . number_format((int) $labLevel),
        'body' => static function () use ($totals): void {
            echo '<div class="metrics metrics--compact">';
            echo '<div class="metric"><span class="metric__label">Niveaux cumulés</span><strong class="metric__value">' . number_format((int) $totals['completedLevels']) . '</strong></div>';
            echo '<div class="metric"><span class="metric__label">Domaines actifs</span><strong class="metric__value">' . number_format((int) $totals['unlockedResearch']) . '</strong></div>';
            echo '<div class="metric"><span class="metric__label">Meilleur niveau</span><strong class="metric__value">' . number_format((int) $totals['highestLevel']) . '</strong></div>';
            echo '</div>';
        },
    ]) ?>

    <div class="grid grid--stacked">
        <?php foreach ($categories as $category): ?>
            <?php $categoryImage = $category['image'] ?? null; ?>
            <?= $card([
                'title' => $category['label'],
                'subtitle' => 'Technologies associées à ce domaine stratégique',
                'illustration' => !empty($categoryImage) ? $assetBase . '/' . ltrim($categoryImage, '/') : null,
                'body' => static function () use ($category, $baseUrl, $icon, $csrf_start, $selectedPlanetId): void {
                    echo '<div class="research-list">';
                    foreach ($category['items'] as $item) {
                        $definition = $item['definition'];
                        $canResearch = (bool) ($item['canResearch'] ?? false);
                        $level = (int) ($item['level'] ?? 0);
                        $maxLevel = (int) ($item['maxLevel'] ?? 0);
                        $progress = (int) round(($item['progress'] ?? 0) * 100);
                        echo '<article class="research-entry' . ($canResearch ? '' : ' is-locked') . '">';
                        echo '<header class="research-entry__header">';
                        echo '<div>';
                        echo '<h3>' . htmlspecialchars($definition->getLabel()) . '</h3>';
                        echo '<p class="research-entry__description">' . htmlspecialchars($definition->getDescription()) . '</p>';
                        echo '</div>';
                        echo '<span class="research-entry__level">Niveau ' . $level . ' / ' . ($maxLevel > 0 ? $maxLevel : '∞') . '</span>';
                        echo '</header>';
                        echo '<div class="progress-bar"><span class="progress-bar__value" style="width: ' . $progress . '%"></span></div>';
                        echo '<div class="research-entry__body">';
                        echo '<div class="research-entry__costs">';
                        echo '<h4>Prochaine amélioration</h4>';
                        echo '<ul class="resource-list">';
                        foreach ($item['nextCost'] as $resource => $amount) {
                            echo '<li>' . $icon((string) $resource, ['baseUrl' => $baseUrl, 'class' => 'icon-sm']) . '<span>' . number_format((int) $amount) . '</span></li>';
                        }
                        echo '<li>' . $icon('time', ['baseUrl' => $baseUrl, 'class' => 'icon-sm']) . '<span>' . htmlspecialchars(format_duration((int) $item['nextTime'])) . '</span></li>';
                        echo '</ul>';
                        echo '</div>';
                        if (!($item['requirements']['ok'] ?? true)) {
                            echo '<div class="research-entry__requirements">';
                            echo '<h4>Pré-requis</h4>';
                            echo '<ul>';
                            foreach ($item['requirements']['missing'] as $missing) {
                                echo '<li>' . htmlspecialchars($missing['label']) . ' (' . number_format((int) $missing['current']) . '/' . number_format((int) $missing['level']) . ')</li>';
                            }
                            echo '</ul>';
                            echo '</div>';
                        }
                        echo '</div>';
                        echo '<footer class="research-entry__footer">';
                        echo '<form method="post" action="' . htmlspecialchars($baseUrl) . '/research?planet=' . (int) $selectedPlanetId . '" data-async="queue" data-queue-target="research">';
                        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string) $csrf_start) . '">';
                        echo '<input type="hidden" name="research" value="' . htmlspecialchars($definition->getKey()) . '">';
                        $label = $canResearch ? 'Lancer la recherche' : 'Pré-requis manquants';
                        $disabled = $canResearch ? '' : ' disabled';
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
