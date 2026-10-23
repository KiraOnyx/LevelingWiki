# Phase 3 — authentification et administration

Livraison du 22 octobre 2026. Le catalogue public et son thème Axentix Neu 2.0.0 sont conservés. Aucun compte réel ni succès réel n’a été créé. Les comptes de test utilisent des mots de passe aléatoires, dans le projet Docker éphémère `levelingwiki-tests` uniquement.

## Démarrer et créer le premier administrateur

Depuis la racine du dépôt, avec le `.env` existant :

```sh
docker compose config --quiet
docker compose up -d --build
docker compose exec -T web php bin/migrate.php
docker compose exec web php bin/create-admin.php
```

La dernière commande ouvre une saisie interactive : identifiant, email facultatif (Entrée pour ignorer), mot de passe puis confirmation. La saisie du mot de passe est masquée. Choisir une phrase unique de **12 à 72 octets** (les caractères accentués peuvent occuper plusieurs octets). Il n’existe aucun mot de passe prédéfini. Le mot de passe n’est jamais transmis dans les arguments de commande ni affiché après création.

La commande refuse de créer un nouvel administrateur lorsqu’un administrateur actif existe déjà. Les suivants se créent depuis l’interface. Un verrou SQL sérialise les appels concurrents à la commande. Une variante `--username=... --password-stdin` (option `--email=...` facultative) accepte un secret fourni sur stdin par un gestionnaire de secrets ; ne pas inscrire le secret dans une ligne de commande ou un fichier versionné.

Ouvrir ensuite http://localhost:8080/admin. La connexion accepte uniquement l’identifiant et le mot de passe. L’email est facultatif, modifiable ou supprimable dans la gestion des comptes ; une adresse renseignée reste validée et unique. Les emails des comptes existants sont conservés. Aucune inscription publique. Le diagnostic initial reste accessible sur `/statut`.

## Autorisations

| Action | EDITOR | ADMIN |
| --- | --- | --- |
| Tableau de bord, liste et consultation des brouillons | Oui | Oui |
| Créer / modifier un succès et ses récompenses | Oui | Oui |
| Publier / remettre en brouillon, modifier la vérification | Oui | Oui |
| Joindre / remplacer / retirer une capture | Oui | Oui |
| Associer les titres existants | Oui | Oui |
| Archiver / restaurer / supprimer un succès | Non | Oui |
| Gérer catégories et titres | Non | Oui |
| Gérer les utilisateurs et consulter l’historique | Non | Oui |
| Modifier son propre mot de passe | Oui | Oui |

`AdminGuard` vérifie côté serveur le rôle sur chaque route. Il n’existe aucun accès reposant seulement sur un bouton masqué. Chaque requête authentifiée relit aussi l’état actif et la version de session du compte.

## Authentification et sessions

- `password_hash(PASSWORD_DEFAULT)` avec coût bcrypt 12 sur PHP 8.2 ; vérification `password_verify()` et réhachage si nécessaire. Le réhachage conditionnel ne peut pas écraser une réinitialisation concurrente.
- Mode de session strict, cookies uniquement, `HttpOnly`, `SameSite=Lax`, aucun identifiant dans les URL. Identifiant de session et jeton CSRF renouvelés à la connexion.
- Expiration après **30 minutes d’inactivité** et **8 heures absolues**, configurables. Les sessions ne sont pas restaurées après un redémarrage/recréation du conteneur web.
- Déconnexion par POST, destruction de la session et du cookie. CSRF sur toutes les écritures, y compris connexion, déconnexion et mot de passe.
- Limitation SQL atomique : cinq tentatives échouées par compte dans une fenêtre de 15 minutes ; la tentative suivante reçoit 429. Les variantes de casse de l’identifiant partagent le même compteur de compte. Une connexion réussie remet ce compteur à zéro. Limite complémentaire de 30 tentatives par IP / 15 minutes, connexions réussies incluses. Les compteurs obsolètes sont purgés. Les clés sont des empreintes SHA-256 ; aucun mot de passe ni IP en clair n’y figure.
- IP prise depuis `REMOTE_ADDR`, sans confiance implicite dans `X-Forwarded-For`. Derrière un proxy, configurer correctement l’adresse réelle côté serveur ; sinon les utilisateurs derrière ce proxy partagent la limite IP.
- Messages de connexion génériques, y compris pour un compte désactivé. Un hash factice non lié à un compte assure un travail de vérification pour un identifiant inconnu.

