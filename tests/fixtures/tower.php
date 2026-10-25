<?php
declare(strict_types=1);
// Fictional progression only. Rewards faithfully reproduce the supplied brief.
return [
    'divisions' => [
        ['id'=>'v1','name'=>'Valhalla 1','short'=>'V1','level'=>43,'color'=>'violet'],
        ['id'=>'v2','name'=>'Valhalla 2','short'=>'V2','level'=>37,'color'=>'blue'],
        ['id'=>'v3','name'=>'Valhalla 3','short'=>'V3','level'=>24,'color'=>'teal'],
    ],
    'levels' => require dirname(__DIR__,2).'/config/guild-tower.php',
];
