<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{OwnerAccess,Database,Input,Audit,ValidationException};
$options=getopt('',['generate','write-env','show','reset-password','username:','confirm-owner:','password-stdin']);
try {
    if(isset($options['generate'])){
        $path=OwnerAccess::generate();
        if(isset($options['write-env'])){
            $file=dirname(__DIR__).'/.env';$old=file_get_contents($file);
            if($old===false)throw new RuntimeException();
            $line='OWNER_LOGIN_PATH='.$path;
            $new=preg_match('/^OWNER_LOGIN_PATH=/m',$old)?preg_replace('/^OWNER_LOGIN_PATH=.*$/m',$line,$old):rtrim($old)."\n".$line."\n";
            if(file_put_contents($file,$new,LOCK_EX)!==strlen($new))throw new RuntimeException();
            chmod($file,0600);echo "Chemin renouvelé dans .env. Recréez le service web pour appliquer la configuration.\n";
        }else echo $path,"\n";
    }elseif(isset($options['show'])){
        $path=OwnerAccess::path();if(!$path)throw new ValidationException('Aucun chemin valide dans l’environnement actif.');echo $path,"\n";
    }elseif(isset($options['reset-password'])){
        $name=$options['username']??'';
        if($name===''||$name!==($options['confirm-owner']??null))throw new ValidationException('Confirmation explicite du propriétaire requise.');
        if(isset($options['password-stdin']))$password=rtrim(stream_get_contents(STDIN),"\r\n");
        else{
            if(!stream_isatty(STDIN))throw new ValidationException('Terminal interactif requis.');
            fwrite(STDOUT,"Nouveau mot de passe (saisie masquée) : ");system('stty -echo',$status);if($status!==0)throw new RuntimeException();
            try{$password=rtrim((string)fgets(STDIN),"\r\n");fwrite(STDOUT,"\nConfirmer : ");$confirm=rtrim((string)fgets(STDIN),"\r\n");}finally{system('stty echo');echo "\n";}
            if(!hash_equals($password,$confirm))throw new ValidationException('Confirmation différente.');
        }
        $password=Input::password($password);$pdo=Database::connect();$pdo->beginTransaction();
        $q=$pdo->prepare("SELECT id FROM users WHERE username=? AND role='super_admin' AND auth_provider='local' AND is_active=1 FOR UPDATE");$q->execute([$name]);$id=$q->fetchColumn();
        if(!$id)throw new ValidationException('Propriétaire local actif introuvable.');
        $pdo->prepare('UPDATE users SET password_hash=?,must_change_password=0,session_version=session_version+1 WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT,['cost'=>12]),$id]);
        Audit::record($pdo,(int)$id,'owner.recovery','user',(int)$id);$pdo->commit();echo "Mot de passe renouvelé. Sessions précédentes invalidées.\n";
    }else throw new ValidationException('Options : --generate [--write-env], --show, --reset-password --username=… --confirm-owner=…');
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,$e instanceof ValidationException?$e->getMessage()."\n":"Opération impossible. Vérifiez les permissions et la configuration locale.\n");exit(1);}
