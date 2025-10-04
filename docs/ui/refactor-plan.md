# Plan de refonte UI/UX "Genesis Reborn"

## 1. Audit rapide de l'existant

### 1.1 Feuilles de style et assets
- `public/assets/css/tokens.css` : tokens initiaux (palette sombre, quelques rayons/espaces) avec alias partiels (`--color-warning` pointant vers `--color-danger`).
- `public/assets/css/bootstrap-bridge.css` : remappage manuel de propriétés Bootstrap vers les tokens maison (typo, palette, radius, espacement, boutons/cartes/inputs).
- `public/assets/css/app.css` : feuille principale (~3k lignes) gérant layout (shell, sidebar), composants (boutons maison, cards, badges, tableaux, modales, formulaires, alertes, pagination...).
- `public/assets/css/bootstrap.min.css` : Bootstrap 5 minifié (à conserver en lecture seule, plusieurs préfixes `-moz-`).
- Assets SVG : `public/assets/svg/sprite.svg` (icônes référencées dans les templates, utile pour vérifier contraste/arrière-plan).

### 1.2 Partials / templates concernés
- Layout racine : `templates/layouts/base.php` (structure `<body>`, sidebar, includes CSS).
- Composants PHP partagés : `templates/components/_card.php`, `_icon.php`, `_requirements.php`, `_resource_bar.php`, `helpers.php`.
- Pages principales à fort enjeu UI :
  - `templates/pages/dashboard/*.php`
  - `templates/pages/fleet/*.php`
  - `templates/pages/colony/*.php`
  - Autres sections (`hangar`, `research`, `shipyard`, `profile`, etc.) qui consomment les mêmes classes custom.
- Docs existantes : `docs/migration/bootstrap-mapping.md` (table actuelle mélange tokens + remarques contraste).

### 1.3 Inventaire des tokens actuels vs besoins

| Catégorie | Tokens présents | Manques / incohérences relevés |
| --- | --- | --- |
| Couleurs | `--color-bg`, `--color-surface(-alt/-soft)`, `--color-text`, `--color-muted`, `--color-primary`, `--color-primary-strong`, `--color-accent`, `--color-success`, `--color-danger`. Aliases : `--color-fg`, `--color-secondary`, `--color-info`, `--color-warning` (ce dernier pointe vers `danger`). | Pas de vraie teinte "warning"/"info" distincte, pas de neutres intermédiaires (`--color-neutral-xxx`), pas d'états `hover/pressed/disabled` explicites. Focus ring défini mais non décliné selon composants. |
| Typographie | `--font-family-base`, tailles discrètes (`xs` → `xl`), `--line-height-base`. | Pas de `clamp()` responsive, manque de hiérarchie (title/body/caption), pas de tokens pour `font-weight` ou `letter-spacing`. |
| Espacements | `--space-1` → `--space-6` (0.25 → 2rem), alias `--space-xxs` → `--space-xxl`. | Manque de valeurs au-delà de 2rem, nombreuses occurrences de `calc(... + 16px)` dans `app.css` → besoin d'étendre la scale ou ajouter `--space-7/8/9`. |
| Radius & ombres | `--radius-sm`, `--radius`, `--radius-lg`, `--shadow`, `--shadow-card`. | Pas de radius pour pills/badges dédié, pas de niveau d'ombre secondaire (hover). |
| Divers | `--sidebar-width`, `--container-max`, `--transition`, `--focus-ring`. | Besoin d'un token d'animation court/long, d'épaisseurs de border, de z-index (sidebar, overlays), d'opacités pour surfaces. |

