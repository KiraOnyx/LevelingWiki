# Navigation fixe et suivi dynamique — livraison UX

## Audit préalable

- Le filtre latéral dépendait d'un viewport de 1 200 px ; il ne connaissait pas la largeur réelle de sa collection.
- Le header et les filtres défilaient avec la page. La hauteur du header varie avec les retours à la ligne et les safe areas.
- `card.php` réservait le formulaire de progression à Mes succès. Le Codex imposait une visite de la fiche.
- `app.js` exécutait `location.reload()` après une déclaration dans Mes succès. La réponse POST ne contenait que l'identifiant et l'état.
- `PersonalService::overview()` calculait déjà les totaux fiables, les récompenses documentées et la répartition par catégorie : aucun second moteur métier n'était nécessaire.
- La recherche globale apparaissait dans toutes les pages membres et administratives, même sans collection. Mes succès reproduisait le butin et la répartition de l'accueil.
- L'import local possède une route, un service et des tests de compatibilité. Seule son interface et son lecteur localStorage sont retirés ; les données déjà importées sont préservées.
- Les formulaires Discord réutilisaient la mise en page des actions personnelles ; labels, identifiants et boutons manquaient de groupement.

## Implémentation

### Disposition et navigation

La collection est un conteneur CSS `inline-size`. Dès 1 040 px disponibles, elle utilise une colonne de filtres de 260 px et une grille de cartes d'au moins 340 px. En dessous, les filtres reviennent au-dessus et les cartes s'adaptent avec `auto-fill` ; sous 650 px disponibles, une seule colonne. Le repli des filtres suit également la taille du conteneur via ResizeObserver.

Le header est sticky, opaque, à z-index 50. Un ResizeObserver mesure sa hauteur réelle dans `--navigation-height`. Les filtres sticky se positionnent 16 px plus bas et leur hauteur maximale tient dans le viewport dynamique. Ils défilent intérieurement uniquement si nécessaire. Safe area supérieure et marges de défilement sont prises en compte.

Les mises à jour conservent le scroll courant avec limitation naturelle par la nouvelle hauteur. Elles n'imposent aucun retour en haut. Les onglets de collection conservent les autres filtres et ne rechargent plus la page.

### Cartes et progression

Les cartes du Codex et de Mes succès partagent le même formulaire existant `/progression`, son CSRF et sa confirmation d'annulation. Description et conditions sont affichées avec un extrait maximal de 280 caractères chacune ; le titre et le lien secondaire « Voir le guide » ouvrent toujours la fiche complète. L'action « Consulter » est retirée.

Après confirmation serveur : état, bouton, date, compteur, pourcentage et résultats sont mis à jour. La carte reste visible dans « Tous » et disparaît du filtre auquel elle ne correspond plus. L'état « Marqué comme obtenu » conserve une action distincte d'annulation. Une confirmation visuelle courte respecte reduced-motion. La source de vérité est toujours la base ; les totaux proviennent de `overview()`, y compris les récompenses inconnues et les grandes valeurs numériques représentées en chaînes. Les points de rendu des récompenses et catégories savent appliquer ces valeurs sans recalcul client.

Mes succès conserve une progression synthétique et sa collection. Butin et domaines restent sur l'accueil, accessibles par un lien explicite. Le Codex affiche un compteur compact obtenu/total et le pourcentage. Aucun changement de récompenses, catégories, droits ou règles métier.

### Recherche, profil et Discord

La recherche est dans le panneau de filtres des deux collections. Une recherche contextuelle insensible aux accents est ajoutée à la liste administrative des succès. Aucune recherche sur l'accueil, le profil ou les autres pages administratives.

L'import local n'est plus affiché dans le profil. Le service et la route de compatibilité restent protégés et testés ; aucune progression ni donnée localStorage n'est supprimée.

Les paramètres Discord sont regroupés en connexion, règles d'accès, puis rôles ADMIN / EDITOR / MEMBER. Les identifiants restent séparés des libellés et les formulaires s'empilent sur mobile. Aucun secret affiché, aucune modification OAuth ou permission. Les mêmes actions POST sont conservées.

## Utilisateurs : recommandation à valider, non appliquée

Conserver cet onglet. Il assure encore la suspension individuelle indépendante de Discord, la réactivation, l'invalidation des sessions (`session_version`), la maintenance de l'email facultatif et des mots de passe locaux. Auth vérifie l'activité et la version de session. SUPER_ADMIN reste local, protégé contre la désactivation/rétrogradation, et sa récupération CLI reste disponible.

Proposition précise pour une intervention validée séparément :
1. Retirer la création locale ADMIN/EDITOR de l'interface courante : ces comptes historiques ne peuvent plus se connecter localement.
2. Afficher les rôles Discord en lecture seule dans Utilisateurs : le sélecteur actuel est ignoré côté serveur, les rôles autorisés étant la référence.
3. Conserver les actions de suspension/réactivation avec confirmation du mot de passe propriétaire et invalidation des sessions.
4. Regrouper la maintenance locale et les indications de récupération dans une section propriétaire, sans supprimer les routes ni comptes.

