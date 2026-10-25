# Tour de guilde — Phase C

## Résultat et audit

Phase C implémentée le 25 octobre 2026. MariaDB est désormais la source de vérité des paliers, types et récompenses de `/tour-de-guilde`. Aucun suivi de récupération, inventaire, association membre/division ou statistique de phase D n’a été ajouté.

L’audit a porté sur les migrations 001 à 015 et leur exécuteur à empreintes, `config/guild-tower.php`, les contrôleurs de tour/prototype, `GuildDivisionRepository`, `GuildDivisionService`, `Auth`, `PermissionService`, `Input`, `Audit`, les layouts administratifs, les composants SVG/CSS/JS et les suites PHP/Node. Les conventions PDO, transactions, CSRF, archivage et version attendue ont été reprises. Aucun framework ni paquet supplémentaire.

La migration 015 et les migrations antérieures sont inchangées. Pas de branche, commit, push ou merge. La modification préexistante de `.env.example` n’appartient pas à cette phase.

## Migration et données initiales

`database/migrations/016_guild_catalog.php` crée :

- `guild_milestones` : niveau entier positif jusqu’à 4 294 967 295, titre, description, archivage, version et dates. Une colonne générée indexée garantit l’unicité du niveau parmi les paliers actifs, y compris en concurrence et lors d’une restauration.
- `guild_reward_types` : identifiant technique unique permanent, nom, nom court, description, icône locale autorisée, unité, catégorie, ordre, archivage, version et dates.
- `guild_milestone_rewards` : identifiant stable, références au palier et au type, quantité entière non négative (BIGINT UNSIGNED), distribution, description, visibilité, ordre, archivage, version et dates. Clés étrangères restrictives.
- `guild_catalog_initializations` : marqueur durable d’import. L’initialisation transactionnelle n’est exécutée qu’une fois. Une réexécution du corps de migration ne réinsère pas les entrées volontairement supprimées ou archivées et ne modifie pas les éditions administratives. Les DDL sont réexécutables après interruption.

Import :

| Niveau | Récompense | Distribution |
|---|---|---|
| 1 | 300 pièces d’or | Individuelle |
| 5 | 25 gemmes | Individuelle |
| 10 | 5 places de membres | Collective |
| 20 | 1 clé par membre, catégorie à préciser | Individuelle |
| 30 | 10 clés, catégorie et distribution à préciser | À confirmer |
| 40 | 10 places de membres | Collective |
| 50 | 1500 pièces d’or | Individuelle |
| 60, 70, 80, 90, 100 | ???, type et quantité NULL | À confirmer |

Cinq types initiaux : pièces d’or, gemmes, clés de donjon, clés de guilde, places de membres. Les deux types de clés sont distincts. Aucun n’est associé arbitrairement aux niveaux 20 et 30 : ces récompenses conservent leur quantité et une description explicite, avec type NULL. Les ressources personnalisées se créent dans l’administration, sans modification PHP.

La catégorie d’un type décrit sa nature. La distribution appartient à chaque récompense et reste indépendante de cette catégorie. « À confirmer » préserve les informations incomplètes au lieu d’inventer une distribution.

`config/guild-tower.php` est vidé de son catalogue actif. Les anciennes données sont isolées dans `tests/fixtures/tower-levels.php`, exclusivement pour le prototype et ses tests. La migration conserve son propre import figé et ne dépend pas de ces fixtures.

La migration a été appliquée localement après vérification des empreintes 001–015 et réussite de la suite isolée. Résultat local : 12 paliers, 5 types, 12 récompenses. Les empreintes SHA-256 des trois tables de divisions étaient strictement identiques avant et après. Aucune fixture de test n’a été importée dans la base locale.

## Routes

| Méthode | Route | Fonction |
|---|---|---|
| GET | `/admin/tour-de-guilde/paliers` | Recherche, tri, filtre actif/archivé/tous, édition avec `id` |
| POST | `/admin/tour-de-guilde/paliers/save` | Création, modification, archivage, restauration |
| GET | `/admin/tour-de-guilde/types` | Catalogue et édition avec `id` |
| POST | `/admin/tour-de-guilde/types/save` | Création, modification, archivage, restauration |
| POST | `/admin/tour-de-guilde/recompenses/save` | Ajout ou édition d’une récompense du palier |

