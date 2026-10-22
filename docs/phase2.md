# Phase 2 — livraison et validation

Validation du 22 octobre 2026 sur macOS Apple Silicon, Docker 29.9.2 / Compose 5.6.0. PHP 8.2.35, MariaDB 11.4.13. Aucun commit ni déploiement distant effectué.

## Choix graphiques

Fond noir verdâtre, surfaces vert sombre, bordures fines, accents menthe pour les médailles, vert pour l’XP, ambre pour les pièces, ivoire pour les gemmes, turquoise pour les clés et violet pour les titres/secrets. Cartes arrondies et ombres douces inspirées de la capture Leveling Fitness. Grand titre éditorial, médaillon vectoriel décoratif et état vide dédié, sans faux succès. Police système sans téléchargement externe. Effets de survol discrets, désactivés avec `prefers-reduced-motion`.

Neu-Axentix **2.0.0**, distribution CSS officielle conservée localement avec sa licence MIT :

| Classe réellement utilisée | Usage |
| --- | --- |
| `neu-dark` | Thème de base sur le body |
| `neu-flat` | Statistiques, suivi, cartes, panneaux de fiche et médaillon |
| `neu-pressed` | Champ de recherche et état vide |

Les couleurs et ombres sont adaptées au vert dans `theme.css`. Les classes restantes sont des classes applicatives, pas des composants Axentix inventés. Aucune bibliothèque JavaScript Axentix n’est nécessaire pour ces ombres statiques. Sources consultées : [installation](https://neu.useaxentix.com/docs/getting-started/), [couleurs](https://neu.useaxentix.com/docs/colors/), [ombres](https://neu.useaxentix.com/docs/shadows/).

## Fichiers créés

```text
app/Controllers/CatalogController.php
app/Models/AchievementRepository.php
app/Services/CatalogService.php
app/Views/catalog.php
app/Views/detail.php
app/Views/error.php
app/Views/helpers.php
app/Views/layout.php
app/Views/partials/card.php
app/Views/partials/progress.php
app/Views/partials/rewards.php
database/migrations/007_achievement_details.sql
public/.htaccess
public/assets/css/theme.css
public/assets/images/emblem.svg
public/assets/js/app.js
public/assets/js/catalog-core.mjs
public/assets/vendor/neu-axentix/neu-axentix.min.css
public/assets/vendor/neu-axentix/LICENSE
tests/catalog.php
tests/catalog.test.mjs
tests/compose.yaml
tests/fixtures.json
tests/http.php
tests/seed.php
docs/phase1.md
docs/phase2.md
```

## Fichiers modifiés

- `public/index.php` : routage vers catalogue, fiche, diagnostic et 404 ; en-têtes HTTP.
- `docker/apache/000-default.conf` : fallback vers le contrôleur frontal et MIME des modules `.mjs`.
- `tests/database.php` : nombre attendu de migrations déterminé depuis les fichiers, désormais sept.
- `README.md` : documentation actuelle, commandes de test et préparation du déploiement. L’ancien contenu reste archivé dans `docs/phase1.md`.

Le Dockerfile, le Compose principal, la connexion PDO, la configuration des secrets et les migrations 001–006 ne sont pas modifiés.

## Vérifications réellement exécutées

| Vérification | Résultat |
| --- | --- |
| `docker compose config --quiet` | Succès |
| `docker compose up -d --build` | Succès ; Apache et MariaDB opérationnels |
| Migration 007 puis relance du runner | Appliquée une fois, sept migrations ensuite reconnues sans réapplication |
| `tests/database.php` | Non-régression SQL réussie, données de test annulées |
| Syntaxe PHP | Tous les fichiers vérifiés sans erreur |
| `tests/http.php`, réel et isolé | `/`, `/succes`, `/statut`, assets : 200 ; pages inconnues et fichiers privés : 404 ; fiche isolée : 200 ; MIME `.mjs` correct |
| `tests/catalog.php` sur base isolée | 10 contrôles réussis : lecture, statistiques, catégories, titres, conditions, NULL, chemins d’images et échappement |
| `tests/catalog.test.mjs` | 5 groupes réussis, sur Node local puis Node 22 dans Docker |
| Recherche dynamique dans le navigateur | Recherche `ECLAIREUR` trouve le titre `Éclaireur`, sans accents ni casse |
| Filtres combinés dans le navigateur | Secret + Épique + catégorie + XP + Titres : un seul résultat attendu |
| Tri dans le navigateur | XP décroissante : 2500, 500, 0, inconnu ; les six tris sont aussi couverts par les tests JS |
| Recherche inexistante et réinitialisation | État sans résultat puis retour aux quatre fixtures |
| localStorage réel | Marquage, rechargement : état conservé, 1/4 et 25 % ; filtre Obtenus : un résultat ; retrait au clavier : 0 % |
| Fiche détaillée | Conditions, type, rareté, récompenses, titre, statut, date et retour au catalogue vérifiés |
| Responsive | 320, 390, 768 et 1440 px : aucun débordement horizontal ; une colonne mobile, deux colonnes tablette/bureau ; filtres repliés au chargement mobile |
| Contrastes calculés | Texte principal 15,71:1 ; secondaire 8,56:1 ; badges de récompenses entre 6,63:1 et 10,25:1 ; placeholder 7,87:1 |
| Clavier | Ouverture du panneau par Entrée ; Tab vers le filtre, contour visible de 3 px ; bouton obtenu activé par Espace |
| Console JavaScript | Aucune erreur ni alerte relevée sur les parcours vérifiés |
| Logs Apache/PHP consultés | Aucune erreur PHP relevée pendant les parcours |
| Données réelles | `SELECT COUNT(*) FROM achievements` = 0 après les tests |

Les vérifications navigateur ont révélé une balise `option` incorrecte dans le filtre Secret ; correction et nouveau passage des filtres réussis. Un test HTTP couvre désormais ce cas. Le focus est conservé après réorganisation des cartes. L’environnement isolé est supprimé en fin de validation, tandis que le site réel reste démarré sur le port 8080.

## Limites explicites

- Catalogue réel vide : quatre fixtures uniquement dans la base Docker éphémère sur 8081, jamais dans la base réelle. Aucun PDF ni import des 33 succès.
- Le navigateur reçoit le catalogue entier : adapté au volume prévu, pagination et recherche serveur à prévoir si celui-ci devient très volumineux.
- Pas d’authentification, d’administration, de téléversement ni de synchronisation de progression. Le bouton Administration indique clairement que la connexion est à venir.
- Images justificatives locales prises en charge ; aucune capture de production disponible. Pas de test visuel avec une vraie capture attachée à un succès.
- Tests visuels dans le navigateur intégré sur macOS avec tailles simulées ; pas de validation sur appareils physiques, autres moteurs ou lecteur d’écran. `prefers-reduced-motion` est prévu dans le CSS, sans modification de la préférence système pendant les tests.
- Aucun audit WCAG formel ni déploiement sur un hébergeur gratuit. Les règles de contraste et de focus sont prévues, sans revendication de certification.
- Les contrôles interactifs nécessitent JavaScript ; le catalogue et les fiches restent rendus côté PHP.
