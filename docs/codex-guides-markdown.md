# Codex : ergonomie et guides Markdown

## État de livraison

Code implémenté et testé dans l'environnement Docker jetable. **Migration 017 appliquée et vérifiée uniquement dans `levelingwiki-tests`**, pas dans la base locale existante ni en production. Le code doit être déployé avec cette migration avant utilisation des nouveaux écrans. Aucune donnée réelle créée/modifiée, aucun guide fictif importé. Aucune branche, aucun commit/push/merge. Phase D inchangée.

## Audit et causes

Le catalogue est chargé par `CatalogController` / `AchievementRepository`, le suivi par `PersonalService`, les écritures par `AdminController` / `AchievementService`. Les champs `description` et `achievement_details.unlock_conditions` existaient déjà ; aucun stockage de guide ou dépendance Markdown n'existait. Le guide reste distinct des conditions, et n'est pas inclus dans le JSON global du catalogue.

Les cartes cumulaient description, conditions, état personnel et vérification éditoriale. Le badge SECRET prenait une colonne qui rétrécissait le titre. Le thème imposait aux boutons de suivi une pleine largeur et ajoutait une bordure/padding au formulaire. Des marges automatiques sur les récompenses créaient des zones vides. La fiche exposait surtout les mêmes informations, plus la vérification et la date technique.

Les captures justificatives utilisent déjà un stockage privé et une route contrôlée. Ce stockage n'a pas été rendu public : les guides utilisent un sous-répertoire et une table d'association distincts. Les vues, CSS, scripts, permissions, migrations et suites existantes ont été examinés. Les deux captures mentionnées dans le cahier des charges n'étaient pas attachées à ce nouveau message ; l'audit visuel s'est appuyé sur les rendus de l'application.

## Interface membre

- Ligne supérieure : icône, catégorie discrète, SECRET à droite ; titre sur toute la largeur suivante, y compris avec des textes longs.
- Condition prioritaire (`unlock_conditions`, repli sur la description existante si vide), plus lisible ; aucun guide long utilisé comme extrait de carte.
- Suppression du statut éditorial des cartes/fiches. Récompenses connues conservées et mention discrète seulement si incomplètes. État obtenu/date conservés, déclaration/annulation inchangées.
- Actions regroupées : Voir le guide et bouton de suivi adapté au texte sur desktop, tactile sur mobile.
- Mode liste réorganisé avec condition visible, récompenses et actions ; onglets, compteurs, URL, filtres, tris conservés.
- Fiche : condition sous le titre ; guide principal, suivi compact ; récompenses/métadonnées utiles en colonne secondaire sur desktop. Empilement mobile. Aucune section de guide lorsqu'il est vide.
- Les preuves justificatives restent gérées dans l'administration ; elles ne sont pas automatiquement utilisées comme illustrations du guide.

## Modèle et migration

`017_achievement_guides.php` ajoute `achievement_details.guide_markdown` (MEDIUMTEXT nullable) et `achievement_guide_images` : identifiant persistant, FK vers succès, chemin unique, texte alternatif, date et index du succès. Aucun import de contenu. Migration additive inspectant la colonne et créant la table si nécessaire, compatible avec une reprise après DDL partiel.

Le Markdown est limité à 100 000 caractères à l'écriture. Les mises à jour anciennes qui omettent le champ conservent le guide. Le formulaire transmet une empreinte attendue ; un conflit de guide est rejeté sous verrou transactionnel, sans remplacer le texte saisi dans le formulaire d'erreur. Cela n'introduit pas un nouveau mécanisme de version pour tous les autres champs des succès.

Audit `achievement.guide` : empreinte SHA-256 et taille avant/après, jamais le Markdown intégral. Audit d'ajout d'image avec sa référence. Les autres audits restent conservés.

## Administration et images

