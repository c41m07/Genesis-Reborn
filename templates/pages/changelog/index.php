<?php
/** @var string|null $title Titre éventuel passé à la vue */
/** @var string|null $baseUrl URL de base pour les liens */


$title = $title ?? 'Journal des versions';
$baseUrl = $baseUrl ?? '';
$changelog = is_array($changelog ?? null) ? $changelog : [];

// Section active du menu pour mettre en surbrillance "Changelog" dans la sidebar.
$activeSection = $activeSection ?? 'changelog';

ob_start();
?>
<header class="ui-page-header">
    <div class="ui-page-header__titles">
        <h1 class="ui-page-header__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h1>
        <p class="ui-page-header__subtitle">Consultez l’historique des versions et des évolutions du projet.</p>
    </div>
</header>

<article class="ui-card changelog-card">
    <header class="ui-card__header">
        <div class="ui-card__meta">
            <h2 class="ui-card__title">Historique des mises à jour</h2>
            <p class="ui-card__subtitle">Parcourez les versions pour connaître les ajouts et correctifs.</p>
        </div>
    </header>
    <div class="ui-card__body changelog-card__body">
        <?php if (!empty($changelog)): ?>
            <?php foreach ($changelog as $entry): ?>
                <?php
                $version = $entry['version'] ?? '';
                $date = $entry['date'] ?? '';
                $changes = $entry['changes'] ?? [];
                ?>
                <details class="tech-section tech-section--group changelog-entry">
                    <summary class="tech-section__summary">
                        <span class="tech-section__title" role="heading" aria-level="2">
                            Version <?= htmlspecialchars($version, ENT_QUOTES) ?>
                            <?php if ($date): ?>
                                — <small><?= htmlspecialchars($date, ENT_QUOTES) ?></small>
                            <?php endif; ?>
                        </span>
                        <span class="tech-section__icon" aria-hidden="true"></span>
                    </summary>
                    <?php if (!empty($changes)): ?>
                        <ul class="tech-section__list tech-section__list--nested">
                            <?php foreach ($changes as $change): ?>
                                <li><?= htmlspecialchars($change, ENT_QUOTES) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="changelog-entry__empty">Aucun détail supplémentaire pour cette version.</p>
                    <?php endif; ?>
                </details>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="changelog-card__empty">Aucune version disponible.</p>
        <?php endif; ?>
    </div>
</article>
<?php
// On récupère le contenu généré et on l’assigne à la variable $content.
$content = ob_get_clean();

// Enfin, on inclut le layout global qui ajoutera le header, la sidebar, etc.
require __DIR__ . '/../../layouts/base.php';
