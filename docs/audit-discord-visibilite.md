# Audit Discord et visibilité — 27 octobre 2026

> Politique de session actualisée : voir [Sessions Discord](discord-sessions.md). Les mentions historiques de cache de cinq minutes et de contrôle Discord forcé ci-dessous sont remplacées par un cache serveur de 24 heures et des contrôles locaux sur toutes les routes.
> Mise à jour : la politique décrite historiquement ci-dessous a été remplacée à la demande de l’utilisateur. Voir [Hiérarchie Discord](discord-hierarchie.md) : connexion MEMBER OU EDITOR OU ADMIN ; EDITOR gère MEMBER. Les constats et résultats ci-dessous décrivent l’audit précédent.

## Résultats de l’audit

Le parcours utilise OAuth2 avec `identify guilds.members.read`, state à usage unique (10 minutes), PKCE S256, cookies HttpOnly/SameSite Lax, renouvellement de jeton et vérification d’appartenance au serveur. Chaque accès administratif force une nouvelle vérification des rôles ; les lectures membres utilisent le cache borné et la version de politique. Les modifications des associations invalident ce cache. Les échecs restent fermés : aucun ancien privilège sensible n’est réutilisé.

Causes identifiées dans le code :

- `DiscordClient` regroupait réseau, HTTP non réussi et refus d’accès dans le même message. Le message ne permettait pas d’identifier la cause du callback réel.
- `AdminGuard` perdait le contexte du membre refusé, et la vue de message proposait systématiquement un lien vers `/admin`. Le layout administratif testait seulement la présence d’un utilisateur.
- Le catalogue de la Tour proposait son lien d’audit aux éditeurs alors que l’historique est réservé à ADMIN/SUPER_ADMIN.
- Le contrôleur bloquait tous les retraits d’association Discord par ADMIN, alors que le service et l’interface lui autorisaient déjà la gestion MEMBER/EDITOR.
- Les formulaires Discord combinaient grille desktop, flex mobile et alignement de fin de ligne. Le bouton Annuler était ajouté hors du groupe d’actions.

Lecture seule de l’environnement local : client ID et serveur ID au format attendu, secret renseigné, callback local avec chemin `/auth/discord/callback`. **Une association MEMBER, deux associations ADMIN et aucune association EDITOR** étaient enregistrées au moment du contrôle. Aucune valeur sensible ou identifiant réel n’a été affiché ni modifié. L’absence de mapping EDITOR peut expliquer des droits éditoriaux absents ; elle ne prouve pas la cause de l’échec OAuth.

La règle historique, documentée et testée, exige MEMBER même si ADMIN ou EDITOR est présent. Cette condition est préservée. Parmi les rôles autorisés après cette condition : ADMIN > EDITOR > MEMBER. SUPER_ADMIN reste un propriétaire local privé, jamais issu de Discord.

**Le callback Discord réel n’a pas été reproduit.** Restent notamment à vérifier : secret effectivement valide, URI exacte enregistrée dans le portail Discord (schéma/hôte/port/chemin), serveur choisi, appartenance, rôle MEMBER réel, consentement/scopes, disponibilité réseau et session/cookie du navigateur. Aucun contournement des contrôles n’a été ajouté. Les captures initiales évoquées ne sont pas disponibles comme fichiers dans ce tour ; l’inspection repose sur des rendus actuels de fixtures isolées.

## Correctifs

`DiscordAccessException` expose uniquement des catégories et messages fixes. Réseau, limitation 429 et panne 5xx : Discord momentanément injoignable ; 404 de la route membre : absence du serveur requis ; refus OAuth : nouvelle connexion nécessaire ; configuration invalide : administrateur à contacter ; state absent/invalide/expiré : recommencer ; réponse inexploitable : erreur technique. Aucun corps de réponse Discord, code, jeton ou secret n’est recopié dans l’interface ou les journaux.

La vérification sensible conserve le refus 403 même si son origine est une panne, avec message adapté et sans navigation privilégiée. Le callback distingue 503 technique et 403 accès/OAuth. Une session applicative arrivée à expiration renvoie vers `/connexion?expired=1` avec une explication. Le paramètre n’accorde aucun accès.

Le contexte utilisateur est conservé lors d’un refus ; menus et retours sont rendus conditionnellement avec PermissionService. MEMBER ne reçoit pas de liens d’administration, même dans une réponse 403. EDITOR ne voit plus le lien d’audit inaccessible du catalogue. ADMIN voit les rôles ADMIN en consultation ; seuls les propriétaires peuvent les modifier. Les actions personnelles restent disponibles.

Les boutons des formulaires de rôle sont regroupés ; Annuler réinitialise le formulaire, ferme le volet et remet le focus sur son déclencheur. Échap conserve ce comportement. Les champs se regroupent sur ordinateur et s’empilent à largeur mobile. La confirmation de retrait décrit la suppression de l’association locale, sans suggérer la suppression du rôle dans Discord.

## Permissions vérifiées

