# Migration progressive vers Bootstrap 5

Ce document suit l’avancement de la migration Bootstrap tout en maintenant la charte de Genesis Reborn.

## Ordre de chargement des feuilles de style

1. `tokens.css` — source de vérité des design tokens.
2. `bootstrap.min.css` — distribution officielle de Bootstrap (hébergée dans `public/assets/css`).
3. `bootstrap-bridge.css` — mappe les variables `--bs-*` vers les tokens (`--color-*`, `--space-*`, `--radius-*`, typo…).
4. `app.css` — styles applicatifs et héritage.

Conserver cet ordre dans `templates/layouts/base.php` afin que Bootstrap consomme les tokens avant nos overrides historiques.

## Activation progressive via `is-bootstrapized`

- Ajouter `$layoutBodyClasses = 'is-bootstrapized';` dans le template de la page pilote.
- Les styles Bootstrap ne sont ajustés que sous `body.is-bootstrapized`, les anciennes classes restent inchangées ailleurs.
- Permet d’itérer page par page sans régression globale.

## Composants migrés

### Boutons (Dashboard)

| Avant (classes maison) | Après (Bootstrap) | Règles CSS conservées / ajustées | Points d’attention |
| --- | --- | --- | --- |
| `.button.button--primary` | `.btn.btn-primary` | Variante primaire réimplémentée dans `app.css` (`body.is-bootstrapized .btn-primary`) pour garder le fond `var(--color-primary)` et le hover `var(--color-primary-strong)`. | Vérifier le contraste texte/fond (AA OK grâce à #041021 sur fond primaire). |
| `.button.button--ghost` | `.btn.btn-outline-primary` | Outline mappé sur `var(--color-border)` avec hover translucide via `app.css`. | Focus visible via `var(--focus-ring)` injecté sur `:focus-visible`. |
| `.link-button` (CTA compacts) | `.btn.btn-outline-primary.btn-sm` | Taille réduite reprise via `body.is-bootstrapized .btn-sm` (font `var(--font-size-xs)`). | Garder le spacing flex des footers (`.production-card__footer`) pour éviter le wrap serré. |

**Règles à nettoyer plus tard**
- Les styles `.button*` restent nécessaires pour les pages non migrées. Ils seront supprimés lorsque l’ensemble sera passé sur Bootstrap.

### Grilles / layout (Dashboard)

| Avant (classes maison) | Après (Bootstrap) | Règles CSS conservées / ajustées | Points d’attention |
| --- | --- | --- | --- |
| `section.dashboard` sans container | `section.dashboard.container-xxl` | `main.workspace__content` garde son padding global ; `container-xxl` garantit l’alignement avec les grilles Bootstrap. | Vérifier que la largeur max reste cohérente avec `var(--container-max)`. |
| `.dashboard-layout` (grid 2 colonnes) | `.row.g-4.g-xl-5` | Bloc legacy conservé dans `app.css` mais annoté comme à déprécier. | Les colonnes Bootstrap apportent les gutters ; vérifier les espacements mobiles (`g-4`). |
| `.dashboard-main` / `.dashboard-side` seuls | `col-12 col-xl-8` + `col-12 col-xl-4` (avec classes historiques pour le gap interne) | Les classes historiques gardent le `gap` vertical, mais la largeur repose désormais sur les colonnes. | Les composants enfants doivent occuper toute la hauteur (ajout de `h-100` sur les cards principales). |

### Cards / panneaux (Dashboard)

| Avant (classes maison) | Après (Bootstrap) | Règles CSS conservées / ajustées | Points d’attention |
| --- | --- | --- | --- |
| `.panel` | `.card` | Les tokens alimentent les variables Bootstrap via `bootstrap-bridge.css`; nouvelle section `body.is-bootstrapized .card` applique l’ombre/arrondi historique. | Les autres pages utilisent encore `.panel` → bloc marqué comme « legacy ». |
| `.panel__header` / `__body` / `__footer` | `.card-header` / `.card-body` / `.card-footer` | Padding et flex alignements recréés dans `app.css` sous le guard `is-bootstrapized`. | Garder la hiérarchie des titres (H2/H3) pour l’accessibilité. |
| `.panel--highlight` | `.card.card-highlight` | Gradient mis à jour pour reprendre `panel--highlight`; la bannière garde son dégradé spécifique via `.dashboard-banner.card.card-highlight`. | Vérifier le contraste texte/fond sur le gradient. |
| `.production-grid` + `.production-card` autonomes | `.row.g-4` contenant des `.card.production-card` | Ancien wrapper CSS annoté legacy ; nouveaux styles ciblent `body.is-bootstrapized .production-card` pour conserver la typo uppercase et les footers en flex. | S’assurer que les footers conservent la distribution `space-between` malgré la règle générale Bootstrap. |

## Composants à venir

| Composant | Statut | Actions prévues |
| --- | --- | --- |
| Grilles / layout | En cours (Dashboard) | Propager `container`/`row`/`col` aux autres pages sécurisées, supprimer les wrappers legacy lorsqu’ils ne sont plus référencés. |
| Cards / panneaux | En cours (Dashboard) | Étendre le mapping `.card` aux autres panneaux (tech tree, fleet, etc.) avant de purger `.panel*`. |
| Alertes | À planifier | Revoir les messages flash → `.alert.alert-*` en réutilisant les tokens `--success-soft`, etc. |
| Formulaires | À planifier | Mapper `.form-field` vers `.form-control`, vérifier focus et contrastes. |

Mettre à jour ce tableau à chaque composant migré : l’objectif est de noter le mapping, les règles conservées/supprimées et les points d’attention (responsive, focus, contrastes, dark mode le cas échéant).

## Note de migration des tokens

- Les design tokens sont centralisés dans `public/assets/css/tokens.css`, qui reste la source de vérité de la palette et des espacements.
- `public/assets/css/bootstrap-bridge.css` expose ces tokens vers les variables `--bs-*` de Bootstrap via le bridge (couleurs, rayons, espacements) en s'appuyant sur les alias harmonisés `--color-body`, `--color-secondary`, `--color-info` et `--color-warning`.
- Pour étendre le système, ajouter d’abord le token dans `tokens.css`, puis le mapper dans `bootstrap-bridge.css` avant d’ajuster les composants `app.css` sous le guard `body.is-bootstrapized`.