### 1.4 Problèmes détectés
- **Doublons / variations ad-hoc** : `app.css` répète les mêmes déclarations (gaps, couleurs) sur de nombreuses classes au lieu de composants mutualisés (ex. `.button`, `.button--primary`, `.btn` custom, `.sidebar__link`).
- **Variables non résolues** : `--color-warning` et `--color-info` héritent de `danger/accent`, créant un risque de confusion lors du mapping Bootstrap (`var(--color-warning, #ff7a7a)`).
- **Espacements magiques** : `calc(var(--space-4) + 16px)`, `padding: 0.55rem ...`, offsets en pixels (`130px`) pour le scroll ou les panels.
- **Typographie figée** : tailles statiques, pas de scale responsive, titres gérés dans CSS sans tokens.
- **Accessibilité** : focus visible global (`box-shadow: var(--focus-ring)`), mais certains éléments (liens sidebar disabled) n'ont pas d'état `:focus-visible`. Contrastes à vérifier (textes `--color-muted` sur surfaces soft).
- **Compatibilité Bootstrap** : Bridge partiel (boutons, cards, forms) mais non exhaustif (navs, badges, alerts). Plusieurs composants custom n'utilisent pas la nomenclature Bootstrap (ex. `.badge` vs `.tag`).

## 2. Plan d'action (checklist par étape)

### Étape 2 — Tokens & bridge
- [x] Définir une palette complète (base + états : hover/active, surfaces, overlays) avec noms cohérents (`primary`, `secondary`, `info`, `warning`, `danger`, `success`, `neutral`).
- [x] Mettre en place une échelle typographique via `clamp()` pour 3 niveaux (heading, body, small) + poids (400/500/600/700).
- [x] Étendre l'échelle d'espacements (`--space-1` → `--space-9` en multiples de 4px) et documenter les alias (`--space-md`, etc.).
- [x] Ajouter tokens radius (`--radius-xs` → `--radius-xl`), ombres (`--shadow-sm`, `--shadow-md`, `--shadow-lg`), opacités et focus rings multiples.
- [x] Actualiser `bootstrap-bridge.css` : mapping complet des couleurs (`--bs-warning`, `--bs-info` distincts), typographie, espacement, composants (buttons, navs, badges, alerts, pagination, forms).
- [x] Dresser le tableau de correspondance tokens ↔ Bootstrap et nettoyer les fallback `, #hex` inutiles une fois tokens fiables.

### Étape 3 — Composants UI normalisés
- [x] Créer des classes BEM (`.btn`, `.btn--primary`, `.card`, `.input`, etc.) ou s'appuyer sur `.btn`, `.card` Bootstrap avec utilitaires personnalisés.
- [x] Décliner variantes `primary/secondary/neutral/info/warning/danger/success` + états (`hover`, `focus-visible`, `active`, `disabled`, `loading`).
- [x] Définir tailles `sm/md/lg` via tokens d'espacement et line-height.
- [x] Garantir focus ring accessible (contraste, offset) et attributs ARIA dans les snippets.
- [x] Documenter les snippets d'usage dans `docs/ui/COMPONENTS.md` (exemples minimalistes, HTML pur + classes).

### Étape 4 — Refactor templates/pages
- [x] Remplacer les classes custom duplicatives par composants normalisés ou utilitaires Bootstrap (`d-flex`, `gap-*` mappés via bridge, etc.).
- [x] Supprimer règles obsolètes de `app.css` (ex. modales ad-hoc) et vendor prefixes superflus (hors Bootstrap minifié).
- [x] Harmoniser les marges/paddings avec l'échelle (`--space-*` ou utilitaires `.p-*`).
- [x] Vérifier la structure sémantique (`<main>`, `<nav>`, `<header>`, `<aside>`, `<footer>`).
- [x] Tester la responsivité aux 4 breakpoints (320, 768, 1024, 1440px) et ajuster layout (sidebar, tableaux) avec flex/grid.

### Étape 5 — Qualité & CI
- [x] Ajouter Stylelint (config compatible BEM + tokens, ordre des propriétés, interdiction `!important` sauf exceptions documentées).
- [x] Mettre en place scripts npm (`lint:css`, `lint:css:fix`).
- [x] Écrire des tests UI smoke (PHPUnit/JS) vérifiant présence des classes, attributs ARIA, focus trap.
- [x] Générer un rapport de contrastes (outil CLI : `pa11y`, `axe`, ou script Node) et documenter les résultats.
- [x] Mettre à jour `docs/migration/bootstrap-mapping.md` (tableau complet, bordures visibles) + ajouter `CHANGELOG.md` concis.