La gestion des utilisateurs demande au responsable ADMIN de confirmer son mot de passe courant. Les créations et réinitialisations imposent un changement personnel à la prochaine connexion. Le mot de passe temporaire choisi doit être transmis par un canal privé ; l’application ne l’affiche ni ne l’envoie par email. Un changement de rôle, une désactivation, une réinitialisation ou un changement personnel incrémente la version de session et invalide les anciennes sessions au prochain accès.

Les modifications d’accès verrouillent les administrateurs actifs dans une transaction avant de vérifier le dernier administrateur : une désactivation ou rétrogradation qui laisserait zéro administrateur actif est refusée.

## Publication, contenu et historique

Les états de publication sont `draft` et `published`, indépendants des quatre statuts de vérification. Les nouveaux succès sont des brouillons par défaut. L’archivage utilise `archived_at` : il retire le succès public sans le supprimer. La restauration rétablit son état de publication précédent.

Le repository **public** applique `publication_status = 'published' AND archived_at IS NULL`. Cette même lecture alimente cartes, recherche, statistiques, suivi local et fiches. Les captures privées vérifient également publication/archivage avant d’autoriser un accès anonyme. Les statistiques administratives comptent tous les succès pour le total, puis les succès non archivés pour les autres indicateurs. « Récompenses à confirmer » compte les succès dont les quatre montants et la liste des titres ne sont pas tous documentés.

Validation serveur : nom, slug ASCII unique, longueurs, choix autorisés, catégorie non archivée, titres existants, montants entiers non signés dans la capacité BIGINT, source HTTP(S) sans identifiants intégrés. Un champ de récompense vide devient NULL ; `0` reste zéro. Les associations de titres sont plusieurs-à-plusieurs. La source correspond à une URL, conformément au champ `source_url` existant.

Une catégorie utilisée par un succès, même brouillon ou archivé, ne peut être archivée ou supprimée. Une catégorie archivée ne peut recevoir de nouvelles associations ; les validations concernées utilisent des verrous SQL. Un titre utilisé ne peut être supprimé.

Les écritures métier et leur événement d’audit partagent la même transaction. L’historique conserve responsable, action, entité, date UTC, valeurs avant/après filtrées. Il distingue notamment création, modification, changement de récompenses, publication/dépublication, archivage/restauration, suppression et opérations sur les comptes. Les champs password/hash/CSRF/session ne sont pas autorisés dans les détails de l’audit. Les sessions et mots de passe ne sont pas journalisés. L’interface présente 50 événements par page.

## Captures justificatives

- Extensions et MIME autorisés : JPEG, PNG et WebP. Contrôle par `finfo`, `getimagesize`, taille effective et dimensions (20 mégapixels maximum), en plus de l’extension. Un nom `.php`, un SVG ou un fichier PHP renommé `.png` est refusé.
- Taille applicative par défaut : **5 Mio**, via `UPLOAD_MAX_BYTES`. Les plafonds PHP Docker sont `upload_max_filesize=8M` et `post_max_size=12M` ; ajuster aussi ces valeurs si la limite applicative est augmentée.
- Nom généré avec 32 octets aléatoires, aucune utilisation du nom original comme chemin. Fichiers créés par le mécanisme d’upload HTTP PHP, permissions 0640.
- Dans Docker, stockage dans `/var/www/storage/uploads`, volume persistant `upload_data`, **hors de `/var/www/html/public` et du montage source en lecture seule**. Le service web peut y écrire ; aucun port ou serveur de fichiers distinct n’est ajouté.
- Route `/media/<nom-aléatoire>` : autorisation basée sur le succès propriétaire, MIME explicite, `nosniff`, CSP avec sandbox, absence de cache. Le serveur ne peut pas exécuter ces fichiers comme PHP, puisqu’ils sont lus comme des octets hors du DocumentRoot.
- Remplacement/retrait/suppression : nettoyage après validation de la transaction. Une erreur avant commit nettoie la nouvelle capture. La suppression n’accepte que les noms générés, vérifie le répertoire réel et refuse les liens symboliques.

