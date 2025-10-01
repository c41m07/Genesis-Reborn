# Étape 3 – Qualité & CI ciblée

## Design note
- **Objectif** : sécuriser la couche Infrastructure (router + CSRF) et les modules front critiques en automatisant leur validation dans la CI.
- **Couverture** :
  - Ajout de tests unitaires dédiés pour `Router` (résolution statique/dynamique, normalisation d’URL) et `CsrfTokenManager` (génération et vérification de jetons).
  - Introduction d’un test Node sur `modules/countdown.js` pour verrouiller l’orchestration du rafraîchissement des files.
- **CI** : la pipeline frontend exécute désormais `npm test` en plus des linters/builds afin de capturer toute régression JavaScript.

## Checklist d’acceptation
- [x] Des tests unitaires couvrent le router HTTP et la gestion des jetons CSRF (cas nominaux + erreurs).
- [x] Un test Node vérifie l’initialisation, le rafraîchissement et le nettoyage du module `countdown`.
- [x] La CI GitHub Actions exécute `npm test` dans le job frontend.
- [x] La documentation de maintenance mentionne l’exécution des nouveaux tests (PHP & Node).

## Commandes CI recommandées
- `composer cs`
- `composer stan`
- `composer test`
- `npm run lint`
- `npm run build`
- `npm test`
