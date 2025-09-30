# Guide de configuration de l'équilibrage

Ce document décrit la structure des fichiers YAML de balance utilisés pour définir bâtiments, technologies et unités,
ainsi que la manière dont `BalanceConfigLoader` normalise ces données pour les exposer au domaine. Les YAML sont chargés
en tableaux associatifs typés, identiques à ceux déjà utilisés dans `config/game/*.php`, puis injectés dans les
catalogues et services de calcul (coûts, temps, production, etc.).

## Organisation des fichiers

```
config/
└── game/
    ├── buildings.yaml   # Définition des bâtiments planétaires
    ├── research.yaml    # Arbre technologique
    └── ships.yaml       # Catalogue des unités spatiales
```

`BalanceConfigLoader` lit chaque fichier, applique les valeurs par défaut nécessaires (illustrations de catégorie, bonus
optionnels, etc.) et retourne trois tableaux que le conteneur passe respectivement à `BuildingCatalog`,
`ResearchCatalog` et `ShipCatalog`.【F:config/services.php†L84-L137】【F:src/Domain/Service/BuildingCatalog.php†L13-L40】【F:
src/Domain/Service/ResearchCatalog.php†L16-L48】【F:src/Domain/Service/ShipCatalog.php†L16-L46】 Les sections suivantes
détaillent le schéma attendu pour chaque ressource.

## `buildings.yaml`

Chaque entrée est indexée par une clé unique (`metal_mine`, `research_lab`, etc.). Les champs attendus sont :

| Clé                        | Type                                                                          | Obligatoire | Description                                                                                                   |
|----------------------------|-------------------------------------------------------------------------------|-------------|---------------------------------------------------------------------------------------------------------------|
| `label`                    | string                                                                        | oui         | Nom affiché du bâtiment.                                                                                      |
| `base_cost`                | mapping<string, int>                                                          | oui         | Coût de base par ressource (metal, crystal, hydrogen).【F:config/game/buildings.php†L4-L19】                    |
| `growth_cost`              | float                                                                         | oui         | Multiplicateur appliqué à chaque niveau pour le coût.【F:src/Domain/Service/BuildingCalculator.php†L16-L22】    |
| `base_time`                | int                                                                           | oui         | Durée de construction niveau 1 (secondes).                                                                    |
| `growth_time`              | float                                                                         | oui         | Multiplicateur de temps par niveau.【F:src/Domain/Service/BuildingCalculator.php†L23-L33】                      |
| `prod_base`                | int                                                                           | non         | Production de base par heure pour la ressource ciblée.                                                        |
| `prod_growth`              | float                                                                         | non         | Multiplicateur de production par niveau.【F:src/Domain/Service/ResourceEffectFactory.php†L21-L39】              |
| `energy_use_base`          | int                                                                           | non         | Consommation/production énergétique de base.                                                                  |
| `energy_use_growth`        | float                                                                         | non         | Multiplicateur énergétique par niveau.                                                                        |
| `energy_use_linear`        | bool                                                                          | non         | Si vrai, multiplie la consommation par le niveau actuel.【F:src/Domain/Service/BuildingCalculator.php†L37-L46】 |
| `affects`                  | string                                                                        | oui         | Ressource ou catégorie impactée (`metal`, `energy`, `storage`, etc.).                                         |
| `requires.buildings`       | mapping<string, int>                                                          | non         | Pré-requis en bâtiments.                                                                                      |
| `requires.research`        | mapping<string, int>                                                          | non         | Pré-requis en recherches.【F:src/Domain/Service/BuildingCalculator.php†L200-L232】                              |
| `image`                    | string                                                                        | non         | Illustration dédiée (sinon gérée côté front).                                                                 |
| `storage`                  | mapping<string, {base: float, growth: float}>                                 | non         | Bonus de capacité par niveau.【F:src/Domain/Service/ResourceEffectFactory.php†L52-L62】                         |
| `upkeep`                   | mapping<string, {base?: float, growth?: float, linear?: bool}>                | non         | Entretien en ressources/énergie.【F:src/Domain/Service/ResourceEffectFactory.php†L65-L82】                      |
| `ship_build_speed_bonus`   | {base?: float, growth?: float, linear?: bool, max?: float}                    | non         | Bonus appliqué au chantier spatial.【F:src/Domain/Service/BuildingCalculator.php†L118-L179】                    |
| `research_speed_bonus`     | {base?: float, growth?: float, linear?: bool, max?: float, per_level?: float} | non         | Bonus global de vitesse de recherche.【F:src/Domain/Service/BuildingCalculator.php†L200-L239】                  |
| `construction_speed_bonus` | {base?, growth?, linear?, max?, per_level?}                                   | non         | Bonus générique sur les temps de construction.【F:src/Domain/Service/BuildingCalculator.php†L200-L239】         |

