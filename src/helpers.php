<?php
declare(strict_types=1);

function root_path(string $path = ''): string
{
    return dirname(__DIR__) . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function config(): array
{
    static $config;
    return $config ??= require root_path('config/config.php');
}

function local_config(): array
{
    static $config;
    $file = root_path('config/local.php');
    if (!is_file($file)) {
        return [];
    }
    return $config ??= require $file;
}

function db(): PDO
{
    return Database::connect(local_config()['db'] ?? []);
}

function setting(string $key, mixed $default = null): mixed
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    if ($value === false) {
        return $cache[$key] = $default;
    }
    $decoded = json_decode((string)$value, true);
    return $cache[$key] = (json_last_error() === JSON_ERROR_NONE ? $decoded : $value);
}

function save_setting(string $key, mixed $value): void
{
    $payload = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt = db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $stmt->execute([$key, $payload]);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)$token)) {
        json_response(['ok' => false, 'message' => 'Sicherheitsprüfung fehlgeschlagen. Seite neu laden.'], 419);
    }
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '{}', true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function is_api_request(): bool
{
    return str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api.php') || str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/');
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function valid_date(string $value): bool
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $d && $d->format('Y-m-d') === $value;
}

function nights(string $arrival, string $departure): int
{
    return max(0, (int)(new DateTimeImmutable($arrival))->diff(new DateTimeImmutable($departure))->days);
}

