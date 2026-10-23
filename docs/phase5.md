# Phase 5 — Valhalla

## A. Audit préalable (avant modification)

Pages examinées : catalogue/accueil (même vue), détail et captures, connexion/refus, profil/progression, tâches ; administration : tableau de bord, succès/liste/édition, catégories/titres, comptes, rôles Discord, historique, mot de passe, statut et erreurs.

Constats : deux destinations Accueil/Succès identiques ; profil mêlant identité, statistiques, tâches et tous les formulaires de progression ; fiche avec suivi global, formulaire et bouton obtenu redondants. En-têtes membre/admin dupliqués ; administration visible sans permission. Recherche unique au catalogue mais absente ailleurs. Formulaires POST sécurisés mais changement de progression quittant le catalogue ; pas de gestion commune des doubles soumissions. Tableaux administratifs sans filtres contextuels.

Modèle : discord_identities impose une identité Discord unique et une relation 1:1 avec users. personal_achievements est la source unique pour progression et tâches, avec unicité membre/succès, index membre/statut, clés étrangères et CHECK de cohérence. Les comptes sont retrouvés par ID Discord, pseudo/avatar actualisés ; une révocation conserve compte et progression. Aucun besoin de nouvelles tables ni de migration. Les 12 migrations restent inchangées.

Permissions : contrôles serveur dans Auth, AdminGuard, DiscordAuthService, MemberController et services. MEMBER requis avant EDITOR/ADMIN ; OWNER local exclusif (super_admin interne), impossibilité de promotion Discord ; CSRF et vérification fraîche pour écritures. Maintenir ces contrôles, les NULL/zéro, captures privées, brouillons, audit, import et ancien suivi local.

Performance : chargement groupé du catalogue et des titres, pas de requête par carte ; catalogue actuel de 33 succès. Éviter les parcours imbriqués pour associer la progression. Pagination serveur à envisager si le volume augmente fortement, sans inventer une complexité immédiate.

## Plan de réalisation

B. Réutilisation du schéma et services. C. Tokens et composants Valhalla partagés. D. En-tête unique et recherche du wiki depuis toute page autorisée. E. Accueil personnel, Mes succès, progression sur place. F. Administration harmonisée et filtres contextuels. G. Tests Docker isolés et vérification responsive.

## B–F. Réalisation

- **Modèle conservé** : aucune migration, aucune table ajoutée. `obtained` reste la valeur interne et devient « Terminé » dans le sélecteur ; OWNER reste `super_admin` en base. Identité Discord et progression ne dépendent jamais du pseudo.
- **Design system** : `valhalla.css` centralise palette, espacements, rayons, ombres, focus, composants de navigation, cartes et formulaires. Axentix Neu 2.0.0 reste la fondation (`neu-dark`, `neu-flat`). Les styles historiques restent chargés pour préserver les écrans existants, puis la couche Valhalla les harmonise. Aucun nouveau framework, police distante ou service permanent.
- **Navigation partagée** : un seul partiel membre/admin ; Accueil, Wiki, Mes succès, Mes tâches, Profil ; Administration seulement pour les contributeurs autorisés. Navigation mobile en grille, focus visible et état courant.
- **Recherche** : un champ unique dans l’en-tête autorisé. Depuis une autre page il ouvre le wiki ; dans le wiki/Mes succès il filtre sur place après 250 ms. Recherche du contenu publié uniquement (noms, descriptions, conditions, titres et récompenses), pas des comptes privés ou brouillons administratifs. Catégorie, rareté, type, vérification, progression, récompenses et tri restent contextuels et combinables.
- **Accueil** : statistiques personnelles réelles, progression, tâches actives et quatre dernières fiches publiées/mises à jour. L’ordre utilise la dernière modification existante ; aucune date de publication fictive n’est créée.
- **Mes succès** : réutilise le catalogue et ses cartes ; trois états, filtres, enregistrement sur place via POST CSRF. Le serveur reste l’autorité. Succès et erreurs accessibles, blocage des doubles clics, confirmation avant réinitialisation d’un succès terminé. Une erreur réseau n’annonce pas un succès ; une nouvelle tentative reste possible.
- **Profil** : identité, type de connexion, permissions et import volontaire du suivi local conservé. La liste complète des succès quitte le profil pour éviter la répétition.
- **Administration** : mêmes navigation et composants ; filtres publication/catégorie sur la liste, compte de résultats et réinitialisation ; pseudo et Discord User ID dans les comptes. Les contrôles serveur et les formulaires protégés restent en place. Retrait/destruction/abandon demandent confirmation côté interface, sans remplacer les validations serveur.
- Les cartes et formulaires de progression sont partagés entre les pages ; les cartes compactes de l’accueil ouvrent le guide. Pas de classement fictif, pas de difficulté inventée : seule la rareté disponible est affichée.

