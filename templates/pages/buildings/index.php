<?php
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var array|null $overview */
/** @var string $baseUrl */
/** @var array $flashes */
/** @var int|null $currentUserId */
/** @var string|null $csrf_upgrade */
/** @var int|null $selectedPlanetId */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary */
$title = $title ?? 'Bâtiments';
$planet = $overview['planet'] ?? null;
$buildings = $overview['buildings'] ?? [];
$queue = $overview['queue'] ?? ['count' => 0, 'jobs' => []];
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
        <h1>Gestion de la planète</h1>
        <?php if ($planet): ?>
            <p class="page-header__subtitle">Optimisez les infrastructures de <?= htmlspecialchars($planet->getName()) ?> pour accroître votre production.</p>
        <?php else: ?>
            <p class="page-header__subtitle">Sélectionnez une planète depuis le menu pour gérer ses infrastructures.</p>
        <?php endif; ?>
    </div>
    <div class="page-header__actions">
        <?php if (!empty($planets ?? [])): ?>
            <form class="planet-switcher" method="get" action="<?= htmlspecialchars($baseUrl) ?>/buildings">
                <label class="planet-switcher__label" for="planet-selector-buildings">Planète</label>
                <select class="planet-switcher__select" id="planet-selector-buildings" name="planet" data-auto-submit>
                    <?php foreach ($planets as $planetOption): ?>
                        <option value="<?= $planetOption->getId() ?>"<?= ($planet && $planetOption->getId() === $planet->getId()) ? ' selected' : '' ?>><?= htmlspecialchars($planetOption->getName()) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <?php if ($planet): ?>
            <a class="button button--ghost" href="<?= htmlspecialchars($baseUrl) ?>/research?planet=<?= $planet->getId() ?>">Voir les recherches</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($overview === null): ?>
    <article class="panel">
        <div class="panel__body">
            <p>Aucune planète sélectionnée. Utilisez le sélecteur de planète pour afficher ses bâtiments.</p>
        </div>
    </article>
<?php else: ?>
    <article class="panel">
        <header class="panel__header">
            <h2>File de construction</h2>
            <p class="panel__subtitle">Suivi des améliorations en cours.</p>
        </header>
        <div class="panel__body">
            <?php if (($queue['count'] ?? 0) === 0): ?>
                <p class="empty-state">Aucune amélioration n’est planifiée. Lancez une construction pour optimiser vos infrastructures.</p>
            <?php else: ?>
                <ul class="queue-list">
                    <?php foreach ($queue['jobs'] as $job): ?>
                        <li class="queue-list__item">
                            <div>
                                <strong><?= htmlspecialchars($job['label'] ?? $job['building']) ?></strong>
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
            <h2>Infrastructures disponibles</h2>
            <p class="panel__subtitle">Améliorez vos installations pour accélérer votre expansion.</p>
        </header>
        <div class="panel__body building-grid">
            <?php foreach ($buildings as $building): ?>
                <?php $definition = $building['definition']; ?>
                <section class="building-card <?= $building['canUpgrade'] ? '' : 'is-locked' ?>">
                    <header class="building-card__header">
                        <div class="building-card__info">
                            <h3><?= htmlspecialchars($definition->getLabel()) ?></h3>
                            <span class="badge badge--level">Niveau actuel <?= $building['level'] ?></span>
                        </div>
                        <?php if ($definition->getImage()): ?>
                            <img class="building-card__illustration" src="<?= htmlspecialchars($baseUrl . '/' . $definition->getImage()) ?>" alt="">
                        <?php endif; ?>
                    </header>
                    <div class="building-card__body">
                        <div class="building-card__costs">
                            <h4>Coût de l’amélioration</h4>
                            <ul class="resource-list">
                                <?php foreach ($building['cost'] as $resource => $amount): ?>
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
                                    <span><?= htmlspecialchars(format_duration((int) $building['time'])) ?></span>
                                </li>
                            </ul>
                        </div>
                        <div class="building-card__effects">
                            <?php $production = $building['production']; ?>
                            <?php $energy = $building['energy']; ?>
                            <div class="effect-block">
                                <?php
                                $resourceKey = $production['resource'];
                                $resourceLabels = [
                                    'metal' => 'Métal',
                                    'crystal' => 'Cristal',
                                    'hydrogen' => 'Hydrogène',
                                    'energy' => 'Énergie',
                                ];
                                $resourceLabel = $resourceLabels[$resourceKey] ?? ucfirst($resourceKey);
                                $unitSuffix = $resourceKey === 'energy' ? ' énergie/h' : ' ' . $resourceLabel . '/h';
                                $currentValue = number_format($production['current']);
                                $gainValue = number_format($production['delta']);
                                $currentPrefix = $production['current'] > 0 ? '+' : '';
                                $gainPrefix = $production['delta'] > 0 ? '+' : '';
                                ?>
                                <span class="effect-block__label">Production – <?= htmlspecialchars($resourceLabel) ?></span>
                                <strong class="effect-block__value <?= $production['current'] >= 0 ? 'is-positive' : 'is-negative' ?>"><?= $currentPrefix . $currentValue ?><?= htmlspecialchars($unitSuffix) ?></strong>
                                <span class="effect-block__hint">Gain prochain niveau : <span class="<?= $production['delta'] >= 0 ? 'is-positive' : 'is-negative' ?>"><?= $gainPrefix . $gainValue ?><?= htmlspecialchars($unitSuffix) ?></span></span>
                            </div>
                            <?php if ($energy['current'] > 0 || $energy['delta'] !== 0): ?>
                                <div class="effect-block">
                                    <span class="effect-block__label">Consommation énergétique</span>
                                    <strong class="effect-block__value is-negative">-<?= number_format($energy['current']) ?> énergie/h</strong>
                                    <?php $energyPrefix = $energy['delta'] > 0 ? '+' : ''; ?>
                                    <span class="effect-block__hint">Variation au niveau suivant : <span class="<?= $energy['delta'] >= 0 ? 'is-negative' : 'is-positive' ?>"><?= $energyPrefix ?><?= number_format($energy['delta']) ?> énergie/h</span></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!$building['requirements']['ok']): ?>
                            <div class="building-card__requirements">
                                <h4>Pré-requis</h4>
                                <ul>
                                    <?php foreach ($building['requirements']['missing'] as $missing): ?>
                                        <li><?= htmlspecialchars($missing['label']) ?> (<?= $missing['current'] ?>/<?= $missing['level'] ?>)</li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                    <footer class="building-card__footer">
                        <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/buildings?planet=<?= $planet->getId() ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_upgrade ?? '') ?>">
                            <input type="hidden" name="building" value="<?= htmlspecialchars($definition->getKey()) ?>">
                            <button class="button button--primary" type="submit" <?= $building['canUpgrade'] ? '' : 'disabled' ?>><?= $building['canUpgrade'] ? 'Améliorer' : 'Conditions non remplies' ?></button>
                        </form>
                    </footer>
                </section>
            <?php endforeach; ?>
        </div>
    </article>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/base.php';
