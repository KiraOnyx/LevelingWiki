# LevelingWiki — Phase 1

Socle technique du futur wiki communautaire des succès de Leveling Fitness. Aucun catalogue, compte utilisateur fonctionnel ou panneau d’administration n’est implémenté. La capture fournie et Axentix Neu serviront à une phase ultérieure, après validation.

## Prérequis et démarrage

Docker Desktop démarré sur macOS (Apple Silicon accepté), Docker Compose et Git. PHP et Composer ne sont pas nécessaires sur l’hôte. Depuis le dépôt :

```sh
open -a Docker
cp .env.example .env
# Renseigner DB_PASSWORD et DB_ROOT_PASSWORD avec deux mots de passe distincts.
# Pour générer chaque valeur : openssl rand -hex 24
chmod 600 .env
docker compose config --quiet
docker compose up -d --build
docker compose exec -T web php bin/migrate.php
```

Ouvrir http://localhost:8080. La page affiche le résultat de `SELECT 1`, sans identifiant ni erreur PDO. Elle renvoie HTTP 200 si la connexion fonctionne et 503 sinon. La connexion seule ne garantit pas que les migrations ont été appliquées.

`.env` est lu par Compose, qui transmet uniquement les variables nécessaires à chaque service. PHP ne charge pas lui-même ce fichier. Le mot de passe root reste réservé au conteneur MariaDB. Ne pas publier la sortie de `docker compose config` sans `--quiet` : elle contient les valeurs résolues.

## Commandes courantes

```sh
# État et logs
docker compose ps
docker compose logs --tail=100 -f web database
# Arrêt, données conservées
docker compose down
# Redémarrage
docker compose up -d
# Migrations (exécutable plusieurs fois)
docker compose exec -T web php bin/migrate.php
# Tests d’intégration (fixtures annulées dans une transaction)
docker compose exec -T web php tests/database.php
# HTTP
curl --fail http://localhost:8080/
# Connexion et disponibilité InnoDB
docker compose exec -T database healthcheck.sh --connect --innodb_initialized
# Syntaxe PHP
docker compose exec -T web sh -c 'find app config public bin tests -name "*.php" -exec php -l {} \;'
```

Réinitialisation **destructive des données locales de ce projet** :

```sh
docker compose down --volumes
docker compose up -d --build
docker compose exec -T web php bin/migrate.php
```

Un changement de mot de passe dans `.env` ne modifie pas les comptes d’un volume déjà initialisé. Pour des données à conserver, modifier les comptes SQL avant d’ajuster `.env` ; sinon réinitialiser le volume. Ne pas supprimer de volume contenant des données utiles sans sauvegarde.

## Architecture

```text
app/
  bootstrap.php
  Controllers/StatusController.php
  Models/.gitkeep
  Services/Database.php
  Middleware/.gitkeep
  Views/status.php
bin/migrate.php
config/
  database.php
  local.php.example
database/
  migrations/
    001_users.sql
    002_categories.sql
    003_titles.sql
    004_achievements.sql
    005_achievement_titles.sql
    006_audit_logs.sql
  seeds/.gitkeep
docker/
  apache/000-default.conf
  php.ini
public/
  index.php
  assets/{css,js,images}/.gitkeep
tests/database.php
.dockerignore
.env.example
.gitignore
Dockerfile
compose.yaml
README.md
```

PHP 8.2/Apache et MariaDB 11.4 utilisent les images officielles multiarchitectures, sans forcer amd64. PDO MySQL utilise des requêtes préparées natives ; UTF-8 complet (`utf8mb4`) et UTC sont configurés. Aucun framework ni dépendance Composer pour ce socle minimal. Les tags suivent les mises à jour correctives ; figer les digests si une reconstruction strictement identique est requise.

Apache sert exclusivement `public/` ; les sources sont montées en lecture seule en développement. Le port web est lié à `127.0.0.1:8080`. MariaDB n’a aucun port publié et conserve ses données dans `database_data`. Le démarrage web attend le healthcheck MariaDB.

## Schéma et migrations

- `users` : pseudo et email uniques, hash du mot de passe, rôle. Aucune authentification implémentée.
- `categories` : classification éditoriale, nom et slug uniques.
- `titles` : titres pouvant être gagnés, nom et slug uniques.
- `achievements` : catégorie facultative, rareté indépendante, type `unknown/normal/secret`, récompenses XP/or/gemmes/clés. La rareté reste un libellé libre tant que le vocabulaire réel n’est pas confirmé.
- `achievement_titles` : relation plusieurs-à-plusieurs, paire unique, suppressions en cascade.
- `audit_logs` : acteur facultatif, action, entité, instant et valeurs JSON avant/après. L’acteur supprimé devient NULL ; la trace reste présente. La cible polymorphe n’a volontairement pas de clé étrangère, pour conserver une trace même après suppression. L’écriture et l’immutabilité applicative des logs seront à implémenter ultérieurement ; ne pas y stocker de mots de passe ou secrets.