Les captures anciennes éventuellement placées manuellement dans `public/assets/images` restent des assets publics. Tous les téléversements de cette phase utilisent le stockage privé. Aucun téléversement réel n’a été fait pendant la livraison. Le contrôle MIME/dimensions n’est pas un antivirus ni un réencodage d’image. Une panne entre commit et nettoyage peut laisser un fichier orphelin non servi ; aucun nettoyage global destructif automatique n’est installé.

## Migrations et compatibilité

Les migrations **001 à 007 restent inchangées**.

- `008_admin_schema.php` : deux rôles `admin`/`editor`, état actif, version de session, obligation de changement du mot de passe ; publication et archivage des succès ; couleur et archivage des catégories.
- `009_login_limits.sql` : compteurs de connexion et index temporel.

Le runner reconnaît désormais les migrations PHP introspectives et les SQL, dans le même ordre numéroté avec verrou et SHA-256. La migration 008 vérifie chaque colonne avant DDL pour permettre une reprise après interruption. Le DDL n’est toujours pas transactionnel.

Par prudence, les succès déjà présents passent à **brouillon** lors de l’ajout du champ de publication : les publier explicitement après contrôle. Les anciens comptes `member` deviennent des éditeurs **désactivés** ; les anciens `moderator` deviennent des éditeurs. Les administrateurs existants conservent leur rôle. La base réelle était vide au moment de la migration.

Aucun Node permanent, Redis ou service dédié. PHP 8.2 avec PDO MySQL et Fileinfo, sessions PHP natives, MariaDB 11.4. Les tests sont réalisés avec MariaDB ; MySQL doit être vérifié sur la version exacte de l’hébergeur.

### Hébergement mutualisé

Conserver `public/` comme seule racine publique. Sur un hébergement sans variables d’environnement, adapter `config/security.php` (aucun secret de compte n’y est nécessaire). Le chemin par défaut hors Docker est `<racine-du-projet>/storage/uploads`, **à maintenir hors de la racine web** ; ce dossier et le répertoire de sessions PHP doivent être inscriptibles par PHP uniquement. Sauvegarder ensemble la base et les captures.

Activer HTTPS et `SESSION_SECURE_COOKIE=1` (ou `APP_ENV=production`, qui impose Secure). Le mode local HTTP conserve Secure désactivé. Les headers de proxy ne sont pas interprétés comme preuve HTTPS par l’application : configurer cette option explicitement derrière un proxy TLS. Ne jamais déployer l’administration en HTTP public.

La migration 008 requiert PHP : exécuter le runner par SSH, terminal de l’hébergeur ou une connexion CLI distante autorisée. Sans aucun moyen d’exécuter ce runner, faire préparer le schéma par l’hébergeur ; ne pas exposer de route HTTP de migration ou de création du premier compte. Le simple import des fichiers SQL seuls ne suffit plus.

## Tests reproductibles

```sh
# Suite complète, environnement jetable créé puis supprimé, même en cas d’échec :
sh tests/run-admin.sh

# Non-régression sur le site réel, avec données transactionnelles annulées :
docker compose exec -T web php tests/database.php
docker compose exec -T web php tests/http.php

# Logique publique de recherche / filtres / tris :
docker run --rm --network none -v "$PWD:/workspace:ro" -w /workspace node:22-alpine node --test tests/catalog.test.mjs
```

Le script isolé utilise exclusivement le projet `levelingwiki-tests`, port 8081, base et captures en tmpfs. Les tests refusent de s’exécuter ailleurs. Ils génèrent des secrets aléatoires et testent la vraie commande de création d’administrateur, puis les vrais endpoints Apache avec cookies et formulaires multipart. Les quatre fixtures de phase 2 restent isolées. Les tests d’expiration utilisent 10 secondes d’inactivité dans ce seul environnement ; ils attendent réellement 11 secondes. Le test de durée absolue vieillit uniquement l’horodatage d’une session éphémère, puis vérifie le refus serveur.

### Résultats obtenus

