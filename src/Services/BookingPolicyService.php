<?php
declare(strict_types=1);

final class BookingPolicyService
{
    public static function type(int $typeId): array
    {
        $stmt=db()->prepare('SELECT * FROM apartment_types WHERE id=? LIMIT 1');
        $stmt->execute([$typeId]);
        $row=$stmt->fetch();
        if(!$row)throw new ValidationException('Der gewählte Wohnungstyp existiert nicht.');
        return $row;
    }

    public static function cancellationSnapshot(int $typeId): array
    {
        $type=self::type($typeId);
        return [
            'apartment_type_id'=>(int)$type['id'],
            'apartment_type_name'=>(string)$type['name'],
            'free_until_days'=>(int)($type['cancel_free_until_days']??30),
            'tier1_from_days'=>(int)($type['cancel_tier1_from_days']??14),
            'tier1_percent'=>(float)($type['cancel_tier1_percent']??30),
            'tier2_from_days'=>(int)($type['cancel_tier2_from_days']??0),
            'tier2_percent'=>(float)($type['cancel_tier2_percent']??80),
            'no_show_percent'=>(float)($type['cancel_no_show_percent']??100),
            'captured_at'=>date('c'),
        ];
    }

    public static function validateCancellation(array $data): array
    {
        $free=max(0,min(730,(int)($data['cancel_free_until_days']??30)));
        $tier1=max(0,min(730,(int)($data['cancel_tier1_from_days']??14)));
        $tier2=max(0,min(730,(int)($data['cancel_tier2_from_days']??0)));
        $p1=self::percent($data['cancel_tier1_percent']??30);
        $p2=self::percent($data['cancel_tier2_percent']??80);
        $noShow=self::percent($data['cancel_no_show_percent']??100);
        if(!($free>$tier1 && $tier1>$tier2)){
            throw new ValidationException('Die Stornostufen müssen logisch absteigend sein: kostenlos bis mehr Tage als Stufe 1, Stufe 1 mehr Tage als Stufe 2.');
        }
        if($p2<$p1)throw new ValidationException('Die spätere Stornostufe darf nicht günstiger sein als die frühere Stornostufe.');
        if($noShow<$p2)throw new ValidationException('Die No-Show-Gebühr darf nicht niedriger als die letzte Stornostufe sein.');
        return [
            'cancel_free_until_days'=>$free,
            'cancel_tier1_from_days'=>$tier1,
            'cancel_tier1_percent'=>$p1,
            'cancel_tier2_from_days'=>$tier2,
            'cancel_tier2_percent'=>$p2,
            'cancel_no_show_percent'=>$noShow,
        ];
    }

    public static function cancellationQuote(array $snapshot,string $arrival,?string $cancelDate=null,float $total=0): array
    {
        if(!valid_date($arrival))throw new ValidationException('Das Anreisedatum für die Stornoberechnung ist ungültig.');
        $cancelDate=$cancelDate?:date('Y-m-d');
        if(!valid_date($cancelDate))throw new ValidationException('Das Stornodatum ist ungültig.');
        $arrivalDate=new DateTimeImmutable($arrival);
        $cancel=new DateTimeImmutable($cancelDate);
        $days=(int)$cancel->diff($arrivalDate)->format('%r%a');
        $free=(int)($snapshot['free_until_days']??30);
        $tier1=(int)($snapshot['tier1_from_days']??14);
        $tier2=(int)($snapshot['tier2_from_days']??0);
        if($days<0)$percent=(float)($snapshot['no_show_percent']??100);
        elseif($days>=$free)$percent=0.0;
        elseif($days>=$tier1)$percent=(float)($snapshot['tier1_percent']??30);
        elseif($days>=$tier2)$percent=(float)($snapshot['tier2_percent']??80);
        else $percent=(float)($snapshot['no_show_percent']??100);
        return ['days_before_arrival'=>$days,'percent'=>$percent,'amount'=>round(max(0,$total)*$percent/100,2)];
    }

    public static function capacityCheck(int $typeId,int $adults,int $children,int $babies=0,bool $override=false,string $reason=''): array
    {
        $type=self::type($typeId);
        $persons=max(0,$adults)+max(0,$children);
        $standard=max(1,(int)($type['standard_occupancy']??$type['max_occupancy']??2));
        $maximum=max($standard,(int)($type['max_occupancy']??$standard));
        $exceedsStandard=$persons>$standard;
        $exceedsMaximum=$persons>$maximum;
        if($exceedsStandard&&!$override){
            throw new ValidationException('Die Regelbelegung von '.$standard.' Personen wird überschritten. Bitte den Hinweis bestätigen.');
        }
        if($exceedsMaximum){
            if(!(int)($type['allow_capacity_override']??0))throw new ConflictException('Die maximale Belegung von '.$maximum.' Personen darf für diesen Wohnungstyp nicht überschritten werden.');
            if(trim($reason)==='')throw new ValidationException('Bitte begründen Sie die Überschreitung der maximalen Belegung.');
        }
        return [
            'persons'=>$persons,'babies'=>$babies,'standard'=>$standard,'maximum'=>$maximum,
            'exceeds_standard'=>$exceedsStandard,'exceeds_maximum'=>$exceedsMaximum,
            'requires_reason'=>$exceedsMaximum,
            'message'=>$exceedsMaximum
                ? 'Die maximale Belegung von '.$maximum.' Personen wird überschritten. Die Ausnahme muss begründet und bestätigt werden.'
                : ($exceedsStandard?'Die Regelbelegung von '.$standard.' Personen wird überschritten. Bitte prüfen und bestätigen.':''),
        ];
    }

