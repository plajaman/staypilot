<?php
declare(strict_types=1);

/**
 * Sichere, eigenständige Medienablage für die öffentliche Webseite.
 * Wohnungstyp-Bilder bleiben bewusst in apartment_type_images getrennt.
 */
final class SiteMediaService
{
    public static function all(): array
    {
        $rows=db()->query('SELECT * FROM site_media ORDER BY created_at DESC,id DESC')->fetchAll();
        foreach($rows as &$row){$row['alt_texts']=json_decode((string)($row['alt_text_json']??''),true)?:[];}
        unset($row);
        return $rows;
    }

    public static function get(int $id): array
    {
        $stmt=db()->prepare('SELECT * FROM site_media WHERE id=? LIMIT 1');$stmt->execute([$id]);$row=$stmt->fetch();
        if(!$row)throw new NotFoundException('Webseitenbild nicht gefunden.');
        $row['alt_texts']=json_decode((string)($row['alt_text_json']??''),true)?:[];
        return $row;
    }

    public static function upload(array $file,array $altTexts=[],string $requestedName=''): array
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new ValidationException('Das Bild konnte nicht hochgeladen werden.');
        if((int)($file['size']??0)<=0||(int)$file['size']>12*1024*1024)throw new ValidationException('Das Bild darf höchstens 12 MB groß sein.');
        $tmp=(string)($file['tmp_name']??'');$info=@getimagesize($tmp);if(!$info)throw new ValidationException('Die hochgeladene Datei ist kein gültiges Bild.');
        $mime=(string)($info['mime']??'');if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))throw new ValidationException('Erlaubt sind JPG-, PNG- und WebP-Bilder.');
        $original=mb_substr((string)($file['name']??'bild'),0,255);$base=$requestedName!==''?$requestedName:pathinfo($original,PATHINFO_FILENAME);$slug=self::slug($base);$token=bin2hex(random_bytes(5));$name=$slug.'-'.$token.'.webp';$thumb=$slug.'-'.$token.'-thumb.webp';
        $dir=root_path('storage/site-images');$thumbDir=$dir.'/thumbs';self::ensureDirectory($dir);self::ensureDirectory($thumbDir);self::protect($dir);
        $target=$dir.'/'.$name;$thumbTarget=$thumbDir.'/'.$thumb;$id=0;$pdo=db();
        try{
            [$width,$height]=self::writeOptimized($tmp,$mime,$target,1920,1440,82);
            self::writeOptimized($tmp,$mime,$thumbTarget,720,540,78);
            $cleanAlt=[];foreach(OfferService::LANGUAGES as $language=>$label)$cleanAlt[$language]=mb_substr(trim((string)($altTexts[$language]??'')),0,255);
            $pdo->beginTransaction();
            $user=Auth::user();
            $pdo->prepare('INSERT INTO site_media(file_path,thumb_path,seo_filename,original_name,mime_type,width,height,size_bytes,alt_text_json,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([
                'storage/site-images/'.$name,'storage/site-images/thumbs/'.$thumb,$name,$original,'image/webp',$width,$height,(int)filesize($target),json_encode($cleanAlt,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$user['id']??null,
            ]);
            $id=(int)$pdo->lastInsertId();$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();foreach([$target,$thumbTarget] as $created)if(is_file($created))@unlink($created);throw $e;}
        AuditLogger::record('site_media',$id,'create',null,['file'=>$name],'Webseitenbild hochgeladen und optimiert');
        return self::get($id);
    }

    public static function updateMeta(int $id,array $altTexts): array
    {
        $old=self::get($id);$clean=[];foreach(OfferService::LANGUAGES as $language=>$label)$clean[$language]=mb_substr(trim((string)($altTexts[$language]??'')),0,255);
        db()->prepare('UPDATE site_media SET alt_text_json=?,updated_at=NOW() WHERE id=?')->execute([json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$id]);
        AuditLogger::record('site_media',$id,'update',$old,['alt_texts'=>$clean],'Bildtexte aktualisiert');return self::get($id);
    }

    public static function delete(int $id): void
    {
        $old=self::get($id);$path=(string)$old['file_path'];
        $stmt=db()->prepare('SELECT COUNT(*) FROM site_page_blocks WHERE settings_json LIKE ?');$stmt->execute(['%'.$path.'%']);
        if((int)$stmt->fetchColumn()>0)throw new ConflictException('Dieses Bild wird noch auf einer Webseite verwendet und kann deshalb nicht gelöscht werden.');
        $stmt=db()->prepare("SELECT COUNT(*) FROM settings WHERE setting_key='site_design_json' AND setting_value LIKE ?");$stmt->execute(['%'.$path.'%']);
        if((int)$stmt->fetchColumn()>0)throw new ConflictException('Dieses Bild wird noch als Logo oder Designelement verwendet.');
        db()->prepare('DELETE FROM site_media WHERE id=?')->execute([$id]);
        foreach(['file_path','thumb_path'] as $key){$relative=(string)($old[$key]??'');if($relative!==''){$file=root_path($relative);if(is_file($file))@unlink($file);}}
        AuditLogger::record('site_media',$id,'delete',$old,null,'Webseitenbild gelöscht');
    }

    private static function ensureDirectory(string $dir): void
    {
        if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('Der Webseiten-Bildordner konnte nicht angelegt werden.');
        if(!is_writable($dir))throw new RuntimeException('Der Webseiten-Bildordner ist nicht beschreibbar.');
    }

    private static function writeOptimized(string $source,string $mime,string $target,int $maxW,int $maxH,int $quality): array
    {
        if(!function_exists('imagecreatetruecolor')||!function_exists('imagewebp'))throw new RuntimeException('Für die automatische Bildoptimierung muss PHP-GD mit WebP-Unterstützung aktiviert sein.');
        $src=match($mime){'image/jpeg'=>@imagecreatefromjpeg($source),'image/png'=>@imagecreatefrompng($source),'image/webp'=>@imagecreatefromwebp($source),default=>false};
        if(!$src)throw new ValidationException('Das Bild konnte nicht verarbeitet werden.');
        $w=imagesx($src);$h=imagesy($src);$scale=min(1,$maxW/max(1,$w),$maxH/max(1,$h));$nw=max(1,(int)round($w*$scale));$nh=max(1,(int)round($h*$scale));
        $dst=imagecreatetruecolor($nw,$nh);imagealphablending($dst,false);imagesavealpha($dst,true);$transparent=imagecolorallocatealpha($dst,0,0,0,127);imagefilledrectangle($dst,0,0,$nw,$nh,$transparent);imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);
        if(!imagewebp($dst,$target,$quality)){imagedestroy($src);imagedestroy($dst);throw new RuntimeException('Das optimierte WebP-Bild konnte nicht gespeichert werden.');}
        imagedestroy($src);imagedestroy($dst);return[$nw,$nh];
    }

    private static function protect(string $dir): void
    {
        $file=$dir.'/.htaccess';if(!is_file($file))@file_put_contents($file,"Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl|py|sh)$\">\nRequire all denied\n</FilesMatch>\n");
    }

    private static function slug(string $value): string
    {
        $value=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:$value;$value=mb_strtolower($value);$value=preg_replace('/[^a-z0-9]+/','-',$value)??'';return trim($value,'-')?:'webseitenbild';
    }
}
