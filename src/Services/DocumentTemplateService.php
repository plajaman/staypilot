<?php
declare(strict_types=1);

final class DocumentTemplateService
{
    private static bool $schemaEnsured = false;

    public const CHANNELS = [
        'email' => 'E-Mail',
        'pdf' => 'PDF/Dokument',
        'customer' => 'Kundenbereich',
    ];

    public const LANGUAGES = [
        'de' => 'Deutsch',
        'en' => 'English',
        'es' => 'Espanol',
        'pt' => 'Portugues',
        'fr' => 'Francais',
        'it' => 'Italiano',
        'ca' => 'Catala',
    ];

    public const CATEGORIES = [
        'booking' => 'Buchung',
        'billing' => 'Abrechnung',
        'operations' => 'Betrieb',
        'guest' => 'Gast',
        'general' => 'Allgemein',
    ];

    public const CONTEXTS = [
        'booking' => 'Buchung',
        'housekeeping' => 'Putzplan',
        'guest' => 'Gast',
        'portal' => 'Kundenbereich',
        'system' => 'Freies Dokument',
    ];

    public const MAIL_KEYS = [
        'booking_confirmation' => 'Buchungsbestaetigung',
        'payment_received' => 'Zahlungseingang',
        'cancellation' => 'Storno',
        'offer' => 'Angebot',
        'checkin_request' => 'Online-Check-in',
        'housekeeping_task' => 'Putzauftrag',
        'free_mail' => 'Freie Mail',
    ];

    public static function list(?string $channel = null): array
    {
        self::ensureSchema();
        $channel = $channel !== null && $channel !== '' ? self::channel($channel) : '';
        $sql = 'SELECT * FROM document_templates';
        $params = [];
        if ($channel !== '') {
            $sql .= ' WHERE channel=?';
            $params[] = $channel;
        }
        $sql .= ' ORDER BY channel, status="active" DESC, sort_order, language, name';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return [
            'templates' => $rows,
            'by_channel' => self::groupByChannel($rows),
            'channels' => self::CHANNELS,
            'languages' => self::LANGUAGES,
            'categories' => self::CATEGORIES,
            'contexts' => self::CONTEXTS,
            'mail_keys' => self::MAIL_KEYS,
            'placeholders' => self::placeholders(),
            'customer_placeholders' => self::customerPlaceholders(),
        ];
    }

    public static function get(int $id, ?string $channel = null): array
    {
        self::ensureSchema();
        $channel = self::channel($channel ?? 'email');
        if ($id <= 0) {
            return self::blankTemplate($channel);
        }
        $stmt = db()->prepare('SELECT * FROM document_templates WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new NotFoundException('Dokumentvorlage nicht gefunden.');
        }
        return $row;
    }

