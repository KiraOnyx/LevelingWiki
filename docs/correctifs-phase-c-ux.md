# Correctifs Phase C et Codex unifié — 25 octobre 2026

## Résultat

Le catalogue de la Tour est désormais administrable par EDITOR, ADMIN et SUPER_ADMIN. Les divisions/Guild Masters gardent leurs permissions propres. La navigation des succès est regroupée ; Le Codex contient le catalogue et le suivi personnel. Phase D non commencée. Aucune migration ni donnée réelle modifiée pour cette intervention. Aucun commit, push, merge ou branche créé.

## Cause de la confusion d’édition

La sélection d’un palier par `?id=…` chargeait correctement ses données, mais le lien peu explicite « Modifier, archiver ou restaurer » renvoyait en haut de la même page, avec le formulaire situé sous toute la liste. Cela rendait l’édition difficile à découvrir, sans constituer un défaut de persistance identifié.

Chaque carte possède maintenant un bouton-lien « Modifier » qui vise `#catalog-editor`. Le formulaire est prérempli, les récompenses associées accessibles et les filtres conservés. Après sauvegarde, la redirection 303 retourne aussi vers l’éditeur. Création et modification gardent des titres distincts ; les statuts permettent archivage/restauration sans perte des références. Le bouton-lien est natif, accessible au clavier.

## Permissions et sécurité

Le contrôleur `GuildCatalogController` et le service `GuildCatalogService` vérifient explicitement le droit `PermissionService::edit`. Le service exige aussi `PermissionService::member`, excluant les comptes devant changer leur mot de passe. MEMBER reste refusé côté serveur. Aucun nouveau rôle ni mécanisme d’authentification.

CSRF, identité Discord, liste de champs, PDO préparé, validations, transactions, relations parent/enfant, version attendue, conflits 409 et audit restent actifs. Les droits de `GuildDivisionService` ne sont pas modifiés. Un EDITOR responsable de division obtient le droit catalogue par son rôle EDITOR, sans obtenir la gestion des divisions étrangères.

## Administration

Premier niveau : Vue d’ensemble, Succès, Tour de guilde, puis Utilisateurs / Paramètres Discord / Historique / Mon mot de passe selon les droits existants.

Sous-menu Succès : Gestion des succès, Catégories, Titres, Voir le Codex. Catégories et Titres sont visibles uniquement aux administrateurs conformément aux permissions existantes. Le sous-menu s’affiche sur la liste, l’édition des succès et les taxonomies. Routes, données, formulaires et filtres existants conservés. Il reprend la classe `admin-nav` du sous-menu Tour.

## Codex unifié

Navigation membre : Accueil, Le Codex, Tour de guilde, Mon profil ; lien Administration conservé pour les rôles autorisés.

- `/succes` : À débloquer, cartes, par défaut.
- `/succes?progress=remaining` : À débloquer.
- `/succes?progress=obtained` : Obtenus, date décroissante par défaut.
- `/succes?progress=remaining&view=list` : manquants en liste.
- `/succes?progress=obtained&view=list&sort=obtained-desc` : historique complet obtenu, du plus récent au plus ancien.

Les deux onglets affichent les compteurs persistants, indépendamment de la recherche. La déclaration et son annulation confirmée actualisent les compteurs, la date et l’appartenance à l’onglet sans rechargement. Les actions existantes sont réutilisées. Aucun suivi local parallèle n’a été créé.

Cartes et liste réutilisent les mêmes éléments et formulaires. En liste : nom, catégorie, récompenses, statut, date si obtenu, guide et action personnelle. Les descriptions longues sont réservées aux cartes et fiches. Les colonnes deviennent verticales sur smartphone. La liste contient tous les succès obtenus disponibles, sans limite de cinq éléments. Recherche, filtres, rareté et tris restent disponibles ; changer de filtre conserve l’onglet et le mode. Les paramètres sont écrits dans l’URL. « Réinitialiser » revient aux filtres par défaut et à À débloquer.

`/mes-succes` et `/mes-succes/` redirigent en 302 vers le Codex. Sans paramètre, l’ancien choix Obtenus est conservé ; `progress=remaining` reste respecté. Les autres paramètres de recherche, filtre, tri et affichage sont transmis. Les fiches `/succes/{slug}` restent inchangées. L’ancien template délègue au Codex afin de ne pas maintenir deux interfaces.

