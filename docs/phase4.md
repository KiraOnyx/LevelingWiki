# Phase 4 — Discord, permissions et suivi personnel

Livraison du 23 octobre 2026. Le projet PHP/Docker existant est conservé. Le catalogue est désormais privé ; aucun succès importé n’est publié automatiquement. Le contrôle final de la base réelle relève 32 brouillons et un succès déjà publié (« Même jour, autre monde », journal du 22 octobre à 17:41:09), conservés sans changement. Aucun compte réel Discord n’est créé par les tests et aucun compte local n’est promu automatiquement.

## Mise en service locale

Depuis la racine du dépôt, conserver le fichier `.env` existant :

```sh
docker compose config --quiet
docker compose up -d --build
docker compose exec -T web php bin/migrate.php
```

Les migrations 001–010 sont inchangées. Les migrations 011 et 012 sont additives ; la migration PHP 011 vérifie ses étapes pour permettre une relance après interruption. Le runner conserve son verrou et ses empreintes.

### Désigner explicitement le propriétaire

Le compte local existant observé est `ArKii`, ADMIN actif. Son identifiant, son email facultatif et son mot de passe sont conservés. Pour le désigner comme propriétaire, exécuter vous-même :

```sh
docker compose exec web php bin/designate-owner.php --username=ArKii --confirm-owner=ArKii
```

Saisir son mot de passe actuel dans le terminal : la saisie est masquée. La commande vérifie le mot de passe, le statut actif et le rôle ADMIN local, impose une confirmation explicite de l’identifiant, refuse si un propriétaire existe déjà, puis journalise la promotion. Une contrainte unique SQL interdit un second SUPER_ADMIN. Une contrainte interdit ce rôle à un compte Discord. Les anciennes sessions sont invalidées ; reconnectez-vous sur **http://localhost:8080/admin/login**.

Cette commande n’a pas été exécutée sur le compte réel : votre validation est requise. Ne transmettez pas votre mot de passe dans le chat. L’option `--password-stdin` existe pour les tests et les gestionnaires de secrets, jamais pour inscrire le secret dans un argument ou un fichier versionné.

Sur une installation sans aucun administrateur local : créer d’abord le compte avec `docker compose exec web php bin/create-admin.php`, puis le désigner explicitement avec la commande ci-dessus et son propre identifiant.

Les anciens comptes locaux ADMIN/EDITOR ne sont ni supprimés ni associés à Discord. Ils peuvent encore vérifier leur mot de passe local et le changer, mais ils ne reçoivent plus de droits sur le wiki tant qu’ils ne sont pas le propriétaire désigné. Ce changement est nécessaire pour appliquer les conditions Discord demandées aux membres, éditeurs et administrateurs. L’administration des comptes est réservée au propriétaire. La désactivation/rétrogradation du propriétaire est refusée ; son changement de mot de passe reste possible avec vérification du mot de passe actuel.

## Configurer Discord

