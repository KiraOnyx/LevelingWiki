<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Services\{Auth,PermissionService};
final class AdminGuard {
    public static function authorize(Auth $auth,string $path): ?array {
        // Chaque accès administratif revérifie Discord ; aucun privilège sensible sur cache périmé.
        $user=$auth->user(true);
        if (!$user) {header('Location: /connexion',true,303);return null;}
        if ($user['must_change_password'] && $user['auth_provider']==='local' && !in_array($path,['/admin/password','/admin/logout'],true)) {header('Location: /admin/password',true,303);return null;}
        if ($path==='/admin/logout') return $user;
        if ($path==='/admin/password' && $user['auth_provider']==='local') return $user;
        if (!PermissionService::member($user) || !PermissionService::edit($user)) {http_response_code(403);return ['denied'=>true];}
        if (PermissionService::owner($user)) return $user;
        if ($path==='/admin/users' || $path==='/admin/password' || str_ends_with($path,'/delete')) {http_response_code(403);return ['denied'=>true];}
        if (PermissionService::admin($user)) return $user;
        $allowed=['/admin','/admin/achievements','/admin/achievements/new','/admin/achievements/save'];
        if (!in_array($path,$allowed,true) && !preg_match('~^/admin/achievements/[1-9][0-9]*/edit$~D',$path)) {http_response_code(403);return ['denied'=>true];}
        return $user;
    }
}
