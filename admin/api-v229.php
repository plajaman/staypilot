<?php
declare(strict_types=1);

function checkin_documents_v229(): never
{
    $bookingId = (int)($_GET['booking_id'] ?? $_GET['id'] ?? 0);
    if ($bookingId <= 0) throw new ValidationException('Buchung fehlt.');
    $booking = CheckinDocumentService::bookingRow($bookingId);
    $bundle = CheckinService::bundle($bookingId);
    json_response([
        'ok' => true,
        'booking' => $booking,
        'summary' => $bundle['checkin_summary'],
        'documents' => CheckinDocumentService::documentsForBooking($bookingId),
        'print_urls' => [
            'checkin_summary' => '../print/checkin_summary.php?booking_id=' . $bookingId,
            'registration_form' => '../print/meldeschein.php?booking_id=' . $bookingId,
        ],
        'communication' => CheckinDocumentService::communicationStatus($bookingId),
    ]);
}

function generate_checkin_documents_v229(): never
{
    $d = request_data();
    $bookingId = (int)($d['booking_id'] ?? 0);
    if ($bookingId <= 0) throw new ValidationException('Buchung fehlt.');
    $result = CheckinDocumentService::generate($bookingId, [
        'create_summary' => $d['create_summary'] ?? 1,
        'create_registration' => $d['create_registration'] ?? 1,
        'force' => $d['force'] ?? 0,
    ], (int)(Auth::user()['id'] ?? 0));
    $mail = ['sent'=>false,'message'=>'Benachrichtigung deaktiviert.'];
    if (normalize_bool($d['notify_customer'] ?? 0)) {
        $ids = array_map(static fn(array $doc): int => (int)$doc['id'], $result['created']);
        $mail = CheckinDocumentService::sendToCustomer($bookingId, $ids, trim((string)($d['message'] ?? '')));
    }
    json_response([
        'ok' => true,
        'message' => count($result['created']) . ' Check-in-Dokument(e) erzeugt.' . ($mail['sent'] ? ' Kunde wurde per E-Mail informiert.' : ''),
        'created' => $result['created'],
        'documents' => $result['documents'],
        'email' => $mail,
    ]);
}

function send_checkin_documents_v229(): never
{
    $d = request_data();
    $bookingId = (int)($d['booking_id'] ?? 0);
    if ($bookingId <= 0) throw new ValidationException('Buchung fehlt.');
    $ids = $d['document_ids'] ?? [];
    if (!is_array($ids)) $ids = [];
    $mail = CheckinDocumentService::sendToCustomer($bookingId, array_values(array_filter(array_map('intval', $ids))), trim((string)($d['message'] ?? '')));
    json_response(['ok'=>true,'message'=>$mail['sent'] ? 'Check-in-Dokumente wurden per E-Mail gesendet.' : 'Kunden-E-Mail nicht bestätigt: ' . $mail['message'],'email'=>$mail,'documents'=>CheckinDocumentService::documentsForBooking($bookingId)]);
}

function checkin_communication_check_v229(): never
{
    $bookingId = (int)($_GET['booking_id'] ?? $_GET['id'] ?? 0);
    if ($bookingId <= 0) throw new ValidationException('Buchung fehlt.');
    json_response(['ok'=>true,'communication'=>CheckinDocumentService::communicationStatus($bookingId)]);
}
