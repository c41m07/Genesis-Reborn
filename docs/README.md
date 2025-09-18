# Genesis Reborn – Documentation Solo

Ce référentiel décrit la version solo du jeu de gestion spatiale "Genesis Reborn". Le socle a été pensé pour évoluer vers un mode multijoueur massif : toutes les entités et fichiers de production transportent un `player_id`, les contrôleurs filtrent systématiquement par joueur connecté et la logique de journal/événements accepte déjà des interactions inter-joueurs.

## Design system et assets web

Toutes les couleurs, espacements et ombres proviennent des jetons définis dans `public/assets/css/tokens.css`. Le respect de ces variables garantit la cohérence visuelle, les contrastes accessibles (WCAG AA) et la facilité de theming futur.

### Utiliser les icônes SVG
- Les icônes sont regroupées dans le sprite `public/assets/svg/sprite.svg`.
- Pour afficher une icône :
  ```html
  <svg class="icon" aria-hidden="true">
      <use href="/assets/svg/sprite.svg#icon-metal"></use>
  </svg>
  ```
- Les classes utilitaires `.icon`, `.icon-sm` et `.icon-lg` gèrent le gabarit et utilisent `currentColor` pour s’intégrer automatiquement au thème.

### Ajouter une nouvelle icône
1. Créer un fichier SVG 24×24 dans `public/assets/svg/icons/` (remplissage à `currentColor`).
2. Lancer `npm run svgo:build` pour optimiser les fichiers et régénérer `sprite.svg`.
3. Inclure l’icône via `<use href="/assets/svg/sprite.svg#icon-nom">` dans les gabarits.

### Scripts utiles
- `npm run svgo:build` optimise les SVG avec SVGO puis reconstruit le sprite.
- `npm run dev` / `npm run build` préparent l’outillage front (Vite) si besoin ultérieurement.

## Schéma de données

Les migrations SQL définissent un schéma multijoueur prêt pour des milliers de comptes. Toutes les clés étrangères sont en `ON DELETE CASCADE` (ou `SET NULL`) pour préserver l’intégrité en cas de suppression de joueur ou d’entités liées.

### Joueurs et ressources
- **players** : informations de compte (email unique, username unique, hash de mot de passe, timestamps). Relations 1→N vers `planets`, `planet_buildings`, `player_technologies`, `fleets`, `events`, `player_pve_runs` et l’ensemble des files (`build_queue`, `research_queue`, `ship_build_queue`).
- **resources** : dictionnaire des ressources économiques (clé, nom, description, unité) utilisé par l’UI et les futurs échanges commerciaux.
- **users (vue)** : exposition de `players` pour la compatibilité avec l’ORM existant (colonnes `id`, `email`, `password`).

### Colonies, bâtiments et production
- **planets** : coordonnées galactiques, stocks de ressources, capacités, timestamps de tick et colonnes `prod_*_per_hour` pour mémoriser les rendements calculés. Chaque planète référence un `player_id`.
- **buildings** : catalogue des définitions de bâtiments avec coûts de base, multiplicateurs de croissance, production/consommation énergétique et prérequis (JSON).
- **planet_buildings** : niveaux atteints pour chaque bâtiment sur chaque planète (`player_id` obligatoire, clé unique planète+bâtiment).
- **build_queue** : file d’attente des constructions, avec `player_id`, `planet_id`, clé de bâtiment, niveau cible et `ends_at` pour le tick serveur.

### Technologies et recherche
- **technologies** : catalogue des recherches (coûts de base, durée, multiplicateurs, prérequis JSON et niveau de laboratoire requis).
- **player_technologies** : niveaux détenus par joueur avec suivi éventuel de la planète qui mène la recherche en cours.
- **research_queue** : file d’attente des recherches (`player_id`, `planet_id`, clé de recherche, niveau cible, `ends_at`).

### Flottes et chantiers spatiaux
- **ships** : catalogue des vaisseaux (classe, coûts, vitesse, cargo, consommation de carburant, statistiques de combat, prérequis).
- **ship_build_queue** : production de vaisseaux par planète, avec quantité et échéance.
- **fleets** : flottes actives (planète d’origine, destination éventuelle, type de mission, statut, horodatages de départ/arrivée/retour, payload JSON, carburant consommé). Supporte les transitions multijoueur (destination étrangère, missions PvE/PvP).
- **fleet_ships** : composition détaillée des flottes (1→N avec `fleets` et `ships`).

### PvE, missions et journal
- **pve_missions** : catalogue des scénarios PvE (difficulté, durée, récompenses, prérequis JSON).
- **player_pve_runs** : instances d’exécution (statut, timestamps, récompenses, flotte associée).
- **events** : journal de bord multi-joueur (`player_id` principal, `related_player_id` optionnel, références planète/flotte, payload JSON et sévérité).

## Services métier

Les services encapsulent les règles de calcul afin de garder les contrôleurs et repositories simples.

- **BuildingCatalog** (`src/Domain/Service/BuildingCatalog.php`) : charge les définitions depuis la configuration et expose `all()` (liste complète) et `get(string $key)` (récupération typée, exception si clé inconnue).
- **BuildingCalculator** (`src/Domain/Service/BuildingCalculator.php`) :
  - `nextCost(BuildingDefinition, int)` calcule le coût du niveau suivant.
  - `nextTime(BuildingDefinition, int)` donne la durée de construction.
  - `productionAt(BuildingDefinition, int)` et `energyUseAt(...)` évaluent production et énergie à un niveau donné.
  - `cumulativeCost(...)` retourne la somme des coûts jusqu’à un niveau cible.
  - `checkRequirements(...)` valide prérequis bâtiments/recherches et fournit les éléments manquants.
