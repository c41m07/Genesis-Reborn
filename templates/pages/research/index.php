<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets Planètes du joueur. */
/** @var array|null $overview Détails du laboratoire actif. */
/** @var string $baseUrl URL de base pour générer les liens. */
/** @var string|null $csrf_start Jeton CSRF pour lancer une recherche. */
/** @var int|null $selectedPlanetId Identifiant de la planète affichée. */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary Résumé de ressources utilisé par le layout. */

$title = $title ?? 'Laboratoire de recherche';
$icon = require __DIR__ . '/../../components/_icon.php';
$card = require __DIR__ . '/../../components/_card.php';
$requirementsPanel = require __DIR__ . '/../../components/_requirements.php';
require_once __DIR__ . '/../../components/helpers.php';

$overview = $overview ?? null;
$queue = $overview['queue'] ?? ['count' => 0, 'jobs' => []];
$categories = $overview['categories'] ?? [];
$labBonus = (float)($overview['labBonus'] ?? 0.0);
$assetBase = rtrim($baseUrl, '/');

ob_start();
?>
<header class="ui-page-header">
    <div class="ui-page-header__titles">
        <h1 class="ui-page-header__title">Laboratoire de recherche</h1>
        <?php if ($overview): ?>
            <p class="ui-page-header__subtitle">Développez les technologies clés pour soutenir votre expansion interstellaire.</p>
        <?php else: ?>
            <p class="ui-page-header__subtitle">Sélectionnez une planète depuis l’en-tête pour accéder à ses laboratoires.</p>
        <?php endif; ?>
    </div>
    <div class="ui-page-header__actions">
        <?php if ($overview): ?>
            <a class="ui-button ui-button--ghost ui-button--sm" href="<?= htmlspecialchars($baseUrl) ?>/tech-tree?planet=<?= (int)$selectedPlanetId ?>">
                <span class="ui-button__label">Voir l’arbre technologique</span>
            </a>
        <?php endif; ?>
    </div>
</header>

<?php if ($overview === null): ?>
    <?= $card([
            'baseClass' => 'ui-card',
            'title' => 'Aucune recherche active',
            'bodyClass' => 'ui-card__body',
            'body' => static function (): void {
                echo '<p>Sélectionnez une planète via le sélecteur supérieur pour planifier vos programmes scientifiques.</p>';
            },
    ]) ?>
