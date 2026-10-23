<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\{Database, Auth, UploadService, PermissionService};
final class MediaController
{
    public function show(string $path): void
    {
        try {
            $pdo = Database::connect();
            $user=(new Auth($pdo))->user();
            if (!$user || !PermissionService::member($user)) {http_response_code(403);return;}
            $query = $pdo->prepare('SELECT a.publication_status, a.archived_at FROM achievement_details d JOIN achievements a ON a.id = d.achievement_id WHERE d.evidence_path = ? OR d.icon_path = ?');
            $query->execute([$path,$path]);
            $item = $query->fetch();
            if (!$item) { http_response_code(404); return; }
            if ($item['publication_status'] !== 'published' || $item['archived_at'] !== null) {
                $user = (new Auth($pdo))->user(true);
                if (!$user || !PermissionService::edit($user) || $user['must_change_password']) { http_response_code(404); return; }
            }
            $file = (new UploadService())->resolve($path);
            if (str_starts_with($path,'/assets/images/')) {
                $root=realpath(dirname(__DIR__,2).'/public/assets/images');
                $candidate=realpath(dirname(__DIR__,2).'/public'.$path);
                $file=$candidate && dirname($candidate)===$root && !is_link(dirname(__DIR__,2).'/public'.$path) ? $candidate : null;
            }
            if (!$file) { http_response_code(404); return; }
            $mime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','avif'=>'image/avif','png'=>'image/png','webp'=>'image/webp'][pathinfo($file, PATHINFO_EXTENSION)];
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($file));
            header('Content-Disposition: inline; filename="capture.' . pathinfo($file, PATHINFO_EXTENSION) . '"');
            header("Content-Security-Policy: default-src 'none'; sandbox");
            readfile($file);
        } catch (\Throwable) { http_response_code(503); }
    }
}