function booking_conflict(?int $apartmentId, string $arrival, string $departure, ?int $ignoreId = null): bool
{
    if (!$apartmentId) {
        return false;
    }
    $sql = "SELECT COUNT(*) FROM bookings WHERE apartment_id=? AND status NOT IN ('cancelled','rejected') AND arrival < ? AND departure > ?";
    $params = [$apartmentId, $departure, $arrival];
    if ($ignoreId) {
        $sql .= ' AND id<>?';
        $params[] = $ignoreId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    if ((int)$stmt->fetchColumn() > 0) {
        return true;
    }
    $sql = 'SELECT COUNT(*) FROM availability_blocks WHERE apartment_id=? AND start_date < ? AND end_date > ?';
    $stmt = db()->prepare($sql);
    $stmt->execute([$apartmentId, $departure, $arrival]);
    return (int)$stmt->fetchColumn() > 0;
}

function generate_reference(): string
{
    do {
        $ref = 'SP-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $stmt = db()->prepare('SELECT COUNT(*) FROM bookings WHERE reference=?');
        $stmt->execute([$ref]);
    } while ((int)$stmt->fetchColumn() > 0);
    return $ref;
}

function calculate_price(int $apartmentId, string $arrival, string $departure, array $options = []): float
{
    return (float)calculate_price_details($apartmentId, $arrival, $departure, $options)['total'];
}

function calculate_price_details(int $apartmentId, string $arrival, string $departure, array $options = []): array
{
    $stmt = db()->prepare('SELECT * FROM apartments WHERE id=?');
    $stmt->execute([$apartmentId]);
    $apt = $stmt->fetch();
    if (!$apt || !valid_date($arrival) || !valid_date($departure) || $arrival >= $departure) {
        return [
            'nights'=>0,'accommodation'=>0.0,'cleaning'=>0.0,'parking'=>0.0,'pets'=>0.0,
            'extra_beds'=>0.0,'baby_beds'=>0.0,'late_checkout'=>0.0,'breakfast'=>0.0,
            'half_board'=>0.0,'length_discount'=>0.0,'length_discount_percent'=>0.0,
            'code_discount'=>0.0,'discount_code'=>'','tourist_tax'=>0.0,'total'=>0.0
        ];
    }

    $accommodationData = PricingService::accommodation($apartmentId, $arrival, $departure);
    $accommodationBeforeBookingSpecial = (float)$accommodationData['amount'];
    $accommodation = $accommodationBeforeBookingSpecial;
    $nightCount = (int)$accommodationData['nights'];

    $bookingSpecialType = trim((string)($options['special_price_type'] ?? 'none'));
    $bookingSpecialValue = (float)($options['special_price_value'] ?? 0);
    $bookingSpecialAdjustment = 0.0;
    if ($bookingSpecialType === 'fixed_nightly') {
        $newAccommodation = max(0.0, $bookingSpecialValue) * $nightCount;
        $bookingSpecialAdjustment = $newAccommodation - $accommodation;
        $accommodation = $newAccommodation;
    } elseif ($bookingSpecialType === 'percent_discount') {
        $bookingSpecialAdjustment = -min($accommodation, round($accommodation * max(0.0, $bookingSpecialValue) / 100, 2));
        $accommodation += $bookingSpecialAdjustment;
    } elseif ($bookingSpecialType === 'fixed_discount') {
        $bookingSpecialAdjustment = -min($accommodation, max(0.0, $bookingSpecialValue));
        $accommodation += $bookingSpecialAdjustment;
    } elseif ($bookingSpecialType === 'surcharge') {
        $bookingSpecialAdjustment = max(0.0, $bookingSpecialValue);
        $accommodation += $bookingSpecialAdjustment;
    }
    $lengthPercent = 0.0;
    $stmt = db()->prepare('SELECT percent FROM length_discounts WHERE active=1 AND min_nights<=? ORDER BY min_nights DESC LIMIT 1');
    $stmt->execute([$nightCount]);
    $lengthPercent = (float)($stmt->fetchColumn() ?: 0);
    $lengthDiscount = round($accommodation * $lengthPercent / 100, 2);

    $parkingSpaces = max(0,(int)($options['parking_spaces'] ?? 0));
    $pets = max(0,(int)($options['pets'] ?? 0));
    $extraBeds = max(0,(int)($options['extra_beds'] ?? 0));
    $babyBeds = max(0,(int)($options['baby_beds'] ?? 0));
    $lateCheckout = normalize_bool($options['late_checkout'] ?? 0);
    $adults = max(0,(int)($options['adults'] ?? 0));
    $children = max(0,(int)($options['children'] ?? 0));
    $breakfast = normalize_bool($options['breakfast'] ?? 0);
    $halfBoard = normalize_bool($options['half_board'] ?? 0);
    $breakfastDays = max(0,(int)($options['breakfast_days'] ?? 0));
    if ($breakfast && $breakfastDays === 0) $breakfastDays = $nightCount;
    $halfBoardDays = max(0,(int)($options['half_board_days'] ?? 0));
    if ($halfBoard && $halfBoardDays === 0) $halfBoardDays = $nightCount;

    $cleaning = (float)$apt['cleaning_fee'];
    $parking = round((float)$apt['parking_price_per_night'] * $parkingSpaces * $nightCount,2);
    $petFee = round((float)$apt['pet_price_per_night'] * $pets * $nightCount,2);
    $extraBedFee = round((float)$apt['extra_bed_price_per_night'] * $extraBeds * $nightCount,2);
    $babyBedFee = round((float)$apt['baby_bed_fee'] * $babyBeds,2);
    $lateFee = $lateCheckout ? (float)$apt['late_checkout_fee'] : 0.0;
    $breakfastFee = $breakfast ? round((float)$apt['breakfast_price'] * ($adults + $children) * $breakfastDays,2) : 0.0;
    $halfBoardFee = $halfBoard ? round((float)$apt['half_board_price'] * ($adults + $children) * $halfBoardDays,2) : 0.0;
    $touristTax = round((float)($options['tourist_tax'] ?? 0),2);

    $beforeCode = max(0,$accommodation - $lengthDiscount) + $cleaning + $parking + $petFee + $extraBedFee + $babyBedFee + $lateFee + $breakfastFee + $halfBoardFee;
    $code = strtoupper(trim((string)($options['discount_code'] ?? '')));
    $codeDiscount = 0.0;
    $codeId = null;
    if ($code !== '') {
        $stmt = db()->prepare("SELECT * FROM discount_codes
            WHERE UPPER(code)=? AND active=1
              AND (start_date IS NULL OR start_date<=?)
              AND (end_date IS NULL OR end_date>=?)
              AND min_nights<=?
              AND (apartment_id IS NULL OR apartment_id=?)
              AND (max_uses IS NULL OR used_count<max_uses)
            LIMIT 1");
        $stmt->execute([$code,$arrival,$departure,$nightCount,$apartmentId]);
        $rule = $stmt->fetch();
        if ($rule) {
            $codeId = (int)$rule['id'];
            $codeDiscount = $rule['discount_type']==='fixed'
                ? min($beforeCode,(float)$rule['discount_value'])
                : round($beforeCode * (float)$rule['discount_value'] / 100,2);
        } elseif (!normalize_bool($options['allow_invalid_discount_code'] ?? 0)) {
            throw new RuntimeException('Der Rabattcode ist ungültig oder für diesen Aufenthalt nicht verfügbar.');
        }
    }

    $manualDiscount = max(0,(float)($options['manual_discount'] ?? 0));
    $total = round(max(0,$beforeCode - $codeDiscount - $manualDiscount) + $touristTax,2);
    if ($bookingSpecialType === 'fixed_total') {
        $bookingSpecialAdjustment = max(0.0, $bookingSpecialValue) - $total;
        $total = round(max(0.0, $bookingSpecialValue), 2);
    }

    return [
        'nights'=>$nightCount,
        'accommodation'=>round($accommodation,2),
        'accommodation_before_booking_special'=>round($accommodationBeforeBookingSpecial,2),
        'booking_special_type'=>$bookingSpecialType,
        'booking_special_value'=>round($bookingSpecialValue,2),
        'booking_special_adjustment'=>round($bookingSpecialAdjustment,2),
        'minimum_stay'=>(int)$accommodationData['minimum_stay'],
        'minimum_stay_source'=>(string)$accommodationData['minimum_source'],
        'nightly'=>$accommodationData['nightly'],
        'cleaning'=>round($cleaning,2),
        'parking'=>$parking,
        'pets'=>$petFee,
        'extra_beds'=>$extraBedFee,
        'baby_beds'=>$babyBedFee,
        'late_checkout'=>round($lateFee,2),
        'breakfast'=>$breakfastFee,
        'half_board'=>$halfBoardFee,
        'length_discount'=>$lengthDiscount,
        'length_discount_percent'=>$lengthPercent,
        'code_discount'=>$codeDiscount,
        'manual_discount'=>round($manualDiscount,2),
        'discount_code'=>$code,
        'discount_code_id'=>$codeId,
        'tourist_tax'=>$touristTax,
        'total'=>$total
    ];
}

function log_sync(string $provider, string $direction, string $operation, string $status, string $message = '', ?int $httpCode = null, string $request = '', string $response = ''): void
{
    $stmt = db()->prepare('INSERT INTO sync_logs(provider,direction,operation,status,http_code,message,request_excerpt,response_excerpt) VALUES(?,?,?,?,?,?,?,?)');
    $stmt->execute([$provider,$direction,$operation,$status,$httpCode,$message,mb_substr($request,0,12000),mb_substr($response,0,12000)]);
}

function normalize_bool(mixed $value): int
{
    return in_array(mb_strtolower(trim((string)$value)), ['1','true','ja','yes','y','x','on'], true) ? 1 : 0;
}
