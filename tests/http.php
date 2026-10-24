<?php
declare(strict_types=1);
// À lancer dans le conteneur web : teste le véritable serveur Apache local.
$checks = ['/' => 303, '/succes' => 303, '/statut' => 200, '/page-inexistante' => 404,
    '/succes/introuvable-test' => 303, '/.env' => 404, '/config/database.php' => 404, '/bin/import-achievements.php' => 404,
    '/database/imports/achievements-valhalla.json' => 404,
    '/manifest.webmanifest'=>200, '/assets/icons/icon-192.png'=>200, '/assets/icons/icon-512.png'=>200, '/assets/icons/apple-touch-icon.png'=>200,
    '/assets/js/catalog-core.mjs' => 200, '/assets/css/theme.css' => 200,
    '/assets/vendor/neu-axentix/neu-axentix.min.css' => 200];
if (getenv('APP_ENV') === 'test') $checks['/succes/elan-du-matin-test'] = 303;
foreach ($checks as $path => $expected) {
    $context = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location'=>0, 'timeout' => 5]]);
    $body = file_get_contents('http://localhost' . $path, false, $context);
    preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $matches);
    if ((int) ($matches[1] ?? 0) !== $expected || $body === false) throw new RuntimeException("HTTP inattendu : $path");
    if (str_contains($body, 'SQLSTATE') || str_contains($body, 'Fatal error') || str_contains($body, 'Warning:')) {
        throw new RuntimeException("Erreur PHP divulguée : $path");
    }
    if ($path === '/succes' && str_contains($body, 'catalog-data')) {
        throw new RuntimeException('Données privées dans une réponse anonyme.');
    }
    if ($path === '/assets/js/catalog-core.mjs' && !str_contains(implode('\n', $http_response_header), 'text/javascript')) {
        throw new RuntimeException('MIME incorrect pour les modules JavaScript.');
    }
    echo "OK : $path → $expected\n";
}
