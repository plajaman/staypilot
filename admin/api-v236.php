<?php
declare(strict_types=1);


function document_template_sample_booking_v236(): array
{
    return [
        'id' => 0,
        'reference' => 'SP-TEST-001',
        'public_language' => 'de',
        'language' => 'de',
        'guest_name' => 'Max Mustermann',
        'guest_first_name' => 'Max',
        'guest_last_name' => 'Mustermann',
        'guest_email' => 'max.mustermann@example.invalid',
        'arrival' => date('Y-m-d', strtotime('+14 days')),
        'departure' => date('Y-m-d', strtotime('+21 days')),
        'planned_arrival_time' => '15:00',
        'planned_departure_time' => '10:00',
        'adults' => 2,
        'children' => 1,
        'babies' => 0,
        'total_price' => 980.00,
        'paid_amount' => 250.00,
        'payment_status' => 'partial',
        'apartment_name' => 'Apartment Musterblick',
        'apartment_code' => 'A-101',
        'currency' => 'EUR',
        'documents' => [],
        'emails' => [],
    ];
}

function document_templates_v236(): never
{
    $channel = isset($_GET['channel']) ? (string)$_GET['channel'] : null;
    json_response(['ok' => true] + DocumentTemplateService::list($channel));
}

function document_template_v236(): never
{
    $id = (int)($_GET['id'] ?? 0);
    $channel = isset($_GET['channel']) ? (string)$_GET['channel'] : null;
    $template = DocumentTemplateService::get($id, $channel);
    json_response([
        'ok' => true,
        'template' => $template,
        'placeholders' => DocumentTemplateService::placeholders(),
        'customer_placeholders' => DocumentTemplateService::customerPlaceholders(),
        'placeholder_catalog' => DocumentTemplateService::placeholderCatalog(),
        'customer_placeholder_catalog' => DocumentTemplateService::customerPlaceholderCatalog(),
        'media' => SiteMediaService::all(),
        'variants' => array_values(array_filter(
            array_map(
                static fn(string $language): ?array => DocumentTemplateService::findVariant(
                    (string)($template['channel'] ?? $channel ?? 'email'),
                    (string)($template['code'] ?? ''),
                    $language
                ),
                array_keys(DocumentTemplateService::LANGUAGES)
            )
        )),
    ]);
}

function save_document_template_v236(): never
{
    $id = DocumentTemplateService::save(request_data(), Auth::user() ?? []);
    AuditLogger::record('document_template', $id, 'save', null, DocumentTemplateService::get($id), 'Dokumentvorlage gespeichert');
    json_response(['ok' => true, 'message' => 'Dokumentvorlage gespeichert.', 'id' => $id]);
}

function archive_document_template_v236(): never
{
    $id = (int)(request_data()['id'] ?? 0);
    DocumentTemplateService::archive($id);
    AuditLogger::record('document_template', $id, 'archive', null, null, 'Dokumentvorlage archiviert');
    json_response(['ok' => true, 'message' => 'Dokumentvorlage archiviert.']);
}

function duplicate_document_template_v236(): never
{
    $id = (int)(request_data()['id'] ?? 0);
    $newId = DocumentTemplateService::duplicate($id, Auth::user() ?? []);
    AuditLogger::record('document_template', $newId, 'duplicate', null, DocumentTemplateService::get($newId), 'Dokumentvorlage dupliziert');
    json_response(['ok' => true, 'message' => 'Vorlage als bearbeitbare Kopie angelegt.', 'id' => $newId]);
}

function preview_document_template_v236(): never
{
    $id = (int)($_GET['id'] ?? request_data()['id'] ?? 0);
    if ($id <= 0) {
        throw new ValidationException('Bitte zuerst speichern, dann Vorschau öffnen.');
    }
    json_response(['ok' => true] + DocumentTemplateService::preview($id));
}

function preview_document_template_draft_v236(): never
{
    json_response(['ok' => true] + DocumentTemplateService::previewData(request_data()));
}

