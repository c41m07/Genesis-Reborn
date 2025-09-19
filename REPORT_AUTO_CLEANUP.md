# Auto Cleanup Report

## Removed or Replaced Assets
- `public/assets/svg/illustrations/placeholders/building-default.svg` — removed because it was no longer referenced by any template or script.

## Sprite and Icon Updates
- Added `research.svg`, `shipyard.svg`, `tech.svg`, and `time.svg` to `public/assets/svg/icons/` and regenerated the sprite via `npm run svgo:build` (sprite size `4.41 KB → 4.40 KB`, −0.2%).

## CSS / JS Size Snapshot
| Asset | Before | After | Δ |
| --- | --- | --- | --- |
| `public/assets/css/app.css` | 29,116 B | 29,117 B | +1 B |
| `public/assets/js/app.js` | 6,417 B | 6,418 B | +1 B |

## Tooling & Dependencies
- Installed Composer dependencies (`composer install`) and ensured `composer stan` enforces a `--memory-limit=256M` flag.
- Added ESLint/Stylelint setup (flat config files, npm scripts, dev dependencies) and wired `npm run lint`, `npm run lint:js`, and `npm run lint:css`.
- Created `vite.config.js` to enable library builds for `public/assets/js/app.js` so `npm run build` succeeds, and ignored the generated `dist/` directory.
- Added `.github/workflows/ci.yml` to run Composer validation, coding standards, PHPStan, PHPUnit, Node linting, and the front-end build on pushes/PRs.
- `npm install` now brings in `@eslint/js`, `eslint`, `globals`, `stylelint`, and `stylelint-config-standard` as devDependencies (npm reports two moderate vulnerabilities inherited from upstream packages).

## Verification Commands
- `npm run svgo:build` regenerated the sprite after new icons were added.
- Composer / PHP: `composer test`, `composer stan`.
- Node / Front: `npm run lint:js`, `npm run lint:css`, `npm run lint`, `npm run build` (build artifacts cleaned afterwards).

