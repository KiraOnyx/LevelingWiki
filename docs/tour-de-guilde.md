# Tour de guilde — audit et prototype graphique

Livraison des étapes 1 et 2, le 24 octobre 2026. **Validation visuelle attendue avant toute migration ou intégration métier.**

## Consulter le prototype

- URL : http://localhost:8080/prototype/tour-de-guilde
- L’ouverture normale sans ancre arrive au niveau 1, avec un mouvement final limité à 120 px. Remonter naturellement avec le défilement.
- Accès direct : `/prototype/tour-de-guilde#division-v1`, `#level-40`, `#tower-top`.
- Variante de test sans animation : `/prototype/tour-de-guilde?motion=reduce`. Le réglage système `prefers-reduced-motion` est également respecté.
- « Explorer la tour » rejoint les divisions, le sommet, le pied ou la présentation. Sur smartphone, ce menu se replie dans une rangée réservée sous l’en-tête, afin de ne pas masquer les récompenses.
- « Voir les divisions » révèle, pour chaque récompense, les divisions ayant atteint le palier.
- « Scénarios de test visuel » propose trois divisions, huit divisions proches, une progression jusqu’à 135, ou des divisions au pied et au sommet.
- Les scénarios sont temporaires et disparaissent au rechargement. Aucun compte fictif, aucune récupération et aucune écriture en base.
- Route uniquement disponible avec `APP_ENV=development` ou `test`, GET uniquement ; sinon 404/405. Les conteneurs existants servaient déjà le dépôt sur le port 8080. Aucun redémarrage ni changement de configuration nécessaire.

## Étape 1 — audit de l’existant

### Architecture et composants

Application PHP 8.2 / MariaDB 11.4, Apache dans Docker ; autoload interne `app/bootstrap.php`, routeur explicite `public/index.php`. Aucun framework applicatif à ajouter. Contrôleurs dans `app/Controllers`, services métier dans `app/Services`, repositories dans `app/Models`, vues PHP dans `app/Views`.

`MemberController` gère l’espace personnel, `CatalogController` le catalogue, `AdminController` l’administration. Le layout partagé charge Axentix Neu localement, les styles thème/administration/Valhalla et les scripts existants. Le design actuellement appliqué est ardoise/or, plus récent que les premières couleurs du projet. Le prototype conserve cette identité dans l’en-tête et utilise violet/bleu dans la nouvelle section. `helpers.php` fournit l’échappement HTML et les petites icônes SVG.

### Authentification et permissions

`Auth`, `DiscordAuthService`, `DiscordClient` et `DiscordRoleService` portent l’identité Discord et la vérification des rôles. `MemberController::requireUser` est le point de réutilisation pour la future page privée. Le propriétaire local existant est une exception préexistante à conserver, pas un nouveau compte à créer.

`PermissionService` connaît `member`, `editor`, `admin`, `super_admin`. Aucun rôle `manager` autonome. `AdminGuard` vérifie les autorisations sensibles et les routes explicitement autorisées. Proposition pour l’étape métier : gestion divisions/membres via la capacité d’édition existante (`editor` et supérieurs), catalogue et paliers via la capacité administrative. Cette extension de routes devra être explicite, testée et documentée ; rien n’est changé dans cette livraison.

### Données et migrations

14 migrations versionnées SQL/PHP, exécutées par `bin/migrate.php`, avec verrou MariaDB et vérification d’empreintes. Les migrations déjà appliquées ne doivent pas être modifiées. Le DDL ne rollback pas : les migrations PHP inspectent les étapes pour permettre leur reprise.

Tables réutilisables : `users` et identités Discord pour les membres ; `audit_logs` pour la traçabilité. Les catégories, titres, succès et `personal_achievements` appartiennent au suivi de succès et ne représentent pas des paliers de guilde. Aucun modèle de division/tour identifié dans les sources examinées. Pas d’inspection ni de mutation des données réelles.

### Statistiques et AJAX

`PersonalService::overview` calcule les indicateurs personnels, catégories, historique récent et estimations de récompenses des succès. Les vues `member/home`, `member/profile`, `partials/ascension`, `partials/recent`, `partials/estimates` et `partials/progress` sont les points d’intégration. Il n’y a pas de tableau de bord de guilde existant à dupliquer.

Les récompenses des succès sont aujourd’hui agrégées sur quatre colonnes fixes. Le catalogue extensible de la tour doit rester séparé, avec agrégations par type et snapshots de déclarations ; reprendre ce calcul tel quel ne suffirait pas.

`interface.js` utilise les POST existants, CSRF, réponse JSON, désactivation pendant l’envoi et retour accessible. `MemberController` traite `Accept: application/json`. Réutiliser ces conventions pour les déclarations futures, sans recalcul de navigation ni rechargement.

### Sécurité et tests

PDO préparé, validations `Input` / `ValidationException`, transactions et `FOR UPDATE` dans les services, contrôles CSRF dans les contrôleurs, identité depuis la session, échappement `e`, CSP restrictive et no-store dans le routeur. L’édition rapide des succès compare une valeur antérieure pour détecter les conflits ; étendre ce principe par version aux entités de tour.

`Audit::record` filtre les champs historisés : ses champs autorisés devront être étendus soigneusement aux nouvelles quantités, références et associations, sans identité sensible superflue.

Tests PHP d’intégration et HTTP, tests Node natifs, environnement jetable `tests/compose.yaml` sur 8081 avec base en tmpfs et Discord simulé. Ce prototype n’a besoin d’aucune base. Les tests métier, CSRF et Discord complets appartiennent aux étapes suivantes, après validation visuelle.