### Exemple YAML

```yaml
automatter_reactor:
  label: "Réacteur à antimatière"
  base_cost: { metal: 3200, crystal: 2200, hydrogen: 1200 }
  base_time: 90
  growth_cost: 1.55
  growth_time: 1.6
  prod_base: 800
  prod_growth: 1.2
  energy_use_base: 0
  energy_use_growth: 1.0
  affects: energy
  requires:
    buildings:
      fusion_reactor: 5
      research_lab: 6
    research:
      reactor_antimatter: 1
  upkeep:
    hydrogen:
      base: 60
      growth: 1.2
  image: assets/svg/illustrations/buildings/antimatter-reactor.svg
```

### Consommation côté domaine

`BalanceConfigLoader` valide les types et fournit le tableau final au `BuildingCatalog`, qui instancie un
`BuildingDefinition` par entrée et expose ensuite ces définitions aux cas d’usage (survol des bâtiments, files
d’attente, etc.).【F:src/Domain/Service/BuildingCatalog.php†L13-L49】【F:
src/Application/UseCase/Building/GetBuildingsOverview.php†L16-L209】 `ResourceEffectFactory` dérive à partir des mêmes
données les effets appliqués par `ResourceTickService` (production horaire, stockage, entretien).【F:
src/Domain/Service/ResourceEffectFactory.php†L12-L89】【F:src/Domain/Service/ResourceTickService.php†L16-L188】

## `research.yaml`

Chaque clé représente une technologie. Les champs attendus :

| Clé            | Type                 | Obligatoire      | Description                                                      |
|----------------|----------------------|------------------|------------------------------------------------------------------|
| `label`        | string               | oui              | Nom affiché.                                                     |
| `category`     | string               | oui              | Regroupement pour l’arbre techno (Propulsion, Ingénierie, etc.). |
| `description`  | string               | non              | Texte descriptif.                                                |
| `base_cost`    | mapping<string, int> | oui              | Coût initial par ressource.                                      |
| `base_time`    | int                  | oui              | Temps de recherche niveau 1.                                     |
| `growth_cost`  | float                | non (défaut 1.0) | Multiplicateur de coût.                                          |
| `growth_time`  | float                | non (défaut 1.0) | Multiplicateur de temps.                                         |
| `max_level`    | int                  | non (défaut 10)  | Niveau plafond (0 = illimité).                                   |
| `requires`     | mapping<string, int> | non              | Technologies requises.                                           |
| `requires_lab` | int                  | non              | Niveau minimum du `research_lab`.                                |
| `image`        | string               | non              | Illustration (sinon déterminée via la catégorie).                |

Une fois le YAML chargé, le loader applique une illustration par défaut à partir du mapping `category → image` lorsque
le champ `image` est absent.【F:config/game/research.php†L1-L33】【F:config/game/research.php†L281-L290】 Les données
alimentent `ResearchCatalog`, qui instancie des `ResearchDefinition` et regroupe les items par catégorie pour
`GetResearchOverview` et `GetTechTree`.【F:src/Domain/Service/ResearchCatalog.php†L16-L69】【F:
src/Application/UseCase/Research/GetResearchOverview.php†L27-L141】

`ResearchCalculator` utilise `growth_cost`, `growth_time` ainsi que le bonus de laboratoire calculé à partir du bâtiment
`research_lab`. Le bonus est lu dans `buildings.yaml` (clé `research_speed_bonus`) et plafonné avant d’être appliqué aux
durées de recherche.【F:config/services.php†L107-L131】【F:src/Domain/Service/ResearchCalculator.php†L8-L66】

### Exemple YAML

```yaml
reactor_antimatter:
  label: "Réacteurs à antimatière"
  category: "Propulsion"
  description: "Confinement annulaire et catalyseurs d’antimatière."
  base_cost: { metal: 380, crystal: 340, hydrogen: 200 }
  base_time: 130
  growth_cost: 1.75
  growth_time: 1.7
  max_level: 10
  requires:
    propulsion_basic: 5
    engineering_heavy: 2
  requires_lab: 3
```

## `ships.yaml`

Chaque clé décrit une unité spatiale. Les champs attendus :

| Clé                 | Type                 | Obligatoire           | Description                                         |
|---------------------|----------------------|-----------------------|-----------------------------------------------------|
| `label`             | string               | oui                   | Nom affiché.                                        |
| `category`          | string               | non (défaut `Divers`) | Catégorie utilisée pour le regroupement et l’image. |
| `role`              | string               | non                   | Brève description du rôle tactique.                 |
| `description`       | string               | non                   | Texte long.                                         |
| `base_cost`         | mapping<string, int> | non (défaut vide)     | Coût de construction.                               |
| `build_time`        | int                  | non (défaut 0)        | Durée de construction de base.                      |
| `stats`             | mapping<string, int> | non                   | Caractéristiques (attaque, défense, vitesse…).      |
| `requires_research` | mapping<string, int> | non                   | Technologies requises.                              |
| `image`             | string               | non                   | Illustration (catégorie par défaut sinon).          |

