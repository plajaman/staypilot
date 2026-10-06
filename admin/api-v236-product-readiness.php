<?php
declare(strict_types=1);

function pr109_table_exists(string $table): bool
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function pr109_column_exists(string $table, string $column): bool
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function pr109_active_booking_condition(string $alias = 'b'): string
{
    $parts = [];
    if (pr109_column_exists('bookings', 'deleted_at')) $parts[] = "($alias.deleted_at IS NULL OR $alias.deleted_at='0000-00-00 00:00:00')";
    if (pr109_column_exists('bookings', 'status')) $parts[] = "LOWER(COALESCE($alias.status,'')) NOT IN ('cancelled','canceled','storniert','deleted','gelöscht','rejected','abgelehnt','declined','void')";
    return $parts ? implode(' AND ', $parts) : '1=1';
}

function pr109_inactive_booking_condition(string $alias = 'b'): string
{
    $parts = [];
    if (pr109_column_exists('bookings', 'deleted_at')) $parts[] = "$alias.deleted_at IS NOT NULL";
    if (pr109_column_exists('bookings', 'status')) $parts[] = "LOWER(COALESCE($alias.status,'')) IN ('cancelled','canceled','storniert','deleted','gelöscht','rejected','abgelehnt','declined','void')";
    return $parts ? '(' . implode(' OR ', $parts) . ')' : '0=1';
}

function pr109_valid_guest_condition(string $alias = 'g'): string
{
    $parts = ["$alias.id IS NOT NULL"];
    if (pr109_column_exists('guests', 'deleted_at')) $parts[] = "($alias.deleted_at IS NULL OR $alias.deleted_at='0000-00-00 00:00:00')";
    return implode(' AND ', $parts);
}

function pr109_active_offer_condition(string $alias = 'o'): string
{
    $parts = [];
    if (pr109_column_exists('offers', 'deleted_at')) $parts[] = "($alias.deleted_at IS NULL OR $alias.deleted_at='0000-00-00 00:00:00')";
    if (pr109_column_exists('offers', 'status')) $parts[] = "LOWER(COALESCE($alias.status,'')) NOT IN ('archived','archive','cancelled','canceled','storniert','deleted','gelöscht','declined','abgelehnt','expired','void')";
    return $parts ? implode(' AND ', $parts) : '1=1';
}

function pr109_limit(): int { return 30; }

function pr109_fetch_all(string $sql, array $params = []): array
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function pr109_count(string $sql, array $params = []): int
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function pr109_group(string $key, string $title, string $description, string $actionLabel, array $items, string $recommendedAction): array
{
    return [
        'key' => $key,
        'title' => $title,
        'description' => $description,
        'action_label' => $actionLabel,
        'recommended_action' => $recommendedAction,
        'count' => count($items),
        'items' => $items,
    ];
}