Les routes existantes de divisions et Guild Masters restent identiques. Les filtres `q`, `status`, `sort` sont conservés après enregistrement. Les formulaires POST redirigent en 303 et affichent un message ; JavaScript désactive les boutons à la soumission et les réactive au retour navigateur. La validation serveur reste obligatoire. Les erreurs de champs sont affichées près des contrôles concernés et dans un résumé.

## Utilisation

1. **Créer un palier** : Administration → Tour de guilde → Paliers et récompenses. Remplir niveau, titre, description et statut dans « Créer un palier ». Enregistrer. Le formulaire de récompenses devient disponible.
2. **Ajouter plusieurs récompenses** : dans ce palier, utiliser « Ajouter une récompense ». Choisir type, quantité, distribution, visibilité et ordre. Enregistrer puis répéter. L’aperçu textuel suit les champs sans rechargement ni déplacement de la tour.
3. **Créer un type personnalisé** : ouvrir Types de récompenses. Renseigner un identifiant comme `jetons-evenement`, son nom, les métadonnées et une icône locale. L’identifiant technique ne peut plus être changé après création ; le nom reste modifiable.
4. **Révéler une inconnue** : ouvrir la récompense existante, compléter son type et sa quantité, choisir sa distribution, passer la visibilité à « Connue » puis enregistrer. L’identifiant de récompense est conservé.
5. **Classer les clés** : ouvrir les paliers 20 et 30, sélectionner le type de clé après confirmation métier, préciser la distribution si nécessaire et ajuster la description. Aucun type de clé n’a été présumé.
6. **Archiver un palier** : l’ouvrir, choisir « Archivé » puis enregistrer. Pour restaurer, filtrer les archivés puis repasser à « Actif ». Un conflit de niveau actif est refusé. Restaurer le palier avant d’éditer ses récompenses.
7. **Archiver une récompense ou un type** : modifier son statut. Les références restent intactes. Les récompenses archivées ne sont plus montrées aux membres. Un type archivé reste affiché sur les récompenses qui le référencent déjà, mais n’est plus disponible pour de nouvelles associations.
8. **Vérifier la tour** : ouvrir `/tour-de-guilde` ou la recharger. Chaque palier actif produit automatiquement un étage. Les niveaux 120, 150, 200 ou plus ne nécessitent aucun changement SVG. « Voir les divisions » calcule les déblocages à partir des niveaux persistants réels.
9. **Consulter les changements** : Administration → Historique. Les actions `guild.catalog.create/update/archive/restore` montrent les valeurs avant/après, notamment niveau, quantité, visibilité, type et distribution.

## Inconnues et sécurité

La projection `GuildCatalogRepository::present()` retire type, quantité, description et distribution de chaque récompense inconnue avant transmission à la vue. Seuls un identifiant, `???`, une icône générique et le statut inconnu sont publics. Le JSON intégré est aussi filtré : aucun secret ne dépend d’un simple masquage CSS. Chaque récompense possède son propre statut, permettant des combinaisons connues/inconnues.

Toutes les écritures nécessitent l’identité existante et `GuildDivisionService::admin()` (ADMIN ou SUPER_ADMIN autorisé). MEMBER et EDITOR, y compris Guild Master, ne peuvent pas modifier ce catalogue. Le périmètre Guild Master existant n’est pas élargi. Authentification sensible rafraîchie, CSRF, liste des champs autorisés, requêtes préparées, contrôles de relations et échappement HTML restent côté serveur.

Chaque modification verrouille l’entité en transaction et vérifie la version attendue. Une version périmée renvoie 409, sans écrasement. Une récompense ne peut pas être déplacée vers un autre palier par falsification de l’identifiant parent. L’audit est dans la même transaction. Aucun endpoint de suppression physique n’existe.

## Graphisme et performance

