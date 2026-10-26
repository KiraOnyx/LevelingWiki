# Administration : ergonomie et tableau de bord

Livraison du 26 octobre 2026. Aucune migration, modification de données réelles ou opération Git. La phase D n'est pas commencée.

## Audit et corrections communes

Les dimensions provenaient de trois couches : `admin.css`, la peau `valhalla.css` et `admin-forms.css`. Les labels de filtres héritaient notamment d'alignements destinés aux formulaires ordinaires. Les panneaux et cartes mélangeaient des largeurs et espacements différents ; les actions du catalogue étaient séparées en blocs verticaux.

`admin-forms.css` consolide les dimensions administratives dans les variables `--ad-*` : texte, espacements, contrôles de 2,75 rem, rayons et largeurs simple/intermédiaire/complexe. La portée reste administrative. Grilles adaptatives, retours à la ligne et container queries remplacent les corrections propres à chaque page. Aucun nouvel `!important`.

Les filtres partagent des labels au-dessus des champs, des hauteurs identiques et un alignement commun. Les cartes du catalogue regroupent Modifier et Archiver/Restaurer horizontalement. L'espace Utilisateurs est centré et limité ; les actions se replient sur mobile. Les tableaux deviennent des lignes étiquetées sur petit écran. Les formulaires à la demande conservent Annuler, Échap, focus, confirmations et validations serveur.

Pages inspectées : Vue d'ensemble, Succès et création, Catégories, Titres, Divisions/Guild Masters, Paliers, Types, Utilisateurs, Discord, Historique et Mot de passe. Le panneau Explorer la tour range les réglages dans « Rendu et ambiance » ; les ancres et le code de positionnement architectural restent inchangés.

## Statistiques et règles de calcul

Le service `AdminDashboardService` charge des agrégations MariaDB dans une transaction de lecture cohérente. Aucun calcul SQL par membre ou par succès ; aucune statistique dérivée stockée. Les requêtes sont statiques et ne concatènent aucune entrée utilisateur. Aucun index supplémentaire n'a été ajouté.

- **Membres actifs** : comptes Discord actifs, avec identité persistée, rôle MEMBER/EDITOR/ADMIN, sans changement de mot de passe obligatoire. Les administrateurs et éditeurs éligibles sont inclus. Les comptes locaux techniques, dont le propriétaire local, sont exclus. Il s'agit du dernier état d'autorisation enregistré, pas d'un recensement Discord en temps réel.
- **Succès disponibles** : publiés et non archivés.
- **Réussites déclarées** : lignes personnelles `obtained` des membres éligibles sur les succès disponibles. Ce total n'inclut pas les anciens succès devenus indisponibles.
- **Progression moyenne** : déclarations / (membres × succès disponibles) × 100, arrondi à une décimale. Dénominateur nul : 0 %.
- **Divisions/niveau maximal** : uniquement les divisions non archivées ; maximum 0 en l'absence de division.
- **Classement** : nombre de succès disponibles obtenus, rang SQL avec égalités (1, 1, 3), progression individuelle sur le catalogue disponible. Les 20 premières lignes sont affichées ; une égalité peut traverser cette limite. Ordre secondaire stable par pseudonyme puis identifiant interne, jamais affiché.
- **Graphiques** : dix succès les plus/moins déclarés, y compris zéro déclaration. Ils ne mesurent pas la difficulté. Barres HTML natives et valeurs textuelles, sans bibliothèque additionnelle.
- **Catégories** : moyenne sur les membres éligibles et les succès disponibles de chaque catégorie active. Catégories vides conservées à 0 %. Groupe Non classés lorsque nécessaire.
- **Activité** : audits de déclaration/annulation, première connexion enregistrée (pas une date d'arrivée supposée dans la guilde), historique réel des niveaux et événements éditoriaux. Les listes sont bornées, puis fusionnées en 20 événements récents.
- **À examiner** : brouillons actifs ; succès dont au moins une récompense reste inconnue ; paliers actifs sans récompense active ou avec une récompense inconnue/incomplète/non classifiée ; divisions sans Guild Master Discord actif et éligible. Chaque compteur renvoie à la gestion correspondante.

Les indicateurs éditoriaux précédents restent dans Administration → Succès. Le lien Brouillons préremplit son filtre par URL.

## Permissions et confidentialité

La route conserve Auth/AdminGuard. Le service exige les droits de membre et d'édition. Les éditeurs voient les agrégats et l'activité éditoriale ; le classement nominatif et l'activité personnelle sont réservés aux administrateurs/propriétaire. Aucun contenu des audits de mots de passe, comptes ou permissions n'est envoyé au dashboard. Les identifiants techniques sont retirés des lignes du classement ; les avatars Discord utilisent leur URL CDN habituelle lorsqu'elle est valide.

Aucune permission d'écriture existante n'est élargie. Les protections propriétaire et Guild Master sont conservées.

## Validation

- `sh tests/run-admin.sh` : **608 assertions OK**, sortie 0, environnement `levelingwiki-tests` jetable arrêté à la fin.
- `node --test tests/*.test.mjs` : **20 tests réussis**, aucun échec.
- Nouveau test statistique : population vide, catalogue vide, membres sans réussite, égalités, comptes locaux/inactifs exclus, administrateur membre inclus, brouillons/archives exclus, catégories vides, divisions archivées, récompenses inconnues, confidentialité éditeur et refus membre. Fixtures transactionnelles annulées.
- **171 mesures de dimensions** sur les pages administratives et formulaires ouverts/fermés aux largeurs 320, 375, 390, 430, 768, 1024, 1200, 1440 et 1920 px : aucun débordement horizontal de page ou contrôle détecté.
- Inspection graphique des pages, filtres, actions et formulaires ; correction finale de la grille des six indicateurs pour éviter une tuile isolée. Inspection desktop et mobile de cette grille après correction.
- Utilisateurs/Mot de passe : rendus des vrais templates avec fixtures inertes sur serveur statique, sans création de propriétaire ni élargissement de droits. Texte agrandi à 200 % et formulaire ouvert vérifiés sur Utilisateurs, sans débordement.
- Navigation Tour vérifiée sur mobile : arrivée au pied, ouverture des réglages et accès au sommet ; géométrie et rendus couverts également par les tests JS existants.

Limites : pas d'iPhone/Safari iOS physique, ni de clavier virtuel réel testé. Les mesures ne constituent pas un test de performance à grande échelle. Le test du texte agrandi concerne le formulaire Utilisateurs, pas chaque page. Les contrôles visuels de la Tour ne remplacent pas une nouvelle campagne complète sur tous les appareils.

## Fichiers de cette intervention

Créés : `app/Services/AdminDashboardService.php`, `public/assets/css/admin-dashboard.css`, `public/assets/css/tower-navigation.css`, `tests/admin-dashboard.php`, `tests/render-admin-visual.php`, ce document.

Modifiés : `app/Controllers/AdminController.php`, `app/Views/admin/dashboard.php`, `app/Views/admin/layout.php`, `app/Views/admin/guild-catalog.php`, `app/Views/admin/users.php`, `app/Views/layout.php`, `app/Views/tower/prototype.php`, `public/assets/css/admin-forms.css`, `public/assets/js/admin-forms.js`, `public/assets/js/interface.js`, `tests/quick-status.php`, `tests/run-admin.sh`.

D'autres fichiers étaient déjà modifiés par les interventions précédentes : ils n'ont pas été réinitialisés. Le changement préexistant dans `.env.example` est conservé.
