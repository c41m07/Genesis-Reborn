# Genesis Reborn

> Jeu de gestion spatiale en PHP 8.2. Architecture MVC maison, gabarits PHP/HTML modulaires et front Vanilla JS. Version solo complète et prête à évoluer vers le multijoueur.

## Sommaire

- [Aperçu du jeu](#aperçu-du-jeu)
- [Fonctionnalités livrées](#fonctionnalités-livrées)
- [Architecture & pile technique](#architecture--pile-technique)
- [Modèle métier & données](#modèle-métier--données)
- [Boucles de gameplay](#boucles-de-gameplay)
- [Équilibrage & configuration YAML](#équilibrage--configuration-yaml)
- [Migrations de base de données](#migrations-de-base-de-données)
- [Design system & front-end](#design-system--front-end)
- [Qualité, CI & maintenance](#qualité-ci--maintenance)
- [Commandes utiles](#commandes-utiles)
- [Notes de conception](#notes-de-conception)
- [Roadmap](#roadmap)

---

## Aperçu du jeu

Genesis Reborn propose une expérience de gestion de colonie spatiale. Chaque joueur dispose d\'un empire composé de planètes et progresse en développant ses infrastructures, ses recherches et ses flottes. Toutes les entités sont filtrées par `player_id` pour garantir l\'isolement des données, et l\'interface fournit un tableau de bord synthétique, un chantier spatial complet ainsi qu\'un journal des événements.

---

## Fonctionnalités livrées

- **Comptes & sessions sécurisées** : inscription, connexion, déconnexion, stockage des sessions via une couche `SessionInterface` encapsulée et tokens CSRF pour chaque requête POST.
- **Colonies & infrastructures** : construction de bâtiments avec files d\'attente, calculs de coûts, gestion d\'énergie, bonus de production et jauges de ressources.
- **Recherche scientifique** : arbre technologique complet avec prérequis, files par planète et bonus liés au laboratoire de recherche.
- **Chantier spatial & flotte** : production de vaisseaux, composition de flottes et missions planifiées (exploration, attaque, transport) avec calcul des temps et de la consommation.
- **Journal & tableau de bord** : vue synthétique de la progression, points de prestige et historique des événements.
- **Carte galactique** : navigation sur les systèmes pour préparer l\'exploration et les missions.
- **Arbre de prérequis** : visualisation consolidée des dépendances bâtiments/recherches/vaisseaux.
- **Système de ressources** : production horaire, capacités de stockage, entretien et avertissements visuels lorsque les réserves chutent.
- **Pipeline front robuste** : modules JavaScript ES2022, composants PHP réutilisables, sprites SVG optimisés et design tokens accessibles.

---

## Architecture & pile technique

```
/config
  balance/*.yml (définitions bâtiments/recherches/vaisseaux)
  bootstrap.php, parameters.php, routes.php, services.php
/database/migrations (SQL incrémental)
/public
  assets/css (tokens.css, app.css)
  assets/js (app.js, modules/*)
  assets/svg (sprite.svg, icons/)
  index.php
/src
  Domain (Entities, ValueObjects, Services, interfaces de repositories)
  Application (UseCases, DTO, services de queue)
  Infrastructure (HTTP, DB, Sécurité, adaptateurs de config)
/templates
  layouts/, components/, pages/
tests (PHPUnit)
```

- **Back-end** : PHP 8.2+, architecture en couches, autoload PSR-4 via Composer.
- **Front-end** : Vanilla JS (modules ESM), CSS tokens, pipeline d'icônes SVG, Vite pour le bundling et outils de linting.
- **Tests & QA** : PHPUnit, PHPStan (niveau 6), PHP-CS-Fixer, ESLint, Stylelint, tests Node (`node --test`).

---

## Modèle métier & données

- **Joueur** : possède des planètes, recherches, files de construction et flottes. Les accès sont filtrés par `player_id` sur toutes les requêtes.
- **Planète** : stocke les niveaux de bâtiments, les files (`build_queue`, `research_queue`, `ship_build_queue`) et les ressources courantes/capacité.
- **Technologie** : progression par joueur, dépendances croisées (bâtiments, recherches) et impact sur les temps de recherche.
- **Flotte & missions** : `FleetMovement` représente chaque mission, orchestrée par le `FleetNavigationService` et persistée via un repository dédié.
- **Journal** : événements PvE, synthèse d\'activité, points de progression.
- **Équilibrage** : YAML dans `config/balance/` chargés par `BalanceConfigLoader` pour alimenter les catalogues (`BuildingCatalog`, `ResearchCatalog`, `ShipCatalog`).

---

## Boucles de gameplay

### Construction & ressources
- Calculs de coûts et de temps via `BuildingCalculator` avec multiplicateurs par niveau et bonus de vitesse.
- Production horaire gérée par `ResourceTickService`, en appliquant les effets définis dans les YAML (production, stockage, entretien).
- Jauge front (`resource-meter`) met à jour en temps réel les réserves et capacités via un ticker JS.

### Recherche & technologies
- `ResearchCatalog` regroupe les technologies par catégorie et applique les prérequis (`requires`, `requires_lab`).
- `ResearchCalculator` combine multiplicateurs de temps/coût avec les bonus de laboratoire, plafonnés pour éviter les dérives.

### Chantier spatial & flotte
- `ShipCatalog` fournit les statistiques et prérequis des vaisseaux.
- `FleetNavigationService` normalise les coordonnées (grâce au VO `Coordinates`) et calcule vitesse, consommation et ETA.
- Use cases dédiés : planification (`PlanFleetMission`), lancement (`LaunchFleetMission`), traitement des arrivées (`ProcessFleetArrivals`).
- Module JS `fleet-planner.js` gère les appels AJAX (`/fleet/plan`, `/fleet/launch`) et garde un repli sans JavaScript.

---

## Équilibrage & configuration YAML

Les fichiers YAML se trouvent dans `config/balance/` :

- **`buildings.yaml`** : coûts (`base_cost`, `growth_cost`), temps (`base_time`, `growth_time`), production, énergie, bonus (recherche, chantier, construction), stockage et entretien. Chaque entrée est normalisée en `BuildingDefinition`.
- **`research.yaml`** : catégories, prérequis (`requires`, `requires_lab`), multiplicateurs (`growth_cost`, `growth_time`), niveau maximum et illustrations. Le loader applique des images par défaut selon la catégorie.
- **`ships.yaml`** : catégories, rôles, statistiques (`attack`, `defense`, `speed`…), prérequis de recherche et temps de construction. Les catégories fournissent aussi les illustrations par défaut.

`BalanceConfigLoader` vérifie les champs, applique les valeurs par défaut et transmet les données aux catalogues pour les cas d'usage (survol, fiches, files d'attente).

Les conventions de nommage et règles de couche sont détaillées dans `docs/baseline.md` et la structure globale est décrite dans `ARCHITECTURE.md`.

---

## Migrations de base de données

Le système de migration (PHP CLI) applique les scripts SQL de manière incrémentale tout en évitant les pertes de données.

- **Table de suivi** : `migrations` stocke le nom du fichier, la date d\'application et un checksum SHA256.
- **Exécution** : `composer db:migrate` parcourt `/migrations/`, saute automatiquement les scripts déjà appliqués et rejette ceux modifiés après coup.
- **Sécurité** : les migrations destructives (ex: `schema.sql`) sont ignorées si des données existent ; toutes les opérations sont encapsulées dans des transactions.
- **Organisation** : scripts nommés `YYYYMMDDHHMM_description.sql`. Les scripts historiques destructifs restent pour référence mais ne sont plus utilisés.

---

## Design system & front-end

- **CSS tokens** (`public/assets/css/tokens.css`) définissent couleurs, espacements, typos et contrastes (WCAG AA).
- **Sprite SVG** (`public/assets/svg/sprite.svg`) généré via `npm run svgo:build`, alimenté par les fichiers placés dans `public/assets/svg/icons/`.
- **Modules JS** : `public/assets/js/app.js` initialise les comportements (compteurs de file d\'attente, planificateur de flotte, etc.) et expose `window.QueueCountdown` (`init`, `destroy`, `refresh`).
- **Accessibilité** : état `resource-meter--warning` pour signaler les réserves critiques ; composants réutilisables dans `templates/components/` pour limiter la duplication.

---

## Qualité, CI & maintenance

- **PHP** :
  - PSR-12, `declare(strict_types=1)` et imports ordonnés (PHP-CS-Fixer : `composer cs` / `composer cs:fix`).
  - Analyse statique PHPStan niveau 6 (`composer stan`).
  - Tests unitaires (`composer test`).
- **JavaScript** :
  - ESLint (`npm run lint:js`) avec règles inspirées d\'Airbnb.
  - Prettier (`npm run fmt`) pour le formatage.
  - Tests Node (`npm test`) couvrant les modules critiques (`countdown`, etc.).
- **CSS** : Stylelint (`npm run lint:css`).
- **CI** : exécute linters et tests PHP/JS, plus l\'optimisation SVG (`npm run svgo`).
- **Bonnes pratiques** : éviter les scripts inline (tout est centralisé dans les modules), déclencher `document.dispatchEvent(new Event('queue:updated'))` après une action AJAX pour rafraîchir les compteurs.

---

## Commandes utiles

```bash
# Dépendances back
composer update
composer install
composer dump-autoload --optimize

# Dépendances front
npm install

# Base de données
composer db:migrate

# Tests & qualité
composer test
composer stan
composer cs
npm run lint:js
npm run lint:css
npm test
npm run fmt

# Assets
npm run svgo:build

# Serveur de développement PHP
php -S localhost:8000 -t public
```

---

## Notes de conception

- **Value Objects & enums** : `Coordinates`, `ResourceCost`, `FleetMission`, `FleetStatus` sécurisent les entrées, conservent une compatibilité tableau et exposent des helpers (`toArray()`, `fromString()`).
- **Refonte flotte v1** : use cases `PlanFleetMission`, `LaunchFleetMission`, `ProcessFleetArrivals` orchestrent les missions, alimentés par `FleetMovementRepositoryInterface` et un module JS asynchrone avec repli serveur.
- **Qualité ciblée** : tests dédiés pour le routeur HTTP et le gestionnaire CSRF, test Node pour le module `countdown`, pipeline CI exécutant les suites PHP & JS.

---

## Roadmap

Consultez [ROADMAP.md](./ROADMAP.md) pour la feuille de route détaillée (fonctionnalités solo, multijoueur et améliorations futures).

