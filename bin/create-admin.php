<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Services\{Database, UserService, ValidationException};
function prompt(string $label): string { fwrite(STDOUT, $label); return trim((string) fgets(STDIN)); }
try {
    $options = getopt('', ['username:', 'email:', 'password-stdin']);
    if (isset($options['password-stdin'])) {
        $username = $options['username'] ?? ''; $email = $options['email'] ?? '';
        $password = rtrim(stream_get_contents(STDIN), "\r\n");
    } else {
        if (!stream_isatty(STDIN)) throw new ValidationException('Un terminal interactif est requis, ou --username --password-stdin (email facultatif : --email).');
        $username = prompt('Identifiant : '); $email = prompt('Email (facultatif, Entrée pour ignorer) : ');
        fwrite(STDOUT, 'Mot de passe (12 à 72 octets, saisie masquée) : ');
        system('stty -echo', $status);
        if ($status !== 0) throw new ValidationException('Impossible de masquer la saisie.');
        try {
            $password = rtrim((string) fgets(STDIN), "\r\n");
            fwrite(STDOUT, "\nConfirmer : ");
            $confirmation = rtrim((string) fgets(STDIN), "\r\n");
        } finally { system('stty echo'); fwrite(STDOUT, "\n"); }
        if (!hash_equals($password,$confirmation)) throw new ValidationException('Les mots de passe ne correspondent pas.');
    }
    (new UserService(Database::connect()))->bootstrap(['username'=>$username,'email'=>$email,'password'=>$password]);
    fwrite(STDOUT, "Premier administrateur créé. Aucun mot de passe enregistré dans les logs.\n");
} catch (ValidationException $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
catch (Throwable) { fwrite(STDERR,"Création impossible. Vérifiez la configuration et les migrations.\n");exit(1); }
