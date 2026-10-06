<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
Auth::requireRole(['housekeeping_manager', 'admin', 'manager', 'reception']);

$from = (string)($_GET['from'] ?? date('Y-m-d'));
$to = (string)($_GET['to'] ?? date('Y-m-d', strtotime('+7 days')));
$lang = in_array((string)($_GET['lang'] ?? 'de'), ['de', 'es'], true) ? (string)$_GET['lang'] : 'de';
if (!valid_date($from) || !valid_date($to) || $from > $to) {
    http_response_code(400);
    exit($lang === 'es' ? 'Periodo no válido' : 'Ungültiger Zeitraum');
}

$stmt = db()->prepare("SELECT h.task_date,h.due_time,a.code apartment,h.task_type,h.status,
    ht.name team,hm.name member,h.priority,h.estimated_minutes,h.actual_minutes,h.notes,h.completion_notes
    FROM housekeeping_tasks h
    JOIN apartments a ON a.id=h.apartment_id
    LEFT JOIN housekeeping_teams ht ON ht.id=h.team_id
    LEFT JOIN housekeeping_members hm ON hm.id=h.member_id
    WHERE h.task_date BETWEEN ? AND ?
    ORDER BY h.task_date,h.due_time,a.name");
$stmt->execute([$from, $to]);

$headers = $lang === 'es'
    ? ['Fecha','Listo antes de','Apartamento','Tipo','Estado','Equipo','Empleado','Prioridad','Minutos previstos','Minutos reales','Nota','Nota final']
    : ['Datum','Fertig bis','Apartment','Art','Status','Team','Mitarbeiter','Priorität','Plan-Minuten','Ist-Minuten','Notiz','Abschlussnotiz'];

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="housekeeping-'.$from.'-'.$to.'-'.$lang.'.csv"');
echo "\xEF\xBB\xBF";
$output = fopen('php://output', 'wb');
fputcsv($output, $headers, ';');
foreach ($stmt as $row) {
    fputcsv($output, [
        $row['task_date'],
        $row['due_time'] ? substr((string)$row['due_time'], 0, 5) : '',
        $row['apartment'],
        HousekeepingWorkflow::taskTypeLabel((string)$row['task_type'], $lang),
        HousekeepingWorkflow::statusLabel((string)$row['status'], $lang),
        $row['team'],
        $row['member'],
        $row['priority'],
        $row['estimated_minutes'],
        $row['actual_minutes'],
        $row['notes'],
        $row['completion_notes'],
    ], ';');
}
fclose($output);