Les modules SVG 2D/2,5D, cristaux, pierres, fenêtres, corniches, montagnes, brume statique et éclairages existants sont conservés. Une carte peut contenir plusieurs lignes de récompenses ; la hauteur de l’étage s’adapte au contenu avant le recalcul des marqueurs et collisions. Les divisions gardent leur interpolation proportionnelle entre les niveaux réellement rendus.

Un pied architectural au niveau 1 reste présent si ce palier est archivé ou si le catalogue est vide ; il n’invente aucune récompense. Une division au-dessus du dernier palier conserve une ancre architecturale supérieure. L’arrivée au pied et les raccourcis restent assurés par le module existant. Aucune mise à jour automatique ni boucle permanente n’a été ajoutée ; les changements métier apparaissent au prochain chargement. Le repli 2D et les préférences de mouvement sont inchangés.

Icônes : fonction `icon()` et SVG locaux déjà présents. Aucun téléchargement, nouvel asset tiers ou nouvelle licence ; provenance des décors existants dans `tour-de-guilde-iteration-2.md`.

## Vérifications

- `sh tests/run-admin.sh` : **543 contrôles OK**, sortie 0, dans Docker `levelingwiki-tests`, base tmpfs distincte, nettoyage automatique.
- `node --test tests/*.test.mjs` : **19 tests réussis**.
- Tests de prototype inclus : **12 contrôles**, compris dans les 543.
- Nouvelle suite `tests/guild-catalog.php` : import, références, inconnues, plusieurs récompenses, création/modification/archivage/restauration des trois entités, niveau 120, versions périmées, niveaux invalides, quantité négative, permissions, CSRF, parent forgé, identifiant composé, type archivé, audit, absence de fuite HTML/JSON, réexécution sans écrasement et sans résurrection, conflit de restauration, invariance des divisions, catalogue vide sans repli statique.
- Vérification navigateur de l’ajout d’une récompense via le formulaire : réussite et filtres conservés.
- Administration et tour examinées à **320, 390, 768, 1200 et 1440 px** : aucun débordement horizontal mesuré. Sur la tour avec deux divisions persistantes de test et un palier 120 : aucun chevauchement marqueur/carte ni marqueur/marqueur mesuré aux cinq largeurs.
- `git diff --check` : réussi.

Logs locaux de vérification : `/private/tmp/levelingwiki-phase-c-tests-final.log` et `/private/tmp/levelingwiki-phase-c-js.log`.

Limites : vérification responsive dans le navigateur intégré, pas sur un iPhone physique ni un Safari iOS réel. Pas de mesure mémoire matérielle. Le catalogue est rendu intégralement et n’est pas virtualisé ; une très grande quantité de paliers/récompenses demanderait une mesure supplémentaire. La création d’un palier puis l’ajout de ses récompenses se font en deux étapes enregistrées. Aucune synchronisation en temps réel de la tour.

Une tentative de désignation d’un propriétaire de test a été refusée par le contrôle automatique ; aucune désignation n’a eu lieu. La vérification graphique a utilisé le parcours OAuth simulé des tests avec le compte administrateur déjà existant, sans modifier ses permissions.

## Fichiers créés

- `database/migrations/016_guild_catalog.php`
- `app/Controllers/GuildCatalogController.php`
- `app/Models/GuildCatalogRepository.php`
- `app/Services/GuildCatalogService.php`
- `app/Views/admin/guild-catalog.php`
- `app/Views/admin/guild-navigation.php`
- `public/assets/css/guild-catalog.css`
- `public/assets/js/guild-catalog.js`
- `tests/guild-catalog.php`
- `tests/fixtures/tower-levels.php`
- `docs/tour-de-guilde-phase-c.md`

## Fichiers modifiés

- `app/Controllers/GuildTowerController.php`
- `app/Services/Audit.php`
- `app/Views/admin/guild-divisions.php`
- `app/Views/admin/layout.php`
- `app/Views/layout.php`
- `app/Views/tower/prototype.php`
- `config/guild-tower.php`
- `public/assets/css/tower.css`
- `public/assets/js/tower.js`
- `public/index.php`
- `tests/fixtures/tower.php`
- `tests/run-admin.sh`
