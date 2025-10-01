# Étape 1 – Value Objects & Enums (Design Note)

## Objectifs
- Introduire des Value Objects (`Coordinates`, `ResourceStock`, `ResourceCost`) pour encapsuler les structures critiques actuellement manipulées sous forme de tableaux.
- Introduire des enums natives (`FleetMission`, `FleetStatus`) pour cadrer les missions/statuts acceptés par la flotte.
- Réaliser un spike limité dans `CostService` et `FleetNavigationService` afin de démontrer l’intégration progressive des nouveaux types sans casser les usages actuels.

## Décisions clés
- Les VO exposent des constructeurs contrôlés (`fromArray`, `fromInts`) avec validations simples (noms de ressources, valeurs >= 0).
- `ResourceCost` conserve la compatibilité avec les tableaux : les méthodes du `CostService` acceptent désormais un `ResourceCost` ou un tableau et retournent le même type que l’entrée, ce qui permet une adoption incrémentale.
- `FleetNavigationService` accepte indifféremment un tableau ou un `Coordinates` via une méthode de normalisation interne.
- Les enums fournissent des helpers dédiés (`isIdle`, `isActive`) et une méthode `fromString` tolérant la casse.

## Remplacements ciblés
- `src/Domain/Service/CostService.php` : calculs de coût via `ResourceCost`.
- `src/Domain/Service/FleetNavigationService.php` : normalisation des coordonnées via `Coordinates`.
- Tests associés mis à jour pour consommer les nouveaux types.

## Checklist d’acceptation
- [x] Les Value Objects valident leurs entrées et exposent un `toArray()` pour conserver la rétrocompatibilité.
- [x] `CostService` accepte/retourne `ResourceCost` tout en conservant la compatibilité avec les tableaux existants.
- [x] `FleetNavigationService` accepte un `Coordinates` ou un tableau en entrée.
- [x] Des tests unitaires couvrent les nouveaux VO/enums ainsi que le spike dans `CostService`.
- [x] Documentation ajoutée pour tracer la décision de conception.

## Commandes de vérification
- `composer test`
- `composer stan`