function pr109_build_review(): array
{

    $groups = [];

    if (pr109_table_exists('bookings') && pr109_table_exists('guests') && pr109_column_exists('bookings', 'guest_id')) {
        $active = pr109_active_booking_condition('b');
        $validGuest = pr109_valid_guest_condition('g');
        $items = pr109_fetch_all("SELECT b.id, COALESCE(b.reference, CONCAT('Buchung #', b.id)) number,
            CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest,
            b.arrival, b.departure, b.status
            FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id
            WHERE $active AND b.guest_id IS NOT NULL AND NOT ($validGuest)
            ORDER BY b.id DESC LIMIT " . pr109_limit());
        $groups[] = pr109_group('booking_without_valid_guest', 'Aktive Buchungen ohne gültigen Gast', 'Diese Buchungen haben keinen aktiven Gastbezug mehr. Sie sollten nicht als normale Buchung weiterlaufen.', 'In Klärung setzen', $items, 'clarify_booking');
    }

    if (pr109_table_exists('offers') && pr109_table_exists('guests') && pr109_column_exists('offers', 'guest_id')) {
        $active = pr109_active_offer_condition('o');
        $validGuest = pr109_valid_guest_condition('g');
        $items = pr109_fetch_all("SELECT o.id, COALESCE(o.offer_number, CONCAT('Angebot #', o.id)) number,
            COALESCE(o.guest_name, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,''))) guest,
            COALESCE(o.guest_email,'') email, o.arrival, o.departure, o.status
            FROM offers o LEFT JOIN guests g ON g.id=o.guest_id
            WHERE $active AND o.guest_id IS NOT NULL AND NOT ($validGuest)
            ORDER BY o.id DESC LIMIT " . pr109_limit());
        $groups[] = pr109_group('offer_without_valid_guest', 'Aktive Angebote ohne gültigen Gast', 'Diese Angebote hängen an gelöschten oder fehlenden Gästen und sollten nicht als aktive Klärung erscheinen.', 'Angebot archivieren', $items, 'archive_offer');
    }

    if (pr109_table_exists('booking_payment_schedule') && pr109_table_exists('bookings') && pr109_column_exists('booking_payment_schedule', 'booking_id')) {
        $inactive = pr109_inactive_booking_condition('b');
        $statusFilter = pr109_column_exists('booking_payment_schedule', 'status') ? " AND LOWER(COALESCE(s.status,'')) NOT IN ('received','paid','waived','cancelled','canceled','storniert','aufgehoben')" : '';
        $items = pr109_fetch_all("SELECT s.id, s.booking_id, COALESCE(b.reference, CONCAT('Buchung #', b.id)) number,
            s.due_date arrival, NULL departure, COALESCE(s.status,'') status, COALESCE(s.amount,0) amount
            FROM booking_payment_schedule s LEFT JOIN bookings b ON b.id=s.booking_id
            WHERE s.booking_id IS NOT NULL AND (b.id IS NULL OR $inactive) $statusFilter
            ORDER BY s.id DESC LIMIT " . pr109_limit());
        $groups[] = pr109_group('payment_schedule_without_active_booking', 'Offene Zahlungsziele ohne aktive Buchung', 'Diese Zahlungsziele gehören zu gelöschten/stornierten Buchungen und dürfen nicht als offen weiterlaufen.', 'Zahlungsziel aufheben', $items, 'waive_payment_schedule');
    }

    if (pr109_table_exists('housekeeping_tasks') && pr109_table_exists('bookings') && pr109_column_exists('housekeeping_tasks', 'booking_id')) {
        $inactive = pr109_inactive_booking_condition('b');
        $statusFilter = pr109_column_exists('housekeeping_tasks', 'status') ? " AND LOWER(COALESCE(t.status,'')) NOT IN ('done','completed','cancelled','canceled','storniert','freigegeben','bezugsbereit')" : '';
        $items = pr109_fetch_all("SELECT t.id, t.booking_id, COALESCE(t.title, CONCAT('Putzaufgabe #', t.id)) number,
            COALESCE(t.task_date, t.due_date) arrival, NULL departure, COALESCE(t.status,'') status
            FROM housekeeping_tasks t LEFT JOIN bookings b ON b.id=t.booking_id
            WHERE t.booking_id IS NOT NULL AND (b.id IS NULL OR $inactive) $statusFilter
            ORDER BY t.id DESC LIMIT " . pr109_limit());
        $groups[] = pr109_group('housekeeping_without_active_booking', 'Putzaufgaben ohne aktive Buchung', 'Diese Aufgaben gehören nicht mehr zu einer aktiven Buchung und sollten im Putzplan nicht offen bleiben.', 'Putzaufgabe stornieren', $items, 'cancel_housekeeping');
    }

    if (pr109_table_exists('booking_checkins') && pr109_table_exists('bookings') && pr109_column_exists('booking_checkins', 'booking_id')) {
        $inactive = pr109_inactive_booking_condition('b');
        $items = pr109_fetch_all("SELECT c.id, c.booking_id, COALESCE(b.reference, CONCAT('Buchung #', b.id)) number,
            b.arrival, b.departure, COALESCE(c.status,'') status
            FROM booking_checkins c LEFT JOIN bookings b ON b.id=c.booking_id
            WHERE c.booking_id IS NOT NULL AND (b.id IS NULL OR $inactive)
            ORDER BY c.id DESC LIMIT " . pr109_limit());
        $groups[] = pr109_group('checkin_without_active_booking', 'Check-ins ohne aktive Buchung', 'Diese Check-ins hängen an fehlenden/gelöschten Buchungen und sollten als storniert oder geschlossen markiert werden.', 'Check-in schließen', $items, 'close_checkin');
    }

    if (pr109_table_exists('booking_documents') && pr109_table_exists('bookings') && pr109_column_exists('booking_documents', 'booking_id')) {
        $inactive = pr109_inactive_booking_condition('b');
        $docActive = pr109_column_exists('booking_documents', 'deleted_at') ? " AND (d.deleted_at IS NULL OR d.deleted_at='0000-00-00 00:00:00')" : '';
        $items = pr109_fetch_all("SELECT d.id, d.booking_id, COALESCE(d.document_number, d.title, CONCAT('Dokument #', d.id)) number,
            b.arrival, b.departure, COALESCE(d.status,'') status
            FROM booking_documents d LEFT JOIN bookings b ON b.id=d.booking_id
            WHERE d.booking_id IS NOT NULL AND (b.id IS NULL OR $inactive) $docActive
            ORDER BY d.id DESC LIMIT " . pr109_limit());
        $groups[] = pr109_group('document_without_active_booking', 'Dokumente ohne aktive Buchung', 'Diese Dokumente hängen an nicht aktiven Buchungen und sollten aus aktiven Arbeitslisten entfernt werden.', 'Dokument logisch ausblenden', $items, 'hide_document');
    }

    $groups = array_values(array_filter($groups, static fn(array $g): bool => (int)$g['count'] > 0));
    return ['ok' => true, 'groups' => $groups, 'total' => array_sum(array_map(static fn($g) => (int)$g['count'], $groups))];

}

function pms_consistency_review_v236109(): never
{
    Auth::requireLogin();
    json_response(pr109_build_review());
}

function pr109_audit(string $entity, int $id, string $action, array $before, array $after, string $note): void
{
    if (class_exists('AuditLogger')) {
        try { AuditLogger::record($entity, $id, $action, $before, $after, $note); } catch (Throwable $e) {}
    }
}

function pms_consistency_action_v236109(): never
{
    $user = Auth::requireLogin();
    if (!in_array((string)($user['role'] ?? ''), ['admin','manager'], true)) {
        throw new ForbiddenException('Dieser Konsistenz-Assistent ist nur für Admin/Manager freigegeben.');
    }
    $d = request_data();
    $issue = (string)($d['issue_key'] ?? '');
    $id = (int)($d['id'] ?? 0);
    $bulk = !empty($d['bulk']);
    $reason = trim((string)($d['reason'] ?? 'Kern-PMS-Konsistenz-Assistent'));
    if ($reason === '') $reason = 'Kern-PMS-Konsistenz-Assistent';

    $affected = 0;
    $pdo = db();
    $applyOne = static function (string $issue, int $id, string $reason) use (&$affected, $pdo): void {
        if ($id <= 0) return;
        switch ($issue) {
            case 'booking_without_valid_guest':
                if (!pr109_table_exists('bookings') || !pr109_column_exists('bookings', 'status')) return;
                $before = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                if (!$before) return;
                $stmt = $pdo->prepare("UPDATE bookings SET status='clarification_required' WHERE id=? AND " . pr109_active_booking_condition('bookings'));
                $stmt->execute([$id]); $affected += $stmt->rowCount();
                $after = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('booking', $id, 'pms_consistency_clarification', $before, $after, $reason);
                break;
            case 'offer_without_valid_guest':
                if (!pr109_table_exists('offers') || !pr109_column_exists('offers', 'status')) return;
                $before = pr109_fetch_all('SELECT * FROM offers WHERE id=? LIMIT 1', [$id])[0] ?? [];
                if (!$before) return;
                $sql = "UPDATE offers SET status='archived'";
                if (pr109_column_exists('offers', 'archived_at')) $sql .= ", archived_at=COALESCE(archived_at,NOW())";
                $sql .= " WHERE id=?";
                $stmt = $pdo->prepare($sql); $stmt->execute([$id]); $affected += $stmt->rowCount();
                $after = pr109_fetch_all('SELECT * FROM offers WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('offer', $id, 'pms_consistency_archive', $before, $after, $reason);
                break;
            case 'payment_schedule_without_active_booking':
                if (!pr109_table_exists('booking_payment_schedule') || !pr109_column_exists('booking_payment_schedule', 'status')) return;
                $before = pr109_fetch_all('SELECT * FROM booking_payment_schedule WHERE id=? LIMIT 1', [$id])[0] ?? [];
                if (!$before) return;
                $stmt = $pdo->prepare("UPDATE booking_payment_schedule SET status='waived' WHERE id=? AND LOWER(COALESCE(status,'')) NOT IN ('received','paid','waived','cancelled','canceled','storniert','aufgehoben')");
                $stmt->execute([$id]); $affected += $stmt->rowCount();
                $after = pr109_fetch_all('SELECT * FROM booking_payment_schedule WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('payment_schedule', $id, 'pms_consistency_waive', $before, $after, $reason);
                break;
            case 'housekeeping_without_active_booking':
                if (!pr109_table_exists('housekeeping_tasks') || !pr109_column_exists('housekeeping_tasks', 'status')) return;
                $before = pr109_fetch_all('SELECT * FROM housekeeping_tasks WHERE id=? LIMIT 1', [$id])[0] ?? [];
                if (!$before) return;
                $sql = "UPDATE housekeeping_tasks SET status='cancelled'";
                if (pr109_column_exists('housekeeping_tasks', 'updated_at')) $sql .= ", updated_at=NOW()";
                if (pr109_column_exists('housekeeping_tasks', 'notes')) $sql .= ", notes=CONCAT(COALESCE(notes,''), CASE WHEN COALESCE(notes,'')='' THEN '' ELSE '\n' END, ?)";
                $sql .= " WHERE id=?";
                $params = pr109_column_exists('housekeeping_tasks', 'notes') ? [$reason, $id] : [$id];
                $stmt = $pdo->prepare($sql); $stmt->execute($params); $affected += $stmt->rowCount();
                $after = pr109_fetch_all('SELECT * FROM housekeeping_tasks WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('housekeeping_task', $id, 'pms_consistency_cancel', $before, $after, $reason);
                break;
            case 'checkin_without_active_booking':
                if (!pr109_table_exists('booking_checkins') || !pr109_column_exists('booking_checkins', 'status')) return;
                $before = pr109_fetch_all('SELECT * FROM booking_checkins WHERE id=? LIMIT 1', [$id])[0] ?? [];
                if (!$before) return;
                $stmt = $pdo->prepare("UPDATE booking_checkins SET status='cancelled' WHERE id=?");
                $stmt->execute([$id]); $affected += $stmt->rowCount();
                $after = pr109_fetch_all('SELECT * FROM booking_checkins WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('booking_checkin', $id, 'pms_consistency_close', $before, $after, $reason);
                break;
            case 'document_without_active_booking':
                if (!pr109_table_exists('booking_documents')) return;
                $before = pr109_fetch_all('SELECT * FROM booking_documents WHERE id=? LIMIT 1', [$id])[0] ?? [];
                if (!$before) return;
                $sql = "UPDATE booking_documents SET ";
                $sets = [];
                if (pr109_column_exists('booking_documents', 'deleted_at')) $sets[] = "deleted_at=COALESCE(deleted_at,NOW())";
                if (pr109_column_exists('booking_documents', 'status')) $sets[] = "status='deleted'";
                if (!$sets) return;
                $sql .= implode(',', $sets) . " WHERE id=?";
                $stmt = $pdo->prepare($sql); $stmt->execute([$id]); $affected += $stmt->rowCount();
                $after = pr109_fetch_all('SELECT * FROM booking_documents WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('booking_document', $id, 'pms_consistency_hide', $before, $after, $reason);
                break;
        }
    };

    if ($bulk) {
        $review = pr109_build_review();
        foreach (($review['groups'] ?? []) as $group) {
            if ((string)($group['key'] ?? '') !== $issue) continue;
            foreach (($group['items'] ?? []) as $item) $applyOne($issue, (int)($item['id'] ?? 0), $reason);
        }
    } else {
        if ($id <= 0) throw new ValidationException('Eintrag fehlt.');
        $applyOne($issue, $id, $reason);
    }

    json_response(['ok' => true, 'message' => $affected . ' Eintrag/Einträge sicher bearbeitet.', 'affected' => $affected]);
}

function pr113_active_booking_sql(string $alias = 'b'): string
{
    return pr109_active_booking_condition($alias);
}

function pr113_booked_status_sql(string $alias = 'b'): string
{
    if (!pr109_column_exists('bookings', 'status')) return '1=1';
    return "LOWER(COALESCE($alias.status,'')) IN ('confirmed','accepted','booked','reserved','clarification_required')";
}

function pr113_group(string $key, string $title, string $description, string $actionLabel, array $items): array
{
    return [
        'key' => $key,
        'title' => $title,
        'description' => $description,
        'action_label' => $actionLabel,
        'count' => count($items),
        'items' => $items,
    ];
}

function pr113_build_flow_review(): array
{
    $groups = [];
    $limit = pr109_limit();

    if (pr109_table_exists('offers')) {
        $offerActive = pr109_active_offer_condition('o');
        if (pr109_column_exists('offers', 'status')) {
            $items = pr109_fetch_all("SELECT o.id, COALESCE(o.offer_number, CONCAT('Angebot #',o.id)) number, COALESCE(o.guest_name,'') guest, COALESCE(o.guest_email,'') email, o.arrival, o.departure, o.status
                FROM offers o WHERE $offerActive AND LOWER(COALESCE(o.status,'')) IN ('accepted','angenommen','confirmed') AND (o.booking_id IS NULL OR o.booking_id=0)
                ORDER BY o.id DESC LIMIT " . $limit);
            $groups[] = pr113_group('accepted_offer_without_booking', 'Angenommene Angebote ohne Buchung', 'Diese Angebote wurden angenommen, haben aber noch keine erzeugte oder verknüpfte Buchung. Das muss im Betrieb sichtbar geklärt werden.', 'Zur Prüfung markieren', $items);
        }
        if (pr109_table_exists('bookings') && pr109_column_exists('offers', 'booking_id')) {
            $inactive = pr109_inactive_booking_condition('b');
            $items = pr109_fetch_all("SELECT o.id, COALESCE(o.offer_number, CONCAT('Angebot #',o.id)) number, COALESCE(o.guest_name,'') guest, COALESCE(o.guest_email,'') email, o.arrival, o.departure, o.status
                FROM offers o JOIN bookings b ON b.id=o.booking_id WHERE $offerActive AND $inactive
                ORDER BY o.id DESC LIMIT " . $limit);
            $groups[] = pr113_group('offer_linked_to_inactive_booking', 'Aktive Angebote mit gelöschter/stornierter Buchung', 'Diese Angebote verweisen auf eine nicht mehr aktive Buchung und dürfen nicht weiter als aktive Klärung laufen.', 'Angebot archivieren', $items);
        }
    }

    if (pr109_table_exists('bookings')) {
        $active = pr113_active_booking_sql('b');
        $booked = pr113_booked_status_sql('b');
        if (pr109_column_exists('bookings', 'apartment_id')) {
            $items = pr109_fetch_all("SELECT b.id, COALESCE(b.reference, CONCAT('Buchung #',b.id)) number, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest, b.arrival, b.departure, b.status
                FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id WHERE $active AND $booked AND (b.apartment_id IS NULL OR b.apartment_id=0)
                ORDER BY b.arrival ASC, b.id DESC LIMIT " . $limit);
            $groups[] = pr113_group('booking_without_apartment', 'Bestätigte/gebuchte Buchungen ohne Wohnung', 'Diese Buchungen sind im Betrieb relevant, haben aber noch keine konkrete Wohnung. Kalender, Housekeeping und Gästeinformation bleiben dadurch unsicher.', 'In Klärung setzen', $items);
        }
        if (pr109_table_exists('booking_payment_schedule')) {
            $items = pr109_fetch_all("SELECT b.id, COALESCE(b.reference, CONCAT('Buchung #',b.id)) number, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest, b.arrival, b.departure, b.status, b.total_price amount
                FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id LEFT JOIN booking_payment_schedule s ON s.booking_id=b.id
                WHERE $active AND COALESCE(b.total_price,0)>0 GROUP BY b.id HAVING COUNT(s.id)=0
                ORDER BY b.arrival ASC, b.id DESC LIMIT " . $limit);
            $groups[] = pr113_group('booking_without_payment_schedule', 'Buchungen mit Preis ohne Zahlungsplan', 'Diese Buchungen haben einen Betrag, aber keinen Zahlungsplan. Das gefährdet Anzahlungen, Restbeträge und offene Forderungen.', 'Zahlungsplan erzeugen', $items);

            $items = pr109_fetch_all("SELECT * FROM (SELECT b.id, COALESCE(b.reference, CONCAT('Buchung #',b.id)) number, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest, b.arrival, b.departure, b.status, b.total_price amount, COALESCE(SUM(s.amount),0) scheduled
                FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id LEFT JOIN booking_payment_schedule s ON s.booking_id=b.id
                WHERE $active AND COALESCE(b.total_price,0)>0 GROUP BY b.id HAVING ABS(COALESCE(b.total_price,0)-scheduled)>0.05) x
                ORDER BY arrival ASC, id DESC LIMIT " . $limit);
            $groups[] = pr113_group('payment_schedule_mismatch', 'Zahlungsplan weicht von Buchungssumme ab', 'Die Summe der Zahlungsziele passt nicht zur Buchungssumme. Das darf nicht automatisch überschrieben werden, sondern wird für die Abrechnung markiert.', 'Zur Abrechnungsprüfung markieren', $items);
        }
        if (pr109_table_exists('booking_customer_access')) {
            $items = pr109_fetch_all("SELECT b.id, COALESCE(b.reference, CONCAT('Buchung #',b.id)) number, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest, b.arrival, b.departure, b.status
                FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id LEFT JOIN booking_customer_access a ON a.booking_id=b.id AND a.active=1
                WHERE $active AND $booked AND a.id IS NULL ORDER BY b.arrival ASC, b.id DESC LIMIT " . $limit);
            $groups[] = pr113_group('booking_without_customer_access', 'Buchungen ohne aktiven Kundenportal-Zugang', 'Der Gast kann ohne aktiven Zugang Status, Check-in und Dokumente nicht zuverlässig sehen.', 'Kundenportal-Zugang erzeugen', $items);
        }
        if (pr109_table_exists('booking_checkins')) {
            $items = pr109_fetch_all("SELECT b.id, COALESCE(b.reference, CONCAT('Buchung #',b.id)) number, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest, b.arrival, b.departure, b.status
                FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id LEFT JOIN booking_checkins c ON c.booking_id=b.id
                WHERE $active AND $booked AND c.booking_id IS NULL ORDER BY b.arrival ASC, b.id DESC LIMIT " . $limit);
            $groups[] = pr113_group('booking_without_checkin', 'Buchungen ohne Check-in-Datensatz', 'Für diese Buchungen fehlt der technische Check-in-Arbeitsdatensatz.', 'Check-in-Datensatz anlegen', $items);
        }
        if (pr109_table_exists('housekeeping_tasks')) {
            $items = pr109_fetch_all("SELECT b.id, COALESCE(b.reference, CONCAT('Buchung #',b.id)) number, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest, b.arrival, b.departure, b.status, b.apartment_id
                FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id LEFT JOIN housekeeping_tasks t ON t.booking_id=b.id
                WHERE $active AND $booked AND b.apartment_id IS NOT NULL AND t.id IS NULL ORDER BY b.departure ASC, b.id DESC LIMIT " . $limit);
            $groups[] = pr113_group('booking_without_housekeeping', 'Buchungen ohne Housekeeping-Auftrag', 'Für diese Buchungen gibt es noch keinen Reinigungsauftrag. Das kann Abreise/Reinigung/Freigabe stören.', 'Housekeeping-Auftrag anlegen', $items);
        }
    }

    $groups = array_values(array_filter($groups, static fn(array $g): bool => (int)$g['count'] > 0));
    return ['ok' => true, 'groups' => $groups, 'total' => array_sum(array_map(static fn($g) => (int)$g['count'], $groups))];
}

function pms_flow_review_v236113(): never
{
    Auth::requireLogin();
    json_response(pr113_build_flow_review());
}

function pr113_append_note(string $table, int $id, string $note): int
{
    foreach (['internal_notes','notes','completion_notes'] as $col) {
        if (pr109_column_exists($table, $col)) {
            $stmt = db()->prepare("UPDATE $table SET $col=CONCAT(COALESCE($col,''), CASE WHEN COALESCE($col,'')='' THEN '' ELSE '\n' END, ?) WHERE id=?");
            $stmt->execute([$note, $id]);
            return $stmt->rowCount();
        }
    }
    return 0;
}

function pr113_create_payment_schedule(int $bookingId): int
{
    if (!pr109_table_exists('booking_payment_schedule')) return 0;
    $row = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$bookingId])[0] ?? [];
    if (!$row) return 0;
    $exists = pr109_count('SELECT COUNT(*) FROM booking_payment_schedule WHERE booking_id=?', [$bookingId]);
    if ($exists > 0) return 0;
    $total = max(0.0, round((float)($row['total_price'] ?? 0), 2));
    if ($total <= 0.005) return 0;
    $deposit = max(0.0, round((float)($row['deposit_amount'] ?? 0), 2));
    if ($deposit > $total) $deposit = $total;
    if ((int)($row['deposit_required'] ?? 1) === 0) $deposit = 0.0;
    $remaining = max(0.0, round($total - $deposit, 2));
    $today = date('Y-m-d');
    $depositDue = !empty($row['deposit_due_date']) ? (string)$row['deposit_due_date'] : $today;
    $remainingDue = !empty($row['remaining_due_date']) ? (string)$row['remaining_due_date'] : (!empty($row['arrival']) ? (new DateTimeImmutable((string)$row['arrival']))->modify('-14 days')->format('Y-m-d') : $today);
    $insert = db()->prepare('INSERT INTO booking_payment_schedule(booking_id,installment_type,label,amount,due_date,status,paid_amount,waived_reason,sort_order) VALUES(?,?,?,?,?,?,?,?,?)');
    $affected = 0;
    if ($deposit > 0.005) { $insert->execute([$bookingId, 'deposit', 'Anzahlung', $deposit, $depositDue, $depositDue < $today ? 'overdue' : 'open', 0, null, 10]); $affected += $insert->rowCount(); }
    if ($remaining > 0.005) { $insert->execute([$bookingId, 'remaining', 'Restbetrag', $remaining, $remainingDue, $remainingDue < $today ? 'overdue' : 'open', 0, null, 20]); $affected += $insert->rowCount(); }
    if (class_exists('BookingWorkflowService')) { try { BookingWorkflowService::syncBillingState($bookingId); } catch (Throwable $e) {} }
    return $affected;
}

function pr113_create_customer_access(int $bookingId): int
{
    if (!pr109_table_exists('booking_customer_access')) return 0;
    $exists = pr109_count('SELECT COUNT(*) FROM booking_customer_access WHERE booking_id=? AND active=1', [$bookingId]);
    if ($exists > 0) return 0;
    $row = pr109_fetch_all('SELECT departure FROM bookings WHERE id=? LIMIT 1', [$bookingId])[0] ?? [];
    if (!$row || !class_exists('Crypto')) return 0;
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $departure = (string)($row['departure'] ?? date('Y-m-d'));
    $validUntil = (new DateTimeImmutable($departure ?: 'today'))->modify('+30 days')->format('Y-m-d 23:59:59');
    $stmt = db()->prepare('INSERT INTO booking_customer_access(booking_id,token_hash,token_encrypted,active,valid_until) VALUES(?,?,?,?,?)');
    $stmt->execute([$bookingId, hash('sha256', $token), Crypto::encrypt($token), 1, $validUntil]);
    return $stmt->rowCount();
}

function pr113_create_checkin(int $bookingId): int
{
    if (!pr109_table_exists('booking_checkins')) return 0;
    $exists = pr109_count('SELECT COUNT(*) FROM booking_checkins WHERE booking_id=?', [$bookingId]);
    if ($exists > 0) return 0;
    $row = pr109_fetch_all('SELECT planned_arrival_time,vehicle_plate,special_requests FROM bookings WHERE id=? LIMIT 1', [$bookingId])[0] ?? [];
    if (!$row) return 0;
    $stmt = db()->prepare('INSERT INTO booking_checkins(booking_id,status,planned_arrival_time,vehicle_plate,special_requests) VALUES(?,?,?,?,?)');
    $stmt->execute([$bookingId, 'open', $row['planned_arrival_time'] ?? null, $row['vehicle_plate'] ?? null, $row['special_requests'] ?? null]);
    return $stmt->rowCount();
}

function pr113_create_housekeeping(int $bookingId): int
{
    if (!pr109_table_exists('housekeeping_tasks')) return 0;
    $exists = pr109_count('SELECT COUNT(*) FROM housekeeping_tasks WHERE booking_id=?', [$bookingId]);
    if ($exists > 0) return 0;
    $row = pr109_fetch_all('SELECT id,apartment_id,departure FROM bookings WHERE id=? LIMIT 1', [$bookingId])[0] ?? [];
    $apartmentId = (int)($row['apartment_id'] ?? 0);
    if (!$row || $apartmentId <= 0) return 0;
    $taskDate = (string)($row['departure'] ?? date('Y-m-d'));
    $stmt = db()->prepare("INSERT INTO housekeeping_tasks(apartment_id,booking_id,task_date,task_type,status,origin_type,priority,estimated_minutes,linen_change,towel_change,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$apartmentId, $bookingId, $taskDate, 'turnover', 'open', 'booking', 'normal', 60, 1, 1, 'Automatisch vom Kern-PMS-Ablauf-Assistenten angelegt.']);
    return $stmt->rowCount();
}

function pms_flow_action_v236113(): never
{
    $user = Auth::requireLogin();
    if (!in_array((string)($user['role'] ?? ''), ['admin','manager'], true)) throw new ForbiddenException('Nur Admin/Manager dürfen den Ablauf-Assistenten anwenden.');
    $d = request_data();
    $issue = (string)($d['issue_key'] ?? '');
    $id = (int)($d['id'] ?? 0);
    $bulk = !empty($d['bulk']);
    $reason = trim((string)($d['reason'] ?? 'Kern-PMS-Ablauf-Assistent')) ?: 'Kern-PMS-Ablauf-Assistent';
    $affected = 0;

    $apply = static function(string $issue, int $id) use (&$affected, $reason): void {
        if ($id <= 0) return;
        $before = [];
        $after = [];
        switch ($issue) {
            case 'accepted_offer_without_booking':
                $before = pr109_fetch_all('SELECT * FROM offers WHERE id=? LIMIT 1', [$id])[0] ?? [];
                $affected += pr113_append_note('offers', $id, '[Produktreife] Angenommenes Angebot ohne Buchung: manuelle Buchungserzeugung/Prüfung erforderlich. ' . $reason);
                $after = pr109_fetch_all('SELECT * FROM offers WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('offer', $id, 'pms_flow_mark_review', $before, $after, $reason);
                break;
            case 'offer_linked_to_inactive_booking':
                $before = pr109_fetch_all('SELECT * FROM offers WHERE id=? LIMIT 1', [$id])[0] ?? [];
                if ($before && pr109_column_exists('offers', 'status')) { $stmt=db()->prepare("UPDATE offers SET status='archived', archived_at=COALESCE(archived_at,NOW()) WHERE id=?"); $stmt->execute([$id]); $affected += $stmt->rowCount(); }
                $after = pr109_fetch_all('SELECT * FROM offers WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('offer', $id, 'pms_flow_archive_inactive_booking_link', $before, $after, $reason);
                break;
            case 'booking_without_apartment':
                $before = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                if ($before && pr109_column_exists('bookings', 'status')) { $stmt=db()->prepare("UPDATE bookings SET status='clarification_required' WHERE id=?"); $stmt->execute([$id]); $affected += $stmt->rowCount(); }
                $affected += pr113_append_note('bookings', $id, '[Produktreife] Buchung ohne Wohnung: interne Zuordnung/Klärung erforderlich. ' . $reason);
                $after = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('booking', $id, 'pms_flow_clarification_no_apartment', $before, $after, $reason);
                break;
            case 'booking_without_payment_schedule':
                $before = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                $affected += pr113_create_payment_schedule($id);
                $after = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('booking', $id, 'pms_flow_create_payment_schedule', $before, $after, $reason);
                break;
            case 'payment_schedule_mismatch':
                $before = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                $affected += pr113_append_note('bookings', $id, '[Produktreife] Zahlungsplan weicht von Buchungssumme ab: Abrechnung manuell prüfen. ' . $reason);
                if (class_exists('BookingWorkflowService')) { try { BookingWorkflowService::syncBillingState($id); } catch (Throwable $e) {} }
                $after = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('booking', $id, 'pms_flow_mark_billing_mismatch', $before, $after, $reason);
                break;
            case 'booking_without_customer_access':
                $before = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                $affected += pr113_create_customer_access($id);
                $after = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('booking', $id, 'pms_flow_create_customer_access', $before, $after, $reason);
                break;
            case 'booking_without_checkin':
                $before = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                $affected += pr113_create_checkin($id);
                $after = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('booking', $id, 'pms_flow_create_checkin', $before, $after, $reason);
                break;
            case 'booking_without_housekeeping':
                $before = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                $affected += pr113_create_housekeeping($id);
                $after = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('booking', $id, 'pms_flow_create_housekeeping', $before, $after, $reason);
                break;
        }
    };

    if ($bulk) {
        $review = pr113_build_flow_review();
        foreach (($review['groups'] ?? []) as $group) if ((string)($group['key'] ?? '') === $issue) foreach (($group['items'] ?? []) as $item) $apply($issue, (int)($item['id'] ?? 0));
    } else {
        if ($id <= 0) throw new ValidationException('Eintrag fehlt.');
        $apply($issue, $id);
    }
    json_response(['ok' => true, 'message' => $affected . ' Änderung(en) im Kern-PMS-Ablauf-Assistenten ausgeführt.', 'affected' => $affected]);
}

function pr114_money(float $value): float
{
    return round($value, 2);
}

function pr114_billing_review_group(string $key, string $title, string $description, string $actionLabel, array $items): array
{
    return [
        'key' => $key,
        'title' => $title,
        'description' => $description,
        'action_label' => $actionLabel,
        'count' => count($items),
        'items' => $items,
    ];
}

function pr114_active_booking_sql(string $alias = 'b'): string
{
    return pr109_active_booking_condition($alias);
}

function pr114_build_billing_review(): array
{
    $groups = [];
    $limit = pr109_limit();
    if (!pr109_table_exists('bookings')) return ['ok' => true, 'groups' => [], 'total' => 0];
    $active = pr114_active_booking_sql('b');

    if (pr109_table_exists('booking_payment_schedule')) {
        $items = pr109_fetch_all("SELECT * FROM (SELECT b.id, COALESCE(b.reference, CONCAT('Buchung #', b.id)) number,
            CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest,
            b.arrival, b.departure, COALESCE(b.payment_status,'') status,
            COALESCE(b.total_price,0) amount, COALESCE(SUM(s.amount),0) scheduled
            FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id LEFT JOIN booking_payment_schedule s ON s.booking_id=b.id
            WHERE $active AND COALESCE(b.total_price,0)>0
            GROUP BY b.id HAVING ABS(COALESCE(b.total_price,0)-scheduled)>0.05) x
            ORDER BY arrival ASC, id DESC LIMIT " . $limit);
        $groups[] = pr114_billing_review_group('billing_schedule_total_mismatch', 'Zahlungsplan passt nicht zur Buchungssumme', 'Die Summe der Zahlungsziele weicht von der Buchungssumme ab. Das muss vor Rechnung/Mahnung geklärt werden.', 'Zur Abrechnungsprüfung markieren', $items);

        $items = pr109_fetch_all("SELECT s.id, s.booking_id, COALESCE(b.reference, CONCAT('Buchung #', b.id)) number,
            CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest,
            b.arrival, b.departure, COALESCE(s.status,'') status, COALESCE(s.amount,0) amount, COALESCE(s.paid_amount,0) paid_amount
            FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id LEFT JOIN guests g ON g.id=b.guest_id
            WHERE $active AND (COALESCE(s.amount,0)<0 OR COALESCE(s.paid_amount,0)<0 OR COALESCE(s.paid_amount,0)>COALESCE(s.amount,0)+0.05)
            ORDER BY s.id DESC LIMIT " . $limit);
        $groups[] = pr114_billing_review_group('billing_invalid_schedule_values', 'Ungültige Werte im Zahlungsplan', 'Ein Zahlungsziel hat negative Werte oder mehr bezahlt als gefordert. Das muss manuell geprüft werden.', 'Zur Prüfung markieren', $items);

        $items = pr109_fetch_all("SELECT s.id, s.booking_id, COALESCE(b.reference, CONCAT('Buchung #', b.id)) number,
            CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest,
            b.arrival, b.departure, COALESCE(s.status,'') status, COALESCE(s.amount,0) amount, s.due_date
            FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id LEFT JOIN guests g ON g.id=b.guest_id
            WHERE $active AND LOWER(COALESCE(s.status,'')) IN ('open','partial') AND COALESCE(s.amount,0)>COALESCE(s.paid_amount,0)+0.05 AND s.due_date IS NOT NULL AND s.due_date<CURDATE()
            ORDER BY s.due_date ASC, s.id DESC LIMIT " . $limit);
        $groups[] = pr114_billing_review_group('billing_overdue_not_marked', 'Überfällige Zahlungsziele nicht als überfällig markiert', 'Diese Zahlungsziele sind fällig, stehen aber noch offen/teiloffen. Der Status kann sicher neu berechnet werden.', 'Zahlungsstatus neu berechnen', $items);
    }

    if (pr109_table_exists('booking_payments')) {
        $items = pr109_fetch_all("SELECT * FROM (SELECT b.id, COALESCE(b.reference, CONCAT('Buchung #', b.id)) number,
            CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest,
            b.arrival, b.departure, COALESCE(b.payment_status,'') status,
            COALESCE(b.paid_amount,0) amount, COALESCE(SUM(CASE WHEN p.status='received' THEN p.amount ELSE 0 END),0) paid_sum
            FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id LEFT JOIN booking_payments p ON p.booking_id=b.id
            WHERE $active GROUP BY b.id HAVING ABS(COALESCE(b.paid_amount,0)-paid_sum)>0.05) x
            ORDER BY arrival ASC, id DESC LIMIT " . $limit);
        $groups[] = pr114_billing_review_group('billing_booking_paid_mismatch', 'Buchungsstatus bezahlt stimmt nicht mit Zahlungen überein', 'Die gespeicherte bezahlte Summe der Buchung weicht von den erhaltenen Zahlungen ab. Das kann sicher neu synchronisiert werden.', 'Zahlungsstatus synchronisieren', $items);

        $inactive = pr109_inactive_booking_condition('b');
        $items = pr109_fetch_all("SELECT p.id, p.booking_id, COALESCE(b.reference, CONCAT('Buchung #', b.id)) number,
            CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest,
            b.arrival, b.departure, COALESCE(p.status,'') status, COALESCE(p.amount,0) amount
            FROM booking_payments p LEFT JOIN bookings b ON b.id=p.booking_id LEFT JOIN guests g ON g.id=b.guest_id
            WHERE p.booking_id IS NOT NULL AND (b.id IS NULL OR $inactive) AND LOWER(COALESCE(p.status,'')) IN ('received','open','partial')
            ORDER BY p.id DESC LIMIT " . $limit);
        $groups[] = pr114_billing_review_group('billing_payment_without_active_booking', 'Zahlungen zu nicht aktiven Buchungen', 'Diese Zahlungen hängen an fehlenden/gelöschten/stornierten Buchungen. Sie dürfen nicht als aktueller Umsatz laufen.', 'Zur Abrechnungsprüfung markieren', $items);
    }

    if (pr109_table_exists('booking_payment_schedule')) {
        $inactive = pr109_inactive_booking_condition('b');
        $items = pr109_fetch_all("SELECT s.id, s.booking_id, COALESCE(b.reference, CONCAT('Buchung #', b.id)) number,
            CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest,
            b.arrival, b.departure, COALESCE(s.status,'') status, COALESCE(s.amount,0) amount
            FROM booking_payment_schedule s LEFT JOIN bookings b ON b.id=s.booking_id LEFT JOIN guests g ON g.id=b.guest_id
            WHERE s.booking_id IS NOT NULL AND (b.id IS NULL OR $inactive) AND LOWER(COALESCE(s.status,'')) NOT IN ('paid','received','waived','cancelled','canceled','void','aufgehoben','storniert')
            ORDER BY s.id DESC LIMIT " . $limit);
        $groups[] = pr114_billing_review_group('billing_open_schedule_without_active_booking', 'Offene Zahlungsziele ohne aktive Buchung', 'Diese Zahlungsziele gehören nicht mehr zu einer aktiven Buchung und sollen nicht gemahnt werden.', 'Zahlungsziel aufheben', $items);
    }

    $groups = array_values(array_filter($groups, static fn(array $g): bool => (int)$g['count'] > 0));
    return ['ok' => true, 'groups' => $groups, 'total' => array_sum(array_map(static fn($g) => (int)$g['count'], $groups))];
}

function pms_billing_review_v236114(): never
{
    Auth::requireLogin();
    if (class_exists('BookingWorkflowService')) { try { BookingWorkflowService::syncBillingState(); } catch (Throwable $e) {} }
    json_response(pr114_build_billing_review());
}

function pr114_mark_billing_review(string $entity, int $id, string $reason): int
{
    $table = $entity === 'booking' ? 'bookings' : ($entity === 'payment_schedule' ? 'booking_payment_schedule' : ($entity === 'payment' ? 'booking_payments' : ''));
    if ($table === '' || !pr109_table_exists($table)) return 0;
    foreach (['internal_notes','notes','completion_notes','waived_reason','reference'] as $col) {
        if (pr109_column_exists($table, $col)) {
            $stmt = db()->prepare("UPDATE $table SET $col=CONCAT(COALESCE($col,''), CASE WHEN COALESCE($col,'')='' THEN '' ELSE '\n' END, ?) WHERE id=?");
            $stmt->execute(['[Produktreife Abrechnung] '.$reason, $id]);
            return $stmt->rowCount();
        }
    }
    return 0;
}

function pms_billing_action_v236114(): never
{
    $user = Auth::requireLogin();
    if (!in_array((string)($user['role'] ?? ''), ['admin','manager'], true)) throw new ForbiddenException('Nur Admin/Manager dürfen den Abrechnungs-Assistenten anwenden.');
    $d = request_data();
    $issue = (string)($d['issue_key'] ?? '');
    $id = (int)($d['id'] ?? 0);
    $bulk = !empty($d['bulk']);
    $reason = trim((string)($d['reason'] ?? 'Abrechnungsstatus-Assistent')) ?: 'Abrechnungsstatus-Assistent';
    $affected = 0;
    $apply = static function(string $issue, int $id) use (&$affected, $reason): void {
        if ($id <= 0) return;
        switch ($issue) {
            case 'billing_schedule_total_mismatch':
            case 'billing_invalid_schedule_values':
                $before = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                $affected += pr114_mark_billing_review('booking', $id, 'Manuelle Prüfung erforderlich: ' . $issue . '. ' . $reason);
                if (class_exists('BookingWorkflowService')) { try { BookingWorkflowService::syncBillingState($id); } catch (Throwable $e) {} }
                $after = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('booking', $id, 'pms_billing_mark_review', $before, $after, $reason);
                break;
            case 'billing_overdue_not_marked':
                $row = pr109_fetch_all('SELECT booking_id FROM booking_payment_schedule WHERE id=? LIMIT 1', [$id])[0] ?? [];
                $bookingId = (int)($row['booking_id'] ?? 0);
                $before = pr109_fetch_all('SELECT * FROM booking_payment_schedule WHERE id=? LIMIT 1', [$id])[0] ?? [];
                if (class_exists('BookingWorkflowService')) { try { BookingWorkflowService::refreshOverdueStatuses(); if ($bookingId>0) BookingWorkflowService::syncBillingState($bookingId); } catch (Throwable $e) {} }
                $after = pr109_fetch_all('SELECT * FROM booking_payment_schedule WHERE id=? LIMIT 1', [$id])[0] ?? [];
                if ($before != $after) $affected++;
                pr109_audit('payment_schedule', $id, 'pms_billing_refresh_overdue', $before, $after, $reason);
                break;
            case 'billing_booking_paid_mismatch':
                $before = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                if (class_exists('BookingWorkflowService')) { try { BookingWorkflowService::syncBillingState($id); } catch (Throwable $e) {} }
                $after = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$id])[0] ?? [];
                if ($before != $after) $affected++;
                pr109_audit('booking', $id, 'pms_billing_sync_paid_amount', $before, $after, $reason);
                break;
            case 'billing_payment_without_active_booking':
                $before = pr109_fetch_all('SELECT * FROM booking_payments WHERE id=? LIMIT 1', [$id])[0] ?? [];
                $affected += pr114_mark_billing_review('payment', $id, 'Zahlung hängt an nicht aktiver Buchung. Nicht automatisch löschen. ' . $reason);
                $after = pr109_fetch_all('SELECT * FROM booking_payments WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('booking_payment', $id, 'pms_billing_payment_review', $before, $after, $reason);
                break;
            case 'billing_open_schedule_without_active_booking':
                $before = pr109_fetch_all('SELECT * FROM booking_payment_schedule WHERE id=? LIMIT 1', [$id])[0] ?? [];
                if ($before && pr109_column_exists('booking_payment_schedule', 'status')) {
                    $stmt = db()->prepare("UPDATE booking_payment_schedule SET status='waived', waived_reason=CONCAT(COALESCE(waived_reason,''), CASE WHEN COALESCE(waived_reason,'')='' THEN '' ELSE '\n' END, ?) WHERE id=? AND LOWER(COALESCE(status,'')) NOT IN ('paid','received','waived','cancelled','canceled','void','aufgehoben','storniert')");
                    $stmt->execute([$reason, $id]);
                    $affected += $stmt->rowCount();
                }
                $after = pr109_fetch_all('SELECT * FROM booking_payment_schedule WHERE id=? LIMIT 1', [$id])[0] ?? [];
                pr109_audit('payment_schedule', $id, 'pms_billing_waive_inactive_booking', $before, $after, $reason);
                break;
        }
    };
    if ($bulk) {
        $review = pr114_build_billing_review();
        foreach (($review['groups'] ?? []) as $group) if ((string)($group['key'] ?? '') === $issue) foreach (($group['items'] ?? []) as $item) $apply($issue, (int)($item['id'] ?? 0));
    } else {
        if ($id <= 0) throw new ValidationException('Eintrag fehlt.');
        $apply($issue, $id);
    }
    json_response(['ok' => true, 'message' => $affected . ' Änderung(en) im Abrechnungsstatus-Assistenten ausgeführt.', 'affected' => $affected]);
}


function pr115_housekeeping_review_group(string $key, string $title, string $description, string $actionLabel, array $items): array
{
    return ['key'=>$key,'title'=>$title,'description'=>$description,'action_label'=>$actionLabel,'count'=>count($items),'items'=>$items];
}

function pr115_housekeeping_active_status_sql(string $alias='t'): string
{
    return "LOWER(COALESCE($alias.status,'')) NOT IN ('released','cancelled','canceled','storniert','freigegeben')";
}

function pr115_blocking_incident_sql(string $alias='t'): string
{
    if (!pr109_table_exists('housekeeping_incidents')) return '0';
    return "(SELECT COUNT(*) FROM housekeeping_incidents i WHERE i.task_id=$alias.id AND i.status IN ('open','review') AND (i.severity IN ('high','critical') OR i.apartment_usable=0))";
}

function pr115_build_housekeeping_review(): array
{
    $groups=[]; $limit=pr109_limit();
    if (!pr109_table_exists('housekeeping_tasks')) return ['ok'=>true,'groups'=>[],'total'=>0];
    $active = pr115_housekeeping_active_status_sql('t');
    $block = pr115_blocking_incident_sql('t');

    $items = pr109_fetch_all("SELECT t.id, t.booking_id, COALESCE(a.code,a.name,CONCAT('Wohnung #',t.apartment_id)) number,
        COALESCE(t.task_type,'') task_type, t.task_date arrival, COALESCE(t.status,'') status,
        COALESCE(t.priority,'normal') priority, COALESCE(t.assigned_to,'') assigned_to
        FROM housekeeping_tasks t LEFT JOIN apartments a ON a.id=t.apartment_id
        WHERE $active AND LOWER(COALESCE(t.status,'')) IN ('open','assigned','in_progress')
          AND t.task_date < DATE_SUB(CURDATE(), INTERVAL 2 DAY)
        ORDER BY t.task_date ASC, t.id DESC LIMIT $limit");
    $groups[] = pr115_housekeeping_review_group('hk_stale_open', 'Alte offene Housekeeping-Aufgaben', 'Diese Aufgaben sind älter als zwei Tage und blockieren die Betriebsübersicht. Sie werden nicht automatisch abgeschlossen, sondern zur Leitungsprüfung markiert.', 'Zur Prüfung markieren', $items);

    $items = pr109_fetch_all("SELECT t.id, t.booking_id, COALESCE(a.code,a.name,CONCAT('Wohnung #',t.apartment_id)) number,
        COALESCE(t.task_type,'') task_type, t.task_date arrival, COALESCE(t.status,'') status,
        t.cleaning_completed_at, t.inspected_at, ($block) blocking_incidents
        FROM housekeeping_tasks t LEFT JOIN apartments a ON a.id=t.apartment_id
        WHERE $active AND (LOWER(COALESCE(t.status,'')) IN ('done','cleaning_done') OR t.cleaning_completed_at IS NOT NULL)
          AND t.inspected_at IS NULL
        ORDER BY t.task_date ASC, t.id DESC LIMIT $limit");
    $groups[] = pr115_housekeeping_review_group('hk_cleaning_done_needs_inspection', 'Reinigung abgeschlossen, Kontrolle fehlt', 'Die Reinigung wurde gemeldet, aber die Kontrollstufe fehlt. Diese Aufgaben müssen im Housekeeping-Prozess sichtbar nachkontrolliert werden.', 'Kontrolle anfordern', $items);

    $items = pr109_fetch_all("SELECT t.id, t.booking_id, COALESCE(a.code,a.name,CONCAT('Wohnung #',t.apartment_id)) number,
        COALESCE(t.task_type,'') task_type, t.task_date arrival, COALESCE(t.status,'') status,
        t.inspected_at, t.ready_reported_at, COALESCE(t.inspection_result,'') inspection_result, ($block) blocking_incidents
        FROM housekeeping_tasks t LEFT JOIN apartments a ON a.id=t.apartment_id
        WHERE $active AND t.inspected_at IS NOT NULL AND t.ready_reported_at IS NULL AND ($block)=0
        ORDER BY t.task_date ASC, t.id DESC LIMIT $limit");
    $groups[] = pr115_housekeeping_review_group('hk_inspected_not_ready', 'Kontrolliert, aber nicht bezugsbereit gemeldet', 'Die Kontrolle ist dokumentiert, aber der Bezugsbereit-Schritt fehlt. Ohne diesen Status bleibt die Wohnung im Prozess unklar.', 'Bezugsbereit melden', $items);

    $items = pr109_fetch_all("SELECT t.id, t.booking_id, COALESCE(a.code,a.name,CONCAT('Wohnung #',t.apartment_id)) number,
        COALESCE(t.task_type,'') task_type, t.task_date arrival, COALESCE(t.status,'') status,
        t.ready_reported_at, t.released_at, ($block) blocking_incidents
        FROM housekeeping_tasks t LEFT JOIN apartments a ON a.id=t.apartment_id
        WHERE $active AND t.ready_reported_at IS NOT NULL AND t.released_at IS NULL AND ($block)=0
        ORDER BY t.task_date ASC, t.id DESC LIMIT $limit");
    $groups[] = pr115_housekeeping_review_group('hk_ready_not_released', 'Bezugsbereit, aber nicht final freigegeben', 'Die Wohnung ist bezugsbereit gemeldet, aber die finale Freigabe durch Admin/Rezeption fehlt noch.', 'Final freigeben', $items);

    if (pr109_table_exists('housekeeping_incidents')) {
        $items = pr109_fetch_all("SELECT t.id, t.booking_id, COALESCE(a.code,a.name,CONCAT('Wohnung #',t.apartment_id)) number,
            COALESCE(t.task_type,'') task_type, t.task_date arrival, COALESCE(t.status,'') status, ($block) blocking_incidents
            FROM housekeeping_tasks t LEFT JOIN apartments a ON a.id=t.apartment_id
            WHERE $active AND ($block)>0
            ORDER BY t.task_date ASC, t.id DESC LIMIT $limit");
        $groups[] = pr115_housekeeping_review_group('hk_blocked_by_incident', 'Aufgaben durch offene Mängel blockiert', 'Diese Aufgaben haben offene oder kritische Mängel. Sie dürfen nicht automatisch freigegeben werden, sondern müssen bewusst geprüft werden.', 'Blockade prüfen markieren', $items);
    }

    $items = pr109_fetch_all("SELECT t.id, t.booking_id, COALESCE(a.code,a.name,CONCAT('Wohnung #',t.apartment_id)) number,
        COALESCE(t.task_type,'') task_type, t.task_date arrival, COALESCE(t.status,'') status,
        COALESCE(t.assigned_to,'') assigned_to
        FROM housekeeping_tasks t LEFT JOIN apartments a ON a.id=t.apartment_id
        WHERE $active AND LOWER(COALESCE(t.status,'')) IN ('open','assigned')
          AND COALESCE(t.member_id,0)=0 AND COALESCE(t.team_id,0)=0 AND COALESCE(TRIM(t.assigned_to),'')=''
        ORDER BY t.task_date ASC, t.id DESC LIMIT $limit");
    $groups[] = pr115_housekeeping_review_group('hk_unassigned_active', 'Aktive Aufgaben ohne Zuweisung', 'Aktive Aufgaben ohne Team/Mitarbeiter bleiben im Betrieb leicht liegen.', 'Zur Zuweisung markieren', $items);

    $groups = array_values(array_filter($groups, static fn(array $g): bool => (int)$g['count'] > 0));
    return ['ok'=>true,'groups'=>$groups,'total'=>array_sum(array_map(static fn($g)=>(int)$g['count'],$groups))];
}

function pms_housekeeping_review_v236115(): never
{
    Auth::requireLogin();
    json_response(pr115_build_housekeeping_review());
}

function pr115_append_task_note(int $id, string $note): int
{
    if (!pr109_table_exists('housekeeping_tasks')) return 0;
    $stmt = db()->prepare("UPDATE housekeeping_tasks SET notes=CONCAT(COALESCE(notes,''), CASE WHEN COALESCE(notes,'')='' THEN '' ELSE '\n' END, ?) WHERE id=?");
    $stmt->execute([$note, $id]);
    return $stmt->rowCount();
}

function pms_housekeeping_action_v236115(): never
{
    $user = Auth::requireLogin();
    if (!in_array((string)($user['role'] ?? ''), ['admin','manager','reception','housekeeping_manager'], true)) throw new ForbiddenException('Nur Admin, Rezeption oder Housekeeping-Leitung dürfen den Housekeeping-Assistenten anwenden.');
    $d=request_data(); $issue=(string)($d['issue_key']??''); $id=(int)($d['id']??0); $bulk=!empty($d['bulk']);
    $reason=trim((string)($d['reason']??'Housekeeping-Freigabe-Assistent')) ?: 'Housekeeping-Freigabe-Assistent';
    $affected=0; $uid=(int)($user['id']??0);
    $apply = static function(string $issue, int $id) use (&$affected, $reason, $uid): void {
        if ($id<=0 || !pr109_table_exists('housekeeping_tasks')) return;
        $before=pr109_fetch_all('SELECT * FROM housekeeping_tasks WHERE id=? LIMIT 1',[$id])[0]??[];
        if (!$before) return;
        switch ($issue) {
            case 'hk_stale_open':
            case 'hk_unassigned_active':
            case 'hk_blocked_by_incident':
                $affected += pr115_append_task_note($id, '[Produktreife Housekeeping] Zur Leitungsprüfung markiert: '.$issue.'. '.$reason);
                if (pr109_column_exists('housekeeping_tasks','priority')) {
                    $stmt=db()->prepare("UPDATE housekeeping_tasks SET priority=CASE WHEN priority IN ('urgent','high') THEN priority ELSE 'high' END WHERE id=?");
                    $stmt->execute([$id]); $affected += $stmt->rowCount();
                }
                break;
            case 'hk_cleaning_done_needs_inspection':
                $affected += pr115_append_task_note($id, '[Produktreife Housekeeping] Kontrolle erforderlich. '.$reason);
                if (pr109_column_exists('housekeeping_tasks','status')) {
                    $stmt=db()->prepare("UPDATE housekeeping_tasks SET status='cleaning_done', cleaning_completed_at=COALESCE(cleaning_completed_at,NOW()) WHERE id=? AND LOWER(COALESCE(status,'')) NOT IN ('released','cancelled','canceled','storniert')");
                    $stmt->execute([$id]); $affected += $stmt->rowCount();
                }
                break;
            case 'hk_inspected_not_ready':
                $blocking = (int)pr109_count("SELECT COUNT(*) FROM housekeeping_incidents WHERE task_id=? AND status IN ('open','review') AND (severity IN ('high','critical') OR apartment_usable=0)",[$id]);
                if ($blocking===0) {
                    $stmt=db()->prepare("UPDATE housekeeping_tasks SET status='ready', ready_reported_at=COALESCE(ready_reported_at,NOW()), ready_reported_by=COALESCE(ready_reported_by,?) WHERE id=? AND released_at IS NULL");
                    $stmt->execute([$uid,$id]); $affected += $stmt->rowCount();
                    $affected += pr115_append_task_note($id, '[Produktreife Housekeeping] Nach dokumentierter Kontrolle als bezugsbereit gemeldet. '.$reason);
                }
                break;
            case 'hk_ready_not_released':
                $blocking = (int)pr109_count("SELECT COUNT(*) FROM housekeeping_incidents WHERE task_id=? AND status IN ('open','review') AND (severity IN ('high','critical') OR apartment_usable=0)",[$id]);
                if ($blocking===0) {
                    $stmt=db()->prepare("UPDATE housekeeping_tasks SET status='released', released_at=COALESCE(released_at,NOW()), released_by=COALESCE(released_by,?), release_note=CONCAT(COALESCE(release_note,''), CASE WHEN COALESCE(release_note,'')='' THEN '' ELSE '\n' END, ?) WHERE id=? AND ready_reported_at IS NOT NULL AND released_at IS NULL");
                    $stmt->execute([$uid,'[Produktreife Housekeeping] Finale Freigabe durch Housekeeping-Assistent. '.$reason,$id]); $affected += $stmt->rowCount();
                }
                break;
        }
        $after=pr109_fetch_all('SELECT * FROM housekeeping_tasks WHERE id=? LIMIT 1',[$id])[0]??[];
        pr109_audit('housekeeping_task',$id,'pms_housekeeping_'.$issue,$before,$after,$reason);
    };
    if ($bulk) {
        $review=pr115_build_housekeeping_review();
        foreach (($review['groups']??[]) as $group) if ((string)($group['key']??'')===$issue) foreach (($group['items']??[]) as $item) $apply($issue,(int)($item['id']??0));
    } else { if ($id<=0) throw new ValidationException('Eintrag fehlt.'); $apply($issue,$id); }
    json_response(['ok'=>true,'message'=>$affected.' Änderung(en) im Housekeeping-Freigabe-Assistenten ausgeführt.','affected'=>$affected]);
}


function pr117_status_lifecycle_group(string $key, string $title, string $description, string $actionLabel, array $items): array
{
    return [
        'key' => $key,
        'title' => $title,
        'description' => $description,
        'action_label' => $actionLabel,
        'count' => count($items),
        'items' => $items,
    ];
}

function pr117_offer_terminal_sql(string $alias = 'o'): string
{
    $parts = [];
    if (pr109_column_exists('offers', 'status')) {
        $parts[] = "LOWER(COALESCE($alias.status,'')) IN ('archived','archive','cancelled','canceled','storniert','deleted','gelöscht','declined','abgelehnt','expired','void')";
    }
    if (pr109_column_exists('offers', 'deleted_at')) $parts[] = "$alias.deleted_at IS NOT NULL";
    if (pr109_column_exists('offers', 'archived_at')) $parts[] = "$alias.archived_at IS NOT NULL";
    return $parts ? '(' . implode(' OR ', $parts) . ')' : '0=1';
}

function pr117_booking_terminal_sql(string $alias = 'b'): string
{
    return pr109_inactive_booking_condition($alias);
}

function pr117_build_lifecycle_review(): array
{
    $groups = [];
    $limit = pr109_limit();

    if (pr109_table_exists('offers') && pr109_table_exists('bookings') && pr109_column_exists('offers','booking_id')) {
        $offerTerminal = pr117_offer_terminal_sql('o');
        $bookingActive = pr109_active_booking_condition('b');
        $items = pr109_fetch_all("SELECT o.id, COALESCE(o.offer_number, CONCAT('Angebot #',o.id)) number, COALESCE(o.guest_name,'') guest, COALESCE(o.guest_email,'') email, o.arrival, o.departure, COALESCE(o.status,'') status, o.booking_id
            FROM offers o JOIN bookings b ON b.id=o.booking_id
            WHERE $offerTerminal AND $bookingActive
            ORDER BY o.id DESC LIMIT " . $limit);
        $groups[] = pr117_status_lifecycle_group('terminal_offer_with_active_booking', 'Archiviertes/storniertes Angebot mit aktiver Buchung', 'Ein Angebot ist beendet, aber die verknüpfte Buchung läuft noch aktiv. Das kann Dashboard, Abrechnung und Kundenportal widersprüchlich machen.', 'Buchung zur Prüfung markieren', $items);
    }

    if (pr109_table_exists('offers') && pr109_table_exists('booking_payment_schedule') && pr109_column_exists('booking_payment_schedule','offer_id')) {
        $offerTerminal = pr117_offer_terminal_sql('o');
        $items = pr109_fetch_all("SELECT s.id, s.offer_id, COALESCE(o.offer_number, CONCAT('Angebot #',o.id)) number, COALESCE(o.guest_name,'') guest, o.arrival, o.departure, COALESCE(s.status,'') status, COALESCE(s.amount,0) amount
            FROM booking_payment_schedule s JOIN offers o ON o.id=s.offer_id
            WHERE $offerTerminal AND LOWER(COALESCE(s.status,'')) NOT IN ('paid','received','waived','cancelled','canceled','void','aufgehoben','storniert')
            ORDER BY s.id DESC LIMIT " . $limit);
        $groups[] = pr117_status_lifecycle_group('open_schedule_for_terminal_offer', 'Offenes Zahlungsziel zu beendetem Angebot', 'Zahlungsziele zu archivierten/stornierten Angeboten dürfen nicht weiter als offen/mahnbar gelten.', 'Zahlungsziel aufheben', $items);
    }

    if (pr109_table_exists('bookings') && pr109_table_exists('booking_payment_schedule')) {
        $terminal = pr117_booking_terminal_sql('b');
        $items = pr109_fetch_all("SELECT s.id, s.booking_id, COALESCE(b.reference, CONCAT('Buchung #',b.id)) number, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest, b.arrival, b.departure, COALESCE(s.status,'') status, COALESCE(s.amount,0) amount
            FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id LEFT JOIN guests g ON g.id=b.guest_id
            WHERE $terminal AND LOWER(COALESCE(s.status,'')) NOT IN ('paid','received','waived','cancelled','canceled','void','aufgehoben','storniert')
            ORDER BY s.id DESC LIMIT " . $limit);
        $groups[] = pr117_status_lifecycle_group('open_schedule_for_terminal_booking', 'Offenes Zahlungsziel zu stornierter/gelöschter Buchung', 'Stornierte oder gelöschte Buchungen dürfen keine offenen Zahlungsziele mehr in aktiven Listen erzeugen.', 'Zahlungsziel aufheben', $items);
    }

    if (pr109_table_exists('bookings') && pr109_table_exists('booking_customer_access')) {
        $terminal = pr117_booking_terminal_sql('b');
        $items = pr109_fetch_all("SELECT a.id, a.booking_id, COALESCE(b.reference, CONCAT('Buchung #',b.id)) number, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest, b.arrival, b.departure, COALESCE(b.status,'') status
            FROM booking_customer_access a JOIN bookings b ON b.id=a.booking_id LEFT JOIN guests g ON g.id=b.guest_id
            WHERE $terminal AND COALESCE(a.active,1)=1
            ORDER BY a.id DESC LIMIT " . $limit);
        $groups[] = pr117_status_lifecycle_group('active_customer_access_for_terminal_booking', 'Aktiver Kundenportal-Zugang zu beendeter Buchung', 'Kundenportal-Zugänge zu stornierten/gelöschten Buchungen sollen nicht aktiv bleiben.', 'Kundenportal-Zugang deaktivieren', $items);
    }

    if (pr109_table_exists('bookings') && pr109_table_exists('booking_checkins')) {
        $terminal = pr117_booking_terminal_sql('b');
        $items = pr109_fetch_all("SELECT c.id, c.booking_id, COALESCE(b.reference, CONCAT('Buchung #',b.id)) number, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest, b.arrival, b.departure, COALESCE(c.status,'') status
            FROM booking_checkins c JOIN bookings b ON b.id=c.booking_id LEFT JOIN guests g ON g.id=b.guest_id
            WHERE $terminal AND LOWER(COALESCE(c.status,'')) NOT IN ('cancelled','canceled','closed','completed','storniert','deleted')
            ORDER BY c.id DESC LIMIT " . $limit);
        $groups[] = pr117_status_lifecycle_group('open_checkin_for_terminal_booking', 'Offener Check-in zu beendeter Buchung', 'Check-in-Arbeitsdaten zu stornierten/gelöschten Buchungen sollen geschlossen werden.', 'Check-in schließen', $items);
    }

    if (pr109_table_exists('bookings') && pr109_table_exists('housekeeping_tasks')) {
        $terminal = pr117_booking_terminal_sql('b');
        $items = pr109_fetch_all("SELECT t.id, t.booking_id, COALESCE(t.title, CONCAT('Putzaufgabe #',t.id)) number, COALESCE(t.task_date,t.due_date) arrival, NULL departure, COALESCE(t.status,'') status
            FROM housekeeping_tasks t JOIN bookings b ON b.id=t.booking_id
            WHERE $terminal AND LOWER(COALESCE(t.status,'')) NOT IN ('done','completed','released','cancelled','canceled','storniert')
            ORDER BY t.id DESC LIMIT " . $limit);
        $groups[] = pr117_status_lifecycle_group('active_housekeeping_for_terminal_booking', 'Aktive Housekeeping-Aufgabe zu beendeter Buchung', 'Housekeeping-Aufgaben zu beendeten Buchungen dürfen nicht weiter als offene Betriebsarbeit erscheinen.', 'Housekeeping-Aufgabe stornieren', $items);
    }

    if (pr109_table_exists('bookings') && pr109_table_exists('booking_documents')) {
        $terminal = pr117_booking_terminal_sql('b');
        $docActive = pr109_column_exists('booking_documents','deleted_at') ? " AND (d.deleted_at IS NULL OR d.deleted_at='0000-00-00 00:00:00')" : '';
        $items = pr109_fetch_all("SELECT d.id, d.booking_id, COALESCE(d.document_number,d.title,CONCAT('Dokument #',d.id)) number, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest, b.arrival, b.departure, COALESCE(d.status,'') status
            FROM booking_documents d JOIN bookings b ON b.id=d.booking_id LEFT JOIN guests g ON g.id=b.guest_id
            WHERE $terminal $docActive AND LOWER(COALESCE(d.status,'')) NOT IN ('cancelled','canceled','deleted','storniert','void')
            ORDER BY d.id DESC LIMIT " . $limit);
        $groups[] = pr117_status_lifecycle_group('active_document_for_terminal_booking', 'Aktives Dokument zu beendeter Buchung', 'Dokumente zu beendeten Buchungen sollen aus aktiven Arbeitslisten verschwinden, aber nicht hart gelöscht werden.', 'Dokument aus aktiven Listen ausblenden', $items);
    }

    $groups = array_values(array_filter($groups, static fn(array $g): bool => (int)$g['count'] > 0));
    return ['ok'=>true,'groups'=>$groups,'total'=>array_sum(array_map(static fn($g)=>(int)$g['count'],$groups))];
}

function pms_lifecycle_review_v236117(): never
{
    Auth::requireLogin();
    json_response(pr117_build_lifecycle_review());
}

function pms_lifecycle_action_v236117(): never
{
    $user = Auth::requireLogin();
    if (!in_array((string)($user['role'] ?? ''), ['admin','manager','reception'], true)) {
        throw new ForbiddenException('Nur Admin, Manager oder Rezeption dürfen den Status-/Archiv-Assistenten anwenden.');
    }
    $d = request_data();
    $issue = (string)($d['issue_key'] ?? '');
    $id = (int)($d['id'] ?? 0);
    $bulk = !empty($d['bulk']);
    $reason = trim((string)($d['reason'] ?? 'Status-/Archiv-Assistent')) ?: 'Status-/Archiv-Assistent';
    $affected = 0;
    $pdo = db();

    $apply = static function(string $issue, int $id) use (&$affected, $pdo, $reason): void {
        if ($id <= 0) return;
        switch ($issue) {
            case 'terminal_offer_with_active_booking':
                if (!pr109_table_exists('offers') || !pr109_table_exists('bookings') || !pr109_column_exists('offers','booking_id')) return;
                $offer = pr109_fetch_all('SELECT * FROM offers WHERE id=? LIMIT 1',[$id])[0] ?? [];
                $bookingId = (int)($offer['booking_id'] ?? 0);
                if ($bookingId <= 0) return;
                $before = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1',[$bookingId])[0] ?? [];
                if (!$before) return;
                $affected += pr113_append_note('bookings',$bookingId,'[Produktreife Statuskette] Verknüpftes Angebot ist archiviert/storniert. Buchung zur Prüfung markiert. '.$reason);
                if (pr109_column_exists('bookings','status')) {
                    $stmt=$pdo->prepare("UPDATE bookings SET status='clarification_required' WHERE id=? AND " . pr109_active_booking_condition('bookings'));
                    $stmt->execute([$bookingId]); $affected += $stmt->rowCount();
                }
                $after = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1',[$bookingId])[0] ?? [];
                pr109_audit('booking',$bookingId,'pms_lifecycle_offer_terminal_mark_booking',$before,$after,$reason);
                break;
            case 'open_schedule_for_terminal_offer':
            case 'open_schedule_for_terminal_booking':
                if (!pr109_table_exists('booking_payment_schedule') || !pr109_column_exists('booking_payment_schedule','status')) return;
                $before = pr109_fetch_all('SELECT * FROM booking_payment_schedule WHERE id=? LIMIT 1',[$id])[0] ?? [];
                if (!$before) return;
                $stmt=$pdo->prepare("UPDATE booking_payment_schedule SET status='waived' WHERE id=? AND LOWER(COALESCE(status,'')) NOT IN ('paid','received','waived','cancelled','canceled','void','aufgehoben','storniert')");
                $stmt->execute([$id]); $affected += $stmt->rowCount();
                $after = pr109_fetch_all('SELECT * FROM booking_payment_schedule WHERE id=? LIMIT 1',[$id])[0] ?? [];
                pr109_audit('payment_schedule',$id,'pms_lifecycle_waive_terminal',$before,$after,$reason);
                break;
            case 'active_customer_access_for_terminal_booking':
                if (!pr109_table_exists('booking_customer_access')) return;
                $before = pr109_fetch_all('SELECT * FROM booking_customer_access WHERE id=? LIMIT 1',[$id])[0] ?? [];
                if (!$before) return;
                $sets=[];
                if (pr109_column_exists('booking_customer_access','active')) $sets[]='active=0';
                if (pr109_column_exists('booking_customer_access','disabled_at')) $sets[]='disabled_at=COALESCE(disabled_at,NOW())';
                if (!$sets) return;
                $stmt=$pdo->prepare('UPDATE booking_customer_access SET '.implode(',',$sets).' WHERE id=?');
                $stmt->execute([$id]); $affected += $stmt->rowCount();
                $after = pr109_fetch_all('SELECT * FROM booking_customer_access WHERE id=? LIMIT 1',[$id])[0] ?? [];
                pr109_audit('booking_customer_access',$id,'pms_lifecycle_disable_access',$before,$after,$reason);
                break;
            case 'open_checkin_for_terminal_booking':
                if (!pr109_table_exists('booking_checkins') || !pr109_column_exists('booking_checkins','status')) return;
                $before = pr109_fetch_all('SELECT * FROM booking_checkins WHERE id=? LIMIT 1',[$id])[0] ?? [];
                if (!$before) return;
                $stmt=$pdo->prepare("UPDATE booking_checkins SET status='cancelled' WHERE id=? AND LOWER(COALESCE(status,'')) NOT IN ('cancelled','canceled','closed','completed','storniert','deleted')");
                $stmt->execute([$id]); $affected += $stmt->rowCount();
                $after = pr109_fetch_all('SELECT * FROM booking_checkins WHERE id=? LIMIT 1',[$id])[0] ?? [];
                pr109_audit('booking_checkin',$id,'pms_lifecycle_close_terminal',$before,$after,$reason);
                break;
            case 'active_housekeeping_for_terminal_booking':
                if (!pr109_table_exists('housekeeping_tasks') || !pr109_column_exists('housekeeping_tasks','status')) return;
                $before = pr109_fetch_all('SELECT * FROM housekeeping_tasks WHERE id=? LIMIT 1',[$id])[0] ?? [];
                if (!$before) return;
                $stmt=$pdo->prepare("UPDATE housekeeping_tasks SET status='cancelled'" . (pr109_column_exists('housekeeping_tasks','updated_at') ? ", updated_at=NOW()" : "") . " WHERE id=? AND LOWER(COALESCE(status,'')) NOT IN ('done','completed','released','cancelled','canceled','storniert')");
                $stmt->execute([$id]); $affected += $stmt->rowCount();
                $affected += pr115_append_task_note($id,'[Produktreife Statuskette] Aufgabe wegen beendeter Buchung storniert. '.$reason);
                $after = pr109_fetch_all('SELECT * FROM housekeeping_tasks WHERE id=? LIMIT 1',[$id])[0] ?? [];
                pr109_audit('housekeeping_task',$id,'pms_lifecycle_cancel_terminal',$before,$after,$reason);
                break;
            case 'active_document_for_terminal_booking':
                if (!pr109_table_exists('booking_documents')) return;
                $before = pr109_fetch_all('SELECT * FROM booking_documents WHERE id=? LIMIT 1',[$id])[0] ?? [];
                if (!$before) return;
                $sets=[];
                if (pr109_column_exists('booking_documents','deleted_at')) $sets[]='deleted_at=COALESCE(deleted_at,NOW())';
                if (pr109_column_exists('booking_documents','status')) $sets[]="status='deleted'";
                if (!$sets) return;
                $stmt=$pdo->prepare('UPDATE booking_documents SET '.implode(',',$sets).' WHERE id=?');
                $stmt->execute([$id]); $affected += $stmt->rowCount();
                $after = pr109_fetch_all('SELECT * FROM booking_documents WHERE id=? LIMIT 1',[$id])[0] ?? [];
                pr109_audit('booking_document',$id,'pms_lifecycle_hide_terminal',$before,$after,$reason);
                break;
        }
    };

    if ($bulk) {
        $review = pr117_build_lifecycle_review();
        foreach (($review['groups'] ?? []) as $group) {
            if ((string)($group['key'] ?? '') !== $issue) continue;
            foreach (($group['items'] ?? []) as $item) $apply($issue, (int)($item['id'] ?? 0));
        }
    } else {
        if ($id <= 0) throw new ValidationException('Eintrag fehlt.');
        $apply($issue, $id);
    }
    json_response(['ok'=>true,'message'=>$affected.' Änderung(en) im Status-/Archiv-Assistenten ausgeführt.','affected'=>$affected]);
}

/**
 * V2.3.6.119 – Buchungsquellen-Zuordnung & Abrechnungsart-Assistent.
 * Bestehende Buchungen werden nicht gelöscht, sondern kontrolliert klassifiziert.
 */
function pr119_mode_label(?string $mode): string
{
    $mode = class_exists('BookingAccountingService') ? BookingAccountingService::normalizeMode((string)$mode) : (string)$mode;
    return match($mode) {
        'external' => 'Extern abgerechnet',
        'none' => 'Keine Abrechnung',
        'portal_later' => 'Portalabrechnung später',
        default => 'Intern abrechnen',
    };
}

function pr119_source_guess(?string $source): string
{
    return class_exists('BookingAccountingService') ? BookingAccountingService::modeFromSource((string)$source) : 'internal';
}

function pr119_booking_cols(): string
{
    $cols = ["b.id", "COALESCE(b.reference, CONCAT('Buchung #', b.id)) number", "COALESCE(b.source,'') source", "COALESCE(b.accounting_mode,'internal') accounting_mode", "COALESCE(b.billing_excluded_reason,'') billing_excluded_reason", "b.arrival", "b.departure", "COALESCE(b.status,'') status", "COALESCE(b.total_price,0) total_price"];
    if (pr109_table_exists('guests')) $cols[] = "TRIM(CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,''))) guest";
    else $cols[] = "'' guest";
    if (pr109_table_exists('booking_channels')) { $cols[] = "COALESCE(c.name,'') channel"; $cols[] = "COALESCE(c.default_accounting_mode,'') channel_mode"; }
    else { $cols[] = "'' channel"; $cols[] = "'' channel_mode"; }
    return implode(',', $cols);
}

function pr119_booking_join(): string
{
    $join = '';
    if (pr109_table_exists('guests')) $join .= ' LEFT JOIN guests g ON g.id=b.guest_id';
    if (pr109_table_exists('booking_channels') && pr109_column_exists('bookings','booking_channel_id')) $join .= ' LEFT JOIN booking_channels c ON c.id=b.booking_channel_id';
    return $join;
}

function pr119_group(string $key, string $title, string $description, string $actionLabel, array $items, string $recommendedMode): array
{
    return [
        'key'=>$key,
        'title'=>$title,
        'description'=>$description,
        'action_label'=>$actionLabel,
        'recommended_mode'=>$recommendedMode,
        'recommended_mode_label'=>pr119_mode_label($recommendedMode),
        'count'=>count($items),
        'items'=>$items,
    ];
}

function pr119_build_review(): array
{
    $groups=[];
    if (!pr109_table_exists('bookings') || !pr109_column_exists('bookings','accounting_mode')) {
        return ['ok'=>true,'groups'=>[],'total'=>0,'message'=>'Buchungsquellen-/Abrechnungsart-Spalten sind noch nicht vorhanden.'];
    }
    $active = pr109_active_booking_condition('b');
    $cols = pr119_booking_cols();
    $join = pr119_booking_join();

    $sourceExpr = "LOWER(COALESCE(b.source,''))";
    $portalRegex = "booking|airbnb|expedia|vrbo|hotel.?spider|csv|xml|ical|import|portal|channel|avantio|smoobu|beds24|lodgify|hostaway";
    $noneRegex = "personal|mitarbeiter|eigent|owner|sperr|block|wartung|technik|familie|privat";

    $items = pr109_fetch_all("SELECT $cols FROM bookings b $join WHERE $active AND ($sourceExpr REGEXP ? OR COALESCE(b.external_provider,'')<>'' OR COALESCE(b.external_id,'')<>'') AND COALESCE(b.accounting_mode,'internal')='internal' ORDER BY b.arrival DESC,b.id DESC LIMIT ".pr109_limit(), [$portalRegex]);
    $groups[] = pr119_group('portal_source_marked_internal','Portal-/Importbuchungen noch intern abrechnungsrelevant','Diese Buchungen sehen nach Booking.com/Airbnb/CSV/XML/Portal aus, sind aber noch intern abrechnungsrelevant. Dadurch entstehen falsche offene Zahlungen oder Umsätze.','Als extern abgerechnet markieren',$items,'external');

    $items = pr109_fetch_all("SELECT $cols FROM bookings b $join WHERE $active AND $sourceExpr REGEXP ? AND COALESCE(b.accounting_mode,'internal')<>'none' ORDER BY b.arrival DESC,b.id DESC LIMIT ".pr109_limit(), [$noneRegex]);
    $groups[] = pr119_group('personal_source_not_none','Personal/Sperrung/Eigentümer nicht als reine Belegung markiert','Diese Einträge sollen den Kalender blockieren, aber keine interne Abrechnung erzeugen.','Als keine Abrechnung markieren',$items,'none');

    if (pr109_table_exists('booking_payment_schedule')) {
        $items = pr109_fetch_all("SELECT $cols, COUNT(s.id) schedule_count FROM bookings b $join JOIN booking_payment_schedule s ON s.booking_id=b.id WHERE $active AND COALESCE(b.accounting_mode,'internal') IN ('external','none','portal_later') AND LOWER(COALESCE(s.status,'')) IN ('open','partial','overdue') GROUP BY b.id ORDER BY b.arrival DESC,b.id DESC LIMIT ".pr109_limit());
        $groups[] = pr119_group('non_internal_with_open_schedule','Nicht intern abrechnungsrelevante Buchungen mit offenen Zahlungszielen','Diese Buchungen sind extern/keine Abrechnung, haben aber noch offene interne Zahlungsziele.','Interne Zahlungsziele aufheben',$items,'keep');

        $items = pr109_fetch_all("SELECT $cols FROM bookings b $join LEFT JOIN booking_payment_schedule s ON s.booking_id=b.id WHERE $active AND COALESCE(b.accounting_mode,'internal')='internal' AND COALESCE(b.total_price,0)>0 GROUP BY b.id HAVING COUNT(s.id)=0 ORDER BY b.arrival DESC,b.id DESC LIMIT ".pr109_limit());
        $groups[] = pr119_group('internal_without_payment_schedule','Interne Buchungen ohne Zahlungsplan','Diese Buchungen sollen intern abgerechnet werden, besitzen aber noch keinen Zahlungsplan.','Zur Zahlungsplanprüfung markieren',$items,'internal');
    }

    $items = pr109_fetch_all("SELECT $cols FROM bookings b $join WHERE $active AND (COALESCE(b.source,'')='' OR COALESCE(b.accounting_mode,'')='') ORDER BY b.arrival DESC,b.id DESC LIMIT ".pr109_limit());
    $groups[] = pr119_group('missing_source_or_mode','Buchungen ohne klare Quelle/Abrechnungsart','Diese Buchungen sollten vor Produktivbetrieb eindeutig klassifiziert werden.','Quelle/Abrechnungsart nach Quelle setzen',$items,'auto');

    $groups = array_values(array_filter($groups, static fn(array $g): bool => (int)$g['count'] > 0));
    return [
        'ok'=>true,
        'groups'=>$groups,
        'total'=>array_sum(array_map(static fn($g)=>(int)$g['count'],$groups)),
        'modes'=>[
            ['value'=>'internal','label'=>pr119_mode_label('internal')],
            ['value'=>'external','label'=>pr119_mode_label('external')],
            ['value'=>'none','label'=>pr119_mode_label('none')],
            ['value'=>'portal_later','label'=>pr119_mode_label('portal_later')],
        ]
    ];
}

function booking_accounting_review_v236119(): never
{
    Auth::requireLogin();
    json_response(pr119_build_review());
}

function pr119_audit_booking(int $id, string $action, array $before, array $after, string $note): void
{
    if (function_exists('log_booking_change')) {
        try { log_booking_change($id, $action, $before, $after, $note); } catch (Throwable) {}
    }
    if (class_exists('AuditLogger')) {
        try { AuditLogger::record('booking', $id, $action, $before, $after, $note); } catch (Throwable) {}
    }
}

function booking_accounting_action_v236119(): never
{
    $user = Auth::requireLogin();
    if (!in_array((string)($user['role'] ?? ''), ['admin','manager'], true)) throw new ForbiddenException('Der Buchungsquellen-Assistent ist nur für Admin/Manager freigegeben.');
    $d = request_data();
    $issue = (string)($d['issue_key'] ?? '');
    $id = (int)($d['id'] ?? 0);
    $bulk = !empty($d['bulk']);
    $targetMode = (string)($d['target_mode'] ?? '');
    $reason = trim((string)($d['reason'] ?? 'Buchungsquellen-/Abrechnungsart-Assistent')) ?: 'Buchungsquellen-/Abrechnungsart-Assistent';
    $review = pr119_build_review();
    $ids=[];
    if ($bulk) {
        foreach (($review['groups'] ?? []) as $g) if (($g['key'] ?? '') === $issue) foreach (($g['items'] ?? []) as $it) $ids[]=(int)$it['id'];
    } elseif ($id>0) $ids[]=$id;
    $ids = array_values(array_unique(array_filter($ids)));
    $affected=0;
    foreach ($ids as $bookingId) {
        $before = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$bookingId])[0] ?? [];
        if (!$before) continue;
        $mode = $targetMode !== '' ? $targetMode : match($issue) {
            'portal_source_marked_internal' => 'external',
            'personal_source_not_none' => 'none',
            'missing_source_or_mode' => pr119_source_guess((string)($before['source'] ?? '')),
            default => (string)($before['accounting_mode'] ?? 'internal'),
        };
        if ($issue === 'non_internal_with_open_schedule') {
            if (class_exists('BookingAccountingService')) BookingAccountingService::neutralizeInternalBilling(db(), $bookingId, (string)($before['accounting_mode'] ?? 'external'), $reason);
            else db()->prepare("UPDATE booking_payment_schedule SET status='waived' WHERE booking_id=? AND status IN ('open','partial','overdue')")->execute([$bookingId]);
        } elseif ($issue === 'internal_without_payment_schedule') {
            // Keine automatische Betragserzeugung: nur markieren, damit Abrechnung/Workflow sauber entscheidet.
            if (pr109_column_exists('bookings','internal_notes')) {
                db()->prepare("UPDATE bookings SET internal_notes=CONCAT(COALESCE(internal_notes,''), CASE WHEN COALESCE(internal_notes,'')='' THEN '' ELSE '\n' END, ?) WHERE id=?")->execute([$reason . ': Zahlungsplan prüfen/anlegen.', $bookingId]);
            }
        } else {
            if (class_exists('BookingAccountingService')) BookingAccountingService::applyModeToBooking(db(), $bookingId, $mode, $reason);
            else db()->prepare('UPDATE bookings SET accounting_mode=?, billing_excluded_reason=? WHERE id=?')->execute([$mode, $mode==='internal'?null:$reason, $bookingId]);
        }
        $after = pr109_fetch_all('SELECT * FROM bookings WHERE id=? LIMIT 1', [$bookingId])[0] ?? [];
        pr119_audit_booking($bookingId, 'booking_accounting_mode_v236119', $before, $after, $reason);
        $affected++;
    }
    json_response(['ok'=>true,'message'=>$affected.' Buchungen verarbeitet.','affected'=>$affected,'review'=>pr119_build_review()]);
}

/**
 * V2.3.6.122 – Produktreife: Datenbereinigung und Backup-Abschluss.
 * Sichere Behandlung verwaister Zahlungs-/Check-in-Bezüge ohne harte Löschung.
 */
function pms_orphan_cleanup_review_v236122(): never
{
    Auth::requireLogin();
    json_response(pr122_build_orphan_review());
}

function pr122_build_orphan_review(): array
{
    $groups = [];
    $inactive = pr109_table_exists('bookings') ? pr109_inactive_booking_condition('b') : '0=1';

    if (pr109_table_exists('booking_payment_schedule') && pr109_table_exists('bookings') && pr109_column_exists('booking_payment_schedule','booking_id')) {
        $items = pr109_fetch_all("SELECT s.id, s.booking_id, COALESCE(b.reference, CONCAT('Buchung #', s.booking_id)) number,
            s.due_date arrival, NULL departure, COALESCE(s.status,'') status, COALESCE(s.amount,0) amount
            FROM booking_payment_schedule s LEFT JOIN bookings b ON b.id=s.booking_id
            WHERE s.booking_id IS NOT NULL AND (b.id IS NULL OR $inactive)
              AND LOWER(COALESCE(s.status,'')) IN ('open','partial','overdue','offen','teiloffen')
            ORDER BY s.id DESC LIMIT " . pr109_limit());
        $groups[] = pr109_group('orphan_open_payment_schedule', 'Offene Zahlungsziele ohne aktive Buchung', 'Diese Zahlungsziele dürfen nicht als offene Forderung weiterlaufen. Sie werden nicht gelöscht, sondern aufgehoben.', 'Zahlungsziele aufheben', $items, 'waive');
    }

    if (pr109_table_exists('booking_payments') && pr109_table_exists('bookings') && pr109_column_exists('booking_payments','booking_id')) {
        $items = pr109_fetch_all("SELECT p.id, p.booking_id, COALESCE(b.reference, CONCAT('Buchung #', p.booking_id)) number,
            COALESCE(p.payment_date, p.created_at) arrival, NULL departure, COALESCE(p.status,'') status, COALESCE(p.amount,0) amount
            FROM booking_payments p LEFT JOIN bookings b ON b.id=p.booking_id
            WHERE p.booking_id IS NOT NULL AND (b.id IS NULL OR $inactive)
            ORDER BY p.id DESC LIMIT " . pr109_limit());
        $groups[] = pr109_group('orphan_payment', 'Zahlungen ohne aktive Buchung', 'Diese Zahlungen bleiben erhalten, werden aber für die Abrechnungsprüfung markiert und aus aktuellen Forderungslisten herausgehalten.', 'Zahlungen markieren', $items, 'review_payment');
    }

    if (pr109_table_exists('booking_checkins') && pr109_table_exists('bookings') && pr109_column_exists('booking_checkins','booking_id')) {
        $checkinKey = pr109_column_exists('booking_checkins','id') ? 'c.id' : 'c.booking_id';
        $items = pr109_fetch_all("SELECT $checkinKey id, c.booking_id, COALESCE(b.reference, CONCAT('Buchung #', c.booking_id)) number,
            b.arrival, b.departure, COALESCE(c.status,'') status
            FROM booking_checkins c LEFT JOIN bookings b ON b.id=c.booking_id
            WHERE c.booking_id IS NOT NULL AND (b.id IS NULL OR $inactive)
              AND LOWER(COALESCE(c.status,'')) NOT IN ('cancelled','canceled','closed','completed','storniert','deleted')
            ORDER BY $checkinKey DESC LIMIT " . pr109_limit());
        $groups[] = pr109_group('orphan_checkin', 'Offene Check-ins ohne aktive Buchung', 'Diese Check-ins hängen an fehlenden/gelöschten Buchungen und sollen geschlossen werden.', 'Check-ins schließen', $items, 'close_checkin');
    }

    $groups = array_values(array_filter($groups, static fn(array $g): bool => (int)$g['count'] > 0));
    return [
        'ok' => true,
        'groups' => $groups,
        'total' => array_sum(array_map(static fn($g) => (int)$g['count'], $groups)),
        'message' => $groups ? 'Verwaiste Abrechnungs-/Check-in-Bezüge gefunden.' : 'Keine verwaisten Zahlungs-/Check-in-Bezüge gefunden.'
    ];
}

function pms_orphan_cleanup_action_v236122(): never
{
    $user = Auth::requireLogin();
    if (!in_array((string)($user['role'] ?? ''), ['admin','manager'], true)) {
        throw new ForbiddenException('Dieser Bereinigungsassistent ist nur für Admin/Manager freigegeben.');
    }
    $d = request_data();
    $issue = (string)($d['issue_key'] ?? '');
    $id = (int)($d['id'] ?? 0);
    $bulk = !empty($d['bulk']);
    $reason = trim((string)($d['reason'] ?? 'Produktreife Datenbereinigung V2.3.6.122')) ?: 'Produktreife Datenbereinigung V2.3.6.122';
    $review = pr122_build_orphan_review();
    $ids = [];
    if ($bulk) {
        foreach (($review['groups'] ?? []) as $g) {
            if (($g['key'] ?? '') === $issue) {
                foreach (($g['items'] ?? []) as $it) $ids[] = (int)$it['id'];
            }
        }
    } elseif ($id > 0) {
        $ids[] = $id;
    }
    $ids = array_values(array_unique(array_filter($ids)));
    $affected = 0;
    foreach ($ids as $targetId) {
        switch ($issue) {
            case 'orphan_open_payment_schedule':
                if (!pr109_table_exists('booking_payment_schedule') || !pr109_column_exists('booking_payment_schedule','status')) break;
                $before = pr109_fetch_all('SELECT * FROM booking_payment_schedule WHERE id=? LIMIT 1', [$targetId])[0] ?? [];
                if (!$before) break;
                $sql = "UPDATE booking_payment_schedule SET status='waived'";
                $params = [];
                if (pr109_column_exists('booking_payment_schedule','waived_reason')) {
                    $sql .= ", waived_reason=CONCAT(COALESCE(waived_reason,''), CASE WHEN COALESCE(waived_reason,'')='' THEN '' ELSE '\n' END, ?)";
                    $params[] = $reason;
                }
                $sql .= " WHERE id=? AND LOWER(COALESCE(status,'')) IN ('open','partial','overdue','offen','teiloffen')";
                $params[] = $targetId;
                $stmt = db()->prepare($sql); $stmt->execute($params); $affected += $stmt->rowCount();
                $after = pr109_fetch_all('SELECT * FROM booking_payment_schedule WHERE id=? LIMIT 1', [$targetId])[0] ?? [];
                pr109_audit('payment_schedule', $targetId, 'pms122_orphan_schedule_waived', $before, $after, $reason);
                break;

            case 'orphan_payment':
                if (!pr109_table_exists('booking_payments')) break;
                $before = pr109_fetch_all('SELECT * FROM booking_payments WHERE id=? LIMIT 1', [$targetId])[0] ?? [];
                if (!$before) break;
                $sets = [];
                $params = [];
                if (pr109_column_exists('booking_payments','status')) $sets[] = "status=CASE WHEN LOWER(COALESCE(status,'')) IN ('','open','active') THEN 'review' ELSE status END";
                foreach (['note','notes','internal_notes'] as $col) {
                    if (pr109_column_exists('booking_payments', $col)) {
                        $sets[] = "$col=CONCAT(COALESCE($col,''), CASE WHEN COALESCE($col,'')='' THEN '' ELSE '\n' END, ?)";
                        $params[] = $reason;
                        break;
                    }
                }
                if ($sets) {
                    $sql = 'UPDATE booking_payments SET ' . implode(',', $sets) . ' WHERE id=?';
                    $params[] = $targetId;
                    $stmt = db()->prepare($sql); $stmt->execute($params); $affected += $stmt->rowCount();
                }
                $after = pr109_fetch_all('SELECT * FROM booking_payments WHERE id=? LIMIT 1', [$targetId])[0] ?? [];
                pr109_audit('booking_payment', $targetId, 'pms122_orphan_payment_review', $before, $after, $reason);
                break;

            case 'orphan_checkin':
                if (!pr109_table_exists('booking_checkins') || !pr109_column_exists('booking_checkins','status')) break;
                $keyCol = pr109_column_exists('booking_checkins','id') ? 'id' : 'booking_id';
                $before = pr109_fetch_all("SELECT * FROM booking_checkins WHERE $keyCol=? LIMIT 1", [$targetId])[0] ?? [];
                if (!$before) break;
                $stmt = db()->prepare("UPDATE booking_checkins SET status='cancelled' WHERE $keyCol=? AND LOWER(COALESCE(status,'')) NOT IN ('cancelled','canceled','closed','completed','storniert','deleted')");
                $stmt->execute([$targetId]); $affected += $stmt->rowCount();
                $after = pr109_fetch_all("SELECT * FROM booking_checkins WHERE $keyCol=? LIMIT 1", [$targetId])[0] ?? [];
                pr109_audit('booking_checkin', $targetId, 'pms122_orphan_checkin_closed', $before, $after, $reason);
                break;
        }
    }
    json_response(['ok'=>true,'message'=>$affected . ' Einträge verarbeitet.','affected'=>$affected,'review'=>pr122_build_orphan_review()]);
}


/**
 * V2.3.6.123 – sichere Löschung verwaister Abrechnungs-/Check-in-Daten.
 * Nur Einträge ohne aktive Buchung können nach Bestätigung endgültig entfernt werden.
 */
function pms_orphan_cleanup_delete_v236123(): never
{
    $user = Auth::requireLogin();
    if (!in_array((string)($user['role'] ?? ''), ['admin','manager'], true)) {
        throw new ForbiddenException('Endgültiges Entfernen ist nur für Admin/Manager freigegeben.');
    }
    $d = request_data();
    $issue = (string)($d['issue_key'] ?? '');
    $id = (int)($d['id'] ?? 0);
    $bulk = !empty($d['bulk']);
    $confirm = trim((string)($d['confirm'] ?? ''));
    $reason = trim((string)($d['reason'] ?? 'Produktreife Löschung verwaister Daten V2.3.6.123')) ?: 'Produktreife Löschung verwaister Daten V2.3.6.123';
    if ($confirm !== 'ENDGUELTIG') {
        throw new RuntimeException('Bitte endgültige Löschung ausdrücklich bestätigen.');
    }
    $allowed = ['orphan_open_payment_schedule','orphan_payment','orphan_checkin'];
    if (!in_array($issue, $allowed, true)) {
        throw new RuntimeException('Diese Prüfgruppe darf nicht endgültig gelöscht werden.');
    }
    $review = pr122_build_orphan_review();
    $ids = [];
    if ($bulk) {
        foreach (($review['groups'] ?? []) as $g) {
            if (($g['key'] ?? '') === $issue) {
                foreach (($g['items'] ?? []) as $it) $ids[] = (int)$it['id'];
            }
        }
    } elseif ($id > 0) {
        $ids[] = $id;
    }
    $ids = array_values(array_unique(array_filter($ids)));
    $affected = 0;
    foreach ($ids as $targetId) {
        switch ($issue) {
            case 'orphan_open_payment_schedule':
                if (!pr109_table_exists('booking_payment_schedule')) break;
                $before = pr109_fetch_all('SELECT * FROM booking_payment_schedule WHERE id=? LIMIT 1', [$targetId])[0] ?? [];
                if (!$before) break;
                // Sicherheitsprüfung: nur ohne aktive Buchung löschen.
                if (!pr123_is_orphan_booking_ref((int)($before['booking_id'] ?? 0))) break;
                pr109_audit('payment_schedule', $targetId, 'pms123_orphan_schedule_deleted', $before, [], $reason);
                $stmt = db()->prepare('DELETE FROM booking_payment_schedule WHERE id=?');
                $stmt->execute([$targetId]);
                $affected += $stmt->rowCount();
                break;

            case 'orphan_payment':
                if (!pr109_table_exists('booking_payments')) break;
                $before = pr109_fetch_all('SELECT * FROM booking_payments WHERE id=? LIMIT 1', [$targetId])[0] ?? [];
                if (!$before) break;
                if (!pr123_is_orphan_booking_ref((int)($before['booking_id'] ?? 0))) break;
                pr109_audit('booking_payment', $targetId, 'pms123_orphan_payment_deleted', $before, [], $reason);
                if (pr109_table_exists('booking_payment_allocations') && pr109_column_exists('booking_payment_allocations','payment_id')) {
                    db()->prepare('DELETE FROM booking_payment_allocations WHERE payment_id=?')->execute([$targetId]);
                }
                $stmt = db()->prepare('DELETE FROM booking_payments WHERE id=?');
                $stmt->execute([$targetId]);
                $affected += $stmt->rowCount();
                break;

            case 'orphan_checkin':
                if (!pr109_table_exists('booking_checkins')) break;
                $keyCol = pr109_column_exists('booking_checkins','id') ? 'id' : 'booking_id';
                $before = pr109_fetch_all("SELECT * FROM booking_checkins WHERE $keyCol=? LIMIT 1", [$targetId])[0] ?? [];
                if (!$before) break;
                if (!pr123_is_orphan_booking_ref((int)($before['booking_id'] ?? $targetId))) break;
                pr109_audit('booking_checkin', $targetId, 'pms123_orphan_checkin_deleted', $before, [], $reason);
                // Uploads zu diesem Check-in werden nicht als Datei gelöscht; nur der verwaiste Check-in-Datensatz.
                $stmt = db()->prepare("DELETE FROM booking_checkins WHERE $keyCol=?");
                $stmt->execute([$targetId]);
                $affected += $stmt->rowCount();
                break;
        }
    }
    json_response(['ok'=>true,'message'=>$affected . ' verwaiste Einträge endgültig entfernt.','affected'=>$affected,'review'=>pr122_build_orphan_review()]);
}

function pr123_is_orphan_booking_ref(int $bookingId): bool
{
    if ($bookingId <= 0 || !pr109_table_exists('bookings')) return true;
    $inactive = pr109_inactive_booking_condition('b');
    $row = pr109_fetch_all("SELECT b.id FROM bookings b WHERE b.id=? AND NOT ($inactive) LIMIT 1", [$bookingId]);
    return empty($row);
}
