# Catégories, espace et installation mobile

## Audit du 23 octobre 2026

Architecture PHP/PDO et templates conservés. `categories` possède id, slug unique, nom unique, couleur et archivage ; `achievements.category_id` est une clé étrangère. Les slugs historiques sont bleu, rose, jaune, turquoise et vert, avec ids 4–8 dans la base auditée. « Même jour, autre monde » avait une catégorie NULL et quatre récompenses NULL. Le PDF disponible dans Downloads est nommé `Codex_Succes_Secrets_Valhalla (1).pdf` : ses pages 2–8 correspondent au guide décrit ; la fiche 33 figure sur la page verte avec récompense inconnue.

Le Codex était limité au conteneur commun et à deux colonnes. Les marges fixes héritées pouvaient entrer en conflit avec une largeur fluide. Les tableaux administratifs avaient un défilement local mais pas de cible clavier explicite. Aucun manifest, service worker ou métadonnée Apple n’était présent. Les images WebP existantes sont conservées, ainsi que l’opacité de Yggdrasil.

## Modèle de catégories

| Slug conservé | Ancien nom | Nouveau nom | Couleur |
|---|---|---|---|
| bleu | Bleu | Timing et endurance | #4F8FD1 |
| rose | Rose | Actions et conditions spéciales | #C778A8 |
| jaune | Jaune | Progression et défis | #D2AA56 |
| turquoise | Turquoise | Découvertes et butin | #39AAA7 |
| vert | Vert | Régularité et séries | #70A47A |

Migration `014_category_identity.php` : transaction, ciblage exact slug/nom historique (ou nom final pour réexécution), contrôle de conflit d’unicité, aucun DELETE/INSERT de catégorie. Les catégories personnalisées sont ignorées. Les identifiants et slugs sont conservés. Seul le succès au slug exact `meme-jour-autre-monde`, au nom exact attendu et sans catégorie est rattaché à vert. Aucune récompense ni progression n’est modifiée. Un conflit de nom annule toute la transaction et nécessite une résolution explicite.

`CategoryIdentity` centralise les noms et couleurs de référence pour l’import et la correspondance exacte slug → classe visuelle. Les variables CSS centralisent les accents de présentation. Les autres catégories gardent leurs libellés et couleur enregistrée pour la pastille ; leur bordure utilise l’accent neutre. Aucune inférence approximative depuis le nom. La migration embarque une copie figée de la correspondance pour rester indépendante des changements futurs du service.

L’import accepte les anciens libellés de la source et les nouveaux noms. Il crée les catégories sous les noms finaux, réutilise les ids et refuse les conflits. « À confirmer » est uniquement accepté pour le slug de la fiche 33, et est résolu vers vert ; ce n’est jamais une catégorie créée. Les réimportations continuent d’ignorer les succès existants sans réécrire leurs contenus.

## Interface

Conteneur Codex/Mes succès jusqu’à 1440 px ; une, deux ou trois colonnes selon l’écran, sans étirer une carte unique sur toute la grille. Les autres vues conservent leurs largeurs adaptées. Filtres à cinq, trois, deux ou une colonne. Cartes sombres avec bordure gauche, badge textuel, contour d’icône et légère teinte ; couleurs des récompenses inchangées. La catégorie reste lisible sans perception des couleurs.

Section d’installation en bas de l’accueil, deux cartes Safari/Android qui s’empilent sur mobile. Logo existant rasterisé en PNG 180/192/512 px, sans dépendance ajoutée à l’application.

## Installation et sécurité

Manifest `/manifest.webmanifest` : LevelingWiki, nom court Valhalla, start_url `/`, scope `/`, standalone, thème #111923, fond #090E15. Métadonnées Apple sur le template membre et la connexion. Aucun service worker, cache privé, notification ou mode hors ligne. Le démarrage passe par la route normale : anonyme → connexion, membre authentifié → accueil. Les règles de session et OAuth restent inchangées.

Utiliser HTTPS en production et une URL accessible depuis le téléphone ; `localhost` sur le téléphone désigne le téléphone, pas le Mac. Docker reste lié à 127.0.0.1 : aucun port n’est exposé au réseau pour cette intervention. Le partage de session entre Safari/Chrome et une fenêtre installée dépend de la plateforme ; une nouvelle connexion peut être demandée. Les libellés et possibilités d’installation varient selon les versions ; aucun ajout automatique n’est promis.

