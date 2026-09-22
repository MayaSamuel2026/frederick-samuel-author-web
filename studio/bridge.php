<?php
declare(strict_types=1);

const BOOK_AUTHOR_CORE_URL = 'https://noeva-core.179-198-203-247.nip.io';

function ba_bridge_secret_path(): string {
    return __DIR__ . '/data/bookauthor-bridge-token';
}
function ba_bridge_token_state(): array {
    $path=ba_bridge_secret_path();
    $token='';
    if(is_file($path)){
        $raw=@file_get_contents($path);
        if(is_string($raw)) $token=trim($raw);
    }
    if(!preg_match('/^[A-Za-z0-9_-]{48,192}$/',$token)){
        $dir=dirname($path);
        if(!is_dir($dir)) @mkdir($dir,0770,true);
        $token=rtrim(strtr(base64_encode(random_bytes(48)),'+/','-_'),'=');
        $tmp=$path.'.tmp.'.bin2hex(random_bytes(6));
        if(@file_put_contents($tmp,$token."\n",LOCK_EX)===false){
            return ['ok'=>false,'error'=>'bridge_secret_write_failed','token'=>null,'sha256'=>null];
        }
        @chmod($tmp,0600);
        if(!@rename($tmp,$path)){
            @unlink($tmp);
            return ['ok'=>false,'error'=>'bridge_secret_commit_failed','token'=>null,'sha256'=>null];
        }
        @chmod($path,0600);
    }
    return ['ok'=>true,'token'=>$token,'sha256'=>hash('sha256',$token)];
}
function ba_core_request(string $method, string $path, ?array $payload=null): array {
    $secret=ba_bridge_token_state();
    if(!($secret['ok']??false) || empty($secret['token'])){
        return ['ok'=>false,'error'=>$secret['error']??'bridge_secret_unavailable','http_status'=>0];
    }
    $url = rtrim(BOOK_AUTHOR_CORE_URL, '/') . '/' . ltrim($path, '/');
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-Book-Author-Bridge: ' . $secret['token'],
    ];
    $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) return ['ok'=>false,'error'=>'core_transport_error','detail'=>$error,'http_status'=>$code];
    } else {
        $opts = ['http'=>[
            'method'=>strtoupper($method),
            'header'=>implode("\r\n",$headers)."\r\n",
            'content'=>$body ?? '',
            'timeout'=>25,
            'ignore_errors'=>true,
        ]];
        $raw = @file_get_contents($url, false, stream_context_create($opts));
        $code = 0;
        if (isset($http_response_header) && is_array($http_response_header) && preg_match('/\s(\d{3})\s/', (string)($http_response_header[0]??''), $m)) {
            $code = (int)$m[1];
        }
        if ($raw === false) return ['ok'=>false,'error'=>'core_transport_error','http_status'=>$code];
    }

    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) return ['ok'=>false,'error'=>'core_invalid_response','http_status'=>$code];
    $decoded['_http_status'] = $code;
    return $decoded;
}

function ba_core_health(): array {
    return ba_core_request('GET','/v1/bookauthor/health');
}

function ba_core_submit(string $action, string $sourceUrl, string $sourceKind, int $projectId, int $sourceId): array {
    return ba_core_request('POST','/v1/bookauthor/submit',[
        'action'=>$action,
        'source_url'=>$sourceUrl,
        'source_kind'=>$sourceKind,
        'project_id'=>$projectId,
        'source_id'=>$sourceId,
        'target'=>'NEW',
        'priority'=>80,
    ]);
}

function ba_core_status(string $jobId): array {
    return ba_core_request('GET','/v1/bookauthor/status?job_id='.rawurlencode($jobId));
}
