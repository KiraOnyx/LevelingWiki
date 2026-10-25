# Tour de guilde — itération 2, phase A

25 octobre 2026. **Phase graphique uniquement. Phase B non commencée ; validation visuelle explicite requise.**

## Accès et comparaison

- Original 2D : http://localhost:8080/prototype/tour-de-guilde
- Variante 2,5D : http://localhost:8080/prototype/tour-de-guilde?view=relief
- Comparer au même point : http://localhost:8080/prototype/tour-de-guilde?view=relief#division-v1
- Tester sans animation : ajouter `&motion=reduce` à la variante.
- « Explorer la tour » contient les sélecteurs **Rendu architectural** et **Ambiance**. Le changement est immédiat, sans rechargement, sans nouvelle descente et sans perte des scénarios de test. L’URL est actualisée pour permettre son partage ; Retour conserve la restauration native et rétablit le rendu indiqué par l’URL.
- La 2D reste le rendu par défaut. Ses définitions SVG et sa feuille CSS sont inchangées par cette itération. Son décor d’origine reste affiché, quelle que soit la sélection du décor expérimental.
- En 2,5D, l’ambiance propose Automatique, Complète statique, Légère et Sans décor. Le mode léger est choisi automatiquement sur petit écran, avec économiseur de données ou indices matériels modestes (mémoire annoncée ≤ 4 Go ou ≤ 4 processeurs logiques). Ces indices sont facultatifs, approximatifs et absents sur certains navigateurs : ils ne permettent pas de mesurer la puissance réelle.
- La réduction des mouvements et l’économiseur de données gardent la priorité sur le mode complet. Aucune brume n’est animée, même en mode complet.

Les données restent exclusivement celles des fixtures du prototype. Aucune récupération, aucun nouveau compte, aucune migration et aucune connexion DB ajoutée.

## Audit ciblé et décisions

L’architecture initiale est dans `app/Views/tower/architecture.php` et utilise des symboles SVG réutilisés. `prototype.php` produit les paliers et l’interface HTML, `tower.js` gère navigation/scénarios et `tower-geometry.mjs` les calculs d’interpolation et de collisions. Le contrôleur est limité aux environnements development/test et à GET. Les styles sont conditionnels au prototype dans le layout partagé.

Cette séparation permet d’ajouter un jeu de symboles **sans transformer l’interface HTML**, sans changer les hauteurs des paliers, sans modifier les coordonnées des marqueurs et sans toucher au code d’authentification.

Le nouveau fichier `architecture-relief.php` porte les définitions indépendantes. Le contrôleur n’accepte que `view=relief` ; toute autre valeur conserve la 2D. Les attributs `data-architecture` permettent de changer les références SVG des étages, du sommet et de la base. Les modules ajoutés par le scénario 135 reçoivent aussi le bon rendu.

### Variante 2,5D livrée

- Faces frontales et latérales distinctes ; côté droit dans l’ombre.
- Pierres irrégulières biseautées, joints creusés, variations de teinte et petites fractures.
- Colonnes segmentées en relief et contreforts projetés.
- Fenêtres avec archivoltes, embrasures sombres, vitrages en retrait et appuis saillants.
- Corniches avec face supérieure éclairée, tranche et ombre portée.
- Créneaux à plusieurs faces et marches de la base en perspective.
- Cristaux facettés, halo statique local et lumière froide bleu/violet.
- Silhouettes montagneuses originales sur trois plans ; deux couches de brume au maximum.

La perspective est dessinée dans les SVG : il n’y a ni caméra, ni profondeur physique, ni éclairage calculé en temps réel. La vue reste volontairement frontale pour préserver l’interpolation verticale.

Le décor est un cadre sticky borné au viewport (maximum 1100 px de haut), contenu dans l’espace de la tour ; pas une texture géante de toute sa hauteur. Il se trouve derrière les cartes et les marqueurs, n’intercepte aucun clic et est masqué aux technologies d’assistance. Les ombres des modules sont dessinées plutôt que produites par de grands filtres de flou. Les filtres drop-shadow des étages 2D sont désactivés dans la variante.

## Comparaison et étude de la vraie 3D

