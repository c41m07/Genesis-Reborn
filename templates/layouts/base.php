<?php
/** @var string $title */
/** @var string $baseUrl */
/** @var array<int, array{type: string, message: string}> $flashes */
/** @var int|null $currentUserId */
/** @var string|null $csrf_logout */
/** @var array{planet: \App\Domain\Entity\Planet, resources: array<string, array{value: int, perHour: int, capacity: int}>}|null $activePlanetSummary */
/** @var array<int, \App\Domain\Entity\Planet> $planets */
/** @var string|null $activeSection */
/** @var int|null $selectedPlanetId */

require_once __DIR__ . '/../components/helpers.php';

$baseUrl = $baseUrl ?? '';
$assetBase = rtrim($baseUrl, '/');
$asset = static fn (string $path): string => asset_url($path, $assetBase);
$spriteHref = $asset('assets/svg/sprite.svg');
$spriteIcon = static fn (string $name): string => $asset('assets/svg/sprite.svg#icon-' . $name);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Genesis Reborn') ?></title>
    <link rel="preload" href="<?= htmlspecialchars($spriteHref, ENT_QUOTES) ?>" as="image" type="image/svg+xml">
    <link rel="stylesheet" href="<?= htmlspecialchars($asset('assets/css/tokens.css'), ENT_QUOTES) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($asset('assets/css/app.css'), ENT_QUOTES) ?>">
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
$resourceEndpoint = $assetBase . '/api/resources';
$resourcePlanetId = $currentPlanetId ?? 0;

