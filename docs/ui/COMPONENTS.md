# Composants UI — Genesis Reborn

> Les snippets suivants illustrent la nouvelle API de composants basés sur les
design tokens (`tokens.css`) et le bridge Bootstrap. Chaque extrait est en HTML
pur et peut être copié dans un template PHP.

## Boutons

```html
<button type="button" class="ui-button ui-button--primary">
  <span class="ui-button__label">Action principale</span>
</button>
<button type="button" class="ui-button ui-button--secondary ui-button--sm">
  <span class="ui-button__label">Action secondaire</span>
</button>
<a href="#" class="ui-button ui-button--ghost ui-button--lg" role="button">
  <span class="ui-button__label">Lien discret</span>
</a>
<button type="button" class="ui-button ui-button--danger is-loading" aria-live="polite" aria-busy="true">
  <span class="ui-button__label">Suppression</span>
  <span class="ui-button__spinner" aria-hidden="true"></span>
</button>
```

## Cartes

```html
<article class="ui-card ui-card--surface" role="group" aria-labelledby="card-title">
  <header class="ui-card__header">
    <div>
      <h2 id="card-title" class="ui-card__title">Rapport de flotte</h2>
      <p class="ui-card__subtitle">Dernière mise à jour il y a 5 minutes</p>
    </div>
    <span class="ui-badge ui-badge--info">En cours</span>
  </header>
  <div class="ui-card__body">
    <p>Préparez votre flotte pour l'expédition suivante.</p>
  </div>
  <footer class="ui-card__footer">
    <button type="button" class="ui-button ui-button--primary ui-button--sm">Lancer la mission</button>
    <button type="button" class="ui-button ui-button--neutral ui-button--sm">Voir les détails</button>
  </footer>
</article>
```

## Contrôles de formulaire

```html
<div class="ui-field">
  <label class="ui-field__label" for="field-planet">Nom de la planète</label>
  <input id="field-planet" name="planet" class="ui-input" type="text" placeholder="Ex : Andromède" autocomplete="off">
  <p class="ui-field__hint">Utilisez un nom unique pour cette colonie.</p>
</div>
<div class="ui-field">
  <label class="ui-field__label" for="field-type">Type de mission</label>
  <select id="field-type" name="mission" class="ui-select">
    <option>Exploration</option>
    <option>Commerce</option>
    <option>Combat</option>
  </select>
</div>
<div class="ui-field">
  <label class="ui-field__label" for="field-notes">Instructions</label>
  <textarea id="field-notes" name="notes" class="ui-textarea" rows="4" placeholder="Ajoutez des détails pour la flotte."></textarea>
</div>
<div class="ui-field">
  <label class="ui-field__label" for="field-capacity">Capacité restante</label>
  <input id="field-capacity" name="capacity" class="ui-input ui-input--success" type="number" value="85" aria-describedby="capacity-help">
  <p id="capacity-help" class="ui-field__hint">Vos cargos sont prêts à 85&nbsp;%.</p>
</div>
<div class="ui-field">
  <label class="ui-field__label" for="field-error">Code d'autorisation</label>
  <input id="field-error" name="auth" class="ui-input ui-input--invalid" type="password" aria-invalid="true" aria-describedby="auth-error">
  <p id="auth-error" class="ui-field__hint" role="alert">Le code fourni est invalide.</p>
</div>
```

## Badges

```html
<span class="ui-badge ui-badge--primary">Nouveau</span>
<span class="ui-badge ui-badge--warning">Alerte</span>
<span class="ui-badge ui-badge--neutral">Neutre</span>
```

## Alertes

```html
<section class="ui-alert ui-alert--warning" role="alert" aria-live="assertive">
  <svg class="ui-alert__icon" aria-hidden="true">
    <use href="/assets/svg/sprite.svg#icon-warning"></use>
  </svg>
  <div>
    <h3 class="ui-alert__title">Tempête solaire détectée</h3>
    <p class="ui-alert__description">Renforcez les boucliers des colonies exposées avant la prochaine heure galactique.</p>
  </div>
</section>
```

## Onglets / Navigation

```html
<div class="ui-tabs" data-controller="tabs">
  <div role="tablist" class="ui-tabs__list" aria-label="Gestion de flotte">
    <button type="button" class="ui-tabs__trigger" role="tab" id="tab-overview" aria-controls="panel-overview" aria-selected="true">
      Aperçu
    </button>
    <button type="button" class="ui-tabs__trigger" role="tab" id="tab-fleet" aria-controls="panel-fleet" aria-selected="false">
      Flotte
    </button>
    <button type="button" class="ui-tabs__trigger" role="tab" id="tab-log" aria-controls="panel-log" aria-selected="false">
      Journal
    </button>
  </div>
  <div id="panel-overview" class="ui-tabs__panel" role="tabpanel" aria-labelledby="tab-overview" aria-hidden="false">
    <p>Résumé de mission et indicateurs principaux.</p>
  </div>
  <div id="panel-fleet" class="ui-tabs__panel" role="tabpanel" aria-labelledby="tab-fleet" aria-hidden="true">
    <p>Composition détaillée de la flotte.</p>
  </div>
  <div id="panel-log" class="ui-tabs__panel" role="tabpanel" aria-labelledby="tab-log" aria-hidden="true">
    <p>Historique des événements récents.</p>
  </div>
</div>
```

## Pagination

```html
<nav class="ui-pagination" role="navigation" aria-label="Pagination des rapports">
  <button type="button" class="ui-pagination__item" aria-label="Page précédente" aria-disabled="true">«</button>
  <button type="button" class="ui-pagination__item" aria-current="page">1</button>
  <button type="button" class="ui-pagination__item">2</button>
  <button type="button" class="ui-pagination__item">3</button>
  <button type="button" class="ui-pagination__item" aria-label="Page suivante">»</button>
</nav>
```

## Modale avec focus trap

```html
<div
  class="ui-modal is-open"
  role="dialog"
  aria-modal="true"
  aria-labelledby="fleet-modal-title"
  data-focus-trap
>
  <div class="ui-modal__header">
    <h2 id="fleet-modal-title">Planifier une mission</h2>
    <button type="button" class="ui-button ui-button--ghost" data-modal-dismiss>
      <span class="ui-button__label">Fermer</span>
    </button>
  </div>
  <div class="ui-modal__body">
    <p>Définissez la planète cible et le type de mission souhaité.</p>
    <label class="ui-field">
      <span class="ui-field__label">Destination</span>
      <input class="ui-input" type="text" name="target" required>
    </label>
    <label class="ui-field">
      <span class="ui-field__label">Mission</span>
      <select class="ui-select" name="mission" required>
        <option value="transport">Transport</option>
        <option value="attack">Attaque</option>
      </select>
    </label>
  </div>
  <div class="ui-modal__footer">
    <button type="button" class="ui-button ui-button--ghost" data-modal-dismiss>
      <span class="ui-button__label">Annuler</span>
    </button>
    <button type="submit" class="ui-button ui-button--primary">
      <span class="ui-button__label">Lancer</span>
    </button>
  </div>
</div>
```
