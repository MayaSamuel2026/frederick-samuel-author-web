<?php
declare(strict_types=1);

const BOOK_AUTHOR_CORE_URL = 'https://noeva-core.179-198-203-247.nip.io';
const BOOK_AUTHOR_BRIDGE_TOKEN = '5Tw7okPTJbLHNfHgmvv1n5nQcjaYclgbEKfoNg0nIYswADkCPPirFsVRLAv_zg3s';

function ba_core_request(string $method, string $path, ?array $payload=null): array {
    $url = rtrim(BOOK_AUTHOR_CORE_URL, '/') . '/' . ltrim($path, '/');
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-Book-Author-Bridge: ' . BOOK_AUTHOR_BRIDGE_TOKEN,
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
        'target'=>'ANY',
        'priority'=>80,
    ]);
}

function ba_core_status(string $jobId): array {
    return ba_core_request('GET','/v1/bookauthor/status?job_id='.rawurlencode($jobId));
}
