<?php
/** @var string $title */
/** @var string $baseUrl */
/** @var array<int, array{type: string, message: string}> $flashes */
/** @var int|null $currentUserId */
/** @var string|null $csrf_logout */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int}>}|null $activePlanetSummary */
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var string|null $activeSection */
/** @var int|null $selectedPlanetId */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Nova Empire') ?></title>
    <link rel="preload" href="<?= htmlspecialchars($baseUrl) ?>/assets/svg/sprite.svg" as="image" type="image/svg+xml">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/assets/css/tokens.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/assets/css/app.css">
</head>
<?php
$planets = $planets ?? [];
$selectedPlanetId = $selectedPlanetId ?? null;
$activeSection = $activeSection ?? null;
$activePlanetSummary = $activePlanetSummary ?? null;
$isAuthenticated = !empty($currentUserId);

$activePlanet = null;
if (is_array($activePlanetSummary) && isset($activePlanetSummary['planet']) && $activePlanetSummary['planet'] instanceof \App\Domain\Entity\Planet) {
    $activePlanet = $activePlanetSummary['planet'];
}

$activePlanetName = $activePlanet ? $activePlanet->getName() : 'Planète active';
$currentPlanetId = $selectedPlanetId ?? ($activePlanet ? $activePlanet->getId() : null);
$resourceSummary = is_array($activePlanetSummary) ? ($activePlanetSummary['resources'] ?? []) : [];

