<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' https://cdn.discordapp.com; object-src 'none'; base-uri 'none'; frame-ancestors 'none'");
header('Cache-Control: no-store');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$controller = new App\Controllers\CatalogController();
if (App\Services\OwnerAccess::matches($path)) {
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Referrer-Policy: no-referrer');
    (new App\Controllers\AdminController())->dispatch($path);
} elseif (in_array($path, ['/','/connexion','/auth/discord','/auth/discord/callback','/deconnexion','/profil','/progression','/progression/import-local'], true)) {
    (new App\Controllers\MemberController())->dispatch($path);
} elseif ($path === '/tour-de-guilde') {
    (new App\Controllers\GuildTowerController())->show();
} elseif ($path === '/admin/tour-de-guilde' || str_starts_with($path, '/admin/tour-de-guilde/')) {
    (new App\Controllers\GuildTowerController())->admin(rtrim($path, '/'));
} elseif ($path === '/admin' || str_starts_with($path, '/admin/')) {
    (new App\Controllers\AdminController())->dispatch(rtrim($path, '/'));
} elseif (str_starts_with($path, '/media/') || preg_match('~^/assets/images/[^/]+\.(png|jpe?g|webp|avif)$~iD',$path)) {
    (new App\Controllers\MediaController())->show($path);
} elseif ($path === '/prototype/tour-de-guilde') {
    (new App\Controllers\TowerPrototypeController())->show();
} elseif ($path === '/statut') {
    (new App\Controllers\StatusController())->index();
} elseif ($path === '/mes-succes' || $path === '/succes' || $path === '/succes/') {
    $controller->show();
} elseif (preg_match('~^/succes/([^/]+)$~D', $path, $match)) {
    $controller->show($match[1]);
} else {
    $controller->notFound();
}
