# Tour de guilde — phase B et correctif smartphone

Livraison du 25 octobre 2026, après validation explicite de la direction 2,5D.

## Tester directement

1. Se connecter à LevelingWiki avec le compte Discord autorisé ou le propriétaire local existant.
2. Ouvrir **Administration → Tour de guilde**, http://localhost:8080/admin/tour-de-guilde.
3. Créer une division : nom, nom court, description, couleur, niveau. Cocher les Guild Masters souhaités, puis enregistrer.
4. Ouvrir http://localhost:8080/tour-de-guilde : le marqueur, son accent de couleur, son raccourci et le classement proviennent de MariaDB.
5. Changer le niveau depuis l’administration, puis recharger la tour. La nouvelle position et les écarts sont recalculés ; l’ancien et le nouveau niveau restent dans l’historique.
6. Un Guild Master éditeur peut ouvrir sa division, modifier son niveau et consulter son historique. Une division étrangère reste en consultation dans la liste, sans possibilité d’en modifier le niveau ni d’accéder à son historique.

Les candidats Guild Masters sont les comptes Discord actifs déjà connus du site, avec le rôle applicatif `editor` ou `admin`. Si une personne n’apparaît pas, elle doit se connecter avec un rôle Discord reconnu par la configuration existante. Aucun compte ni rôle Discord supplémentaire n’est créé par la fonctionnalité. La liste utilise les rôles connus en base ; l’autorisation sensible est revérifiée par l’authentification existante au moment de l’accès à l’administration.

**La migration 015 a déjà été appliquée à l’environnement local sur le port 8080.** Les trois tables étaient vides après application. Aucune division, association ou donnée fictive n’a été insérée dans cette base.

## Routes

| Route | Méthode | Accès |
| --- | --- | --- |
| `/tour-de-guilde` | GET | Membres Valhalla authentifiés, selon `MemberController::requireUser` |
| `/admin/tour-de-guilde` | GET | `editor`, `admin`, propriétaire existant ; contrôles supplémentaires par division pour les détails/historiques |
| `/admin/tour-de-guilde/save` | POST + CSRF | Administrateurs : création, métadonnées, archivage/restauration et responsables |
| `/admin/tour-de-guilde/level` | POST + CSRF | Administrateurs ou éditeurs Discord affectés à cette division active |
| `/prototype/tour-de-guilde` | GET | Prototype indépendant, développement/test uniquement, fixtures conservées |

Navigation principale : Accueil, Le Codex, Mes succès, Tour de guilde, Mon profil ; entrée Administration conservée pour les rôles autorisés. Navigation mobile adaptée à cinq destinations. La page métier utilise le layout commun et le rendu 2,5D par défaut ; `?view=flat` et le sélecteur permettent de revenir à la 2D.

## Données et migration

`database/migrations/015_guild_divisions.sql`, additive et versionnée :

- `guild_divisions` : nom unique, nom court, description, couleur, niveau positif, version, archivage et dates.
- `guild_division_masters` : relation plusieurs-à-plusieurs entre divisions et utilisateurs existants, auteur/date d’attribution, unicité de l’association.
- `guild_division_levels` : division, auteur, niveau précédent, nouveau niveau et date. Création de division historisée avec un ancien niveau nul.

Les tables existantes ne sont pas transformées. Les migrations précédentes ne sont pas modifiées. Le contrôle préalable sur la base locale a confirmé que seule la migration 015 était en attente. Le mécanisme habituel `bin/migrate.php` l’a appliquée après réussite des tests isolés.

Archivage réversible, pas de suppression physique de division. L’historique reste accessible aux responsables autorisés et aux administrateurs. Pagination de l’historique par lots de 50. Les dates affichées sont en UTC. Niveau compris entre 1 et la limite technique `INT UNSIGNED` (4 294 967 295), sans plafond métier à 100. Au-delà du dernier palier connu, la tour ajoute une ancre architecturale au niveau maximal réel sans inventer de récompense.

Les opérations administratives utilisent également `audit_logs`, avec anciennes/nouvelles valeurs, auteur et date. Les identifiants des responsables, le nom court et la version ont été ajoutés aux champs autorisés par `Audit`. Les associations inchangées conservent leur auteur et date d’attribution.

## Permissions et protections

- Aucun nouveau mécanisme d’authentification. `Auth` et `MemberController::requireUser` restent les points d’entrée ; accès administratif avec vérification sensible des rôles Discord.
- Les administrateurs gèrent toutes les divisions, même s’ils ne figurent pas dans leurs responsables.
- Un éditeur Discord n’acquiert le droit de changer un niveau que par son association explicite à la division. Une association seule ne suffit pas après perte du rôle `editor` ou désactivation du compte.
- L’éditeur ne peut créer, archiver, renommer, recolorer ni changer les associations, même avec une requête forgée vers le POST administratif.
- Identité auteur issue de la session ; division envoyée par le navigateur rechargée et vérifiée côté serveur.
- CSRF, champs autorisés par opération, validations de couleur/texte/niveau/identifiant, requêtes préparées et échappement HTML.
- Transaction et verrou de division pour les mises à jour de niveau, métadonnées et responsables. Un retrait de responsable et une modification de niveau sont sérialisés sur la même division.
- Version attendue obligatoire pour une modification : une soumission périmée est rejetée avec HTTP 409. Une seconde soumission ne remplace pas silencieusement la première.
- Niveau et historique écrits dans la même transaction. Conflits/erreurs ne laissent pas une association ou un niveau partiellement modifié.
- POST classiques avec redirection 303 et confirmation accessible, conformément aux formulaires existants ; pas de faux enregistrement optimiste. Les erreurs de conflit affichent les données actuelles à recontrôler.

## Correctif mobile