<?php else: ?>
    <?= $card([
            'baseClass' => 'ui-card',
            'class' => 'research-queue',
            'title' => 'Recherches en cours',
            'subtitle' => 'Suivi des programmes scientifiques actifs',
            'bodyClass' => 'ui-card__body research-queue__body',
            'body' => static function () use ($queue, $labBonus): void {
                $emptyMessage = 'Aucune recherche n’est en cours. Lancez une étude pour étendre vos connaissances.';
                if ($labBonus > 0) {
                    $bonusPercent = $labBonus * 100;
                    $bonusDisplay = rtrim(rtrim(number_format($bonusPercent, 1), '0'), '.');
                    echo '<p class="metric-line"><span class="metric-line__label">Bonus de vitesse</span><span class="metric-line__value metric-line__value--positive">+' . htmlspecialchars($bonusDisplay) . ' %</span></p>';
                }
                echo '<div class="queue-block" data-queue="research" data-empty="' . htmlspecialchars($emptyMessage, ENT_QUOTES) . '" data-server-now="' . time() . '">';
                if (($queue['count'] ?? 0) === 0) {
                    echo '<p class="empty-state">' . htmlspecialchars($emptyMessage) . '</p>';
                } else {
                    echo '<ul class="queue-list">';
                    foreach ($queue['jobs'] as $job) {
                        $label = $job['label'] ?? $job['research'] ?? '';
                        $endTime = null;
                        if (!empty($job['endsAt']) && $job['endsAt'] instanceof \DateTimeImmutable) {
                            $endTime = $job['endsAt']->getTimestamp();
                        } elseif (isset($job['remaining'])) {
                            $endTime = time() + max(0, (int)$job['remaining']);
                        }
                        echo '<li class="queue-list__item' . ($endTime ? '" data-endtime="' . (int)$endTime . '"' : '"') . '>';
                        echo '<div><strong>' . htmlspecialchars((string)$label) . '</strong><span>Niveau ' . format_number((int)($job['targetLevel'] ?? 0)) . '</span></div>';
                        echo '<div class="queue-list__timing">';
                        echo '<span><span class="countdown">' . htmlspecialchars(format_duration((int)($job['remaining'] ?? 0))) . '</span></span>';
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

    <?php foreach ($categories as $category): ?>
        <?php if (empty($category['items'])) {
            continue;
        } ?>
        <section class="ui-section research-section">
            <header class="ui-section__header">
                <h2><?= htmlspecialchars($category['label']) ?></h2>
            </header>
            <div class="ui-card-grid ui-card-grid--quad">
                <?php foreach ($category['items'] as $item): ?>
                    <?php
                    $definition = $item['definition'];
                    $canResearch = (bool)($item['canResearch'] ?? false);
                    $level = (int)($item['level'] ?? 0);
                    $maxLevel = (int)($item['maxLevel'] ?? 0);
                    $progress = (int)(($item['progress'] ?? 0.0) * 100);
                    //                    $progress = (int) round(($item['progress'] ?? 0.0) * 100);
                    $requirements = $item['requirements'] ?? ['ok' => true, 'missing' => []];
                    $requirementsOk = (bool)($requirements['ok'] ?? true);
                    $affordable = (bool)($item['affordable'] ?? false);
                    $missingResources = array_map(static fn ($value) => (int)$value, $item['missingResources'] ?? []);
                    $statusClasses = [];
                    if (!$canResearch) {
                        $statusClasses[] = 'is-locked';
                    }
                    $status = trim(implode(' ', $statusClasses));
                    $imagePath = $definition->getImage();
                    ?>
                    <?= $card([
                            'baseClass' => 'ui-card',
                            'title' => $definition->getLabel(),
                            'badge' => 'Niveau ' . $level . ' / ' . ($maxLevel > 0 ? $maxLevel : '∞'),
                            'status' => $status,
                            'class' => 'tech-card',
                            'attributes' => [
                                    'id' => 'research-' . $definition->getKey(),
                                    'data-research-card' => $definition->getKey(),
                            ],
                            'illustration' => $imagePath ? $assetBase . '/' . ltrim($imagePath, '/') : null,
                            'headerClass' => 'ui-card__header tech-card__header',
                            'bodyClass' => 'ui-card__body tech-card__body',
                            'footerClass' => 'ui-card__footer tech-card__footer',
                            'body' => static function () use (
                                $definition,
                                $item,
                                $progress,
                                $level,
                                $maxLevel,
                                $baseUrl,
                                $icon,
                                $requirementsPanel,
                                $requirements,
                                $missingResources
                            ): void {

                                echo '<p class="tech-card__description">' . htmlspecialchars($definition->getDescription()) . '</p>';
                                echo '<div class="tech-card__section">';
                                echo '<h3>Prochaine amélioration</h3>';
                                echo '<ul class="resource-list">';
                                foreach ($item['nextCost'] as $resource => $amount) {
                                    $resourceKey = (string)$resource;
                                    $classes = ['resource-list__item'];
                                    if (($missingResources[$resourceKey] ?? 0) > 0) {
                                        $classes[] = 'resource-list__item--missing';
                                    }
                                    echo '<li class="' . implode(' ', $classes) . '" data-resource="' . htmlspecialchars($resourceKey) . '">'
                                            . $icon($resourceKey, ['baseUrl' => $baseUrl, 'class' => 'icon-sm'])
                                            . '<span>' . format_number((int)$amount) . '</span>'
                                            . '</li>';
                                }
                                $nextTime = (int)($item['nextTime'] ?? 0);
                                $nextBaseTime = (int)($item['nextBaseTime'] ?? $nextTime);
                                echo '<li class="resource-list__item resource-list__item--time" data-resource="time">'
                                        . $icon('time', ['baseUrl' => $baseUrl, 'class' => 'icon-sm'])
                                        . '<span>' . htmlspecialchars(format_duration($nextTime));
                                if ($nextBaseTime !== $nextTime) {
                                    echo ' <small>(base ' . htmlspecialchars(format_duration($nextBaseTime)) . ')</small>';
                                }
                                echo '</span></li>';
                                echo '</ul>';
                                echo '</div>';
                                if (!($requirements['ok'] ?? true)) {
                                    $requirementItems = [];
                                    foreach ($requirements['missing'] ?? [] as $missing) {
                                        if (!is_array($missing)) {
                                            continue;
                                        }

                                        $requirementItems[] = [
                                                'label' => $missing['label'] ?? $missing['key'] ?? '',
                                                'current' => (int)($missing['current'] ?? 0),
                                                'required' => (int)($missing['level'] ?? 0),
                                        ];
                                    }

                                    if ($requirementItems !== []) {
                                        echo '<div class="tech-card__section">';
                                        echo $requirementsPanel([
                                                'title' => 'Pré-requis',
                                                'items' => $requirementItems,
                                                'icon' => $icon('research', [
                                                        'baseUrl' => $baseUrl,
                                                        'class' => 'icon-sm requirements-panel__glyph',
                                                ]),
                                        ]);
                                        echo '</div>';
                                    }
                                }
                            },
                            'footer' => static function () use (
                                $baseUrl,
                                $definition,
                                $csrf_start,
                                $selectedPlanetId,
                                $canResearch,
                                $requirementsOk,
                                $affordable
                            ): void {
                                echo '<form method="post" action="' . htmlspecialchars($baseUrl) . '/research?planet=' . (int)$selectedPlanetId . '" data-async="queue" data-queue-target="research">';
                                echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)$csrf_start) . '">';
                                echo '<input type="hidden" name="research" value="' . htmlspecialchars($definition->getKey()) . '">';
                                if ($canResearch) {
                                    $label = 'Lancer la recherche';
                                } elseif ($requirementsOk && !$affordable) {
                                    $label = 'Ressources insuffisantes';
                                } else {
                                    $label = 'Pré-requis manquants';
                                }
                                $disabled = $canResearch ? '' : ' disabled';
                                $buttonClasses = 'ui-button ui-button--primary ui-button--sm';
                                if ($requirementsOk && !$affordable) {
                                    $buttonClasses = 'ui-button ui-button--warning ui-button--sm';
                                } elseif (!$requirementsOk) {
                                    $buttonClasses = 'ui-button ui-button--neutral ui-button--sm';
                                }
                                echo '<button class="' . $buttonClasses . '" type="submit"' . $disabled . '>';
                                echo '<span class="ui-button__label">' . htmlspecialchars($label) . '</span>';
                                echo '</button>';
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
require __DIR__ . '/../../layouts/base.php';
?>


