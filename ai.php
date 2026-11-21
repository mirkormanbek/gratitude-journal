<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized'], JSON_UNESCAPED_UNICODE); exit; }
date_default_timezone_set((string)($config['timezone'] ?? 'Asia/Almaty'));
function jout(array $x, int $status=200): void { http_response_code($status); echo json_encode($x, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function bodyJson(): array { $raw=file_get_contents('php://input'); $x=json_decode($raw ?: '{}',true); return is_array($x)?$x:[]; }
function requireCsrf(): void { $sent=$_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''; $expected=$_SESSION['csrf'] ?? ''; if(!$sent || !$expected || !hash_equals($expected,$sent)) jout(['ok'=>false,'error'=>'csrf'],403); }
function validDate(string $s): bool { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$s); return $d && $d->format('Y-m-d')===$s; }
function aiRateLimit(): void { $now=time(); $calls=$_SESSION['ai_call_times'] ?? []; if(!is_array($calls))$calls=[]; $calls=array_values(array_filter($calls,function($t) use($now){return is_int($t)&&$t>$now-3600;})); if(count($calls)>=20)jout(['ok'=>false,'error'=>'Лимит AI: не более 20 генераций в час.'],429); $calls[]=$now; $_SESSION['ai_call_times']=$calls; }
function extractOutputText(array $data): string { $parts=[]; foreach(($data['output'] ?? []) as $item){ if(($item['type'] ?? '')!=='message')continue; foreach(($item['content'] ?? []) as $part){ if(($part['type'] ?? '')==='output_text' && isset($part['text']))$parts[]=(string)$part['text']; }} return trim(implode("\n",$parts)); }
function openaiText(array $config,string $prompt): string { $key=trim((string)($config['openai_api_key'] ?? '')); $model=trim((string)($config['openai_model'] ?? 'gpt-5.6-luna')); if($key==='')throw new RuntimeException('В config.php не настроен openai_api_key.'); $payload=['model'=>$model,'input'=>$prompt]; $ch=curl_init('https://api.openai.com/v1/responses'); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>70,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]); $raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch); if($raw===false||$err)throw new RuntimeException('Ошибка OpenAI: '.$err); $data=json_decode($raw,true); if($http<200||$http>=300){$msg=is_array($data)?($data['error']['message']??('HTTP '.$http)):('HTTP '.$http);throw new RuntimeException('OpenAI: '.$msg);} if(!is_array($data))throw new RuntimeException('Некорректный ответ OpenAI.'); $text=extractOutputText($data); if($text==='')throw new RuntimeException('OpenAI вернул пустой ответ.'); return $text; }
function parseJsonObject(string $text): array { $text=trim($text);$text=preg_replace('/^```(?:json)?\s*/iu','',$text);$text=preg_replace('/\s*```$/u','',$text);$x=json_decode($text,true);if(!is_array($x)){ $a=strpos($text,'{');$b=strrpos($text,'}');if($a!==false&&$b!==false&&$b>$a)$x=json_decode(substr($text,$a,$b-$a+1),true);} if(!is_array($x))throw new RuntimeException('Не удалось разобрать ответ AI.');return $x; }
function cleanText($v,int $max=8000): string { return mb_substr(trim((string)$v),0,$max); }
function streakFromDates(array $dates,string $today): int {
    $dates=array_values(array_unique(array_filter(array_map('strval',$dates))));rsort($dates,SORT_STRING);if(!$dates)return 0;
    $todayD=new DateTimeImmutable($today);$yesterday=$todayD->modify('-1 day')->format('Y-m-d');
    if($dates[0]!==$today && $dates[0]!==$yesterday)return 0;
    $expected=new DateTimeImmutable($dates[0]);$streak=0;$set=array_fill_keys($dates,true);
    while(isset($set[$expected->format('Y-m-d')])){$streak++;$expected=$expected->modify('-1 day');}
    return $streak;
}
function progressData(PDO $pdo,string $today): array {
    $q=$pdo->query("SELECT content_date,quran_ayah,quran_ayah_end FROM daily_content WHERE quran_completed_at IS NOT NULL ORDER BY content_date DESC")->fetchAll();
    $qDates=[];$qVerses=0;foreach($q as $r){$qDates[]=(string)$r['content_date'];$a=(int)($r['quran_ayah']??0);$b=(int)($r['quran_ayah_end']??$a);if($a>0&&$b>=$a)$qVerses+=($b-$a+1);} $qVerses=min(6236,$qVerses);
    $pDates=$pdo->query("SELECT content_date FROM daily_content WHERE person_completed_at IS NOT NULL ORDER BY content_date DESC")->fetchAll(PDO::FETCH_COLUMN);
    $pos=$pdo->query("SELECT surah_number,ayah_number FROM quran_progress WHERE id=1 LIMIT 1")->fetch()?:['surah_number'=>1,'ayah_number'=>1];
    return [
      'quran'=>['completed_readings'=>count($q),'completed_verses'=>$qVerses,'total_verses'=>6236,'percent'=>round(($qVerses/6236)*100,2),'streak'=>streakFromDates($qDates,$today),'next_surah'=>(int)$pos['surah_number'],'next_ayah'=>(int)$pos['ayah_number']],
      'person'=>['completed_people'=>count($pDates),'streak'=>streakFromDates($pDates,$today)]
    ];
}