- **ResearchCatalog** (`src/Domain/Service/ResearchCatalog.php`) : similaire au catalogue de bâtiments avec `all()`, `get()` et `groupedByCategory()` pour l’affichage.
- **ResearchCalculator** (`src/Domain/Service/ResearchCalculator.php`) : calcule coûts/temps de recherche et vérifie les prérequis (niveau de laboratoire compris).
- **ShipCatalog** (`src/Domain/Service/ShipCatalog.php`) : expose `all()`, `get()` et `groupedByCategory()` pour construire les interfaces de flotte.
- **CostService** (`src/Domain/Service/CostService.php`) : utilitaires génériques de coûts (`nextLevelCost`, `cumulativeCost`, `scaledDuration`, `applyDiscount`).
- **ResourceTickService** (`src/Domain/Service/ResourceTickService.php`) : `tick(array $planetStates, DateTimeInterface $now, ?array $effectsOverride)` applique la production/consommation de ressources et retourne les stocks, capacités et ratio énergétique normalisés.
- **FleetNavigationService** (`src/Domain/Service/FleetNavigationService.php`) :
  - `plan(...)` calcule distance, vitesse effective, ETA, consommation de carburant en tenant compte des modificateurs.
  - `distance(...)` calcule la distance galactique normalisée.
- **FleetResolutionService** (`src/Domain/Service/FleetResolutionService.php`) : `advance(array $fleets, DateTimeInterface $now, ?callable $pveResolver, ?callable $explorationResolver)` fait progresser les missions (arrivée, retour, payload) en permettant d’injecter des résolveurs personnalisés.
- **ProcessBuildQueue** (`src/Application/Service/ProcessBuildQueue.php`) : exécute les jobs arrivés à échéance, met à jour les niveaux de bâtiments et recalcule les productions horaires de la planète.
- **ProcessResearchQueue** (`src/Application/Service/ProcessResearchQueue.php`) : applique les recherches terminées au profil du joueur.
- **ProcessShipBuildQueue** (`src/Application/Service/ProcessShipBuildQueue.php`) : transforme les jobs terminés en renforts de flotte.

## Endpoints HTTP

Les contrôleurs renvoient des vues HTML et exigent un jeton CSRF pour toute action POST. Le paramètre de requête `planet` permet toujours de sélectionner une colonie appartenant au joueur connecté.

### Authentification
- `GET /` et `GET /login` : affiche le formulaire de connexion.
- `POST /login` : champs `email`, `password`, `csrf_token`.
- `GET /register` : formulaire d’inscription.
- `POST /register` : champs `email`, `password`, `password_confirm`, `csrf_token`.
- `POST /logout` : nécessite `csrf_token`, redirige vers `/login`.

### Gestion de compte et synthèse
- `GET /dashboard` : tableau de bord général. Query string `planet` optionnelle pour choisir la planète active.
- `GET /profile` : profil commandant avec liste des planètes et synthèse des productions.
- `GET /tech-tree` : arbre technologique regroupé par catégories. Paramètre `planet` optionnel.

### Colonies et progression
- `GET /colony` : vue d’une planète (file de construction, niveaux). Paramètre `planet` optionnel.
- `POST /colony` : planifie l’amélioration d’un bâtiment. Champs `building` (clé), `csrf_token` spécifique à la planète sélectionnée.
- `GET /research` : état des recherches et files par planète. Paramètre `planet` optionnel.
- `POST /research` : lance une recherche. Champs `research` (clé), `csrf_token`.

### Chantier spatial et flottes
- `GET /shipyard` : production navale et file d’attente. Paramètre `planet` optionnel.
- `POST /shipyard` : planifie la construction. Champs `ship` (clé), `quantity` (>=1), `csrf_token`.
- `GET /fleet` : synthèse des vaisseaux disponibles, planification de trajectoire. Paramètre `planet` optionnel.
- `POST /fleet` : calcule un plan de vol sans lancer de mission (interface solo). Champs `destination_galaxy`, `destination_system`, `destination_position`, `composition[ship_key]`, `speed_factor` (0.1–1.0) et `csrf_token`.
- `GET /journal` : journal de bord agrégé (bâtiments, recherches, chantiers en cours). Paramètre `planet` optionnel.

## Roadmap vers le multijoueur

1. **Finaliser le socle solo** : stabiliser les files de production, la navigation et la résolution PvE pour garantir des ticks fiables et scalables.
2. **Classement global** : indexer la puissance économique/militaire par joueur, exposer un leaderboard et préparer des APIs de consultation publiques.
3. **Commerce inter-joueurs** : ajouter des routes protégées pour publier/offres d’échange basées sur `resources` et intégrer des validations côté service (taxes, limites de quantité).
4. **PvP basique** : réutiliser `FleetNavigationService`/`FleetResolutionService` pour gérer attaques de colonies (nouveaux résolveurs, événements bilatéraux, pertes persistantes).
5. **Alliances & diplomatie** : introduire des entités `alliances`, des statuts diplomatiques et des journaux partagés, puis étendre la résolution de missions pour les guerres collectives.
6. **Scalabilité** : planifier des workers et crons horizontaux (queues par shards, idempotence des services `Process*`, notifications d’événements) pour supporter plusieurs milliers de joueurs simultanés.

