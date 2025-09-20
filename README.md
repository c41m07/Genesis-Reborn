# Genesis Reborn

Genesis Reborn est un jeu de stratégie spatial écrit en PHP 8.1 avec un front-end HTML/CSS/JS modulaire. Le dépôt suit PSR-12, propose des commentaires fonctionnels en français et s’appuie sur Composer et NPM pour automatiser les tâches quotidiennes.

## Structure

```
config/           Configuration applicative et catalogues du jeu
public/           Point d’entrée et assets compilés
src/              Code métier (Application, Domain, Infrastructure, Controller)
templates/
  layouts/        Layout de base partagé
  components/     Partials PHP réutilisables
  pages/          Pages (auth, colony, dashboard, fleet, galaxy, journal, profile, research, shipyard, tech-tree)
docs/             Documentation fonctionnelle détaillée
migrations/       Scripts SQL pour la base
```

## Installation

```bash
composer install
composer dump-autoload --optimize
npm install
```

## Commandes utiles

```bash
# Qualité PHP
composer test       # PHPUnit
composer stan       # Analyse statique PHPStan
composer cs         # Formatage PSR-12 via PHP-CS-Fixer

# Front-end
npm run lint        # ESLint + Stylelint
npm run svgo:build  # Optimisation des SVG et génération du sprite
```

## Qualité & conventions

- Code PHP formaté PSR-12 avec des commentaires explicatifs en français.
- Toutes les pages front utilisent `templates/layouts/base.php` et les composants partagés.
- Les assets inutilisés sont évités : n’ajoutez que des fichiers réellement référencés dans les catalogues ou les vues.
- Pensez à exécuter les scripts Composer et NPM avant de pousser des modifications.

## Documentation

Le document `docs/README.md` détaille les mécaniques de jeu, les endpoints et la roadmap. Conservez-le à jour lors des évolutions majeures.