`BalanceConfigLoader` complète l’image à partir des catégories si nécessaire, puis transmet les entrées au `ShipCatalog`
.【F:config/game/ships.php†L1-L33】【F:config/game/ships.php†L329-L338】【F:src/Domain/Service/ShipCatalog.php†L16-L46】 Les
définitions sont utilisées par `GetShipyardOverview` pour vérifier les prérequis (`requires_research`), calculer les
temps de file d’attente avec le bonus du chantier spatial et agréger les unités par catégorie pour l’interface du
chantier.【F:src/Application/UseCase/Shipyard/GetShipyardOverview.php†L17-L164】【F:
src/Domain/Service/BuildingCalculator.php†L118-L151】

### Exemple YAML

```yaml
fighter:
  label: "Ailes Lyrae"
  category: "Petits vaisseaux"
  role: "Chasseur léger de supériorité spatiale"
  description: "Véhicule agile conçu pour intercepter rapidement les menaces proches."
  base_cost: { metal: 220, crystal: 90, hydrogen: 45 }
  build_time: 60
  stats: { attaque: 6, défense: 3, vitesse: 18 }
  requires_research:
    propulsion_basic: 1
    life_support: 2
    miniaturisation: 2
    weapon_light: 2
    radar_basic: 1
```

## Ajuster un coefficient global ou ajouter du contenu

### Ajuster un coefficient global

1. **Temps ou coût des bâtiments** : modifier `growth_time` ou `growth_cost` dans les entrées pertinentes de
   `buildings.yaml`. Le `BuildingCalculator` multiplie respectivement le temps et le coût du prochain niveau par ces
   coefficients.【F:src/Domain/Service/BuildingCalculator.php†L16-L33】
2. **Vitesse de recherche globale** : ajuster `research_lab.research_speed_bonus`. Le conteneur lit cette configuration
   et la transmet à `ResearchCalculator`, qui applique le bonus (plafonné) à toutes les recherches.【F:
   config/game/buildings.php†L80-L137】【F:config/services.php†L107-L131】【F:
   src/Domain/Service/ResearchCalculator.php†L18-L44】
3. **Production/consommation énergétique** : modifier `prod_base`, `prod_growth`, `energy_use_base`, `energy_use_growth`
   ou `energy_use_linear`. `ResourceEffectFactory` transforme ces paramètres en effets utilisés par
   `ResourceTickService` pour le calcul horaire.【F:src/Domain/Service/ResourceEffectFactory.php†L21-L49】【F:
   src/Domain/Service/ResourceTickService.php†L100-L160】

Après modification, recharger la configuration (redémarrage de l’application ou invalidation de cache) suffit : les
services consomment les nouveaux coefficients à l’initialisation.

### Ajouter une technologie

1. Ajouter une nouvelle entrée dans `research.yaml` avec les champs décrits plus haut (clé unique, `label`, `category`,
   `base_cost`, etc.).
2. Définir ses prérequis (`requires`, `requires_lab`) pour qu’ils apparaissent correctement dans l’arbre et les
   vérifications de file.【F:src/Domain/Service/ResearchCalculator.php†L45-L92】
3. Facultatif : renseigner `image`; sinon l’illustration de catégorie sera utilisée.【F:
   config/game/research.php†L281-L290】
4. Vérifier dans l’interface recherche/tech tree que la technologie apparaît avec les bonnes dépendances (
   `GetResearchOverview`, `GetTechTree`).【F:src/Application/UseCase/Research/GetResearchOverview.php†L76-L141】

### Ajouter une unité spatiale

1. Ajouter une entrée dans `ships.yaml` avec une clé unique, `label`, `base_cost`, `build_time` et `requires_research`.
2. Choisir `category` pour bénéficier des regroupements automatiques et des images par défaut.【F:
   config/game/ships.php†L1-L33】【F:config/game/ships.php†L329-L338】
3. Mettre à jour éventuellement les textes ou illustrations front si la catégorie est nouvelle.
4. Tester dans le chantier spatial : `GetShipyardOverview` affichera l’unité, vérifier que les prérequis sont respectés
   et que le temps de construction reflète bien le bonus du chantier.【F:
   src/Application/UseCase/Shipyard/GetShipyardOverview.php†L92-L133】

En procédant ainsi, la nouvelle unité ou technologie est immédiatement disponible pour les services métiers (queues de
construction, calculs de coûts, production, etc.) grâce à l’injection centralisée opérée par `BalanceConfigLoader`.
