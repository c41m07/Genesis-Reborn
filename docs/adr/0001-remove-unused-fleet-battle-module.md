# ADR 0001: Retirer le module de résolution de batailles de flotte

## Contexte

L’audit de l’étape 1 a mis en évidence que le service `FleetResolutionService` et les DTO associés au module de bataille de flottes
(`AttackingFleetDTO`, `DefendingFleetDTO`, `FleetBattleResultDTO`, `FleetBattleRoundDTO`, `FleetParticipantDTO`) ne sont référencés ni par
les use cases ni par les contrôleurs. Les seules utilisations restantes provenaient de l’enregistrement automatique dans le conteneur
et de tests unitaires dédiés. Aucun flux applicatif ne crée ou ne consomme ces objets et aucune route n’active de simulation de
bataille.

## Décision

Nous supprimons entièrement le module de résolution de batailles :

- retrait du service `FleetResolutionService` et des DTO du namespace `App\Domain\Battle\DTO` ;
- suppression de leur enregistrement dans `config/services.php` ;
- suppression des tests unitaires spécifiques qui ne couvrent plus un comportement utilisable.

## Conséquences

- Le conteneur d’injection ne publie plus de dépendances liées à un module inactif, ce qui réduit le temps de construction et la
  surface de maintenance.
- Les tests ciblant un service inutilisé disparaissent, ce qui simplifie la suite de tests et clarifie la couverture réelle des
  fonctionnalités.
- Toute future implémentation de combat devra introduire un nouveau module documenté et couvert par un ADR distinct, afin d’éviter
  les dérives architecturales.
