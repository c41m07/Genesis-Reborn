# Changelog

## [Unreleased]

### Ajouté
- Stylelint strict (ordre alphabétique, interdiction des `!important` non documentés) et scripts associés pour surveiller les CSS.
- Tests UI de fumée (structure des pages migrées, focus trap) ainsi qu’un test unitaire pour le module de focus trap.
- Script `tools/generate-contrast-report.mjs` générant `docs/ui/contrast-report.md` afin de tracer le respect du contraste AA.

### Modifié
- Layout principal enrichi d’attributs ARIA (`aria-live`, `role`) et d’un `data-focus-trap` sur la navigation latérale.
- Modules front (`sidebar`, `app.js`) initialisent désormais un focus trap accessible partagé par les modales et la sidebar.
- Documentation UI complétée avec le guide des modales, le rapport de contraste et l’état d’accessibilité dans le mapping Bootstrap.
