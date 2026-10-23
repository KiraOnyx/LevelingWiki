# LevelingWiki

> **Refonte Valhalla : Codex, suivi obtenu/non obtenu et connexion propriétaire privée.** Consulter [la documentation actuelle, les tests et la récupération propriétaire](docs/valhalla-refonte.md). Les documents des phases 3–5 sont historiques : leurs anciennes instructions de connexion locale et de tâches sont remplacées par cette refonte.

Wiki communautaire indépendant consacré aux succès de Leveling Fitness : PHP 8.2/Apache, MariaDB 11.4, PDO, Axentix Neu 2.0.0 et JavaScript natif. Catalogue privé, comptes Discord, propriétaire local indépendant, administration, progression personnelle persistante. Les succès importés ne sont jamais publiés automatiquement.

## Démarrage entièrement avec Docker

Docker Desktop doit être démarré. Ni PHP, ni Composer, ni Node ne sont requis sur l’hôte.

```sh
open -a Docker
# Premier démarrage seulement : copier .env.example vers .env.
# Renseigner deux mots de passe différents (openssl rand -hex 24 pour chaque valeur).
cp .env.example .env
chmod 600 .env
docker compose config --quiet
docker compose up -d --build
docker compose exec -T web php bin/migrate.php
```

Si `.env` existe déjà, le conserver. Les migrations peuvent être rejouées. Ne pas partager la sortie de `docker compose config` sans `--quiet` : elle contient les secrets résolus.

- Accueil : http://localhost:8080/
- Catalogue : http://localhost:8080/succes
- Fiche : `/succes/<slug>` pour un succès existant.
- Administration : http://localhost:8080/admin
- Diagnostic de phase 1 conservé : http://localhost:8080/statut

Le service web est lié à `127.0.0.1:8080`. MariaDB n’a pas de port publié. Les fichiers privés restent hors du DocumentRoot `public/`. Les sources sont montées en lecture seule. Images officielles ARM64, volume SQL persistant, healthcheck MariaDB.

## Commandes utiles

```sh
docker compose ps
docker compose logs --tail=100 -f web database
docker compose down                 # Arrêt, données conservées
docker compose up -d                # Redémarrage
docker compose exec -T web php bin/migrate.php
docker compose exec -T web php tests/database.php
docker compose exec -T web php tests/http.php
```

Réinitialisation **destructive des seules données locales du projet** :

```sh
docker compose down --volumes
docker compose up -d --build
docker compose exec -T web php bin/migrate.php
```

Un changement de mot de passe dans `.env` ne modifie pas les comptes d’un volume existant. Modifier d’abord les comptes SQL pour conserver les données, ou réinitialiser le volume si elles sont jetables. Sauvegarder les données utiles avant toute suppression.

## Organisation

```text
app/
  Controllers/       Catalogue, statut, administration et médias privés
  Models/            Repositories distincts pour données publiques et administration
  Services/          Données, authentification, validation, uploads et audit
  Middleware/        AdminGuard : autorisations côté serveur
  Views/             Vues publiques, partials/ et admin/
bin/                 Migrations et création du premier administrateur
config/             Configuration par environnement ou local.php privé
database/
  migrations/       001–007 inchangées ; 008_admin_schema.php, 009_login_limits.sql
  seeds/             Aucun seed métier de production
public/
  index.php          Routage uniquement
  .htaccess          Routage Apache pour un hébergement compatible
  assets/
    css/theme.css, admin.css
    js/app.js, catalog-core.mjs
    images/emblem.svg
    vendor/neu-axentix/neu-axentix.min.css, LICENSE
docker/              Apache et PHP
tests/               Tests SQL/HTTP/JS, fixtures et Compose isolé
docs/                Rapports des phases 1 et 2
```

## Interface et règles métier

