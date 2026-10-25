<?php
declare(strict_types=1);
// Isolated prototype fixture. Live catalogue comes exclusively from MariaDB.
return [
        ['level'=>1,'label'=>'300 pièces d’or','kind'=>'personal','icon'=>'medal'],
        ['level'=>5,'label'=>'25 gemmes','kind'=>'personal','icon'=>'spark'],
        ['level'=>10,'label'=>'+5 places de membres','kind'=>'collective','icon'=>'shield'],
        ['level'=>20,'label'=>'+1 clé par membre','kind'=>'unconfirmed','icon'=>'lock'],
        ['level'=>30,'label'=>'+10 clés','kind'=>'unconfirmed','icon'=>'lock'],
        ['level'=>40,'label'=>'+10 places de membres','kind'=>'collective','icon'=>'shield'],
        ['level'=>50,'label'=>'1 500 pièces d’or','kind'=>'personal','icon'=>'medal'],
        ...array_map(static fn(int $level): array => ['level'=>$level,'label'=>'Récompense inconnue','kind'=>'unknown','icon'=>'lock'], [60,70,80,90,100]),
    ];
