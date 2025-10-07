# Composants UI — Genesis Reborn

> Les snippets suivants illustrent la nouvelle API de composants basés sur les
design tokens (`tokens.css`) et le bridge Bootstrap. Chaque extrait est en HTML
pur et peut être copié dans un template PHP.

## Boutons

```html
<button type="button" class="btn btn-primary">
  <span class="btn__label">Action principale</span>
</button>
<button type="button" class="btn btn-secondary btn-sm">
  <span class="btn__label">Action secondaire</span>
</button>
<a href="#" class="btn btn-ghost btn-lg" role="button">
  <span class="btn__label">Lien discret</span>
</a>
<button type="button" class="btn btn-danger is-loading" aria-live="polite" aria-busy="true">
  <span class="btn__label">Suppression</span>
  <span class="btn__spinner" aria-hidden="true"></span>
</button>
```

## Cartes

```html
<article class="card card-surface" role="group" aria-labelledby="card-title">
  <header class="card-header d-flex flex-column flex-lg-row gap-4 align-items-start align-items-lg-center">
    <div class="card-meta d-flex flex-column gap-2">
      <span class="card-eyebrow text-uppercase text-secondary-emphasis">Rapport prioritaire</span>
      <h2 id="card-title" class="card-title">Rapport de flotte</h2>
      <p class="card-subtitle">Dernière mise à jour il y a 5 minutes</p>
    </div>
    <span class="badge card-badge ms-lg-auto text-uppercase">En cours</span>
  </header>
  <div class="card-body d-flex flex-column gap-3">
    <p>Préparez votre flotte pour l'expédition suivante.</p>
  </div>
  <footer class="card-footer d-flex flex-wrap gap-2 justify-content-end">
    <button type="button" class="btn btn-primary btn-sm">Lancer la mission</button>
    <button type="button" class="btn btn-neutral btn-sm">Voir les détails</button>
  </footer>
</article>
```

## Contrôles de formulaire

```html
<div class="mb-3">
  <label class="form-label" for="field-planet">Nom de la planète</label>
  <input id="field-planet" name="planet" class="form-control" type="text" placeholder="Ex : Andromède" autocomplete="off">
  <p class="form-text">Utilisez un nom unique pour cette colonie.</p>
</div>
<div class="mb-3">
  <label class="form-label" for="field-type">Type de mission</label>
  <select id="field-type" name="mission" class="form-select">
    <option>Exploration</option>
    <option>Commerce</option>
    <option>Combat</option>
  </select>
</div>
<div class="mb-3">
  <label class="form-label" for="field-notes">Instructions</label>
  <textarea id="field-notes" name="notes" class="form-control" rows="4" placeholder="Ajoutez des détails pour la flotte."></textarea>
</div>
<div class="mb-3">
  <label class="form-label" for="field-capacity">Capacité restante</label>
  <input id="field-capacity" name="capacity" class="form-control is-valid" type="number" value="85" aria-describedby="capacity-help">
  <p id="capacity-help" class="form-text">Vos cargos sont prêts à 85&nbsp;%.</p>
</div>
<div class="mb-3">
  <label class="form-label" for="field-error">Code d'autorisation</label>
  <input id="field-error" name="auth" class="form-control is-invalid" type="password" aria-invalid="true" aria-describedby="auth-error">
  <p id="auth-error" class="form-text text-danger" role="alert">Le code fourni est invalide.</p>
</div>
```

## Badges

```html
<span class="badge text-bg-primary">Nouveau</span>
<span class="badge text-bg-warning">Alerte</span>
<span class="badge text-bg-secondary">Neutre</span>
```

## Alertes

```html
<div class="alert alert-warning d-flex gap-3 align-items-start" role="alert" aria-live="assertive">
  <svg class="flex-shrink-0" aria-hidden="true">
    <use href="/assets/svg/sprite.svg#icon-warning"></use>
  </svg>
  <div>
    <h3 class="alert-heading mb-1">Tempête solaire détectée</h3>
    <p class="mb-0">Renforcez les boucliers des colonies exposées avant la prochaine heure galactique.</p>
  </div>
</div>
```

## Onglets / Navigation

```html
<div>
  <ul class="nav nav-pills" id="fleet-tabs" role="tablist" aria-label="Gestion de flotte">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="tab-overview" data-bs-toggle="pill" data-bs-target="#panel-overview" type="button" role="tab" aria-controls="panel-overview" aria-selected="true">
        Aperçu
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-fleet" data-bs-toggle="pill" data-bs-target="#panel-fleet" type="button" role="tab" aria-controls="panel-fleet" aria-selected="false">
        Flotte
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-log" data-bs-toggle="pill" data-bs-target="#panel-log" type="button" role="tab" aria-controls="panel-log" aria-selected="false">
        Journal
      </button>
    </li>
  </ul>
  <div class="tab-content mt-3">
    <div id="panel-overview" class="tab-pane fade show active" role="tabpanel" aria-labelledby="tab-overview">
      <p>Résumé de mission et indicateurs principaux.</p>
    </div>
    <div id="panel-fleet" class="tab-pane fade" role="tabpanel" aria-labelledby="tab-fleet">
      <p>Composition détaillée de la flotte.</p>
    </div>
    <div id="panel-log" class="tab-pane fade" role="tabpanel" aria-labelledby="tab-log">
      <p>Historique des événements récents.</p>
    </div>
  </div>
</div>
```

## Pagination

```html
<nav aria-label="Pagination des rapports">
  <ul class="pagination">
    <li class="page-item disabled">
      <button type="button" class="page-link" aria-label="Page précédente">«</button>
    </li>
    <li class="page-item active" aria-current="page">
      <button type="button" class="page-link">1</button>
    </li>
    <li class="page-item">
      <button type="button" class="page-link">2</button>
    </li>
    <li class="page-item">
      <button type="button" class="page-link">3</button>
    </li>
    <li class="page-item">
      <button type="button" class="page-link" aria-label="Page suivante">»</button>
    </li>
  </ul>
</nav>
```

## Modale avec focus trap

```html
<div class="modal fade show d-block" role="dialog" aria-modal="true" aria-labelledby="fleet-modal-title">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="fleet-modal-title" class="modal-title h4">Planifier une mission</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <p>Définissez la planète cible et le type de mission souhaité.</p>
        <div class="mb-3">
          <label class="form-label" for="modal-target-input">Destination</label>
          <input class="form-control" type="text" name="target" id="modal-target-input" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="modal-mission-select">Mission</label>
          <select class="form-select" name="mission" id="modal-mission-select" required>
            <option value="transport">Transport</option>
            <option value="attack">Attaque</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-neutral" data-bs-dismiss="modal">
          <span class="btn__label">Annuler</span>
        </button>
        <button type="submit" class="btn btn-primary">
          <span class="btn__label">Lancer</span>
        </button>
      </div>
    </div>
  </div>
</div>
```
