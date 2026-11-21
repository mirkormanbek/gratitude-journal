<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['user_id'])){http_response_code(401);echo json_encode(['ok'=>false,'error'=>'unauthorized'],JSON_UNESCAPED_UNICODE);exit;}
date_default_timezone_set((string)($config['timezone'] ?? 'Asia/Almaty'));

function qout(array $x,int $status=200): void {http_response_code($status);echo json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function qbody(): array {$raw=file_get_contents('php://input');$x=json_decode($raw?:'{}',true);return is_array($x)?$x:[];}
function qcsrf(): void {$sent=$_SERVER['HTTP_X_CSRF_TOKEN']??'';$expected=$_SESSION['csrf']??'';if(!$sent||!$expected||!hash_equals($expected,$sent))qout(['ok'=>false,'error'=>'csrf'],403);}
function qdate(string $s): bool {$d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);return $d&&$d->format('Y-m-d')===$s;}
function qcleanHtml(string $s): string {$s=preg_replace('/<sup\b[^>]*>.*?<\/sup>/isu','',$s);$s=str_replace(['<br>','<br/>','<br />'],"\n",$s);return trim(html_entity_decode(strip_tags($s),ENT_QUOTES|ENT_HTML5,'UTF-8'));}
function qcleanTranslation(string $s): string {
    $s=qcleanHtml($s);
    // Некоторые русские ресурсы Quran Foundation добавляют после перевода большой комментарий в [[...]].
    $pos=strpos($s,'[['); if($pos!==false)$s=trim(substr($s,0,$pos));
    $s=preg_replace('/\s+/u',' ',trim($s));
    return $s;
}
function qfBases(array $config): array {$env=trim((string)($config['quran_environment']??'prelive'));return $env==='production'?['production','https://oauth2.quran.foundation','https://apis.quran.foundation']:['prelive','https://prelive-oauth2.quran.foundation','https://apis-prelive.quran.foundation'];}

function qfToken(array $config,bool $force=false): string {
    list($env,$authBase,$apiBase)=qfBases($config);
    if(!$force&&!empty($_SESSION['qf_token'])&&!empty($_SESSION['qf_token_exp'])&&(int)$_SESSION['qf_token_exp']>time()+90)return (string)$_SESSION['qf_token'];
    $id=trim((string)($config['quran_client_id']??''));$secret=trim((string)($config['quran_client_secret']??''));
    if($id===''||$secret==='')throw new RuntimeException('Не настроены Quran Foundation credentials.');
    $ch=curl_init($authBase.'/oauth2/token');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_USERPWD=>$id.':'.$secret,CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Accept: application/json'],CURLOPT_POSTFIELDS=>http_build_query(['grant_type'=>'client_credentials','scope'=>'content'])]);
    $raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
    if($raw===false||$err)throw new RuntimeException('Quran OAuth: '.$err);
    $j=json_decode($raw,true);
    if($http<200||$http>=300||!is_array($j)||empty($j['access_token'])){$m=is_array($j)?($j['error_description']??$j['message']??$j['error']??('HTTP '.$http)):('HTTP '.$http);throw new RuntimeException('Quran OAuth: '.$m);}
    $_SESSION['qf_token']=(string)$j['access_token'];$_SESSION['qf_token_exp']=time()+(int)($j['expires_in']??3500);
    return (string)$j['access_token'];
}

function qfGet(array $config,string $path,array $query=[]): array {
    list($env,$authBase,$apiBase)=qfBases($config);$id=trim((string)($config['quran_client_id']??''));
    $run=function($token)use($apiBase,$path,$query,$id){
        $url=$apiBase.'/content/api/v4'.$path.($query?'?'.http_build_query($query):'');
        $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>45,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_HTTPHEADER=>['Accept: application/json','x-auth-token: '.$token,'x-client-id: '.$id]]);
        $raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);return[$http,$raw,$err];
    };
    list($http,$raw,$err)=$run(qfToken($config));
    if($http===401){unset($_SESSION['qf_token'],$_SESSION['qf_token_exp']);list($http,$raw,$err)=$run(qfToken($config,true));}
    if($raw===false||$err)throw new RuntimeException('Quran API: '.$err);
    $j=json_decode($raw,true);
    if($http<200||$http>=300||!is_array($j)){$m=is_array($j)?($j['message']??$j['error']??('HTTP '.$http)):('HTTP '.$http);throw new RuntimeException('Quran API: '.$m);}
    return $j;
}