Toutes les tables métier ont `created_at` et `updated_at`. Les relations et les filtres métier sont indexés. Les récompenses sont des entiers non signés : `NULL` signifie **inconnue**, `0` signifie **aucune**, une valeur positive est connue. `title_rewards_known` indique si la liste des titres est connue (liste vide + vrai = aucun titre). La cohérence de cette liste sera gérée par les futurs services métier.

Les statuts sont `unverified`, `pending`, `verified`, `rejected`. Un succès vérifié exige une date de vérification ; les autres statuts imposent une date NULL. Le vérificateur peut devenir NULL si son compte est supprimé.

Le runner CLI trie les fichiers SQL, mémorise leurs SHA-256 dans `schema_migrations` et refuse une migration appliquée dont le contenu a changé. Un verrou SQL évite deux exécutions simultanées. Chaque fichier actuel contient une création de table idempotente ; une interruption entre création et enregistrement permet une relance. Le DDL MySQL/MariaDB n’est pas transactionnel : aucun rollback automatique n’est promis. `IF NOT EXISTS` ne répare pas une table modifiée manuellement ; partir d’un schéma vide ou contrôlé. Ajouter de nouvelles migrations pour faire évoluer le schéma, sans modifier celles déjà appliquées. Aucun seed métier fictif n’est ajouté.

## Préparer un hébergement PHP/MySQL gratuit

1. Vérifier PHP >= 8.2, PDO MySQL, InnoDB, utf8mb4 et MariaDB >= 10.6 ou MySQL >= 8.0.16 (contraintes CHECK réellement appliquées). La validation locale porte sur MariaDB 11.4 ; tester sur la version exacte de l’hébergeur avant publication.
2. Créer une base et un utilisateur dédiés avec les droits nécessaires aux migrations. Récupérer l’hôte et le port fournis. Docker n’est pas nécessaire sur l’hébergement.
3. Configurer le DocumentRoot vers `public/`. Garder `app/`, `config/`, `database/` et `bin/` hors de la racine publique. Si l’hébergeur impose `public_html`, y placer le contenu de `public/`, les autres dossiers au niveau parent (le chemin `dirname(__DIR__)` reste valable). Ne jamais téléverser tout le dépôt dans la racine publique.
4. Fournir les cinq variables `DB_*` utilisées par `config/database.php`. À défaut, copier `config/local.php.example` vers `config/local.php`, hors racine publique, y saisir les paramètres et restreindre les permissions. Ce fichier est ignoré par Git et exclu du contexte Docker. Utiliser des secrets distincts de ceux du développement.
5. Exécuter `php bin/migrate.php` via SSH/terminal de l’hébergeur. Sans CLI, importer les six fichiers SQL dans l’ordre via phpMyAdmin sur une base vide ; cela ne crée pas le journal `schema_migrations`. Lors du passage ultérieur au runner, celui-ci enregistrera les créations idempotentes ; vérifier préalablement que le schéma n’a pas divergé. Ne pas créer de route HTTP de migration.
6. Activer HTTPS, désactiver `display_errors` et vérifier que seuls les fichiers publics sont accessibles. Vérifier connexion, contraintes et imports sur une base de test. Si l’hébergeur ne permet pas ces prérequis ou l’isolation des fichiers privés, choisir un autre hébergeur.

Les limites/offres des hébergeurs gratuits évoluent ; aucun fournisseur ni déploiement public n’est choisi en phase 1.

Références techniques : [image PHP officielle](https://hub.docker.com/_/php), [healthcheck de l’image MariaDB](https://github.com/MariaDB/mariadb-docker/blob/master/healthcheck.sh).

## Validation exécutée le 22 octobre 2026

Environnement observé : Docker 29.9.2, Compose 5.6.0, `aarch64`, PHP 8.2.35 et MariaDB 11.4.13.

| Vérification réellement lancée | Résultat |
| --- | --- |
| `docker compose config --quiet` | Succès |
| `docker compose up -d --build` | Construction et démarrage réussis |
| HTTP `localhost:8080` | 200, « Projet initialisé », « connexion établie » |
| Healthcheck MariaDB et `SELECT 1` via PDO | Succès |
| Premier passage des migrations | Six migrations appliquées |
| Deuxième passage | Six migrations déjà appliquées, aucun doublon |
| `php tests/database.php` | Tables, récompenses, unicité, clés étrangères, contraintes de vérification et suppressions : succès ; fixtures annulées |
| Vérification syntaxique PHP | Huit fichiers PHP valides |
| Accès HTTP à `.env`, configuration et migration SQL | 404 pour les trois requêtes |
| Mot de passe erroné injecté dans un processus PHP isolé | Code 503 et texte générique, sans mot de passe ni erreur PDO |
| Git | `.env` ignoré ; `git diff --check` réussi |

Les accès au moteur Docker et au port HTTP ont nécessité de sortir du bac à sable de l’agent. Après autorisation technique, les vérifications ont réussi. Les services sont laissés démarrés. Aucun déploiement distant, test sur MySQL distinct ou effacement du volume n’a été effectué. Aucun blocage local restant.
