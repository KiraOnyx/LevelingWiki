<?php
declare(strict_types=1);
namespace App\Controllers;

/** Read-only visual sandbox. No database, identity, session or persistent writes. */
final class TowerPrototypeController
{
    public function show(): void
    {
        if (!in_array(getenv('APP_ENV'), ['development', 'test'], true)) {
            http_response_code(404);
            echo 'Page introuvable.';
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            return;
        }
        header('X-Robots-Tag: noindex, nofollow');
        require_once dirname(__DIR__) . '/Views/helpers.php';
        $fixture = require dirname(__DIR__, 2) . '/tests/fixtures/tower.php';
        $isPrototype = true;
        $renderer = ($_GET['view'] ?? '') === 'relief' ? 'relief' : 'flat';
        $levels = $fixture['levels'];
        $divisions = $fixture['divisions'];
        $title = 'Tour de guilde — Prototype · LevelingWiki';
        $page = 'tower/prototype';
        $items = [];
        require dirname(__DIR__) . '/Views/layout.php';
    }
}
