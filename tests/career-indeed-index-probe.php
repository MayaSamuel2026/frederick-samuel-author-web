<?php
declare(strict_types=1);
/** Diagnostic only: independently inspect search-engine RSS results. No Indeed HTTP requests. */
$queries=[
  'site:de.indeed.com/viewjob "Regionalleiter" "Pharma"',
  'site:de.indeed.com/viewjob "Sales Director" pharma',
];
foreach($queries as $query){
  $url='https://www.bing.com/search?'.http_build_query(['q'=>$query,'format'=>'rss','cc'=>'de','setlang'=>'de-DE']);
  $c=curl_init($url);
  curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>14,
    CURLOPT_FOLLOWLOCATION=>false,CURLOPT_USERAGENT=>'FrederickCareerPublicIndexProbe/1.0 (+https://fredericksamuel.com)',
    CURLOPT_HTTPHEADER=>['Accept: application/rss+xml,application/xml,text/xml'],CURLOPT_ENCODING=>'']);
  $body=curl_exec($c);$code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);$err=curl_error($c);curl_close($c);
  echo "QUERY: $query | HTTP $code | bytes=".strlen(is_string($body)?$body:'').($err?" | error=$err":'')."\n";
  if(!is_string($body)||$code!==200)continue;
  $doc=new DOMDocument();$previous=libxml_use_internal_errors(true);
  $ok=$doc->loadXML($body,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
  libxml_clear_errors();libxml_use_internal_errors($previous);
  if(!$ok){echo "Non-XML response\n";continue;}
  $all=$doc->getElementsByTagName('item');$verified=0;
  foreach($all as $item){
    $title=trim($item->getElementsByTagName('title')->item(0)?->textContent??'');
    $link=trim($item->getElementsByTagName('link')->item(0)?->textContent??'');
    $host=strtolower((string)parse_url($link,PHP_URL_HOST));
    if(!in_array($host,['de.indeed.com','www.indeed.com','indeed.com'],true))continue;
    if(!preg_match('~[?&](?:jk|vjk)=([a-z0-9]{8,})~i',$link))continue;
    $verified++;
    echo "INDEXED: ".mb_substr($title,0,100)." | ".mb_substr($link,0,190)."\n";
    if($verified>=5)break;
  }
  echo "RSS items=".$all->length."; direct Indeed job links=$verified\n";
}