| Fonction | MEMBER | EDITOR | ADMIN | SUPER_ADMIN local |
|---|---|---|---|---|
| Codex, guides, suivi et statistiques personnels, Tour | Oui | Oui | Oui | Oui |
| Administration / succès et guides | Non | Oui | Oui | Oui |
| Catégories et titres | Non | Non | Oui | Oui |
| Catalogue de paliers et récompenses | Non | Oui | Oui | Oui |
| Suppression définitive d’un palier | Non | Non | Oui | Oui |
| Divisions : gestion complète | Non | Non | Oui | Oui |
| Niveau/historique d’une division assignée | Non | Guild Master assigné | Oui | Oui |
| Historique administratif | Non | Non | Oui | Oui |
| Associations Discord MEMBER/EDITOR | Non | Non | Oui | Oui |
| Associations Discord ADMIN | Non | Non (route refusée) | Lecture | Gestion |
| Utilisateurs / accès propriétaire | Non | Non | Non | Oui |

Guild Master est une association spécifique, sans élévation ADMIN ; les règles existantes imposent un rôle éditorial pour ses actions de division. Les contrôles serveur restent actifs pour les URL directes et requêtes forgées. Les boutons personnels « Marquer comme obtenu » et « Annuler la déclaration » ne sont pas des actions administratives.

## Tests et limites

- Suite complète Docker : `sh tests/run-admin.sh`, code de sortie 0, **727 vérifications PHP réussies** sur l’état final. Environnement isolé détruit après les tests.
- Tests JavaScript : 31 réussis, aucun échec ; ajout d’un test Annuler/Échap/focus dans le groupe d’actions.
- Tests dédiés `discord-access.php` : 35 vérifications HTTP/service, dont navigation MEMBER, refus directs, hiérarchie, MEMBER obligatoire, absence serveur, OAuth invalide, panne API, absence de secrets, expiration, configuration invalide et retrait d’un mapping EDITOR par ADMIN.
- Les suites existantes vérifient aussi révocation de rôles, invalidation de politique, SUPER_ADMIN, Guild Masters, CSRF, Codex, guides, Tour et Phase D.
- Mesures à 320, 375, 390, 430, 768, 1024, 1200, 1440, 1920 px : rôles ouverts/fermés, profil MEMBER, refus membre, callback invalide et session expirée. Aucun débordement horizontal observé. Inspection graphique réelle mobile et ordinateur ; conflit d’alignement mobile corrigé puis revérifié. Aucun test Safari iOS physique ni clavier virtuel réel.
- Captures : répertoire de visualisation du chat, fichiers `discord-roles-mobile.png`, `discord-roles-desktop.png`, `discord-denied-member.png`, `discord-responsive.json`. Fixtures uniquement, consultées via pages statiques locales ; les écritures sont testées par HTTP dans Docker isolé. La fidélité de la capture desktop est parfois réduite par le navigateur intégré.

Aucune migration créée ou appliquée à la base existante. Aucune association réelle, aucun rôle Discord ni aucune donnée réelle modifiés. Aucune branche, commit, push ou merge. Les modifications préexistantes de la Phase D et `.env.example` sont préservées.

## Vérification locale

1. Ouvrir une nouvelle session de navigateur sur l’origine locale prévue, puis démarrer la connexion depuis `/connexion` (ne pas réutiliser une ancienne URL de callback).
2. En cas de refus, relever uniquement le message affiché et le statut HTTP. Ne pas partager l’URL du callback, son code, les cookies ou les jetons.
3. Vérifier dans le portail Discord l’URI de redirection exacte et le serveur visé, sans publier les secrets. Vérifier l’appartenance et le rôle MEMBER configuré. Un rôle ADMIN seul ne satisfait pas la politique actuelle.
4. Pour un compte éditorial, comparer les rôles réels aux associations locales : l’audit a constaté l’absence d’association EDITOR. Toute modification doit être décidée par un administrateur autorisé ; aucune correction automatique n’a été faite.
5. Avec MEMBER, contrôler accueil, Codex, profil et Tour puis saisir `/admin` : refus et retour au Codex, aucun lien administratif. Vérifier que le suivi personnel reste disponible.
6. Avec EDITOR, vérifier succès et catalogue, sans historique, paramètres Discord ou suppression définitive. Avec ADMIN, vérifier les rôles MEMBER/EDITOR et la lecture seule des rôles ADMIN. Avec propriétaire, vérifier la gestion ADMIN et les protections privées.
7. Pour tester un retrait/renommage ou une panne, utiliser exclusivement les fixtures Docker. Ne pas retirer un rôle réel pour reproduire un test.

## Fichiers concernés par cette intervention

Créés : `app/Services/DiscordAccessException.php`, `tests/discord-access.php`, `tests/discord-forms.test.mjs`, `tests/render-discord-access.php`, ce document.

Modifiés : `app/Services/DiscordClient.php`, `app/Services/DiscordAuthService.php`, `app/Services/Auth.php`, `app/Middleware/AdminGuard.php`, `app/Controllers/MemberController.php`, `app/Controllers/AdminController.php`, `app/Views/admin/layout.php`, `app/Views/admin/message.php`, `app/Views/admin/discord.php`, `app/Views/admin/guild-catalog.php`, `app/Views/member/login.php`, `app/Views/member/denied.php`, `app/Views/layout.php`, `public/assets/css/admin-forms.css`, `public/assets/js/admin-forms.js`, `public/assets/js/interface.js`, `tests/run-admin.sh`.