$menu = [
    'dashboard' => ['label' => 'Tableau de bord', 'path' => '/dashboard', 'icon' => 'overview'],
    'buildings' => ['label' => 'Bâtiments', 'path' => '/buildings', 'icon' => 'buildings'],
    'research' => ['label' => 'Recherche', 'path' => '/research', 'icon' => 'research'],
    'shipyard' => ['label' => 'Chantier spatial', 'path' => '/shipyard', 'icon' => 'shipyard'],
    'tech-tree' => ['label' => 'Arbre techno', 'path' => '/tech-tree', 'icon' => 'tech'],
];
$currentSectionPath = $menu[$activeSection]['path'] ?? '/dashboard';
?>
<body class="app <?= $isAuthenticated ? 'app--secured' : 'app--guest' ?>">
<div class="app-shell">
    <?php if ($isAuthenticated): ?>
        <aside class="sidebar" id="primary-sidebar" data-sidebar>
            <div class="sidebar__inner">
                <div class="sidebar__header">
                    <div class="sidebar__brand">
                        <a class="brand" href="<?= htmlspecialchars($baseUrl) ?>/dashboard">Nova Empire</a>
                        <span class="brand__tagline">Génésis Reborn</span>
                    </div>
                    <button class="sidebar__close" type="button" aria-label="Fermer la navigation" data-sidebar-close></button>
                </div>
                <nav class="sidebar__section" aria-label="Navigation principale">
                    <p class="sidebar__title">Empire</p>
                    <ul class="sidebar__nav">
                        <?php foreach ($menu as $key => $item): ?>
                            <?php $isCurrent = $activeSection === $key; ?>
                            <li class="sidebar__item <?= $isCurrent ? 'is-active' : '' ?>">
                                <a class="sidebar__link" href="<?= htmlspecialchars($baseUrl . $item['path']) ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?>>
                                    <svg class="icon icon-sm" aria-hidden="true">
                                        <use href="<?= htmlspecialchars($baseUrl) ?>/assets/svg/sprite.svg#icon-<?= htmlspecialchars($item['icon']) ?>"></use>
                                    </svg>
                                    <span><?= htmlspecialchars($item['label']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </div>
        </aside>
        <div class="sidebar-overlay" data-sidebar-overlay></div>
    <?php endif; ?>
    <div class="workspace">
        <header class="topbar <?= $isAuthenticated ? '' : 'topbar--guest' ?>">
            <?php if ($isAuthenticated): ?>
                <div class="topbar__primary">
                    <button class="topbar__menu" type="button" aria-label="Ouvrir la navigation" data-sidebar-toggle aria-controls="primary-sidebar" aria-expanded="false">
                        <span class="topbar__menu-icon" aria-hidden="true"></span>
                    </button>
                    <div class="topbar__planet">
                        <span class="topbar__label">Planète active</span>
                        <h1 class="topbar__title"><?= htmlspecialchars($activePlanetName) ?></h1>
                    </div>
                    <?php if (!empty($planets)): ?>
                        <form class="topbar__selector" method="get" action="<?= htmlspecialchars($baseUrl . $currentSectionPath) ?>">
                            <label class="visually-hidden" for="topbar-planet-select">Changer de planète</label>
                            <select id="topbar-planet-select" name="planet" data-auto-submit>
                                <?php foreach ($planets as $planetOption): ?>
                                    <option value="<?= $planetOption->getId() ?>"<?= ($planetOption->getId() === $currentPlanetId) ? ' selected' : '' ?>><?= htmlspecialchars($planetOption->getName()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="topbar__resources">
                    <?php foreach (['metal' => 'Métal', 'crystal' => 'Cristal', 'hydrogen' => 'Hydrogène', 'energy' => 'Énergie'] as $key => $label): ?>
                        <?php
                        $data = $resourceSummary[$key] ?? ['value' => 0, 'perHour' => 0];
                        $perHourValue = (int) ($data['perHour'] ?? 0);
                        $rateNumber = number_format($perHourValue);
                        $ratePrefix = ($key !== 'energy' && $perHourValue > 0) ? '+' : '';
                        $rateDisplay = $ratePrefix . $rateNumber . '/h';
                        $rateClass = $perHourValue >= 0 ? 'is-positive' : 'is-negative';
                        ?>
                        <div class="resource-meter" role="group" aria-label="<?= htmlspecialchars($label) ?>">
                            <div class="resource-meter__icon">
                                <svg class="icon icon-sm" aria-hidden="true">
                                    <use href="<?= htmlspecialchars($baseUrl) ?>/assets/svg/sprite.svg#icon-<?= htmlspecialchars($key) ?>"></use>
                                </svg>
                            </div>
                            <div class="resource-meter__details">
                                <span class="resource-meter__label"><?= htmlspecialchars($label) ?></span>
                                <div class="resource-meter__values">
                                    <span class="resource-meter__value"><?= number_format((int) ($data['value'] ?? 0)) ?></span>
                                    <span class="resource-meter__rate <?= $rateClass ?>"><?= htmlspecialchars($rateDisplay) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="topbar__actions">
                    <a class="button button--ghost" href="#">Profil</a>
                    <?php if (!empty($csrf_logout)): ?>
                        <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/logout" class="logout-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_logout ?? '') ?>">
                            <button type="submit" class="button button--ghost">
                                <svg class="icon icon-sm" aria-hidden="true">
                                    <use href="<?= htmlspecialchars($baseUrl) ?>/assets/svg/sprite.svg#icon-logout"></use>
                                </svg>
                                <span>Déconnexion</span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="guest-branding">
                    <a class="brand" href="<?= htmlspecialchars($baseUrl) ?>/">Nova Empire</a>
                    <nav class="guest-nav" aria-label="Accès invité">
                        <a href="<?= htmlspecialchars($baseUrl) ?>/login">Connexion</a>
                        <a href="<?= htmlspecialchars($baseUrl) ?>/register">Inscription</a>
                    </nav>
                </div>
            <?php endif; ?>
        </header>
        <main class="workspace__content">
            <?php if (!empty($flashes)): ?>
                <div class="flashes">
                    <?php foreach ($flashes as $flash): ?>
                        <div class="flash flash--<?= htmlspecialchars($flash['type']) ?>">
                            <?= htmlspecialchars($flash['message']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?= $content ?? '' ?>
        </main>
        <footer class="footer">
            <small>&copy; <?= date('Y') ?> Nova Empire – Génésis Reborn</small>
        </footer>
    </div>
</div>
<script src="<?= htmlspecialchars($baseUrl) ?>/assets/js/app.js" defer></script>
</body>
</html>