| Approche | Avantages | Limites / coût | Décision phase A |
| --- | --- | --- | --- |
| 2D originale | Très simple, silhouette régulière, données et HTML séparés | Relief peu prononcé ; éclairage fixe | Conservée et accessible par défaut |
| 2,5D SVG | Profondeur dessinée, comparateur immédiat, aucune bibliothèque, interactions HTML natives | Pas de rotation ni de vrais volumes ; motifs répétés ; lumière fixe | Variante réalisée, à valider visuellement |
| WebGL hybride | Vrais volumes, perspective et éclairage variables, matériaux possibles | Modélisation, shaders, buffers, gestion du contexte GPU, budget mémoire et tests iOS supplémentaires | Étudiée, **non réalisée et non retenue automatiquement** |

La 2,5D apporte une profondeur visible sans changer la navigation. Une vraie 3D n’est donc pas nécessaire pour soumettre cette expérimentation. Si le relief reste insuffisant après votre examen, un essai séparé devra définir un cadrage et un gain visuel précis.

Architecture envisageable pour cet essai : un seul canvas décoratif limité au viewport, projection orthographique, modules architecturaux instanciés et visibles uniquement autour du viewport, éclairage simple sans ombres dynamiques, rendu à la demande, ratio de pixels plafonné, interface HTML indépendante et repli 2D lors d’une perte/absence du contexte. Le traitement de scroll nécessaire pour synchroniser la caméra serait limité à une image demandée, sans boucle permanente. Les bulles et récompenses resteraient des éléments HTML accessibles, jamais des textures.