| Contrôle effectivement exécuté | Résultat |
| --- | --- |
| Compose, construction et démarrage du site réel | Réussis ; MariaDB saine |
| Migrations 008/009 et second passage du runner | Réussis ; aucune réapplication |
| Création du premier compte par CLI sur base isolée | Réussie, secret aléatoire sur stdin et hash vérifié |
| Connexion par identifiant sans email, erreur générique et régénération de session | Réussies |
| Cookies HttpOnly/SameSite, CSRF login et écriture | Vérifiés |
| Déconnexion, expiration inactive et absolue | Vérifiées |
| Compteur partagé entre variantes de casse de l’identifiant et blocage 429 | Vérifiés |
| Accès anonyme et rôle EDITOR insuffisant en GET/POST | Refusés |
| Création/modification des succès, NULL/zéro, validation et unicité | Réussies |
| Publication, brouillon, archivage, restauration, suppression | Vérifiés ; exclusion publique effective |
| Upload PNG, accès privé/public, type MIME, suppression physique | Vérifiés |
| Fichiers PHP, faux PNG, SVG et dépassement de taille | Refusés |
| Traversée de chemin et accès direct au stockage | Refusés (400 Apache ou 404 applicatif) |
| Catégories utilisées, titres partagés entre plusieurs succès | Protections et associations vérifiées |
| Création utilisateur, changement imposé, reset et désactivation | Vérifiés ; anciennes sessions invalidées |
| Dernier administrateur : désactivation et rétrogradation | Refusées |
| Historique et absence de mots de passe/hashes/tokens | Vérifiés |
| Tests SQL, repository et HTTP de phase 2 après la suite | Réussis |
| Tests JavaScript publics via Node 22 dans Docker | 5 tests réussis |
| Inspection navigateur du login, dashboard et formulaire | Réussie ; aucune erreur console relevée |
| Formulaire mobile 390 px et clavier | Une colonne, aucun débordement ; focus visible de 3 px |

Deux attentes de test ont été corrigées : Apache renvoie 400, plutôt que 404, pour une traversée au-dessus de la racine URL ; le cache de métadonnées du processus de test devait être invalidé après suppression par un autre processus. La suppression réelle du fichier était correcte. Le premier montage du volume d’upload sous la source en lecture seule a été déplacé hors de celle-ci avant de poursuivre.

## Limites

Pas d’inscription publique, d’envoi d’email, de récupération automatique du mot de passe, de MFA, d’antivirus, ni d’audit de sécurité externe. Un administrateur réinitialise les autres comptes ; la CLI sert à initialiser un premier administrateur actif. La protection du dernier administrateur est testée en séquence ; les verrous prévus pour la concurrence n’ont pas fait l’objet d’un test de charge concurrent.

Les tables sont consultables dans leur totalité (l’historique est paginé). Pas de versionnement éditorial ni de fusion concurrente : deux éditeurs peuvent écraser successivement leurs modifications. La page publique reçoit toujours le catalogue publié entier, conformément au volume prévu. Les tests visuels sont réalisés dans le navigateur intégré sur macOS, pas sur appareils physiques ni sur un hébergeur distant. Aucun certificat de conformité ou de sécurité n’est revendiqué.

À la livraison initiale de la phase 3, aucun PDF ni succès n’avait été importé. L’import JSON demandé ensuite est documenté ci-dessous.