Jusqu’à 600 px, la tour est centrée avec une largeur de 220 à 300 px (environ 281 px à 390 px de viewport). Les fenêtres et corniches gardent une hauteur architecturale fixe ; les cartes viennent en dessous dans le même palier. La continuité du mur est conservée derrière les cartes, sans étirer les fenêtres pour remplir toute la hauteur.

Les marqueurs utilisent les deux côtés disponibles, avec exclusion des cartes **et** des médaillons du placement. Les traits restent reliés à la position interpolée réelle, même quand une bulle doit être décalée. Le dépliage d’une carte réserve la hauteur nécessaire au palier. Les noms longs peuvent se répartir sur plusieurs lignes.

« Explorer la tour » conserve une rangée réservée sous l’en-tête, une hauteur maximale et un défilement interne sur petit écran. Fermeture par destination, Échap ou appui extérieur. Son positionnement tient compte de la hauteur effective de l’en-tête authentifié, observée après redimensionnement. Marges latérales, bas de page et notification utilisent les safe areas. Le rendu architectural desktop est conservé ; son panneau latéral s’ajuste désormais à la hauteur effective de l’en-tête.

Le décor, la brume statique, le mode léger et la réduction des mouvements restent actifs. Défilement natif, arrivée initiale au pied et ancres sont conservés. Retour/rechargement laissent le navigateur restaurer la position.

## Tests exécutés

### Docker isolé

`sh tests/run-admin.sh` : **succès, code de sortie 0, 481 contrôles OK**, dont **39 contrôles Tour de guilde**. Le scénario complet a été réexécuté après l’ajustement de conservation des dates d’attribution. Le projet Docker `levelingwiki-tests`, sa base tmpfs et son mock Discord sont indépendants de l’environnement réel et ont été arrêtés/supprimés par le script en fin de test.

Les nouveaux contrôles couvrent :

- migration vide et état sans division ; page permanente sans fixtures/scénarios ; navigation ; authentification ; refus des membres non autorisés ;
- création administrateur, persistance, couleur, raccourci, échappement des noms ; plusieurs responsables ;
- CSRF ; ID de division étrangère ; historique étranger ; champs d’escalade ; niveaux invalides ;
- modification par un responsable ; historique atomique ; version périmée rejetée ; niveau non écrasé ;
- retrait immédiat d’un responsable ; affectation d’un compte non éligible refusée ; rollback ;
- archivage, retrait de la tour, interdiction de progression archivée, restauration, conservation de l’historique ;
- perte du rôle Discord, audit des associations, refus POST sur la page membre.

La suite précédente d’administration, de suivi, du catalogue, des migrations et de Discord est incluse dans ces contrôles de non-régression.

### Autres vérifications

- Suite Node : 19 tests réussis (interpolation, collisions, comportements existants et sélecteur de rendu).
- Test PHP du prototype : 12 assertions réussies ; syntaxe PHP des nouveaux contrôleur/service/repository/vues et du routeur vérifiée.
- Contrôle visuel du prototype à 390 px : tour centrée, cartes sous les fenêtres, lisibilité des divisions ; desktop à 1200 px conservé.
- Mesures DOM après stabilisation à 320 et 390 px : huit marqueurs sans collision entre eux, avec les cartes ou les médaillons, et sans débordement horizontal. Contrôles également effectués aux largeurs tablette/desktop 768, 1200 et 1440 px.
- `git diff --check` sans erreur.

Les permissions/persistances sont testées par requêtes HTTP authentifiées dans l’environnement isolé. Aucun compte réel n’a été utilisé pour créer une division de démonstration. Le contrôle visuel mobile porte sur le prototype qui partage le rendu de la page métier ; l’essai tactile sur iPhone physique/Safari et en standalone PWA reste à réaliser.

## Fichiers de la phase B

Créés :

- `database/migrations/015_guild_divisions.sql`
- `app/Models/GuildDivisionRepository.php`
- `app/Services/GuildDivisionService.php`
- `app/Controllers/GuildTowerController.php`
- `app/Views/admin/guild-divisions.php`
- `config/guild-tower.php`
- `tests/guild-tower.php`
- `docs/tour-de-guilde-phase-b.md`

Modifiés :

- `public/index.php` : routes permanentes.
- `app/Services/Audit.php` : champs d’audit de la tour.
- `app/Views/partials/navigation.php`, `app/Views/admin/layout.php` : liens et administration.
- `app/Views/layout.php` : versions des ressources.
- `app/Controllers/TowerPrototypeController.php`, `app/Views/tower/prototype.php`, `tests/fixtures/tower.php` : séparation explicite prototype/métier et partage des paliers informatifs.
- `public/assets/js/tower.js`, `public/assets/js/tower-renderer.mjs` : données réelles, couleurs, extension de niveau, placement mobile, menu et repli 2D.
- `public/assets/css/tower.css`, `public/assets/css/valhalla.css` : proportions mobile, navigation et formulaires.
- `tests/tower-renderer.test.mjs`, `tests/run-admin.sh` : tests et intégration à la suite.

## Limites volontaires

Les paliers/récompenses connus sont isolés dans `config/guild-tower.php`, sans dépendance aux fixtures. Ils sont informatifs et non administrables dans cette phase. Le futur catalogue pourra remplacer cette source sans changer l’identité des divisions ou les relations utilisateurs.

Aucune récupération individuelle, association des membres ordinaires à une division, statistique détaillée de ressources ou catalogue complet n’a été ajouté. La page prend les données à chaque chargement, sans rafraîchissement automatique en arrière-plan. La base réelle reste sans division jusqu’à votre première création administrative.

Aucune branche, commit, push ou merge. La modification préexistante de `.env.example` est laissée intacte.
