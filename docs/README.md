# Genesis Reborn – README Global

> Jeu de gestion spatiale — version **Solo** déjà pensée pour évoluer en **Multijoueur**.  
> Projet en PHP 8.2 avec gabarits HTML/CSS/JS modulaires, architecture MVC, et design system réutilisable.

---

## Sommaire
- [Aperçu du projet](#aperçu-du-projet)
- [Fonctionnalités principales](#fonctionnalités-principales)
- [Architecture & organisation](#architecture--organisation)
- [Modèles & données](#modèles--données)
- [Services métier](#services-métier)
- [Endpoints & navigation](#endpoints--navigation)
- [Design system & assets](#design-system--assets)
- [Sessions & sécurité](#sessions--sécurité)
- [Installation & commandes](#installation--commandes)
- [Roadmap & évolutions multi](#roadmap--évolutions-multi)
- [Bugs connus & TODO](#bugs-connus--todo)
- [Qualité & tests](#qualité--tests)
- [Licence](#licence)

---

## Aperçu du projet
- Version **Solo** opérationnelle : toutes les entités portent un `player_id` et sont filtrées par joueur connecté.
- Architecture **MVC refondue** : front controller unique, routeur interne, séparation Domain/Application/Infrastructure/Templates/Config.
- Composer + PSR-4, tests unitaires, analyse statique, outils QA intégrés.
- Front basé sur gabarits HTML/CSS, Vanilla JS (ESM) et pipeline d’icônes SVG.

---

## Fonctionnalités principales
- **Comptes & authentification** : inscription, connexion, déconnexion, CSRF sur POST.
- **Colonies & bâtiments** : construction, file d’attente, coûts, production, calcul d’énergie.
- **Recherches** : catalogue, prérequis, files par planète.
- **Chantier spatial & flottes** : construction navale, planification de missions, gestion carburant/ETA, résolutions PvE.
- **Journal & tableau de bord** : suivi des événements et synthèse de progression.

---

## Architecture & organisation
```
/config
  /game (buildings.php, research.php, ships.php)
  /migrations
/public
  /assets/css (tokens.css, app.css)
  /assets/svg (sprite.svg, icons/)
  index.php
/src
  /Domain (Entity, Service, ValueObjects)
  /Application (UseCase, Services Process*)
  /Infrastructure (HTTP, DB, Container, Security)
/templates (layouts + pages)
tests (PHPUnit)
```

---

## Modèles & données
- **Joueur** : unique par email/username, propriétaire de planètes, files, flottes.
- **Planètes** : niveaux de bâtiments, file `build_queue`, rendement/énergie recalculés.
- **Technologies** : niveaux par joueur, file `research_queue`.
- **Vaisseaux & flottes** : production par `ship_build_queue`, missions (exploration, attaque, transport).
- **Journal** : événements PvE et synthèses multi-joueurs.

---

## Services métier
- Catalogues : `BuildingCatalog`, `ResearchCatalog`, `ShipCatalog`.
- Calculs : `BuildingCalculator`, `ResearchCalculator`, `CostService`.
- Files & ticks : `ResourceTickService`, `ProcessBuildQueue`, `ProcessResearchQueue`, `ProcessShipBuildQueue`.
- Flottes : `FleetNavigationService` (planification des missions).

---

## Endpoints & navigation
- **Auth** : `/`, `/login`, `/register`, `/logout`
- **Dashboard** : `/dashboard?planet=`
- **Colonies** : `/colony` (upgrade bâtiments)
- **Recherches** : `/research`
- **Chantier spatial** : `/shipyard`
- **Flottes** : `/fleet`
- **Journal** : `/journal`

> Tous les POST sont protégés par **CSRF token**.

---

## Design system & assets
- **Tokens CSS** (`public/assets/css/tokens.css`) : couleurs, espacements, typographie, contrastes (WCAG AA).
- **Sprite d’icônes** (`public/assets/svg/sprite.svg`) via `<use href="/assets/svg/sprite.svg#icon-...">`.
- **Pipeline icônes** : ajouter des SVG dans `public/assets/svg/icons/` puis exécuter `npm run svgo:build`.

---

## Sessions & sécurité
- Gestion via `App\Infrastructure\Http\Session\Session`.
- Méthodes : `get/set/has/remove`, `flash`, `pull`.
- Accessible via injection `SessionInterface` (ne pas utiliser `$_SESSION` directement).

---

## Installation & commandes
```bash
# Dépendances
composer install
composer dump-autoload --optimize
npm install

# Base de données
composer db:create

# QA & tests
composer test
composer stan
composer cs

# Dev server
php -S localhost:8000 -t public

# Icônes
npm run svgo:build
```

---

## Roadmap & évolutions multi
1. Fiabiliser ticks (production/recherche/chantier).
2. Classements globaux & API publique.
3. Commerce inter-joueurs.
4. PvP basique (attaques, événements bilatéraux).
5. Alliances et diplomatie.
6. Scalabilité : workers/cron, shard des queues, observabilité.

---

## Bugs connus & TODO
- **Files d’attente** :
    - Construction & recherches doivent être **séquentielles** (5 max).
    - Les niveaux doivent s’enchaîner correctement (mine lvl1 → lvl2).

---

## Qualité & tests
- **Tests unitaires** via PHPUnit (sessions, calculs bâtiments).
- **Analyse statique** : `composer stan` (PHPStan).
- **Normes de code** : `composer cs` (PHP-CS-Fixer).
- **Rollback** : `git reset --hard HEAD~n` ou `git revert <hash>`.

---

## Licence
Projet éducatif en cours de développement.  
Assets tiers sous licence respective.  