    public static function childAges(mixed $value,int $children,bool $required=true): array
    {
        $children=max(0,$children);
        if($children===0)return [];
        if(is_string($value)){
            $trimmed=trim($value);
            if($trimmed==='')$value=[];
            else{
                $decoded=json_decode($trimmed,true);
                $value=is_array($decoded)?$decoded:(preg_split('/[,;\s]+/',$trimmed,-1,PREG_SPLIT_NO_EMPTY)?:[]);
            }
        }
        if(!is_array($value))$value=[];
        $ages=[];
        foreach(array_values($value) as $raw){
            if(is_int($raw))$age=$raw;
            elseif(is_string($raw)&&preg_match('/^\d{1,2}$/',trim($raw)))$age=(int)trim($raw);
            elseif(is_float($raw)&&floor($raw)===$raw)$age=(int)$raw;
            else throw new ValidationException('Bitte geben Sie für jedes Kind ein ganzzahliges Alter zwischen 0 und 17 Jahren an.');
            if($age<0||$age>17)throw new ValidationException('Bitte geben Sie für jedes Kind ein Alter zwischen 0 und 17 Jahren an.');
            $ages[]=$age;
        }
        if($required&&count($ages)!==$children){
            throw new ValidationException('Bitte geben Sie für jedes der '.$children.' Kinder genau ein Alter zwischen 0 und 17 Jahren an.');
        }
        return array_slice($ages,0,$children);
    }

    public static function cancellationText(array $snapshot,string $language='de'): string
    {
        $language=array_key_exists($language,OfferService::LANGUAGES)?$language:'de';
        $f=(int)($snapshot['free_until_days']??30);$d1=(int)($snapshot['tier1_from_days']??14);$p1=self::formatPercent($snapshot['tier1_percent']??30);$d2=(int)($snapshot['tier2_from_days']??0);$p2=self::formatPercent($snapshot['tier2_percent']??80);$pn=self::formatPercent($snapshot['no_show_percent']??100);
        $r1High=max($d1,$f-1);$r2High=max($d2,$d1-1);
        $texts=[
            'de'=>"Kostenlos bei Stornierung mindestens {$f} Tage vor Anreise. {$d1} bis {$r1High} Tage vorher: {$p1} %. {$d2} bis {$r2High} Tage vorher: {$p2} %. Danach oder bei Nichtanreise: {$pn} %.",
            'en'=>"Free cancellation at least {$f} days before arrival. {$d1} to {$r1High} days before arrival: {$p1}%. {$d2} to {$r2High} days before arrival: {$p2}%. Afterwards or no-show: {$pn}%.",
            'es'=>"Cancelación gratuita al menos {$f} días antes de la llegada. Entre {$d1} y {$r1High} días antes: {$p1} %. Entre {$d2} y {$r2High} días antes: {$p2} %. Después o no presentación: {$pn} %.",
            'pt'=>"Cancelamento gratuito pelo menos {$f} dias antes da chegada. Entre {$d1} e {$r1High} dias antes: {$p1}%. Entre {$d2} e {$r2High} dias antes: {$p2}%. Depois ou em caso de não comparência: {$pn}%.",
            'fr'=>"Annulation gratuite au moins {$f} jours avant l’arrivée. Entre {$d1} et {$r1High} jours avant : {$p1} %. Entre {$d2} et {$r2High} jours avant : {$p2} %. Ensuite ou en cas de non-présentation : {$pn} %.",
            'it'=>"Cancellazione gratuita almeno {$f} giorni prima dell’arrivo. Da {$d1} a {$r1High} giorni prima: {$p1}%. Da {$d2} a {$r2High} giorni prima: {$p2}%. Successivamente o in caso di mancata presentazione: {$pn}%.",
            'ca'=>"Cancel·lació gratuïta almenys {$f} dies abans de l’arribada. Entre {$d1} i {$r1High} dies abans: {$p1} %. Entre {$d2} i {$r2High} dies abans: {$p2} %. Després o en cas de no presentació: {$pn} %.",
        ];
        return $texts[$language];
    }

    private static function percent(mixed $value): float{return max(0,min(100,round((float)str_replace(',','.',(string)$value),2)));}
    private static function formatPercent(mixed $value): string{return rtrim(rtrim(number_format((float)$value,2,'.',''),'0'),'.');}
}