function qfChapters(array $config): array {
    if(!empty($_SESSION['qf_chapters_v42'])&&is_array($_SESSION['qf_chapters_v42']))return $_SESSION['qf_chapters_v42'];
    $j=qfGet($config,'/chapters',['language'=>'ru']);$c=$j['chapters']??[];if(!is_array($c))$c=[];$_SESSION['qf_chapters_v42']=$c;return $c;
}

function qfRussianTranslation(array $config): array {
    $override=(int)($config['quran_translation_id']??0);if($override>0)return[$override,'Resource '.$override];
    if(!empty($_SESSION['qf_ru_translation_id_v42']))return[(int)$_SESSION['qf_ru_translation_id_v42'],(string)($_SESSION['qf_ru_translation_name_v42']??'')];
    $j=qfGet($config,'/resources/translations',['language'=>'ru']);$list=$j['translations']??[];if(!is_array($list)||!$list)throw new RuntimeException('Не найден русский перевод.');
    $pick=null;$fallback=null;
    foreach($list as $r){
        $name=mb_strtolower((string)(($r['name']??'').' '.($r['author_name']??'').' '.($r['translated_name']['name']??'')));
        $lang=mb_strtolower((string)($r['language_name']??''));
        if(strpos($lang,'russian')===false&&strpos($lang,'рус')===false)continue;
        if(!$fallback)$fallback=$r;
        if(strpos($name,'kuliev')!==false||strpos($name,'кулиев')!==false||strpos($name,'elmir')!==false){$pick=$r;break;}
        if($pick===null&&strpos($name,'awqaf')===false&&strpos($name,'ministry')===false&&strpos($name,'egypt')===false)$pick=$r;
    }
    if(!$pick)$pick=$fallback?:$list[0];
    $id=(int)($pick['id']??$pick['resource_id']??0);if($id<=0)throw new RuntimeException('Не удалось определить русский перевод.');
    $name=(string)($pick['name']??$pick['translated_name']['name']??$pick['author_name']??('Resource '.$id));
    $_SESSION['qf_ru_translation_id_v42']=$id;$_SESSION['qf_ru_translation_name_v42']=$name;
    return[$id,$name];
}

function chapterInfo(array $config,int $surah): array {
    $count=0;$name='Сура '.$surah;
    foreach(qfChapters($config) as $c){if((int)($c['id']??0)===$surah){$count=(int)($c['verses_count']??0);$simple=(string)($c['name_simple']??'');$translated=(string)($c['translated_name']['name']??'');$name=$simple?:($translated?:$name);break;}}
    if($surah===1)$name='Аль-Фатиха';if($surah===2)$name='Аль-Бакара';
    return[$name,$count];
}

function readingEnd(int $surah,int $start,int $count): int {
    if($surah===1&&$start===1)return min(7,$count); // Аль-Фатиха всегда целиком
    if($count<=12&&$start===1)return$count;
    if($surah===2){$ends=[7,20,29,39,46,59,66,74,82,86,96,103,112,121,129,141,152,162,176,182,187,195,203,214,220,225,232,242,252,260,274,281,283,286];foreach($ends as $e)if($e>=$start)return min($e,$count);}
    return min($count,$start+4);
}

function qfSelectTafsirResource(array $config): array {
    $override=(int)($config['quran_tafsir_id']??0);
    if($override>0)return[$override,'Tafsir resource '.$override];
    if(!empty($_SESSION['qf_tafsir_id_v43']))return[(int)$_SESSION['qf_tafsir_id_v43'],(string)($_SESSION['qf_tafsir_name_v43']??'Tafsir')];
    $j=qfGet($config,'/resources/tafsirs',['language'=>'en']);
    $list=$j['tafsirs']??[];if(!is_array($list)||!$list)throw new RuntimeException('Quran Foundation не вернул список доступных тафсиров.');
    $priorities=["ma'arif",'maarif','ma arif','usmani','uthmani','jalalayn','ibn kathir'];
    $pick=null;
    foreach($priorities as $needle){
        foreach($list as $r){
            $hay=mb_strtolower((string)(($r['name']??'').' '.($r['author_name']??'').' '.($r['slug']??'').' '.($r['translated_name']['name']??'')));
            if(strpos($hay,$needle)!==false){$pick=$r;break 2;}
        }
    }
    if(!$pick)$pick=$list[0];
    $id=(int)($pick['id']??0);if($id<=0)throw new RuntimeException('Не удалось определить доступный тафсир.');
    $name=(string)($pick['name']??$pick['translated_name']['name']??('Tafsir '.$id));
    $_SESSION['qf_tafsir_id_v43']=$id;$_SESSION['qf_tafsir_name_v43']=$name;
    return[$id,$name];
}

