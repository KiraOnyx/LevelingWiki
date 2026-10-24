<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{Database,CategoryIdentity};
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='test'||getenv('DB_DATABASE')!=='levelingwiki_test')exit(1);
$p=Database::connect();
function identityCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "OK : $label\n";}
// Existing isolated imported records reproduce the historic labels and missing association.
foreach(CategoryIdentity::HISTORICAL as $slug=>$c)$p->prepare('UPDATE categories SET name=?,color=? WHERE slug=?')->execute([$c['old'],'#123456',$slug]);
$p->exec("UPDATE achievements SET category_id=NULL WHERE slug='meme-jour-autre-monde'");
$p->exec("INSERT INTO categories(slug,name,color) VALUES('bleu-personnalise','Bleu personnalisé','#abcdef')");
$beforeCategories=$p->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$beforeAchievements=$p->query('SELECT * FROM achievements ORDER BY id')->fetchAll();
$beforeProgress=$p->query('SELECT * FROM personal_achievements ORDER BY id')->fetchAll();
$migrate=require dirname(__DIR__).'/database/migrations/014_category_identity.php';$migrate($p);
foreach(CategoryIdentity::HISTORICAL as $slug=>$c){$q=$p->prepare('SELECT * FROM categories WHERE slug=?');$q->execute([$slug]);$after=$q->fetch();$before=array_values(array_filter($beforeCategories,fn($r)=>$r['slug']===$slug))[0];identityCheck($after['id']===$before['id']&&$after['name']===$c['name']&&$after['color']===$c['color'],'identifiant préservé et nouvelle identité : '.$slug);}
$green=(int)$p->query("SELECT id FROM categories WHERE slug='vert'")->fetchColumn();
foreach($beforeAchievements as &$row)if($row['slug']==='meme-jour-autre-monde')$row['category_id']=$green;
unset($row);
identityCheck($beforeAchievements===$p->query('SELECT * FROM achievements ORDER BY id')->fetchAll(),'succès, récompenses et associations préservés sauf rattachement explicite');
identityCheck($beforeProgress===$p->query('SELECT * FROM personal_achievements ORDER BY id')->fetchAll(),'progressions inchangées');
$afterCategories=$p->query('SELECT * FROM categories ORDER BY id')->fetchAll();
identityCheck(end($beforeCategories)===end($afterCategories),'catégorie personnalisée inchangée');
$migrate($p);identityCheck($afterCategories===$p->query('SELECT * FROM categories ORDER BY id')->fetchAll(),'migration catégories idempotente');
identityCheck(!(int)$p->query("SELECT COUNT(*) FROM categories WHERE name='À confirmer'")->fetchColumn(),'aucune catégorie À confirmer');
// Uniqueness conflict must roll back all changes, never merge custom records.
$p->exec("UPDATE categories SET name='Bleu' WHERE slug='bleu'");$p->exec("INSERT INTO categories(slug,name) VALUES('custom-conflict','Timing et endurance')");$before=$p->query('SELECT * FROM categories ORDER BY id')->fetchAll();
try{$migrate($p);throw new LogicException('Expected conflict');}catch(PDOException|RuntimeException $e){identityCheck($before===$p->query('SELECT * FROM categories ORDER BY id')->fetchAll(),'conflit de nom : transaction annulée sans fusion');}
$p->exec("DELETE FROM categories WHERE slug='custom-conflict'");$migrate($p);
$manifest=json_decode(file_get_contents(dirname(__DIR__).'/public/manifest.webmanifest'),true,512,JSON_THROW_ON_ERROR);
identityCheck($manifest['start_url']==='/'&&$manifest['display']==='standalone'&&count($manifest['icons'])===2,'manifest minimal et accueil existant');
foreach($manifest['icons'] as $icon){$size=getimagesize(dirname(__DIR__).'/public'.$icon['src']);identityCheck($icon['sizes']===$size[0].'x'.$size[1],'icône installable '.$icon['sizes']);}
require __DIR__.'/http-client.php';
$credentials=json_decode(file_get_contents('/tmp/levelingwiki-test-admin.json'),true);$client=new HttpTestClient();$client->login($credentials['username'],$credentials['password']);
$home=$client->request('/');identityCheck($home['status']===200&&str_contains($home['body'],'Le Valhalla vous accompagne partout')&&str_contains($home['body'],'iPhone et iPad')&&str_contains($home['body'],'Android')&&str_contains($home['body'],'/manifest.webmanifest'),'accueil authentifié et installation');
$catalog=$client->request('/succes');$edit=$client->request('/admin/achievements/new');
foreach(CategoryIdentity::HISTORICAL as $category)identityCheck(str_contains($catalog['body'],$category['name'])&&str_contains($edit['body'],$category['name']),'nouveau nom dans filtres et formulaire : '.$category['name']);
identityCheck(!str_contains($home['body'],'serviceWorker.register'),'aucun service worker privé ajouté');
