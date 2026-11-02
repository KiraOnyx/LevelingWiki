# Cohérence UX/UI — validation du 30 octobre 2026

Les composants partagés ont été harmonisés sans changer l’architecture PHP/JavaScript, Axentix Neu, les données réelles ou les permissions. Aucun ajout de dépendance, migration, branche, commit, push ou merge.

## 1. Problèmes identifiés

La marge supérieure de 20 px portée par tous les boutons secondaires décalait Annuler par rapport à Enregistrer. Plusieurs en-têtes juxtaposaient les liens sans barre d’actions. Les formulaires d’ajout de catégories et titres occupaient une carte large même fermés. Les actions des divisions et certaines ouvertures de formulaires ressemblaient à du texte. La suppression d’un palier semblait attachée à ses récompenses. Le décor Yggdrasil était positionné dans le contenu et défilait avec lui. Les paliers et types conservaient des filtres à soumission manuelle. Enfin, une sauvegarde de division sans master_ids remplaçait les associations par une liste vide.

## 2. Composants harmonisés

- Boutons principaux dorés, secondaires sombres, modifications secondaires et actions destructives rouge discret.
- Hauteur minimale de 44 px, hauteur libre lorsque le texte passe sur plusieurs lignes, largeur bornée, états hover/focus/active/disabled.
- Barres admin-actions, card-actions et discord-role-actions avec espacement de 12 px, retour à la ligne et alignement commun.
- Annuler intégré au même groupe que la sauvegarde dans les formulaires repliables. Échap et retour du focus conservés.
- Ajouts de catégories/titres compacts à l’état fermé ; panneau de formulaire uniquement à l’ouverture.
- Retours alignés sur les éditeurs ; suppression de palier dans une section séparée et explicitement décrite.
- Contrôles À débloquer / Obtenus et Cartes / Liste normalisés, sans modification de leur état actif ni de leur logique.

Les espacements sont portés par les conteneurs d’actions, pas par une marge intrinsèque des boutons. Les liens ordinaires restent des liens.

## 3. Pages corrigées

Divisions (liste, création, modification et historique), paliers, récompenses, types de récompenses, catégories, titres, liste et formulaire des succès, paramètres Discord, accès utilisateurs et contrôles du Codex. Les composants partagés bénéficient aussi aux autres formulaires qui les utilisent.

## 4. Fond fixe

L’image existante yggdrasil-background.webp est désormais portée par un pseudo-élément fixe du body des pages membres concernées. Son centrage, le mode contain sans répétition, la taille maximale et les opacités desktop/mobile existantes sont conservés. Le décalage initial tient compte de la hauteur mesurée du header. Les autres illustrations et dégradés ne sont pas remplacés.

Le contexte d’empilement du body isole cette couche placée derrière le contenu ; pointer-events:none empêche toute interception des interactions. Aucune animation et aucune dépendance à background-attachment:fixed. Les mesures avant/après défilement dans Chromium confirment position et dimensions identiques. Le contrôle dans WebKit local n’a pas abouti : le processus est resté bloqué au démarrage puis a été arrêté. Safari/iOS et la PWA installée restent à vérifier sur appareil réel.

## 5. Recherche automatique

Les listes déjà chargées (succès administratifs, catégories, titres, comptes) filtrent localement après 200 ms : casse et accents neutralisés, compteur actualisé, message explicite en cas de résultat vide, filtres combinés et Réinitialiser conservés. Le Codex garde son mécanisme existant à 250 ms, déjà automatique et insensible aux accents.

Les paliers/types demandent une nouvelle liste au serveur après 200 ms. Seuls les résultats autorisés rendus par la route existante et le compteur sont remplacés. Le formulaire, la saisie et le focus restent en place ; le document n’est pas rechargé. AbortController annule la requête précédente et un numéro de révision ignore toute réponse obsolète. Une redirection de connexion n’est jamais injectée comme liste. Les erreurs donnent un message explicite. Sans JavaScript, le formulaire GET et son bouton Filtrer restent utilisables.

Les validations et confirmations des formulaires d’archivage/suppression sont réinstallées après remplacement des résultats, sans accumuler des écouteurs globaux conservant les anciens formulaires. Aucun jeu de données administratif supplémentaire n’est exposé au navigateur.

