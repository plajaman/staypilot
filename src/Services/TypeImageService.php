<?php
declare(strict_types=1);

final class TypeImageService
{
    public static function upload(int $typeId,array $file,array $altTexts=[]): array
    {
        if($typeId<=0)throw new ValidationException('Wohnungstyp fehlt.');
        BookingPolicyService::type($typeId);
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new ValidationException('Das Bild konnte nicht hochgeladen werden.');
        if((int)($file['size']??0)<=0||(int)$file['size']>12*1024*1024)throw new ValidationException('Das Bild darf höchstens 12 MB groß sein.');
        $tmp=(string)($file['tmp_name']??'');
        $info=@getimagesize($tmp);if(!$info)throw new ValidationException('Die hochgeladene Datei ist kein gültiges Bild.');
        $mime=(string)($info['mime']??'');
        if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))throw new ValidationException('Erlaubt sind JPG-, PNG- und WebP-Bilder.');
        $stmt=db()->prepare('SELECT code,name FROM apartment_types WHERE id=?');$stmt->execute([$typeId]);$type=$stmt->fetch();
        $slug=self::slug((string)($type['code']?:$type['name']?:'wohnung'));
        $token=bin2hex(random_bytes(5));$name=$slug.'-'.$token.'.webp';$thumb=$slug.'-'.$token.'-thumb.webp';
        $dir=root_path('storage/type-images');$thumbDir=$dir.'/thumbs';
        if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('Bildordner konnte nicht erstellt werden.');
        if(!is_dir($thumbDir)&&!mkdir($thumbDir,0775,true)&&!is_dir($thumbDir))throw new RuntimeException('Vorschaubildordner konnte nicht erstellt werden.');
        self::protect($dir);
        $target=$dir.'/'.$name;$thumbTarget=$thumbDir.'/'.$thumb;
        $pdo=db();$id=0;
        try{
            [$width,$height]=self::writeOptimized($tmp,$mime,$target,1920,1440,82);
            self::writeOptimized($tmp,$mime,$thumbTarget,720,540,78);
            $pdo->beginTransaction();
            // Sperrt den Typ kurz, damit bei parallelen Erst-Uploads nur ein Titelbild entsteht.
            $lock=$pdo->prepare('SELECT id FROM apartment_types WHERE id=? FOR UPDATE');$lock->execute([$typeId]);
            if(!$lock->fetchColumn())throw new NotFoundException('Wohnungstyp nicht gefunden.');
            $coverStmt=$pdo->prepare('SELECT COUNT(*) FROM apartment_type_images WHERE apartment_type_id=? AND is_cover=1');$coverStmt->execute([$typeId]);$isCover=(int)$coverStmt->fetchColumn()===0?1:0;
            $user=Auth::user();
            $pdo->prepare('INSERT INTO apartment_type_images(apartment_type_id,file_path,thumb_path,seo_filename,original_name,mime_type,width,height,size_bytes,alt_text_json,title_text_json,caption_text_json,is_cover,sort_order,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$typeId,'storage/type-images/'.$name,'storage/type-images/thumbs/'.$thumb,$name,mb_substr((string)($file['name']??''),0,255),'image/webp',$width,$height,(int)filesize($target),json_encode($altTexts,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode([],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode([],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$isCover,0,$user['id']??null]);
            $id=(int)$pdo->lastInsertId();$pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            foreach([$target,$thumbTarget] as $createdFile)if(is_file($createdFile))@unlink($createdFile);
            throw $e;
        }
        AuditLogger::record('apartment_type_image',$id,'create',null,['apartment_type_id'=>$typeId,'file'=>$name],'Wohnungstyp-Bild hochgeladen und optimiert');
        return self::get($id);
    }

    public static function get(int $id): array
    {
        $stmt=db()->prepare('SELECT * FROM apartment_type_images WHERE id=?');$stmt->execute([$id]);$row=$stmt->fetch();
        if(!$row)throw new NotFoundException('Bild nicht gefunden.');
        $row['alt_texts']=json_decode((string)($row['alt_text_json']??''),true)?:[];
        $row['title_texts']=json_decode((string)($row['title_text_json']??''),true)?:[];
        $row['caption_texts']=json_decode((string)($row['caption_text_json']??''),true)?:[];
        return $row;
    }

    public static function delete(int $id): void
    {
        $old=self::get($id);$typeId=(int)$old['apartment_type_id'];$paths=[];
        foreach(['file_path','thumb_path'] as $key){$relative=(string)($old[$key]??'');if($relative!=='')$paths[]=root_path($relative);}
        $pdo=db();$pdo->beginTransaction();
        try{
            $pdo->prepare('DELETE FROM apartment_type_images WHERE id=?')->execute([$id]);
            $stmt=$pdo->prepare('SELECT COUNT(*) FROM apartment_type_images WHERE apartment_type_id=? AND is_cover=1');$stmt->execute([$typeId]);
            if(!(int)$stmt->fetchColumn())$pdo->prepare('UPDATE apartment_type_images SET is_cover=1 WHERE apartment_type_id=? ORDER BY sort_order,id LIMIT 1')->execute([$typeId]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        // Erst nach erfolgreicher Datenbankänderung löschen; übrig gebliebene Dateien wären harmloser als ein defekter Datensatz.
        foreach($paths as $path)if(is_file($path))@unlink($path);
        AuditLogger::record('apartment_type_image',$id,'delete',$old,null,'Wohnungstyp-Bild gelöscht');
    }

    public static function setCover(int $id): void
    {
        $image=self::get($id);$pdo=db();$pdo->beginTransaction();try{$pdo->prepare('UPDATE apartment_type_images SET is_cover=0 WHERE apartment_type_id=?')->execute([(int)$image['apartment_type_id']]);$pdo->prepare('UPDATE apartment_type_images SET is_cover=1 WHERE id=?')->execute([$id]);$pdo->commit();}catch(Throwable $e){$pdo->rollBack();throw $e;}
    }

    public static function updateMeta(int $id,array $data): array
    {
        $image=self::get($id);
        $alt=$data['alt_texts']??[]; if(!is_array($alt)) $alt=[];
        $title=$data['title_texts']??[]; if(!is_array($title)) $title=[];
        $caption=$data['caption_texts']??[]; if(!is_array($caption)) $caption=[];
        $cleanAlt=[];$cleanTitle=[];$cleanCaption=[];
        foreach(OfferService::LANGUAGES as $lang=>$label){
            $cleanAlt[$lang]=mb_substr(trim((string)($alt[$lang]??'')),0,255);
            $cleanTitle[$lang]=mb_substr(trim((string)($title[$lang]??'')),0,160);
            $cleanCaption[$lang]=mb_substr(trim((string)($caption[$lang]??'')),0,400);
        }
        db()->prepare('UPDATE apartment_type_images SET alt_text_json=?,title_text_json=?,caption_text_json=?,sort_order=? WHERE id=?')->execute([
            json_encode($cleanAlt,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            json_encode($cleanTitle,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            json_encode($cleanCaption,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            (int)($data['sort_order']??0),
            $id
        ]);
        return self::get($id);
    }

    private static function writeOptimized(string $source,string $mime,string $target,int $maxW,int $maxH,int $quality): array
    {
        if(!function_exists('imagecreatetruecolor')||!function_exists('imagewebp')){
            throw new RuntimeException('Für die automatische Bildoptimierung muss die PHP-Erweiterung GD mit WebP-Unterstützung aktiviert sein.');
        }
        $src=match($mime){'image/jpeg'=>@imagecreatefromjpeg($source),'image/png'=>@imagecreatefrompng($source),'image/webp'=>@imagecreatefromwebp($source),default=>false};
        if(!$src)throw new ValidationException('Das Bild konnte nicht verarbeitet werden.');
        $w=imagesx($src);$h=imagesy($src);$scale=min(1,$maxW/max(1,$w),$maxH/max(1,$h));$nw=max(1,(int)round($w*$scale));$nh=max(1,(int)round($h*$scale));
        $dst=imagecreatetruecolor($nw,$nh);imagealphablending($dst,false);imagesavealpha($dst,true);$transparent=imagecolorallocatealpha($dst,0,0,0,127);imagefilledrectangle($dst,0,0,$nw,$nh,$transparent);imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);
        if(!function_exists('imagewebp')||!imagewebp($dst,$target,$quality)){imagedestroy($src);imagedestroy($dst);throw new RuntimeException('WebP-Bild konnte nicht erzeugt werden. Bitte GD/WebP auf dem Server aktivieren.');}
        imagedestroy($src);imagedestroy($dst);return [$nw,$nh];
    }
    private static function protect(string $dir): void{$file=$dir.'/.htaccess';if(!is_file($file))@file_put_contents($file,"Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl|py|sh)$\">\nRequire all denied\n</FilesMatch>\n");}
    private static function slug(string $value): string{$value=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:$value;$value=mb_strtolower($value);$value=preg_replace('/[^a-z0-9]+/','-',$value);return trim((string)$value,'-')?:'wohnung';}
}