L'éditeur est intégré au formulaire, sur toute sa largeur utile : titres, gras, italique, listes, lien, citation, texte Markdown, aperçu explicite et messages accessibles. Il conserve le contenu pendant l'édition et après erreur serveur. L'aperçu provient exactement du même moteur serveur que la fiche. Il n'est pas rafraîchi en boucle : cliquer sur Actualiser l'aperçu ; l'ajout d'image le rafraîchit automatiquement.

Images : enregistrer d'abord un nouveau succès, ouvrir Ajouter une image / Images associées, décrire l'image, choisir JPEG/PNG/WebP, envoyer. La référence est insérée à la sélection courante ; les images précédentes peuvent être réinsérées depuis la liste associée. Sauvegarder ensuite le succès pour publier le texte modifié.

Stockage : `UPLOAD_DIRECTORY/guides`, hors `public`. Nom aléatoire de 64 caractères hexadécimaux. Réutilisation de la validation UploadService : vrai upload PHP, extension, MIME finfo, en-tête image, taille et dimensions. SVG refusés. Aucune exécution possible via le serveur web.

Limites par défaut : 5 Mio/image, 20 mégapixels, 30 images par succès. Variables : `GUIDE_IMAGE_MAX_BYTES`, `GUIDE_IMAGE_MAX_PIXELS`, `GUIDE_IMAGE_MAX_COUNT`. Les plafonds PHP `upload_max_filesize`/`post_max_size` restent applicables. Texte alternatif obligatoire à l'upload et repli accessible au rendu si la syntaxe Markdown en omet un.

Pas de suppression automatique : enlever une référence du guide conserve le fichier et son association, pour pouvoir le réutiliser. Une image envoyée puis abandonnée reste associée et compte dans la limite. Pas de médiathèque globale ni de purge automatique ; une suppression physique de succès peut laisser un fichier orphelin dans le stockage (à traiter par maintenance contrôlée, jamais en supprimant les fichiers encore utilisés).

## Routes et sécurité

- POST `/admin/achievements/guide-preview` : aperçu JSON.
- POST `/admin/achievements/guide-image` : upload JSON.
- GET `/guide-media/{nom-aléatoire}.{jpg|png|webp}` : lecture contrôlée.
- Sauvegarde du guide : route existante `/admin/achievements/save`.

Écriture/aperçu : Auth existant avec revalidation, droits EDITOR/ADMIN/SUPER_ADMIN, CSRF ; MEMBER refusé côté serveur. Lecture d'image : membre authentifié, succès publié/non archivé, ou éditeur autorisé pour son travail éditorial. Une URL non associée ne suffit pas à accéder au fichier. Chemins stricts, résolution realpath, liens symboliques et traversées refusés ; `nosniff`, CSP restrictive sur images et cache no-store.

Parsedown **1.8.0**, licence MIT, fichier PHP local de 52 070 octets, sans bibliothèque front-end. Sources et empreinte dans `app/Vendor/Parsedown/README.md`. Mode sûr et échappement du HTML activés. Liens autorisés uniquement HTTP(S), sans identifiants ni caractères de contrôle, attributs échappés, rel `noopener noreferrer nofollow`. Images autorisées uniquement dans la liste d'associations du succès ; images externes, data URI et références à d'autres succès neutralisées. Aucun chargement réseau serveur depuis le Markdown. Les titres du guide commencent au minimum à h3 après le h2 de section.

## Tests et validation