## 6–7. Guild Masters : retrait de la sélection et conservation

Les cases, textes d’aide et requêtes de chargement des candidats ont été retirés des formulaires de création/modification.

Lors d’une sauvegarde sans master_ids, le service conserve les associations lues sous le verrou de la division. Il ne supprime ni ne recrée leurs lignes : identifiants, auteur d’attribution et dates restent identiques. Cela vaut aussi pour une association à un compte devenu inactif. Une création sans champ d’attribution ne nomme aucun responsable automatiquement. Les contrôles d’accès des Guild Masters demeurent actifs.

Conséquence pour la gestion future : cette interface ne permet plus d’attribuer ou de retirer un Guild Master. Les associations existantes continuent de fonctionner. Le traitement serveur explicite master_ids reste soumis aux autorisations et validations administratives existantes ; aucun nouvel endpoint ni outil d’attribution n’a été créé. Toute future interface d’attribution devra faire l’objet d’une demande distincte.

## 8. Actions des divisions

Niveau et historique et Modifier sont deux boutons secondaires distincts, avec un espacement commun. Ils utilisent la même page existante, avec une ancre vers l’historique ou l’éditeur. Modifier est réservé aux administrateurs autorisés ; les éditeurs assignés conservent seulement Niveau et historique. Les autres éditeurs restent en consultation et MEMBER n’accède pas à l’administration. Les contrôles serveur sont inchangés.

## 9–10. Tests PHP et JavaScript

- PHP : **833 assertions réussies dans 27 scripts**, via `sh tests/run-admin.sh`. Le compte exclut deux simples messages de fin de suite parmi les lignes OK. Les 21 nouvelles assertions couvrent notamment création sans attribution, conservation exacte des associations actives/inactives, permissions MEMBER/EDITOR/ADMIN/SUPER_ADMIN, progression du Guild Master, CSRF, boutons et recherche combinée insensible aux accents. Les tests existants des paliers, récompenses, sessions et autres fonctionnalités passent.
- JavaScript : **41 tests réussis, zéro échec, zéro ignoré**, via `node --test tests/*.test.mjs`. Six nouveaux tests couvrent recherche sans Entrée, debounce, accents/casse, filtres combinés, état vide, réinitialisation, réponse obsolète et expiration de session.
- `git diff --check` : réussi.

Toutes les écritures de test visent exclusivement le projet Docker levelingwiki-tests, APP_ENV=test et la base levelingwiki_test. Discord est simulé ; aucune association Discord ou donnée réelle n’a été modifiée.

## 11. Responsive et accessibilité

Chromium local, largeurs **320, 375, 390, 430, 768, 1024, 1200, 1440 et 1920 px**. Treize pages et quatre états supplémentaires donnent **153 contrôles de mise en page**, tous réussis : aucun débordement horizontal, aucun bouton tronqué parmi les contrôles mesurés, aucune zone d’action mesurée inférieure à 44 px. Aucune erreur JavaScript de page observée.

Parcours réels supplémentaires : recherche pendant la saisie sans Entrée, recherche + statut, réinitialisation, état vide, conservation du document et de la saisie, confirmation d’archivage après remplacement des résultats, ouverture au clavier, annulation et fond pendant le défilement. Inspection visuelle des captures desktop/mobile effectuée en complément.

Contrastes calculés sur les couleurs des boutons : principal au point le moins contrasté du dégradé 7,44:1 ; secondaire 10,86:1 ; destructif 9,03:1 ; état sélectionné 8,61:1. Focus visible et labels existants conservés. Ces mesures ne constituent pas un audit WCAG exhaustif de toutes les pages.

## 12. Captures et résultats