$action=(string)($_GET['action'] ?? '');
try{
 if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')jout(['ok'=>false,'error'=>'method not allowed'],405); requireCsrf(); $x=bodyJson(); $date=(string)($x['date']??date('Y-m-d')); if(!validDate($date))throw new RuntimeException('Некорректная дата.');
 if($action==='person_day'){
   $s=$pdo->prepare("SELECT person_name,person_content,person_completed_at FROM daily_content WHERE content_date=? LIMIT 1");$s->execute([$date]);$row=$s->fetch();
   if($row && trim((string)$row['person_content'])!==''){ $p=json_decode((string)$row['person_content'],true); if(is_array($p)&&!empty($p['story'])&&!empty($p['contribution'])){$p['completed']=!empty($row['person_completed_at']);jout(['ok'=>true,'cached'=>true,'data'=>$p]);} }
   aiRateLimit(); $used=$pdo->query("SELECT person_name FROM daily_content WHERE person_name IS NOT NULL AND person_name<>'' ORDER BY content_date DESC LIMIT 90")->fetchAll(PDO::FETCH_COLUMN); $exclude=implode(', ',array_map('strval',$used?:[]));
   $prompt="Создай рубрику «Человек дня» для личного дневника. Цель — не урок продуктивности, а увлекательное знакомство с реально существовавшим человеком, который оставил заметный вклад в мире.\n".
   "Выбирай людей из науки, медицины, инженерии, бизнеса, спорта, искусства, литературы, образования, исследований, архитектуры и других созидательных областей. Чередуй эпохи, страны и уровень известности. Иногда можно выбирать мусульманских учёных, людей из Казахстана и Центральной Азии. Не выбирай действующих политиков.\n".
   "Не повторяй: ".($exclude!==''?$exclude:'нет').".\n\n".
   "История должна читаться как хорошая мини-биография: живо, конкретно, без мотивационных штампов. Объясни контекст эпохи, что человек сделал, как к этому пришёл и почему его вклад важен. Добавь одну проверяемую интересную деталь. Не придумывай цитаты, диалоги, мотивы или точные детали, в которых не уверен. Если факт спорный — не используй его.\n".
   "Верни ТОЛЬКО JSON без markdown: {\"name\":\"\",\"field\":\"\",\"teaser\":\"\",\"intro\":\"\",\"story\":\"\",\"contribution\":\"\",\"interesting_fact\":\"\"}.\n".
   "teaser: 1-2 предложения для карточки. intro: 1 предложение с годами/местом только если уверен. story: примерно 450-700 слов на русском, 5-8 абзацев. contribution: 2-4 предложения. interesting_fact: 1-3 предложения.";
   $p=parseJsonObject(openaiText($config,$prompt)); foreach(['name','field','teaser','intro','story','contribution','interesting_fact'] as $k)$p[$k]=cleanText($p[$k]??'',12000); if($p['name']===''||$p['story']==='')throw new RuntimeException('AI не сформировал историю.');
   $json=json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); $pdo->prepare("INSERT INTO daily_content(content_date,person_name,person_content) VALUES(?,?,?) ON DUPLICATE KEY UPDATE person_name=VALUES(person_name),person_content=VALUES(person_content)")->execute([$date,$p['name'],$json]); $p['completed']=false; jout(['ok'=>true,'cached'=>false,'data'=>$p]);
 }
 if($action==='person_complete'){
   $s=$pdo->prepare("SELECT person_name,person_content FROM daily_content WHERE content_date=? LIMIT 1");$s->execute([$date]);$row=$s->fetch();if(!$row||trim((string)($row['person_name']??''))==='')throw new RuntimeException('Человек дня ещё не загружен.');
   $pdo->prepare("UPDATE daily_content SET person_completed_at=COALESCE(person_completed_at,NOW()) WHERE content_date=?")->execute([$date]);
   jout(['ok'=>true,'progress'=>progressData($pdo,$date)]);
 }
 if($action==='progress'){
   jout(['ok'=>true,'progress'=>progressData($pdo,$date)]);
 }
 if($action==='discuss'){
   aiRateLimit();$subject=(string)($x['subject']??'');$question=cleanText($x['question']??'',1800);if($question==='')throw new RuntimeException('Вопрос пустой.');$s=$pdo->prepare("SELECT * FROM daily_content WHERE content_date=? LIMIT 1");$s->execute([$date]);$row=$s->fetch()?:[];
   if($subject==='person'){ $pc=json_decode((string)($row['person_content']??''),true);if(!is_array($pc))throw new RuntimeException('Сначала загрузите человека дня.');$context=json_encode($pc,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$prompt="Вот карточка реального человека из дневника: {$context}\n\nВопрос пользователя: {$question}\n\nОтветь по-русски. Не выдумывай биографические детали или цитаты. Если вопрос выходит за факты карточки и ты не уверен, обозначь неопределённость."; }
   elseif($subject==='quran'){ $passage=cleanText($row['quran_passage_json']??'',24000);$translation=cleanText($row['quran_translation']??'',12000);$tafsir=cleanText($row['quran_explanation']??'',12000);if($passage===''&&$translation==='')throw new RuntimeException('Сначала загрузите чтение Корана.');$pj=json_decode($passage,true);$source=is_array($pj)?cleanText($pj['tafsir_source']??'подключённый тафсир',500):'подключённый тафсир';$prompt="Ты помогаешь с личным размышлением над Кораном. Не изменяй текст аятов и не выдавай собственный ответ за тафсир, фетву или позицию ҚМДБ.\nОтрывок JSON: {$passage}\n".($tafsir!==''?"Краткое русское изложение на основе источника {$source}: {$tafsir}\n":"Подключённое толкование в дневнике не загрузилось.\n")."Вопрос: {$question}\nОтветь кратко и бережно, опираясь на текст аятов и приведённое толкование, если оно есть. Если требуется религиозное постановление, предложи свериться с официальным источником ҚМДБ или имамом."; }
   else throw new RuntimeException('Неизвестная тема.'); jout(['ok'=>true,'answer'=>openaiText($config,$prompt)]);
 }
 if($action==='analysis'){
   aiRateLimit();$type=(string)($x['type']??'');if(!in_array($type,['today','week','ceo','capital'],true))throw new RuntimeException('Неизвестный тип анализа.');$to=new DateTimeImmutable($date);$from=$to;$context=[];$label='';
   if($type==='today'){ $label='сегодня';$s=$pdo->prepare("SELECT entry_date,allah,self_gratitude,lesson,trust_allah,tomorrow_action,money_action,focus_loss,mood FROM daily_entries WHERE entry_date=?");$s->execute([$date]);$context['daily']=$s->fetch()?:null; }
   elseif($type==='week'){ $label='последние 7 дней';$from=$to->modify('-6 days');$s=$pdo->prepare("SELECT entry_date,allah,self_gratitude,lesson,trust_allah,tomorrow_action,money_action,focus_loss,mood FROM daily_entries WHERE entry_date BETWEEN ? AND ? ORDER BY entry_date");$s->execute([$from->format('Y-m-d'),$date]);$context['daily']=$s->fetchAll(); }
   elseif($type==='ceo'){ $label='последний CEO Review';$s=$pdo->prepare("SELECT * FROM ceo_reviews WHERE review_date<=? ORDER BY review_date DESC LIMIT 1");$s->execute([$date]);$context['ceo']=$s->fetch()?:null; }
   else{ $label='последняя запись капитала';$s=$pdo->prepare("SELECT * FROM capital_entries WHERE entry_date<=? ORDER BY entry_date DESC LIMIT 1");$s->execute([$date]);$context['capital']=$s->fetch()?:null; }
   $cj=json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$prompt="Проанализируй только выбранные пользователем данные: {$label}. JSON: {$cj}\nНе выдумывай отсутствующие факты. Если данных мало — скажи. Ответ по-русски: 1) что видно 2) что сработало 3) что мешает 4) три следующих действия 5) один вопрос себе.";$analysis=openaiText($config,$prompt);$pdo->prepare("INSERT INTO ai_analyses(analysis_type,period_from,period_to,request_context,analysis) VALUES(?,?,?,?,?)")->execute([$type,$from->format('Y-m-d'),$to->format('Y-m-d'),$cj,$analysis]);jout(['ok'=>true,'analysis'=>$analysis]);
 }
 jout(['ok'=>false,'error'=>'unknown action'],400);
}catch(Throwable $e){jout(['ok'=>false,'error'=>$e->getMessage()],500);}