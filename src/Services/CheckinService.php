<?php
declare(strict_types=1);

final class CheckinService
{
    private const REQUIRED_FIELDS = ['first_name','last_name','gender','document_number','document_type','nationality','date_of_birth','address','postal_code','city','country'];

    public static function bookingByToken(string $token): array
    {
        $booking = BookingWorkflowService::publicByToken($token);
        $bookingId = (int)$booking['booking_id'];
        $stmt = db()->prepare('SELECT vehicle_plate,special_requests,guest_request,planned_arrival_time,planned_departure_time FROM bookings WHERE id=? LIMIT 1');
        $stmt->execute([$bookingId]);
        $extra = $stmt->fetch() ?: [];
        return array_merge($booking, $extra, self::bundle($bookingId));
    }

    public static function bundle(int $bookingId): array
    {
        $checkin = self::checkinRow($bookingId);
        $travellers = self::travellersForBooking($bookingId);
        $uploads = self::uploadsForBooking($bookingId);
        $summary = self::summaryFromRows($travellers, $checkin);
        return ['checkin' => $checkin, 'travellers' => $travellers, 'checkin_uploads' => $uploads, 'checkin_summary' => $summary];
    }

    public static function checkinRow(int $bookingId): array
    {
        $stmt = db()->prepare('SELECT * FROM booking_checkins WHERE booking_id=? LIMIT 1');
        $stmt->execute([$bookingId]);
        return $stmt->fetch() ?: [
            'booking_id' => $bookingId,
            'status' => 'open',
            'planned_arrival_time' => null,
            'vehicle_plate' => null,
            'special_requests' => null,
            'consent_privacy' => 0,
            'consent_house_rules' => 0,
            'signature_name' => null,
            'submitted_at' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ];
    }

