<?php
declare(strict_types=1);
final class HttpTestClient {
    public array $cookies=[];
    public function request(string $path, ?array $data=null, ?array $file=null, array $extraHeaders=[]): array {
        $headers=['Connection: close',...$extraHeaders];$body='';
        if ($this->cookies) $headers[]='Cookie: '.implode('; ',array_map(fn($k,$v)=>$k.'='.$v,array_keys($this->cookies),$this->cookies));
        if ($data!==null && $file!==null) {
            $boundary='lw-test-'.bin2hex(random_bytes(10));
            $headers[]='Content-Type: multipart/form-data; boundary='.$boundary;
            foreach ($data as $key=>$value) foreach (is_array($value)?$value:[$value] as $part) {
                $field=$key.(is_array($value)?'[]':'');
                $body.="--$boundary\r\nContent-Disposition: form-data; name=\"$field\"\r\n\r\n$part\r\n";
            }
            $body.="--$boundary\r\nContent-Disposition: form-data; name=\"evidence\"; filename=\"{$file['name']}\"\r\nContent-Type: {$file['mime']}\r\n\r\n{$file['body']}\r\n--$boundary--\r\n";
        } elseif ($data!==null) { $headers[]='Content-Type: application/x-www-form-urlencoded';$body=http_build_query($data); }
        $context=stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>implode("\r\n",$headers),'content'=>$body,'ignore_errors'=>true,'follow_location'=>0,'timeout'=>15]]);
        $response=file_get_contents('http://127.0.0.1'.$path,false,$context);
        if ($response===false) throw new RuntimeException('Serveur de test inaccessible.');
        preg_match('/\s(\d{3})\s/',$http_response_header[0],$status);
        $location='';foreach ($http_response_header as $header) {
            if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$header,$m)) $this->cookies[$m[1]]=$m[2];
            if (stripos($header,'Location: ')===0) $location=substr($header,10);
        }
        if (str_contains($response,'Fatal error') || str_contains($response,'Warning:') || str_contains($response,'SQLSTATE')) throw new RuntimeException('Erreur PHP dans la réponse.');
        return ['status'=>(int)$status[1],'body'=>$response,'headers'=>$http_response_header,'location'=>$location];
    }
    public function token(string $path='/admin'): string {
        $response=$this->request($path);
        if (!preg_match('/name="csrf" value="([a-f0-9]{64})"/',$response['body'],$m)) throw new RuntimeException('Jeton absent : '.$path.' HTTP '.$response['status']);
        return $m[1];
    }
    public function login(string $identifier,string $password): array { return $this->request((string)getenv('OWNER_LOGIN_PATH'),['csrf'=>$this->token((string)getenv('OWNER_LOGIN_PATH')),'identifier'=>$identifier,'password'=>$password]); }
    public function post(string $path,array $data,?array $file=null): array { return $this->request($path,['csrf'=>$this->token(),...$data],$file); }
}