Références : [sessions PHP](https://www.php.net/manual/en/session.security.ini.php), [authentification OWASP](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html), [CSRF OWASP](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html).

## Inventaire des fichiers

### Créés

- `app/Controllers/AdminController.php`
- `app/Controllers/MediaController.php`
- `app/Middleware/AdminGuard.php`
- `app/Models/AdminRepository.php`
- `app/Services/AchievementService.php`
- `app/Services/Audit.php`
- `app/Services/Auth.php`
- `app/Services/Input.php`
- `app/Services/TaxonomyService.php`
- `app/Services/UploadService.php`
- `app/Services/UserService.php`
- `app/Services/ValidationException.php`
- `app/Views/admin/achievement-form.php`
- `app/Views/admin/achievements.php`
- `app/Views/admin/dashboard.php`
- `app/Views/admin/history.php`
- `app/Views/admin/layout.php`
- `app/Views/admin/login.php`
- `app/Views/admin/message.php`
- `app/Views/admin/password.php`
- `app/Views/admin/taxonomy.php`
- `app/Views/admin/users.php`
- `bin/create-admin.php`
- `config/security.php`
- `database/migrations/008_admin_schema.php`
- `database/migrations/009_login_limits.sql`
- `docs/phase3.md`
- `public/assets/css/admin.css`
- `tests/admin.php`
- `tests/run-admin.sh`

### Modifiés

- `.dockerignore`
- `.env.example`
- `.gitignore`
- `Dockerfile`
- `README.md`
- `app/Models/AchievementRepository.php`
- `app/Services/CatalogService.php`
- `app/Views/layout.php`
- `bin/migrate.php`
- `compose.yaml`
- `docker/php.ini`
- `public/index.php`
- `tests/compose.yaml`
- `tests/database.php`
- `tests/seed.php`

## Email facultatif

La migration `010_optional_user_email.sql` rend `users.email` nullable sans supprimer son index unique ni les adresses existantes. Les champs vides deviennent NULL ; plusieurs comptes peuvent ainsi ne pas avoir d’email. Un champ omis lors d’une mise à jour conserve l’adresse existante, un champ vide la supprime. Appliquer avec `docker compose exec web php bin/migrate.php`. La connexion par email est désormais refusée, y compris pour les anciens comptes : utiliser leur identifiant habituel.

Les tests isolés couvrent la CLI sans option email, les créations HTTP sans email ou avec espaces, la connexion avec et sans adresse enregistrée, l’ajout et la suppression d’adresse, la conservation d’une adresse omise, le rejet d’adresses invalides ou dupliquées et le refus de connexion par email.

## Import du guide communautaire Valhalla (33 succès)

La source fournie est copiée à l’identique dans `database/imports/achievements-valhalla.json` (SHA-256 : `ade5019c0b8177758d468270d255451db610a89a3aac0736e76a871b9edf15e2`). Ce fichier demeure hors du DocumentRoot. Il s’agit du guide communautaire du 14/10/2026, pas d’une preuve officielle. Ses valeurs sont conservées même lorsqu’une capture de référence antérieure montre des récompenses différentes.

### Commandes

Depuis la racine du dépôt, avec Docker démarré et les migrations déjà appliquées :

```sh
# Tests exclusivement sur la base jetable, puis suppression de cet environnement :
sh tests/run-admin.sh

# Aide sans connexion à la base :
docker compose exec -T web php bin/import-achievements.php --help

# Prévisualisation obligatoire avant la décision d’appliquer ; aucune écriture :
docker compose exec -T web php bin/import-achievements.php --file=database/imports/achievements-valhalla.json --dry-run

# Écriture explicite, après réussite des tests et examen du rapport :
docker compose exec -T web php bin/import-achievements.php --file=database/imports/achievements-valhalla.json --apply

# Simulation de relance idempotente :
docker compose exec -T web php bin/import-achievements.php --file=database/imports/achievements-valhalla.json --dry-run
```

Il faut exactement un mode : `--dry-run` ou `--apply`. Sans mode, avec les deux modes, ou avec des options inconnues/répétées, la commande échoue sans écriture. Le programme ne mémorise pas une prévisualisation antérieure ; l’opérateur doit exécuter le dry-run avant apply. La validation complète est répétée dans les deux modes.

Le fichier doit être local et son chemin réel doit appartenir à `database/imports/` ou au répertoire `imports/` frère de `UPLOAD_DIRECTORY` : avec Compose, `/var/www/storage/imports/`. Les liens symboliques qui sortent de ces racines, les URL et les fichiers de plus de 2 Mio sont refusés. Aucun accès HTTP ni dépendance supplémentaire.

### Mapping éditorial

| Source | Destination |
| --- | --- |
| Nom, slug, description | Champs correspondants du succès |
| Description des conditions | Également `achievement_details.unlock_conditions` |
| Bleu, Rose, Jaune, Turquoise, Vert | Catégories de même nom ; couleurs des nouvelles catégories : #609DE8, #DF89BA, #E7C75A, #62D9CA, #65C66B |
| À confirmer (n°33) | `category_id = NULL` ; libellé source conservé dans l’audit |
| Rareté non précisée | `rarity = NULL` ; aucune rareté déduite des couleurs |
| Guide intitulé « succès secrets » | `secret_type = secret`, interprétation du titre du guide, non vérifiée |
| Montants xp/gold/gems/keys | `reward_xp/gold/gems/keys`, NULL conservés, zéros explicites conservés |
| Titres vides | Aucune association créée ; `title_rewards_known = false` |
| Source URL absente, aucune capture | `source_url = NULL`, aucune image |
| Statut éditorial | `draft` et `unverified` pour les 33 |
| Métadonnées source, numéro, note de vérification | Audit `achievement.import`, avec empreinte SHA-256 du JSON |

Le n°33 conserve ses quatre récompenses NULL. Une catégorie existante de même nom est réutilisée, y compris si son slug diffère ; ses couleur et description sont conservées. Une catégorie archivée ou un slug associé à un autre nom provoque un refus explicite, sans réactivation automatique.

### Atomicité, relances et acteur

`AchievementImportService` valide le document entier (33 numéros distincts de 1 à 33, structure et clés strictes, champs obligatoires, noms uniques selon la collation SQL, slugs, catégories, montants entiers non négatifs ou NULL) avant tout appel d’écriture. Les erreurs de lignes indiquent leur index logique dans le tableau `achievements`, à partir de 1.

La création réutilise `AchievementService`, sa validation commune et `TaxonomyService`. Ces services participent à une transaction externe pour les créations sans fichier ; leurs opérations administratives ordinaires gardent leur transaction autonome. Une erreur annule tout le lot, catégories, détails, associations et audit inclus. Les séquences AUTO_INCREMENT peuvent toutefois avancer après un rollback : ce sont des trous d’identifiants, pas des données importées. Un verrou SQL sérialise les imports CLI ; les conflits sont vérifiés à nouveau dans la transaction.

Le couple nom/slug déjà présent est ignoré sans mise à jour, même si ses récompenses, sa description ou sa publication ont évolué. Même slug avec autre nom, ou même nom avec autre slug : échec explicite. Aucun rapprochement approximatif ni écrasement.

L’acteur est le système CLI : `actor_id` et `created_by` NULL, provenance explicite `import_actor = cli:import-achievements` dans chaque audit d’import. Aucun compte technique ou administrateur fictif n’est créé. Les notes sont consultables dans les détails de l’historique ; elles ne deviennent pas une fausse URL source. Les migrations 001–010 ne sont pas modifiées.

Exemples de rapports (le JSON réel contient aussi les détails) :

```text
Prévisualisation : lus=33, valides=33, créés=0, ignorés=0, erreurs=0, à_créer=33
Application     : lus=33, valides=33, créés=33, ignorés=0, erreurs=0, à_créer=33
Relance         : lus=33, valides=33, créés=0, ignorés=33, erreurs=0, à_créer=0
```

### Fichiers de cette évolution

Créés : `bin/import-achievements.php`, `app/Services/AchievementImportService.php`, `database/imports/achievements-valhalla.json`, `tests/import.php`.

Modifiés : `app/Services/AchievementService.php` (validation partagée et participation transactionnelle), `app/Services/TaxonomyService.php` (participation transactionnelle), `app/Services/Audit.php` (champs de provenance autorisés), `tests/run-admin.sh`, `tests/catalog.php`, `tests/http.php`, `docs/phase3.md`.

Les tests d’import couvrent les arguments, chemins et symlinks, la prévisualisation sans aucune écriture, les entrées invalides et conflits, la catégorie existante, les NULL/zéros, les brouillons, les audits, une panne SQL simulée sur la dernière insertion et le rollback intégral, ainsi que deux relances préservant une correction éditoriale. Les tests SQL, administration et catalogue complètent ces contrôles. Le test historique des catégories vérifie maintenant la présence des deux catégories des fixtures, car les catégories de l’import enrichissent normalement cette liste.

### Exécution du 22/10/2026

La suite Docker isolée complète a réussi après correction de l’hypothèse du test des catégories. L’environnement jetable a été supprimé. Le dry-run réel a ensuite annoncé 33 créations sans conflit ; l’application réelle a créé 33 brouillons avec 0 erreur. Le dry-run suivant annonce 33 ignorés et 0 création. Les routes publiques fonctionnent, le catalogue publié reste vide et MariaDB est saine. Aucun succès n’a été publié automatiquement. Le test de rollback forcé et la seconde application effective ont été exécutés uniquement dans la base isolée.