1. Ouvrir le [portail développeur Discord](https://discord.com/developers/applications), créer une application ou choisir une application que vous contrôlez.
2. Dans **General Information**, relever l’**Application ID / Client ID**.
3. Dans **OAuth2**, obtenir le **Client Secret** (Discord peut proposer de le réinitialiser). Le placer uniquement dans le `.env` serveur. Ne pas l’inscrire dans Git, une capture ou un message.
4. Dans les redirections OAuth2, ajouter exactement `http://localhost:8080/auth/discord/callback`. Les différences de protocole, port, chemin ou slash comptent. Utiliser HTTPS sur un hébergement distant.
5. Dans Discord, activer **Paramètres utilisateur > Avancé > Mode développeur**, puis copier l’identifiant du serveur via son menu contextuel. Remplacer `xxxx` par cet identifiant réel.
6. Renseigner le `.env` :

```dotenv
DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=
DISCORD_GUILD_ID=xxxx
DISCORD_REDIRECT_URI=http://localhost:8080/auth/discord/callback
DISCORD_ROLE_TTL=300
```

7. Appliquer la configuration : `docker compose up -d`. Le fichier `.env` est lu par Compose ; aucun secret n’est renvoyé dans le HTML.
8. Se connecter comme SUPER_ADMIN local, puis ouvrir **Administration > Paramètres Discord**, route `/admin/settings/discord`.
9. Dans Discord, copier les **Role ID** des rôles choisis (mode développeur). Ajouter au moins un ID à la liste **MEMBER**, avec un libellé administratif libre.
10. Ajouter les IDs **EDITOR** et **ADMIN** voulus. Seul le SUPER_ADMIN peut modifier la liste ADMIN. Les ADMIN peuvent gérer MEMBER et EDITOR.
11. Un changement de libellé n’altère pas l’identité du rôle. Les noms sont saisis manuellement : aucune récupération de la liste complète des rôles via bot n’est nécessaire.
12. Les paramètres OAuth2 sensibles restent une configuration serveur, modifiée par le propriétaire ayant accès au `.env`/hébergeur. L’interface ne permet à aucun ADMIN Discord de les lire ou de les modifier ; elle ne propose pas d’édition web du secret.

Ne pas ajouter de bot au serveur pour ce flux. Les seuls scopes demandés sont `identify` et `guilds.members.read`. L’autorisation OAuth2 du membre permet de lire son identité et ses propres rôles dans le serveur configuré. Le rôle Discord `@everyone` n’est pas utilisé implicitement : configurer un rôle MEMBER attribué explicitement.

## Permissions appliquées côté serveur

| Niveau | Conditions | Accès |
| --- | --- | --- |
| MEMBER | Discord valide, membre du serveur, au moins un Role ID MEMBER autorisé | Catalogue publié, captures autorisées, progression, tâches |
| EDITOR | MEMBER + un Role ID EDITOR | MEMBER + création/modification, récompenses, captures, publication/dépublication |
| ADMIN | MEMBER + un Role ID ADMIN | EDITOR + catégories/titres, archivage/restauration, audit, gestion des listes MEMBER/EDITOR |
| SUPER_ADMIN | Compte local unique désigné explicitement | Tous les droits, comptes, liste ADMIN et suppressions définitives |

ADMIN implique EDITOR sans exiger un rôle EDITOR. Un rôle EDITOR ou ADMIN sans MEMBER ne donne aucun accès. Aucun ID Discord ne peut octroyer SUPER_ADMIN. Les comptes Discord sont identifiés exclusivement par leur Discord User ID unique ; le pseudo et l’avatar sont actualisés à chaque connexion. Aucun rapprochement par pseudo ou email n’est effectué.

Les droits sont recalculés à la connexion et au plus tard après 300 secondes pendant la lecture (délai configurable entre 1 et 300 secondes). Les accès administratifs, les captures non publiées et les écritures personnelles forcent une vérification Discord récente. Toute modification d’une liste ou d’un libellé de rôle incrémente la version globale de politique : les caches sont invalidés dès la requête suivante. Une erreur réseau, un token invalide, une adhésion en attente ou une réponse incohérente refuse l’accès ; aucun ancien privilège sensible n’est réutilisé. Le compte local propriétaire n’appelle pas Discord.

Un membre qui perd MEMBER conserve son compte, sa progression et ses tâches. Un compte désactivé localement est refusé même si ses rôles Discord seraient suffisants. Pour une modification de politique survenant exactement pendant une requête déjà autorisée, la nouvelle politique s’applique au plus tard à la requête suivante.

## Flux OAuth2 et confidentialité

- Départ : POST `/auth/discord`, CSRF obligatoire.
- Callback : GET `/auth/discord/callback`.
- Déconnexion : POST `/deconnexion`, CSRF obligatoire ; suppression de la session locale et tentative de révocation du grant Discord.
- Authorization Code avec `state` aléatoire de 256 bits, lié à la session, expirant après dix minutes et consommé une seule fois, même lors d’un échec.
- PKCE **S256**, code verifier côté serveur ; client secret envoyé uniquement au token endpoint.
- Échanges HTTPS vérifiés, délai réseau limité, redirections API interdites, taille de réponse limitée, validation du type/scopes/durée des tokens et de l’identité. Issuer vérifié lorsqu’il est présent.
- Access/refresh tokens uniquement dans la session PHP serveur, jamais dans localStorage, cookies lisibles, HTML, base métier ou audit. Le rafraîchissement s’effectue côté serveur.
- Session régénérée après connexion, cookies HttpOnly/SameSite, expiration inactive et absolue conservées. Activer `SESSION_SECURE_COOKIE=1` et HTTPS en production.
- Les logs Apache n’enregistrent plus les query strings : ni code OAuth ni state dans les URLs journalisées. Ne pas réintroduire les paramètres d’URL dans les logs d’un reverse proxy.
- En cas d’échec de révocation distante lors de la déconnexion, la session locale est tout de même détruite. L’utilisateur peut révoquer l’application dans les applications autorisées Discord.
- Sauvegarder/protéger le répertoire des sessions PHP hors de la racine publique. Une suppression des sessions demande une reconnexion, sans perte de progression.

Références officielles : [OAuth2 Discord](https://docs.discord.com/developers/topics/oauth2), [métadonnées OAuth2 et support S256](https://discord.com/.well-known/oauth-authorization-server), [Get Current User Guild Member](https://docs.discord.com/developers/resources/user#get-current-user-guild-member).

## Catalogue privé et captures

Un visiteur anonyme est redirigé vers `/connexion`. Un compte sans autorisation reçoit une réponse 403 sans contenu du catalogue. Le JSON qui alimente recherche, filtres et tris n’est généré qu’après autorisation. Aucune API ou export public n’a été ajouté.

Les captures `/media/...` exigent un compte autorisé ; les brouillons et archives ne sont accessibles qu’aux contributeurs ayant les permissions d’édition. Les anciennes images matricielles sous `public/assets/images` passent également par le contrôleur via une règle Apache : un fichier existant ne contourne pas le contrôle. Seuls les chemins associés à un succès autorisé sont servis. Les SVG d’identité graphique restent des assets publics ; aucune capture SVG n’est acceptée. Pour un autre serveur HTTP, reproduire cette réécriture avant de déployer.

## Progression et tâches

Le profil est accessible sur `/profil`, les tâches sur `/mes-taches`. Une tâche est un succès poursuivi, pas un second catalogue.

Une seule table `personal_achievements` contient l’état de progression et le cycle de vie de la tâche pour le couple unique compte/succès. Les contraintes SQL interdisent une tâche active sans progression « En cours », ou une tâche terminée sans succès « Obtenu ».

- **Accepter** un succès publié et non archivé crée une tâche active, marque « En cours » et enregistre la date d’acceptation.
- Une seconde acceptation est refusée. Un succès déjà obtenu doit d’abord être réinitialisé volontairement.
- **Terminer** enregistre la réalisation et marque le succès obtenu.
- **Abandonner** conserve la tâche et remet « Non commencé », sauf si le succès était déjà obtenu.
- **Reprendre** une tâche abandonnée réutilise sa ligne et sa date d’acceptation initiale.
- Modifier la progression en « Obtenu » termine aussi la tâche associée. « En cours » réactive la tâche ; « Non commencé » l’abandonne.
- Les tâches dont le succès devient indisponible restent dans l’historique sous « Succès indisponible », sans nom, description, catégorie, récompenses ou lien privé. Elles ne peuvent être nouvellement acceptées, reprises ou terminées ; l’abandon reste possible.
- Une suppression définitive par le propriétaire conserve la ligne personnelle avec référence de succès NULL ; aucun contenu supprimé n’est recopié dans le profil.
- Les statistiques globales de progression portent sur les succès actuellement disponibles. Les compteurs de tâches conservent également l’historique des tâches devenues indisponibles.
- Chaque écriture exige authentification, CSRF et validation serveur ; l’utilisateur cible vient de la session, jamais de la requête.

Le suivi local historique est conservé. Dans « Votre aventure », choisir **Mon compte** ou **Ce navigateur uniquement**. Le profil propose une importation volontaire, avec confirmation, des succès locaux obtenus encore publiés. Elle fusionne vers l’état obtenu sans effacer localStorage et sans importer de brouillon.

## Tester avec un vrai compte Discord

1. Désigner le propriétaire et remplir le `.env`, puis configurer les listes de rôles comme ci-dessus.
2. Ouvrir une fenêtre privée sur **http://localhost:8080**, cliquer « Se connecter avec Discord », choisir le compte et consentir aux deux scopes.
3. Avec le rôle MEMBER autorisé, vérifier l’accès au catalogue et à `/profil`.
4. Sans MEMBER, vérifier la page d’accès refusé, y compris avec un rôle EDITOR ou ADMIN seul.
5. Ajouter MEMBER + EDITOR : vérifier création/modification et publication, et le refus de `/admin/settings/discord`.
6. Ajouter MEMBER + ADMIN, sans EDITOR : vérifier l’édition, la gestion de MEMBER/EDITOR, et le refus de modification de la liste ADMIN, des comptes et des suppressions définitives.
7. Revenir au compte local propriétaire pour vérifier la gestion de la liste ADMIN.
8. Pour tester réellement une tâche, utiliser un succès déjà publié ou publier **volontairement** un succès contrôlé ; aucune commande de cette phase ne le fait automatiquement.
9. Sur sa fiche, « Accepter la tâche », puis dans « Mes tâches » vérifier En cours, Abandonner, Reprendre, Terminer et le compteur obtenu du profil.
10. Se déconnecter et se reconnecter : retrouver la même progression et la même tâche, sans doublon.
11. Retirer MEMBER dans Discord : l’accès de lecture disparaît au plus tard à l’expiration des 300 secondes ; une écriture sensible force le contrôle immédiatement. Retirer EDITOR/ADMIN : vérifier le refus des écrans/actions concernés.
12. Retirer un Role ID autorisé dans les paramètres du wiki : la requête suivante doit revalider l’accès, sans attendre 300 secondes.
13. Couper temporairement l’accès réseau à Discord dans un environnement de test : vérifier le refus sensible et le maintien de la connexion locale propriétaire.
14. Ne pas partager le Client Secret ou les tokens pour diagnostiquer une erreur ; vérifier callback exact, Guild ID, rôles et consentement.

## Tests automatisés et résultats

```sh
# Environnement jetable indépendant, simulateur Discord interne sans port hôte :
sh tests/run-admin.sh

# Logique JavaScript indépendante du serveur Discord :
docker run --rm --network none -v "$PWD:/workspace:ro" -w /workspace node:22-alpine node --test tests/catalog.test.mjs

# Smoke HTTP anonyme de l’environnement courant :
docker compose exec -T web php tests/http.php
```

Le simulateur n’est activable que si `APP_ENV=test` **et** `DB_DATABASE=levelingwiki_test`. Il ne figure pas dans le Compose principal. Aucun appel Discord réel n’est effectué par la suite.

Scénarios exécutés : OAuth valide avec S256 et scopes contrôlés, state invalide/rejoué, code et token invalides, absence du serveur, MEMBER manquant, rôles multiples, EDITOR/ADMIN sans MEMBER, révocations, version de politique, panne API, rafraîchissement, unicité du propriétaire, pseudo Discord identique au compte local, création/reconnexion sans doublon, mise à jour du pseudo, CSRF, isolation personnelle, acceptation/abandon/reprise/validation, dates et synchronisation, réinitialisation volontaire, migration locale, brouillon/archivage, historique masqué, secret absent de l’audit et de l’HTML.

La suite existante d’administration a été adaptée au propriétaire local et à un éditeur Discord simulé. Elle continue à vérifier les mots de passe, sessions, rate limiting, uploads, NULL/zéro, publication et audit. L’import atomique des 33 succès et ses tests d’idempotence/rollback restent exécutés sur la base jetable. Les tests HTTP anonymes attendent désormais 303 vers la connexion pour le catalogue.

Les cinq tests JavaScript ont réussi. Les parcours navigateur ont été inspectés sur les données isolées : connexion propriétaire, profil, acceptation et validation d’une tâche ; affichage mobile à 390 px sans débordement horizontal et aucune erreur console relevée.

## Limites et activation restant à effectuer

- La connexion Discord réelle n’a pas été testée : Client ID/Secret, Guild ID réel et Role ID doivent être fournis par le propriétaire.
- La désignation réelle d’ArKii comme SUPER_ADMIN attend sa confirmation explicite et son mot de passe dans la CLI. Aucun faux propriétaire de production n’est créé.
- Les paramètres OAuth sensibles sont modifiés sur le serveur, pas dans un formulaire web. La gestion des Role ID est disponible dans l’interface.
- Sessions PHP sur disque, sans Redis ; la reconnexion est requise après perte/expiration des sessions. La progression reste en MariaDB.
- Aucun bot, synchronisation en arrière-plan, historique complet de chaque cycle de tâche, MFA locale, email de récupération ou second catalogue de quêtes.
- Tests MariaDB/PHP 8.2 Docker ARM64 et navigateur intégré ; aucun déploiement chez un hébergeur mutualisé ni test de charge concurrent en conditions réelles.
- Les rôles Discord et l’appartenance peuvent changer pendant une requête déjà autorisée ; le contrôle suivant applique la nouvelle politique. Les opérations sensibles ne se fondent pas sur le cache de lecture.

## Inventaire des fichiers de la phase 4

### Créés

- `database/migrations/011_discord_identity.php` : fournisseurs d’identité, propriétaire unique, identités Discord, rôles autorisés et version de politique.
- `database/migrations/012_personal_tracking.sql` : progression et tâches, contraintes de cohérence, dates et index.
- `config/discord.php`
- `bin/designate-owner.php`
- `app/Services/OwnerService.php`
- `app/Services/DiscordClient.php`
- `app/Services/DiscordAuthService.php`
- `app/Services/DiscordRoleService.php`
- `app/Services/PermissionService.php`
- `app/Services/PersonalService.php`
- `app/Controllers/MemberController.php`
- `app/Views/member/login.php`
- `app/Views/member/denied.php`
- `app/Views/member/profile.php`
- `app/Views/member/progress-form.php`
- `app/Views/member/tasks.php`
- `app/Views/admin/discord.php`
- `tests/discord-mock.php`
- `tests/discord-support.php`
- `tests/http-client.php`
- `tests/phase4.php`
- `docs/phase4.md`

### Modifiés

- `.env.example`, `compose.yaml`, `Dockerfile`, `docker/apache/000-default.conf`
- `public/index.php`, `public/assets/js/app.js`, `public/assets/css/admin.css`
- `app/Controllers/CatalogController.php`, `app/Controllers/AdminController.php`, `app/Controllers/MediaController.php`
- `app/Middleware/AdminGuard.php`
- `app/Models/AdminRepository.php`
- `app/Services/Auth.php`, `app/Services/UserService.php`, `app/Services/Audit.php`
- `app/Views/layout.php`, `app/Views/detail.php`, `app/Views/partials/progress.php`
- `app/Views/admin/layout.php`, `app/Views/admin/login.php`, `app/Views/admin/users.php`, `app/Views/admin/achievement-form.php`, `app/Views/admin/taxonomy.php`
- `tests/admin.php`, `tests/http.php`, `tests/compose.yaml`, `tests/run-admin.sh`
- `README.md`

## Vérification finale du 23 octobre 2026

- `sh tests/run-admin.sh` : code de sortie 0, 290 contrôles OK ; environnement isolé arrêté et supprimé après exécution.
- JavaScript sous Node 22 Docker : 5/5 tests réussis.
- Lint de tous les fichiers PHP : aucune erreur de syntaxe.
- Compose principal : configuration valide, image reconstruite, web actif sur 127.0.0.1:8080, MariaDB healthy sans port hôte.
- Migrations 011 et 012 appliquées après les tests isolés ; seconde exécution sans modification, 12 migrations présentes et empreintes antérieures inchangées.
- Contrôles HTTP anonymes principaux réussis ; page de connexion contrôlée dans le navigateur.
- Base réelle : 33 succès (32 brouillons, 1 publication antérieure), ArKii actif/local/ADMIN, aucune identité Discord ni promotion automatique.
