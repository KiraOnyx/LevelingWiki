# Valhalla — simplification du suivi et accès propriétaire

## Audit préalable

Architecture conservée : front controller PHP, contrôleurs catalogue/membre/admin, PDO, services Auth/DiscordAuthService/PersonalService, vues partagées Neu, migrations 001–012 et suites Docker isolées.

Incompatibilités constatées : `/admin/login` public et lié dans la navigation et la connexion Discord ; Auth accepte encore les comptes locaux historiques ADMIN/EDITOR. Le suivi associe progression à trois états et tâches dans `personal_achievements` ; Mes succès réutilise exactement le catalogue. L’accueil ne calcule ni récompenses estimées ni catégories ; palette encore verte. Les permissions Discord et la protection du propriétaire sont déjà centralisées et conservées.

Stratégie : route privée configurable, connexion locale exclusivement SUPER_ADMIN, récupération CLI ; migration 013 avec archive des lignes avant conversion binaire ; vues personnelles distinctes alimentées par le même service de synthèse ; thème ardoise/or sans dépendance externe. Aucun compte promu, aucune publication automatique. Tests exclusivement dans la base jetable.

## Fonctionnement livré

- Accueil personnel : hall nordique vectoriel, avatar/pseudo, ascension, catégories réelles, derniers succès obtenus et récompenses estimées.
- Le Codex reste une encyclopédie complète des seuls succès publiés accessibles, indépendante de la progression. Recherche unique dans la navigation, filtres combinables, récompenses et guides conservés.
- Mes succès possède deux onglets : Obtenus et À débloquer. Dates, conditions et actions diffèrent selon l’état. La déclaration se fait sans quitter la fiche ; son retrait exige une confirmation. Les statistiques se recalculent depuis MariaDB.
- Mon profil présente les informations disponibles et le suivi. L’import volontaire de l’ancien localStorage reste disponible ; le navigateur n’est pas une seconde source de vérité pour un compte connecté.
- Les tâches et leurs actions ont disparu de la navigation et des routes. Leurs anciennes données sont archivées.
- Thème ardoise/or partagé avec l’administration ; catégories gardant leurs couleurs, boucliers, illustration SVG locale, composants de progression et récompenses communs. Axentix Neu 2.0.0 reste chargé localement, sans nouveau framework ni police distante.
- Les estimations additionnent les récompenses connues des succès obtenus. Une valeur NULL reste inconnue, zéro reste zéro ; un total partiel est signalé. Le wiki ne distribue aucune récompense dans le jeu.

## Migration 013 et préservation

`013_binary_progress.php` ajoute une archive `personal_achievements_legacy` avant toute conversion. Les anciennes lignes y sont copiées intégralement avec INSERT IGNORE. Les tâches terminées deviennent obtenues ; les dates existantes sont conservées, avec repli sur la date de réalisation puis de modification. Les autres états deviennent non obtenus. La contrainte SQL interdit ensuite les états intermédiaires et les tâches actives. La migration est réexécutable sans écraser l’archive.

Les migrations 001–012 sont inchangées. Aucun succès, récompense, catégorie ou compte n’est supprimé. Une sauvegarde SQL privée complète a été créée avant application dans `storage/backups/pre-valhalla-013.sql` (exclue de Git et de l’image Docker). Cette sauvegarde contient des données sensibles : ne pas la publier.

Pour un retour arrière : arrêter les écritures, sauvegarder aussi l’état courant, restaurer d’abord la sauvegarde antérieure dans un environnement séparé avec la version de code correspondante, vérifier l’intégrité puis planifier la bascule. Une restauration complète écraserait les changements postérieurs : les réconcilier explicitement. L’archive permet une récupération ciblée des anciens états ; ne pas la réinjecter directement sous la nouvelle contrainte binaire.

## Connexion propriétaire

