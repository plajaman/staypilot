<?php
declare(strict_types=1);

function offers_v214(): never
{
    json_response(['ok'=>true]+OfferService::overview($_GET));
}

function offer_form_data_v214(): never
{
    json_response(['ok'=>true]+OfferService::formData()+['templates'=>OfferService::templates()]);
}

function offer_v214(): never
{
    json_response(['ok'=>true,'offer'=>OfferService::get((int)($_GET['id']??0))]);
}

function offer_quote_v214(): never
{
    json_response(['ok'=>true,'quote'=>OfferService::calculate(request_data())]);
}

function save_missing_offer_price_v215(): never
{
    $result=OfferService::saveMissingPrice(request_data());
    json_response(['ok'=>true]+$result);
}

function save_offer_v214(): never
{
    $offer=OfferService::save(request_data());
    json_response(['ok'=>true,'message'=>'Angebot als Entwurf gespeichert.','offer'=>$offer]);
}

function revise_offer_v214(): never
{
    $offer=OfferService::revise((int)(request_data()['id']??0));
    json_response(['ok'=>true,'message'=>'Eine neue bearbeitbare Angebotsrevision wurde erstellt.','offer'=>$offer]);
}

function send_offer_v214(): never
{
    $offer=OfferService::send((int)(request_data()['id']??0));
    $message=in_array((string)$offer['status'],['sent','viewed'],true)&&!empty($offer['sent_at'])?'Angebot wurde versendet und im Versandprotokoll erfasst.':'Angebot wurde versendet.';
    json_response(['ok'=>true,'message'=>$message,'offer'=>$offer]);
}

function archive_offer_v214(): never
{
    $offer=OfferService::archive((int)(request_data()['id']??0));
    json_response(['ok'=>true,'message'=>'Angebot wurde archiviert.','offer'=>$offer]);
}

function convert_offer_v214(): never
{
    $data = request_data();
    $workflowOptions = $data;
    if (!array_key_exists('workflow', $workflowOptions)) {
        $workflowOptions['workflow'] = normalize_bool($data['send_email'] ?? 0) || normalize_bool($data['attach_pdf'] ?? 0) || normalize_bool($data['create_arrival_pdf'] ?? 0) || normalize_bool($data['create_payment_pdf'] ?? 0) ? 1 : 0;
    }
    $result = OfferService::convertToBooking((int)($data['id'] ?? 0), (int)($data['apartment_id'] ?? 0), $workflowOptions);
    $message = 'Angebot wurde als verbindliche Buchung übernommen.';
    if (!empty($result['workflow']['email_sent'])) {
        $message .= ' Die Bestätigungs-E-Mail mit Dokumenten wurde versendet.';
    } elseif (!empty($workflowOptions['send_email'])) {
        $message .= ' Die Buchung wurde angelegt; der E-Mail-Versand muss geprüft werden.';
    }
    json_response(['ok' => true, 'message' => $message] + $result);
}

function offer_services_v214(): never
{
    json_response(['ok'=>true,'services'=>OfferService::services()]);
}

function save_offer_service_v214(): never
{
    json_response(['ok'=>true,'message'=>'Zusatzleistung gespeichert.']+OfferService::saveService(request_data()));
}

function delete_offer_service_v214(): never
{
    OfferService::deleteService((int)(request_data()['id']??0));
    json_response(['ok'=>true,'message'=>'Zusatzleistung wurde gelöscht oder bei bestehender Verwendung deaktiviert.']);
}

function offer_content_blocks_v214(): never
{
    json_response(['ok'=>true,'content_blocks'=>OfferService::contentBlocks(),'languages'=>OfferService::LANGUAGES]);
}

function save_offer_content_block_v214(): never
{
    json_response(['ok'=>true,'message'=>'Mehrsprachiger Textbaustein wurde gespeichert.']+OfferService::saveContentBlock(request_data()));
}

function delete_offer_content_block_v214(): never
{
    OfferService::deleteContentBlock((int)(request_data()['id']??0));
    json_response(['ok'=>true,'message'=>'Textbaustein wurde gelöscht oder bei bestehender Verwendung deaktiviert.']);
}

function offer_translations_v214(): never
{
    json_response(['ok'=>true,'translations'=>OfferService::typeTranslations(),'templates'=>OfferService::templates(),'languages'=>OfferService::LANGUAGES]);
}

function save_offer_translations_v214(): never
{
    json_response(['ok'=>true,'message'=>'Übersetzungen des Wohnungstyps wurden gespeichert.','translations'=>OfferService::saveTranslations(request_data())]);
}

function save_offer_templates_v214(): never
{
    json_response(['ok'=>true,'message'=>'Mehrsprachige Angebotstexte wurden gespeichert.','templates'=>OfferService::saveTemplates(request_data())]);
}

function offer_settings_v214(): never
{
    json_response(['ok'=>true,'settings'=>OfferService::settings()]);
}

function save_offer_settings_v214(): never
{
    json_response(['ok'=>true,'message'=>'Angebotseinstellungen wurden gespeichert.','settings'=>OfferService::saveSettings(request_data())]);
}