- `sh tests/run-admin.sh` : **652 assertions OK**, sortie 0, Docker jetable arrêté à la fin.
- `node --test tests/*.test.mjs` : **25 tests réussis**, zéro échec.
- `git diff --check` : sans erreur.
- PHP : migration réexécutable, guides vides/remplis, persistance, conservation après édition, conflit, EDITOR/ADMIN par HTTP, rôles dont SUPER_ADMIN via PermissionService existant, refus MEMBER/CSRF, rendu/aperçu, HTML hostile et URL dangereuses, image valide, faux PNG/SVG, taille/dimensions excessives, relation forgée, lecture anonyme/non associée/privée, audit compact, suivi et récompenses. Les suites antérieures restent exécutées.
- JS : outils Markdown et sélection, insertion d'image, rendu d'aperçu reçu, erreur réseau conservant la rédaction, validation avant upload ; suites antérieures de suivi, catalogue et Tour.
- Mesures responsive finales : cartes, obtenus, liste, fiche avec guide/image, fiche sans guide, éditeur, cartes aux catégories longues, aux **320/375/390/430/768/1024/1200/1440/1920 px**. 63 états, aucun débordement de page/contrôle ni chevauchement catégorie/SECRET détecté.
- Inspection graphique réelle desktop/mobile : hiérarchie des cartes, titres longs, badge SECRET, récompenses multiples/inconnues, état obtenu/date, guide illustré, fiche vide, éditeur et panneau d'images. Corrections itératives des colonnes, marges héritées, double bordure et largeur de l'éditeur.

La revue automatique du navigateur a refusé une navigation vers Discord.com susceptible d'ouvrir une connexion réelle. L'inspection utilise donc les réponses des vrais contrôleurs obtenues par les tests HTTP et servies comme rendus locaux **inertes** ; aucun compte Discord réel n'a été utilisé. Les écritures/upload/permissions sont testés en HTTP sur le mock Docker, pas via ces pages statiques.

Limites : pas de Safari iOS physique, clavier virtuel réel ou installation PWA testés. Certaines captures desktop produites par l'outil sont moins nettes que les rendus mobiles. Pas de sauvegarde automatique du brouillon après rechargement : enregistrer le succès. Les erreurs de conflit doivent être résolues en rechargeant et en recopiant le texte conservé, pas en écrasant silencieusement.

## Procédure de mise en service et test manuel

1. Avant déploiement : sauvegarder la base, vérifier `schema_migrations` et les empreintes des migrations existantes ; déployer les fichiers et lancer le mécanisme habituel `php bin/migrate.php` dans l'environnement visé. Vérifier l'entrée 017. Ne pas lancer les seeds/tests contre cette base.
2. Se connecter avec un rôle EDITOR ou administrateur et ouvrir Administration → Succès → Modifier.
3. Renseigner les conditions courtes, rédiger le guide et demander l'aperçu. Enregistrer, recharger et vérifier le contenu.
4. Ajouter une image avec alternative ; vérifier sa présence dans l'aperçu et la liste associée ; enregistrer à nouveau.
5. Ouvrir la fiche publiée avec un MEMBER. Vérifier guide, image, récompenses et suivi ; un MEMBER ne doit pas pouvoir ouvrir les routes d'édition.
6. Vider le guide et enregistrer : sa section disparaît, le reste de la fiche continue de fonctionner.
7. Ouvrir le même guide dans deux formulaires : après enregistrement du premier, l'ancien doit refuser d'écraser le guide récent.
8. Vérifier un succès brouillon/archivé : sa fiche/image ne doit pas être accessible au membre.

## Fichiers

Créés :
- `database/migrations/017_achievement_guides.php`
- `app/Controllers/GuideController.php`
- `app/Services/GuideMarkdown.php`, `GuideImageService.php`
- `app/Views/admin/guide-editor.php`
- `app/Vendor/Parsedown/Parsedown.php`, `LICENSE.txt`, `README.md`
- `public/assets/css/codex-guides.css`, `public/assets/js/guide-editor.js`
- `tests/guides.php`, `tests/guide-editor.test.mjs`, `tests/render-guide-visual.php`
- Ce document.

Modifiés :
- `app/Controllers/AdminController.php`, `CatalogController.php`
- `app/Models/AdminRepository.php`
- `app/Services/AchievementService.php`, `UploadService.php`, `Audit.php`
- `app/Views/admin/achievement-form.php`, `app/Views/admin/layout.php`
- `app/Views/detail.php`, `app/Views/partials/card.php`, `app/Views/layout.php`
- `public/index.php`, `public/assets/js/interface.js`
- `tests/http-client.php`, `tests/run-admin.sh`.

Les autres changements préexistants, notamment `.env.example` et le dashboard administratif, sont conservés.
