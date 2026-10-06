<?php
declare(strict_types=1);
require_once __DIR__.'/src/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, private');

$token=trim((string)($_GET['token']??''));
$offer=null;$error='';
$browserLanguage=mb_strtolower(substr((string)($_SERVER['HTTP_ACCEPT_LANGUAGE']??'de'),0,2));
$language=array_key_exists($browserLanguage,OfferService::LANGUAGES)?$browserLanguage:'de';
$isPost=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
try{
    if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new RuntimeException('invalid_token');
    $offer=OfferService::publicByToken($token,!$isPost);
    $language=(string)($offer['language']??$language);
    if($isPost){
        $csrf=(string)($_POST['_csrf']??'');
        if(!hash_equals($_SESSION['csrf']??'', $csrf))throw new RuntimeException('csrf');
        OfferService::publicDecision($token,(string)($_POST['decision']??''));
        header('Location: angebot.php?token='.rawurlencode($token),true,303);
        exit;
    }
}catch(Throwable $e){
    $error=$e->getMessage();
    if($offer){
        try{$offer=OfferService::publicByToken($token,false);$language=(string)($offer['language']??$language);}catch(Throwable){}
    }
    try{AppLogger::info('Öffentlicher Angebotsaufruf fehlgeschlagen',['reason'=>$error,'token_prefix'=>substr($token,0,8)],'offer-public');}catch(Throwable){}
}
if(!$offer):
$fallbackDictionary=OfferService::dictionary($language);
?><!doctype html><html lang="<?=e($language)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title><?=e($fallbackDictionary['offer']??'Offer')?></title><style>body{margin:0;background:#f1f5f9;color:#172033;font-family:Arial,sans-serif}.box{max-width:620px;margin:10vh auto;background:#fff;padding:32px;border-radius:16px;box-shadow:0 12px 40px rgba(15,23,42,.12)}h1{margin-top:0}.error{padding:14px;border-radius:10px;background:#fef2f2;color:#991b1b}</style></head><body><main class="box"><h1><?=e($fallbackDictionary['offer']??'Offer')?></h1><div class="error"><?=e($fallbackDictionary['invalid_offer']??'The offer could not be loaded.')?></div></main></body></html><?php exit; endif;

$dictionary=OfferService::dictionary($language);
$status=(string)$offer['status'];
$controls=$error!==''?'<section class="offer-response declined">'.e($dictionary['action_error']??'The action could not be completed.').'</section>':'';
if(in_array($status,['sent','viewed'],true)){
    $controls='<section class="offer-response"><h2>'.e($dictionary['decision_prompt']??'').'</h2><div class="offer-response-buttons sp-public-actions">'
      .'<form method="post"><input type="hidden" name="_csrf" value="'.e(csrf_token()).'"><input type="hidden" name="decision" value="accept"><button class="accept" type="submit" data-confirm="'.e($dictionary['accept_confirm']??'').'">✓ '.e($dictionary['accept']??'').'</button></form>'
      .'<form method="post"><input type="hidden" name="_csrf" value="'.e(csrf_token()).'"><input type="hidden" name="decision" value="decline"><button class="decline" type="submit" data-confirm="'.e($dictionary['decline_confirm']??'').'">× '.e($dictionary['decline']??'').'</button></form>'
      .'</div></section>';
}elseif($status==='accepted'){
    $controls='<section class="offer-response success">✓ '.e($dictionary['accepted_message']??'').'</section>';
}elseif($status==='declined'){
    $controls='<section class="offer-response declined">'.e($dictionary['declined_message']??'').'</section>';
}elseif($status==='expired'){
    $controls='<section class="offer-response expired">'.e($dictionary['expired_message']??'').'</section>';
}elseif($status==='draft'){
    $controls='<section class="offer-response preview">'.e($dictionary['draft_preview']??'').'</section>';
}elseif($status==='converted'){
    $controls='<section class="offer-response success">✓ '.e($dictionary['converted_message']??'').'</section>';
}
$controls.='<div class="offer-print"><button type="button" data-print-offer>🖨️ '.e($dictionary['print']??'Print').'</button></div>';
$document=OfferService::render($offer,false,'public')['html'];
$extraCss='<style>.offer-response{margin:24px 0;padding:18px;border-radius:12px;background:#eff6ff;border:1px solid #bfdbfe;text-align:center}.offer-response h2{font-size:18px;margin:0 0 14px}.offer-response-buttons{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}.offer-response form{margin:0}.offer-response button,.offer-print button{border:0;border-radius:10px;padding:13px 22px;font-weight:700;cursor:pointer;font-size:15px}.offer-response .accept{background:#15803d;color:#fff}.offer-response .decline{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}.offer-response.success{background:#ecfdf5;border-color:#a7f3d0;color:#166534;font-weight:700}.offer-response.declined,.offer-response.expired{background:#fff1f2;border-color:#fecdd3;color:#9f1239}.offer-response.preview{background:#fffbeb;border-color:#fde68a;color:#92400e}.offer-print{text-align:center;margin:18px 0}.offer-print button{background:#e2e8f0;color:#172033}@media print{.offer-response,.offer-print{display:none!important}.wrap{box-shadow:none;margin:0;max-width:none}}@media(max-width:520px){.offer-response-buttons,.offer-response-buttons form,.offer-response-buttons button{width:100%}}</style>';
$document=str_replace('</head>',$extraCss.'</head>',$document);
$placeholderPattern='~<div data-sp-offer-controls>.*?</div>\s*</div>~s';
if(preg_match($placeholderPattern,$document))$document=preg_replace($placeholderPattern,$controls,$document,1)??$document;
elseif(str_contains($document,'<div data-sp-offer-controls>'))$document=str_replace('<div data-sp-offer-controls></div>',$controls,$document);
else{
    $anchor='<section class="sp-offer-terms"';
    if(str_contains($document,$anchor))$document=str_replace($anchor,$controls.$anchor,$document);
    else $document=str_replace('</main>',$controls.'</main>',$document);
}
$script='<script>document.querySelectorAll("[data-confirm]").forEach(function(button){button.addEventListener("click",function(event){if(!window.confirm(button.dataset.confirm||""))event.preventDefault();});});document.querySelector("[data-print-offer]")?.addEventListener("click",function(){window.print();});</script>';
$document=str_replace('</body>',$script.'</body>',$document);
echo $document;
