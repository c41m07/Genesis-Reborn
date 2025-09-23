<?php
/** @var string|null $title Titre éventuel passé à la vue */
/** @var string|null $baseUrl URL de base pour les liens */


$title   = $title ?? 'Journal des versions';
$baseUrl = $baseUrl ?? '';
$changelog = [];

// Chemin vers le fichier JSON contenant le journal des versions.
$changelogPath = __DIR__ . '/../../../public/data/changelog.json';
if (is_readable($changelogPath)) {
    $jsonContent = file_get_contents($changelogPath);
    $decoded     = json_decode($jsonContent, true);
    if (is_array($decoded)) {
        $changelog = $decoded;
    }
}

// Section active du menu pour mettre en surbrillance "Changelog" dans la sidebar.
$activeSection = $activeSection ?? 'changelog';

ob_start();
?>
    <section class="page-header">
        <div>
            <h1><?= htmlspecialchars($title, ENT_QUOTES) ?></h1>
            <p class="page-header__subtitle">
                Consultez l’historique des versions et des évolutions du projet.
            </p>
        </div>
    </section>

    <section class="tech-tree" data-base-url="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>">
        <div class="tech-tree col-12">
            <!-- Barre latérale: liste des versions -->
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
                                Version <?= htmlspecialchars($version, ENT_QUOTES) ?>
                                <?php if ($date): ?>
                                    — <small><?= htmlspecialchars($date, ENT_QUOTES) ?></small>
                                <?php endif; ?>
                            </span>
                                <span class="tech-section__icon" aria-hidden="true"></span>
                            </summary>
                            <ul class="tech-section__list tech-section__list--nested">
                                <?php foreach ($changes as $change): ?>
                                    <li class ="tech-node-link tech-node-link"><?= htmlspecialchars($change,
                                                ENT_QUOTES) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Aucune version disponible.</p>
                <?php endif; ?>
            </aside>
        </div>
    </section>
<?php
// On récupère le contenu généré et on l’assigne à la variable $content.
$content = ob_get_clean();

// Enfin, on inclut le layout global qui ajoutera le header, la sidebar, etc.
require __DIR__ . '/../../layouts/base.php';
