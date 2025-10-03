# Baseline post-nettoyage

Ce document fixe les conventions et garanties minimales après l'exécution du plan de nettoyage Genesis Reborn.

## Structure de projet

- `src/Domain` : modèles métiers, valeur-objets, services déterministes, interfaces de repository.
- `src/Application` : cas d'usage, orchestrateurs et services transverses (`QueueFinalizer`). Pas de SQL ni de dépendance UI.
- `src/Infrastructure` : adaptateurs (PDO, YAML, HTTP, sécurité). Toute dépendance externe réside ici.
- `templates/` : layouts, composants et pages PHP. Chaque vue reçoit des données préparées par les contrôleurs.
- `public/` : point d'entrée (`index.php`), assets statiques, bundle Vite.
- `config/` : paramètres d'application, services du conteneur, routes, fichiers d'équilibrage (`config/balance/*.yml`).
- `docs/adr/` : Architecture Decision Records, numérotés séquentiellement.
- `var/audit/` : rapports d'audit (report initial, inventaire final, exports QA).

## Nommage & PSR-4

- Espaces de noms alignés sur l'arborescence (`App\Domain\`, `App\Application\`, `App\Infrastructure\`, `App\Controller\`).
- Classes PHP en PascalCase, fichiers homonymes (`QueueFinalizer` → `QueueFinalizer.php`).
- Templates et partials en snake_case (`templates/components/_resource_bar.php`).
- Assets statiques en kebab-case (`queue-countdown.js`, `resource-meter.css`).
- Utiliser `declare(strict_types=1);` en tête de fichier PHP.

## Exceptions & erreurs

- Les exceptions métier héritent de `DomainException` ou d'une hiérarchie dédiée et sont lancées depuis Domain/Application.
- Les erreurs techniques (PDO, HTTP) sont encapsulées dans Infrastructure et converties en réponses contrôlées.
- Toute méthode publique retournant `null` doit documenter explicitement le cas via PHPDoc.
- Les logs sensibles utilisent `#[\SensitiveParameter]` lorsque pertinent.

## Règles de couche

- UI n'accède pas directement aux repositories : les contrôleurs consomment uniquement les services Application.
- Application dépend des ports Domain (interfaces) et peut orchestrer plusieurs services Domain.
- Domain est exempt de toute dépendance Infrastructure ou UI ; seules les interfaces vivent dans Domain.
- Les adaptateurs Infrastructure implémentent explicitement les interfaces Domain et sont enregistrés dans `config/services.php`.

## Qualité & CI

- `composer test` doit être vert (PHPUnit 10.5+).
- `composer stan` doit être sans erreur (PHPStan niveau 6) sans ignorer non justifié.
- `composer cs` doit passer avant tout merge (PSR-12, `declare(strict_types=1)` obligatoire).
- Front-end : `npm run lint`, `npm test` et `npm run build` exécutés en CI (`.github/workflows/ci.yml`).
- Les nouvelles dépendances doivent être verrouillées en PHP 8.2+ (`composer.json`).

## Processus de changement

- Chaque décision structurante est documentée via un ADR (`docs/adr/00NN-*.md`).
- Avant toute suppression d'artefact, vérifier via `rg`, coverage PHPUnit et inspection CI.
- Commits atomiques avec message explicite (`chore/remove-legacy-queue-finalization`).
- Tag légers `cleanup-lot-N` disponibles pour rollback rapide.

## Documentation & audits

- Maintenir `ARCHITECTURE.md` aligné sur les couches effectives.
- Mettre à jour `README.md` après chaque ajout de module majeur.
- Conserver sous `var/audit/code/` les exports `report.md`, `final-inventory.md`, et les rapports d'analyse (`phpstan-report.json`, `csfixer.json`, `coverage.cov`).