Aucune de ces modifications structurelles n'a été appliquée. L'onglet et ses protections restent en place.

## Fichiers de cette intervention

Créés : `docs/navigation-suivi-dynamique.md`, `tests/dynamic-progress.php`, `tests/dynamic-ui.test.mjs`.

Modifiés :
- `app/Controllers/MemberController.php`
- `app/Views/partials/navigation.php`
- `app/Views/partials/catalog-results.php`
- `app/Views/partials/card.php`
- `app/Views/partials/ascension.php`
- `app/Views/partials/estimates.php`
- `app/Views/partials/category-progress.php`
- `app/Views/member/progress-form.php`
- `app/Views/member/achievements.php`
- `app/Views/member/profile.php`
- `app/Views/admin/discord.php`
- `app/Views/admin/achievements.php`
- `app/Views/layout.php`, `app/Views/admin/layout.php`
- `public/assets/css/valhalla.css`
- `public/assets/js/app.js`, `public/assets/js/interface.js`
- `tests/phase5.php`, `tests/run-admin.sh`, `README.md`

Pas de migration, nouvelle dépendance, changement Git ou modification des données réelles. Les autres modifications du dépôt préexistaient.

## Commandes de vérification

```sh
sh tests/run-admin.sh
node --test tests/catalog.test.mjs tests/interface.test.mjs tests/dynamic-ui.test.mjs
docker compose config --quiet
git diff --check
```

Le projet Compose de test utilise uniquement `levelingwiki-tests`, MariaDB en tmpfs, port 8081 et API Discord simulée. L'application principale reste au port 8080.

## Vérifications visuelles réalisées

Navigateur intégré Codex : 204 mesures (12 pages × 17 largeurs), aucun débordement horizontal global. Largeurs : 320, 375, 390, 430, 600, 768, 820, 1024, 1087, 1088, 1199, 1200, 1366, 1440, 1920, 2560, 3840 px. Hauteur 844 px pour les petits écrans, 900 px pour les autres ; sticky également vérifié à 1366×768.

Pages : accueil, Codex, collection personnelle, profil, fiche, dashboard admin, liste admin, formulaire de succès, paramètres Discord, utilisateurs, connexion, 404.

Transitions complémentaires : conteneur 1 039 px → disposition horizontale et cartes de 333 px ; conteneur 1 040 px → sidebar et cartes de 368 px. Le seuil réel est ici atteint entre les viewports 1 102 et 1 103 px, en tenant compte des marges et de la scrollbar.

À scroll 3 053 px : header en haut (bas à 107 px), filtres à 123 px, panneau de 627 px pour un contenu de 781 px. Les contrôles du bas restent accessibles par défilement interne. Un changement de type a conservé exactement le scroll à 886 px.

Interactions vérifiées : déclaration depuis le Codex desktop/mobile, confirmation d'annulation, collection obtenus/manquants sans reload, carte retirée après changement d'état, totaux serveur actualisés (ex. 3/28, 10,7 % dans les fixtures), recherche administrative « elan » trouvant « Élan », formulaire de renommage d'un rôle Discord simulé. Aucune erreur JavaScript capturée.

## Limites

Pas de tests physiques iPhone/Android ni de matrice Safari/Firefox/Edge. Les safe areas sont prévues en CSS mais n'ont pas été mesurées sur une encoche réelle. Les mesures de largeur ne certifient pas toutes les interactions dans tous les navigateurs. Les pages sans action de progression relisent les données à la navigation ; pas de synchronisation temps réel entre onglets. Sans JavaScript, les déclarations utilisent toujours le parcours serveur avec rechargement. En cas de réponse réseau perdue après validation serveur, l'interface n'annonce pas de réussite et invite à réessayer ; la route reste idempotente pour une déclaration identique.

## Résultats finaux

- Suite complète `sh tests/run-admin.sh` : code de sortie 0, nouveau test dynamique inclus. Authentification simulée, propriétaire, permissions, CSRF, importation, migrations et édition rapide restent couverts.
- JavaScript : 12 tests réussis, dont calcul affiché exclusivement depuis la réponse serveur, précision des grandes récompenses, doubles soumissions et erreurs réseau.
- Syntaxe PHP : tous les fichiers de `app`, `tests` et `bin` passent `php -l` dans Docker.
- `docker compose config --quiet` et `git diff --check` : réussite.
- Environnement de test arrêté automatiquement ; web principal actif sur 8080 et MariaDB healthy.
- La préparation initiale du test a été corrigée pour comparer correctement un pourcentage JSON entier à son équivalent PHP flottant. Une fixture de défilement a aussi été ajustée pour respecter la contrainte existante exigeant une date pour un succès vérifié. Ces corrections concernent uniquement les tests isolés.
