# Discord — logique OU et gestion descendante

Mise à jour du 28 octobre 2026. Ce document remplace la condition MEMBER obligatoire décrite dans l’audit précédent.

## Audit et décision

`DiscordAuthService::role()` imposait explicitement MEMBER avant de choisir ADMIN ou EDITOR. Cette même méthode est utilisée au callback et lors de la réévaluation des permissions, y compris après renouvellement du jeton. La résolution utilise désormais les associations stockées dans MariaDB, en comparant les Discord Role ID exacts : ADMIN, sinon EDITOR, sinon MEMBER, sinon refus. L’appartenance au serveur et les autres contrôles OAuth restent obligatoires. Les noms/libellés ne déterminent jamais les permissions.

`PermissionService::manageDiscordLevel()` centralise la règle de rang strictement inférieur. `discordSettings()` contrôle l’accès à la rubrique. Aucun rôle Discord ne confère SUPER_ADMIN : son mécanisme local privé est conservé.

## Matrice appliquée

| Acteur | Associations MEMBER | Associations EDITOR | Associations ADMIN | SUPER_ADMIN |
|---|---|---|---|---|
| MEMBER | Refus | Refus | Refus | Refus |
| EDITOR | Ajouter, renommer, retirer | Masquées, écritures refusées | Masquées, écritures refusées | Refus |
| ADMIN | Ajouter, renommer, retirer | Ajouter, renommer, retirer | Lecture seule, écritures refusées | Refus |
| SUPER_ADMIN local | Gestion | Gestion | Gestion | Mécanisme privé existant |

Le header membre demeure inchangé : MEMBER ne voit aucun lien administratif. EDITOR accède maintenant à Paramètres Discord et ne voit que MEMBER. ADMIN conserve la section ADMIN en consultation, sans commandes de gestion. Annuler, Échap, retour du focus, confirmations et styles responsive sont conservés.

Les Guild Masters gardent leurs autorisations de division existantes. Cette association ne confère pas ADMIN et n’élargit pas le périmètre des divisions modifiables.

## Écritures et concurrence

Aucune migration n’est nécessaire : `discord_roles.id` et `authorization_policy.version` existent déjà.

Les formulaires transmettent la version attendue de la politique. Le chargement lit version et associations sous un verrou partagé cohérent. L’écriture verrouille d’abord la politique, vérifie la version puis verrouille l’association concernée. Toute version périmée produit un conflit HTTP 409, sans modification ni audit de réussite.

La permission du niveau cible est vérifiée côté service. Pour une association identifiée par son ID, le niveau réellement enregistré est également contrôlé. Le niveau et le Discord Role ID d’une association existante sont immuables : une requête qui tente de les changer est refusée. L’interface ne propose pas de déplacement entre niveaux. Un renommage ne modifie que le libellé.

La clé composite historique `(level, discord_role_id)` reste prise en charge : elle identifie une association précise et ne déplace jamais une association d’un autre niveau. Le même rôle Discord peut avoir plusieurs associations existantes ; la résolution choisit le niveau le plus élevé. Ajouter une association MEMBER pour un Role ID également associé à ADMIN ne modifie pas l’association ADMIN et n’élève pas le créateur.

Les contrôles d’origine et de destination empêchent notamment un EDITOR de soumettre l’ID d’une association ADMIN avec une cible MEMBER. Transactions, requêtes préparées, CSRF, audit et invalidation de politique sont conservés. L’audit reçoit l’identifiant stable de l’association et les valeurs avant/après filtrées par le mécanisme existant. Les protections des routes restent côté serveur.

Après une modification de politique, les permissions sont réévaluées à la requête suivante. Toutes les routes utilisent les rôles vérifiés en session pendant au maximum 24 heures. Une modification locale recalcule les droits depuis ces rôles sans appel Discord (voir `discord-sessions.md`). Une perte d’ADMIN peut laisser MEMBER si ce rôle est toujours autorisé ; la perte du dernier rôle retire l’accès. Aucun jeton, code OAuth ou secret n’est ajouté aux journaux.

## Validation