### Stratégie proposée après validation — non implémentée

- Tables dédiées : `guild_divisions`, `guild_milestones`, `guild_reward_types`, `guild_milestone_rewards`, `guild_memberships`, `guild_reward_claims`, et événements de déclarations/corrections si nécessaire. Référencer `users` et réutiliser `audit_logs`.
- Unicité du niveau actif d’un palier et de l’association active d’un membre via clés générées/index uniques compatibles MariaDB ; unicité durable `(user_id, reward_id)` pour empêcher la récupération répétée après changement de division.
- Archiver au lieu de supprimer les éléments référencés. Déclaration avec division d’origine, quantité, type/unité/libellé historisés, date et annulation. Ne pas recalculer le passé à partir du catalogue courant.
- Séparer la nature de ressource du mode de distribution par récompense. Les clés des niveaux 20/30 restent non classées tant que leur nature n’est pas confirmée. Les niveaux 60–100 restent inconnus.
- Écritures critiques sous transaction, verrous ordonnés, version attendue pour les mises à jour administratives, contrôles d’accès serveur systématiques et quantité exclusivement serveur.
- Statistiques : personnels réellement déclarés, accessibles actuels et collectifs séparés. Regroupement historique par division d’origine, regroupement des membres selon leur association actuelle.

## Étape 2 — modifications livrées

Architecture SVG mutualisée (`pattern`, `symbol`, `use`) : pierres irrégulières, joints, colonnes latérales, fenêtres gothiques, corniches, cristaux, médaillons, créneaux et base fortifiée. Aucun bitmap généré, moteur 3D, framework ou dépendance ajouté. Le rendu utilise un module par palier configuré, pas par niveau numérique.

Le prototype utilise une fixture PHP isolée avec les douze paliers du cahier des charges et trois divisions fictives 43/37/24. Les fiches sont alternées à gauche/droite sur desktop et alignées sur la droite sur mobile. Les marqueurs occupent la place libre en évitant cartes et autres marqueurs ; leurs traits rejoignent toujours le niveau réel, interpolé entre les médaillons rendus. Sur mobile, les noms sont raccourcis et un appui donne le détail complet.

Calculs au chargement, changement de scénario, changement de taille et ouverture d’un détail. Aucun traitement permanent au scroll. Hauteur réservée pour les détails dépliés. Navigation avec liens, focus clavier, messages accessibles et préférences de mouvement. Le navigateur conserve sa restauration native au rechargement/Retour ; la descente automatique n’est lancée qu’à l’ouverture normale, sauf ancre ou interaction préalable.

### Fichiers

Créés :

- `app/Controllers/TowerPrototypeController.php`
- `app/Views/tower/prototype.php`
- `app/Views/tower/architecture.php`
- `public/assets/css/tower.css`
- `public/assets/js/tower.js`
- `public/assets/js/tower-geometry.mjs`
- `tests/fixtures/tower.php`
- `tests/tower-geometry.test.mjs`
- `tests/tower-prototype.php`
- `docs/tour-de-guilde.md`

Modifiés : `public/index.php` (route de démonstration), `app/Views/layout.php` (CSS/JS chargés seulement pour le prototype).

Migrations : **aucune**. Branches, commits, push, merge : **aucun**. La modification préexistante de `.env.example` est conservée sans intervention.

### Vérifications exécutées

- `node --test tests/*.test.mjs` : 16 tests réussis, dont 4 nouveaux tests géométriques (interpolation irrégulière, niveau 135, 100 marqueurs proches, extrémités).
- `node --check` sur les deux nouveaux modules : syntaxe valide.
- `php -l` dans le conteneur sur contrôleur, vues, fixture, layout et routeur : aucune erreur.
- `php tests/tower-prototype.php` : 7 assertions réussies ; environnement test, 12 paliers, inconnues préservées, absence de fausse récupération, refus en production/configuration absente, refus POST. Aucun accès DB.
- Navigateur réel : contrôle du rendu à 1440, 1200, 768, 390 et 320 pixels ; absence de défilement horizontal constatée. Navigation vers les divisions et scénario huit divisions ; contrôles DOM sans collision entre marqueurs ou cartes sur les scénarios examinés.
- Scénario 135 : extension au-dessus de 100, sans nouvelle récompense inventée ; navigation vers le niveau supérieur.
- Navigation directe avec ancre de division, variante sans animation et Retour du navigateur : destination/restauration vérifiées après stabilisation. Détails dépliés à 320 px : hauteur du palier ajustée à la carte.
- Console du prototype : aucune erreur ou alerte observée.

### Limites et validation attendue

Ce livrable valide une direction graphique et les interactions de navigation, **pas la fonctionnalité complète**. Pas de migration, d’administration, d’association réelle, de récupération persistante ni de statistiques branchées au compte. Le prototype est accessible sans connexion uniquement en développement/test parce qu’il ne lit ni identité ni données réelles ; la future page métier utilisera la protection membre existante.

Les simulations responsive ne remplacent pas un essai sur iPhone physique/Safari et en PWA standalone. Vérifier manuellement le confort tactile, les zones de sécurité, le zoom de texte, le mode système de réduction des animations et les longues listes réelles. Le test de 100 marqueurs porte sur le calcul, pas sur une validation esthétique de 100 divisions affichées.

Valider l’architecture, les proportions, la palette, la lisibilité des récompenses/divisions, le panneau de navigation et l’arrivée au pied. **Attendre la validation visuelle explicite avant l’étape 3.**