- [Divisions desktop](/Users/nicolaspaiva/.codex/visualizations/2026/10/29/01a189d3-ba24-7512-815d-53256e20eae5/coherence/divisions-1440.png)
- [Édition division mobile](/Users/nicolaspaiva/.codex/visualizations/2026/10/29/01a189d3-ba24-7512-815d-53256e20eae5/coherence/division-390.png)
- [Catégories compactes desktop](/Users/nicolaspaiva/.codex/visualizations/2026/10/29/01a189d3-ba24-7512-815d-53256e20eae5/coherence/categories-fermees-1440.png)
- [Modification catégorie mobile](/Users/nicolaspaiva/.codex/visualizations/2026/10/29/01a189d3-ba24-7512-815d-53256e20eae5/coherence/categorie-modifier-390.png)
- [Palier et suppression séparée](/Users/nicolaspaiva/.codex/visualizations/2026/10/29/01a189d3-ba24-7512-815d-53256e20eae5/coherence/palier-suppression-1440.png)
- [Formulaire titre mobile](/Users/nicolaspaiva/.codex/visualizations/2026/10/29/01a189d3-ba24-7512-815d-53256e20eae5/coherence/titres-390.png)
- [Paramètres Discord desktop](/Users/nicolaspaiva/.codex/visualizations/2026/10/29/01a189d3-ba24-7512-815d-53256e20eae5/coherence/discord-1440.png)
- [Contrôles du Codex desktop](/Users/nicolaspaiva/.codex/visualizations/2026/10/29/01a189d3-ba24-7512-815d-53256e20eae5/coherence/codex-1440.png)
- [Fond pendant le scroll](/Users/nicolaspaiva/.codex/visualizations/2026/10/29/01a189d3-ba24-7512-815d-53256e20eae5/coherence/fond-scroll-1440.png)
- [Mesures responsive](/Users/nicolaspaiva/.codex/visualizations/2026/10/29/01a189d3-ba24-7512-815d-53256e20eae5/coherence/responsive.json)

Les captures ne contiennent que des comptes et contenus fictifs. Elles sont enregistrées localement dans le dossier de visualisations, hors du dépôt.

## 13. Fichiers modifiés

- [app/Controllers/GuildTowerController.php](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/app/Controllers/GuildTowerController.php)
- [app/Services/GuildDivisionService.php](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/app/Services/GuildDivisionService.php)
- [app/Views/admin/achievement-form.php](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/app/Views/admin/achievement-form.php)
- [app/Views/admin/achievements.php](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/app/Views/admin/achievements.php)
- [app/Views/admin/discord.php](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/app/Views/admin/discord.php)
- [app/Views/admin/guild-catalog.php](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/app/Views/admin/guild-catalog.php)
- [app/Views/admin/guild-divisions.php](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/app/Views/admin/guild-divisions.php)
- [app/Views/admin/layout.php](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/app/Views/admin/layout.php)
- [app/Views/admin/taxonomy.php](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/app/Views/admin/taxonomy.php)
- [app/Views/admin/users.php](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/app/Views/admin/users.php)
- [app/Views/layout.php](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/app/Views/layout.php)
- [public/assets/css/admin-forms.css](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/public/assets/css/admin-forms.css)
- [public/assets/css/admin.css](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/public/assets/css/admin.css)
- [public/assets/css/valhalla.css](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/public/assets/css/valhalla.css)
- [public/assets/js/admin-forms.js](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/public/assets/js/admin-forms.js)
- [public/assets/js/guild-catalog.js](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/public/assets/js/guild-catalog.js)
- [public/assets/js/interface.js](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/public/assets/js/interface.js)
- [tests/phase-d.test.mjs](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/tests/phase-d.test.mjs)
- [tests/run-admin.sh](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/tests/run-admin.sh)
- [tests/ui-coherence.php](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/tests/ui-coherence.php)
- [tests/ui-filters.test.mjs](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/tests/ui-filters.test.mjs)
- [docs/ux-coherence-validation.md](/Users/nicolaspaiva/Documents/GitHub/LevelingWiki/docs/ux-coherence-validation.md)

## 14. Limites restantes

Aucun échec fonctionnel identifié dans les suites et parcours exécutés. La vérification sur Safari/iOS et dans une PWA réellement installée reste manuelle : le moteur WebKit disponible localement n’a pas démarré. Aucun changement de données réelles n’est nécessaire pour cette vérification. Les deux blocages temporaires d’approbation par limite d’usage ont été résolus à la reprise ; ils n’ont entraîné aucune modification partielle de données réelles.
