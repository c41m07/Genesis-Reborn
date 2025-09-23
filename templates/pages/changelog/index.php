<?php
ob_start();
?>
    <section class="page-header">
        <div>
            <h1><?= htmlspecialchars($title ?? 'Journal des versions') ?></h1>
            <p class="page-header__subtitle">
                Consultez l’historique des versions et des évolutions du projet.
            </p>
        </div>
    </section>

    <section class="tech-tree" data-base-url="<?= htmlspecialchars($baseUrl ?? '') ?>">
        <div class="tech-tree__layout">
            <!-- Barre latérale : liste des versions -->
            <aside class="tech-tree__sidebar">
                <?php if (!empty($changelog)): ?>
                    <?php foreach ($changelog as $entry): ?>
                        <?php
                        $version = $entry['version'] ?? '';
                        $date    = $entry['date'] ?? '';
                        $changes = $entry['changes'] ?? [];
                        ?>
                        <details class="tech-section tech-section--group">
                            <summary class="tech-section__summary">
                            <span class="tech-section__title" role="heading" aria-level="2">
                                Version <?= htmlspecialchars($version) ?>
                            </span>
                                <span class="tech-section__icon" aria-hidden="true"></span>
                            </summary>
                            <ul class="tech-section__list tech-section__list--nested">
                                <?php foreach ($changes as $change): ?>
                                    <li><?= htmlspecialchars($change) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Aucune version disponible.</p>
                <?php endif; ?>
            </aside>

            <!-- Panneau de détails: message par défaut ou contenu dynamique -->
            <section class="tech-tree__details" id="tech-tree-detail">
                <p>Sélectionnez une version pour afficher ses détails.</p>
            </section>
        </div>
    </section>

<?php
// On récupère le contenu généré et on l’assigne à la variable $content.
$content = ob_get_clean();

// Enfin, on inclut le layout global qui ajoutera le header, la sideboard, etc.
require __DIR__ . '/../../layouts/base.php';
