# Phase D — Récompenses personnelles et coopération

## Audit préalable

Les succès disposent de quatre colonnes de ressources (XP, or, gemmes et clés), distinctes du catalogue dynamique des récompenses de la Tour. Le suivi personnel impose une paire unique membre/succès. `PersonalService::overview` centralise déjà les données de l’accueil et du profil. Les succès publiés, y compris secrets selon les règles actuelles du Codex, sont accessibles aux membres ; les brouillons et archives sont exclus.

La migration 017 des guides a été vérifiée dans l’environnement existant : appliquée, empreinte conforme au fichier. Aucune migration historique n’a été modifiée. La migration additive 018 ajoute `participants_required`, entier de 1 à 100, valeur initiale 1. Elle est appliquée uniquement dans les bases de test isolées. Son application dans l’environnement existant reste à effectuer lors du déploiement ; aucune donnée réelle n’a été modifiée pendant cette intervention.

Les récompenses de palier référencent leur palier avec une clé étrangère restrictive. Aucune récupération personnelle de récompense de tour n’existe dans ce modèle. Les types partagés, divisions, niveaux et registres d’initialisation sont indépendants et doivent rester intacts.

## Fonctionnement

### Estimations personnelles

Une agrégation SQL paramétrée additionne les valeurs actuelles des succès publiés non archivés. La jointure sur l’identifiant du membre et la contrainte d’unicité du suivi empêchent les doubles comptages. Les titres ne sont pas joints à l’agrégation, évitant toute multiplication des montants.

Pour chaque ressource : obtenu = somme des succès déclarés obtenus ; restant = somme des autres succès accessibles ; potentiel = somme des deux groupes. Les sommes MariaDB restent des chaînes décimales, sans conversion flottante ni dépassement des entiers PHP. Les valeurs NULL sont comptées séparément comme inconnues, jamais présentées comme des zéros confirmés. Chaque sous-total incomplet porte une indication « Partiel ». Les totaux sont explicitement des estimations, sans crédit dans Leveling Fitness. Une annulation ou une modification éditoriale est prise en compte au prochain calcul.

Le profil présente les trois montants et la progression par catégorie (obtenus / accessibles, pourcentage arrondi à une décimale). Les couleurs proviennent des catégories. L’accueil conserve une synthèse compacte et un lien vers le profil. Les contrôleurs utilisent l’identité de session, pas un identifiant de membre fourni dans l’URL.

### Codex et coopération

Les filtres et tris de ressources sont générés depuis les descripteurs du modèle des succès existant. Les ressources de la Tour ne sont pas assimilées à celles des succès. Le filtre « À confirmer » inclut les montants NULL et les titres non renseignés. Le tri utilise BigInt et place les montants inconnus à la fin. Les paramètres se combinent avec recherche, catégorie, onglet et mode d’affichage.

Le formulaire administratif accepte 1 à 100 participants. EDITOR, ADMIN et SUPER_ADMIN conservent leurs permissions existantes de gestion des succès. Un champ omis lors d’une modification préserve la valeur enregistrée. Au-delà de 1, un badge « À réaliser à N » apparaît sur la carte et la fiche, sans modifier les guides ni supposer l’appartenance des participants à Valhalla. Aucun système de partenaires n’a été créé.

### Suppression définitive d’un palier

POST `/admin/tour-de-guilde/paliers/delete` : ADMIN et SUPER_ADMIN uniquement, authentification, CSRF, confirmation explicite et version attendue obligatoires. Le service verrouille le palier et ses récompenses, supprime uniquement ces récompenses puis le palier, et écrit l’audit dans la même transaction. Une version périmée ou une dépendance restrictive entraîne un refus sans suppression partielle. Les types globaux, divisions et autres paliers restent présents. Le registre d’import n’est pas supprimé : un palier retiré n’est pas réinséré par l’initialisation.

L’audit `guild.catalog.delete` conserve identifiant, titre/niveau, auteur, date, nombre et identifiants des associations retirées. Aucun exemplaire récupérable n’est conservé dans les tables métier. La confirmation accessible utilise le composant existant, Annuler reçoit le focus initial et Échap annule. Sans JavaScript, une case obligatoire explicite la confirmation. Les filtres de liste sont conservés après suppression.

## Validation manuelle

