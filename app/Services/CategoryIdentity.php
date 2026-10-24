<?php
declare(strict_types=1);
namespace App\Services;
final class CategoryIdentity
{
    public const HISTORICAL = [
        'bleu'=>['old'=>'Bleu','name'=>'Timing et endurance','color'=>'#4F8FD1','tone'=>'blue'],
        'rose'=>['old'=>'Rose','name'=>'Actions et conditions spéciales','color'=>'#C778A8','tone'=>'pink'],
        'jaune'=>['old'=>'Jaune','name'=>'Progression et défis','color'=>'#D2AA56','tone'=>'yellow'],
        'turquoise'=>['old'=>'Turquoise','name'=>'Découvertes et butin','color'=>'#39AAA7','tone'=>'turquoise'],
        'vert'=>['old'=>'Vert','name'=>'Régularité et séries','color'=>'#70A47A','tone'=>'green'],
    ];
    public static function fromSource(string $name, string $slug): ?array
    {
        if ($name==='À confirmer' && $slug==='meme-jour-autre-monde') return ['vert',self::HISTORICAL['vert']];
        foreach(self::HISTORICAL as $key=>$category) if(in_array($name,[$category['old'],$category['name']],true)) return [$key,$category];
        return null;
    }
    public static function tone(?string $slug): string {return self::HISTORICAL[$slug??'']['tone']??'neutral';}
}
