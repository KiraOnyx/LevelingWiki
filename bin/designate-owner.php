<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{Database,OwnerService,ValidationException};
try {
    $options=getopt('',['username:','confirm-owner:','password-stdin']);
    $username=$options['username']??'';$confirm=$options['confirm-owner']??'';
    if ($username==='' || $confirm!==$username) throw new ValidationException('Usage : php bin/designate-owner.php --username=IDENTIFIANT --confirm-owner=IDENTIFIANT [--password-stdin]');
    if (isset($options['password-stdin'])) $password=rtrim(stream_get_contents(STDIN),"\r\n");
    else {
        if (!stream_isatty(STDIN)) throw new ValidationException('Terminal interactif requis.');
        fwrite(STDOUT,"Désigner ce compte ADMIN local comme propriétaire unique : $username\nMot de passe actuel (masqué) : ");
        system('stty -echo',$status);if ($status!==0) throw new ValidationException('Saisie masquée indisponible.');
        try {$password=rtrim((string)fgets(STDIN),"\r\n");} finally {system('stty echo');fwrite(STDOUT,"\n");}
    }
    (new OwnerService(Database::connect()))->designate($username,$confirm,$password);
    echo "Propriétaire désigné explicitement. Reconnectez-vous.\n";
} catch (ValidationException $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
catch (Throwable) {fwrite(STDERR,"Désignation impossible ; aucune promotion effectuée.\n");exit(1);}
