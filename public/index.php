<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'");
header('Cache-Control: no-store');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$controller = new App\Controllers\CatalogController();
if ($path === '/statut') {
    (new App\Controllers\StatusController())->index();
} elseif ($path === '/' || $path === '/succes' || $path === '/succes/') {
    $controller->show();
} elseif (preg_match('~^/succes/([^/]+)$~D', $path, $match)) {
    $controller->show($match[1]);
} else {
    $controller->notFound();
}
