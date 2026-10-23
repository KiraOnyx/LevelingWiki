<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\{Database, Auth, Input, ValidationException, AchievementService, TaxonomyService, UserService, CatalogService, Audit, PermissionService, DiscordRoleService};
use App\Models\AdminRepository;
use App\Middleware\AdminGuard;
use PDO;

final class AdminController
{
    private PDO $pdo;
    private Auth $auth;
    private AdminRepository $repository;
    private ?array $user = null;
    public function dispatch(string $path): void
    {
        try {
            $this->pdo = Database::connect(); $this->auth = new Auth($this->pdo); $this->repository = new AdminRepository($this->pdo);
            $this->auth->start();
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if (!in_array($method,['GET','POST'],true)) { http_response_code(405); header('Allow: GET, POST'); $this->render('message',['error'=>'Méthode non autorisée.']); return; }
            if (\App\Services\OwnerAccess::matches($path)) { $this->login($method); return; }
            if ($path === '/admin/login') { http_response_code(404);$this->render('message',['error'=>'Page introuvable.']);return; }
            $this->user = AdminGuard::authorize($this->auth,$path);
            if (!$this->user) return;
            if (isset($this->user['denied'])) { $this->user = null; $this->render('message',['error'=>'Accès refusé pour ce rôle.']); return; }
            if ($method === 'POST') {
                if (!$this->auth->validCsrf($_POST['csrf'] ?? null)) { http_response_code(403); $this->render('message',['error'=>'Formulaire expiré ou invalide. Rechargez la page avant de réessayer.']); return; }
                try { $this->write($path); }
                catch (ValidationException $e) { http_response_code(422); $this->read($path,$e->getMessage(),$_POST); }
                return;
            }
            $this->read($path);
        } catch (\Throwable) {
            error_log('LevelingWiki : administration indisponible.'); http_response_code(503);
            if (isset($this->auth)) $this->render('message',['error'=>'Service momentanément indisponible. Réessayez plus tard.']);
            else echo 'Service momentanément indisponible.';
        }
    }
    private function login(string $method): void
    {
        if ($current=$this->auth->user()) {
            if (!PermissionService::owner($current)) {http_response_code(404);$this->render('message',['error'=>'Page introuvable.']);return;}
            $this->redirect('/admin');return;
        }
        $error = null;
        if ($method === 'POST') {
            if (!$this->auth->validCsrf($_POST['csrf'] ?? null)) { http_response_code(403); $error='Formulaire expiré ou invalide. Rechargez la page.'; }
            else {
                $identifier = is_string($_POST['identifier'] ?? null) ? trim($_POST['identifier']) : '';
                $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
                if (strlen($identifier) <= 254 && $this->auth->login($identifier,$password,$_SERVER['REMOTE_ADDR'] ?? 'unknown')) { $this->redirect('/admin'); return; }
                if (http_response_code() !== 429) http_response_code(401);
                $error='Connexion impossible. Vérifiez vos identifiants ou réessayez plus tard.';
            }
        }
        $this->render('login',['error'=>$error]);
    }
    private function write(string $path): void
    {
        $actor = (int) $this->user['id'];
        if (!PermissionService::owner($this->user) && (($_POST['action']??'')==='delete' || ($path==='/admin/settings/discord' && ($_POST['level']??'')==='admin'))) {
            http_response_code(403);$this->render('message',['error'=>'Action réservée au propriétaire.']);return;
        }
        if ($path==='/admin/settings/discord') {
            (new DiscordRoleService($this->pdo))->save($_POST,$this->user);
            $this->success('Rôles enregistrés. Les permissions en cache sont invalidées.',$path);return;
        }
        if ($path === '/admin/logout') {
            Audit::record($this->pdo,$actor,'logout','user',$actor); $this->auth->logout(); $this->redirect('/connexion'); return;
        }
        if ($path === '/admin/password') {
            if (!$this->auth->confirmPassword($this->user,$_POST['current_password'] ?? null)) throw new ValidationException('Le mot de passe actuel est incorrect.');
            $password = Input::password($_POST['password'] ?? null);
            if (!is_string($_POST['password_confirmation'] ?? null) || !hash_equals($password,$_POST['password_confirmation'])) throw new ValidationException('Les mots de passe ne correspondent pas.');
            if ($this->auth->confirmPassword($this->user,$password)) throw new ValidationException('Choisissez un mot de passe différent du précédent.');
            (new UserService($this->pdo))->changePassword($actor,$password); $this->auth->logout(); $this->redirect('/connexion'); return;
        }
        if ($path === '/admin/achievements/save') {
            $file = isset($_FILES['evidence']) && is_array($_FILES['evidence']) ? $_FILES['evidence'] : null;
            $id = (new AchievementService($this->pdo))->save($_POST,$file,$actor);
            $this->success('Succès enregistré.','/admin/achievements/'.$id.'/edit'); return;
        }
        if (preg_match('~^/admin/achievements/([1-9][0-9]*)/(archive|restore|delete)$~D',$path,$match)) {
            $this->confirm();
            (new AchievementService($this->pdo))->removeOrArchive(Input::id($match[1]),$match[2],$actor);
            $this->success('Opération effectuée.','/admin/achievements'); return;
        }
        if (in_array($path,['/admin/categories','/admin/titles'],true)) {
            if (($_POST['action'] ?? '') !== 'save') $this->confirm();
            (new TaxonomyService($this->pdo))->save(basename($path),$_POST,$actor);
            $this->success('Modification enregistrée.',$path); return;
        }
        if ($path === '/admin/users') {
            if (!$this->auth->confirmPassword($this->user,$_POST['current_password'] ?? null)) throw new ValidationException('Confirmez votre mot de passe actuel pour gérer les accès.');
            $action = Input::choice($_POST,'action',['create','update','reset']);
            $service = new UserService($this->pdo);
            if ($action === 'create') $service->create($_POST,$actor); else $service->update($_POST,$actor);
            $this->success('Accès mis à jour. Les sessions concernées sont invalidées.','/admin/users'); return;
        }
        http_response_code(404); $this->render('message',['error'=>'Action introuvable.']);
    }
    private function read(string $path, ?string $error = null, array $old = []): void
    {
        if ($path==='/admin/settings/discord') {
            $this->render('discord',['roles'=>$this->pdo->query('SELECT * FROM discord_roles ORDER BY level,label')->fetchAll(),'error'=>$error]);return;
        }
        if ($path === '/admin') {
            $items = $this->repository->achievements();
            $active = array_filter($items,fn($a)=>$a['archived_at'] === null);
            $stats = [count($items),count(array_filter($active,fn($a)=>$a['publication_status']==='published')),
                count(array_filter($active,fn($a)=>$a['publication_status']==='draft')),count(array_filter($active,fn($a)=>$a['secret_type']==='secret')),
                count(array_filter($active,fn($a)=>!CatalogService::rewardsKnown($a)))];
            $this->render('dashboard',compact('items','stats','error')); return;
        }
        if ($path === '/admin/achievements' || preg_match('~^/admin/achievements/[1-9][0-9]*/(?:delete|archive|restore)$~D',$path)) {
            $this->render('achievements',['items'=>$this->repository->achievements(),'error'=>$error]); return;
        }
        if (in_array($path,['/admin/achievements/new','/admin/achievements/save'],true) || preg_match('~^/admin/achievements/([1-9][0-9]*)/edit$~D',$path,$match)) {
            $id = isset($match[1]) ? Input::id($match[1]) : (!empty($old['id']) && is_scalar($old['id']) && ctype_digit((string)$old['id']) ? (int)$old['id'] : null);
            $item = $id ? $this->repository->achievement($id) : [];
            if ($id && !$item) { http_response_code(404);$this->render('message',['error'=>'Succès introuvable.']);return; }
            if ($old) {
                foreach (['name','slug','description','unlock_conditions','category_id','rarity','secret_type','source_url','verification_status','publication_status',...array_keys(CatalogService::REWARDS)] as $key) $item[$key] = is_string($old[$key] ?? null) ? $old[$key] : '';
                $item['title_rewards_known'] = isset($old['title_rewards_known']);
                $item['title_ids'] = is_array($old['title_ids'] ?? null) ? array_map('intval',array_filter($old['title_ids'],'is_scalar')) : [];
            }
            $this->render('achievement-form',['item'=>$item,'categories'=>$this->repository->categories(),'titles'=>$this->repository->titles(),'error'=>$error]);return;
        }
        if ($path === '/admin/categories' || $path === '/admin/titles') {
            $kind = basename($path);$this->render('taxonomy',['kind'=>$kind,'records'=>$this->repository->$kind(),'error'=>$error]);return;
        }
        if ($path === '/admin/users') { $this->render('users',['records'=>$this->repository->users(),'error'=>$error]);return; }
        if ($path === '/admin/history') {
            $page = max(1,min(10000,is_scalar($_GET['page'] ?? null) ? (int) $_GET['page'] : 1));
            $this->render('history',['records'=>$this->repository->history($page),'pageNumber'=>$page]);return;
        }
        if ($path === '/admin/password') { $this->render('password',['error'=>$error]);return; }
        http_response_code(404);$this->render('message',['error'=>'Page introuvable.']);
    }
    private function confirm(): void { if (($_POST['confirm'] ?? '') !== '1') throw new ValidationException('Confirmez explicitement cette opération.'); }
    private function success(string $message, string $url): void { $_SESSION['flash']=$message; $this->redirect($url); }
    private function redirect(string $url): void { header('Location: '.$url,true,303); }
    private function render(string $view, array $data = []): void
    {
        require_once dirname(__DIR__).'/Views/helpers.php';
        $user = $this->user; $csrf = $this->auth->csrf(); $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
        extract($data, EXTR_SKIP);
        require dirname(__DIR__).'/Views/admin/layout.php';
    }
}
