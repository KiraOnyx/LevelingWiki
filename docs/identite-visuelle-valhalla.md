# Illustrations Valhalla — 23 octobre 2026

## Audit et périmètre

L’accueil utilise `app/Views/member/home.php`, avec une bannière `.nordic-hero`. Les pages membres partagent `app/Views/layout.php`, les composants existants et Axentix Neu 2.0.0 local. `theme.css` définit la structure, `valhalla.css` son habillage. Les adaptations existantes à 820 et 650 px sont conservées.

Le contrôleur catalogue pose déjà le statut 404 et rend `app/Views/error.php` ; cette vue sert aussi aux erreurs 503. La nouvelle branche visuelle dépend uniquement du statut 404. Les routes, contrôleurs, authentifications, permissions, données et comportements sont inchangés. Aucun changement de migration, aucun commit.

Les captures métier sous `/assets/images/` sont interceptées par Apache pour contrôle d’accès. Les illustrations décoratives publiques sont donc rangées dans `/assets/illustrations/valhalla/`, sans modifier cette protection ni Apache.

## Ressources

Conversion locale avec `cwebp -q 85`, dimensions originales conservées, sans génération ni retouche des illustrations :

| Source fournie | Ressource publique | Dimensions | Poids arrondi |
| --- | --- | --- | --- |
| Page404.png | `/assets/illustrations/valhalla/ginnungagap-404.webp` | 1983 × 793 | 239 Kio |
| LesPortesDuValhalla.png | `/assets/illustrations/valhalla/valhalla-gates.webp` | 1408 × 768 | 72 Kio |
| Fond_Site.png | `/assets/illustrations/valhalla/yggdrasil-background.webp` | 1588 × 1191 | 227 Kio |

Total : environ 538 Kio, contre 5,3 Mio pour les PNG. Les originaux restent intacts dans le dossier fourni par l’utilisateur.

## Intégration

- **404** : fond couvrant la surface du viewport, proportions conservées, dégradé inférieur, texte demandé et lien `/`. La navigation et le pied de page sont masqués uniquement dans cette vue immersive. En portrait, cadrage à 30 % pour préserver le personnage ; en paysage, cadrage central conservant aussi le portail lorsque le ratio le permet. Aucune redirection automatique.
- **Accueil** : image des portes à droite, dégradé horizontal opaque à gauche puis progressif ; renforcement sur petits écrans. Aucun changement du HTML de l’accueil, des textes, boutons, statistiques ou dimensions générales.
- **Yggdrasil** : pseudo-élément derrière le contenu, sans interaction, limité à Accueil, Codex, Mes succès et Profil. Un seul motif, à 6 % d’opacité et 4 % sur mobile. Aucun motif sur les fiches, erreurs, connexion ou administration. L’opacité n’affecte jamais les composants.
- Les fonds sont décoratifs et ne créent aucun contenu pour les lecteurs d’écran. Les couleurs de repli et dégradés restent définis même si une image manque. Aucune dépendance ni modification JavaScript. Le CSS est versionné `v=6.2` dans le template membre.

## Fichiers de cette intervention

Modifiés :

- `app/Views/layout.php` : classes visuelles limitées aux vues concernées et version CSS.
- `app/Views/error.php` : présentation 404 spécifique, autres erreurs conservées.
- `public/assets/css/valhalla.css` : illustrations, cadrages et calques décoratifs.
- `tests/run-admin.sh` : ajout du test d’intégration graphique.

Créés :

- Les trois fichiers WebP cités ci-dessus.
- `tests/illustrations.php`.
- `docs/identite-visuelle-valhalla.md`.

## Vérifications exécutées

```sh
sh tests/run-admin.sh
node --test tests/catalog.test.mjs tests/interface.test.mjs
docker compose config --quiet
docker compose exec -T web php -l app/Views/layout.php
docker compose exec -T web php -l app/Views/error.php
docker compose exec -T web php tests/http.php
git diff --check
```

Résultats : suite Docker complète réussie (code 0), huit tests JavaScript réussis, syntaxe PHP valide et aucune erreur de diff. Le test supplémentaire vérifie : HTTP 404 réel et textes exacts ; retour `/` ; ressources 200 avec MIME WebP et signature correcte ; classes décoratives présentes sur les quatre pages et absentes sur connexion/administration/404 ; bannière et progression conservées ; refus CSRF toujours 403 ; vue 503 conservée (rendu isolé, sans provoquer une panne réelle).

Vérification navigateur sur compte et quatre succès jetables uniquement : accueil desktop 1440 × 900, tablette 820 × 1180 et mobile 390 × 844 ; 404 aux mêmes formats ; absence de débordement horizontal ; images visibles ; liens des deux boutons de bannière fonctionnels ; retour 404 vers l’accueil (puis connexion normale pour un anonyme) ; focus clavier visible sur le retour ; opacité du motif contrôlée sur les quatre pages ; aucune erreur console observée. L’environnement Docker jetable est arrêté après vérification. Aucune écriture dans la base réelle.

## Limites et validation finale

Contrôles visuels réalisés dans le navigateur intégré, pas dans quatre moteurs distincts. Une vérification complémentaire Safari, Firefox et Edge reste à faire avant validation multi-navigateurs. Les fonds utilisent des propriétés CSS standard et WebP ; aucune API spécifique à un moteur.

Le repli sans image est prévu dans le CSS, mais une panne réseau d’image n’a pas été simulée dans le navigateur. La vérification de contraste est visuelle pour ces nouveaux dégradés, sans certification exhaustive WCAG.

À valider : luminosité des portes sur votre écran, discrétion de Yggdrasil, cadrage portrait privilégiant le personnage (le portail sort naturellement du champ sur les écrans étroits). Les captures de livraison montrent exclusivement des données de test.

Le site principal reste consultable sur `http://localhost:8080`. Les sources étant montées dans Docker, recharger la page suffit. Aucun déploiement, commit ou merge n’a été effectué.
