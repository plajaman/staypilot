<?php
declare(strict_types=1);

/** V2.1.4 – Admin-API für den mehrsprachigen Frontend-Editor. */

function site_editor_data_v218(): never
{
    $language=PublicSiteService::language((string)($_GET['language']??'de'));
    $pages=db()->query('SELECT * FROM site_pages ORDER BY sort_order,title_fallback,slug')->fetchAll();
    $translationsStmt=db()->prepare('SELECT * FROM site_page_translations WHERE page_id=? ORDER BY language');
    $blocksStmt=db()->prepare('SELECT * FROM site_page_blocks WHERE page_id=? ORDER BY sort_order,id');
    $blockTranslationsStmt=db()->prepare('SELECT * FROM site_page_block_translations WHERE block_id=? ORDER BY language');
    foreach($pages as &$page){
        $translationsStmt->execute([(int)$page['id']]);
        $translations=[];foreach($translationsStmt->fetchAll() as $row)$translations[(string)$row['language']]=$row;
        $page['translations']=$translations;
        $blocksStmt->execute([(int)$page['id']]);$blocks=$blocksStmt->fetchAll();
        foreach($blocks as &$block){
            $block['settings']=json_decode((string)($block['settings_json']??''),true)?:[];
            $blockTranslationsStmt->execute([(int)$block['id']]);$blockTranslations=[];
            foreach($blockTranslationsStmt->fetchAll() as $row){$row['content']=json_decode((string)($row['content_json']??''),true)?:[];$blockTranslations[(string)$row['language']]=$row;}
            $block['translations']=$blockTranslations;
        }unset($block);
        $page['blocks']=$blocks;
        $active=$translations[$language]??$translations['de']??[];
        $page['display_title']=trim((string)($active['title']??''))?:$page['title_fallback'];
    }unset($page);
    json_response([
        'ok'=>true,'pages'=>$pages,'design'=>PublicSiteService::design(),'labels'=>site_all_labels_v218(),'media'=>SiteMediaService::all(),
        'languages'=>PublicSiteService::languages(),'block_types'=>PublicSiteService::BLOCK_TYPES,'selected_language'=>$language,
        'public_urls'=>['home'=>'../index.php','page'=>'../seite.php','type'=>'../wohnungstyp.php'],
    ]);
}

