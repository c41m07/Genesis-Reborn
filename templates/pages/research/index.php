<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var array|null $overview */
/** @var string $baseUrl */
/** @var string|null $csrf_start */
/** @var int|null $selectedPlanetId */
$title = $title ?? 'Recherche';
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
        <?php if (!empty($planets ?? [])): ?>
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
            <a class="button button--ghost" href="<?= htmlspecialchars($baseUrl) ?>/tech-tree?planet=<?= $selectedPlanetId ?>">Voir l’arbre technologique</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($overview === null): ?>
    <article class="panel">
        <div class="panel__body">
            <p>Aucune planète sélectionnée. Utilisez le sélecteur de planète pour choisir un monde et planifier vos recherches.</p>
        </div>
    </article>
<?php else: ?>
    <?php $queue = $overview['queue'] ?? ['count' => 0, 'jobs' => []]; ?>
    <article class="panel">
        <header class="panel__header">
            <h2>Recherches en cours</h2>
            <p class="panel__subtitle">Suivi des programmes scientifiques actifs.</p>
        </header>
        <div class="panel__body">
            <?php if (($queue['count'] ?? 0) === 0): ?>
                <p class="empty-state">Aucune recherche n’est en cours. Lancez une étude pour étendre vos connaissances.</p>
            <?php else: ?>
                <ul class="queue-list">
                    <?php foreach ($queue['jobs'] as $job): ?>
                        <li class="queue-list__item">
                            <div>
                                <strong><?= htmlspecialchars($job['label'] ?? $job['research']) ?></strong>
                                <span>Niveau <?= number_format($job['targetLevel']) ?></span>
                            </div>
                            <div class="queue-list__timing">
                                <span>Termine dans <?= htmlspecialchars(format_duration((int) $job['remaining'])) ?></span>
                                <time datetime="<?= $job['endsAt']->format('c') ?>"><?= $job['endsAt']->format('d/m H:i') ?></time>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </article>
    <article class="panel panel--wide">
        <header class="panel__header">
            <h2>Laboratoire Helios</h2>
            <p class="panel__subtitle">Niveau actuel : <?= $overview['labLevel'] ?></p>
        </header>
        <div class="panel__body metrics metrics--compact">
            <div class="metric">
                <span class="metric__label">Niveaux cumulés</span>
                <strong class="metric__value"><?= number_format($overview['totals']['completedLevels']) ?></strong>
            </div>
            <div class="metric">
                <span class="metric__label">Domaines actifs</span>
                <strong class="metric__value"><?= number_format($overview['totals']['unlockedResearch']) ?></strong>
            </div>
            <div class="metric">
                <span class="metric__label">Meilleur niveau</span>
                <strong class="metric__value"><?= number_format($overview['totals']['highestLevel']) ?></strong>
            </div>
        </div>
    </article>

    <div class="research-grid">
        <?php foreach ($overview['categories'] as $category): ?>
            <section class="panel research-category">
                <header class="panel__header">
                    <div>
                        <h2><?= htmlspecialchars($category['label']) ?></h2>
                        <p class="panel__subtitle">Technologies associées à ce domaine stratégique.</p>
                    </div>
                    <?php if (!empty($category['image'])): ?>
                        <img class="panel__illustration" src="<?= htmlspecialchars($baseUrl . '/' . $category['image']) ?>" alt="">
                    <?php endif; ?>
                </header>
                <div class="panel__body research-list">
                    <?php foreach ($category['items'] as $item): ?>
                        <?php $definition = $item['definition']; ?>
                        <article class="research-card <?= $item['canResearch'] ? '' : 'is-locked' ?>">
                            <header class="research-card__header">
                                <div>
                                    <h3><?= htmlspecialchars($definition->getLabel()) ?></h3>
                                    <p class="research-card__description"><?= htmlspecialchars($definition->getDescription()) ?></p>
                                </div>
                                <span class="research-card__level">Niveau <?= $item['level'] ?> / <?= $definition->getMaxLevel() ?></span>
                            </header>
                            <div class="progress-bar">
                                <span class="progress-bar__value" style="width: <?= (int) round($item['progress'] * 100) ?>%"></span>
                            </div>
                            <div class="research-card__content">
                                <div class="research-card__costs">
                                    <h4>Prochaine amélioration</h4>
                                    <ul class="resource-list">
                                        <?php foreach ($item['nextCost'] as $resource => $amount): ?>
                                            <li>
                                                <svg class="icon icon-sm" aria-hidden="true">
                                                    <use href="<?= htmlspecialchars($baseUrl) ?>/assets/svg/sprite.svg#icon-<?= htmlspecialchars($resource) ?>"></use>
                                                </svg>
                                                <span><?= number_format($amount) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                        <li>
                                            <svg class="icon icon-sm" aria-hidden="true">
                                                <use href="<?= htmlspecialchars($baseUrl) ?>/assets/svg/sprite.svg#icon-time"></use>
                                            </svg>
                                            <span><?= htmlspecialchars(format_duration((int) $item['nextTime'])) ?></span>
                                        </li>
                                    </ul>
                                </div>
                                <?php if (!$item['requirements']['ok']): ?>
                                    <div class="research-card__requirements">
                                        <h4>Pré-requis</h4>
                                        <ul>
                                            <?php foreach ($item['requirements']['missing'] as $missing): ?>
                                                <li><?= htmlspecialchars($missing['label']) ?> (<?= $missing['current'] ?>/<?= $missing['level'] ?>)</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <footer class="research-card__footer">
                                <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/research?planet=<?= $selectedPlanetId ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_start ?? '') ?>">
                                    <input type="hidden" name="research" value="<?= htmlspecialchars($definition->getKey()) ?>">
                                    <button class="button button--primary" type="submit" <?= $item['canResearch'] ? '' : 'disabled' ?>><?= $item['canResearch'] ? 'Lancer la recherche' : 'Pré-requis manquants' ?></button>
                                </form>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/base.php';
