<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var array|null $overview */
/** @var string $baseUrl */
/** @var string|null $csrf_start */
/** @var int|null $selectedPlanetId */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary */

$title = $title ?? 'Laboratoire de recherche';
$icon = require __DIR__ . '/../components/_icon.php';
$card = require __DIR__ . '/../components/_card.php';
require_once __DIR__ . '/../components/helpers.php';

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
        <h1>Laboratoire de recherche</h1>
        <?php if ($overview): ?>
            <p class="page-header__subtitle">Développez les technologies clés pour soutenir votre expansion interstellaire.</p>
        <?php else: ?>
            <p class="page-header__subtitle">Sélectionnez une planète depuis l’en-tête pour accéder à ses laboratoires.</p>
        <?php endif; ?>
    </div>
    <div class="page-header__actions">
        <?php if ($overview): ?>
            <a class="button button--ghost" href="<?= htmlspecialchars($baseUrl) ?>/tech-tree?planet=<?= (int) $selectedPlanetId ?>">Voir l’arbre technologique</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($overview === null): ?>
    <?= $card([
        'title' => 'Aucune recherche active',
        'body' => static function (): void {
            echo '<p>Sélectionnez une planète via le sélecteur supérieur pour planifier vos programmes scientifiques.</p>';
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

    <?php foreach ($categories as $category): ?>
        <?php if (empty($category['items'])) { continue; } ?>
        <section class="content-section">
            <header class="content-section__header">
                <h2><?= htmlspecialchars($category['label']) ?></h2>
            </header>
            <div class="card-grid card-grid--quad">
                <?php foreach ($category['items'] as $item): ?>
                    <?php
                    $definition = $item['definition'];
                    $canResearch = (bool) ($item['canResearch'] ?? false);
                    $level = (int) ($item['level'] ?? 0);
                    $maxLevel = (int) ($item['maxLevel'] ?? 0);
                    $progress = (int) round(($item['progress'] ?? 0) * 100);
                    $status = $canResearch ? '' : 'is-locked';
                    ?>
                    <?= $card([
                        'title' => $definition->getLabel(),
                        'badge' => 'Niveau ' . $level . ' / ' . ($maxLevel > 0 ? $maxLevel : '∞'),
                        'status' => $status,
                        'class' => 'tech-card',
                        'attributes' => [
                            'data-research-card' => $definition->getKey(),
                        ],
                        'body' => static function () use ($definition, $item, $progress, $level, $maxLevel, $baseUrl, $icon): void {
                            echo '<p class="tech-card__description">' . htmlspecialchars($definition->getDescription()) . '</p>';
                            echo '<div class="tech-card__progress">';
                            echo '<div class="progress-bar"><span class="progress-bar__value" style="width: ' . $progress . '%"></span></div>';
                            echo '<p class="tech-card__level">Niveau actuel ' . $level . ($maxLevel > 0 ? ' / ' . $maxLevel : '') . '</p>';
                            echo '</div>';
                            echo '<div class="tech-card__section">';
                            echo '<h3>Prochaine amélioration</h3>';
                            echo '<ul class="resource-list">';
                            foreach ($item['nextCost'] as $resource => $amount) {
                                echo '<li>' . $icon((string) $resource, ['baseUrl' => $baseUrl, 'class' => 'icon-sm']) . '<span>' . number_format((int) $amount) . '</span></li>';
                            }
                            echo '<li>' . $icon('time', ['baseUrl' => $baseUrl, 'class' => 'icon-sm']) . '<span>' . htmlspecialchars(format_duration((int) $item['nextTime'])) . '</span></li>';
                            echo '</ul>';
                            echo '</div>';
                            if (!($item['requirements']['ok'] ?? true)) {
                                echo '<div class="tech-card__section tech-card__requirements">';
                                echo '<h3>Pré-requis</h3>';
                                echo '<ul>';
                                foreach ($item['requirements']['missing'] as $missing) {
                                    echo '<li>' . htmlspecialchars($missing['label']) . ' (' . number_format((int) $missing['current']) . '/' . number_format((int) $missing['level']) . ')</li>';
                                }
                                echo '</ul>';
                                echo '</div>';
                            }
                        },
                        'footer' => static function () use ($baseUrl, $definition, $csrf_start, $selectedPlanetId, $canResearch): void {
                            echo '<form method="post" action="' . htmlspecialchars($baseUrl) . '/research?planet=' . (int) $selectedPlanetId . '" data-async="queue" data-queue-target="research">';
                            echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string) $csrf_start) . '">';
                            echo '<input type="hidden" name="research" value="' . htmlspecialchars($definition->getKey()) . '">';
                            $label = $canResearch ? 'Lancer la recherche' : 'Pré-requis manquants';
                            $disabled = $canResearch ? '' : ' disabled';
                            echo '<button class="button button--primary" type="submit"' . $disabled . '>' . $label . '</button>';
                            echo '</form>';
                        },
                    ]) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';
