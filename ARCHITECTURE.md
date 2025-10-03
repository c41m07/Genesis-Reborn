# Architecture

## Vue d'ensemble

Genesis Reborn applique une architecture hexagonale légère articulée autour de quatre couches :

- **Domain** – modèles métiers, services déterministes, valeur-objets et ports (interfaces de repositories). Aucun accès I/O n'est réalisé ici.
- **Application** – cas d'usage orchestrant les ports Domain et gérant les transactions/finalisation de files. Chaque service expose une méthode publique claire (ex. `process(int $planetId)`), reçoit ses dépendances via injection et renvoie des DTO ou des booléens typés.
- **Infrastructure** – implémentations concrètes (PDO, chargeurs YAML, sécurité HTTP, sessions, injection de dépendances). Cette couche fournit les adaptateurs pour les ports Domain/Application.
- **UI** – contrôleurs HTTP, templates PHP et modules front-end. Les contrôleurs consomment exclusivement les services de la couche Application et transmettent des données prêtes à afficher aux vues.

La dépendance est dirigée vers l'intérieur : UI → Application → Domain, tandis que Infrastructure est instanciée par le conteneur et injectée là où nécessaire.

## Flux HTTP

1. `public/index.php` route la requête via le noyau maison configuré dans `config/bootstrap.php`.
2. Un contrôleur `App\Controller\*` est résolu par le conteneur (`config/services.php`) et invoque un cas d'usage de la couche Application.
3. Le cas d'usage dialogue avec les repositories Domain (interfaces) implémentés en Infrastructure (`App\Infrastructure\Persistence\*`).
4. Les résultats sont transformés en DTO ou tableaux sérialisables et transmis au template PHP (`templates/pages/...`).
5. Les assets front (Vite, CSS tokens, modules JS) se chargent d'enrichir l'expérience sans rompre le mode dégradé.

## Gestion de la configuration et des données

- **Configuration métier** : fichiers YAML dans `config/balance/` chargés par `BalanceConfigLoader`. Les valeurs sont hydratées en définitions immuables (`BuildingDefinition`, `TechnologyDefinition`, `ShipDefinition`).
- **Paramétrage applicatif** : paramètres génériques dans `config/parameters.php` et services déclarés dans `config/services.php`. Les routes HTTP sont définies dans `config/routes.php`.
- **Base de données** : migrations SQL dans `database/migrations/` (exécutées via `composer db:migrate`) et schéma de référence `schema.sql` pour bootstrap local.

## Couche Application

Chaque service Application est centré sur une action métier précise et doit :

- dépendre uniquement d'interfaces Domain ou d'autres services Application faiblement couplés ;
- encapsuler la validation métier (exceptions dédiées) et ne pas émettre de SQL ou d'HTTP ;
- exposer des DTO minimalistes lorsque plusieurs valeurs de sortie sont nécessaires.

`QueueFinalizer` illustre la mutualisation des comportements transverses (mise à jour des timestamps, dispatch d'événements) pour les services de file d'attente.

## Contrats Domain ↔ Infrastructure

Les interfaces de repository résident dans `App\Domain\Repository`. Les implémentations Infrastructure (par exemple `App\Infrastructure\Persistence\PlanetRepository`) sont enregistrées dans le conteneur. Les services Domain reçoivent ces interfaces en constructeur, facilitant les tests via doubles.

## Front-end

- **Bundler** : Vite (`npm run build`) produit les assets versionnés dans `public/dist/`.
- **Design tokens** : `public/assets/css/tokens.css` centralise couleurs, typos et espacements ; les composants consomment les variables CSS.
- **Modules** : `public/assets/js/app.js` importe les modules nécessaires (`queue-countdown`, `fleet-planner`, etc.) ; aucun module orphelin ne subsiste.

## Qualité et observabilité

- **Tests** : PHPUnit (`composer test`) couvre Domain/Application, et des tests d'intégration valident les endpoints critiques.
- **Analyse statique** : PHPStan niveau 6 (`composer stan`). Les erreurs doivent être traitées immédiatement, aucune baseline ignorée.
- **Formatage** : PHP-CS-Fixer (`composer cs`) et linters front (`npm run lint`).
- **Audit** : les rapports d'audit sont conservés sous `var/audit/` pour tracer les décisions (report initial, inventaire final).

## Décisions clés

Les décisions structurantes sont documentées dans `docs/adr/`. Par exemple, l'ADR `0001-remove-unused-fleet-battle-module.md` consigne la suppression du module de résolution de batailles. Toute décision impactant les dépendances ou la persistance doit être accompagnée d'un nouvel ADR.
