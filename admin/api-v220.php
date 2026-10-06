<?php
declare(strict_types=1);

function booking_confirmation_queue_v220(): never
{
    json_response(['ok'=>true,'offers'=>BookingWorkflowService::queue(),'payment_attention'=>BookingWorkflowService::paymentAttention()]);
}

function booking_confirmation_data_v220(): never
{
    $id=(int)($_GET['id']??0);if(!$id)throw new ValidationException('Angebot fehlt.');
    json_response(['ok'=>true]+BookingWorkflowService::confirmationData($id));
}

function confirm_offer_booking_v220(): never
{
    $data=request_data();$id=(int)($data['offer_id']??0);$apartmentId=(int)($data['apartment_id']??0);
    if(!$id||!$apartmentId)throw new ValidationException('Angebot und konkrete Wohnung sind erforderlich.');
    $offer=OfferService::get($id);$options=BookingWorkflowService::normalizeOptions($data,$offer);
    $result=OfferService::convertToBooking($id,$apartmentId,$options);
    $workflow=is_array($result['workflow']??null)?$result['workflow']:[];
    $message='Buchung '.$result['booking_reference'].' wurde bestätigt und im Kalender blockiert.';
    if(!empty($workflow['email_sent']))$message.=' Die Bestätigung wurde per E-Mail versendet.';
    elseif(!empty($options['send_email']))$message.=' Die Buchung ist gespeichert; der E-Mail-Versand muss geprüft werden.';
    json_response(['ok'=>true,'message'=>$message,'booking_id'=>$result['booking_id'],'booking_reference'=>$result['booking_reference'],'workflow'=>$workflow]);
}

function payment_attention_v220(): never
{
    json_response(['ok'=>true,'attention'=>BookingWorkflowService::paymentAttention()]);
}

function booking_no_availability_action_v221(): never
{
    $data=request_data();
    $id=(int)($data['offer_id']??0);
    if(!$id)throw new ValidationException('Angebot fehlt.');
    $mode=(string)($data['mode']??'hold');
    $result=BookingWorkflowService::noAvailabilityAction($id,$mode,$data);
    $message=match($result['mode']??'hold'){
        'notify'=>'Der Gast wurde informiert. Das Angebot bleibt zur Klärung im Buchungseingang.',
        'cancel'=>'Die Anfrage wurde wegen fehlender Verfügbarkeit archiviert.',
        default=>'Das Angebot wurde auf Rückfrage/Warteliste gesetzt und bleibt im Buchungseingang.',
    };
    json_response(['ok'=>true,'message'=>$message,'result'=>$result]);
}