Discord authentifie MEMBER, EDITOR et ADMIN selon les contrôles de rôles existants. La connexion locale accepte exclusivement un SUPER_ADMIN local actif. Aucun compte n’est promu automatiquement, aucun compte Discord n’est lié par pseudo/email. Les comptes locaux historiques restent conservés mais ne peuvent plus utiliser cette connexion.

`OWNER_LOGIN_PATH` doit être renseigné dans `.env`. La commande ci-dessous génère 256 bits aléatoires et écrit la configuration sans afficher le chemin. La route est désactivée si la valeur est absente ou invalide. Les chemins configurés doivent se terminer par 32 à 64 caractères hexadécimaux ; utiliser le générateur pour garantir leur entropie.

```sh
# Génération initiale OU rotation ; conserve les autres variables .env.
docker compose run --rm -T -v "$PWD/.env:/var/www/html/.env:rw" web php bin/owner-access.php --generate --write-env
docker compose up -d web
# À exécuter uniquement dans votre terminal privé pour retrouver le chemin :
docker compose exec web php bin/owner-access.php --show
```

Ouvrir ce chemin sur votre origine locale habituelle. Ne pas partager sa sortie ni la placer dans un rapport. L’ancien chemin retourne 404 après recréation du service. `/admin/login` retourne également 404, sans redirection révélatrice. Aucun lien public ne conduit au formulaire propriétaire ; son formulaire poste vers la page courante sans inscrire le chemin dans ses attributs. Les sessions Discord n’accèdent pas à ce formulaire.

La route impose `X-Robots-Tag: noindex, nofollow, noarchive`, `Referrer-Policy: no-referrer` et les protections de session existantes. La confidentialité du chemin complète le mot de passe, CSRF, limitation des tentatives et permissions ; elle ne les remplace pas.

La configuration Apache fournie ne journalise plus les URI, paramètres ni référents dans ses accès. Elle conserve méthode, protocole, statut et métriques. Si un proxy, CDN ou hébergeur journalise en amont, configurer également son masquage ; cette configuration externe n’est pas contrôlée par le dépôt.

### Récupération du mot de passe

```sh
# Remplacer IDENTIFIANT par celui du propriétaire local existant.
docker compose exec web php bin/owner-access.php --reset-password --username=IDENTIFIANT --confirm-owner=IDENTIFIANT
```

La saisie est masquée et confirmée. La commande vérifie un propriétaire local actif, ne promeut personne, invalide les anciennes sessions et journalise l’opération sans mot de passe. `--password-stdin` existe pour un gestionnaire de secrets ou les tests ; ne pas passer le mot de passe en argument. L’accès système/Docker doit lui-même être réservé aux opérateurs autorisés.

**Limite MFA : TOTP n’est pas implémenté.** Le projet ne possède pas encore de cycle sécurisé d’enrôlement, stockage chiffré du secret et codes de secours à usage unique. La connexion existante n’a pas été affaiblie pour ajouter un mécanisme incomplet. Il n’existe donc ni code de récupération TOTP ni commande de réinitialisation TOTP à utiliser.

## Démarrage et validation

```sh
docker compose config --quiet
docker compose up -d --build
docker compose exec -T web php bin/migrate.php
docker compose ps
# Suite fonctionnelle avec base, réseau et stockage jetables :
sh tests/run-admin.sh
# Tests JavaScript sans service Node permanent :
node --test tests/catalog.test.mjs tests/interface.test.mjs
```

Ne pas utiliser `docker compose down -v` sur l’environnement existant. Les tests fonctionnels utilisent exclusivement le projet `levelingwiki-tests`, puis le démontent. Les données de démonstration ne sont jamais importées dans la base principale.

## Résultats exécutés le 23 octobre 2026

