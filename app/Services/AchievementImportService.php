<?php
declare(strict_types=1);
namespace App\Services;

use PDO;

final class AchievementImportService
{
    public array $report = ['lus'=>0,'valides'=>0,'créés'=>0,'ignorés'=>0,'erreurs'=>0,'à_créer'=>0,'détails'=>[]];
    public function __construct(private PDO $pdo) {}

    private function keys(array $data, array $expected): void
    {
        $actual = array_keys($data); sort($actual); sort($expected);
        if ($actual !== $expected) throw new ValidationException('Champs manquants ou inconnus.');
    }

    public function run(string $file, bool $apply): array
    {
        if (PHP_SAPI !== 'cli') throw new ValidationException('Import réservé à la CLI.');
        $config = require dirname(__DIR__,2).'/config/security.php';
        $roots = [dirname(__DIR__,2).'/database/imports', dirname($config['upload_directory']).'/imports'];
        $path = realpath($file);
        $allowed = false;
        foreach ($roots as $root) {
            $resolved = realpath($root);
            if ($path !== false && $resolved !== false && str_starts_with($path, $resolved.DIRECTORY_SEPARATOR)) $allowed = true;
        }
        if (!$allowed || !is_file($path) || !is_readable($path) || filesize($path)>2097152) throw new ValidationException('Fichier local requis dans database/imports ou storage/imports (2 Mio maximum).');
        $raw = file_get_contents($path);
        try { $document = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new ValidationException('JSON UTF-8 invalide (document).'); }
        if (!is_array($document)) throw new ValidationException('Document JSON invalide.');
        $this->keys($document, ['source','achievements']);
        if (!is_array($document['source']) || !is_array($document['achievements']) || !array_is_list($document['achievements'])) throw new ValidationException('Structure source/achievements invalide.');
        $source = $document['source'];
        $this->keys($source, ['title','edition','status','note']);
        foreach (['title','edition','status','note'] as $key) Input::text($source,$key,2000,true);
        // Ce contrat est spécifique au guide fourni : aucune inférence depuis un autre document.
        if ($source['title'] !== 'Le Codex des succès secrets — Archives de Valhalla'
            || $source['edition'] !== '2026-10-14' || $source['status'] !== 'guide communautaire non officiel') throw new ValidationException('Source ou édition non prise en charge.');
        $this->report['lus'] = count($document['achievements']);
        if ($this->report['lus'] !== 33) throw new ValidationException('Le document doit contenir exactement 33 succès.');
        $rows = []; $numbers = []; $names = []; $slugs = []; $errors = [];
        $service = new AchievementService($this->pdo);
        foreach ($document['achievements'] as $i=>$row) {
            try {
                if (!is_array($row)) throw new ValidationException('Objet succès requis.');
                $this->keys($row, ['source_number','name','slug','description','category','rewards','publication_status','source_url','titles','verification_note']);
                $number = $row['source_number'];
                if (!is_int($number) || $number<1 || $number>33 || isset($numbers[$number])) throw new ValidationException('Numéro source invalide ou dupliqué.');
                $numbers[$number] = true;
                foreach (['name'=>191,'slug'=>191,'description'=>12000,'category'=>100,'verification_note'=>2000] as $key=>$max) {
                    $value = Input::text($row,$key,$max,true);
                    if ($value !== $row[$key]) throw new ValidationException('Espaces périphériques interdits : '.$key);
                }
                if (CategoryIdentity::fromSource($row['category'],$row['slug']) === null) throw new ValidationException('Catégorie inconnue.');
                if ($row['publication_status'] !== 'draft' || $row['source_url'] !== null || $row['titles'] !== []) throw new ValidationException('Brouillon, URL NULL et titres vides requis pour cette source.');
                if (!is_array($row['rewards'])) throw new ValidationException('Récompenses invalides.');
                $this->keys($row['rewards'], ['xp','gold','gems','keys']);
                $data = ['name'=>$row['name'],'slug'=>$row['slug'],'description'=>$row['description'],
                    'unlock_conditions'=>$row['description'],'secret_type'=>'secret','verification_status'=>'unverified',
                    'publication_status'=>'draft','source_url'=>'','rarity'=>'','title_ids'=>[]];
                foreach ($row['rewards'] as $key=>$value) {
                    if ($value !== null && (!is_int($value) || $value<0)) throw new ValidationException('Montant entier positif ou nul, ou NULL requis : '.$key);
                    $data['reward_'.$key] = $value === null ? '' : (string)$value;
                }
                $service->validate($data);
                // Même collation que les colonnes SQL, y compris casse et accents.
                foreach ($names as $j=>$name) {
                    $q=$this->pdo->prepare('SELECT CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci');
                    $q->execute([$row['name'],$name]);
                    if ($q->fetchColumn() || $slugs[$j] === $row['slug']) throw new ValidationException('Nom ou slug dupliqué dans le document.');
                }
                $names[]=$row['name']; $slugs[]=$row['slug'];
                $rows[]=['input'=>$row,'data'=>$data,'line'=>$i+1];
                $this->report['valides']++;
            } catch (ValidationException $e) { $errors[]='Ligne logique '.($i+1).' : '.$e->getMessage(); }
        }
        if ($errors) {
            $this->report['erreurs']=count($errors); $this->report['détails']=$errors;
            throw new ValidationException('Validation du document refusée avant écriture.');
        }
        if ((int)$this->pdo->query("SELECT GET_LOCK('levelingwiki_achievement_import', 10)")->fetchColumn()!==1) throw new ValidationException('Un import est déjà en cours.');
        try {
            if ($apply) $this->pdo->beginTransaction();
            $plan=[]; $categories=[];
            // Préflight complet : aucun service d’écriture avant d’avoir examiné les 33 lignes.
            foreach ($rows as $row) {
                $input=$row['input'];
                $q=$this->pdo->prepare('SELECT id, name, slug FROM achievements WHERE slug = ? OR name = ?'.($apply?' FOR UPDATE':''));
                $q->execute([$input['slug'],$input['name']]); $existing=$q->fetchAll();
                if ($existing) {
                    if (count($existing)!==1 || $existing[0]['slug']!==$input['slug'] || $existing[0]['name']!==$input['name']) throw new ValidationException('Ligne logique '.$row['line'].' : conflit nom/slug existant.');
                    $this->report['ignorés']++;
                    $this->report['détails'][]='Ignoré : '.$input['slug'];
                    continue;
                }
                $mapping=CategoryIdentity::fromSource($input['category'],$input['slug']); $category=$mapping[0]; $identity=$mapping[1];
                if ($mapping !== null && !array_key_exists($category,$categories)) {
                    $q=$this->pdo->prepare('SELECT * FROM categories WHERE slug = ? OR name = ?'.($apply?' FOR UPDATE':''));
                    $q->execute([$category,$identity['name']]); $found=$q->fetchAll();
                    if (count($found)>1 || ($found && (($found[0]['slug']!==$category || !in_array($found[0]['name'],[$identity['old'],$identity['name']],true)) || $found[0]['archived_at']!==null))) throw new ValidationException('Ligne logique '.$row['line'].' : catégorie en conflit ou archivée.');
                    $categories[$category]=$found ? (int)$found[0]['id'] : null;
                }
                $plan[]=$row;
            }
            $this->report['à_créer']=count($plan);
            if ($apply) {
                foreach ($categories as $name=>$id) {
                    if ($id !== null) continue;
                    $slug=$name;$identity=CategoryIdentity::HISTORICAL[$slug];$color=$identity['color'];
                    (new TaxonomyService($this->pdo))->save('categories',['action'=>'save','name'=>$identity['name'],'slug'=>$slug,'color'=>$color,'description'=>''],null);
                    // Audit::record insère aussi un ID : retrouver la catégorie par son slug.
                    $q=$this->pdo->prepare('SELECT id FROM categories WHERE slug = ?'); $q->execute([$slug]);
                    $categories[$name]=(int)$q->fetchColumn();
                }
                foreach ($plan as $row) {
                    $input=$row['input']; $data=$row['data'];
                    $data['category_id']=$categories[CategoryIdentity::fromSource($input['category'],$input['slug'])[0]] ?? null;
                    $id=$service->save($data,null,null);
                    Audit::record($this->pdo,null,'achievement.import','achievement',$id,null,[
                        'import_actor'=>'cli:import-achievements','source_number'=>$input['source_number'],
                        'source_title'=>$source['title'],'source_edition'=>$source['edition'],'source_status'=>$source['status'],
                        'source_note'=>$source['note'],'verification_note'=>$input['verification_note'],
                        'source_category'=>$input['category'],'source_sha256'=>hash('sha256',$raw),
                    ]);
                }
                $this->pdo->commit();
                $this->report['créés']=count($plan);
            }
            $this->report['détails'][]='Même jour, autre monde → Régularité et séries, récompenses inconnues ; rareté NULL ; brouillons non vérifiés ; acteur système CLI.';
            return $this->report;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        } finally { $this->pdo->query("SELECT RELEASE_LOCK('levelingwiki_achievement_import')"); }
    }
}