$facilityStatuses = $facilityStatuses ?? [];
$menuCategories = [
    [
        'label' => 'Empire',
        'items' => [
            'dashboard' => ['label' => 'Vue impériale', 'path' => '/dashboard', 'icon' => 'overview'],
        ],
    ],
    [
        'label' => 'Planètes',
        'items' => [
            'colony' => ['label' => 'Bâtiments', 'path' => '/colony', 'icon' => 'planet'],
            'research' => ['label' => 'Labo de recherche', 'path' => '/research', 'icon' => 'research'],
            'shipyard' => ['label' => 'Chantier spatial', 'path' => '/shipyard', 'icon' => 'shipyard'],
        ],
    ],
    [
        'label' => 'Gestion planétaire',
        'items' => [
            'fleet' => ['label' => 'Flotte', 'path' => '/fleet', 'icon' => 'shipyard'],
        ],
    ],
    [
        'label' => 'Autre',
        'items' => [
            'galaxy' => ['label' => 'Carte galaxie', 'path' => '/galaxy', 'icon' => 'planet'],
            'tech-tree' => ['label' => 'Arbre techno', 'path' => '/tech-tree', 'icon' => 'tech'],
            'journal' => ['label' => 'Journal', 'path' => '/journal', 'icon' => 'tech'],
            'profile' => ['label' => 'Profil', 'path' => '/profile', 'icon' => 'overview'],
            'changelog' => ['label' => 'Changelog', 'path' => '/changelog', 'icon' => 'tech'],
        ],
    ],
];
$menuLookup = [];
foreach ($menuCategories as $category) {
    foreach ($category['items'] as $key => $item) {
        $menuLookup[$key] = $item;
    }
}
$currentSectionPath = $menuLookup[$activeSection]['path'] ?? '/dashboard';
?>
<body class="app <?= $isAuthenticated ? 'app--secured' : 'app--guest' ?>">
<div class="app-shell">
    <?php if ($isAuthenticated): ?>
        <aside class="sidebar" id="primary-sidebar" data-sidebar>
            <div class="sidebar__inner">
                <div class="sidebar__header">
                    <div class="sidebar__brand">
                        <a class="brand" href="<?= htmlspecialchars($assetBase) ?>/dashboard">Genesis Reborn</a>
                        <span class="brand__tagline">Nouvelle ère galactique</span>
                    </div>
                    <button class="sidebar__close" type="button" aria-label="Fermer la navigation" data-sidebar-close></button>
                </div>
                <?php foreach ($menuCategories as $section): ?>
                    <nav class="sidebar__section" aria-label="<?= htmlspecialchars($section['label']) ?>">
                        <p class="sidebar__title"><?= htmlspecialchars($section['label']) ?></p>
                        <ul class="sidebar__nav">
                            <?php foreach ($section['items'] as $key => $item): ?>
                                <?php $isCurrent = $activeSection === $key; ?>
                                <?php
                                $isLocked = false;
                                if ($key === 'research' && array_key_exists('research_lab', $facilityStatuses)) {
                                    $isLocked = !($facilityStatuses['research_lab'] ?? false);
                                }
                                if (in_array($key, ['shipyard', 'fleet'], true) && array_key_exists('shipyard', $facilityStatuses)) {
                                    $isLocked = !($facilityStatuses['shipyard'] ?? false);
                                }
                                ?>
                                <?php
                                $tag = $isLocked ? 'span' : 'a';
                                $linkClass = 'sidebar__link' . ($isLocked ? ' sidebar__link--disabled' : '');
                                $linkAttributes = ' class="' . htmlspecialchars($linkClass, ENT_QUOTES) . '"';
                                if ($isLocked) {
                                    $linkAttributes .= ' role="link" aria-disabled="true" tabindex="-1"';
                                } else {
                                    $linkAttributes .= ' href="' . htmlspecialchars($assetBase . $item['path']) . '"';
                                    if ($isCurrent) {
                                        $linkAttributes .= ' aria-current="page"';
                                    }
                                }
                                ?>
                                <li class="sidebar__item <?= $isCurrent ? 'is-active' : '' ?>">
                                    <<?= $tag . $linkAttributes ?>>
                                        <svg class="icon icon-sm" aria-hidden="true">
                                            <use href="<?= htmlspecialchars($spriteIcon($item['icon']), ENT_QUOTES) ?>"></use>
                                        </svg>
                                        <span><?= htmlspecialchars($item['label']) ?></span>
                                        <?php if ($isLocked): ?>
                                            <span class="sidebar__status sidebar__status--locked" aria-hidden="true"></span>
                                            <span class="visually-hidden"> (installation indisponible)</span>
                                        <?php endif; ?>
                                    </<?= $tag ?>>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                <?php endforeach; ?>
            </div>
        </aside>
        <div class="sidebar-overlay" data-sidebar-overlay></div>
    <?php endif; ?>
    <div class="workspace">
        <header class="topbar <?= $isAuthenticated ? '' : 'topbar--guest' ?>"<?= $isAuthenticated ? ' data-resource-endpoint="' . htmlspecialchars($resourceEndpoint) . '" data-planet-id="' . (int) $resourcePlanetId . '" data-resource-poll="15000"' : '' ?>>
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
                        <form class="topbar__selector" method="get" action="<?= htmlspecialchars($assetBase . $currentSectionPath) ?>">
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
                        $data = $resourceSummary[$key] ?? ['value' => 0, 'perHour' => 0, 'capacity' => 0];
                        $value = max(0, (int) ($data['value'] ?? 0));
                        $capacityValue = max(0, (int) ($data['capacity'] ?? 0));
                        $perHourValue = (int) ($data['perHour'] ?? 0);
                        $rateNumber = format_number($perHourValue);
                        $ratePrefix = ($key !== 'energy' && $perHourValue > 0) ? '+' : '';
                        $rateDisplay = $ratePrefix . $rateNumber . '/h';
                        $rateClass = $perHourValue >= 0 ? 'is-positive' : 'is-negative';
                        $capacityDisplay = $capacityValue > 0 ? format_number($capacityValue) : '—';
                        $meterClasses = 'resource-meter' . (($value <= 0 && $perHourValue < 0) ? ' resource-meter--warning' : '');
                        ?>
                        <div class="<?= $meterClasses ?>" role="group" aria-label="<?= htmlspecialchars($label) ?>" data-resource="<?= htmlspecialchars($key) ?>" data-resource-capacity="<?= $capacityValue ?>">
                            <div class="resource-meter__icon">
                                <svg class="icon icon-sm" aria-hidden="true">
                                    <use href="<?= htmlspecialchars($spriteIcon($key), ENT_QUOTES) ?>"></use>
                                </svg>
                            </div>
                            <div class="resource-meter__details">
                                <span class="resource-meter__label"><?= htmlspecialchars($label) ?></span>
                                <div class="resource-meter__values">
                                    <div class="resource-meter__primary">
                                        <span class="resource-meter__value" data-resource-value><?= format_number($value) ?></span>
                                        <span class="resource-meter__rate <?= $rateClass ?>" data-resource-rate><?= htmlspecialchars($rateDisplay) ?></span>
                                    </div>
                                    <span class="resource-meter__capacity" data-resource-capacity-display>/ <?= htmlspecialchars($capacityDisplay) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="topbar__actions">
                    <a class="button button--ghost" href="<?= htmlspecialchars($assetBase) ?>/profile">Profil</a>
                    <?php if (!empty($csrf_logout)): ?>
                        <form method="post" action="<?= htmlspecialchars($assetBase) ?>/logout" class="logout-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_logout ?? '') ?>">
                            <button type="submit" class="button button--ghost">
                                <svg class="icon icon-sm" aria-hidden="true">
                                    <use href="<?= htmlspecialchars($spriteIcon('logout'), ENT_QUOTES) ?>"></use>
                                </svg>
                                <span>Déconnexion</span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="guest-branding">
                    <a class="brand" href="<?= htmlspecialchars($assetBase) ?>/">Genesis Reborn</a>
                    <nav class="guest-nav" aria-label="Accès invité">
                        <a href="<?= htmlspecialchars($assetBase) ?>/login">Connexion</a>
                        <a href="<?= htmlspecialchars($assetBase) ?>/register">Inscription</a>
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
            <small>&copy; <?= date('Y') ?> Genesis Reborn – Nouvelle ère galactique</small>
        </footer>
    </div>
</div>
<script type="module" src="<?= htmlspecialchars($asset('assets/js/app.js'), ENT_QUOTES) ?>"></script>
</body>
</html>