    public static function findVariant(string $channel, string $code, string $language): ?array
    {
        self::ensureSchema();
        $channel = self::channel($channel);
        $code = self::code($code);
        $language = self::language($language);
        if ($code === '') {
            return null;
        }
        $stmt = db()->prepare('SELECT * FROM document_templates WHERE channel=? AND code=? AND language=? LIMIT 1');
        $stmt->execute([$channel, $code, $language]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function save(array $data, array $user): int
    {
        self::ensureSchema();
        $id = max(0, (int)($data['id'] ?? 0));
        $existing = null;
        if ($id > 0) {
            $stmt = db()->prepare('SELECT * FROM document_templates WHERE id=? LIMIT 1');
            $stmt->execute([$id]);
            $existing = $stmt->fetch() ?: null;
        }

        $channel = self::channel((string)($data['channel'] ?? ($existing['channel'] ?? 'email')));
        $code = self::code((string)($data['code'] ?? ($existing['code'] ?? '')));
        if ($code === '') {
            $code = ($channel === 'customer' ? 'portal' : ($channel === 'pdf' ? 'pdf' : 'email')) . '_' . date('ymd_His');
        }

        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException('Bitte einen Vorlagennamen eintragen.');
        }

        $language = self::language((string)($data['language'] ?? ($existing['language'] ?? 'de')));
        $category = self::category((string)($data['category'] ?? ($existing['category'] ?? 'general')));
        $context = self::context((string)($data['context_type'] ?? ($existing['context_type'] ?? ($channel === 'customer' ? 'portal' : 'booking'))));
        $status = self::status((string)($data['status'] ?? ($existing['status'] ?? 'active')));
        $portalTitle = trim((string)($data['portal_title'] ?? ($existing['portal_title'] ?? '')));
        if ($portalTitle === '') {
            $portalTitle = $name;
        }
        $portalSubtitle = trim((string)($data['portal_subtitle'] ?? ($existing['portal_subtitle'] ?? '')));
        $buttonLabel = trim((string)($data['button_label'] ?? ($existing['button_label'] ?? '')));
        if ($buttonLabel === '') {
            $buttonLabel = $channel === 'customer'
                ? 'Kundenbereich oeffnen'
                : ($channel === 'pdf' ? 'PDF anzeigen' : 'E-Mail anzeigen');
        }

        $subjectTemplate = trim((string)($data['subject_template'] ?? ($existing['subject_template'] ?? '')));
        $titleTemplate = trim((string)($data['title_template'] ?? ($existing['title_template'] ?? $name)));
        $headerHtml = self::cleanHtml((string)($data['header_html'] ?? ($existing['header_html'] ?? '')));
        $bodyHtml = self::cleanHtml((string)($data['body_html'] ?? ($existing['body_html'] ?? '')));
        $footerHtml = self::cleanHtml((string)($data['footer_html'] ?? ($existing['footer_html'] ?? '')));
        $layoutJson = json_encode(self::layout($data['layout_json'] ?? ($existing['layout_json'] ?? null)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $showInPortal = normalize_bool($data['show_in_portal'] ?? ($existing['show_in_portal'] ?? ($channel === 'customer' ? 1 : 0)));

        if ($channel !== 'customer') {
            $portalTitle = '';
            $portalSubtitle = '';
            $showInPortal = false;
            $layoutJson = json_encode(['blocks' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($channel === 'email') {
            $headerHtml = '';
            $footerHtml = '';
        }
        if ($channel !== 'email') {
            $subjectTemplate = '';
        }

        $values = [
            $channel,
            $code,
            $name,
            $category,
            $context,
            $language,
            $status,
            $subjectTemplate,
            $titleTemplate,
            $portalTitle,
            $portalSubtitle,
            $buttonLabel,
            $showInPortal ? 1 : 0,
            $headerHtml,
            $bodyHtml,
            $footerHtml,
            trim((string)($data['filename_template'] ?? ($existing['filename_template'] ?? '{code}-{reference}.pdf'))) ?: '{code}-{reference}.pdf',
            json_encode(self::settings(is_array($data['page_settings'] ?? null) ? $data['page_settings'] : []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $layoutJson,
            (int)($data['sort_order'] ?? ($existing['sort_order'] ?? 100)),
        ];

        if ($id > 0) {
            db()->prepare('UPDATE document_templates SET channel=?,code=?,name=?,category=?,context_type=?,language=?,status=?,subject_template=?,title_template=?,portal_title=?,portal_subtitle=?,button_label=?,show_in_portal=?,header_html=?,body_html=?,footer_html=?,filename_template=?,page_settings_json=?,layout_json=?,sort_order=?,updated_by=? WHERE id=?')
                ->execute(array_merge($values, [(int)($user['id'] ?? 0) ?: null, $id]));
            return $id;
        }

        db()->prepare('INSERT INTO document_templates(channel,code,name,category,context_type,language,status,subject_template,title_template,portal_title,portal_subtitle,button_label,show_in_portal,header_html,body_html,footer_html,filename_template,page_settings_json,layout_json,sort_order,created_by,updated_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute(array_merge($values, [(int)($user['id'] ?? 0) ?: null, (int)($user['id'] ?? 0) ?: null]));

        return (int)db()->lastInsertId();
    }

    public static function archive(int $id): void
    {
        self::ensureSchema();
        if ($id <= 0) {
            throw new ValidationException('Keine Vorlage gewaehlt.');
        }
        db()->prepare("UPDATE document_templates SET status='archived' WHERE id=?")->execute([$id]);
    }

    public static function duplicate(int $id, array $user): int
    {
        self::ensureSchema();
        $template = self::get($id);
        $copyName = trim(preg_replace('/^Wiederherstellung\s+/i', '', (string)($template['name'] ?? '')) ?: (string)($template['name'] ?? 'Vorlage'));
        if ($copyName === '') {
            $copyName = 'Vorlage';
        }
        $copyName .= ' Kopie';

        db()->prepare(
            'INSERT INTO document_templates(channel,code,name,category,context_type,language,status,subject_template,title_template,portal_title,portal_subtitle,button_label,show_in_portal,header_html,body_html,footer_html,filename_template,page_settings_json,layout_json,sort_order,created_by,updated_by)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            (string)($template['channel'] ?? 'email'),
            self::code((string)($template['code'] ?? 'template') . '_copy_' . date('His')),
            $copyName,
            (string)($template['category'] ?? 'general'),
            (string)($template['context_type'] ?? 'booking'),
            (string)($template['language'] ?? 'de'),
            'draft',
            (string)($template['subject_template'] ?? ''),
            (string)($template['title_template'] ?? $copyName),
            (string)($template['portal_title'] ?? ''),
            (string)($template['portal_subtitle'] ?? ''),
            (string)($template['button_label'] ?? ''),
            (int)($template['show_in_portal'] ?? 0),
            (string)($template['header_html'] ?? ''),
            (string)($template['body_html'] ?? ''),
            (string)($template['footer_html'] ?? ''),
            (string)($template['filename_template'] ?? '{code}-{reference}.pdf'),
            (string)($template['page_settings_json'] ?? json_encode(self::defaultSettings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            (string)($template['layout_json'] ?? json_encode(self::defaultLayout(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            (int)($template['sort_order'] ?? 100) + 1,
            (int)($user['id'] ?? 0) ?: null,
            (int)($user['id'] ?? 0) ?: null,
        ]);

        return (int)db()->lastInsertId();
    }

    public static function preview(int $id, array $sample = []): array
    {
        self::ensureSchema();
        $template = self::get($id);
        $context = array_replace(self::sampleContext(), $sample);
        return self::previewTemplateArray($template, $context);
    }

    public static function previewData(array $data): array
    {
        self::ensureSchema();
        $channel = self::channel((string)($data['channel'] ?? 'email'));
        $template = self::blankTemplate($channel);
        $template['channel'] = $channel;
        $template['name'] = trim((string)($data['name'] ?? $template['name']));
        $template['code'] = self::code((string)($data['code'] ?? $template['code']));
        $template['category'] = self::category((string)($data['category'] ?? $template['category']));
        $template['context_type'] = self::context((string)($data['context_type'] ?? $template['context_type']));
        $template['language'] = self::language((string)($data['language'] ?? $template['language']));
        $template['status'] = self::status((string)($data['status'] ?? $template['status']));
        $template['subject_template'] = trim((string)($data['subject_template'] ?? $template['subject_template']));
        $template['title_template'] = trim((string)($data['title_template'] ?? $template['title_template']));
        $template['portal_title'] = trim((string)($data['portal_title'] ?? $template['portal_title']));
        $template['portal_subtitle'] = trim((string)($data['portal_subtitle'] ?? $template['portal_subtitle']));
        $template['button_label'] = trim((string)($data['button_label'] ?? $template['button_label']));
        $template['show_in_portal'] = normalize_bool($data['show_in_portal'] ?? $template['show_in_portal']) ? 1 : 0;
        $template['header_html'] = self::cleanHtml((string)($data['header_html'] ?? $template['header_html']));
        $template['body_html'] = self::cleanHtml((string)($data['body_html'] ?? $template['body_html']));
        $template['footer_html'] = self::cleanHtml((string)($data['footer_html'] ?? $template['footer_html']));
        $template['filename_template'] = trim((string)($data['filename_template'] ?? $template['filename_template']));
        $template['sort_order'] = (int)($data['sort_order'] ?? $template['sort_order']);
        $template['page_settings_json'] = json_encode(
            self::settings(is_array($data['page_settings'] ?? null) ? $data['page_settings'] : []),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $template['layout_json'] = json_encode(
            self::layout($data['layout_json'] ?? ($template['layout_json'] ?? null)),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($channel !== 'customer') {
            $template['portal_title'] = '';
            $template['portal_subtitle'] = '';
            $template['show_in_portal'] = 0;
            $template['layout_json'] = json_encode(['blocks' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($channel === 'email') {
            $template['header_html'] = '';
            $template['footer_html'] = '';
        }
        if ($channel !== 'email') {
            $template['subject_template'] = '';
        }
        $context = array_replace(self::sampleContext(), ['language' => $template['language']]);
        return self::previewTemplateArray($template, $context);
    }

    private static function previewTemplateArray(array $template, array $context): array
    {
        $title = self::replace((string)($template['title_template'] ?? $template['name']), $context);
        $channel = (string)($template['channel'] ?? 'email');
        $html = $channel === 'customer'
            ? self::htmlCustomerPortalLive($template, $context, self::sampleCustomerPayload($context))
            : ($channel === 'pdf'
                ? self::htmlPdfDocument($template, $context)
                : self::htmlEmailDocument($template, $context));
        $pdf = SimplePdf::createFromHtml($title, $html);
        return ['title' => $title, 'html' => $html, 'pdf_base64' => base64_encode($pdf)];
    }

    public static function publicCustomerTemplate(?string $language = 'de'): ?array
    {
        self::ensureSchema();
        $language = self::language((string)$language);
        $stmt = db()->prepare("SELECT * FROM document_templates WHERE channel='customer' AND status='active' AND show_in_portal=1 AND language=? ORDER BY (code='customer_overview') DESC, sort_order, id LIMIT 1");
        $stmt->execute([$language]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
        $stmt->execute(['de']);
        $fallback = $stmt->fetch();
        return $fallback ?: null;
    }

    public static function customerPortalContext(array $booking, array $context = []): array
    {
        $language = self::language((string)($context['language'] ?? $booking['public_language'] ?? 'de'));
        $currency = (string)($booking['currency'] ?? 'EUR');
        $paid = (float)($booking['paid_total'] ?? $booking['paid_amount'] ?? 0);
        $total = (float)($booking['total_price'] ?? 0);
        $open = (float)($booking['open_total'] ?? max(0, $total - $paid));
        $guestName = trim((string)($booking['guest_name'] ?? ''));
        $guestFirstName = trim((string)($booking['guest_first_name'] ?? ''));
        $guestLastName = trim((string)($booking['guest_last_name'] ?? ''));
        if ($guestFirstName === '' && $guestName !== '') {
            $parts = preg_split('/\s+/', $guestName) ?: [];
            $guestFirstName = trim((string)($parts[0] ?? ''));
        }
        if ($guestLastName === '' && $guestName !== '') {
            $parts = preg_split('/\s+/', $guestName) ?: [];
            if (count($parts) > 1) {
                array_shift($parts);
                $guestLastName = trim(implode(' ', $parts));
            }
        }
        $documents = is_array($booking['documents'] ?? null) ? $booking['documents'] : [];
        $latestDocument = [];
        if ($documents) {
            $latestDocument = (array)end($documents);
            reset($documents);
        }
        $emails = is_array($booking['emails'] ?? null) ? $booking['emails'] : [];
        $latestEmail = [];
        if ($emails) {
            $latestEmail = (array)$emails[0];
        }

        return array_replace([
            'language' => $language,
            'reference' => (string)($booking['reference'] ?? ''),
            'guest_name' => $guestName,
            'guest_first_name' => $guestFirstName,
            'guest_last_name' => $guestLastName,
            'guest_company' => (string)($booking['guest_company'] ?? ''),
            'guest_address_line1' => (string)($booking['guest_address_line1'] ?? $booking['address_line1'] ?? ''),
            'guest_address_line2' => (string)($booking['guest_address_line2'] ?? $booking['address_line2'] ?? ''),
            'guest_postcode' => (string)($booking['guest_postcode'] ?? $booking['postcode'] ?? ''),
            'guest_city' => (string)($booking['guest_city'] ?? $booking['city'] ?? ''),
            'guest_country' => (string)($booking['guest_country'] ?? $booking['country'] ?? ''),
            'guest_email' => (string)($booking['guest_email'] ?? $booking['email'] ?? ''),
            'guest_phone' => (string)($booking['guest_phone'] ?? $booking['phone'] ?? ''),
            'salutation' => (string)($booking['salutation'] ?? 'Guten Tag'),
            'arrival' => self::formatDate((string)($booking['arrival'] ?? ''), $language),
            'departure' => self::formatDate((string)($booking['departure'] ?? ''), $language),
            'arrival_time' => (string)($booking['planned_arrival_time'] ?? ''),
            'departure_time' => (string)($booking['planned_departure_time'] ?? ''),
            'apartment_name' => (string)($booking['apartment_name'] ?? ''),
            'apartment_code' => (string)($booking['apartment_code'] ?? ''),
            'adults' => (string)(int)($booking['adults'] ?? 0),
            'children' => (string)(int)($booking['children'] ?? 0),
            'total_price' => self::formatMoney($total, $currency, $language),
            'paid_amount' => self::formatMoney($paid, $currency, $language),
            'open_amount' => self::formatMoney($open, $currency, $language),
            'payment_amount' => self::formatMoney((float)($booking['payment_amount'] ?? 0), $currency, $language),
            'payment_method' => (string)($booking['payment_method'] ?? ''),
            'payment_date' => self::formatDate((string)($booking['payment_date'] ?? ''), $language),
            'invoice_number' => (string)($booking['invoice_number'] ?? ($latestDocument['document_number'] ?? '')),
            'receipt_number' => (string)($booking['receipt_number'] ?? ''),
            'property_name' => (string)setting('property_name', 'StayPilot'),
            'property_address' => (string)setting('full_address', ''),
            'company_name' => (string)setting('property_name', 'StayPilot'),
            'company_address' => (string)setting('full_address', ''),
            'contact_email' => (string)setting('contact_email', ''),
            'contact_phone' => (string)setting('contact_phone', ''),
            'bank_name' => (string)setting('bank_name', ''),
            'bank_iban' => (string)setting('bank_iban', ''),
            'bank_bic' => (string)setting('bank_bic', ''),
            'tax_id' => (string)setting('tax_id', ''),
            'date' => self::formatDate(date('Y-m-d'), $language),
            'today' => self::formatDate(date('Y-m-d'), $language),
            'portal_url' => '',
            'checkin_url' => '',
            'portal_title' => 'Kundenbereich',
            'portal_subtitle' => 'Buchungen, Dokumente und Zahlungen auf einen Blick.',
            'booking_status' => (string)($booking['workflow_status_label'] ?? $booking['status'] ?? ''),
            'document_count' => (string)count($documents),
            'latest_document_title' => (string)($latestDocument['title'] ?? ''),
            'latest_document_number' => (string)($latestDocument['document_number'] ?? ''),
            'latest_document_date' => self::formatDate((string)($latestDocument['generated_at'] ?? ''), $language),
            'email_count' => (string)count($emails),
            'latest_email_subject' => (string)($latestEmail['subject'] ?? ''),
            'latest_email_date' => self::formatDate((string)($latestEmail['created_at'] ?? ''), $language),
            'latest_email_status' => (string)($latestEmail['status'] ?? ''),
            'payment_status' => (string)($booking['payment_status'] ?? ''),
        ], $context);
    }

    public static function renderPublicCustomerPortal(?string $language, array $booking, array $context = []): ?string
    {
        $template = self::publicCustomerTemplate($language);
        if (!$template) {
            return null;
        }
        $portalContext = self::customerPortalContext($booking, $context);
        return self::htmlCustomerPortalLive($template, $portalContext, [
            'documents' => is_array($context['documents'] ?? null) ? $context['documents'] : [],
            'actions' => is_array($context['actions'] ?? null) ? $context['actions'] : [],
            'emails' => is_array($context['emails'] ?? null) ? $context['emails'] : [],
            'payment_schedule' => is_array($context['payment_schedule'] ?? null) ? $context['payment_schedule'] : (is_array($booking['payment_schedule'] ?? null) ? $booking['payment_schedule'] : []),
            'payments' => is_array($context['payments'] ?? null) ? $context['payments'] : (is_array($booking['payments'] ?? null) ? $booking['payments'] : []),
            'checkin' => is_array($context['checkin'] ?? null) ? $context['checkin'] : [],
        ]);
    }


    public static function portalDocuments(array $booking, string $token): array
    {
        $rows = is_array($booking['documents'] ?? null) ? $booking['documents'] : [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = (string)($row['document_type'] ?? 'document');
            $info = self::documentTypeInfo($type);
            $number = trim((string)($row['document_number'] ?? ''));
            $date = self::formatDate((string)($row['generated_at'] ?? $row['created_at'] ?? ''), (string)($booking['public_language'] ?? 'de'));
            $metaParts = array_values(array_filter([$info['label'], $number, $date], static fn($v) => trim((string)$v) !== ''));
            $out[] = [
                'id' => (int)($row['id'] ?? 0),
                'href' => 'kunde-dokument.php?token=' . rawurlencode($token) . '&id=' . (int)($row['id'] ?? 0),
                'title' => trim((string)($row['title'] ?? '')) ?: $info['label'],
                'meta' => implode(' · ', $metaParts),
                'subtitle' => $info['description'],
                'type' => $type,
                'type_label' => $info['label'],
                'badge' => $info['badge'],
                'status' => (string)($row['status'] ?? 'generated'),
                'sort' => $info['sort'],
            ];
        }
        usort($out, static fn(array $a, array $b): int => (($a['sort'] ?? 999) <=> ($b['sort'] ?? 999)) ?: (($b['id'] ?? 0) <=> ($a['id'] ?? 0)));
        return $out;
    }

    public static function portalEmails(array $booking): array
    {
        $rows = is_array($booking['emails'] ?? null) ? $booking['emails'] : [];
        $language = (string)($booking['public_language'] ?? 'de');
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $subject = trim((string)($row['subject'] ?? '')) ?: 'E-Mail';
            $status = (string)($row['status'] ?? '');
            $detail = trim((string)($row['detail'] ?? ''));
            $out[] = [
                'subject' => $subject,
                'status' => $status,
                'date' => self::formatDate((string)($row['created_at'] ?? ''), $language),
                'detail' => $detail,
                'summary' => $detail !== '' ? (function_exists('mb_substr') ? mb_substr($detail, 0, 160) : substr($detail, 0, 160)) : '',
            ];
        }
        return $out;
    }

    public static function resolveAttachmentTemplateId(array $attachment, array $booking = []): int
    {
        self::ensureSchema();
        $templateId = (int)($attachment['document_template_id'] ?? 0);
        if ($templateId <= 0) {
            return 0;
        }
        $mode = (string)($attachment['language_mode'] ?? 'guest');
        if ($mode === 'template') {
            return $templateId;
        }
        $targetLanguage = $mode === 'fixed'
            ? self::language((string)($attachment['fixed_language'] ?? 'de'))
            : self::language((string)($booking['public_language'] ?? $booking['language'] ?? 'de'));
        $template = self::get($templateId, 'pdf');
        $code = (string)($template['code'] ?? '');
        if ($code === '' || (string)($template['language'] ?? '') === $targetLanguage) {
            return $templateId;
        }
        $stmt = db()->prepare("SELECT id FROM document_templates WHERE channel='pdf' AND code=? AND language=? AND status<>'archived' ORDER BY status='active' DESC, id DESC LIMIT 1");
        $stmt->execute([$code, $targetLanguage]);
        $match = (int)($stmt->fetchColumn() ?: 0);
        return $match > 0 ? $match : $templateId;
    }

    public static function attachmentPreviewRows(string $mailKey): array
    {
        $rows = self::attachments($mailKey);
        foreach ($rows as &$row) {
            $mode = (string)($row['language_mode'] ?? 'guest');
            $row['mode_label'] = match ($mode) {
                'fixed' => 'Feste Sprache: ' . strtoupper((string)($row['fixed_language'] ?? 'de')),
                'template' => 'Vorlagensprache: ' . strtoupper((string)($row['template_language'] ?? 'de')),
                default => 'Sprache des Gasts / der Buchung',
            };
            $row['will_generate'] = !empty($row['active']) && (string)($row['template_status'] ?? '') !== 'archived';
            $row['preview_filename'] = trim((string)($row['filename_template'] ?? '')) ?: ('{code}-{reference}.pdf');
        }
        unset($row);
        return $rows;
    }

    public static function renderAttachmentPdf(int $templateId, array $booking, array $context = [], ?string $filenameTemplate = null): ?array
    {
        self::ensureSchema();
        $template = self::get($templateId, 'pdf');
        if (($template['channel'] ?? '') !== 'pdf' || ($template['status'] ?? '') === 'archived') {
            return null;
        }
        $portalContext = self::customerPortalContext($booking, $context);
        $title = self::replace((string)($template['title_template'] ?? $template['name'] ?? 'Dokument'), $portalContext);
        $html = self::htmlPdfDocument($template, $portalContext);
        $pdf = SimplePdf::createFromHtml($title, $html);
        $filename = trim((string)($filenameTemplate ?? ''));
        if ($filename === '') {
            $filename = (string)($template['filename_template'] ?? '{code}-{reference}.pdf');
        }
        $filename = self::replace($filename, array_replace($portalContext, [
            'code' => (string)($template['code'] ?? 'dokument'),
            'template_code' => (string)($template['code'] ?? 'dokument'),
        ]));
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'dokument.pdf';
        if (!str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }
        $dir = root_path('storage/documents/mail-attachments');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $relative = 'storage/documents/mail-attachments/' . date('Ymd-His') . '-' . $filename;
        $absolute = root_path($relative);
        if (@file_put_contents($absolute, $pdf, LOCK_EX) === false) {
            throw new RuntimeException('Der feste Mail-Anhang konnte nicht erzeugt werden.');
        }
        return ['path' => $absolute, 'relative_path' => $relative, 'name' => $filename, 'mime' => 'application/pdf', 'title' => $title];
    }

    private static function documentTypeInfo(string $type): array
    {
        $map = [
            'booking_confirmation' => ['label' => 'Buchungsbestätigung', 'description' => 'Bestätigung der Buchung und Aufenthaltsdaten.', 'badge' => 'Buchung', 'sort' => 10],
            'arrival_information' => ['label' => 'Anreiseinformation', 'description' => 'Informationen für Anreise, Check-in und Aufenthalt.', 'badge' => 'Anreise', 'sort' => 20],
            'payment_overview' => ['label' => 'Zahlungsübersicht', 'description' => 'Übersicht zu Anzahlung, Restbetrag und Zahlungsstatus.', 'badge' => 'Zahlung', 'sort' => 30],
            'invoice' => ['label' => 'Rechnung', 'description' => 'Rechnungsdokument zur Buchung.', 'badge' => 'Rechnung', 'sort' => 40],
            'receipt' => ['label' => 'Quittung', 'description' => 'Bestätigung einer erhaltenen Zahlung.', 'badge' => 'Quittung', 'sort' => 50],
            'credit_note' => ['label' => 'Gutschrift', 'description' => 'Gutschrift oder Korrekturdokument.', 'badge' => 'Gutschrift', 'sort' => 60],
            'cancellation' => ['label' => 'Storno', 'description' => 'Stornierung oder Stornobestätigung.', 'badge' => 'Storno', 'sort' => 70],
        ];
        return $map[$type] ?? ['label' => 'Dokument', 'description' => 'Dokument zur Buchung.', 'badge' => 'Dokument', 'sort' => 900];
    }

    public static function attachments(string $mailKey): array
    {
        self::ensureSchema();
        $stmt = db()->prepare("SELECT a.*, t.name template_name, t.code template_code, t.language template_language, t.status template_status FROM mail_template_attachments a JOIN document_templates t ON t.id=a.document_template_id WHERE a.mail_key=? AND t.channel='pdf' ORDER BY a.sort_order, a.id");
        $stmt->execute([self::mailKey($mailKey)]);
        return $stmt->fetchAll();
    }

    public static function saveAttachments(string $mailKey, array $rows): void
    {
        self::ensureSchema();
        $mailKey = self::mailKey($mailKey);
        $pdo = db();
        $started = !$pdo->inTransaction();
        if ($started) {
            $pdo->beginTransaction();
        }
        try {
            $pdo->prepare('DELETE FROM mail_template_attachments WHERE mail_key=?')->execute([$mailKey]);
            $stmt = $pdo->prepare('INSERT INTO mail_template_attachments(mail_key,document_template_id,active,sort_order,filename_template,language_mode,fixed_language,generation_mode,store_copy) VALUES(?,?,?,?,?,?,?,?,?)');
            foreach (array_values($rows) as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $templateId = (int)($row['document_template_id'] ?? 0);
                if ($templateId <= 0) {
                    continue;
                }
                $stmt->execute([
                    $mailKey,
                    $templateId,
                    normalize_bool($row['active'] ?? 1),
                    ($index + 1) * 10,
                    trim((string)($row['filename_template'] ?? '')) ?: null,
                    in_array((string)($row['language_mode'] ?? 'guest'), ['guest', 'fixed', 'template'], true) ? (string)$row['language_mode'] : 'guest',
                    self::language((string)($row['fixed_language'] ?? 'de')),
                    in_array((string)($row['generation_mode'] ?? 'fresh'), ['fresh', 'stored'], true) ? (string)$row['generation_mode'] : 'fresh',
                    normalize_bool($row['store_copy'] ?? 1),
                ]);
            }
            if ($started) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function placeholders(): array
    {
        return self::placeholderTokenGroups(self::placeholderCatalog());
    }

    public static function customerPlaceholders(): array
    {
        return self::placeholderTokenGroups(self::customerPlaceholderCatalog());
    }

    public static function placeholderCatalog(): array
    {
        return [
            'Empfaenger' => [
                ['token' => '{guest_name}', 'label' => 'Voller Name', 'description' => 'Kompletter Name des Gasts.'],
                ['token' => '{guest_first_name}', 'label' => 'Vorname', 'description' => 'Vorname des Gasts.'],
                ['token' => '{guest_last_name}', 'label' => 'Nachname', 'description' => 'Nachname des Gasts.'],
                ['token' => '{guest_company}', 'label' => 'Firma', 'description' => 'Firma des Gasts oder Rechnungsempfaengers.'],
                ['token' => '{guest_address_line1}', 'label' => 'Strasse / Hausnummer', 'description' => 'Erste Adresszeile.'],
                ['token' => '{guest_address_line2}', 'label' => 'Adresszusatz', 'description' => 'Zweite Adresszeile.'],
                ['token' => '{guest_postcode}', 'label' => 'PLZ', 'description' => 'Postleitzahl.'],
                ['token' => '{guest_city}', 'label' => 'Ort', 'description' => 'Ort des Gasts.'],
                ['token' => '{guest_country}', 'label' => 'Land', 'description' => 'Land des Gasts.'],
                ['token' => '{guest_email}', 'label' => 'E-Mail', 'description' => 'E-Mail-Adresse des Gasts.'],
                ['token' => '{guest_phone}', 'label' => 'Telefon', 'description' => 'Telefonnummer des Gasts.'],
                ['token' => '{salutation}', 'label' => 'Anrede', 'description' => 'Anrede wie Frau, Herr oder Guten Tag.'],
            ],
            'Buchung' => [
                ['token' => '{reference}', 'label' => 'Buchungsnummer', 'description' => 'Interne oder sichtbare Referenz.'],
                ['token' => '{arrival}', 'label' => 'Anreise', 'description' => 'Anreisedatum formatiert.'],
                ['token' => '{departure}', 'label' => 'Abreise', 'description' => 'Abreisedatum formatiert.'],
                ['token' => '{arrival_time}', 'label' => 'Anreisezeit', 'description' => 'Geplante oder gespeicherte Anreisezeit.'],
                ['token' => '{departure_time}', 'label' => 'Abreisezeit', 'description' => 'Geplante oder gespeicherte Abreisezeit.'],
                ['token' => '{adults}', 'label' => 'Erwachsene', 'description' => 'Anzahl Erwachsene.'],
                ['token' => '{children}', 'label' => 'Kinder', 'description' => 'Anzahl Kinder.'],
                ['token' => '{nights}', 'label' => 'Naechte', 'description' => 'Anzahl Uebernachtungen.'],
                ['token' => '{booking_status}', 'label' => 'Buchungsstatus', 'description' => 'Status der Buchung.'],
            ],
            'Unterkunft' => [
                ['token' => '{apartment_name}', 'label' => 'Wohnung / Einheit', 'description' => 'Name der Unterkunft.'],
                ['token' => '{apartment_code}', 'label' => 'Wohnungscode', 'description' => 'Interner oder kurzer Code.'],
                ['token' => '{property_name}', 'label' => 'Objektname', 'description' => 'Name des Betriebs oder Objekts.'],
                ['token' => '{property_address}', 'label' => 'Objektadresse', 'description' => 'Adresse des Betriebs oder Objekts.'],
            ],
            'Preise & Zahlung' => [
                ['token' => '{total_price}', 'label' => 'Gesamtpreis', 'description' => 'Gesamtpreis der Buchung.'],
                ['token' => '{paid_amount}', 'label' => 'Bereits bezahlt', 'description' => 'Schon bezahlter Betrag.'],
                ['token' => '{open_amount}', 'label' => 'Offener Betrag', 'description' => 'Noch offener Restbetrag.'],
                ['token' => '{payment_amount}', 'label' => 'Zahlungsbetrag', 'description' => 'Betrag der letzten oder aktuellen Zahlung.'],
                ['token' => '{payment_method}', 'label' => 'Zahlungsart', 'description' => 'Zahlungsweg, z. B. Ueberweisung.'],
                ['token' => '{payment_date}', 'label' => 'Zahlungsdatum', 'description' => 'Datum der Zahlung.'],
                ['token' => '{payment_status}', 'label' => 'Zahlungsstatus', 'description' => 'Status wie offen oder erledigt.'],
                ['token' => '{invoice_number}', 'label' => 'Rechnungsnummer', 'description' => 'Nummer der Rechnung.'],
                ['token' => '{receipt_number}', 'label' => 'Quittungsnummer', 'description' => 'Nummer der Quittung.'],
            ],
            'Firma' => [
                ['token' => '{company_name}', 'label' => 'Firmenname', 'description' => 'Offizieller Firmenname.'],
                ['token' => '{company_address}', 'label' => 'Firmenadresse', 'description' => 'Adresse der Firma.'],
                ['token' => '{contact_email}', 'label' => 'Kontakt-E-Mail', 'description' => 'Allgemeine Kontaktadresse.'],
                ['token' => '{contact_phone}', 'label' => 'Kontakt-Telefon', 'description' => 'Allgemeine Telefonnummer.'],
                ['token' => '{bank_name}', 'label' => 'Bankname', 'description' => 'Bankverbindung fuer Dokumente.'],
                ['token' => '{bank_iban}', 'label' => 'IBAN', 'description' => 'IBAN fuer Rechnungen oder Hinweise.'],
                ['token' => '{bank_bic}', 'label' => 'BIC', 'description' => 'BIC der Bankverbindung.'],
                ['token' => '{tax_id}', 'label' => 'Steuernummer / USt-ID', 'description' => 'Rechtliche Kennung des Betriebs.'],
                ['token' => '{date}', 'label' => 'Heutiges Datum', 'description' => 'Aktuelles Datum.'],
                ['token' => '{today}', 'label' => 'Heute', 'description' => 'Aktuelles Datum.'],
            ],
            'Kundenbereich' => [
                ['token' => '{portal_title}', 'label' => 'Portal-Titel', 'description' => 'Titel des Kundenbereichs.'],
                ['token' => '{portal_subtitle}', 'label' => 'Portal-Untertitel', 'description' => 'Untertitel des Kundenbereichs.'],
                ['token' => '{portal_url}', 'label' => 'Portal-Link', 'description' => 'Direkter Link zum Kundenbereich.'],
                ['token' => '{checkin_url}', 'label' => 'Check-in-Link', 'description' => 'Direkter Link zum Online-Check-in.'],
                ['token' => '{document_count}', 'label' => 'Anzahl Dokumente', 'description' => 'Wie viele Dokumente verfuegbar sind.'],
                ['token' => '{latest_document_title}', 'label' => 'Letztes Dokument', 'description' => 'Titel des zuletzt hinterlegten Dokuments.'],
                ['token' => '{latest_document_number}', 'label' => 'Letzte Dokumentnummer', 'description' => 'Nummer des zuletzt hinterlegten Dokuments.'],
                ['token' => '{latest_document_date}', 'label' => 'Letztes Dokumentdatum', 'description' => 'Datum des zuletzt hinterlegten Dokuments.'],
            ],
            'Putzplan & Betrieb' => [
                ['token' => '{task_type}', 'label' => 'Aufgabentyp', 'description' => 'Art der Aufgabe oder Reinigung.'],
                ['token' => '{task_date}', 'label' => 'Aufgabendatum', 'description' => 'Datum der Aufgabe.'],
                ['token' => '{task_notes}', 'label' => 'Aufgabenhinweis', 'description' => 'Freitext zur Aufgabe.'],
                ['token' => '{cleaning_team}', 'label' => 'Team', 'description' => 'Zugewiesenes Team.'],
                ['token' => '{linen_change}', 'label' => 'Waeschewechsel', 'description' => 'Ob Waeschewechsel noetig ist.'],
            ],
        ];
    }

    public static function customerPlaceholderCatalog(): array
    {
        return [
            'Gast' => [
                ['token' => '{guest_name}', 'label' => 'Gastname', 'description' => 'Voller Name des Gasts.'],
                ['token' => '{guest_first_name}', 'label' => 'Vorname', 'description' => 'Vorname des Gasts.'],
                ['token' => '{guest_last_name}', 'label' => 'Nachname', 'description' => 'Nachname des Gasts.'],
                ['token' => '{guest_email}', 'label' => 'E-Mail', 'description' => 'E-Mail-Adresse des Gasts.'],
                ['token' => '{guest_phone}', 'label' => 'Telefon', 'description' => 'Telefonnummer des Gasts.'],
                ['token' => '{salutation}', 'label' => 'Anrede', 'description' => 'Anrede fuer Begruessungen.'],
            ],
            'Aufenthalt' => [
                ['token' => '{arrival}', 'label' => 'Anreise', 'description' => 'Anreisedatum.'],
                ['token' => '{departure}', 'label' => 'Abreise', 'description' => 'Abreisedatum.'],
                ['token' => '{arrival_time}', 'label' => 'Anreisezeit', 'description' => 'Geplante Anreisezeit.'],
                ['token' => '{departure_time}', 'label' => 'Abreisezeit', 'description' => 'Geplante Abreisezeit.'],
                ['token' => '{nights}', 'label' => 'Naechte', 'description' => 'Anzahl Uebernachtungen.'],
                ['token' => '{adults}', 'label' => 'Erwachsene', 'description' => 'Anzahl Erwachsene.'],
                ['token' => '{children}', 'label' => 'Kinder', 'description' => 'Anzahl Kinder.'],
                ['token' => '{apartment_name}', 'label' => 'Wohnung', 'description' => 'Name der Unterkunft.'],
                ['token' => '{apartment_code}', 'label' => 'Wohnungscode', 'description' => 'Kurzcode der Unterkunft.'],
            ],
            'Buchung' => [
                ['token' => '{reference}', 'label' => 'Buchungsnummer', 'description' => 'Sichtbare Referenz der Buchung.'],
                ['token' => '{booking_status}', 'label' => 'Buchungsstatus', 'description' => 'Aktueller Status der Buchung.'],
                ['token' => '{portal_title}', 'label' => 'Portal-Titel', 'description' => 'Titel im Kundenbereich.'],
                ['token' => '{portal_subtitle}', 'label' => 'Portal-Untertitel', 'description' => 'Untertitel im Kundenbereich.'],
            ],
            'Zahlung' => [
                ['token' => '{payment_status}', 'label' => 'Zahlungsstatus', 'description' => 'Status der Zahlung.'],
                ['token' => '{total_price}', 'label' => 'Gesamtpreis', 'description' => 'Gesamtpreis der Buchung.'],
                ['token' => '{paid_amount}', 'label' => 'Bezahlt', 'description' => 'Bereits gezahlter Betrag.'],
                ['token' => '{open_amount}', 'label' => 'Offen', 'description' => 'Noch offener Betrag.'],
                ['token' => '{payment_amount}', 'label' => 'Zahlungsbetrag', 'description' => 'Betrag der letzten Zahlung.'],
                ['token' => '{payment_method}', 'label' => 'Zahlungsart', 'description' => 'Zahlungsweg.'],
                ['token' => '{payment_date}', 'label' => 'Zahlungsdatum', 'description' => 'Datum der Zahlung.'],
            ],
            'Dokumente' => [
                ['token' => '{document_count}', 'label' => 'Anzahl Dokumente', 'description' => 'Anzahl sichtbarer Dokumente.'],
                ['token' => '{latest_document_title}', 'label' => 'Letztes Dokument', 'description' => 'Titel des zuletzt hinterlegten Dokuments.'],
                ['token' => '{latest_document_number}', 'label' => 'Letzte Dokumentnummer', 'description' => 'Nummer des zuletzt hinterlegten Dokuments.'],
                ['token' => '{latest_document_date}', 'label' => 'Letztes Dokumentdatum', 'description' => 'Datum des letzten Dokuments.'],
                ['token' => '{invoice_number}', 'label' => 'Rechnungsnummer', 'description' => 'Nummer der Rechnung.'],
                ['token' => '{receipt_number}', 'label' => 'Quittungsnummer', 'description' => 'Nummer der Quittung.'],
            ],
            'E-Mails' => [
                ['token' => '{email_count}', 'label' => 'Anzahl E-Mails', 'description' => 'Anzahl sichtbarer E-Mails.'],
                ['token' => '{latest_email_subject}', 'label' => 'Letzter E-Mail-Betreff', 'description' => 'Betreff der zuletzt protokollierten E-Mail.'],
                ['token' => '{latest_email_date}', 'label' => 'Letzte E-Mail am', 'description' => 'Zeitpunkt der zuletzt protokollierten E-Mail.'],
                ['token' => '{latest_email_status}', 'label' => 'Letzter E-Mail-Status', 'description' => 'Status der zuletzt protokollierten E-Mail.'],
            ],
            'Firma' => [
                ['token' => '{company_name}', 'label' => 'Firmenname', 'description' => 'Offizieller Name des Betriebs.'],
                ['token' => '{company_address}', 'label' => 'Firmenadresse', 'description' => 'Adresse des Betriebs.'],
                ['token' => '{property_name}', 'label' => 'Objektname', 'description' => 'Name des Hauses oder Betriebs.'],
                ['token' => '{property_address}', 'label' => 'Objektadresse', 'description' => 'Adresse des Hauses oder Betriebs.'],
                ['token' => '{contact_email}', 'label' => 'Kontakt-E-Mail', 'description' => 'Kontaktadresse.'],
                ['token' => '{contact_phone}', 'label' => 'Kontakt-Telefon', 'description' => 'Telefonnummer.'],
                ['token' => '{bank_name}', 'label' => 'Bankname', 'description' => 'Bankverbindung.'],
                ['token' => '{bank_iban}', 'label' => 'IBAN', 'description' => 'IBAN der Bankverbindung.'],
                ['token' => '{bank_bic}', 'label' => 'BIC', 'description' => 'BIC der Bankverbindung.'],
                ['token' => '{tax_id}', 'label' => 'Steuernummer / USt-ID', 'description' => 'Rechtliche Kennung.'],
                ['token' => '{date}', 'label' => 'Heutiges Datum', 'description' => 'Aktuelles Datum.'],
            ],
            'Links' => [
                ['token' => '{portal_url}', 'label' => 'Portal-Link', 'description' => 'Link zum Kundenbereich.'],
                ['token' => '{checkin_url}', 'label' => 'Check-in-Link', 'description' => 'Link zum Online-Check-in.'],
            ],
        ];
    }

    private static function blankTemplate(string $channel): array
    {
        $channel = self::channel($channel);
        return [
            'id' => 0,
            'channel' => $channel,
            'code' => '',
            'name' => '',
            'category' => 'general',
            'context_type' => $channel === 'customer' ? 'portal' : 'booking',
            'language' => 'de',
            'status' => 'active',
            'subject_template' => '',
            'title_template' => $channel === 'customer' ? 'Kundenbereich' : ($channel === 'pdf' ? 'Neues PDF-Dokument' : 'Neue E-Mail'),
            'portal_title' => $channel === 'customer' ? 'Kundenbereich' : '',
            'portal_subtitle' => $channel === 'customer' ? 'Buchungen, Dokumente und Zahlungen im Blick.' : '',
            'button_label' => $channel === 'customer' ? 'Kundenbereich oeffnen' : ($channel === 'pdf' ? 'PDF anzeigen' : 'E-Mail anzeigen'),
            'show_in_portal' => $channel === 'customer' ? 1 : 0,
            'header_html' => '',
            'body_html' => $channel === 'customer'
                ? '<p>Willkommen {guest_name}</p><p>Hier sehen Sie Ihre Buchung, Dokumente und Zahlungen.</p>'
                : ($channel === 'pdf'
                    ? '<p>Guten Tag {guest_name},</p><p>vielen Dank. Hiermit erhalten Sie die Informationen zu Ihrer Buchung {reference}.</p>'
                    : '<p>Guten Tag {guest_name},</p><p>Ihr Text ...</p>'),
            'footer_html' => '',
            'filename_template' => 'dokument-{reference}.pdf',
            'page_settings_json' => json_encode(self::defaultSettings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'layout_json' => json_encode(self::defaultLayout(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'sort_order' => 100,
        ];
    }

    private static function htmlEmailDocument(array $template, array $context): string
    {
        $settings = json_decode((string)($template['page_settings_json'] ?? ''), true) ?: self::defaultSettings();
        $body = self::replaceEscaped((string)($template['body_html'] ?? ''), $context);
        $title = self::replace((string)($template['subject_template'] ?? $template['title_template'] ?? $template['name']), $context);
        $accent = self::h((string)($settings['accent'] ?? '#2563eb'));
        $margin = max(0, min(60, (int)($settings['margin'] ?? 24)));
        $emailBackground = self::h((string)($settings['email_bg'] ?? '#ffffff'));
        $logoUrl = trim((string)($settings['logo_url'] ?? ''));
        $logoWidth = max(40, min(320, (int)($settings['logo_width'] ?? 160)));
        $showLogo = normalize_bool($settings['show_logo'] ?? 1);
        $logoHtml = '';
        if ($showLogo && $logoUrl !== '') {
            $src = self::h((preg_match('~^https?://~i', $logoUrl) ? $logoUrl : '../' . ltrim($logoUrl, '/')));
            $logoHtml = '<div class="logo"><img src="' . $src . '" alt="Logo" style="max-width:' . $logoWidth . 'px;max-height:90px"></div>';
        }

        return '<!doctype html><html lang="de"><head><meta charset="utf-8"><style>'
            . 'body{font-family:Arial,sans-serif;color:#172033;margin:0;background:#eef3f9;padding:24px}'
            . '.mail{max-width:760px;margin:0 auto;background:' . $emailBackground . ';border-radius:20px;box-shadow:0 16px 44px rgba(15,23,42,.12);overflow:hidden}'
            . '.mail-inner{padding:' . max(12, (int)round($margin * 1.6)) . 'px}'
            . '.logo{margin-bottom:16px}.logo img{display:block;height:auto}'
            . '.head{border-top:6px solid ' . $accent . ';padding-bottom:12px;margin-bottom:24px}'
            . '.subject{font-size:28px;line-height:1.15;margin:0 0 10px;color:#0f172a}'
            . '.body{line-height:1.6;font-size:15px}.body p{margin:0 0 14px}'
            . '.foot{margin-top:26px;padding-top:14px;border-top:1px solid #dbe4f0;color:#64748b;font-size:13px}'
            . '.button{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:' . $accent . ';color:#fff;text-decoration:none;font-weight:700;padding:12px 18px}'
            . 'table{width:100%;border-collapse:collapse;margin:14px 0}td,th{border:1px solid #dbe4f0;padding:8px 10px;text-align:left}'
            . '</style></head><body><main class="mail"><div class="mail-inner"><section class="head">' . $logoHtml . '<h1 class="subject">' . self::h($title) . '</h1></section><section class="body">' . $body . '</section></div></main></body></html>';
    }

    private static function htmlPdfDocument(array $template, array $context): string
    {
        $settings = json_decode((string)($template['page_settings_json'] ?? ''), true) ?: self::defaultSettings();
        $body = self::replaceEscaped((string)($template['body_html'] ?? ''), $context);
        $header = self::replaceEscaped((string)($template['header_html'] ?? ''), $context);
        $footer = self::replaceEscaped((string)($template['footer_html'] ?? ''), $context);
        $title = self::replace((string)($template['title_template'] ?? $template['name']), $context);
        $accent = self::h((string)($settings['accent'] ?? '#2563eb'));
        $margin = max(0, min(60, (int)($settings['margin'] ?? 24)));
        $logoUrl = trim((string)($settings['logo_url'] ?? ''));
        $logoWidth = max(40, min(320, (int)($settings['logo_width'] ?? 160)));
        $showLogo = normalize_bool($settings['show_logo'] ?? 1);
        $logoHtml = '';
        if ($showLogo && $logoUrl !== '') {
            $src = self::h((preg_match('~^https?://~i', $logoUrl) ? $logoUrl : '../' . ltrim($logoUrl, '/')));
            $logoHtml = '<div class="logo"><img src="' . $src . '" alt="Logo" style="max-width:' . $logoWidth . 'px;max-height:90px"></div>';
        }

        return '<!doctype html><html lang="de"><head><meta charset="utf-8"><style>'
            . 'body{font-family:Arial,sans-serif;color:#172033;margin:0;background:#f5f7fb}'
            . '.page{width:210mm;max-width:860px;margin:0 auto;background:#fff;padding:' . $margin . 'mm;min-height:297mm;box-sizing:border-box}'
            . '.logo{margin-bottom:16px}.logo img{display:block;height:auto}'
            . '.head{border-bottom:4px solid ' . $accent . ';padding-bottom:14px;margin-bottom:24px}'
            . '.foot{border-top:1px solid #dbe4f0;margin-top:34px;padding-top:14px;color:#64748b;font-size:13px}'
            . 'h1{margin:0 0 12px;color:#0f172a}h2{margin:24px 0 10px;color:#0f172a}p{line-height:1.55;margin:0 0 12px}.page-break{break-before:page;page-break-before:always;border-top:2px dashed #cbd5e1;margin:24px 0;padding-top:8px;color:#64748b}table{width:100%;border-collapse:collapse;margin:14px 0}td,th{border:1px solid #dbe4f0;padding:8px 10px;text-align:left;vertical-align:top}'
            . '</style></head><body><main class="page"><section class="head">' . $logoHtml . '<h1>' . self::h($title) . '</h1>' . $header . '</section><section>' . $body . '</section><section class="foot">' . $footer . '</section></main></body></html>';
    }

    private static function htmlCustomerDocument(array $template, array $context): string
    {
        return self::htmlCustomerPortalLive($template, $context, []);
    }

    private static function htmlCustomerPortalLive(array $template, array $context, array $payload): string
    {
        $layout = json_decode((string)($template['layout_json'] ?? ''), true) ?: self::defaultLayout();
        $title = self::replace((string)($template['portal_title'] ?? $template['title_template'] ?? $template['name']), $context);
        $subtitle = self::replace((string)($template['portal_subtitle'] ?? ''), $context);
        $body = self::replaceEscaped((string)($template['body_html'] ?? ''), $context);
        $cards = '';
        $firstHeroRendered = false;
        $hasDocumentsBlock = false;
        $hasEmailsBlock = false;

        foreach ((array)($layout['blocks'] ?? []) as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = (string)($block['type'] ?? 'card');
            $blockTitle = self::replace((string)($block['title'] ?? 'Bereich'), $context);
            $blockSubtitle = self::replace((string)($block['subtitle'] ?? ''), $context);

            if ($type === 'hero') {
                $buttonLabel = trim(self::replace((string)($block['button_label'] ?? ''), $context));
                $buttonHref = trim(self::replace((string)($block['button_href'] ?? ''), $context));
                $buttonHtml = '';
                if ($buttonLabel !== '') {
                    $buttonHtml = $buttonHref !== ''
                        ? '<a class="portal-cta" href="' . self::h($buttonHref) . '">' . self::h($buttonLabel) . '</a>'
                        : '<span class="portal-cta">' . self::h($buttonLabel) . '</span>';
                }
                $cards .= '<section class="portal-hero"><div><span>Kundenbereich</span><h1>' . self::h($blockTitle ?: $title) . '</h1><p>' . self::h($blockSubtitle ?: $subtitle) . '</p></div>' . $buttonHtml . '</section>' . self::customerPortalOverviewHtml($context, $payload);
                $firstHeroRendered = true;
                continue;
            }

            if ($type === 'image') {
                $image = (array)(((array)($block['items'] ?? []))[0] ?? []);
                $src = trim(self::replace((string)($image['text'] ?? ''), $context));
                $imgHtml = $src !== ''
                    ? '<img src="' . self::h($src) . '" alt="' . self::h(self::replace((string)($image['title'] ?? $blockTitle), $context)) . '" style="width:100%;height:auto;max-height:420px;object-fit:cover;border-radius:12px;display:block">'
                    : '<div class="portal-item"><b>Kein Bild</b><span>Bitte ein Bild aus der Galerie waehlen.</span></div>';
                $cards .= '<section class="portal-card"><h2>' . self::h($blockTitle) . '</h2><p>' . self::h($blockSubtitle) . '</p>' . $imgHtml . '</section>';
                continue;
            }

            if ($type === 'gallery') {
                $gallery = '';
                foreach ((array)($block['items'] ?? []) as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $src = trim(self::replace((string)($item['text'] ?? ''), $context));
                    if ($src === '') {
                        continue;
                    }
                    $gallery .= '<div class="portal-item"><img src="' . self::h($src) . '" alt="' . self::h(self::replace((string)($item['title'] ?? 'Bild'), $context)) . '" style="width:100%;height:180px;object-fit:cover;border-radius:10px;display:block;margin-bottom:10px"><b>' . self::h(self::replace((string)($item['title'] ?? 'Bild'), $context)) . '</b></div>';
                }
                if ($gallery === '') {
                    $gallery = '<div class="portal-item"><b>Keine Bilder</b><span>Bitte Bilder aus der Galerie hinzufuegen.</span></div>';
                }
                $cards .= '<section class="portal-card"><h2>' . self::h($blockTitle) . '</h2><p>' . self::h($blockSubtitle) . '</p><div class="portal-grid">' . $gallery . '</div></section>';
                continue;
            }

            $items = '';
            if (!in_array($type, ['payment','status','documents','emails','actions'], true)) {
                foreach ((array)($block['items'] ?? []) as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $items .= '<div class="portal-item"><b>' . self::h(self::replace((string)($item['title'] ?? 'Eintrag'), $context)) . '</b><span>' . self::h(self::replace((string)($item['text'] ?? ''), $context)) . '</span></div>';
                }
            }

            if ($type === 'payment') {
                $total = (string)($context['total_price'] ?? '');
                $paid = (string)($context['paid_amount'] ?? '');
                $open = (string)($context['open_amount'] ?? '');
                $payStatus = (string)($context['payment_status'] ?? '');
                $items .= '<div class="portal-item portal-pay"><small class="portal-badge">Gesamt</small><b>' . self::h($total) . '</b><span>Buchungsbetrag</span></div>';
                $items .= '<div class="portal-item portal-pay"><small class="portal-badge ok">Erhalten</small><b>' . self::h($paid) . '</b><span>bereits verbucht</span></div>';
                $items .= '<div class="portal-item portal-pay"><small class="portal-badge warn">Offen</small><b>' . self::h($open) . '</b><span>' . self::h($payStatus) . '</span></div>';
                $items .= self::customerPortalPaymentScheduleHtml($payload);
            }

            if ($type === 'status') {
                foreach ((array)($context['timeline'] ?? []) as $step) {
                    if (!is_array($step)) {
                        continue;
                    }
                    $cls = (string)($step['class'] ?? '');
                    $badge = $cls === 'done' ? 'OK' : ($cls === 'warn' ? 'Prüfen' : 'Offen');
                    $items .= '<div class="portal-item portal-status ' . self::h($cls) . '"><small class="portal-badge">' . self::h($badge) . '</small><b>' . self::h((string)($step['title'] ?? 'Status')) . '</b><span>' . self::h((string)($step['text'] ?? '')) . '</span></div>';
                }
            }

            if ($type === 'documents') {
                $hasDocumentsBlock = true;
                foreach ((array)($payload['documents'] ?? []) as $document) {
                    if (!is_array($document)) {
                        continue;
                    }
                    $items .= '<a class="portal-item portal-link" href="' . self::h((string)($document['href'] ?? '#')) . '" target="_blank" rel="noopener"><small class="portal-badge">' . self::h((string)($document['badge'] ?? $document['type_label'] ?? 'Dokument')) . '</small><b>' . self::h((string)($document['title'] ?? 'Dokument')) . '</b><span>' . self::h((string)($document['meta'] ?? '')) . '</span>' . (((string)($document['subtitle'] ?? '')) !== '' ? '<em>' . self::h((string)$document['subtitle']) . '</em>' : '') . '</a>';
                }
            }

            if ($type === 'emails') {
                $hasEmailsBlock = true;
                foreach ((array)($payload['emails'] ?? []) as $mail) {
                    if (!is_array($mail)) {
                        continue;
                    }
                    $items .= '<div class="portal-item"><small class="portal-badge">E-Mail</small><b>' . self::h((string)($mail['subject'] ?? 'E-Mail')) . '</b><span>' . self::h(trim((string)($mail['status'] ?? '') . ' · ' . (string)($mail['date'] ?? ''))) . '</span>' . (((string)($mail['summary'] ?? $mail['detail'] ?? '')) !== '' ? '<em>' . self::h((string)($mail['summary'] ?? $mail['detail'])) . '</em>' : '') . '</div>';
                }
            }

            if ($type === 'actions') {
                foreach ((array)($block['items'] ?? []) as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $href = trim(self::replace((string)($item['href'] ?? ''), $context));
                    $label = trim(self::replace((string)($item['title'] ?? 'Aktion'), $context));
                    $desc = trim(self::replace((string)($item['text'] ?? ''), $context));
                    if ($href !== '') {
                        $items .= '<a class="portal-item portal-link portal-action" href="' . self::h($href) . '"><b>' . self::h($label ?: 'Aktion') . '</b><span>' . self::h($desc) . '</span></a>';
                    }
                }
                foreach ((array)($payload['actions'] ?? []) as $action) {
                    if (!is_array($action)) {
                        continue;
                    }
                    $href = trim((string)($action['href'] ?? ''));
                    if ($href === '') {
                        continue;
                    }
                    $items .= '<a class="portal-item portal-link portal-action" href="' . self::h($href) . '"><b>' . self::h((string)($action['title'] ?? 'Aktion')) . '</b><span>' . self::h((string)($action['text'] ?? '')) . '</span></a>';
                }
            }

            if ($items === '') {
                $items = '<div class="portal-item"><b>Hinweis</b><span>Dieser Bereich ist noch leer.</span></div>';
            }

            $cards .= '<section class="portal-card"><h2>' . self::h($blockTitle) . '</h2><p>' . self::h($blockSubtitle) . '</p><div class="portal-grid">' . $items . '</div></section>';
        }

        if (!$hasDocumentsBlock && !empty($payload['documents'])) {
            $items = '';
            foreach ((array)$payload['documents'] as $document) {
                if (!is_array($document)) {
                    continue;
                }
                $items .= '<a class="portal-item portal-link" href="' . self::h((string)($document['href'] ?? '#')) . '" target="_blank" rel="noopener"><small class="portal-badge">' . self::h((string)($document['badge'] ?? $document['type_label'] ?? 'Dokument')) . '</small><b>' . self::h((string)($document['title'] ?? 'Dokument')) . '</b><span>' . self::h((string)($document['meta'] ?? '')) . '</span>' . (((string)($document['subtitle'] ?? '')) !== '' ? '<em>' . self::h((string)$document['subtitle']) . '</em>' : '') . '</a>';
            }
            if ($items !== '') {
                $cards .= '<section class="portal-card"><h2>Dokumente</h2><p>Diese Unterlagen sind fuer den Gast direkt erreichbar.</p><div class="portal-grid">' . $items . '</div></section>';
            }
        }

        if (!$hasEmailsBlock && !empty($payload['emails'])) {
            $items = '';
            foreach ((array)$payload['emails'] as $mail) {
                if (!is_array($mail)) {
                    continue;
                }
                $items .= '<div class="portal-item"><small class="portal-badge">E-Mail</small><b>' . self::h((string)($mail['subject'] ?? 'E-Mail')) . '</b><span>' . self::h(trim((string)($mail['status'] ?? '') . ' · ' . (string)($mail['date'] ?? ''))) . '</span>' . (((string)($mail['summary'] ?? $mail['detail'] ?? '')) !== '' ? '<em>' . self::h((string)($mail['summary'] ?? $mail['detail'])) . '</em>' : '') . '</div>';
            }
            if ($items !== '') {
                $cards .= '<section class="portal-card"><h2>E-Mails</h2><p>Hier sieht der Gast, welche Nachrichten bereits versendet wurden.</p><div class="portal-grid">' . $items . '</div></section>';
            }
        }

        if (!$firstHeroRendered) {
            $cards = self::customerPortalOverviewHtml($context, $payload) . $cards;
        }

        if ($cards === '') {
            $cards = '<section class="portal-card"><h2>' . self::h($title) . '</h2><p>' . self::h($subtitle) . '</p></section>';
        }

        return '<!doctype html><html lang="' . self::h((string)($context['language'] ?? 'de')) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'
            . 'body{font-family:Inter,system-ui,-apple-system,Segoe UI,Arial,sans-serif;color:#172033;margin:0;background:radial-gradient(circle at 8% 0,#dbeafe 0,transparent 30%),radial-gradient(circle at 100% 0,#ccfbf1 0,transparent 28%),linear-gradient(180deg,#eff6ff,#f8fafc)}'
            . '.portal{max-width:1180px;margin:0 auto;padding:28px}.portal-hero{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:32px;border:1px solid #bfdbfe;border-radius:26px;background:linear-gradient(135deg,#0f172a,#1d4ed8 50%,#0f766e);color:#fff;box-shadow:0 24px 70px rgba(37,99,235,.22);overflow:hidden;position:relative}.portal-hero:after{content:"";position:absolute;right:-70px;top:-90px;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.13)}'
            . '.portal-hero span{display:inline-flex;padding:6px 11px;border-radius:999px;background:rgba(255,255,255,.18);font-size:12px;font-weight:900;margin-bottom:10px}.portal-hero h1{margin:0 0 8px;font-size:clamp(28px,4.5vw,50px);line-height:1.05;letter-spacing:-.04em}.portal-hero p{margin:0;max-width:680px;color:rgba(255,255,255,.9);font-size:17px}.portal-cta{position:relative;z-index:2;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:13px 18px;background:#fff;color:#1d4ed8;font-weight:900;white-space:nowrap;text-decoration:none;box-shadow:0 18px 38px rgba(15,23,42,.18)}'
            . '.portal-overview{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin:16px 0}.portal-overview-card{border:1px solid #dbe4f0;border-radius:18px;background:rgba(255,255,255,.92);padding:14px;box-shadow:0 12px 30px rgba(15,23,42,.06)}.portal-overview-card small{display:block;color:#64748b;font-weight:800;text-transform:uppercase;letter-spacing:.05em;font-size:11px;margin-bottom:5px}.portal-overview-card b{font-size:18px;line-height:1.15}.portal-overview-card.warn{border-color:#fed7aa;background:#fff7ed}.portal-overview-card.ok{border-color:#bbf7d0;background:#f0fdf4}'
            . '.portal-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:18px}.portal-card{border:1px solid #dbe4f0;border-radius:20px;background:#fff;padding:20px;box-shadow:0 14px 34px rgba(15,23,42,.06);margin-top:16px}.portal-card h2{margin:0 0 6px;font-size:22px;letter-spacing:-.02em}.portal-card p{margin:0 0 14px;color:#526179}.portal-item{border:1px solid #e2e8f0;border-radius:16px;padding:14px;background:#f8fafc}.portal-item b{display:block;margin-bottom:3px}.portal-item span{display:block;color:#526179;font-size:13px;line-height:1.45}.portal-item em{display:block;margin-top:6px;color:#64748b;font-size:12px;line-height:1.45;font-style:normal}.portal-badge{display:inline-flex;margin:0 0 6px;padding:4px 8px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:900;font-size:11px}.portal-badge.ok{background:#dcfce7;color:#166534}.portal-badge.warn{background:#fef3c7;color:#92400e}.portal-link{text-decoration:none;color:#172033;background:#fff}.portal-link:hover{border-color:#93c5fd;box-shadow:0 12px 24px rgba(37,99,235,.10)}.portal-action{border-color:#bfdbfe;background:#eff6ff}.portal-pay b{font-size:22px}.portal-status.done{border-color:#bbf7d0;background:#f0fdf4}.portal-status.warn{border-color:#fed7aa;background:#fff7ed}.portal-table{grid-column:1/-1;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;background:#fff}.portal-row{display:grid;grid-template-columns:1.3fr .8fr .7fr;gap:8px;padding:11px 13px;border-top:1px solid #eef2f7}.portal-row:first-child{border-top:0}.portal-row small{color:#64748b}.portal-body{margin-top:16px;border:1px solid #dbe4f0;border-radius:20px;background:#fff;padding:20px}.portal-next{display:grid;grid-template-columns:1.1fr .9fr;gap:16px;align-items:stretch}.portal-next-main{background:linear-gradient(135deg,#ffffff,#eef6ff);border-color:#bfdbfe}.portal-next-main h2{font-size:26px}.portal-next-main .portal-cta{margin-top:8px;background:#1d4ed8;color:#fff}.portal-mini-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.portal-mini{border:1px solid #e2e8f0;background:#f8fafc;border-radius:15px;padding:12px}.portal-mini small{display:block;color:#64748b;font-weight:800;margin-bottom:4px}.portal-mini b{font-size:16px}.portal-contact-row{display:grid;gap:8px}.portal-contact-row a{color:#1d4ed8;font-weight:900;text-decoration:none}.portal-meter{height:12px;border-radius:999px;background:#e2e8f0;overflow:hidden;margin:12px 0}.portal-meter span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#22c55e,#2563eb)}.portal-safe-note{margin-top:12px;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;border-radius:14px;padding:12px;font-weight:800}'
            . '@media(max-width:980px){.portal-overview,.portal-grid,.portal-next{grid-template-columns:repeat(2,minmax(0,1fr))}.portal-hero{display:block}.portal-cta{margin-top:14px}.portal-row{grid-template-columns:1fr}}@media(max-width:760px){.portal-next{grid-template-columns:1fr}.portal-mini-grid{grid-template-columns:1fr}}@media(max-width:620px){.portal{padding:14px}.portal-overview,.portal-grid{grid-template-columns:1fr}.portal-card,.portal-hero{border-radius:18px;padding:18px}}'
            . '</style></head><body><main class="portal">' . $cards . ($body !== '' ? '<section class="portal-body">' . $body . '</section>' : '') . '</main></body></html>';
    }


    private static function customerPortalOverviewHtml(array $context, array $payload): string
    {
        $open = trim((string)($context['open_amount'] ?? ''));
        $payment = trim((string)($context['payment_status'] ?? ''));
        $documentCount = (string)count((array)($payload['documents'] ?? []));
        $emailCount = (string)count((array)($payload['emails'] ?? []));
        $checkin = (array)($payload['checkin'] ?? []);
        $checkinStatus = trim((string)($checkin['status'] ?? '')) ?: 'offen';
        $bookingStatus = trim((string)($context['booking_status'] ?? '')) ?: 'aktuell';
        $openWarn = ($open !== '' && !preg_match('/^0([,.]00)?\s*/', $open));

        $overview = '<section class="portal-overview" aria-label="Kundenbereich Uebersicht">'
            . '<div class="portal-overview-card ok"><small>Buchung</small><b>' . self::h($bookingStatus) . '</b></div>'
            . '<div class="portal-overview-card ' . ($openWarn ? 'warn' : 'ok') . '"><small>Zahlung offen</small><b>' . self::h($open ?: '0,00') . '</b><br><span>' . self::h($payment) . '</span></div>'
            . '<div class="portal-overview-card"><small>Dokumente</small><b>' . self::h($documentCount) . '</b></div>'
            . '<div class="portal-overview-card"><small>Online-Check-in</small><b>' . self::h($checkinStatus) . '</b></div>'
            . '<div class="portal-overview-card"><small>Nachrichten</small><b>' . self::h($emailCount) . '</b></div>'
            . '</section>';

        return $overview . self::customerPortalNextStepHtml($context, $payload, $openWarn);
    }

    private static function customerPortalNextStepHtml(array $context, array $payload, bool $openWarn): string
    {
        $checkin = (array)($payload['checkin'] ?? []);
        $missing = (int)($checkin['missing_count'] ?? 0);
        $checkinStatus = trim((string)($checkin['status'] ?? ''));
        $checkinUrl = trim((string)($context['checkin_url'] ?? ''));
        $portalUrl = trim((string)($context['portal_url'] ?? ''));
        $documents = (array)($payload['documents'] ?? []);
        $latestDocument = is_array($documents[0] ?? null) ? (array)$documents[0] : [];

        $nextTitle = 'Alles aktuell';
        $nextText = 'Ihre Buchungsinformationen sind im Kundenbereich zusammengefasst. Aenderungen erscheinen hier automatisch.';
        $ctaLabel = '';
        $ctaHref = '';
        $badge = 'OK';
        $badgeClass = 'ok';

        if ($missing > 0 || in_array($checkinStatus, ['open', 'incomplete'], true)) {
            $nextTitle = 'Online-Check-in ergaenzen';
            $nextText = $missing > 0
                ? 'Es fehlen noch ' . $missing . ' Angaben oder Dateien fuer den Check-in.'
                : 'Der Online-Check-in ist noch nicht vollstaendig abgeschlossen.';
            $ctaLabel = 'Check-in oeffnen';
            $ctaHref = $checkinUrl;
            $badge = 'Bitte pruefen';
            $badgeClass = 'warn';
        } elseif ($openWarn) {
            $nextTitle = 'Zahlungsstand pruefen';
            $nextText = 'Im Zahlungsbereich sehen Sie offene Betraege, Faelligkeiten und bereits erfasste Zahlungen.';
            $badge = 'Offen';
            $badgeClass = 'warn';
        } elseif (!empty($latestDocument['href'])) {
            $nextTitle = 'Dokumente bereithalten';
            $nextText = 'Die neuesten Dokumente sind im Kundenbereich abrufbar und koennen direkt geoeffnet werden.';
            $ctaLabel = 'Neuestes Dokument oeffnen';
            $ctaHref = (string)$latestDocument['href'];
        }

        if ($ctaHref === '' && $portalUrl !== '') {
            $ctaHref = $portalUrl;
        }

        $cta = ($ctaLabel !== '' && $ctaHref !== '')
            ? '<a class="portal-cta" href="' . self::h($ctaHref) . '">' . self::h($ctaLabel) . '</a>'
            : '';

        $arrival = trim((string)($context['arrival'] ?? ''));
        $departure = trim((string)($context['departure'] ?? ''));
        $arrivalTime = trim((string)($context['arrival_time'] ?? ''));
        $departureTime = trim((string)($context['departure_time'] ?? ''));
        $apartment = trim((string)($context['apartment_name'] ?? ''));
        $guests = trim((string)($context['adults'] ?? ''));
        $email = trim((string)($context['contact_email'] ?? ''));
        $phone = trim((string)($context['contact_phone'] ?? ''));
        $openAmount = trim((string)($context['open_amount'] ?? ''));
        $paidAmount = trim((string)($context['paid_amount'] ?? ''));
        $totalAmount = trim((string)($context['total_price'] ?? ''));

        $meterWidth = '0';
        $paidNumber = self::moneyNumber($paidAmount);
        $totalNumber = self::moneyNumber($totalAmount);
        if ($totalNumber > 0) {
            $meterWidth = (string)max(0, min(100, round($paidNumber / $totalNumber * 100)));
        }

        $contact = '';
        if ($email !== '') {
            $contact .= '<span>E-Mail: <a href="mailto:' . self::h($email) . '">' . self::h($email) . '</a></span>';
        }
        if ($phone !== '') {
            $tel = preg_replace('/[^0-9+]/', '', $phone) ?: $phone;
            $contact .= '<span>Telefon: <a href="tel:' . self::h($tel) . '">' . self::h($phone) . '</a></span>';
        }
        if ($contact === '') {
            $contact = '<span>Kontaktdaten werden von der Unterkunft bereitgestellt.</span>';
        }

        return '<section class="portal-next">'
            . '<div class="portal-card portal-next-main"><small class="portal-badge ' . self::h($badgeClass) . '">' . self::h($badge) . '</small><h2>' . self::h($nextTitle) . '</h2><p>' . self::h($nextText) . '</p>' . $cta . '<div class="portal-safe-note">Sicherer Kundenbereich: Ihre Daten werden nur ueber diesen persoenlichen Link angezeigt.</div></div>'
            . '<div class="portal-card"><h2>Reiseuebersicht</h2><div class="portal-mini-grid">'
            . '<div class="portal-mini"><small>Anreise</small><b>' . self::h($arrival ?: '–') . '</b><span>' . self::h($arrivalTime !== '' ? $arrivalTime : 'Zeit nach Vereinbarung') . '</span></div>'
            . '<div class="portal-mini"><small>Abreise</small><b>' . self::h($departure ?: '–') . '</b><span>' . self::h($departureTime !== '' ? $departureTime : 'Zeit nach Vereinbarung') . '</span></div>'
            . '<div class="portal-mini"><small>Unterkunft</small><b>' . self::h($apartment ?: '–') . '</b></div>'
            . '<div class="portal-mini"><small>Personen</small><b>' . self::h($guests ?: '–') . '</b></div>'
            . '</div><div class="portal-meter" aria-label="Zahlungsfortschritt"><span style="width:' . self::h($meterWidth) . '%"></span></div><small>Erhalten: ' . self::h($paidAmount ?: '–') . ' · Offen: ' . self::h($openAmount ?: '–') . '</small></div>'
            . '<div class="portal-card"><h2>Kontakt</h2><div class="portal-contact-row">' . $contact . '</div></div>'
            . '</section>';
    }

    private static function moneyNumber(string $value): float
    {
        $clean = preg_replace('/[^0-9,.-]/', '', $value) ?? '';
        if ($clean === '') {
            return 0.0;
        }
        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }
        return (float)$clean;
    }

    private static function customerPortalPaymentScheduleHtml(array $payload): string
    {
        $schedule = (array)($payload['payment_schedule'] ?? []);
        $payments = (array)($payload['payments'] ?? []);
        $html = '';
        if ($schedule) {
            $rows = '';
            foreach (array_slice($schedule, 0, 8) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $label = trim((string)($row['label'] ?? '')) ?: ((string)($row['installment_type'] ?? '') === 'deposit' ? 'Anzahlung' : 'Restbetrag');
                $amount = (string)($row['amount'] ?? '');
                $due = (string)($row['due_date'] ?? '');
                $status = (string)($row['status'] ?? '');
                $rows .= '<div class="portal-row"><div><b>' . self::h($label) . '</b><br><small>Faellig: ' . self::h($due ?: '–') . '</small></div><div><b>' . self::h($amount) . '</b></div><div><small class="portal-badge ' . ($status === 'paid' || $status === 'received' ? 'ok' : ($status === 'overdue' ? 'warn' : '')) . '">' . self::h($status ?: 'offen') . '</small></div></div>';
            }
            if ($rows !== '') {
                $html .= '<div class="portal-table"><div class="portal-row"><b>Zahlungsplan</b><small>Betrag</small><small>Status</small></div>' . $rows . '</div>';
            }
        }
        if ($payments) {
            $rows = '';
            foreach (array_slice($payments, 0, 6) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $rows .= '<div class="portal-row"><div><b>' . self::h((string)($row['payment_number'] ?? 'Zahlung')) . '</b><br><small>' . self::h((string)($row['payment_method'] ?? '')) . ' ' . self::h((string)($row['reference'] ?? '')) . '</small></div><div><b>' . self::h((string)($row['amount'] ?? '')) . '</b></div><div><small>' . self::h((string)($row['payment_date'] ?? '')) . '</small></div></div>';
            }
            if ($rows !== '') {
                $html .= '<div class="portal-table"><div class="portal-row"><b>Erfasste Zahlungen</b><small>Betrag</small><small>Datum</small></div>' . $rows . '</div>';
            }
        }
        return $html;
    }

    private static function sampleContext(): array
    {
        return [
            'reference' => 'SP-260619-001',
            'guest_name' => 'Max Mustermann',
            'guest_first_name' => 'Max',
            'guest_last_name' => 'Mustermann',
            'guest_company' => 'Mustermann Consulting',
            'guest_address_line1' => 'Musterstrasse 12',
            'guest_address_line2' => '2. Etage',
            'guest_postcode' => '80331',
            'guest_city' => 'Muenchen',
            'guest_country' => 'Deutschland',
            'guest_email' => 'max@example.com',
            'guest_phone' => '+49 171 1234567',
            'salutation' => 'Guten Tag',
            'arrival' => '20.06.2026',
            'departure' => '24.06.2026',
            'arrival_time' => '16:00',
            'departure_time' => '10:00',
            'nights' => '4',
            'apartment_name' => 'Fewo Costa',
            'apartment_code' => 'COSTA',
            'adults' => '2',
            'children' => '0',
            'total_price' => '640,00 EUR',
            'paid_amount' => '300,00 EUR',
            'open_amount' => '340,00 EUR',
            'payment_amount' => '300,00 EUR',
            'payment_method' => 'Ueberweisung',
            'payment_date' => date('d.m.Y'),
            'invoice_number' => 'RE-2026-001',
            'receipt_number' => 'Q-2026-001',
            'property_name' => (string)setting('property_name', 'StayPilot'),
            'property_address' => (string)setting('full_address', ''),
            'company_name' => (string)setting('property_name', 'StayPilot'),
            'company_address' => (string)setting('full_address', ''),
            'contact_email' => (string)setting('contact_email', ''),
            'contact_phone' => (string)setting('contact_phone', ''),
            'bank_name' => (string)setting('bank_name', 'Musterbank'),
            'bank_iban' => (string)setting('bank_iban', 'DE00 0000 0000 0000 0000 00'),
            'bank_bic' => (string)setting('bank_bic', 'MUSTERBIC'),
            'tax_id' => (string)setting('tax_id', 'DE123456789'),
            'date' => date('d.m.Y'),
            'today' => date('d.m.Y'),
            'task_type' => 'Wechselreinigung',
            'task_date' => date('d.m.Y'),
            'task_notes' => 'Terrasse pruefen, Handtuecher auffuellen',
            'cleaning_team' => 'Team A',
            'linen_change' => 'Ja',
            'portal_title' => 'Kundenbereich',
            'portal_subtitle' => 'Buchungen, Dokumente und Zahlungen auf einen Blick.',
            'portal_url' => 'https://example.invalid/kunde',
            'checkin_url' => 'https://example.invalid/checkin',
            'booking_status' => 'bestaetigt',
            'document_count' => '3',
            'latest_document_title' => 'Buchungsbestaetigung',
            'latest_document_number' => 'RE-2026-001',
            'latest_document_date' => date('d.m.Y'),
            'email_count' => '2',
            'latest_email_subject' => 'Ihre Buchungsbestaetigung',
            'latest_email_date' => date('d.m.Y'),
            'latest_email_status' => 'sent',
            'payment_status' => 'offen',
        ];
    }

    private static function sampleCustomerPayload(array $context): array
    {
        $reference = (string)($context['reference'] ?? 'SP-260619-001');
        return [
            'documents' => [
                [
                    'href' => self::samplePreviewHref('Buchungsbestaetigung', 'Beispiel-Dokument zur Buchungsbestaetigung ' . $reference),
                    'title' => 'Buchungsbestaetigung',
                    'meta' => $reference,
                ],
                [
                    'href' => self::samplePreviewHref('Rechnung', 'Beispiel-Rechnung zur Buchung ' . $reference),
                    'title' => 'Rechnung',
                    'meta' => (string)($context['invoice_number'] ?? 'RE-2026-001'),
                ],
            ],
            'actions' => [
                [
                    'href' => self::samplePreviewHref('Online-Check-in', 'Hier waere der Online-Check-in-Link hinterlegt.'),
                    'title' => 'Online-Check-in',
                    'text' => 'Online-Check-in oeffnen',
                ],
                [
                    'href' => self::samplePreviewHref('Kundenbereich', 'Hier waere der direkte Kundenlink hinterlegt.'),
                    'title' => 'Kundenbereich',
                    'text' => 'Kundenbereich oeffnen',
                ],
            ],
            'emails' => [
                [
                    'subject' => (string)($context['latest_email_subject'] ?? 'Ihre Buchungsbestaetigung'),
                    'status' => (string)($context['latest_email_status'] ?? 'sent'),
                    'date' => (string)($context['latest_email_date'] ?? date('d.m.Y')),
                ],
                [
                    'subject' => 'Online-Check-in Einladung',
                    'status' => 'sent',
                    'date' => date('d.m.Y'),
                ],
            ],
        ];
    }

    private static function samplePreviewHref(string $title, string $message): string
    {
        $html = '<!doctype html><html lang="de"><meta charset="utf-8"><title>' . self::h($title) . '</title><body style="font-family:Arial,sans-serif;padding:28px"><h1>' . self::h($title) . '</h1><p>' . self::h($message) . '</p></body></html>';
        return 'data:text/html;charset=utf-8,' . rawurlencode($html);
    }

    private static function replace(string $text, array $context): string
    {
        $text = str_replace(['{{', '}}'], ['{', '}'], $text);
        $context = self::expandLegacyContext($context);
        $map = [];
        foreach ($context as $key => $value) {
            $map['{' . $key . '}'] = (string)$value;
        }
        return strtr($text, $map);
    }

    /**
     * Wie replace(), aber escaped jeden eingesetzten Platzhalterwert per self::h().
     * Nur fuer body_html/header_html/footer_html verwenden: diese Vorlagen enthalten
     * vom Admin geschriebenes rohes HTML und werden ohne weiteres self::h() direkt in
     * die Seite eingefuegt. Platzhalter wie {guest_name} stammen dagegen oft aus dem
     * oeffentlichen Online-Check-in und duerfen dort kein HTML einschleusen koennen
     * (gespeichertes XSS gegen Gaeste im Kundenbereich und Admins in der Vorschau).
     */
    private static function replaceEscaped(string $text, array $context): string
    {
        $text = str_replace(['{{', '}}'], ['{', '}'], $text);
        $context = self::expandLegacyContext($context);
        $map = [];
        foreach ($context as $key => $value) {
            $map['{' . $key . '}'] = self::h((string)$value);
        }
        return strtr($text, $map);
    }

    private static function placeholderTokenGroups(array $catalog): array
    {
        $groups = [];
        foreach ($catalog as $group => $items) {
            $groups[$group] = array_values(array_filter(array_map(static function ($item): string {
                return is_array($item) ? (string)($item['token'] ?? '') : '';
            }, $items)));
        }
        return $groups;
    }

    private static function expandLegacyContext(array $context): array
    {
        $aliases = [
            'guest' => 'guest_name',
            'guest_full_name' => 'guest_name',
            'booking_reference' => 'reference',
            'reservation_reference' => 'reference',
            'booking_number' => 'reference',
            'check_in' => 'arrival',
            'checkin' => 'arrival',
            'check_out' => 'departure',
            'checkout' => 'departure',
            'apartment' => 'apartment_name',
            'accommodation' => 'apartment_name',
            'property' => 'property_name',
            'received_amount' => 'paid_amount',
            'paid_total' => 'paid_amount',
            'remaining_amount' => 'open_amount',
            'balance_due' => 'open_amount',
            'price_total' => 'total_price',
        ];

        foreach ($aliases as $legacy => $current) {
            if (!array_key_exists($legacy, $context) && array_key_exists($current, $context)) {
                $context[$legacy] = $context[$current];
            }
        }

        return $context;
    }

    private static function pdfLines(string $title, string $html): array
    {
        // WICHTIG: Fuer die einfache interne PDF-Ausgabe darf niemals CSS-/HTML-Code
        // als Text in die PDF laufen. Deshalb entfernen wir zuerst komplette head/style/script
        // Bereiche und ziehen erst danach lesbaren Dokumentinhalt heraus.
        $clean = preg_replace('#<head\b[^>]*>.*?</head>#is', '', $html) ?? $html;
        $clean = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $clean) ?? $clean;
        $clean = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $clean) ?? $clean;
        $clean = preg_replace('#<svg\b[^>]*>.*?</svg>#is', '', $clean) ?? $clean;
        $clean = preg_replace('#<img\b[^>]*>#is', '', $clean) ?? $clean;
        $clean = preg_replace('/<\/(h1|h2|h3|h4|p|div|section|article|header|footer|main|tr|li)>/i', "\n", $clean) ?? $clean;
        $clean = preg_replace('/<br\s*\/?>/i', "\n", $clean) ?? $clean;
        $clean = preg_replace('/<\/(td|th)>/i', "  ", $clean) ?? $clean;
        $plain = trim(html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $lines = [];
        foreach (preg_split('/\R+/', $plain) ?: [] as $line) {
            $line = trim((string)(preg_replace('/[ \t]+/u', ' ', $line) ?? $line));
            if ($line !== '' && !preg_match('/^[.#]?[a-z0-9_-]+\s*\{[^}]+\}/i', $line)) {
                $lines[] = $line;
            }
        }
        return $lines;
    }

    private static function cleanHtml(string $html): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        return trim($html);
    }

    private static function defaultSettings(): array
    {
        return [
            'accent' => '#2563eb',
            'paper' => 'a4',
            'margin' => 24,
            'font' => 'system',
            'logo_url' => '',
            'logo_width' => 160,
            'show_logo' => 1,
        ];
    }

    private static function defaultLayout(): array
    {
        return [
            'blocks' => [
                [
                    'type' => 'hero',
                    'title' => 'Ihr sicherer Gastbereich',
                    'subtitle' => 'Hier sehen Sie den aktuellen Stand Ihrer Buchung, Zahlungen und Dokumente.',
                    'button_label' => 'Online-Check-in oeffnen',
                    'button_href' => '{checkin_url}',
                    'items' => [],
                ],
                [
                    'type' => 'status',
                    'title' => 'Aktueller Ablauf',
                    'subtitle' => 'Bestaetigung, Versand, Zahlung und Kundenlogin.',
                    'items' => [
                        ['title' => 'Buchung', 'text' => '{booking_status}'],
                        ['title' => 'Dokumente', 'text' => '{document_count} verfuegbar'],
                        ['title' => 'Zahlung', 'text' => '{payment_status}'],
                    ],
                ],
                [
                    'type' => 'documents',
                    'title' => 'Dokumente',
                    'subtitle' => 'Bestaetigungen, Rechnungen und weitere Unterlagen.',
                    'items' => [],
                ],
                [
                    'type' => 'emails',
                    'title' => 'E-Mails',
                    'subtitle' => 'Versendete Nachrichten und Hinweise zum Aufenthalt.',
                    'items' => [],
                ],
            ],
        ];
    }

    private static function settings(array $settings): array
    {
        return [
            'accent' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($settings['accent'] ?? '')) ? (string)$settings['accent'] : '#2563eb',
            'paper' => in_array((string)($settings['paper'] ?? 'a4'), ['a4', 'letter'], true) ? (string)$settings['paper'] : 'a4',
            'margin' => max(0, min(60, (int)($settings['margin'] ?? 24))),
            'font' => in_array((string)($settings['font'] ?? 'system'), ['system', 'serif', 'mono'], true) ? (string)$settings['font'] : 'system',
            'logo_url' => trim((string)($settings['logo_url'] ?? '')),
            'logo_width' => max(40, min(320, (int)($settings['logo_width'] ?? 160))),
            'show_logo' => normalize_bool($settings['show_logo'] ?? 1) ? 1 : 0,
            'email_bg' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($settings['email_bg'] ?? '')) ? (string)$settings['email_bg'] : '#ffffff',
        ];
    }

    private static function layout($value): array
    {
        if (is_array($value)) {
            return self::normalizeLayout($value);
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return self::normalizeLayout($decoded);
            }
        }
        return self::defaultLayout();
    }

    private static function normalizeLayout(array $layout): array
    {
        $blocks = [];
        foreach ((array)($layout['blocks'] ?? []) as $block) {
            if (!is_array($block)) {
                continue;
            }
            $items = [];
            foreach ((array)($block['items'] ?? []) as $item) {
                if (is_array($item)) {
                    $items[] = [
                        'title' => (string)($item['title'] ?? ''),
                        'text' => (string)($item['text'] ?? ''),
                        'href' => (string)($item['href'] ?? ''),
                    ];
                }
            }
            $blocks[] = [
                'type' => (string)($block['type'] ?? 'card'),
                'title' => (string)($block['title'] ?? ''),
                'subtitle' => (string)($block['subtitle'] ?? ''),
                'button_label' => (string)($block['button_label'] ?? ''),
                'button_href' => (string)($block['button_href'] ?? ''),
                'button_bg' => (string)($block['button_bg'] ?? '#2563eb'),
                'button_color' => (string)($block['button_color'] ?? '#ffffff'),
                'button_radius' => max(0, min(999, (int)($block['button_radius'] ?? 999))),
                'items' => $items,
            ];
        }
        return ['blocks' => $blocks ?: (self::defaultLayout()['blocks'] ?? [])];
    }

    private static function groupByChannel(array $rows): array
    {
        $grouped = ['email' => [], 'pdf' => [], 'customer' => []];
        foreach ($rows as $row) {
            $channel = self::channel((string)($row['channel'] ?? 'email'));
            $grouped[$channel][] = $row;
        }
        return $grouped;
    }

    private static function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function channel(string $channel): string
    {
        return array_key_exists($channel, self::CHANNELS) ? $channel : 'email';
    }

    private static function language(string $language): string
    {
        return array_key_exists($language, self::LANGUAGES) ? $language : 'de';
    }

    private static function category(string $category): string
    {
        return array_key_exists($category, self::CATEGORIES) ? $category : 'general';
    }

    private static function context(string $context): string
    {
        return array_key_exists($context, self::CONTEXTS) ? $context : 'booking';
    }

    private static function status(string $status): string
    {
        return in_array($status, ['active', 'draft', 'archived'], true) ? $status : 'active';
    }

    private static function code(string $code): string
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/[^a-z0-9_\\-]+/', '_', $code) ?? $code;
        $code = trim($code, '_-');
        return substr($code, 0, 80);
    }

    public static function mailKey(string $mailKey): string
    {
        return array_key_exists($mailKey, self::MAIL_KEYS) ? $mailKey : 'free_mail';
    }

    private static function formatMoney(float $amount, string $currency, string $language): string
    {
        $formatted = number_format($amount, 2, $language === 'de' ? ',' : '.', $language === 'de' ? '.' : ',');
        return $formatted . ' ' . $currency;
    }

    private static function formatDate(string $date, string $language): string
    {
        if ($date === '') {
            return '';
        }
        try {
            $dt = new DateTimeImmutable($date);
            return $dt->format($language === 'de' ? 'd.m.Y' : 'Y-m-d');
        } catch (Throwable $e) {
            return $date;
        }
    }

    private static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }
        $pdo = db();
        $pdo->exec("CREATE TABLE IF NOT EXISTS document_templates (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            channel VARCHAR(20) NOT NULL DEFAULT 'email',
            code VARCHAR(80) NOT NULL,
            name VARCHAR(190) NOT NULL,
            category VARCHAR(60) NOT NULL DEFAULT 'general',
            context_type VARCHAR(60) NOT NULL DEFAULT 'booking',
            language VARCHAR(5) NOT NULL DEFAULT 'de',
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            subject_template VARCHAR(255) NULL,
            title_template VARCHAR(255) NOT NULL,
            portal_title VARCHAR(255) NULL,
            portal_subtitle VARCHAR(255) NULL,
            button_label VARCHAR(190) NULL,
            show_in_portal TINYINT(1) NOT NULL DEFAULT 1,
            header_html LONGTEXT NULL,
            body_html LONGTEXT NOT NULL,
            footer_html LONGTEXT NULL,
            filename_template VARCHAR(190) NOT NULL DEFAULT '{code}-{reference}.pdf',
            page_settings_json LONGTEXT NULL,
            layout_json LONGTEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_document_template_code_channel_lang(code,channel,language),
            INDEX idx_document_templates_status(channel,status,sort_order),
            INDEX idx_document_templates_context(context_type,language)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS mail_template_attachments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            mail_key VARCHAR(80) NOT NULL,
            document_template_id BIGINT UNSIGNED NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            filename_template VARCHAR(190) NULL,
            language_mode VARCHAR(20) NOT NULL DEFAULT 'guest',
            fixed_language VARCHAR(5) NULL,
            generation_mode VARCHAR(20) NOT NULL DEFAULT 'fresh',
            store_copy TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_mail_template_attachment(mail_key,document_template_id),
            INDEX idx_mail_template_attachments_mail(mail_key,active,sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::ensureColumn($pdo, 'document_templates', 'channel', "VARCHAR(20) NOT NULL DEFAULT 'email' AFTER id");
        self::ensureColumn($pdo, 'document_templates', 'portal_title', "VARCHAR(255) NULL AFTER title_template");
        self::ensureColumn($pdo, 'document_templates', 'portal_subtitle', "VARCHAR(255) NULL AFTER portal_title");
        self::ensureColumn($pdo, 'document_templates', 'button_label', "VARCHAR(190) NULL AFTER portal_subtitle");
        self::ensureColumn($pdo, 'document_templates', 'show_in_portal', "TINYINT(1) NOT NULL DEFAULT 1 AFTER button_label");
        self::ensureColumn($pdo, 'document_templates', 'layout_json', "LONGTEXT NULL AFTER page_settings_json");

        $count = (int)$pdo->query('SELECT COUNT(*) FROM document_templates')->fetchColumn();
        if ($count === 0) {
            $defaults = [
                ['email', 'booking_confirmation_email', 'Buchungsbestaetigungs-Mail', 'booking', 'booking', 'Ihre Buchungsbestaetigung {reference}', 'Buchungsbestaetigung {reference}', ''],
                ['email', 'payment_received_email', 'Zahlungseingang-Mail', 'billing', 'booking', 'Zahlungseingang {reference}', 'Zahlungseingang {reference}', ''],
                ['email', 'cancellation_email', 'Stornobestaetigungs-Mail', 'booking', 'booking', 'Stornierung {reference}', 'Stornobestaetigung {reference}', ''],
                ['email', 'free_mail', 'Freie Mail', 'general', 'system', 'Nachricht {reference}', 'Nachricht {reference}', ''],
                ['pdf', 'booking_confirmation_pdf', 'Buchungsbestaetigung PDF', 'booking', 'booking', '', 'Buchungsbestaetigung {reference}', 'PDF anzeigen'],
                ['pdf', 'receipt_pdf', 'Quittung PDF', 'billing', 'booking', '', 'Quittung {reference}', 'PDF anzeigen'],
                ['pdf', 'cancellation_pdf', 'Stornobestaetigung PDF', 'booking', 'booking', '', 'Stornobestaetigung {reference}', 'PDF anzeigen'],
                ['customer', 'customer_overview', 'Kundenuebersicht', 'guest', 'portal', 'Kundenbereich {reference}', 'Kundenbereich', 'Kundenbereich oeffnen'],
            ];
            $stmt = $pdo->prepare('INSERT INTO document_templates(channel,code,name,category,context_type,language,subject_template,title_template,portal_title,portal_subtitle,button_label,body_html,filename_template,sort_order,layout_json,show_in_portal,page_settings_json,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($defaults as $index => $row) {
                list($channel, $code, $name, $category, $context, $subject, $title, $buttonLabel) = $row;
                $stmt->execute([
                    $channel,
                    $code,
                    $name,
                    $category,
                    $context,
                    'de',
                    $subject,
                    $title,
                    $channel === 'customer' ? $title : '',
                    $channel === 'customer' ? 'Buchungen, Dokumente und Zahlungen im Blick.' : '',
                    $buttonLabel,
                    $channel === 'customer'
                        ? '<p>Willkommen {guest_name}, hier finden Sie Ihre Buchung, Dokumente und Zahlungen.</p>'
                        : '<p>Guten Tag {guest_name},</p><p>vielen Dank. Ihre Buchung ist bestaetigt.</p>',
                    $code . '-{reference}.pdf',
                    ($index + 1) * 10,
                    json_encode($channel === 'customer' ? self::defaultLayout() : ['blocks' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $channel === 'customer' ? 1 : 0,
                    json_encode(self::defaultSettings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'active',
                ]);
            }
        }

        self::ensureSeparatedChannels($pdo);
        self::seedRecoveryTemplates($pdo);

        self::$schemaEnsured = true;
    }

    private static function seedRecoveryTemplates(PDO $pdo): void
    {
        self::seedLegacyCustomerPortalTemplate($pdo);
        self::seedLegacyBookingConfirmationTemplates($pdo);
    }

    private static function seedLegacyCustomerPortalTemplate(PDO $pdo): void
    {
        $exists = $pdo->prepare("SELECT id FROM document_templates WHERE channel='customer' AND code='customer_legacy_portal' AND language='de' LIMIT 1");
        $exists->execute();
        if ($exists->fetch()) {
            return;
        }

        $layout = [
            'blocks' => [
                [
                    'type' => 'hero',
                    'title' => '{reference}',
                    'subtitle' => '{guest_name} - Hier sehen Sie den aktuellen Stand Ihrer Buchung, Zahlungen und Dokumente.',
                    'button_label' => 'Online-Check-in oeffnen',
                    'button_href' => '{checkin_url}',
                    'items' => [],
                ],
                [
                    'type' => 'status',
                    'title' => 'Aktueller Ablauf',
                    'subtitle' => 'Bestaetigung, Versand, Zahlung und Kundenlogin.',
                    'items' => [
                        ['title' => 'Buchung', 'text' => '{booking_status}'],
                        ['title' => 'Dokumente', 'text' => '{document_count} verfuegbar'],
                        ['title' => 'Zahlung', 'text' => '{payment_status}'],
                    ],
                ],
                [
                    'type' => 'documents',
                    'title' => 'Dokumente',
                    'subtitle' => 'Bestaetigungen, Rechnungen und weitere Unterlagen.',
                    'items' => [],
                ],
                [
                    'type' => 'actions',
                    'title' => 'Schnellzugriffe',
                    'subtitle' => 'Wichtige Aktionen fuer Ihren Aufenthalt.',
                    'items' => [
                        ['title' => 'Online-Check-in', 'text' => '{checkin_url}'],
                        ['title' => 'Kundenbereich', 'text' => '{portal_url}'],
                    ],
                ],
            ],
        ];

        $body = '<p>Wiederherstellbare Vorlage des bisherigen Kundenbereichs.</p><p>Diese Vorlage bleibt als Rueckfall erhalten und kann jederzeit wieder aktiviert oder dupliziert werden.</p>';

        $stmt = $pdo->prepare('INSERT INTO document_templates(channel,code,name,category,context_type,language,status,subject_template,title_template,portal_title,portal_subtitle,button_label,show_in_portal,header_html,body_html,footer_html,filename_template,page_settings_json,layout_json,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            'customer',
            'customer_legacy_portal',
            'Wiederherstellung Kundenbereich',
            'guest',
            'portal',
            'de',
            'draft',
            'Kundenbereich {reference}',
            'Kundenbereich Wiederherstellung',
            'Ihr sicherer Gastbereich',
            'Wiederherstellbare Vorlage des bisherigen Kundenbereichs.',
            'Online-Check-in oeffnen',
            1,
            '',
            $body,
            '',
            'customer-portal-{reference}.pdf',
            json_encode(self::defaultSettings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            900,
        ]);
    }

    private static function seedLegacyBookingConfirmationTemplates(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'booking_confirmation_templates')) {
            return;
        }

        $rows = $pdo->query('SELECT language,email_subject,greeting,intro,additional_info,closing,signature,pdf_title FROM booking_confirmation_templates')->fetchAll();
        if (!$rows) {
            return;
        }

        $existsEmail = $pdo->prepare("SELECT id FROM document_templates WHERE channel='email' AND code='booking_confirmation_legacy' AND language=? LIMIT 1");
        $existsPdf = $pdo->prepare("SELECT id FROM document_templates WHERE channel='pdf' AND code='booking_confirmation_legacy' AND language=? LIMIT 1");
        $insert = $pdo->prepare('INSERT INTO document_templates(channel,code,name,category,context_type,language,status,subject_template,title_template,portal_title,portal_subtitle,button_label,show_in_portal,header_html,body_html,footer_html,filename_template,page_settings_json,layout_json,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

        foreach ($rows as $row) {
            $language = self::language((string)($row['language'] ?? 'de'));
            $subject = trim((string)($row['email_subject'] ?? ''));
            if ($subject === '') {
                $subject = 'Ihre Buchungsbestaetigung {reference}';
            }
            $title = trim((string)($row['pdf_title'] ?? ''));
            if ($title === '') {
                $title = 'Buchungsbestaetigung {reference}';
            }
            $body = '';
            foreach (['greeting', 'intro', 'additional_info', 'closing', 'signature'] as $key) {
                $part = trim((string)($row[$key] ?? ''));
                if ($part !== '') {
                    $body .= '<p>' . self::h($part) . '</p>';
                }
            }
            if ($body === '') {
                $body = '<p>Guten Tag {guest_name},</p><p>vielen Dank. Ihre Buchung ist bestaetigt.</p>';
            }

            $existsEmail->execute([$language]);
            if (!$existsEmail->fetch()) {
                $insert->execute([
                    'email',
                    'booking_confirmation_legacy',
                    'Wiederherstellung Buchungsbestaetigungs-Mail',
                    'booking',
                    'booking',
                    $language,
                    'draft',
                    $subject,
                    $title,
                    '',
                    '',
                    'E-Mail anzeigen',
                    0,
                    '',
                    $body,
                    '',
                    'booking-confirmation-legacy-{reference}.pdf',
                    json_encode(self::defaultSettings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    json_encode(['blocks' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    910,
                ]);
            }

            $existsPdf->execute([$language]);
            if (!$existsPdf->fetch()) {
                $insert->execute([
                    'pdf',
                    'booking_confirmation_legacy',
                    'Wiederherstellung Buchungsbestaetigung PDF',
                    'booking',
                    'booking',
                    $language,
                    'draft',
                    '',
                    $title,
                    '',
                    '',
                    'PDF anzeigen',
                    0,
                    '',
                    $body,
                    '',
                    'booking-confirmation-legacy-{reference}.pdf',
                    json_encode(self::defaultSettings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    json_encode(['blocks' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    910,
                ]);
            }
        }
    }

    private static function ensureSeparatedChannels(PDO $pdo): void
    {
        $existingPdf = [];
        foreach ($pdo->query("SELECT id, code, language FROM document_templates WHERE channel='pdf'")->fetchAll() as $row) {
            $existingPdf[(string)$row['code'] . '|' . (string)$row['language']] = (int)$row['id'];
        }

        $rows = $pdo->query("SELECT * FROM document_templates WHERE channel='email' ORDER BY id")->fetchAll();
        $insert = $pdo->prepare('INSERT INTO document_templates(channel,code,name,category,context_type,language,status,subject_template,title_template,portal_title,portal_subtitle,button_label,show_in_portal,header_html,body_html,footer_html,filename_template,page_settings_json,layout_json,sort_order,created_by,updated_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($rows as $row) {
            $key = (string)($row['code'] ?? '') . '|' . (string)($row['language'] ?? 'de');
            if (isset($existingPdf[$key])) {
                continue;
            }
            $insert->execute([
                'pdf',
                (string)($row['code'] ?? 'document'),
                trim((string)($row['name'] ?? 'Dokument') . ' PDF'),
                (string)($row['category'] ?? 'general'),
                (string)($row['context_type'] ?? 'booking'),
                (string)($row['language'] ?? 'de'),
                'draft',
                '',
                (string)($row['title_template'] ?? $row['name'] ?? 'Dokument'),
                '',
                '',
                'PDF anzeigen',
                0,
                (string)($row['header_html'] ?? ''),
                (string)($row['body_html'] ?? ''),
                (string)($row['footer_html'] ?? ''),
                (string)($row['filename_template'] ?? 'dokument-{reference}.pdf'),
                (string)($row['page_settings_json'] ?? json_encode(self::defaultSettings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                json_encode(['blocks' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                (int)($row['sort_order'] ?? 100),
                $row['created_by'] ?? null,
                $row['updated_by'] ?? null,
            ]);
            $existingPdf[$key] = (int)$pdo->lastInsertId();
        }

        $map = [];
        $stmt = $pdo->query("SELECT code, language, id FROM document_templates WHERE channel='pdf'");
        foreach ($stmt->fetchAll() as $row) {
            $map[(string)$row['code'] . '|' . (string)$row['language']] = (int)$row['id'];
        }

        $attachments = $pdo->query("SELECT a.id, a.document_template_id, t.code, t.language, t.channel FROM mail_template_attachments a JOIN document_templates t ON t.id=a.document_template_id")->fetchAll();
        $update = $pdo->prepare('UPDATE mail_template_attachments SET document_template_id=? WHERE id=?');
        foreach ($attachments as $attachment) {
            if ((string)($attachment['channel'] ?? '') === 'pdf') {
                continue;
            }
            $key = (string)($attachment['code'] ?? '') . '|' . (string)($attachment['language'] ?? 'de');
            $pdfId = (int)($map[$key] ?? 0);
            if ($pdfId > 0) {
                $update->execute([$pdfId, (int)$attachment['id']]);
            }
        }
    }

    private static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        if (!self::columnExists($pdo, $table, $column)) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
            $stmt->execute([$table, $column]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}
