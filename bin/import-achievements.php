<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{AchievementImportService, Database, ValidationException};
$importer=null;
try {
    $options=[];
    foreach (array_slice($argv,1) as $arg) {
        if (preg_match('/^--file=(.+)$/D',$arg,$match)) { $key='file'; $value=$match[1]; }
        elseif (in_array($arg,['--help','--dry-run','--apply'],true)) { $key=substr($arg,2); $value=true; }
        else throw new ValidationException('Option inconnue : utiliser --help.');
        if (isset($options[$key])) throw new ValidationException('Option répétée : '.$key);
        $options[$key]=$value;
    }
    if (isset($options['help']) && count($options)===1) {
        echo "Usage : php bin/import-achievements.php --file=database/imports/achievements-valhalla.json (--dry-run | --apply)\n";
        echo "CLI uniquement. Fichier local confiné à database/imports ou storage/imports. 33 brouillons non vérifiés ; acteur système CLI.\n";
        exit(0);
    }
    if (!isset($options['file']) || isset($options['help']) || (isset($options['dry-run']) === isset($options['apply']))) throw new ValidationException('Choisir exactement --dry-run ou --apply et fournir --file. Utiliser --help.');
    $importer=new AchievementImportService(Database::connect());
    $report=$importer->run($options['file'],isset($options['apply']));
    echo json_encode(['mode'=>isset($options['apply'])?'apply':'dry-run',...$report],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $e) {
    $report=$importer?->report ?? ['lus'=>0,'valides'=>0,'créés'=>0,'ignorés'=>0,'erreurs'=>0];
    $report['erreurs']=max(1,$report['erreurs']);
    $report['message']=$e instanceof ValidationException ? $e->getMessage() : 'Échec de l’import ; transaction annulée. Vérifier la configuration et le schéma.';
    fwrite(STDERR,json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n"); exit(1);
}