function qfSingleVerse(array $config,int $surah,int $ayah,int $ruId,string &$ruName): array {
    $key=$surah.':'.$ayah;
    $j=qfGet($config,'/verses/by_key/'.rawurlencode($key),['language'=>'ru','words'=>'true','translations'=>$ruId,'fields'=>'text_uthmani','translation_fields'=>'resource_name,language_name']);
    $v=$j['verse']??null;if(!is_array($v))throw new RuntimeException('Не найден аят '.$key.'.');
    $translation='';
    foreach(($v['translations']??[]) as $t){if((int)($t['resource_id']??0)===$ruId){$translation=qcleanTranslation((string)($t['text']??''));if(!empty($t['resource_name']))$ruName=(string)$t['resource_name'];break;}}
    $bits=[];foreach(($v['words']??[]) as $w){$t=trim((string)($w['transliteration']['text']??''));if($t!=='')$bits[]=$t;}
    return['verse_key'=>(string)($v['verse_key']??$key),'arabic'=>(string)($v['text_uthmani']??''),'transliteration'=>implode(' ',$bits),'translation'=>$translation];
}

function qfPassage(array $config,int $surah,int $start,int $end): array {
    list($env)=qfBases($config);if($env==='prelive'&&$surah>2)throw new RuntimeException('Prelive Quran Foundation содержит только суры 1–2. Для продолжения нужен Production access.');
    list($ruId,$ruName)=qfRussianTranslation($config);$verses=[];
    // Получаем каждый аят по ключу. Это медленнее один раз в день, зато не теряет последний аят из-за пагинации.
    for($n=$start;$n<=$end;$n++)$verses[]=qfSingleVerse($config,$surah,$n,$ruId,$ruName);
    if(count($verses)!==($end-$start+1))throw new RuntimeException('Получено не полное количество аятов.');
    $words=0;foreach($verses as $v){$words+=count(preg_split('/\s+/u',trim($v['transliteration']),-1,PREG_SPLIT_NO_EMPTY));$words+=count(preg_split('/\s+/u',trim($v['translation']),-1,PREG_SPLIT_NO_EMPTY));}
    $minutes=max(5,min(10,(int)round($words/45)));
    return[$verses,$ruName,$minutes];
}

function qfTafsirPassage(array $config,int $surah,int $start,int $end): array {
    list($resourceId,$sourceName)=qfSelectTafsirResource($config);
    $unique=[];
    for($n=$start;$n<=$end;$n++){
        $key=$surah.':'.$n;$text='';
        try{
            $j=qfGet($config,'/tafsirs/'.$resourceId.'/by_ayah/'.rawurlencode($key),['fields'=>'resource_name,language_name']);
            $t=$j['tafsir']??null;
            if(is_array($t)){
                if(!empty($t['resource_name']))$sourceName=(string)$t['resource_name'];
                $text=qcleanHtml((string)($t['text']??''));
            }
        }catch(Throwable $e){}
        if($text===''){
            try{
                $j=qfGet($config,'/verses/by_key/'.rawurlencode($key),[
                    'language'=>'en','words'=>'false','tafsirs'=>$resourceId,
                    'tafsir_fields'=>'resource_name,language_name'
                ]);
                $v=$j['verse']??null;
                if(is_array($v))foreach(($v['tafsirs']??[]) as $t){
                    if((int)($t['resource_id']??$resourceId)!==$resourceId)continue;
                    if(!empty($t['resource_name']))$sourceName=(string)$t['resource_name'];
                    if(!empty($t['name']))$sourceName=(string)$t['name'];
                    $text=qcleanHtml((string)($t['text']??''));
                    if($text!=='')break;
                }
            }catch(Throwable $e){}
        }
        if($text==='')continue;
        $hash=md5(preg_replace('/\s+/u',' ',mb_strtolower($text)));
        if(!isset($unique[$hash]))$unique[$hash]=$text;
    }
    return[implode("\n\n",array_values($unique)),$sourceName];
}