Les tests existants qui exigeaient MEMBER en complément ont été adaptés à la nouvelle règle, sans retirer les scénarios de refus. La suite ajoute les sept combinaisons de rôles autorisées, les rôles inconnus, la matrice d’ajout/renommage/retrait, les identifiants forgés, les cibles interdites, CSRF, versions périmées, promotion après actualisation et révocation du dernier rôle. Les suites existantes couvrent renouvellement, expiration, propriétaire, Guild Masters, Codex, Tour et Phase D.

Résultats finaux du 28 octobre 2026 :

- `sh tests/run-admin.sh` : code de sortie 0, **772 vérifications PHP réussies**.
- `node --test tests/*.test.mjs` : **31 tests réussis, 0 échec**.
- `git diff --check` : aucune erreur d’espacement.
- Inspection des rendus de fixtures : EDITOR (MEMBER uniquement), ADMIN (ADMIN en lecture seule), navigation MEMBER et refus d’accès. Mesures à 320, 375, 390, 430, 768, 1024, 1200, 1440 et 1920 px, formulaires ouverts et fermés : aucun débordement horizontal observé. Inspection graphique complémentaire desktop/mobile, actions regroupées, noms/identifiants lisibles, annulation et retour du focus vérifiés.
- Captures : `/Users/nicolaspaiva/.codex/visualizations/2026/10/24/01a16f37-2ca0-7a32-a45f-e12532c5a57e/hierarchie-editor-mobile.png`, `hierarchie-admin-desktop.png` et relevé `hierarchie-responsive.json` dans le même répertoire. Pages HTML issues de fixtures HTTP isolées, sans soumission de formulaire dans le navigateur.
- Safari iOS physique et clavier virtuel réel non testés.

 La connexion Discord réelle n’est pas validée : les tests utilisent le serveur Discord simulé de Docker isolé. Aucune association, utilisateur ou rôle réel n’a été modifié. Aucune migration nouvelle, aucune branche, aucun commit, push ou merge.

## Procédure locale

1. Recharger les pages après déploiement du code. Les anciens formulaires sans version doivent être rechargés ; aucune migration n’est nécessaire pour cette intervention.
2. Démarrer une nouvelle connexion depuis `/connexion`, avec un compte déjà membre du serveur et possédant uniquement un rôle autorisé EDITOR ou ADMIN. Ne pas modifier des rôles réels pour fabriquer un scénario de test.
3. Vérifier le rôle obtenu : ADMIN prime sur EDITOR, qui prime sur MEMBER. Vérifier les contrôles avec les comptes et associations déjà disponibles.
4. Avec EDITOR, ouvrir Paramètres Discord : seule la section MEMBER doit être proposée. Avec ADMIN, contrôler MEMBER/EDITOR et la lecture seule ADMIN. Avec le propriétaire, les trois niveaux sont gérables.
5. Tester les ajouts, retraits, requêtes forgées, conflits et révocations exclusivement dans l’environnement jetable (`sh tests/run-admin.sh`). Ouvrir deux formulaires : après une sauvegarde, l’autre doit être refusé comme périmé.
6. En cas d’échec OAuth réel, relever le message et le statut HTTP sans partager l’URL du callback, ses paramètres, les cookies ou les jetons. Vérifier séparément la configuration OAuth et l’appartenance au serveur.

## Fichiers concernés

- `app/Services/PermissionService.php`, `DiscordAuthService.php`, `DiscordAccessException.php`, `DiscordRoleService.php`.
- `app/Middleware/AdminGuard.php`, `app/Controllers/AdminController.php`.
- `app/Views/member/login.php`, `app/Views/admin/layout.php`, `app/Views/admin/discord.php`.
- `tests/http-client.php` (lit la version des formulaires pour les POST de test), `tests/phase4.php`, `tests/discord-access.php`, `tests/discord-hierarchy.php`, `tests/render-discord-access.php`, `tests/run-admin.sh`.
- `docs/phase4.md`, `docs/phase5.md`, `docs/audit-discord-visibilite.md`, ce document.

Les changements préexistants des interventions précédentes restent conservés.