- Suite Docker complète : succès, code de sortie 0. Administration, CSRF, expiration, limitation des tentatives, autorisations, captures valides/dangereuses, importation, catalogue, migrations, audit et API Discord simulée.
- Nouveaux contrôles : navigation sans tâches ni lien propriétaire ; suivi binaire, confirmation du retrait, isolation, statistiques et estimations NULL/zéro ; migration d’anciens états avec conservation des dates et idempotence ; récupération CLI, invalidation de session, rejet d’un non-propriétaire ; rotation HTTP réelle (ancien chemin 404, nouveau formulaire accessible).
- JavaScript : 8 tests réussis, aucun échec.
- Navigateur sur données isolées : connexion propriétaire, accueil, onglets personnels, annulation avec modale, nouvelle déclaration, Codex, focus clavier visible, absence d’erreurs console. Contrôles mobile 390 px et tablette 820 px sans débordement horizontal ; grille tablette à deux colonnes. Captures conservées hors du dépôt.
- Environnement principal : configuration Compose valide, web sur 8080, MariaDB saine ; routes anonymes protégées, fichiers privés inaccessibles ; vérification syntaxique PHP et diff sans erreur. Migration 013 appliquée après sauvegarde et rejouée sans modification.

La connexion Discord réelle n’a pas été rejouée : la suite utilise une API simulée. Les contrôles clavier et responsive ne constituent pas une certification exhaustive WCAG 2.2 AA. Les filtres dynamiques nécessitent JavaScript ; les formulaires de progression conservent une soumission serveur avec confirmation explicite sans JavaScript.

## Inventaire de cette intervention

Le dépôt contenait déjà des changements des phases précédentes ; cet inventaire décrit la refonte, sans attribuer tous les fichiers non suivis à cette seule intervention.

**Créés :**

- `app/Services/OwnerAccess.php`, `bin/owner-access.php`.
- `database/migrations/013_binary_progress.php`.
- `app/Views/partials/ascension.php`, `avatar.php`, `catalog-results.php`, `category-progress.php`, `estimates.php`, `recent.php`.
- `app/Views/member/achievements.php`.
- `public/assets/images/valhalla-hall.svg`.
- `tests/valhalla.php`, `tests/binary-migration.php`, `tests/owner-rotation.php`.
- `docs/valhalla-refonte.md`.

**Adaptés :**

- `public/index.php` ; contrôleurs `AdminController.php`, `CatalogController.php`, `MemberController.php`.
- Services `Auth.php`, `PersonalService.php` ; modèle `AchievementRepository.php`.
- Vues `catalog.php`, `detail.php`, `helpers.php`, `layout.php`, `partials/navigation.php`, `partials/card.php`, `member/home.php`, `member/profile.php`, `member/login.php`, `member/progress-form.php`, `admin/login.php`, `admin/layout.php`.
- `public/assets/css/valhalla.css`, `public/assets/js/app.js`, `public/assets/js/interface.js`.
- `.env.example`, `compose.yaml`, `docker/apache/000-default.conf`, `README.md` ; `.env` local exclusivement pour le chemin propriétaire généré, jamais versionné.
- `tests/compose.yaml`, `tests/http-client.php`, `tests/admin.php`, `tests/phase4.php`, `tests/phase5.php`, `tests/catalog.test.mjs`, `tests/interface.test.mjs`, `tests/run-admin.sh`.

**Retiré :** `app/Views/member/tasks.php`, devenu sans route ni usage. L’historique SQL est conservé dans l’archive de migration.

### Complément de validation — rendu sans JavaScript

Le compteur, l’état vide et l’onglet actif de Mes succès sont désormais calculés dès le rendu PHP, selon la collection sélectionnée. La notice sans JavaScript précise que la déclaration serveur reste utilisable. Trois assertions HTTP vérifient une collection obtenue vide, une collection restante et l’onglet accessible actif.

Calcul de contraste sur le fond de carte `#1A2530` : texte principal `#F2F0E9` 13,63:1 ; secondaire `#A8B0B8` 7,08:1 ; or clair `#E4C98A` 9,64:1 ; texte de modale `#C6CBD0` 9,51:1. Ces quatre combinaisons dépassent 4,5:1 ; il ne s’agit pas d’un audit exhaustif de tous les états visuels.