function qExtractOutputText(array $data): string {
    $parts=[];foreach(($data['output']??[]) as $item){if(($item['type']??'')!=='message')continue;foreach(($item['content']??[]) as $part){if(($part['type']??'')==='output_text'&&isset($part['text']))$parts[]=(string)$part['text'];}}
    return trim(implode("\n",$parts));
}

function qOpenAI(array $config,string $prompt): string {
    $key=trim((string)($config['openai_api_key']??''));$model=trim((string)($config['openai_model']??''));
    if($key===''||$model==='')throw new RuntimeException('OpenAI для перевода толкования не настроен.');
    $payload=['model'=>$model,'input'=>$prompt];$ch=curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>80,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    $raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
    if($raw===false||$err)throw new RuntimeException('OpenAI: '.$err);$j=json_decode($raw,true);
    if($http<200||$http>=300){$m=is_array($j)?($j['error']['message']??('HTTP '.$http)):('HTTP '.$http);throw new RuntimeException('OpenAI: '.$m);}if(!is_array($j))throw new RuntimeException('Некорректный ответ OpenAI.');
    $text=qExtractOutputText($j);if($text==='')throw new RuntimeException('Пустой ответ OpenAI.');return $text;
}

function qParseJsonObject(string $text): array {
    $text=trim($text);$text=preg_replace('/^```(?:json)?\s*/iu','',$text);$text=preg_replace('/\s*```$/u','',$text);
    $x=json_decode($text,true);
    if(!is_array($x)){$a=strpos($text,'{');$b=strrpos($text,'}');if($a!==false&&$b!==false&&$b>$a)$x=json_decode(substr($text,$a,$b-$a+1),true);}
    return is_array($x)?$x:[];
}
function qShortenText(string $text,int $max=1200): string {
    $text=trim($text);if(mb_strlen($text)<=$max)return $text;
    $cut=mb_substr($text,0,$max);$p=mb_strrpos($cut,'.');if($p!==false&&$p>$max*0.55)$cut=mb_substr($cut,0,$p+1);
    return trim($cut);
}
function russianTafsirRender(array $config,string $raw,string $sourceName,int $surah,int $start,int $end): array {
    if(trim($raw)==='')return['',''];
    $raw=mb_substr($raw,0,30000);$range=$surah.':'.$start.($end!==$start?'–'.$end:'');
    $prompt="Ниже дан текст исламского тафсира {$sourceName} для отрывка Корана {$range}.\n\n".
        "Сделай два русских изложения СТРОГО на основе источника. Не добавляй собственных толкований, фетв, историй, цитат или богословских выводов, которых нет в источнике. Не называй результат официальным толкованием КМДБ. Сохрани важные оговорки и правовые пояснения источника, если они есть.\n\n".
        "Верни ТОЛЬКО валидный JSON без markdown: {\"short\":\"\",\"full\":\"\"}. short — 5–8 ясных предложений, примерно 700–1200 знаков, чтобы прочитать за минуту. full — 4–7 коротких абзацев, примерно 1800–3200 знаков, более подробное изложение того же источника.\n\nИсточник:\n".$raw;
    $answer=qOpenAI($config,$prompt);$j=qParseJsonObject($answer);
    $short=trim((string)($j['short']??''));$full=trim((string)($j['full']??''));
    if($full==='')$full=trim($answer);if($short==='')$short=qShortenText($full,1100);
    return[$short,$full];
}

function qrow(PDO $pdo,string $date){$s=$pdo->prepare("SELECT * FROM daily_content WHERE content_date=? LIMIT 1");$s->execute([$date]);$r=$s->fetch();return$r?:null;}
function passageSummary(int $surah,int $start,int $end): string {if($surah===1)return'Аль-Фатиха целиком — семь аятов Открывающей суры.';if($surah===2&&$start===1&&$end===7)return'Начало Аль-Бакара: Коран как руководство, качества верующих и первые противопоставления.';if($surah===2&&$start===8&&$end===20)return'Развёрнутый отрывок о лицемерии и внутренней двойственности.';return'Последовательный смысловой отрывок из суры — читай в контексте соседних аятов.';}

