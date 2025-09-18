# Rapport de restructuration Génésis Reborn

## 1. Vue d’ensemble
- Migration complète vers une architecture MVC modulaire respectant PSR-4.
- Introduction d’un front controller unique (`public/index.php`) et d’un routeur interne.
- Remplacement des fonctions globales par des services et cas d’usage testables.
- Séparation claire des couches : `src/Domain`, `src/Application`, `src/Infrastructure`, `templates/`, `config/`.
- Initialisation de Composer, autoload PSR-4, scripts QA et dépendances (PHPUnit, PHPStan, PHP-CS-Fixer).
- Nouveau thème unifié et pages rendues via templates PHP.

## 2. Mapping des principaux fichiers
| Ancien emplacement | Nouveau composant |
| --- | --- |
| `includes/db.php` | `App\Infrastructure\Database\ConnectionFactory` + service `PDO` |
| `includes/auth.php` | `App\Application\UseCase\Auth\{RegisterUser,LoginUser,LogoutUser}` + `AuthController` |
| `includes/buildings_config.php` | `config/game/buildings.php` chargé par `App\Domain\Service\BuildingCatalog` |
| `includes/buildings.php` | `App\Domain\Service\BuildingCalculator`, `App\Application\UseCase\Building\{GetBuildingsOverview,UpgradeBuilding}`, `PdoBuildingStateRepository` |
| `includes/game.php` (parties ressources) | `App\Application\UseCase\Dashboard\GetDashboard`, `PdoPlanetRepository` |
| `public/dashboard.php` | `templates/pages/dashboard/index.php` via `DashboardController` |
| `public/buildings.php` | `templates/pages/buildings/index.php` via `BuildingController` |
| `resource/SQL/unknown.sql` | `migrations/20250917_initial_schema.sql` (InnoDB + FK) |

## 3. Décisions d’architecture
- **Container léger** : création de `App\Infrastructure\Container\Container` pour gérer services et paramètres.
- **Sessions & sécurité** : encapsulation de la session (`PhpSession`), flash messages (`FlashBag`) et CSRF (`CsrfTokenManager`).
- **Catalogue métier** : définitions des bâtiments, recherches et vaisseaux sous `config/game/`, matérialisées via `BuildingCatalog` et `BuildingCalculator`.
- **Contrôleurs fins** : contrôleurs orchestrent exclusivement les cas d’usage et assurent la gestion des redirections/flash.
- **Templates** : rendu PHP (layout unique + pages thématiques) et nouvelle feuille de style centralisée `public/assets/css/app.css`.
- **Qualité** : ajout de `phpunit.xml.dist`, d’un test unitaire sur le calculateur de bâtiments et scripts Composer (`test`, `cs`, `stan`).

## 4. Points d’attention & TODO
- **Files de construction différées** : la logique actuelle applique les améliorations instantanément. Réintroduire une file asynchrone (`PdoBuildQueueRepository`) et le recalcul différé des ressources.
- **Recherche & chantiers spatiaux** : les données sont conservées (`config/game/research.php`, `config/game/ships.php`) mais les cas d’usage correspondants restent à implémenter.
- **Migration données existantes** : prévoir un script SQL/ETL pour transformer les installations MyISAM historiques vers le nouveau schéma InnoDB.
- **Tests complémentaires** : ajouter des tests fonctionnels sur les contrôleurs et la couche repository (utiliser une base sqlite mémoire ou fixtures).
- **Observabilité** : intégrer un système de journalisation et un middleware d’erreurs pour l’environnement de production.

## 5. Commandes utiles
```bash
# Installation des dépendances
composer install

# Mise à jour de l’autoload
composer dump-autoload --optimize

# Lancement des tests unitaires
composer test

# Analyse statique et formatage (optionnel)
composer stan
composer cs

# Serveur de développement PHP
php -S localhost:8000 -t public
```

## 6. Instructions de rollback
```bash
git checkout work
git reset --hard HEAD~n   # n = nombre de commits appliqués pour la restructuration
```
Ou depuis la branche principale :
```bash
git checkout main
git revert <hash_commit_restructuration>
```