    public static function travellersForBooking(int $bookingId): array
    {
        $stmt = db()->prepare('SELECT * FROM booking_travellers WHERE booking_id=? ORDER BY is_primary DESC,id');
        $stmt->execute([$bookingId]);
        $rows = $stmt->fetchAll();
        if ($rows) return $rows;

        $stmt = db()->prepare("SELECT g.first_name,g.last_name,g.second_last_name,g.gender,g.passport_number document_number,
            g.document_support_number,g.document_type,g.document_issue_date,g.document_country,g.place_of_birth,g.province,
            g.nationality,g.date_of_birth,g.address,g.city,g.country,g.postal_code,g.fixed_phone,g.phone mobile_phone,g.email
            FROM bookings b JOIN guests g ON g.id=b.guest_id WHERE b.id=? LIMIT 1");
        $stmt->execute([$bookingId]);
        $g = $stmt->fetch();
        if (!$g) return [];
        $g['id'] = 0;
        $g['booking_id'] = $bookingId;
        $g['is_primary'] = 1;
        $g['relationship_to_primary'] = 'Hauptgast';
        $g['minor'] = 0;
        $g['signature_status'] = 'open';
        $g['notes'] = '';
        return [$g];
    }

    public static function uploadsForBooking(int $bookingId): array
    {
        $stmt = db()->prepare('SELECT * FROM booking_checkin_uploads WHERE booking_id=? ORDER BY id DESC');
        $stmt->execute([$bookingId]);
        return $stmt->fetchAll();
    }

    public static function summaryForBooking(int $bookingId): array
    {
        $checkin = self::checkinRow($bookingId);
        $summary = self::summaryFromRows(self::travellersForBooking($bookingId), $checkin);
        $summary['upload_count'] = count(self::uploadsForBooking($bookingId));
        $summary['planned_arrival_time'] = $checkin['planned_arrival_time'] ?? null;
        return $summary;
    }

    public static function summaryFromRows(array $travellers, array $checkin): array
    {
        $missing = 0;
        $travellerCount = 0;
        $children = 0;
        $missingDetails = [];
        foreach ($travellers as $idx => $row) {
            $first = trim((string)($row['first_name'] ?? ''));
            $last = trim((string)($row['last_name'] ?? ''));
            if ($first === '' && $last === '') continue;
            $travellerCount++;
            foreach (self::REQUIRED_FIELDS as $key) {
                if (trim((string)($row[$key] ?? '')) === '') {
                    $missing++;
                    $missingDetails[] = 'Person ' . $travellerCount . ': ' . self::fieldLabel($key);
                }
            }
            $minor = (int)($row['minor'] ?? 0);
            $dob = trim((string)($row['date_of_birth'] ?? ''));
            if ($dob !== '') {
                try {
                    if ((new DateTimeImmutable($dob))->diff(new DateTimeImmutable('today'))->y < 18) $minor = 1;
                } catch (Throwable) {}
            }
            if ($minor === 1) {
                $children++;
                if (trim((string)($row['relationship_to_primary'] ?? '')) === '') {
                    $missing++;
                    $missingDetails[] = 'Person ' . $travellerCount . ': Beziehung zum Hauptgast';
                }
            }
        }
        if ($travellerCount === 0) {
            $missing++;
            $missingDetails[] = 'Mindestens eine reisende Person';
        }
        if ((int)($checkin['consent_privacy'] ?? 0) !== 1) {
            $missing++;
            $missingDetails[] = 'Datenschutzzustimmung';
        }
        if ((int)($checkin['consent_house_rules'] ?? 0) !== 1) {
            $missing++;
            $missingDetails[] = 'Hausordnung / Bedingungen bestätigt';
        }
        $status = (string)($checkin['status'] ?? 'open');
        if ($status === '' || $status === 'open') $status = $travellerCount > 0 ? ($missing ? 'incomplete' : 'submitted') : 'open';
        if ($missing > 0 && in_array($status, ['submitted','complete'], true)) $status = 'incomplete';
        $limitedMissing = array_slice($missingDetails, 0, 40);
        return [
            'status' => $status,
            'missing_fields' => $missing,
            'missing_count' => $missing,
            'missing_details' => $limitedMissing,
            'missing' => $limitedMissing,
            'traveller_count' => $travellerCount,
            'minor_count' => $children,
            'complete' => $missing === 0 && in_array($status, ['submitted','reviewed','complete'], true),
        ];
    }

    public static function saveSubmission(int $bookingId, array $data, array $files = [], ?int $userId = null, bool $guestSubmitted = true): array
    {
        $old = self::bundle($bookingId);
        $travellers = $data['travellers'] ?? [];
        if (!is_array($travellers)) $travellers = [];
        $plannedArrival = self::normalizeTime($data['planned_arrival_time'] ?? null);
        $vehicle = self::clean($data['vehicle_plate'] ?? '', 80);
        $special = self::clean($data['special_requests'] ?? '', 10000);
        $signature = self::clean($data['signature_name'] ?? '', 190);
        $privacy = self::bool($data['consent_privacy'] ?? 0);
        $rules = self::bool($data['consent_house_rules'] ?? 0);
        $now = date('Y-m-d H:i:s');

        db()->beginTransaction();
        try {
            db()->prepare('DELETE FROM booking_travellers WHERE booking_id=?')->execute([$bookingId]);
            $insert = db()->prepare('INSERT INTO booking_travellers(booking_id,is_primary,first_name,last_name,second_last_name,gender,document_number,document_support_number,document_type,document_issue_date,document_country,place_of_birth,province,nationality,date_of_birth,address,city,country,postal_code,fixed_phone,mobile_phone,email,relationship_to_primary,minor,signature_status,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $primarySeen = false;
            $count = 0;
            foreach ($travellers as $row) {
                if (!is_array($row)) continue;
                $first = self::clean($row['first_name'] ?? '', 100);
                $last = self::clean($row['last_name'] ?? '', 120);
                if ($first === '' && $last === '') continue;
                if ($first === '' || $last === '') throw new RuntimeException('Bei jeder reisenden Person sind Vor- und Nachname erforderlich.');
                $isPrimary = self::bool($row['is_primary'] ?? 0);
                if ($isPrimary && $primarySeen) $isPrimary = 0;
                if ($isPrimary) $primarySeen = true;
                $dob = self::normalizeDate($row['date_of_birth'] ?? null);
                $minor = self::bool($row['minor'] ?? 0);
                if ($dob) {
                    try { if ((new DateTimeImmutable($dob))->diff(new DateTimeImmutable('today'))->y < 18) $minor = 1; } catch (Throwable) {}
                }
                $insert->execute([
                    $bookingId,
                    $isPrimary,
                    $first,
                    $last,
                    self::nullableClean($row['second_last_name'] ?? '', 120),
                    self::nullableClean($row['gender'] ?? '', 20),
                    self::nullableClean($row['document_number'] ?? '', 100),
                    self::nullableClean($row['document_support_number'] ?? '', 100),
                    self::nullableClean($row['document_type'] ?? '', 40),
                    self::normalizeDate($row['document_issue_date'] ?? null),
                    self::nullableClean($row['document_country'] ?? '', 100),
                    self::nullableClean($row['place_of_birth'] ?? '', 160),
                    self::nullableClean($row['province'] ?? '', 120),
                    self::nullableClean($row['nationality'] ?? '', 100),
                    $dob,
                    self::nullableClean($row['address'] ?? '', 190),
                    self::nullableClean($row['city'] ?? '', 120),
                    self::nullableClean($row['country'] ?? '', 100),
                    self::nullableClean($row['postal_code'] ?? '', 30),
                    self::nullableClean($row['fixed_phone'] ?? '', 80),
                    self::nullableClean($row['mobile_phone'] ?? '', 80),
                    self::nullableClean($row['email'] ?? '', 190),
                    self::nullableClean($row['relationship_to_primary'] ?? '', 100),
                    $minor,
                    'open',
                    self::clean($row['notes'] ?? '', 10000),
                ]);
                $count++;
            }
            if ($count > 0 && !$primarySeen) {
                db()->prepare('UPDATE booking_travellers SET is_primary=1 WHERE booking_id=? ORDER BY id LIMIT 1')->execute([$bookingId]);
            }

            $tempCheckin = [
                'status' => 'submitted',
                'planned_arrival_time' => $plannedArrival,
                'vehicle_plate' => $vehicle,
                'special_requests' => $special,
                'consent_privacy' => $privacy,
                'consent_house_rules' => $rules,
                'signature_name' => $signature,
            ];
            $summary = self::summaryFromRows(self::travellersForBooking($bookingId), $tempCheckin);
            $status = $summary['missing_fields'] > 0 ? 'incomplete' : 'submitted';
            db()->prepare('INSERT INTO booking_checkins(booking_id,status,planned_arrival_time,vehicle_plate,special_requests,consent_privacy,consent_house_rules,signature_name,submitted_at,updated_by) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),planned_arrival_time=VALUES(planned_arrival_time),vehicle_plate=VALUES(vehicle_plate),special_requests=VALUES(special_requests),consent_privacy=VALUES(consent_privacy),consent_house_rules=VALUES(consent_house_rules),signature_name=VALUES(signature_name),submitted_at=VALUES(submitted_at),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP')
                ->execute([$bookingId,$status,$plannedArrival,$vehicle,$special,$privacy,$rules,$signature,$now,$userId]);
            db()->prepare('UPDATE bookings SET planned_arrival_time=COALESCE(?,planned_arrival_time),vehicle_plate=?,special_requests=?,police_status=? WHERE id=?')
                ->execute([$plannedArrival,$vehicle,$special,$summary['missing_fields'] > 0 ? 'open' : 'ready',$bookingId]);

            if (!empty($files['checkin_file'])) self::storeUpload($bookingId, $files['checkin_file'], $userId, $guestSubmitted, self::clean($data['upload_note'] ?? '', 500));

            self::logChange($bookingId, $guestSubmitted ? 'online_checkin_submitted' : 'checkin_saved', $old, self::bundle($bookingId), $count . ' Reisende gespeichert; ' . $summary['missing_fields'] . ' Pflichtangabe(n) fehlen', $userId);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        return self::bundle($bookingId);
    }

    public static function markReviewed(int $bookingId, string $note = '', ?int $userId = null): array
    {
        $old = self::bundle($bookingId);
        $summary = self::summaryForBooking($bookingId);
        if ($summary['missing_fields'] > 0) throw new RuntimeException('Der Check-in ist noch unvollständig und kann nicht als geprüft markiert werden.');
        db()->prepare("INSERT INTO booking_checkins(booking_id,status,reviewed_at,reviewed_by,review_note) VALUES(?,'reviewed',NOW(),?,?) ON DUPLICATE KEY UPDATE status='reviewed',reviewed_at=NOW(),reviewed_by=VALUES(reviewed_by),review_note=VALUES(review_note),updated_at=CURRENT_TIMESTAMP")
            ->execute([$bookingId,$userId,self::clean($note,1000)]);
        db()->prepare("UPDATE bookings SET police_status='ready' WHERE id=?")->execute([$bookingId]);
        self::logChange($bookingId, 'checkin_reviewed', $old, self::bundle($bookingId), 'Online-Check-in geprüft', $userId);
        return self::bundle($bookingId);
    }

    public static function storeUpload(int $bookingId, array $file, ?int $userId = null, bool $guestUpload = false, string $note = ''): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [];
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Die Datei konnte nicht hochgeladen werden.');
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 12 * 1024 * 1024) throw new RuntimeException('Die Check-in-Datei muss kleiner als 12 MB sein.');
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('Ungültiger Upload.');
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        $allowed = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
        ];
        if (!isset($allowed[$mime])) throw new RuntimeException('Erlaubt sind PDF, JPG, PNG, WEBP oder HEIC.');
        $dir = root_path('storage/checkin/' . $bookingId);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Der Check-in-Speicherordner konnte nicht angelegt werden.');
        $protect = root_path('storage/checkin/.htaccess');
        if (!is_file($protect)) @file_put_contents($protect, "Require all denied\nDeny from all\n");
        if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', (string)($file['name'] ?? 'formular')) ?: 'formular';
        $filename = 'checkin-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        $target = $dir . '/' . $filename;
        if (!move_uploaded_file($tmp, $target)) throw new RuntimeException('Die Datei konnte nicht gespeichert werden.');
        $relative = 'storage/checkin/' . $bookingId . '/' . $filename;
        db()->prepare('INSERT INTO booking_checkin_uploads(booking_id,file_path,original_name,mime_type,size_bytes,uploaded_by_user_id,uploaded_by_guest,note) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([$bookingId,$relative,$safeName,$mime,$size,$userId,$guestUpload ? 1 : 0,$note]);
        return self::uploadsForBooking($bookingId)[0] ?? [];
    }

    public static function deleteUpload(int $uploadId, ?int $userId = null): void
    {
        $stmt = db()->prepare('SELECT * FROM booking_checkin_uploads WHERE id=? LIMIT 1');
        $stmt->execute([$uploadId]);
        $row = $stmt->fetch();
        if (!$row) throw new RuntimeException('Datei nicht gefunden.');
        $path = root_path((string)$row['file_path']);
        db()->prepare('DELETE FROM booking_checkin_uploads WHERE id=?')->execute([$uploadId]);
        if (is_file($path)) @unlink($path);
        self::logChange((int)$row['booking_id'], 'checkin_upload_deleted', null, null, 'Check-in-Datei gelöscht', $userId);
    }

    public static function uploadById(int $uploadId): array
    {
        $stmt = db()->prepare('SELECT u.*, b.reference FROM booking_checkin_uploads u JOIN bookings b ON b.id=u.booking_id WHERE u.id=? LIMIT 1');
        $stmt->execute([$uploadId]);
        $row = $stmt->fetch();
        if (!$row) throw new NotFoundException('Datei nicht gefunden.');
        return $row;
    }

    public static function tokenAllowsUpload(string $token, int $uploadId): array
    {
        $booking = self::bookingByToken($token);
        $upload = self::uploadById($uploadId);
        if ((int)$upload['booking_id'] !== (int)$booking['booking_id']) throw new ForbiddenException('Diese Datei gehört nicht zu diesem Gastzugang.');
        return $upload;
    }

    public static function fieldLabel(string $key): string
    {
        return [
            'first_name' => 'Vorname', 'last_name' => 'Nachname', 'gender' => 'Geschlecht', 'document_number' => 'Dokumentnummer',
            'document_type' => 'Dokumentart', 'nationality' => 'Nationalität', 'date_of_birth' => 'Geburtsdatum', 'address' => 'Adresse',
            'postal_code' => 'PLZ', 'city' => 'Ort', 'country' => 'Land',
        ][$key] ?? $key;
    }

    private static function logChange(int $bookingId, string $action, mixed $old, mixed $new, string $note, ?int $userId): void
    {
        try {
            db()->prepare('INSERT INTO booking_change_log(booking_id,action,old_values_json,new_values_json,note,created_by) VALUES(?,?,?,?,?,?)')
                ->execute([$bookingId,$action,json_encode($old,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode($new,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$note,$userId]);
        } catch (Throwable $e) {
            AppLogger::error($e, ['booking_id'=>$bookingId,'action'=>$action], 'checkin-log');
        }
    }

    public static function clean(mixed $value, int $max = 255): string
    {
        $value = trim((string)$value);
        if (mb_strlen($value) > $max) $value = mb_substr($value, 0, $max);
        return $value;
    }

    private static function nullableClean(mixed $value, int $max): ?string
    {
        $value = self::clean($value, $max);
        return $value === '' ? null : $value;
    }

    private static function normalizeDate(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        if (!valid_date($value)) throw new RuntimeException('Ein eingegebenes Datum ist ungültig.');
        return $value;
    }

    private static function normalizeTime(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) throw new RuntimeException('Eine eingegebene Uhrzeit ist ungültig.');
        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    private static function bool(mixed $value): int
    {
        return in_array($value, [1, '1', true, 'true', 'on', 'yes', 'ja'], true) ? 1 : 0;
    }
}
