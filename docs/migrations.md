# Système de Migration Genesis Reborn

## Vue d'ensemble

Le système de migration de Genesis Reborn permet d'appliquer des changements de schéma de base de données de manière incrémentale, **évitant les pertes de données** lors des mises à jour.

## Caractéristiques principales

### ✅ Sécurité des données
- **Aucune perte de données** : Les migrations sont appliquées de manière incrémentale
- **Suivi automatique** : Table `migrations` pour tracer les migrations appliquées
- **Vérification d'intégrité** : Checksum SHA256 pour détecter les modifications

### ✅ Migration intelligente
- **Détection automatique** : Évite de re-appliquer les migrations déjà effectuées
- **Protection des données** : Skip automatique des migrations destructives si des données existent
- **Transactions atomiques** : Rollback automatique en cas d'erreur

## Commandes disponibles

```bash
# Migration sécurisée (recommandée)
composer db:migrate
```

## Structure des migrations

Les fichiers de migration sont dans `/migrations/` avec le format:
```
YYYYMMDDHHII_description.sql
```

### Migrations critiques

- `202509201200_migration_tracking.sql` - Table de suivi des migrations
- `202509201200_schema_safe.sql` - Schéma non-destructif (CREATE IF NOT EXISTS)
- `202509201200_schema.sql` - **DEPRECATED** - Version destructive (DROP TABLE)

## Fonctionnement

1. **Création de la table de suivi** automatique au premier lancement
2. **Lecture de toutes les migrations** dans `/migrations/`
3. **Vérification des migrations appliquées** via la table `migrations`
4. **Protection anti-destruction** : Skip `20250920_schema.sql` si des données existent
5. **Application sélective** des nouvelles migrations uniquement
6. **Enregistrement du suivi** avec checksum pour l'intégrité

## Migration depuis l'ancien système

Le nouveau système est **rétrocompatible** :

- Les migrations déjà appliquées manuellement sont détectées
- Les données existantes sont préservées automatiquement
- `composer db:create` continue de fonctionner (utilise le nouveau système)

## Sécurité et bonnes pratiques

### ✅ Recommandations
- Utiliser `composer db:migrate` pour toutes les nouvelles installations
- Créer de nouvelles migrations avec `CREATE TABLE IF NOT EXISTS`
- Utiliser `ALTER TABLE` pour les modifications de schéma
- Tester les migrations sur une copie avant production

### ❌ À éviter
- Modifications des migrations déjà appliquées (détectées par checksum)
- Utilisation de `DROP TABLE` sans conditions dans de nouvelles migrations
- Suppression manuelle d'enregistrements dans la table `migrations`

## Résolution de problèmes

### Migration déjà modifiée
```
WARNING: 20250920_schema.sql has changed since last application!
```
**Solution** : Les migrations ne doivent pas être modifiées après application.

### Échec de migration
```
Failed to apply migration XXX: [erreur]
```
**Solution** : La transaction est rollback automatiquement. Corriger l'erreur et relancer.

### Données existantes détectées
```
20250920_schema.sql SKIPPED (destructive, data exists)
```
**Statut** : Normal - Protection automatique contre la perte de données.

## Architecture technique

```
tools/db/
├── migrate.php        # Nouveau système de migration sécurisé
└── create-database.php # Legacy (délègue à migrate.php)

migrations/
├── 202509201200_migration_tracking.sql  # Table de suivi
├── 202509201200_schema_safe.sql         # Schéma sécurisé
├── 202509201200_schema.sql              # Legacy destructif
└── [autres migrations...]           # Migrations incrémentales
```

La table `migrations` contient :
- `filename` : Nom du fichier de migration
- `applied_at` : Timestamp d'application
- `checksum` : Hash SHA256 du contenu pour l'intégrité