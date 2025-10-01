# Étape 2 – Fleet v1 (hors combat)

## Design note
- **Objectif** : déléguer la planification et le lancement des missions de flotte à des cas d’usage dédiés, exposés via deux points d’entrée JSON (`/fleet/plan` et `/fleet/launch`) tout en conservant un repli serveur sur la page `/fleet`.
- **Modèle** : introduction d’une entité de domaine `FleetMovement` représentant une mission programmée et d’un repository `FleetMovementRepositoryInterface` pour manipuler les lignes de la table `fleets` associées aux déplacements (création, suivi et complétion).
- **Traitement** :
  - `PlanFleetMission` valide propriétaire, installations et disponibilité des vaisseaux avant de déléguer le calcul à `FleetNavigationService`.
  - `LaunchFleetMission` réutilise la planification, persiste la mission via `FleetMovementRepositoryInterface` et déclenche la déduction des vaisseaux de la garnison.
  - `ProcessFleetArrivals` identifie les missions arrivées et restitue automatiquement les vaisseaux à la garnison (statut `completed`).
- **Interface** : la page `/fleet` consomme désormais les use cases, affiche les missions actives et expose les jetons CSRF pour les appels JSON. Un module JS ESM (`fleet-planner.js`) orchestre les appels AJAX et met à jour l’UI sans rechargement complet.

## Acceptance checklist
- [ ] Les use cases `PlanFleetMission`, `LaunchFleetMission` et `ProcessFleetArrivals` couvrent les validations essentielles (propriété planète, chantier spatial, flotte disponible) et retournent des structures sérialisables.
- [ ] `FleetMovementRepositoryInterface` et son implémentation PDO créent, listent et clôturent les missions en garantissant la cohérence des tables `fleets` et `fleet_ships`.
- [ ] `FleetMissionController` expose `/fleet/plan` et `/fleet/launch` avec gestion CSRF et réponses JSON cohérentes (succès + erreurs).
- [ ] La page `/fleet` reste fonctionnelle sans JavaScript (fallback serveur) tout en supportant la planification/lancement asynchrones.
- [ ] Couverture de tests augmentée : cas d’usage (plan + launch) et contrôleur JSON.

## Commandes CI recommandées
- `composer test`
- `composer stan`
- `composer cs`
- `npm run lint`
- `npm run build`
