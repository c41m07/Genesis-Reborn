# Stratégie de Pull Requests et conventions de commits

Cette étape finalise la refonte UI/UX en cadrant la manière de livrer les changements. Elle garantit que chaque PR reste ciblée, testée et documentée.

## Séquencement des PR

1. **PR1 — Tokens & bridge** : `public/assets/css/tokens.css`, `bootstrap-bridge.css`, table de mapping.
2. **PR2 — Boutons & cards** : introduction/consommation des composants normalisés.
3. **PR3 — Form controls, badges, alerts** : refactor des champs et états système.
4. **PR4 — Pages prioritaires** : Dashboard, Fleet, Colony et composants auxiliaires.
5. **PR5 — Qualité, tests, docs** : Stylelint, focus trap, rapport de contraste, guides.

Chaque PR doit être mergée avant d'entamer la suivante pour limiter les conflits.

## Structure des commits

- Commits atomiques (un composant ou un lot cohérent de tokens).
- Messages au format `[Scope] Résumé court` (ex. `css: aligne les variantes de boutons`).
- Commentaires dans le code au niveau junior : concis, utiles, sans jargon inutile.
- Pas de refactor opportuniste hors périmètre : ouvrir une issue dédiée si nécessaire.

## Checklists de revue

- Respect de la charte visuelle : couleurs, iconographie, atmosphère sombre.
- Accessibilité : focus visible, aria, contrastes AA (vérifiés manuellement + via le rapport).
- Responsivité : 320 / 768 / 1024 / 1440 px.
- Tests automatisés : `npm run lint:css`, `npm test`, et tests backend si impact PHP.
- Documentation à jour : `docs/ui/COMPONENTS.md`, `docs/migration/bootstrap-mapping.md`, `CHANGELOG.md`.

## Instructions pour les PR

Utiliser le template `.github/pull_request_template.md` :

- Compléter le résumé en listes à puces.
- Cocher chaque point de la checklist une fois validé.
- Détailler les vérifications manuelles (parcours, breakpoints, lecteurs d'écran si pertinents).
- Ajouter des captures si l'UI est modifiée.

## Risques & mitigations

| Risque | Impact | Mitigation |
| --- | --- | --- |
| Oubli de tests ou d'updates docs | Revue bloquée, régressions | Checklist obligatoire dans la PR + CI (lint/tests) |
| Branches longues | Conflits et retours en arrière coûteux | PR courtes, merge fréquent, rebase systématique |
| Divergence avec la charte | Incohérence visuelle | Revue design + comparaison avec tokens | 

---

Ce cadre doit accompagner toutes les contributions liées à la refonte jusqu'à la stabilisation complète des composants et pages.
