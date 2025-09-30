# Maintenance & normes

Ce dépôt applique des standards cohérents entre le PHP, le JavaScript et le CSS. Ce mémo rassemble les outils à utiliser
et les commandes à exécuter avant chaque livraison.

## Qualité PHP

- **PSR-12** partout, strict_types et imports ordonnés via **PHP-CS-Fixer** (`composer cs:fix`).
- Analyse statique avec **PHPStan niveau 6** (`composer stan`). La configuration désactive `treatPhpDocTypesAsCertain`
  pour tolérer les structures documentées, tout en imposant des types de valeur explicites dans les itérables.
- Tests/unitaires disponibles via `composer test`.

## Qualité JavaScript

- Code ES2022 en modules ESM (import/export) centralisés sous `public/assets/js/`.
- Linting **ESLint** (config `tools/run-eslint.cjs`, règles type Airbnb): `npm run lint:js`.
- Formatage **Prettier** (`npm run fmt`) appliqué à tout le JS/CSS.
- Initialisation unique depuis `public/assets/js/app.js` exposant l’API `window.QueueCountdown` (`init`, `destroy`,
  `refresh`).

## Qualité CSS

- **Stylelint** (config standard) garantit la cohérence des tokens et de la nomenclature (`npm run lint:css`).
- Les styles résident dans `public/assets/css/` (plus d’inline).

## Chaîne de commandes

```bash
# PHP
composer update   
composer install
composer cs        # vérifie le formatage
composer cs:fix    # corrige le formatage
composer stan      # analyse statique
composer test      # suite de tests

# Front
npm ci             # installe les dépendances
npm run lint:js    # ESLint
npm run lint:css   # Stylelint
npm run fmt        # Vérifie le formatage Prettier
npm run svgo       # Optimise les SVG

# Dev server
php -S localhost:8000 -t public
```

## Scripts inline supprimés

Les anciens scripts de compte à rebours ont été extraits vers `public/assets/js/modules/countdown.js`. Ils étaient
précédemment déclarés en bas de ces templates:

| Fichier                              | Lignes supprimées                              | Remplacement                                                                      |
|--------------------------------------|------------------------------------------------|-----------------------------------------------------------------------------------|
| `templates/pages/colony/index.php`   | ~421-462 (`<script>…initCountdowns…</script>`) | Initialisation via `window.QueueCountdown.init()` dans `public/assets/js/app.js`. |
| `templates/pages/research/index.php` | ~228-268 (même bloc `initCountdowns`)          | Module `countdown.js` + écoute `queue:updated`.                                   |
| `templates/pages/shipyard/index.php` | ~242-283 (même bloc `initCountdowns`)          | Module `countdown.js` + rafraîchissement via évènements.                          |

## Rafraîchissement des files d’attente

- Après toute action AJAX, déclencher `document.dispatchEvent(new Event('queue:updated'))` pour relancer le compte à
  rebours sans dupliquer les intervalles.
- Les modules front exposent des helpers (`modules/dom.js`, `modules/events.js`, `modules/time.js`) pour éviter la
  duplication de logique.

En appliquant systématiquement ces commandes et conventions, on garantit un dépôt homogène et facile à maintenir.