Sources officielles consultées : [Apple — Ajouter un site à l’écran d’accueil](https://support.apple.com/guide/iphone/open-as-web-app-iphea86e5236/ios), [Chrome — Manifest et critères d’installation](https://developer.chrome.com/docs/lighthouse/pwa/installable-manifest). Un manifest ne garantit pas à lui seul une proposition d’installation dans tous les navigateurs.

## Tests et application

La suite `sh tests/run-admin.sh` s’exécute dans le projet Docker jetable `levelingwiki-tests`. Elle inclut les tests d’authentification, permissions, CSRF, déconnexion, progression, administration, captures, import, migrations et routes, ainsi que `tests/category-identity.php`. Ce dernier vérifie les ids, noms/couleurs, catégories personnalisées, associations, récompenses, progressions, conflit de nom avec rollback, idempotence, manifest/icônes et propagation dans les filtres/formulaires.

Une sauvegarde privée complète `storage/backups/pre-category-014.sql` a été réalisée. La copie a été restaurée uniquement dans la base de test, puis comparée avant/après avec `tests/category-copy.php` : toutes les tables et valeurs sont identiques sauf les cinq noms/couleurs, leur date de modification et le rattachement explicitement demandé. Une seconde exécution du migrateur n’a rien modifié. La migration a ensuite été appliquée à la base locale. Les ids 4–8, les 33 succès et les quatre NULL de la fiche 33 sont conservés. Aucune publication n’est effectuée par la migration.

Les sauvegardes contiennent des données privées et sont exclues de Git. Ne pas les partager. En cas de retour arrière, sauvegarder d’abord l’état courant et travailler sur une copie : ne pas restaurer aveuglément une sauvegarde complète par-dessus des modifications utilisateur ultérieures.

Commandes exécutées :

```sh
sh tests/run-admin.sh
node --test tests/catalog.test.mjs tests/interface.test.mjs
docker compose config --quiet
docker compose exec -T web php bin/migrate.php
# Dans le projet Docker de test, sur copie uniquement :
docker compose --env-file .env -f tests/compose.yaml exec -T web php tests/category-copy.php before
docker compose --env-file .env -f tests/compose.yaml exec -T web php bin/migrate.php
docker compose --env-file .env -f tests/compose.yaml exec -T web php tests/category-copy.php after
git diff --check
```

La syntaxe de tous les fichiers PHP a également été vérifiée dans le conteneur. Les huit tests JavaScript passent. Les ressources manifest et icônes sont servies en HTTP 200 ; le manifest possède le MIME `application/manifest+json`.

### Vérifications navigateur

Navigateur intégré : mesures sur 15 pages × 11 résolutions, soit 165 contrôles de largeur, sans débordement horizontal global. Résolutions CSS : 320×568, 390×844, 430×932, 768×1024, 1024×768, 1366×768, 1920×1080, 2560×1440, 3840×2160, 600×800 et 3440×1440. Pages : connexion, 404, accueil, Codex, Mes succès, profil, fiche détaillée, dashboard admin, liste des succès, formulaire existant et nouveau, catégories, utilisateurs, rôles Discord, historique. Les petits écrans membres ont été revérifiés après l’ajustement final des marges.

Inspection visuelle ciblée : connexion à 320 px, Codex trois colonnes à 1920 px, formulaire administratif à 320 px, section d’installation empilée puis à deux colonnes. Tests interactifs : navigation, recherche, filtre Régularité et séries + Secrets (5 résultats), tri Z–A, enregistrement administratif réussi et déclaration d’obtention persistante reflétée sur l’accueil. Les quatre récompenses inconnues restent affichées comme telles. Aucune erreur console observée. Toutes ces opérations interactives utilisent exclusivement un compte et des contenus isolés ; les publications de démonstration ne concernent pas la base principale.

Les tables administratives sont des régions défilantes localement, accessibles au clavier. Les illustrations et leur opacité ne changent pas. La palette de catégorie colore les bordures et badges ; le texte des badges garde la couleur claire du thème, sans dépendre d’une couleur peu contrastée pour être lu.

## Inventaire de cette intervention

Créés :

- `app/Services/CategoryIdentity.php`
- `database/migrations/014_category_identity.php`
- `app/Views/partials/mobile-install.php`, `mobile-meta.php`
- `public/manifest.webmanifest`
- `public/assets/icons/icon-192.png`, `icon-512.png`, `apple-touch-icon.png`
- `tests/category-identity.php`, `tests/category-copy.php`
- `docs/categories-mobile.md`

Modifiés :

- `app/Services/AchievementImportService.php`, `PersonalService.php`
- `app/Models/AchievementRepository.php`
- `app/Views/layout.php`, `detail.php`, `member/home.php`
- `app/Views/partials/card.php`, `category-progress.php`
- `app/Views/admin/layout.php`, `dashboard.php`, `achievements.php`, `history.php`
- `public/assets/css/valhalla.css`
- `tests/import.php`, `illustrations.php`, `http.php`, `run-admin.sh`

## Limites et validation personnelle

Les mesures de viewport ne prouvent pas une compatibilité universelle ni l’absence de tout chevauchement possible avec des données futures. Aucun appareil iOS/Android physique n’a été utilisé ; aucune installation native/standalone réelle ni parcours OAuth depuis une application installée n’a été exécuté. Safari, Firefox et Edge n’ont pas fait l’objet de sessions distinctes. La route de démarrage fonctionne après authentification dans le navigateur de test, mais le partage des cookies entre navigateur et raccourci reste à vérifier sur téléphone.

Pas de notification, service worker, cache hors ligne ou promesse d’installation automatique. Vérifier sur votre domaine HTTPS les menus d’ajout à l’écran d’accueil, l’icône et le retour après connexion Discord. Vérifier également les cinq couleurs sur votre écran, les tableaux mobiles et les libellés des filtres. La capture Anime-Sama n’était pas jointe à ce message ; la section suit les instructions textuelles et le design existant.

Démarrage : `docker compose up -d --build`, puis `docker compose exec -T web php bin/migrate.php` sur un autre environnement à mettre à jour. Sur cet environnement, la migration 014 est déjà appliquée. Aucun nouveau service permanent, branche, commit, push ou merge.
