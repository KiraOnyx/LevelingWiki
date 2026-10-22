<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\Database;
use Throwable;

final class StatusController
{
    public function index(): void
    {
        $connected = false;
        try {
            $connected = (int) Database::connect()->query('SELECT 1')->fetchColumn() === 1;
        } catch (Throwable) {
            // Ne jamais journaliser les paramètres ou le message PDO sur cette page.
            error_log('LevelingWiki : connexion à la base de données indisponible.');
        }
        http_response_code($connected ? 200 : 503);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        require dirname(__DIR__) . '/Views/status.php';
    }
}
