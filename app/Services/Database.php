<?php
declare(strict_types=1);
namespace App\Services;

use PDO;

final class Database
{
    public static function connect(): PDO
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['host'], $config['port'], $config['database']),
            $config['username'], $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 5]
        );
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }
}