## Fichiers de cette phase

Créés :

- `docs/phase5.md`
- `app/Views/partials/navigation.php`
- `app/Views/member/home.php`
- `public/assets/css/valhalla.css`
- `public/assets/js/interface.js`
- `tests/phase5.php`
- `tests/interface.test.mjs`

Modifiés :

- `public/index.php`
- `app/Controllers/MemberController.php`, `app/Controllers/CatalogController.php`
- `app/Models/AdminRepository.php`
- `app/Views/layout.php`, `app/Views/catalog.php`, `app/Views/detail.php`, `app/Views/partials/card.php`
- `app/Views/member/profile.php`, `app/Views/member/progress-form.php`
- `app/Views/admin/layout.php`, `app/Views/admin/achievements.php`, `app/Views/admin/users.php`, `app/Views/admin/login.php`
- `public/assets/js/app.js`, `public/assets/js/catalog-core.mjs`
- `tests/http-client.php`, `tests/run-admin.sh`, `tests/catalog.test.mjs`
- `README.md`

Les autres modifications déjà présentes dans le dépôt proviennent des phases antérieures ; aucun commit ni publication distante effectué ici.

## G. Tests et validation

```sh
sh tests/run-admin.sh
docker run --rm --network none -v "$PWD:/workspace:ro" -w /workspace node:22-alpine node --test tests/catalog.test.mjs tests/interface.test.mjs
```

Le premier script utilise uniquement `levelingwiki-tests`, la base `levelingwiki_test`, un serveur Discord simulé et des stockages temporaires. Il n’utilise ni les membres ni les données existantes pour les scénarios. Il exécute migrations, administration, phases 4/5, import, intégrité, catalogue et HTTP.

La phase 5 vérifie les cinq pages autorisées, l’unicité du champ de recherche, le masquage administratif, la progression JSON persistante, le rejet d’une identité cible arbitraire, CSRF, la perte puis récupération de MEMBER sans doublon ni perte de progression. Les tests précédents couvrent première connexion, reconnexion/pseudo/avatar, permissions OWNER/ADMIN/EDITOR/MEMBER, tâches, récompenses NULL/zéro, captures, édition, audit et import.

Tests JavaScript : huit tests réussis, dont les trois états combinés avec la recherche, le conflit du champ `action` avec l’URL du formulaire, les doubles soumissions et le retour d’erreur réseau. Les assets modifiés sont versionnés pour éviter de réutiliser le moteur de filtrage de la phase précédente depuis le cache.

Contrôles navigateur sur données isolées : connexion propriétaire, recherche « elan », enregistrement « En cours » sans navigation, persistance visible à l’accueil, filtres progression/catégorie combinés, filtre administratif vide et réinitialisation. Mobile 390 px et tablette 820 px sans débordement de page ; grille à deux colonnes sur tablette, une sur mobile, filtres repliés sur mobile. Navigation Tab vers le bouton de recherche avec contour visible. Aucune erreur ou alerte JavaScript relevée après correction. Capture de l’accueil réalisée avec quatre fixtures explicitement marquées test.

## Lancement

```sh
docker compose config --quiet
docker compose up -d --build
```

Ouvrir `http://localhost:8080`. Aucune migration supplémentaire ni réinitialisation requise. La configuration Discord et la désignation explicite du propriétaire restent celles de [la phase 4](phase4.md). Aucun rôle Discord réel n’est modifié par cette phase.

## Limites

- Tests OAuth simulés : pas de validation avec le serveur Discord réel dans cette phase.
- Filtrage du catalogue et filtres administratifs exécutés dans le navigateur ; le catalogue reste lisible et les formulaires natifs utilisables sans JavaScript, mais la recherche dynamique nécessite JavaScript.
- Recherche globale limitée au wiki publié ; ce n’est pas un moteur de recherche transversal des comptes et de l’audit.
- Pas d’audit exhaustif au lecteur d’écran ni test de charge. Pagination côté serveur à prévoir au-delà du volume actuel.
- En cas de perte réseau après une écriture, l’état est « non confirmé » : recharger permet de retrouver l’état effectivement enregistré. Le serveur garde les contrôles d’autorisation, même si une page ouverte affiche encore un ancien bouton.

### Résultat final

Dernière exécution complète : **315 contrôles OK**, code de sortie 0. **8/8 tests JavaScript réussis**. Lint PHP sans erreur de syntaxe ; `git diff --check` propre. Le projet Docker isolé a été arrêté et retiré après les tests. L’environnement principal reste actif, MariaDB `healthy`, port web limité à `127.0.0.1:8080`. `docker compose config --quiet` et les contrôles HTTP anonymes réussissent. Aucun script de test n’a été exécuté contre les données principales ; le contrôle HTTP principal est uniquement en lecture.
