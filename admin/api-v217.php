<?php
declare(strict_types=1);

function offer_communication_v217(): never
{
    json_response(['ok'=>true]+OfferService::communication((int)($_GET['id']??0)));
}

function preview_offer_communication_v217(): never
{
    json_response(['ok'=>true]+OfferService::previewCommunication(request_data()));
}

function save_offer_communication_v217(): never
{
    json_response(['ok'=>true,'message'=>'E-Mail und öffentliche Angebotsseite wurden gespeichert.']+OfferService::saveCommunication(request_data()));
}
