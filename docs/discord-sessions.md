# Sessions Discord et permissions locales

## Architecture

OAuth2 conserve state à usage unique, PKCE et contrôle d’appartenance au serveur. À la connexion, les identifiants de rôles vérifiés et l’état d’adhésion sont conservés exclusivement dans la session serveur, avec les jetons, la date de vérification et la version de politique. L’accès exige l’appartenance au serveur et au moins une association MEMBER, EDITOR ou ADMIN ; le niveau le plus élevé gagne. SUPER_ADMIN reste une identité locale privée.

Toutes les requêtes contrôlent localement l’utilisateur actif, la version de session, sa durée, la politique et les permissions de la route. L’ancien paramètre `sensitive` reste compatible avec les appelants mais ne force plus de requête réseau. Les appels systématiques disparaissent donc de l’administration, des paramètres Discord, des écritures personnelles et des lectures protégées, comme du Codex, du profil et de la Tour. La déconnexion explicite peut toujours révoquer un jeton auprès de Discord.

## Durées et renouvellement

- Cache des rôles : 86 400 secondes maximum depuis la vérification, jamais prolongé par la navigation ni par un changement de politique locale.
- Session : durée absolue et inactivité maximales de 86 400 secondes. Cookie HttpOnly, SameSite Lax, Secure en production ; durée du cookie et conservation PHP fixées à 24 heures. Fermer un onglet ne déconnecte pas.
- À l’expiration absolue, la session est détruite et une nouvelle connexion est demandée. C’est le chemin normal, puisque session et cache sont créés ensemble.
- Si le cache expire dans une session encore valide, la première requête renouvelle si nécessaire le jeton, consulte l’appartenance et les rôles, puis recalcule les droits. Pas de tâche planifiée.
- Un jeton invalide impose une reconnexion. Une panne avec cache expiré refuse l’accès avec une explication ; aucune période de grâce n’est ajoutée. Une panne avec cache valide ne déclenche aucun appel et ne retire aucun droit.
- Les anciennes sessions sans copie des rôles nécessitent une première vérification pour alimenter ce cache.

Les anciennes variables `DISCORD_ROLE_TTL`, `SESSION_IDLE_SECONDS` et `SESSION_ABSOLUTE_SECONDS` ne permettent plus de modifier ces durées en environnement applicatif. Seul `APP_ENV=test` autorise des délais de session plus courts pour les tests isolés, sans dépasser 24 heures. Un hébergement utilisant un nettoyage externe des fichiers de session doit également conserver ceux-ci pendant 24 heures.

**Une révocation directement sur Discord peut prendre jusqu’à 24 heures pour être détectée.** La reconnexion et le renouvellement réévaluent toujours les rôles actuels. Les droits Guild Master restent contrôlés localement sur les divisions assignées.

## Associations et sécurité

Chaque mutation d’association incrémente la version globale de politique. À la requête suivante, toute session concernée recalcule immédiatement ses droits depuis les identifiants Discord déjà vérifiés, sans réseau et sans remettre leur date à zéro. Un rôle devenu non autorisé est refusé. Une vérification externe n’est nécessaire que si ces données sont expirées ou absentes.

La hiérarchie descendante reste inchangée : EDITOR gère MEMBER, ADMIN gère MEMBER et EDITOR, SUPER_ADMIN gère les trois catégories. Les contrôles serveur, CSRF, transactions, concurrence, audit et protections propriétaire restent en place. Aucun rôle ni jeton ne provient du navigateur. Aucun secret n’est ajouté aux journaux.

## Fichiers de cette intervention

Application : `app/Services/Auth.php`, `app/Services/DiscordAuthService.php`, `app/Middleware/AdminGuard.php`, `config/discord.php`, `config/security.php`, `.env.example`, `compose.yaml`.

Tests : nouveau `tests/discord-sessions.php` ; adaptations de `tests/discord-support.php`, `tests/discord-mock.php`, `tests/discord-access.php`, `tests/discord-hierarchy.php`, `tests/phase4.php`, `tests/guild-tower.php`, `tests/run-admin.sh`.

Documentation : ce document, `docs/discord-hierarchie.md`, `docs/phase4.md`, `docs/audit-discord-visibilite.md`. Aucune migration.

## Validation locale

1. Se connecter avec un compte de test MEMBER, EDITOR ou ADMIN seul : vérifier le rôle attendu, puis naviguer entre Codex, profil, Tour et pages administratives autorisées.
2. Dans l’environnement isolé, rendre le faux serveur Discord indisponible : une session fraîche doit rester utilisable sans nouvelle requête API.
3. Retirer une association uniquement dans la base isolée : la requête suivante doit appliquer les nouveaux droits, même hors ligne. Ne pas modifier les associations réelles pour cette vérification.
4. Vieillir le cache de test de 24 heures : vérifier changement de rôle, perte du dernier rôle, renouvellement du jeton et refus explicite si Discord est indisponible.
5. Vieillir la session : vérifier la redirection de reconnexion. Vérifier aussi déconnexion, CSRF invalide, accès direct interdit et protections Guild Master/propriétaire.

`sh tests/run-admin.sh` crée les scénarios exclusivement dans Docker isolé. `node --test tests/*.test.mjs` couvre la non-régression JavaScript. Le faux fournisseur compte les appels HTTP pour prouver leur absence pendant la navigation. Aucun test réel OAuth Discord n’est revendiqué ; il reste à effectuer avec un compte autorisé. Cette intervention ne modifie pas les vues ni le CSS.

## Résultats — 28 octobre 2026

- `sh tests/run-admin.sh` : sortie 0, **799 assertions OK**, dont 27 nouvelles assertions de session. MariaDB et fournisseur Discord simulé isolés ; nettoyage des conteneurs et volumes effectué par le script.
- `node --test tests/*.test.mjs` : **31 tests réussis**, 0 échec, 0 ignoré.
- `git diff --check` : aucune erreur d’espacement.
- Suites existantes conservées : autorisations par rôle et hiérarchie, propriétaire, Guild Masters, expiration réelle de session, CSRF, Codex, progression, récompenses et Tour. Les anciens tests exigeant une révocation Discord immédiate ont été adaptés pour expirer explicitement le cache avant le contrôle ; leur assertion de refus est conservée.
- Aucune donnée réelle modifiée, aucune migration, aucune opération Git d’écriture. OAuth Discord réel et nettoyage de sessions propre à un hébergement externe non testés.
