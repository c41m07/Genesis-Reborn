# Roadmap – Genesis Reborn

> Feuille de route consolidée. Ce document complète le README et suit la progression par grands axes : Solo, Multijoueur, améliorations avancées et idées bonus.

---

## ✅ Fonctionnalités déjà livrées

- **Gestion des comptes & sessions** : inscription, connexion sécurisée, CSRF généralisé et encapsulation de la session PHP.
- **Planètes & infrastructures** : files de construction, calculs de coûts/temps, gestion d'énergie, bonus de production et jauges temps réel.
- **Recherche scientifique** : arbre techno structuré, prérequis croisés, files par planète et bonus dynamiques de laboratoire.
- **Chantier spatial & flottes** : production de vaisseaux, missions planifiées (exploration, transport, attaque), calculs carburant/ETA, restitution automatique.
- **Tableau de bord & journal** : synthèse de progression, points et premiers rapports d'événements.
- **Carte galactique & arbre des dépendances** : navigation dans les systèmes et visualisation consolidée des prérequis bâtiments/recherches/vaisseaux.
- **Système de ressources** : production horaire, capacités de stockage, entretien et alertes visuelles en cas de manque.
- **Pipeline de qualité** : migrations sécurisées, tests PHPUnit/Node, analyse statique PHPStan, linters (PHP-CS-Fixer, ESLint, Stylelint) et optimisation SVG.
- **Planète mère procédurale** : génération aléatoire contrôlée des coordonnées, diamètres et températures selon l'orbite avec variations configurables (`config/balance/globals.yml`).
- **Tableaux de bord enrichis** : synthèse empire avec files triées, équilibre énergétique, puissance militaire et dépenses cumulées, réutilisée dans le profil joueur.
- **Vue galaxie avancée** : filtres colonisables/inactives, recherche textuelle et indicateurs d'activité/puissance par slot.
- **Interfaces réactives** : réponses JSON normalisées pour les upgrades (ressources, files) et ressources instantané sur les 
  écrans profil/colonie.
- **Journal des versions** : contrôleur + API JSON pour exposer le changelog en jeu.

---

## 🛠️ Prochaines étapes (Solo)

- **Colonisation avancée** : capture de nouvelles planètes (slots limités, prérequis de flotte), écran de sélection colonisable et traitement de colonisation.
- **Logistique intra-empire** : convois de ressources entre planètes du joueur, files de transport dédiées et restitution en temps réel.
- **Équilibrage dynamique** : appliquer la génération procédurale aux planètes colonisées, ajuster températures/production et calibrer les coûts échelonnés.
- **PvE enrichi** : missions d'exploration/combat contre IA avec récompenses dynamiques et utilisation du moteur de résolution de bataille.

---

## 🌐 Objectifs Multijoueur

- **Combats PvP** : résolutions simultanées, défenses coordonnées, rapports détaillés.
- **Échanges inter-joueurs** : commerce ou dons de ressources avec taxation et historique.
- **Classements galactiques** : hiérarchies (puissance, recherche, richesse) avec filtres.
- **Messagerie & notifications** : chat in-game, alertes de combats, rapports diplomatiques.
- **Synchronisation live** : rafraîchissement temps réel des files et compteurs (WebSocket ou SSE) partagé entre clients.

---

## 🚀 Améliorations avancées

- **Alliances & diplomatie** : pactes, guerres déclarées, partage de vision de carte.
- **Marché interstellaire** : bourse de ressources avec fluctuations et taxes.
- **Quêtes & événements dynamiques** : scénarios limités dans le temps, anomalies et récompenses uniques.
- **Exploration galactique** : découverte de systèmes vierges, anomalies ou artefacts spéciaux.
- **Accessibilité & UX** : internationalisation, options d'accessibilité, support multi-résolution.
- **Performance & sécurité** : monitoring serveur, anti-cheat, instrumentation.
- **Replays de combat** : historisation des rounds via le moteur de résolution et visualisation pas à pas.

---

## ⭐ Idées bonus

- **Campagne narrative** introduisant progressivement les mécaniques de jeu.
- **API publique / webhooks** pour outils communautaires (bots Discord, dashboards).
- **Support de modding léger** : règles personnalisables, presets partageables.

---

_Mise à jour : 2025-10-01_

