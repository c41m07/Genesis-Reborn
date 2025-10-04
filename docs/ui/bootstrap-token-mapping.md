# Table de correspondance des tokens ↔️ Bootstrap

Ce tableau liste les principaux tokens du design system Genesis Reborn et leur
équivalent Bootstrap configuré dans `public/assets/css/bootstrap-bridge.css`.
Ces correspondances servent de référence pour les prochaines étapes de
normalisation (composants, pages, utilitaires).

| Token Genesis | Variable Bootstrap | Portée / remarques |
| --- | --- | --- |
| `--color-primary` | `--bs-primary`, `--bs-nav-pills-link-active-bg`, `--bs-pagination-active-bg` | États actifs & fonds d'action principaux |
| `--color-primary-hover` | `--bs-link-hover-color`, `--bs-pagination-hover-color` | Hover des liens et pagination |
| `--color-primary-soft` | `--bs-primary-bg-subtle`, `--bs-pagination-hover-bg`, `--bs-pagination-focus-bg` | Surfaces légères (hover/focus) |
| `--color-primary-border` | `--bs-primary-border-subtle`, `--bs-pagination-hover-border-color` | Contours d'états primaires |
| `--color-primary-on` | `--bs-nav-pills-link-active-color`, `--bs-pagination-active-color` | Texte sur fonds primaires |
| `--color-secondary` | `--bs-secondary`, `--bs-secondary-text-emphasis` | Actions secondaires / accents |
| `--color-secondary-soft` | `--bs-secondary-bg-subtle` | Surfaces secondaires |
| `--color-success` | `--bs-success`, `--bs-success-text-emphasis` | États de validation / succès |
| `--color-success-soft` | `--bs-success-bg-subtle` | Fonds de messages succès |
| `--color-info` | `--bs-info`, `--bs-info-text-emphasis` | Informations neutres |
| `--color-info-soft` | `--bs-info-bg-subtle` | Surfaces informatives |
| `--color-warning` | `--bs-warning`, `--bs-warning-text-emphasis` | Alertes préventives |
| `--color-warning-soft` | `--bs-warning-bg-subtle` | Arrière-plans d'avertissement |
| `--color-danger` | `--bs-danger`, `--bs-danger-text-emphasis` | États d'erreur |
| `--color-danger-soft` | `--bs-danger-bg-subtle` | Surfaces d'erreur |
| `--color-border` | `--bs-border-color`, `--bs-card-border-color`, `--bs-table-border-color` | Contours neutres |
| `--color-focus-ring` | `--bs-focus-ring-color`, `--bs-pagination-focus-box-shadow` | Accessibilité : focus visible |
| `--text-primary` | `--bs-body-color`, `--bs-heading-color`, `--bs-card-color` | Couleur de texte par défaut |
| `--text-muted` | `--bs-secondary-color`, `--bs-nav-link-color` | Texte atténué |
| `--font-family-base` | `--bs-font-sans-serif`, `--bs-body-font-family` | Police par défaut |
| `--font-size-300` | `--bs-body-font-size` | Taille de corps |
| `--font-weight-semibold` | `--bs-heading-font-weight`, `--bs-btn-font-weight` | Titres & boutons |
| `--line-height-base` | `--bs-body-line-height` | Lisibilité du corps de texte |
| `--space-2` | `--bs-btn-padding-y`, `--bs-nav-link-padding-y`, `--bs-pagination-padding-y` | Vertical des contrôles |
| `--space-3` | `--bs-btn-padding-y-lg`, `--bs-alert-padding-y` | Espacement médian |
| `--space-4` | `--bs-btn-padding-x`, `--bs-alert-padding-x` | Padding horizontal standard |
| `--space-6` | `--bs-card-spacer-x`, `--bs-card-spacer-y`, `--bs-gutter-x` | Rythme de cartes & grilles |
| `--radius-sm` | `--bs-border-radius-sm`, `--bs-btn-border-radius` | Petits arrondis (inputs, boutons) |
| `--radius-md` | `--bs-border-radius`, `--bs-card-border-radius` | Conteneurs principaux |
| `--radius-pill` | `--bs-border-radius-pill`, `--bs-badge-border-radius`, `--bs-pagination-border-radius` | Formes pill |
| `--shadow-md` | `--bs-card-box-shadow`, `--bs-dropdown-box-shadow` | Élévation moyenne |
| `--focus-ring` | `--bs-btn-focus-box-shadow`, `--bs-form-control-focus-box-shadow` | Halo d'accessibilité |
| `--duration-base` + `--easing-standard` | `--bs-transition-duration`, `--bs-transition-timing-function` | Transitions cohérentes |

> 💡 Les variables Bootstrap héritent automatiquement des mises à jour dans
> `tokens.css`. En cas d'ajout de composants, compléter ce tableau pour conserver
> une trace des équivalences.