### Étape 6 — Stratégie PR & commits
- [x] Respecter le séquencement des PR (tokens → composants → formulaires/alertes → pages → qualité/tests/docs) et le documenter dans `docs/ui/pr-strategy.md`.
- [x] Découper en commits thématiques (tokens, bridge, docs, refactors) avec messages normalisés.
- [x] Inclure dans chaque PR : résumé, checklist de vérification, instructions de test manuel via le template `.github/pull_request_template.md`.

### Étape 7 — Vérifications finales
- [x] Contrôler la présence de toutes les livraisons prévues (tokens, bridge, composants, refactors, qualité, docs, workflow).
- [x] Exécuter les checks automatisés (`npm run lint:css`, `npm test`) et documenter leur réussite.
- [x] Vérifier les critères de définition de fini : aucune variable CSS non résolue, absence de `-moz-appearance` hors Bootstrap minifié, contrastes AA validés.
- [x] Consigner les résultats et points de vigilance résiduels dans `docs/ui/verification.md`.

## 3. Propositions de normalisation

- **Espacement** : adopter une scale 4px → `--space-1: 0.25rem` (4px) jusqu'à `--space-9: 3rem` (48px). Alias `--space-0` (=0) et `--space-10` (64px) en option pour layouts larges.
- **Typographie** : trois styles `--font-size-display: clamp(1.75rem, 1.2vw + 1.4rem, 2.5rem)`, `--font-size-title: clamp(1.25rem, 0.9vw + 1rem, 1.75rem)`, `--font-size-body: clamp(1rem, 0.5vw + 0.9rem, 1.125rem)`, `--font-size-caption: clamp(0.8125rem, 0.4vw + 0.7rem, 0.95rem)`. Poids `--font-weight-regular: 400`, `--font-weight-medium: 500`, `--font-weight-semibold: 600`, `--font-weight-bold: 700`.
- **Radius** : `--radius-xs: 6px`, `--radius-sm: 10px`, `--radius-md: 16px`, `--radius-lg: 24px`, `--radius-pill: 999px`.
- **Ombres** : `--shadow-sm: 0 4px 12px rgba(5, 10, 30, 0.25)`, `--shadow-md: 0 12px 24px rgba(5, 10, 30, 0.35)`, `--shadow-lg: 0 24px 48px rgba(5, 10, 30, 0.45)`.
- **Transitions** : `--transition-fast: 120ms ease`, `--transition-base: 180ms ease`, `--transition-slow: 280ms ease`. Focus ring `--focus-ring-primary: 0 0 0 3px rgba(77, 163, 255, 0.55)`, `--focus-ring-inverse: 0 0 0 3px rgba(4, 16, 33, 0.6)`.
- **Z-index** : `--z-base: 1`, `--z-overlay: 10`, `--z-dropdown: 100`, `--z-modal: 1000`, `--z-toast: 1100`.
## 4. Risques & points de vigilance

| Risque | Impact | Mitigation |
| --- | --- | --- |
| Rupture visuelle lors de la mise à jour des tokens (palettes warning/info non définies aujourd'hui). | Perte d'identité ou contraste insuffisant. | Valider la palette avec design tokens dérivés des couleurs existantes (ex. dériver warning à partir d'une teinte orange compatible) et tester contraste AA avant déploiement. |
| Régression fonctionnelle en remplaçant les classes custom par Bootstrap/utilitaires. | Navigation/pages critiques peuvent casser (ex. actions de fleet). | Audit des templates prioritaires, refactor progressif par PR ciblée, tests manuels (naviguer + CLI). |
| Charge de refactor CSS (~3k lignes) entraînant conflits. | Difficulté à maintenir branches longues. | Découper par composants/pages (cf. stratégie PR), supprimer progressivement les règles, garder un changelog détaillé. |
| Introduction de Stylelint strict. | CI rouge si règles non alignées. | Fournir guide dans docs + script `lint:css --fix`, config commentée pour exceptions. |
| Accessibilité (focus, contrastes) non couverts par tests existants. | Non-conformité WCAG AA. | Ajouter tests automatisés (axe/pa11y) + check manuel sur écrans 320/768/1024/1440px. |

---

Ce plan servira de base pour les étapes 2 à 5 (tokens → composants → refactor pages → qualité & docs) tout en respectant la charte actuelle et les contraintes techniques (PHP templates, Bootstrap minimal, tokens CSS).