function save_site_page_v218(): never
{
    $d=request_data();$id=(int)($d['id']??0);$old=$id?site_page_row_v218($id):null;
    if($id&&!$old)throw new NotFoundException('Die Seite wurde nicht gefunden.');
    $protected=(bool)($old['protected']??false);
    $fallback=mb_substr(trim(strip_tags((string)($d['title_fallback']??''))),0,190);if($fallback==='')throw new ValidationException('Bitte einen internen Seitennamen eingeben.');
    $slug=site_slug_v218((string)($d['slug']??$fallback));
    if($protected&&$old)$slug=(string)$old['slug'];
    $status=in_array((string)($d['status']??'draft'),['draft','published'],true)?(string)$d['status']:'draft';
    $type=in_array((string)($d['page_type']??'standard'),['standard','home','types','contact','legal'],true)?(string)$d['page_type']:'standard';
    if($old&&$old['system_key'])$type=(string)$old['page_type'];
    $values=[$slug,$fallback,$type,$status,normalize_bool($d['show_header']??0),normalize_bool($d['show_footer']??0),max(-9999,min(9999,(int)($d['sort_order']??0))),Auth::user()['id']??null];
    $pdo=db();$pdo->beginTransaction();
    try{
        if($id){$pdo->prepare('UPDATE site_pages SET slug=?,title_fallback=?,page_type=?,status=?,show_header=?,show_footer=?,sort_order=?,updated_by=?,updated_at=NOW() WHERE id=?')->execute([...$values,$id]);}
        else{$pdo->prepare('INSERT INTO site_pages(slug,title_fallback,page_type,status,show_header,show_footer,sort_order,created_by,updated_by) VALUES(?,?,?,?,?,?,?,?,?)')->execute([...$values,Auth::user()['id']??null]);$id=(int)$pdo->lastInsertId();}
        $translations=is_array($d['translations']??null)?$d['translations']:[];
        $up=$pdo->prepare('INSERT INTO site_page_translations(page_id,language,title,navigation_label,seo_title,seo_description) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),navigation_label=VALUES(navigation_label),seo_title=VALUES(seo_title),seo_description=VALUES(seo_description)');
        foreach(PublicSiteService::languages() as $language=>$label){$tr=is_array($translations[$language]??null)?$translations[$language]:[];$up->execute([$id,$language,site_plain_v218($tr['title']??'',190),site_plain_v218($tr['navigation_label']??'',120),site_plain_v218($tr['seo_title']??'',190),site_plain_v218($tr['seo_description']??'',320)]);}
        if(!$old){
            $block=$pdo->prepare('INSERT INTO site_page_blocks(page_id,block_type,system_key,settings_json,active,locked,sort_order,created_by,updated_by) VALUES(?,?,?,?,1,0,10,?,?)');$block->execute([$id,'rich_text',null,'{}',Auth::user()['id']??null,Auth::user()['id']??null]);$blockId=(int)$pdo->lastInsertId();
            $blockTr=$pdo->prepare('INSERT INTO site_page_block_translations(block_id,language,title,content_json) VALUES(?,?,?,?)');foreach(PublicSiteService::languages() as $language=>$label)$blockTr->execute([$blockId,$language,'',json_encode(['title'=>'','content_html'=>'<p></p>'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        }
        $pdo->commit();
    }catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if((string)$e->getCode()==='23000')throw new ConflictException('Diese Seitenadresse ist bereits vergeben.');throw $e;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    $new=site_page_row_v218($id);AuditLogger::record('site_page',$id,$old?'update':'create',$old,$new,'Webseite gespeichert');
    json_response(['ok'=>true,'message'=>$status==='published'?'Seite gespeichert und veröffentlicht.':'Seitenentwurf gespeichert.','id'=>$id]);
}

function delete_site_page_v218(): never
{
    $id=(int)(request_data()['id']??0);$old=site_page_row_v218($id);if(!$old)throw new NotFoundException('Seite nicht gefunden.');
    if((int)$old['protected']===1||$old['system_key'])throw new ConflictException('Diese Systemseite kann nicht gelöscht werden. Sie kann als Entwurf ausgeblendet werden.');
    db()->prepare('DELETE FROM site_pages WHERE id=?')->execute([$id]);AuditLogger::record('site_page',$id,'delete',$old,null,'Webseite gelöscht');json_response(['ok'=>true,'message'=>'Seite gelöscht.']);
}

function duplicate_site_page_v218(): never
{
    $id=(int)(request_data()['id']??0);$source=site_page_row_v218($id);if(!$source)throw new NotFoundException('Ausgangsseite nicht gefunden.');
    $pdo=db();$pdo->beginTransaction();
    try{
        $slug=site_unique_slug_v218((string)$source['slug'].'-kopie');$title=(string)$source['title_fallback'].' – Kopie';
        $pdo->prepare("INSERT INTO site_pages(slug,title_fallback,page_type,status,show_header,show_footer,sort_order,protected,created_by,updated_by) VALUES(?,?,?,'draft',0,0,?,0,?,?)")->execute([$slug,$title,$source['page_type'],(int)$source['sort_order']+1,Auth::user()['id']??null,Auth::user()['id']??null]);$newId=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO site_page_translations(page_id,language,title,navigation_label,seo_title,seo_description) SELECT ?,language,CONCAT(COALESCE(title,?),?),navigation_label,seo_title,seo_description FROM site_page_translations WHERE page_id=?')->execute([$newId,$title,' – Kopie',$id]);
        $blocks=db()->prepare('SELECT * FROM site_page_blocks WHERE page_id=? ORDER BY sort_order,id');$blocks->execute([$id]);
        $insert=$pdo->prepare('INSERT INTO site_page_blocks(page_id,block_type,system_key,settings_json,active,locked,sort_order,created_by,updated_by) VALUES(?,?,?,?,?,0,?,?,?)');
        $copyTr=$pdo->prepare('INSERT INTO site_page_block_translations(block_id,language,title,content_json) SELECT ?,language,title,content_json FROM site_page_block_translations WHERE block_id=?');
        foreach($blocks->fetchAll() as $block){$insert->execute([$newId,$block['block_type'],null,$block['settings_json'],(int)$block['active'],(int)$block['sort_order'],Auth::user()['id']??null,Auth::user()['id']??null]);$copyTr->execute([(int)$pdo->lastInsertId(),(int)$block['id']]);}
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    AuditLogger::record('site_page',$newId,'duplicate',['source_id'=>$id],['slug'=>$slug],'Webseite dupliziert');json_response(['ok'=>true,'message'=>'Seite als Entwurf dupliziert.','id'=>$newId]);
}

function save_site_block_v218(): never
{
    $d=request_data();$id=(int)($d['id']??0);$pageId=(int)($d['page_id']??0);$page=site_page_row_v218($pageId);if(!$page)throw new NotFoundException('Seite nicht gefunden.');
    $old=$id?site_block_row_v218($id):null;if($id&&!$old)throw new NotFoundException('Inhaltsblock nicht gefunden.');if($old&&(int)$old['page_id']!==$pageId)throw new ConflictException('Der Block gehört nicht zu dieser Seite.');
    $type=(string)($d['block_type']??'rich_text');if(!array_key_exists($type,PublicSiteService::BLOCK_TYPES))throw new ValidationException('Unbekannter Blocktyp.');if($old&&(int)$old['locked']===1)$type=(string)$old['block_type'];
    if($type==='booking_search'){
        $duplicate=db()->prepare('SELECT COUNT(*) FROM site_page_blocks WHERE page_id=? AND block_type=? AND id<>?');
        $duplicate->execute([$pageId,'booking_search',$id]);
        if((int)$duplicate->fetchColumn()>0)throw new ConflictException('Auf einer Seite darf die Buchungssuche nur einmal vorkommen.');
    }
    $settings=site_block_settings_v218($type,is_array($d['settings']??null)?$d['settings']:[]);
    $active=normalize_bool($d['active']??1);$sort=max(-9999,min(9999,(int)($d['sort_order']??0)));$userId=Auth::user()['id']??null;
    $pdo=db();$pdo->beginTransaction();
    try{
        if($id)$pdo->prepare('UPDATE site_page_blocks SET block_type=?,settings_json=?,active=?,sort_order=?,updated_by=?,updated_at=NOW() WHERE id=?')->execute([$type,json_encode($settings,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$active,$sort,$userId,$id]);
        else{$countStmt=$pdo->prepare('SELECT COUNT(*) FROM site_page_blocks WHERE page_id=?');$countStmt->execute([$pageId]);$blockCount=(int)$countStmt->fetchColumn();if($blockCount>=50)throw new ConflictException('Eine Seite kann höchstens 50 Inhaltsblöcke enthalten.');$pdo->prepare('INSERT INTO site_page_blocks(page_id,block_type,settings_json,active,locked,sort_order,created_by,updated_by) VALUES(?,?,?, ?,0,?,?,?)')->execute([$pageId,$type,json_encode($settings,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$active,$sort?:($blockCount+1)*10,$userId,$userId]);$id=(int)$pdo->lastInsertId();}
        $translations=is_array($d['translations']??null)?$d['translations']:[];$up=$pdo->prepare('INSERT INTO site_page_block_translations(block_id,language,title,content_json) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),content_json=VALUES(content_json)');
        foreach(PublicSiteService::languages() as $language=>$label){$tr=is_array($translations[$language]??null)?$translations[$language]:[];$content=site_block_content_v218($type,is_array($tr['content']??null)?$tr['content']:$tr);$title=site_plain_v218($tr['title']??($content['title']??''),190);$up->execute([$id,$language,$title,json_encode($content,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);}
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    AuditLogger::record('site_block',$id,$old?'update':'create',$old,site_block_row_v218($id),'Webseitenblock gespeichert');json_response(['ok'=>true,'message'=>'Inhaltsblock gespeichert.','id'=>$id]);
}


function duplicate_site_block_v218(): never
{
    $id=(int)(request_data()['id']??0);$source=site_block_row_v218($id);if(!$source)throw new NotFoundException('Ausgangsblock nicht gefunden.');
    $pageId=(int)$source['page_id'];
    $countStmt=db()->prepare('SELECT COUNT(*) FROM site_page_blocks WHERE page_id=?');$countStmt->execute([$pageId]);
    if((int)$countStmt->fetchColumn()>=50)throw new ConflictException('Eine Seite kann höchstens 50 Inhaltsblöcke enthalten.');
    if((string)$source['block_type']==='booking_search')throw new ConflictException('Die Buchungssuche darf nicht dupliziert werden.');
    $sortStmt=db()->prepare('SELECT COALESCE(MAX(sort_order),0)+10 FROM site_page_blocks WHERE page_id=?');$sortStmt->execute([$pageId]);$sort=(int)$sortStmt->fetchColumn();
    $pdo=db();$pdo->beginTransaction();
    try{
        $pdo->prepare('INSERT INTO site_page_blocks(page_id,block_type,system_key,settings_json,active,locked,sort_order,created_by,updated_by) VALUES(?,?,?,?,?,0,?,?,?)')->execute([$pageId,$source['block_type'],null,$source['settings_json'],(int)$source['active'],$sort,Auth::user()['id']??null,Auth::user()['id']??null]);
        $newId=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO site_page_block_translations(block_id,language,title,content_json) SELECT ?,language,CONCAT(COALESCE(title,"")," – Kopie"),content_json FROM site_page_block_translations WHERE block_id=?')->execute([$newId,$id]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    AuditLogger::record('site_block',$newId,'duplicate',['source_id'=>$id],site_block_row_v218($newId),'Webseitenblock dupliziert');json_response(['ok'=>true,'message'=>'Block dupliziert.','id'=>$newId]);
}

function delete_site_block_v218(): never
{
    $id=(int)(request_data()['id']??0);$old=site_block_row_v218($id);if(!$old)throw new NotFoundException('Inhaltsblock nicht gefunden.');if((int)$old['locked']===1)throw new ConflictException('Dieser Funktionsblock ist geschützt und kann nicht gelöscht werden.');db()->prepare('DELETE FROM site_page_blocks WHERE id=?')->execute([$id]);AuditLogger::record('site_block',$id,'delete',$old,null,'Webseitenblock gelöscht');json_response(['ok'=>true,'message'=>'Inhaltsblock gelöscht.']);
}

function move_site_block_v218(): never
{
    $d=request_data();$id=(int)($d['id']??0);$direction=(string)($d['direction']??'up');$block=site_block_row_v218($id);if(!$block)throw new NotFoundException('Inhaltsblock nicht gefunden.');
    $op=$direction==='down'?'>':'<';$order=$direction==='down'?'ASC':'DESC';$stmt=db()->prepare("SELECT * FROM site_page_blocks WHERE page_id=? AND (sort_order {$op} ? OR (sort_order=? AND id {$op} ?)) ORDER BY sort_order {$order},id {$order} LIMIT 1");$stmt->execute([(int)$block['page_id'],(int)$block['sort_order'],(int)$block['sort_order'],$id]);$other=$stmt->fetch();if(!$other)json_response(['ok'=>true,'message'=>'Block befindet sich bereits am Rand.']);
    $pdo=db();$pdo->beginTransaction();try{$tmp=-1000000-$id;$pdo->prepare('UPDATE site_page_blocks SET sort_order=? WHERE id=?')->execute([$tmp,$id]);$pdo->prepare('UPDATE site_page_blocks SET sort_order=? WHERE id=?')->execute([(int)$block['sort_order'],(int)$other['id']]);$pdo->prepare('UPDATE site_page_blocks SET sort_order=? WHERE id=?')->execute([(int)$other['sort_order'],$id]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    json_response(['ok'=>true,'message'=>'Reihenfolge geändert.']);
}

function save_site_design_v218(): never
{
    $d=request_data();$design=is_array($d['design']??null)?$d['design']:[];$current=PublicSiteService::design();
    $colors=['primary','secondary','background','surface','text','muted','header_background','header_text','footer_background','footer_text','footer_heading','footer_link','contact_background','contact_text','contact_accent','booking_search_background','booking_search_surface','booking_search_text','booking_search_heading','booking_search_button_bg','booking_search_button_text','cookie_background','cookie_text','cookie_accent'];foreach($colors as $key){$value=(string)($design[$key]??$current[$key]);if(!preg_match('/^#[0-9a-fA-F]{6}$/',$value))throw new ValidationException('Ungültige Farbangabe bei '.$key.'.');$current[$key]=$value;}
    foreach(['logo_url','favicon_url','facebook_url','instagram_url','contact_map_link'] as $key)$current[$key]=site_safe_url_v218($design[$key]??$current[$key]);
    foreach(['phone','email','address','copyright','contact_map_lat','contact_map_lng'] as $key)$current[$key]=site_plain_v218($design[$key]??$current[$key],$key==='address'?500:255);
    $current['font']=in_array((string)($design['font']??'system'),['system','modern','serif','rounded','editorial','mono'],true)?(string)$design['font']:'system';
    $current['button_style']=in_array((string)($design['button_style']??'rounded'),['rounded','pill','square'],true)?(string)$design['button_style']:'rounded';
    $current['type_card_layout']=in_array((string)($design['type_card_layout']??'portrait'),['portrait','wide','compact'],true)?(string)$design['type_card_layout']:'portrait';
    $current['type_detail_gallery_layout']=in_array((string)($design['type_detail_gallery_layout']??'magazine'),['magazine','classic'],true)?(string)$design['type_detail_gallery_layout']:'magazine';
    $current['customer_portal_layout']=in_array((string)($design['customer_portal_layout']??'modern'),['modern','classic'],true)?(string)$design['customer_portal_layout']:'modern';
    $current['type_slider_interval']=max(0,min(12000,(int)($design['type_slider_interval']??4200)));
    $current['footer_columns']=max(1,min(3,(int)($design['footer_columns']??3)));
    $current['contact_map_zoom']=max(1,min(19,(int)($design['contact_map_zoom']??15)));
    $current['contact_map_height']=max(180,min(640,(int)($design['contact_map_height']??320)));
    $current['booking_search_radius']=max(0,min(40,(int)($design['booking_search_radius']??18)));
    $current['cookie_margin']=max(0,min(80,(int)($design['cookie_margin']??18)));
    $current['cookie_padding']=max(8,min(60,(int)($design['cookie_padding']??18)));
    $current['cookie_radius']=max(0,min(40,(int)($design['cookie_radius']??18)));
    $current['cookie_position']=in_array((string)($design['cookie_position']??'bottom'),['top','bottom'],true)?(string)$design['cookie_position']:'bottom';
    foreach(['cookie_notice','cookie_blocked_text'] as $key)$current[$key]=site_plain_v218($design[$key]??$current[$key],1000);
    $current['cookie_services']=site_cookie_services_v218((string)($design['cookie_services']??$current['cookie_services']??''));
    $current['content_width']=max(760,min(1600,(int)($design['content_width']??1180)));$current['radius']=max(0,min(40,(int)($design['radius']??18)));$current['card_shadow']=normalize_bool($design['card_shadow']??1);$current['sticky_header']=normalize_bool($design['sticky_header']??1);$current['show_admin_link']=normalize_bool($design['show_admin_link']??0);$current['contact_map_enabled']=normalize_bool($design['contact_map_enabled']??0);$current['booking_search_shadow']=normalize_bool($design['booking_search_shadow']??1);$current['cookie_enabled']=normalize_bool($design['cookie_enabled']??1);
    foreach(['inquiry_show_summary','inquiry_show_top_notice','inquiry_phone_required','inquiry_country_required','inquiry_show_breakfast','inquiry_show_half_board','inquiry_show_contact_preference','inquiry_show_arrival_time','inquiry_show_location_request','inquiry_show_special_occasion','inquiry_privacy_required','inquiry_marketing_consent'] as $key){$current[$key]=normalize_bool($design[$key]??($current[$key]??0));}
    $current['custom_forms']=site_custom_forms_v218(is_array($design['custom_forms']??null)?$design['custom_forms']:($current['custom_forms']??[]));
    $labels=is_array($d['labels']??null)?$d['labels']:[];$cleanLabels=[];$defaults=PublicSiteService::defaultLabels();
    foreach(PublicSiteService::languages() as $language=>$name){$source=is_array($labels[$language]??null)?$labels[$language]:[];foreach($defaults['de'] as $key=>$fallback){if(array_key_exists($key,$source))$cleanLabels[$language][$key]=site_plain_v218($source[$key],500);}}
    save_setting('site_design_json',$current);save_setting('site_labels_json',$cleanLabels);AuditLogger::record('site_design',1,'update',null,['design'=>$current],'Frontend-Design gespeichert');json_response(['ok'=>true,'message'=>'Design, Navigationstexte und Formularbeschriftungen gespeichert.']);
}


function upload_site_media_v218(): never
{
    $alt=[];foreach(OfferService::LANGUAGES as $language=>$label)$alt[$language]=trim((string)($_POST['alt_'.$language]??''));
    $media=SiteMediaService::upload($_FILES['image']??[],$alt,trim((string)($_POST['seo_name']??'')));
    json_response(['ok'=>true,'message'=>'Bild optimiert und in der Medienbibliothek gespeichert.','media'=>$media]);
}

function save_site_media_meta_v218(): never
{
    $d=request_data();$alt=is_array($d['alt_texts']??null)?$d['alt_texts']:[];$media=SiteMediaService::updateMeta((int)($d['id']??0),$alt);
    json_response(['ok'=>true,'message'=>'Bildtexte gespeichert.','media'=>$media]);
}

function delete_site_media_v218(): never
{
    $id=(int)(request_data()['id']??0);SiteMediaService::delete($id);json_response(['ok'=>true,'message'=>'Nicht verwendetes Webseitenbild gelöscht.']);
}

function site_page_row_v218(int $id): ?array{$stmt=db()->prepare('SELECT * FROM site_pages WHERE id=? LIMIT 1');$stmt->execute([$id]);return $stmt->fetch()?:null;}
function site_block_row_v218(int $id): ?array{$stmt=db()->prepare('SELECT * FROM site_page_blocks WHERE id=? LIMIT 1');$stmt->execute([$id]);return $stmt->fetch()?:null;}
function site_all_labels_v218(): array{$defaults=PublicSiteService::defaultLabels();$saved=setting('site_labels_json',[]);if(!is_array($saved))$saved=[];$all=[];foreach(PublicSiteService::languages() as $language=>$name)$all[$language]=array_replace($defaults['de'],$defaults[$language]??[],is_array($saved[$language]??null)?$saved[$language]:[]);return $all;}
function site_plain_v218(mixed $value,int $max): string{return mb_substr(trim(strip_tags((string)$value)),0,$max);}
function site_slug_v218(string $value): string{$value=mb_strtolower(trim($value));$map=['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss','á'=>'a','à'=>'a','â'=>'a','é'=>'e','è'=>'e','ê'=>'e','í'=>'i','ì'=>'i','ó'=>'o','ò'=>'o','ú'=>'u','ù'=>'u','ç'=>'c'];$value=strtr($value,$map);$value=preg_replace('/[^a-z0-9]+/','-',$value)??'';$value=trim($value,'-');if($value===''||in_array($value,['admin','api','gast','team','team-manager','assets','storage','angebot','wohnungstyp'],true))throw new ValidationException('Bitte eine andere, gültige Seitenadresse verwenden.');return mb_substr($value,0,160);}
function site_unique_slug_v218(string $base): string{$base=site_slug_v218($base);$slug=$base;$i=2;$stmt=db()->prepare('SELECT COUNT(*) FROM site_pages WHERE slug=?');while(true){$stmt->execute([$slug]);if(!(int)$stmt->fetchColumn())return $slug;$slug=mb_substr($base,0,150).'-'.$i++;}}
function site_safe_url_v218(mixed $value): string{$value=trim((string)$value);if($value==='')return '';if(preg_match('/[\x00-\x1F\x7F<>\"\']/', $value))throw new ValidationException('Die Linkadresse enthält unzulässige Zeichen.');if(preg_match('#^(https?://|mailto:|tel:|/|\?|#)#i',$value))return mb_substr($value,0,1000);if(!preg_match('#^[a-zA-Z0-9._/-]+(?:\?[^<>\"\']*)?(?:\#[^<>\"\']*)?$#',$value))throw new ValidationException('Bitte nur sichere http(s)-, mailto-, tel- oder interne Links verwenden.');if(preg_match('#^[a-z][a-z0-9+.-]*:#i',$value))throw new ValidationException('Dieses Linkprotokoll ist nicht erlaubt.');return mb_substr($value,0,1000);}

function site_block_settings_v218(string $type,array $data): array
{
    $out=[];
    if(in_array($type,['hero','image_text'],true)){$out['background_image']=site_safe_url_v218($data['background_image']??$data['image_url']??'');$out['image_url']=site_safe_url_v218($data['image_url']??'');$out['image_position']=in_array((string)($data['image_position']??'right'),['left','right','background'],true)?(string)$data['image_position']:'right';$out['alignment']=in_array((string)($data['alignment']??'left'),['left','center','right'],true)?(string)$data['alignment']:'left';$out['height']=in_array((string)($data['height']??'medium'),['small','medium','large'],true)?(string)$data['height']:'medium';}
    if($type==='type_grid'){$out['limit']=max(0,min(50,(int)($data['limit']??0)));$out['show_description']=normalize_bool($data['show_description']??1);$out['show_amenities']=normalize_bool($data['show_amenities']??1);}
    if($type==='reviews'){$out['limit']=max(0,min(30,(int)($data['limit']??0)));}
    if($type==='gallery'){$urls=is_array($data['images']??null)?$data['images']:preg_split('/\R/',(string)($data['images']??''));$out['images']=array_values(array_filter(array_map('site_safe_url_v218',array_slice($urls?:[],0,30))));}
    if($type==='spacer'){$out['height']=max(10,min(240,(int)($data['height']??40)));$out['line']=normalize_bool($data['line']??0);}
    if($type==='contact'){$out['show_form']=normalize_bool($data['show_form']??0);}
    if($type==='custom_form'){$out['form_index']=max(1,min(3,(int)($data['form_index']??1)));}
    $buttonBg=trim((string)($data['button_bg']??''));$buttonText=trim((string)($data['button_text']??''));$buttonSize=(string)($data['button_size']??'normal');
    if($buttonBg!==''&&preg_match('/^#[0-9a-fA-F]{6}$/',$buttonBg))$out['button_bg']=$buttonBg;
    if($buttonText!==''&&preg_match('/^#[0-9a-fA-F]{6}$/',$buttonText))$out['button_text']=$buttonText;
    $out['button_size']=in_array($buttonSize,['small','normal','large'],true)?$buttonSize:'normal';
    $out['button_radius']=max(0,min(40,(int)($data['button_radius']??18)));
    $out['button_align']=in_array((string)($data['button_align']??'left'),['left','center','right'],true)?(string)$data['button_align']:'left';
    return $out;
}

function site_custom_forms_v218(array $forms): array
{
    $out=[];
    for($i=0;$i<3;$i++){
        $form=is_array($forms[$i]??null)?$forms[$i]:[];
        $recipient=trim((string)($form['recipient']??''));
        if($recipient!==''&&!filter_var($recipient,FILTER_VALIDATE_EMAIL))$recipient='';
        $lines=preg_split('/\R/',(string)($form['fields']??''))?:[];
        $clean=[];
        foreach(array_slice($lines,0,30) as $line){
            $parts=array_map('trim',explode('|',(string)$line));
            $label=site_plain_v218($parts[0]??'',120);
            if($label==='')continue;
            $type=in_array($parts[1]??'text',['text','email','tel','date','number','textarea','select','checkbox'],true)?$parts[1]:'text';
            $required=normalize_bool($parts[2]??0)?'1':'0';
            $help=site_plain_v218($parts[3]??'',240);
            $options=site_plain_v218($parts[4]??'',500);
            $clean[]=implode('|',[$label,$type,$required,$help,$options]);
        }
        $out[]=[
            'active'=>normalize_bool($form['active']??0),
            'name'=>site_plain_v218($form['name']??('Formular '.($i+1)),120),
            'recipient'=>$recipient,
            'success'=>site_plain_v218($form['success']??'Vielen Dank. Ihre Nachricht wurde gesendet.',300),
            'fields'=>implode("\n",$clean),
        ];
    }
    return $out;
}

function site_cookie_services_v218(string $value): string
{
    $lines=preg_split('/\R/',$value)?:[];
    $out=[];
    foreach(array_slice($lines,0,40) as $line){
        $parts=array_map('trim',explode('|',(string)$line));
        $name=site_plain_v218($parts[0]??'',120);
        if($name==='')continue;
        $category=in_array($parts[1]??'preferences',['necessary','preferences','statistics','marketing'],true)?$parts[1]:'preferences';
        $cookies=site_plain_v218($parts[2]??'',300);
        $description=site_plain_v218($parts[3]??'',500);
        $active=normalize_bool($parts[4]??1)?'1':'0';
        $out[]=implode('|',[$name,$category,$cookies,$description,$active]);
    }
    return implode("\n",$out);
}

function site_block_content_v218(string $type,array $data): array
{
    $out=['title'=>site_plain_v218($data['title']??'',190),'subtitle'=>site_plain_v218($data['subtitle']??'',500)];
    foreach(['button_label','image_alt','eyebrow'] as $key)$out[$key]=site_plain_v218($data[$key]??'',255);
    $out['button_url']=site_safe_url_v218($data['button_url']??'');
    $out['content_html']=PublicSiteService::sanitizeRich((string)($data['content_html']??''));
    if(in_array($type,['features','faq','reviews'],true)){
        $items=is_array($data['items']??null)?$data['items']:[];$clean=[];
        foreach(array_slice($items,0,30) as $item){if(!is_array($item))continue;$title=site_plain_v218($item['title']??'',190);$text=site_plain_v218($item['text']??'',1000);$icon=site_plain_v218($item['icon']??'✓',20);if($title===''&&$text==='')continue;$clean[]=['icon'=>$icon?:'✓','title'=>$title,'text'=>$text];}$out['items']=$clean;
    }
    return $out;
}