Les recommandations [WebGL de MDN](https://developer.mozilla.org/en-US/docs/Web/API/WebGL_API/WebGL_best_practices) invitent notamment à prévoir un budget mémoire par pixel, à limiter les buffers et à traiter les limites matérielles/contextes. Le poids d’une hypothétique version 3D n’a pas été mesuré : aucun moteur, modèle ou jeu de textures n’a été installé. Aucun chiffre de performance Safari iOS n’est revendiqué.

À titre d’ordre de grandeur, un seul buffer RGBA 390 × 844 à DPR 3 représente environ **11,3 Mio**, avant profondeur, autres buffers, textures et allocations du navigateur. Ce calcul théorique explique l’intérêt d’un ratio plafonné ; ce n’est pas une mesure mémoire de la page actuelle.

## Ressources, sources et licences

### Ressources intégrées

| Ressource | Provenance | Licence / dépendances |
| --- | --- | --- |
| `app/Views/tower/architecture-relief.php` | Dessin SVG original réalisé pour LevelingWiki | Aucune ressource tierce incorporée ; conditions du dépôt, aucune nouvelle licence globale imposée |
| `public/assets/illustrations/tower/mountains.svg` | Dessin vectoriel original, trois silhouettes de profondeur | Aucune ressource tierce incorporée ; mêmes conditions du dépôt |
| Brume, lumière, ombres | Gradients CSS/SVG originaux | Pas de texture externe ni attribution tierce requise |

Aucune image téléchargée, aucune génération bitmap et aucune ressource de licence inconnue intégrée. Aucun appel à un CDN au chargement du prototype.

### Recherche Internet effectuée, candidats non intégrés

- [Medieval Wall 02 — Rob Tuytel, Poly Haven](https://polyhaven.com/a/medieval_wall_02) : mur médiéval usé, jeu de textures photographiques. Candidat si une direction plus réaliste est demandée.
- [Medieval Blocks 03 — Poly Haven](https://polyhaven.com/a/medieval_blocks_03) : grands blocs et joints serrés, autre candidat.
- [Licence officielle Poly Haven](https://polyhaven.com/license) : les assets sont CC0, avec réutilisation et redistribution permises ; ne pas confondre licence des assets et conditions du site.

Ces candidats ne sont pas rejetés pour leur licence. Ils ne sont pas nécessaires à cette expérimentation vectorielle et ajouteraient une direction photographique différente. Pour une sélection manuelle ultérieure : albédo de pierre gris froid peu contrastée, raccordable 512–1024 px, sans perspective/photo d’éclairage marqué, licence explicite de redistribution ; normal map légère uniquement si une vraie 3D est retenue. Documenter alors auteur, URL, licence et transformations du fichier réellement incorporé.

## Poids et limites de performance

Tailles locales mesurées ; gzip calculé hors ligne à titre indicatif, pas une promesse de compression HTTP :

| Nouveau fichier de rendu | Octets bruts | Gzip indicatif |
| --- | ---: | ---: |
| architecture-relief.php | 10 607 | 3 042 |
| tower-relief.css | 3 455 | 1 248 |
| tower-renderer.mjs | 3 281 | 1 226 |
| mountains.svg | 1 090 | 616 |
| **Total** | **18 433** | **6 132** |

Ce total couvre ces quatre fichiers uniquement ; le balisage des sélecteurs et les quelques modifications des fichiers partagés s’ajoutent. Le SVG PHP est incorporé au HTML, pas servi comme un fichier indépendant. Les deux jeux de définitions sont chargés pour permettre le basculement immédiat, même lorsqu’on ouvre la 2D.

Constats navigateur sur le scénario initial : 639 éléments dans le DOM auteur du prototype, 30 références `use` dans le document, zéro animation CSS sur les cinq éléments atmosphériques contrôlés. Ce comptage ne représente pas les instances internes des symboles SVG créées par le moteur de rendu. Le décor observé occupait 795 × 1100 px, pas la hauteur complète de la tour. Pas de boucle de brume, de bibliothèque graphique, de particules ou de canvas ajouté.

Les contrôles suivants ne constituent **pas** un benchmark de FPS, de batterie, de mémoire GPU ou de latence sous charge. Ces mesures et un essai sur appareil iOS physique restent nécessaires avant de conclure à la performance de production. Les signaux matériels ne sont qu’une préférence prudente, complétée par le choix manuel « Légère » / « Sans décor ».

## Tests et résultats

- Suite Node : **19 tests réussis**, dont les 16 précédents et 3 tests du nouveau sélecteur (choix d’effets, priorité des préférences, URL/ancre et modules dynamiques).
- Test PHP du prototype : **12 assertions réussies**, dont les variantes, conservation des douze paliers, paramètre tableau inattendu, refus des mutations et refus hors développement/test. Aucun accès DB.
- Syntaxe PHP : contrôleur, layout, nouvelle architecture et vue valides. Syntaxe JS des modules valide.
- Comparaison réelle dans le navigateur : `scrollY=1875`, hauteur de tour `4199`, coordonnées des trois divisions `2024 / 2216 / 2632` identiques avant/après passage 2,5D → 2D. Retour à 2,5D sans déplacement.
- Scénario huit divisions : contrôles DOM à **320, 390, 768, 1200 et 1440 px**, sans débordement horizontal, sans collision entre marqueurs ni avec les cartes dans le scénario contrôlé. Mode léger à 320/390, complet à partir de 768 sur la machine de test.
- Contrôles visuels : menu mobile de comparaison utilisable à 320 px, architecture et base à 390 px, relief et groupement des divisions sur grand écran.
- Scénario 135 : nouveau module lié à `#tower-floor-relief`, sans ajout de récompense.
- Ouverture sans ancre à 390 px avec `motion=reduce` : palier 1 dans la zone visible, ambiance légère. Ce test force le même chemin logique ; il ne remplace pas un essai du réglage système iOS.

### Fichiers de cette itération

Créés :

- `app/Views/tower/architecture-relief.php`
- `public/assets/css/tower-relief.css`
- `public/assets/js/tower-renderer.mjs`
- `public/assets/illustrations/tower/mountains.svg`
- `tests/tower-renderer.test.mjs`
- `docs/tour-de-guilde-iteration-2.md`

Modifiés :

- `app/Controllers/TowerPrototypeController.php` — choix contrôlé du rendu.
- `app/Views/tower/prototype.php` — sélecteurs, références et décor décoratif.
- `app/Views/layout.php` — chargement CSS uniquement pour le prototype.
- `public/assets/js/tower.js` — installation du sélecteur et prise en compte des modules de scénario.
- `tests/tower-prototype.php` — assertions supplémentaires.

Aucune migration et aucun fichier d’administration modifié. Aucune branche, commit, push ou merge. La modification préexistante de `.env.example` est préservée.

## Validation attendue avant phase B

Valider ou ajuster : profondeur des pierres/colonnes, fenêtres en retrait, corniches, contraste bleu/violet, présence des montagnes et de la brume, lisibilité sur smartphone. Comparer aussi « Sans décor » pour juger l’architecture seule.

Choisir ensuite : conserver la 2D, retenir cette 2,5D, affiner son dessin ou demander un essai WebGL distinct. La vraie 3D n’est pas présumée supérieure.

**Aucune gestion réelle des divisions, permission Guild Master, migration ou donnée persistante ne sera ajoutée avant validation explicite.** Les rôles admin/editor et les autorisations par division restent un sujet de la phase B autorisée ultérieurement.
