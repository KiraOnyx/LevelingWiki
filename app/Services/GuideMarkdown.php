<?php
declare(strict_types=1);
namespace App\Services;
require_once dirname(__DIR__).'/Vendor/Parsedown/Parsedown.php';
/** One server renderer for preview and member view. Raw HTML and remote images are never trusted. */
final class GuideMarkdown extends \Parsedown
{
    public function __construct(private array $images=[]){$this->setSafeMode(true);$this->setMarkupEscaped(true);}
    protected function sanitiseElement(array $element)
    {
        $element=parent::sanitiseElement($element);
        $name=$element['name']??'';
        if($name==='a') {
            $url=$element['attributes']['href']??'';
            if(!preg_match('~^https?://~i',$url)||!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_USER)!==null||preg_match('/[\x00-\x20\\\\]/',$url))unset($element['attributes']['href']);
            $element['attributes']['rel']='noopener noreferrer nofollow';
        }
        if($name==='img') {
            $url=$element['attributes']['src']??'';
            if(!in_array($url,$this->images,true))return ['name'=>'span','text'=>'[Image non disponible]'];
            $element['attributes']['alt']=trim($element['attributes']['alt']??'')?:'Illustration du guide';
            $element['attributes']['loading']='lazy';
        }
        return $element;
    }
    protected function element(array $element) { if(preg_match('/^h([1-6])$/',$element['name']??'',$m))$element['name']='h'.min(6,max(3,(int)$m[1]));return parent::element($element); }
    public static function render(string $markdown,array $images=[]):string{return (new self($images))->text($markdown);}
}