function dataFromRow(array $r,array $config): array {
    list($env)=qfBases($config);$pass=json_decode((string)($r['quran_passage_json']??''),true);if(!is_array($pass))$pass=[];
    $sur=(int)($r['quran_surah']??0);$start=(int)($r['quran_ayah']??0);$end=(int)($r['quran_ayah_end']??$start);list($name)=chapterInfo($config,$sur);
    $verses=$pass['verses']??[];if(!is_array($verses))$verses=[];
    foreach($verses as &$v){if(is_array($v))$v['translation']=qcleanTranslation((string)($v['translation']??''));}unset($v);
    $short=(string)($r['quran_explanation']??'');$full=(string)($pass['tafsir_full_ru']??'');if($full===''&&$short!=='')$full=$short;
    return['surah'=>$sur,'start_ayah'=>$start,'end_ayah'=>$end,'chapter_name'=>$name,'verses'=>$verses,'translation_source'=>$pass['translation_source']??'Quran Foundation','reading_minutes'=>$pass['reading_minutes']??5,'verse_count'=>max(1,$end-$start+1),'summary'=>$pass['summary']??passageSummary($sur,$start,$end),'tafsir'=>$short,'tafsir_full'=>$full,'tafsir_source'=>$pass['tafsir_source']??'доступный тафсир','takeaway'=>(string)($r['quran_takeaway']??''),'completed'=>trim((string)($r['quran_completed_at']??''))!=='','environment'=>$env];
}

