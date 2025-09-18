# Génésis Reborn – Front web

Ce projet PHP propose l’interface du jeu par des gabarits HTML/CSS sobres. Toutes les couleurs, espacements et ombres proviennent des jetons déclarés dans `public/assets/css/tokens.css` afin de garder une direction artistique cohérente.

## Utiliser les icônes SVG
- Les icônes sont regroupées dans le sprite `public/assets/svg/sprite.svg`.
- Pour afficher une icône :
  ```html
  <svg class="icon" aria-hidden="true">
      <use href="/assets/svg/sprite.svg#icon-metal"></use>
  </svg>
  ```
- Les classes utilitaires `.icon`, `.icon-sm` et `.icon-lg` gèrent le gabarit et utilisent `currentColor` pour l’intégration dans le thème.

## Ajouter une nouvelle icône
1. Créer un fichier SVG 24×24 dans `public/assets/svg/icons/` (remplissage à `currentColor`).
2. Lancer `npm run svgo:build` pour optimiser les fichiers et régénérer `sprite.svg`.
3. Inclure l’icône via `<use href="#icon-nom">` dans les gabarits.

## Scripts utiles
- `npm run svgo:build` : optimise les SVG avec SVGO puis reconstruit le sprite.
- `npm run dev` / `npm run build` : scripts réservés au démarrage d’un outillage front (Vite) si besoin ultérieurement.

Respecter ces étapes garantit un rendu accessible (contraste WCAG AA) et des performances stables sans dépendre de polices d’icônes ou d’images raster.