function send_document_template_test_email_v236(): never
{
    $data = request_data();
    $recipient = Validator::email($data, 'test_email_recipient', 'Test-Empfaenger', true);
    $data['channel'] = 'email';
    $preview = DocumentTemplateService::previewData($data);
    $subject = trim((string)($data['subject_template'] ?? 'StayPilot Testmail'));
    if ($subject === '') {
        $subject = 'StayPilot Testmail';
    }
    $sampleReplace = [
        '{guest}' => 'Max Mustermann',
        '{guest_name}' => 'Max Mustermann',
        '{reference}' => 'SP-260619-001',
        '{arrival}' => '20.06.2026',
        '{departure}' => '27.06.2026',
        '{portal_url}' => 'https://example.invalid/kunde.php?token=demo',
        '{checkin_url}' => 'https://example.invalid/checkin.php?token=demo',
        '{company_name}' => (string)setting('company_name', 'StayPilot'),
        '{date}' => date('d.m.Y'),
        '{today}' => date('d.m.Y'),
    ];
    $subject = strtr($subject, $sampleReplace);
    $html = (string)($preview['html'] ?? '');
    $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $mailKey = DocumentTemplateService::mailKey((string)($data['code'] ?? 'booking_confirmation'));
    $attachments = [];
    $cleanup = [];
    $sampleBooking = document_template_sample_booking_v236();
    $attachContext = [
        'portal_url' => 'https://example.invalid/kunde.php?token=demo',
        'checkin_url' => 'https://example.invalid/checkin.php?token=demo',
        'payment_status' => 'partial',
        'language' => 'de',
    ];
    foreach (DocumentTemplateService::attachments($mailKey) as $fixedAttachment) {
        if (empty($fixedAttachment['active'])) {
            continue;
        }
        $templateId = DocumentTemplateService::resolveAttachmentTemplateId($fixedAttachment, $sampleBooking);
        if ($templateId <= 0) {
            continue;
        }
        $rendered = DocumentTemplateService::renderAttachmentPdf($templateId, $sampleBooking, $attachContext, (string)($fixedAttachment['filename_template'] ?? ''));
        if ($rendered && is_file((string)$rendered['path'])) {
            $attachments[] = ['path' => (string)$rendered['path'], 'name' => (string)$rendered['name'], 'mime' => 'application/pdf'];
            $cleanup[] = (string)$rendered['path'];
        }
    }
    try {
        $result = SmtpMailer::send($recipient, $subject, $text, $html, $attachments);
        foreach ($cleanup as $path) { if (is_file($path)) @unlink($path); }
        CommunicationLogger::record('email', 'document_template_test', (int)($data['id'] ?? 0), (string)(Auth::user()['name'] ?? 'Admin'), $recipient, $subject, $text, 'sent', (string)($result['message_id'] ?? ''));
        AuditLogger::record('document_template', (int)($data['id'] ?? 0), 'test_mail', null, ['recipient' => $recipient, 'mail_key' => $mailKey, 'attachments' => count($attachments)], 'Vorlagen-Testmail versendet');
        json_response(['ok' => true, 'message' => 'Testmail wurde versendet. Anhaenge: ' . count($attachments)]);
    } catch (Throwable $e) {
        foreach ($cleanup as $path) { if (is_file($path)) @unlink($path); }
        CommunicationLogger::record('email', 'document_template_test', (int)($data['id'] ?? 0), (string)(Auth::user()['name'] ?? 'Admin'), $recipient, $subject, $text, 'failed', $e->getMessage());
        throw $e;
    }
}

function document_mail_attachments_v236(): never
{
    $mailKey = (string)($_GET['mail_key'] ?? 'booking_confirmation');
    $templates = DocumentTemplateService::list('pdf')['templates'];
    json_response([
        'ok' => true,
        'mail_key' => $mailKey,
        'attachments' => DocumentTemplateService::attachmentPreviewRows($mailKey),
        'templates' => $templates,
        'mail_keys' => DocumentTemplateService::MAIL_KEYS,
        'languages' => DocumentTemplateService::LANGUAGES,
    ]);
}

function save_document_mail_attachments_v236(): never
{
    $data = request_data();
    $mailKey = (string)($data['mail_key'] ?? 'booking_confirmation');
    $rows = is_array($data['attachments'] ?? null) ? $data['attachments'] : [];
    DocumentTemplateService::saveAttachments($mailKey, $rows);
    $attachments = DocumentTemplateService::attachmentPreviewRows($mailKey);
    AuditLogger::record('mail_template_attachments', 0, 'save', null, ['mail_key' => $mailKey, 'count' => count($attachments)], 'Mailanhänge gespeichert');
    json_response(['ok' => true, 'message' => 'Mail-Anhänge gespeichert.', 'attachments' => $attachments]);
}
