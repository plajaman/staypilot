<?php
declare(strict_types=1);

/**
 * StayPilot V2.2.6: Erweitertes Statistik-Center.
 * Keine neue Datenbankstruktur. Nutzt bestehende Buchungen, Gäste, Wohnungen,
 * Wohnungstypen, Häuser, Reisende und Zahlungsfelder.
 */

function stats_filter_value_v226(string $key): string
{
    return trim((string)($_GET[$key] ?? ''));
}

function stats_filters_v226(): array
{
    $from = stats_filter_value_v226('from');
    $to = stats_filter_value_v226('to');
    if ($from === '') $from = date('Y-m-01');
    if ($to === '') $to = date('Y-m-d');
    if (!valid_date($from) || !valid_date($to) || $from > $to) {
        throw new ValidationException('Der Statistikzeitraum ist ungültig.');
    }
    $end = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');
    $filters = [
        'from' => $from,
        'to' => $to,
        'end' => $end,
        'house_id' => max(0, (int)stats_filter_value_v226('house_id')),
        'apartment_type_id' => max(0, (int)stats_filter_value_v226('apartment_type_id')),
        'apartment_id' => max(0, (int)stats_filter_value_v226('apartment_id')),
        'status' => stats_filter_value_v226('status'),
        'payment_status' => stats_filter_value_v226('payment_status'),
        'country' => stats_filter_value_v226('country'),
        'source' => stats_filter_value_v226('source'),
        'include_cancelled' => in_array(stats_filter_value_v226('include_cancelled'), ['1','true','yes','on'], true),
    ];
    return $filters;
}

function stats_where_v226(array $f): array
{
    $where = ['b.arrival < ?', 'b.departure > ?'];
    $params = [$f['end'], $f['from']];

    if (!$f['include_cancelled'] && $f['status'] === '') {
        $where[] = "b.status NOT IN ('cancelled','rejected')";
    }
    if ($f['status'] !== '') {
        $where[] = 'b.status = ?';
        $params[] = $f['status'];
    }
    if ($f['payment_status'] !== '') {
        $where[] = 'b.payment_status = ?';
        $params[] = $f['payment_status'];
    }
    if ($f['house_id'] > 0) {
        $where[] = 'a.house_id = ?';
        $params[] = $f['house_id'];
    }
    if ($f['apartment_type_id'] > 0) {
        $where[] = 'COALESCE(b.apartment_type_id, a.apartment_type_id) = ?';
        $params[] = $f['apartment_type_id'];
    }
    if ($f['apartment_id'] > 0) {
        $where[] = 'b.apartment_id = ?';
        $params[] = $f['apartment_id'];
    }
    if ($f['country'] !== '') {
        $where[] = "COALESCE(NULLIF(g.country,''),'Unbekannt') = ?";
        $params[] = $f['country'];
    }
    if ($f['source'] !== '') {
        $where[] = 'b.source = ?';
        $params[] = $f['source'];
    }

    return ['sql' => implode(' AND ', $where), 'params' => $params];
}

function stats_filter_options_v226(): array
{
    return [
        'houses' => db()->query("SELECT id,code,name FROM houses ORDER BY active DESC, sort_order, name")->fetchAll(),
        'types' => db()->query("SELECT id,code,name FROM apartment_types ORDER BY active DESC, sort_order, name")->fetchAll(),
        'apartments' => db()->query("SELECT a.id,a.code,a.name,a.house_id,a.apartment_type_id,h.code house_code,t.code type_code FROM apartments a LEFT JOIN houses h ON h.id=a.house_id LEFT JOIN apartment_types t ON t.id=a.apartment_type_id ORDER BY COALESCE(h.sort_order,999),COALESCE(h.name,''),a.sort_order,a.name")->fetchAll(),
        'countries' => db()->query("SELECT country FROM (SELECT COALESCE(NULLIF(country,''),'Unbekannt') country FROM guests UNION SELECT COALESCE(NULLIF(country,''),'Unbekannt') country FROM booking_travellers) x GROUP BY country ORDER BY country")->fetchAll(),
        'sources' => db()->query("SELECT source FROM bookings GROUP BY source ORDER BY source")->fetchAll(),
        'statuses' => db()->query("SELECT status FROM bookings GROUP BY status ORDER BY status")->fetchAll(),
        'payment_statuses' => db()->query("SELECT payment_status FROM bookings GROUP BY payment_status ORDER BY payment_status")->fetchAll(),
    ];
}