function buildAndStorePassage(PDO $pdo,array $config,string $date,int $sur,int $start,int $end): array {
    list($verses,$source,$minutes)=qfPassage($config,$sur,$start,$end);
    $tafsir='';$tafsirFull='';$tafsirSource='доступный тафсир';
    try{list($rawTafsir,$tafsirSource)=qfTafsirPassage($config,$sur,$start,$end);if($rawTafsir!=='')list($tafsir,$tafsirFull)=russianTafsirRender($config,$rawTafsir,$tafsirSource,$sur,$start,$end);}catch(Throwable $e){$tafsir='';$tafsirFull='';}
    $pass=['verses'=>$verses,'translation_source'=>$source,'reading_minutes'=>$minutes,'summary'=>passageSummary($sur,$start,$end),'tafsir_source'=>$tafsirSource,'tafsir_full_ru'=>$tafsirFull,'tafsir_render_version'=>'v44'];
    $arabic=implode("\n",array_column($verses,'arabic'));$translit=implode("\n",array_column($verses,'transliteration'));$translation=implode("\n",array_column($verses,'translation'));$json=json_encode($pass,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $sql="INSERT INTO daily_content(content_date,quran_surah,quran_ayah,quran_ayah_end,quran_arabic,quran_transliteration,quran_translation,quran_passage_json,quran_explanation) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE quran_surah=VALUES(quran_surah),quran_ayah=VALUES(quran_ayah),quran_ayah_end=VALUES(quran_ayah_end),quran_arabic=VALUES(quran_arabic),quran_transliteration=VALUES(quran_transliteration),quran_translation=VALUES(quran_translation),quran_passage_json=VALUES(quran_passage_json),quran_explanation=VALUES(quran_explanation)";
    $pdo->prepare($sql)->execute([$date,$sur,$start,$end,$arabic,$translit,$translation,$json,$tafsir]);
    $row=qrow($pdo,$date);return$row?:[];
}

$action=(string)($_GET['action']??'');
try{
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')qout(['ok'=>false,'error'=>'method not allowed'],405);qcsrf();$x=qbody();$date=(string)($x['date']??date('Y-m-d'));if(!qdate($date))throw new RuntimeException('Некорректная дата.');

    if($action==='today'){
        $row=qrow($pdo,$date);
        if($row && (int)($row['quran_surah']??0)===1){
            $cached0=json_decode((string)($row['quran_passage_json']??''),true);
            $actual0=is_array($cached0)&&isset($cached0['verses'])&&is_array($cached0['verses'])?count($cached0['verses']):0;
            $start0=(int)($row['quran_ayah']??0);$end0=(int)($row['quran_ayah_end']??0);
            if($start0!==1||$end0!==7||$actual0!==7){
                $pdo->prepare("UPDATE quran_progress SET surah_number=1,ayah_number=1 WHERE id=1 AND surah_number=1")->execute();
                $pdo->prepare("UPDATE daily_content SET quran_takeaway='',quran_completed_at=NULL WHERE content_date=?")->execute([$date]);
                $row=buildAndStorePassage($pdo,$config,$date,1,1,7);
                qout(['ok'=>true,'cached'=>false,'repaired_fatiha'=>true,'data'=>dataFromRow($row,$config)]);
            }
        }
        if($row){
            $cached=json_decode((string)($row['quran_passage_json']??''),true);$start=(int)($row['quran_ayah']??0);$end=(int)($row['quran_ayah_end']??0);$expected=$end>=$start&&$start>0?($end-$start+1):0;$actual=is_array($cached)&&isset($cached['verses'])&&is_array($cached['verses'])?count($cached['verses']):0;
            $renderOk=is_array($cached)&&(($cached['tafsir_render_version']??'')==='v44')&&trim((string)($row['quran_explanation']??''))!=='';
            if($expected>0&&$actual===$expected&&$renderOk)qout(['ok'=>true,'cached'=>true,'data'=>dataFromRow($row,$config)]);
            if($expected>0&&$actual===$expected){
                try{
                    list($rawTafsir,$tafsirSource)=qfTafsirPassage($config,(int)$row['quran_surah'],$start,$end);$tafsir='';$tafsirFull='';
                    if($rawTafsir!=='')list($tafsir,$tafsirFull)=russianTafsirRender($config,$rawTafsir,$tafsirSource,(int)$row['quran_surah'],$start,$end);
                    $cached['tafsir_source']=$tafsirSource;$cached['tafsir_full_ru']=$tafsirFull;$cached['tafsir_render_version']='v44';
                    $pdo->prepare("UPDATE daily_content SET quran_explanation=?,quran_passage_json=? WHERE content_date=?")->execute([$tafsir,json_encode($cached,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$date]);
                    $row=qrow($pdo,$date);qout(['ok'=>true,'cached'=>true,'data'=>dataFromRow($row?:[],$config)]);
                }catch(Throwable $e){qout(['ok'=>true,'cached'=>true,'data'=>dataFromRow($row,$config)]);}
            }
        }
        $p=$pdo->query("SELECT surah_number,ayah_number FROM quran_progress WHERE id=1 LIMIT 1")->fetch();if(!$p)throw new RuntimeException('Не найдена quran_progress.');$sur=(int)$p['surah_number'];$start=(int)$p['ayah_number'];list($name,$count)=chapterInfo($config,$sur);if($count<=0)throw new RuntimeException('Не удалось определить суру.');if($sur===1){$start=1;$end=7;$pdo->prepare("UPDATE quran_progress SET ayah_number=1 WHERE id=1 AND surah_number=1")->execute();}else{$end=readingEnd($sur,$start,$count);}
        // Если строка сегодняшнего дня есть, но кэш неполный, сохраняем тот же диапазон и пересобираем его.
        if($row&&((int)($row['quran_surah']??0))>0){$sur=(int)$row['quran_surah'];$start=(int)$row['quran_ayah'];$end=(int)($row['quran_ayah_end']??$start);if($sur===1){$start=1;$end=7;}}
        $row=buildAndStorePassage($pdo,$config,$date,$sur,$start,$end);qout(['ok'=>true,'cached'=>false,'data'=>dataFromRow($row,$config)]);
    }

    if($action==='complete'){
        $take=mb_substr(trim((string)($x['takeaway']??'')),0,6000);$sur=(int)($x['surah']??0);$start=(int)($x['start_ayah']??0);$end=(int)($x['end_ayah']??0);$row=qrow($pdo,$date);
        if(!$row||(int)$row['quran_surah']!==$sur||(int)$row['quran_ayah']!==$start||(int)$row['quran_ayah_end']!==$end)throw new RuntimeException('Отрывок дня не совпадает.');
        $pdo->prepare("UPDATE daily_content SET quran_takeaway=?,quran_completed_at=COALESCE(quran_completed_at,NOW()) WHERE content_date=?")->execute([$take,$date]);list($name,$count)=chapterInfo($config,$sur);$advanced=false;$pdo->beginTransaction();$p=$pdo->query("SELECT surah_number,ayah_number FROM quran_progress WHERE id=1 FOR UPDATE")->fetch();
        if($p&&(int)$p['surah_number']===$sur&&((int)$p['ayah_number']===$start||($sur===1&&$start===1&&$end===7))){if($end<$count){$ns=$sur;$na=$end+1;}else{$ns=$sur+1;$na=1;}if($ns<=114){$pdo->prepare("UPDATE quran_progress SET surah_number=?,ayah_number=? WHERE id=1")->execute([$ns,$na]);$advanced=true;}}
        $pdo->commit();qout(['ok'=>true,'advanced'=>$advanced]);
    }

    qout(['ok'=>false,'error'=>'unknown action'],400);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();qout(['ok'=>false,'error'=>$e->getMessage()],500);}