1. Dans un environnement isolé migré, ouvrir le profil : comparer obtenu, restant et potentiel avec les succès disponibles. Déclarer puis annuler un succès depuis le Codex et recharger le profil.
2. Modifier une récompense, laisser un montant inconnu, vérifier les totaux et l’indication partielle. Aucun montant n’est transféré dans le jeu.
3. Combiner recherche/catégorie/ressource, trier par quantité, changer d’onglet et de mode Cartes/Liste ; vérifier les paramètres d’URL.
4. Avec un éditeur, régler un succès sur 2 participants : contrôler carte et fiche. Vérifier le refus de 0, 101 et d’une valeur décimale.
5. Créer un palier jetable uniquement en test. Avec ADMIN, ouvrir « Supprimer définitivement », annuler, puis confirmer. Contrôler l’absence réelle du palier et de ses récompenses, la présence des types/divisions et l’audit. EDITOR ne doit pas pouvoir supprimer, même par requête forgée.
6. Vérifier smartphone et clavier, notamment les noms longs, filtres, badges et confirmation.

## Tests et captures

Validation du 27 octobre 2026 : `sh tests/run-admin.sh` termine avec le code 0, **692 vérifications PHP réussies**, dont les cas Phase D, et `node --test tests/*.test.mjs` : **30 tests réussis, 0 échec**. Le test de comparaison JSON/PHP des catégories a été normalisé pour les pourcentages entiers (33.0 encodé 33) sans retirer de contrôle.

Contrôles de dimensions : sept écrans (profil, accueil, Codex cartes/liste, fiche, formulaire de succès, paliers) aux neuf largeurs 320, 375, 390, 430, 768, 1024, 1200, 1440 et 1920 px, plus la confirmation ouverte : aucun débordement horizontal détecté. Inspection graphique complémentaire sur ordinateur et mobile : cartes avec noms et catégories longs, badges SECRET/cooperation, récompenses partielles, filtres ouverts, fiche, formulaire et confirmation. Filtre Gemmes testé dans le navigateur : un résultat cohérent et paramètre URL conservé. Annulation du dialogue et retour du focus vérifiés. Aucun défaut visuel majeur observé sur ces fixtures.

Captures et relevés : `/Users/nicolaspaiva/.codex/visualizations/2026/10/24/01a16f37-2ca0-7a32-a45f-e12532c5a57e/phase-d-*` (profil ordinateur/mobile, carte coopérative mobile, confirmation mobile, mesures JSON). Les captures illustrent des données de test, pas des données de membres réels. Les mesures ne constituent pas un test sur un iPhone physique ni une validation de son clavier virtuel. Les rendus visuels sont générés par `tests/render-phase-d-visual.php` dans Docker isolé avec OAuth Discord simulé, puis consultés comme pages statiques locales. Aucune authentification Discord réelle ni suppression réelle n’est déclenchée par le navigateur. Les écritures et transactions sont vérifiées par les tests HTTP/SQL isolés. Safari iOS physique n’est pas disponible.

## Périmètre

Aucune branche, commit, push ou merge. Aucun changement de phase E, de PWA ou de messagerie. La modification préexistante de `.env.example` est conservée sans intervention.

## Fichiers créés

- `app/Views/partials/personal-rewards.php`
- `database/migrations/018_achievement_participants.php`
- `docs/phase-d-recompenses-cooperation.md`
- `public/assets/css/personal-rewards.css`
- `tests/phase-d.php`
- `tests/phase-d.test.mjs`
- `tests/render-phase-d-visual.php`

## Fichiers modifiés

- `app/Controllers/AdminController.php`
- `app/Controllers/GuildCatalogController.php`
- `app/Models/AchievementRepository.php`
- `app/Services/AchievementService.php`
- `app/Services/Audit.php`
- `app/Services/CatalogService.php`
- `app/Services/GuildCatalogService.php`
- `app/Services/PersonalService.php`
- `app/Views/admin/achievement-form.php`
- `app/Views/admin/guild-catalog.php`
- `app/Views/admin/layout.php`
- `app/Views/detail.php`
- `app/Views/layout.php`
- `app/Views/member/profile.php`
- `app/Views/partials/card.php`
- `app/Views/partials/catalog-results.php`
- `app/Views/partials/category-progress.php`
- `app/Views/partials/estimates.php`
- `public/assets/css/admin-forms.css`
- `public/assets/js/admin-forms.js`
- `public/assets/js/app.js`
- `public/assets/js/catalog-core.mjs`
- `public/assets/js/interface.js`
- `public/index.php`
- `tests/dynamic-progress.php`
- `tests/dynamic-ui.test.mjs`
- `tests/run-admin.sh`