function statistics_v226_data(): never
{
    $f = stats_filters_v226();
    $where = stats_where_v226($f);
    $whereSql = $where['sql'];
    $whereParams = $where['params'];
    $days = max(1, nights($f['from'], $f['end']));

    $activeApartments = (int)db()->query("SELECT COUNT(*) FROM apartments WHERE status='active'")->fetchColumn();

    $summarySql = "SELECT
        COUNT(DISTINCT b.id) bookings,
        COALESCE(SUM(b.adults),0) adults,
        COALESCE(SUM(b.children),0) children,
        COALESCE(SUM(b.babies),0) babies,
        COALESCE(SUM(b.adults + b.children + b.babies),0) persons,
        COALESCE(SUM(GREATEST(0,DATEDIFF(LEAST(b.departure,?),GREATEST(b.arrival,?)))),0) period_nights,
        COALESCE(SUM(DATEDIFF(b.departure,b.arrival)),0) stay_nights,
        COALESCE(AVG(DATEDIFF(b.departure,b.arrival)),0) avg_stay,
        COALESCE(SUM(b.total_price * GREATEST(0,DATEDIFF(LEAST(b.departure,?),GREATEST(b.arrival,?))) / GREATEST(1,DATEDIFF(b.departure,b.arrival))),0) revenue,
        COALESCE(SUM(b.paid_amount),0) paid,
        COALESCE(SUM(CASE WHEN b.status IN ('cancelled','rejected') THEN 1 ELSE 0 END),0) cancelled,
        COALESCE(SUM(CASE WHEN b.apartment_id IS NULL THEN 1 ELSE 0 END),0) unassigned
        FROM bookings b
        LEFT JOIN guests g ON g.id=b.guest_id
        LEFT JOIN apartments a ON a.id=b.apartment_id
        LEFT JOIN apartment_types t ON t.id=COALESCE(b.apartment_type_id,a.apartment_type_id)
        LEFT JOIN houses h ON h.id=a.house_id
        WHERE {$whereSql}";
    $stmt = db()->prepare($summarySql);
    $stmt->execute(array_merge([$f['end'], $f['from'], $f['end'], $f['from']], $whereParams));
    $summary = $stmt->fetch() ?: [];

    $travellerSql = "SELECT COUNT(*) total_travellers,
        COALESCE(SUM(CASE WHEN bt.minor=1 THEN 1 ELSE 0 END),0) minor_travellers
        FROM booking_travellers bt
        JOIN bookings b ON b.id=bt.booking_id
        LEFT JOIN guests g ON g.id=b.guest_id
        LEFT JOIN apartments a ON a.id=b.apartment_id
        LEFT JOIN apartment_types t ON t.id=COALESCE(b.apartment_type_id,a.apartment_type_id)
        LEFT JOIN houses h ON h.id=a.house_id
        WHERE {$whereSql}";
    $stmt = db()->prepare($travellerSql);
    $stmt->execute($whereParams);
    $travellerSummary = $stmt->fetch() ?: [];

    $periodNights = (int)($summary['period_nights'] ?? 0);
    $revenue = (float)($summary['revenue'] ?? 0);
    $capacity = max(1, $activeApartments * $days);
    $summaryOut = [
        'bookings' => (int)($summary['bookings'] ?? 0),
        'adults' => (int)($summary['adults'] ?? 0),
        'children' => (int)($summary['children'] ?? 0),
        'babies' => (int)($summary['babies'] ?? 0),
        'persons' => (int)($summary['persons'] ?? 0),
        'travellers' => (int)($travellerSummary['total_travellers'] ?? 0),
        'minor_travellers' => (int)($travellerSummary['minor_travellers'] ?? 0),
        'period_nights' => $periodNights,
        'stay_nights' => (int)($summary['stay_nights'] ?? 0),
        'avg_stay' => round((float)($summary['avg_stay'] ?? 0), 1),
        'revenue' => $revenue,
        'paid' => (float)($summary['paid'] ?? 0),
        'outstanding' => max(0, $revenue - (float)($summary['paid'] ?? 0)),
        'cancelled' => (int)($summary['cancelled'] ?? 0),
        'unassigned' => (int)($summary['unassigned'] ?? 0),
        'occupancy' => round($periodNights / $capacity * 100, 1),
        'adr' => $periodNights > 0 ? round($revenue / $periodNights, 2) : 0,
        'revpar' => round($revenue / $capacity, 2),
    ];

    $rowsSql = "SELECT b.id,b.reference,b.arrival,b.departure,b.status,b.payment_status,b.deposit_status,b.remaining_status,b.source,
        b.adults,b.children,b.babies,b.pets,b.total_price,b.paid_amount,b.deposit_amount,b.deposit_due_date,b.remaining_due_date,
        DATEDIFF(b.departure,b.arrival) stay_nights,
        GREATEST(0,DATEDIFF(LEAST(b.departure,?),GREATEST(b.arrival,?))) period_nights,
        CONCAT(g.first_name,' ',g.last_name) guest_name,g.email guest_email,g.phone guest_phone,g.country guest_country,g.nationality guest_nationality,g.language guest_language,
        g.vip,g.repeat_guest,g.notes guest_notes,
        a.code apartment_code,a.name apartment_name,t.code type_code,t.name type_name,h.code house_code,h.name house_name,
        (SELECT COUNT(*) FROM booking_travellers bt WHERE bt.booking_id=b.id) traveller_count,
        (SELECT COUNT(*) FROM booking_travellers bt WHERE bt.booking_id=b.id AND bt.minor=1) traveller_children
        FROM bookings b
        LEFT JOIN guests g ON g.id=b.guest_id
        LEFT JOIN apartments a ON a.id=b.apartment_id
        LEFT JOIN apartment_types t ON t.id=COALESCE(b.apartment_type_id,a.apartment_type_id)
        LEFT JOIN houses h ON h.id=a.house_id
        WHERE {$whereSql}
        ORDER BY b.arrival,b.departure,b.reference";
    $stmt = db()->prepare($rowsSql);
    $stmt->execute(array_merge([$f['end'], $f['from']], $whereParams));
    $rows = $stmt->fetchAll();

    // Wegen PHP-Closure-Sichtbarkeit die Gruppierungen bewusst einzeln ausführen.
    $groupQuery = static function (array $f, string $whereSql, array $whereParams, string $select, string $groupBy, string $orderBy = 'bookings DESC'): array {
        $sql = "SELECT {$select}, COUNT(DISTINCT b.id) bookings,
            COALESCE(SUM(b.adults+b.children+b.babies),0) persons,
            COALESCE(SUM(b.children+b.babies),0) children,
            COALESCE(SUM(GREATEST(0,DATEDIFF(LEAST(b.departure,?),GREATEST(b.arrival,?)))),0) nights,
            COALESCE(SUM(b.total_price * GREATEST(0,DATEDIFF(LEAST(b.departure,?),GREATEST(b.arrival,?))) / GREATEST(1,DATEDIFF(b.departure,b.arrival))),0) revenue
            FROM bookings b
            LEFT JOIN guests g ON g.id=b.guest_id
            LEFT JOIN apartments a ON a.id=b.apartment_id
            LEFT JOIN apartment_types t ON t.id=COALESCE(b.apartment_type_id,a.apartment_type_id)
            LEFT JOIN houses h ON h.id=a.house_id
            WHERE {$whereSql}
            GROUP BY {$groupBy}
            ORDER BY {$orderBy}";
        $stmt = db()->prepare($sql);
        $stmt->execute(array_merge([$f['end'], $f['from'], $f['end'], $f['from']], $whereParams));
        return $stmt->fetchAll();
    };

    $groups = [
        'countries' => $groupQuery($f, $whereSql, $whereParams, "COALESCE(NULLIF(g.country,''),'Unbekannt') label", "COALESCE(NULLIF(g.country,''),'Unbekannt')", 'bookings DESC,label'),
        'types' => $groupQuery($f, $whereSql, $whereParams, "COALESCE(t.name,'Nicht zugeordnet') label", "COALESCE(t.name,'Nicht zugeordnet')", 'revenue DESC,bookings DESC,label'),
        'houses' => $groupQuery($f, $whereSql, $whereParams, "COALESCE(h.name,'Nicht zugeordnet') label", "COALESCE(h.name,'Nicht zugeordnet')", 'revenue DESC,bookings DESC,label'),
        'sources' => $groupQuery($f, $whereSql, $whereParams, "COALESCE(NULLIF(b.source,''),'Unbekannt') label", "COALESCE(NULLIF(b.source,''),'Unbekannt')", 'bookings DESC,label'),
        'payment' => $groupQuery($f, $whereSql, $whereParams, "COALESCE(NULLIF(b.payment_status,''),'Unbekannt') label", "COALESCE(NULLIF(b.payment_status,''),'Unbekannt')", 'bookings DESC,label'),
        'status' => $groupQuery($f, $whereSql, $whereParams, "COALESCE(NULLIF(b.status,''),'Unbekannt') label", "COALESCE(NULLIF(b.status,''),'Unbekannt')", 'bookings DESC,label'),
    ];

    $travellerCountrySql = "SELECT COALESCE(NULLIF(bt.country,''),'Unbekannt') label, COUNT(*) travellers,
        COALESCE(SUM(CASE WHEN bt.minor=1 THEN 1 ELSE 0 END),0) children
        FROM booking_travellers bt
        JOIN bookings b ON b.id=bt.booking_id
        LEFT JOIN guests g ON g.id=b.guest_id
        LEFT JOIN apartments a ON a.id=b.apartment_id
        LEFT JOIN apartment_types t ON t.id=COALESCE(b.apartment_type_id,a.apartment_type_id)
        LEFT JOIN houses h ON h.id=a.house_id
        WHERE {$whereSql}
        GROUP BY COALESCE(NULLIF(bt.country,''),'Unbekannt')
        ORDER BY travellers DESC,label";
    $stmt = db()->prepare($travellerCountrySql);
    $stmt->execute($whereParams);
    $groups['traveller_countries'] = $stmt->fetchAll();


    // V2.3.6.80: zusätzliche Auswertungen ohne neue Tabellen.
    $monthSql = "SELECT DATE_FORMAT(b.arrival,'%Y-%m') label, COUNT(DISTINCT b.id) bookings,
        COALESCE(SUM(b.adults+b.children+b.babies),0) persons,
        COALESCE(SUM(GREATEST(0,DATEDIFF(LEAST(b.departure,?),GREATEST(b.arrival,?)))),0) nights,
        COALESCE(SUM(b.total_price * GREATEST(0,DATEDIFF(LEAST(b.departure,?),GREATEST(b.arrival,?))) / GREATEST(1,DATEDIFF(b.departure,b.arrival))),0) revenue
        FROM bookings b
        LEFT JOIN guests g ON g.id=b.guest_id
        LEFT JOIN apartments a ON a.id=b.apartment_id
        LEFT JOIN apartment_types t ON t.id=COALESCE(b.apartment_type_id,a.apartment_type_id)
        LEFT JOIN houses h ON h.id=a.house_id
        WHERE {$whereSql}
        GROUP BY DATE_FORMAT(b.arrival,'%Y-%m')
        ORDER BY label";
    $stmt = db()->prepare($monthSql);
    $stmt->execute(array_merge([$f['end'], $f['from'], $f['end'], $f['from']], $whereParams));
    $timeline = $stmt->fetchAll();

    $weekdayNames = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
    $arrivalWeekdays = [];
    foreach ($weekdayNames as $index => $name) $arrivalWeekdays[$index] = ['label'=>$name,'bookings'=>0,'persons'=>0,'revenue'=>0.0];
    $durationBuckets = [
        '1-2' => ['label'=>'1–2 Nächte','bookings'=>0,'persons'=>0,'revenue'=>0.0],
        '3-6' => ['label'=>'3–6 Nächte','bookings'=>0,'persons'=>0,'revenue'=>0.0],
        '7-13' => ['label'=>'7–13 Nächte','bookings'=>0,'persons'=>0,'revenue'=>0.0],
        '14-20' => ['label'=>'14–20 Nächte','bookings'=>0,'persons'=>0,'revenue'=>0.0],
        '21+' => ['label'=>'21+ Nächte','bookings'=>0,'persons'=>0,'revenue'=>0.0],
    ];
    $paymentAlerts = [];
    foreach ($rows as $row) {
        $persons = (int)($row['adults'] ?? 0) + (int)($row['children'] ?? 0) + (int)($row['babies'] ?? 0);
        $revenueRow = (float)($row['total_price'] ?? 0);
        $weekday = (int)(new DateTimeImmutable((string)$row['arrival']))->format('w');
        $arrivalWeekdays[$weekday]['bookings']++;
        $arrivalWeekdays[$weekday]['persons'] += $persons;
        $arrivalWeekdays[$weekday]['revenue'] += $revenueRow;
        $nights = max(0, (int)($row['stay_nights'] ?? 0));
        $bucket = $nights <= 2 ? '1-2' : ($nights <= 6 ? '3-6' : ($nights <= 13 ? '7-13' : ($nights <= 20 ? '14-20' : '21+')));
        $durationBuckets[$bucket]['bookings']++;
        $durationBuckets[$bucket]['persons'] += $persons;
        $durationBuckets[$bucket]['revenue'] += $revenueRow;
        $openAmount = max(0.0, (float)($row['total_price'] ?? 0) - (float)($row['paid_amount'] ?? 0));
        $depositDue = (string)($row['deposit_due_date'] ?? '');
        $remainingDue = (string)($row['remaining_due_date'] ?? '');
        $overdue = ($depositDue !== '' && $depositDue < date('Y-m-d') && !in_array((string)($row['deposit_status'] ?? ''), ['paid','waived'], true))
            || ($remainingDue !== '' && $remainingDue < date('Y-m-d') && !in_array((string)($row['remaining_status'] ?? ''), ['paid','waived'], true))
            || (string)($row['payment_status'] ?? '') === 'overdue';
        if ($openAmount > 0 || $overdue) {
            $paymentAlerts[] = [
                'id'=>(int)$row['id'],'reference'=>(string)$row['reference'],'guest_name'=>(string)($row['guest_name'] ?? ''),
                'arrival'=>(string)$row['arrival'],'departure'=>(string)$row['departure'],'open_amount'=>$openAmount,'overdue'=>$overdue,
                'payment_status'=>(string)($row['payment_status'] ?? ''),'deposit_due_date'=>$depositDue,'remaining_due_date'=>$remainingDue,
            ];
        }
    }
    usort($paymentAlerts, static fn(array $a,array $b): int => (($b['overdue'] <=> $a['overdue']) ?: ($b['open_amount'] <=> $a['open_amount'])));
    $analysis = [
        'timeline' => $timeline,
        'arrival_weekdays' => array_values($arrivalWeekdays),
        'duration_buckets' => array_values($durationBuckets),
        'payment_alerts' => array_slice($paymentAlerts, 0, 20),
        'quick_findings' => [
            'top_country' => $groups['countries'][0]['label'] ?? '–',
            'top_type' => $groups['types'][0]['label'] ?? '–',
            'top_source' => $groups['sources'][0]['label'] ?? '–',
            'payment_alert_count' => count($paymentAlerts),
        ],
    ];

    json_response([
        'ok' => true,
        'from' => $f['from'],
        'to' => $f['to'],
        'filters' => $f,
        'filter_options' => stats_filter_options_v226(),
        'summary' => $summaryOut,
        'groups' => $groups,
        'analysis' => $analysis,
        'rows' => $rows,
    ]);
}