« Tout voir » de Vos derniers exploits (accueil et profil) ouvre Obtenus/Liste/date décroissante. La section des derniers exploits reste inchangée. Le lien des succès manquants de l’accueil pointe directement vers le Codex.

## Vérifications

- `sh tests/run-admin.sh` : **562 contrôles OK**, sortie 0, Docker isolé avec destruction automatique de la base de tests.
- `node --test tests/*.test.mjs` : **20 tests réussis**. Nouveau test d’un historique de 12 succès, ordre de dates décroissant et combinaison avec recherche/onglets.
- `tests/ux-unified.php` : onglet par défaut, ordre, liens directs, redirections et filtres, sous-menu, absence du doublon Catégories, bouton d’édition, préremplissage, création EDITOR, modification ADMIN, archivage SUPER_ADMIN avec le propriétaire de test existant, restauration EDITOR, refus MEMBER, CSRF, version périmée et audit.
- Suites existantes de catalogue, suivi, déclarations/annulations, divisions, migration, sécurité et prototype exécutées. Leurs attentes de routes ont été adaptées à la fusion, et l’ancienne interdiction EDITOR du catalogue retirée conformément à la nouvelle règle.
- Navigateur isolé : déclaration réelle de fixture → 3 à débloquer / 1 obtenu ; annulation via la confirmation existante → 4 / 0 ; recherche « elan » → un résultat ; passage Cartes/Liste ; sélection d’un palier → niveau et titre préremplis, récompenses présentes et formulaire atteint par ancre.
- Liste et formulaires contrôlés à **320, 390, 768, 1200, 1440 px** : aucun débordement horizontal ; les enfants de la ligne restent dans ses limites. Largeur de ligne égale à celle de la zone de résultats à chaque taille. Un conflit de spécificité CSS avec la grille des cartes a été identifié puis corrigé pendant cette vérification.
- `git diff --check` réussi.

Logs : `/private/tmp/levelingwiki-ux-tests.log`, `/private/tmp/levelingwiki-ux-js.log`. Capture : `/Users/nicolaspaiva/.codex/visualizations/2026/10/24/01a16f37-2ca0-7a32-a45f-e12532c5a57e/codex-unifie-liste.png`.

Limites : contrôles responsive dans le navigateur intégré, pas sur Safari iOS matériel. Recherche et filtres dynamiques nécessitent JavaScript, comme auparavant ; sans JavaScript, les onglets, le mode d’affichage, les fiches et les formulaires restent accessibles. L’historique montre les succès actuellement disponibles conformément aux règles existantes de publication ; cette intervention ne réexpose pas les succès archivés ou dépubliés.

## Fichiers créés

- `app/Views/admin/achievement-navigation.php`
- `tests/ux-unified.php`
- `docs/correctifs-phase-c-ux.md`

## Fichiers modifiés pendant cette intervention

- `app/Controllers/GuildCatalogController.php`, `app/Services/GuildCatalogService.php`
- `app/Views/admin/guild-navigation.php`, `app/Views/admin/guild-catalog.php`, `app/Views/admin/layout.php`
- `app/Controllers/CatalogController.php`, `app/Controllers/MemberController.php`, `public/index.php`
- `app/Views/catalog.php`, `app/Views/member/achievements.php`, `app/Views/member/home.php`
- `app/Views/partials/navigation.php`, `app/Views/partials/recent.php`, `app/Views/partials/catalog-results.php`, `app/Views/layout.php`
- `public/assets/js/app.js`, `public/assets/js/catalog-core.mjs`, `public/assets/css/valhalla.css`
- `tests/catalog.test.mjs`, `tests/dynamic-progress.php`, `tests/phase4.php`, `tests/phase5.php`, `tests/valhalla.php`, `tests/illustrations.php`, `tests/guild-catalog.php`, `tests/run-admin.sh`

Les fichiers de la phase C déjà présents dans le working tree avant cette intervention restent conservés ; `.env.example` est une modification préexistante non touchée.