Axentix Neu 2.0.0 est livré localement avec sa licence MIT. Les classes documentées `neu-dark`, `neu-flat` et `neu-pressed` servent de base au thème vert sombre. Aucune dépendance CDN à l’exécution, aucun framework frontend, police système. Les autres classes sont propres à LevelingWiki. Voir les [couleurs officielles](https://neu.useaxentix.com/docs/colors/) et les [ombres officielles](https://neu.useaxentix.com/docs/shadows/).

Le catalogue est rendu côté PHP. Le navigateur reçoit seulement les champs publics des succès pour rechercher, filtrer et trier sans appels réseau supplémentaires. Recherche normalisée en français (casse, accents, œ), temporisation de 250 ms. État des filtres conservé dans l’URL. Sans JavaScript, cartes et fiches restent lisibles ; filtres et suivi local sont masqués avec une explication.

- Les quatre statistiques portent sur les seuls succès publiés et non archivés, indépendamment des filtres. « Récompenses connues » compte les **succès dont les quatre montants et la liste des titres sont documentés**. Zéro est connu ; NULL est inconnu.
- Les récompenses connues sont affichées même si d’autres restent à confirmer. Un montant NULL n’est jamais converti en zéro. Les montants sont transmis comme chaînes pour préserver les BIGINT lors du tri.
- Les filtres de récompenses cochés sont combinés par **ET** : un montant positif ou au moins un titre est requis. Un montant zéro n’est pas une récompense accordée.
- « À confirmer » regroupe tous les statuts autres que `verified`. Les types inconnus ne sont ni secrets ni classiques. Les raretés attendues sont les libellés français Commun, Rare, Épique et Légendaire (casse/accents ignorés), ou NULL.
- Les montants inconnus restent en fin de tri, dans les deux sens. « Date d’ajout » affiche les plus récents d’abord.
- Le suivi utilise `localStorage`, clé `levelingwiki.obtained.v1`, par slug. Les entrées qui ne correspondent plus au catalogue ne comptent pas. Le pourcentage se rapporte au catalogue complet. Le stockage est propre à l’origine et au navigateur ; aucun envoi au serveur. En cas de stockage interdit, une indication prévient que le suivi ne durera que la visite.

La septième migration ajoute une table facultative `achievement_details` en relation 1:1 : conditions de déblocage, chemin d’icône, chemin de capture et horodatages. Les images référencées doivent être locales dans `/assets/images/`, sans sous-dossier, au format PNG/JPEG/WebP/AVIF. Une médaille SVG locale sert d’icône de remplacement. Les nouvelles captures administratives sont stockées hors de public/ et servies par `/media/` après contrôle d’accès ; voir la documentation de phase 3. La date de modification de la fiche est le maximum entre le succès et ses détails ; les écritures administratives sur les titres associés actualisent aussi le succès.

Les six migrations initiales restent inchangées. Les détails des conventions SQL et du runner se trouvent dans le [document historique de phase 1](docs/phase1.md). Ajouter des migrations ; ne pas modifier les fichiers déjà appliqués.

## Tests isolés

Les fixtures ne sont jamais destinées à la base réelle. `tests/seed.php` exige à la fois `APP_ENV=test`, `DB_DATABASE=levelingwiki_test` et un catalogue vide. Le Compose de test crée un réseau indépendant et une MariaDB **en mémoire (tmpfs)**. Son port web est 8081 ; aucun port SQL n’est publié.

Depuis la racine :

```sh
docker compose --env-file .env -f tests/compose.yaml up -d --build
docker compose --env-file .env -f tests/compose.yaml exec -T web php bin/migrate.php
docker compose --env-file .env -f tests/compose.yaml exec -T web php tests/seed.php
docker compose --env-file .env -f tests/compose.yaml exec -T web php tests/catalog.php
docker compose --env-file .env -f tests/compose.yaml exec -T web php tests/http.php
# Inspecter les quatre fixtures sur http://localhost:8081/succes
# Puis supprimer l’environnement jetable :
docker compose --env-file .env -f tests/compose.yaml down
```

Tests de la logique JavaScript, également sans Node sur l’hôte :

```sh
docker run --rm --network none -v "$PWD:/workspace:ro" -w /workspace node:22-alpine node --test tests/catalog.test.mjs
```

Syntaxe PHP :

```sh
docker compose exec -T web sh -c 'find app config public bin tests -name "*.php" -exec php -l {} \;'
```

Les parcours navigateur vérifiés et leurs limites sont consignés dans le [rapport de phase 2](docs/phase2.md).

## Préparer un hébergement PHP/MySQL gratuit

1. Vérifier PHP >= 8.2, PDO MySQL, InnoDB, utf8mb4, MariaDB >= 10.6 ou MySQL >= 8.0.16 (CHECK appliqués). Les tests locaux portent sur MariaDB 11.4, pas sur un MySQL distant.
2. Créer une base et un utilisateur dédiés. Configurer les variables `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Sans variables d’environnement, copier `config/local.php.example` vers `config/local.php`, hors racine publique, avec des secrets propres au serveur et des permissions restreintes. PHP ne lit pas `.env` lui-même.
3. Pointer la racine web sur `public/`. Si l’hébergeur impose `public_html`, y copier le contenu de `public/` (y compris `.htaccess`) et placer les autres dossiers au niveau parent. Ne pas publier `.env`, `.git`, `tests/`, `database/` ou `config/`.
4. Autoriser les directives Apache de `public/.htaccess`, ou configurer l’équivalent de `FallbackResource /index.php` et le MIME JavaScript de `.mjs`. Les routes sont conçues pour un déploiement à la racine du domaine. Sur un autre serveur, configurer son routage équivalent.
5. Exécuter `php bin/migrate.php` par CLI. Sans CLI, la migration PHP 008 nécessite une exécution du runner par un terminal autorisé ou l’assistance de l’hébergeur. Importer les SQL seuls ne suffit plus. Ne pas exposer de route HTTP de migration.
6. Activer HTTPS, masquer les erreurs PHP, vérifier le catalogue, une fiche, les assets `.mjs` et les 404 sur les chemins privés. Tester sur la version exacte du serveur. Si l’isolation des fichiers privés ou le routage ne sont pas possibles, choisir un autre hébergeur.

Aucun fournisseur ni déploiement public n’est sélectionné ici. L’import des 33 succès reste hors périmètre.

## Administration — phase 3

Après le démarrage et les migrations :

```sh
docker compose exec web php bin/create-admin.php
```

L’email est facultatif (Entrée pour ignorer). La connexion utilise uniquement l’identifiant et le mot de passe. Saisir les informations demandées ; le mot de passe est masqué, jamais prédéfini. Connexion sur http://localhost:8080/admin. Les éditeurs créent, modifient et publient les succès ; les administrateurs gèrent aussi les utilisateurs, catégories, titres, archives et suppressions. Les brouillons restent privés.

Les captures résident dans le volume Docker `upload_data`, hors de la racine web. Sauvegarder ce volume avec la base. `docker compose down --volumes` supprime désormais **la base et les captures**. Sur un hébergement public, HTTPS et cookies Secure sont obligatoires.

```sh
# Suite HTTP avec comptes aléatoires et données isolées, nettoyage automatique :
sh tests/run-admin.sh
```

Voir le [guide complet de phase 3](docs/phase3.md) pour les rôles, sessions, limites de connexion, uploads, migrations, résultats de tests et adaptation à un hébergement mutualisé.

## Activation de la phase 4

Après `docker compose up -d --build` et `docker compose exec -T web php bin/migrate.php`, désigner explicitement le propriétaire local existant :

```sh
docker compose exec web php bin/designate-owner.php --username=ArKii --confirm-owner=ArKii
```

Le mot de passe actuel est demandé de façon masquée. Cette opération reste à effectuer par le propriétaire. Les anciens comptes locaux restent conservés mais seul le SUPER_ADMIN accède au wiki sans Discord.

Renseigner `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`, `DISCORD_GUILD_ID`, `DISCORD_REDIRECT_URI`, puis ajouter les Role ID dans `/admin/settings/discord`. Consulter le [guide complet et les tests réels](docs/phase4.md). Le simulateur Discord de la suite de tests n’est jamais activé dans le Compose principal.
