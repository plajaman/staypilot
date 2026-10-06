<?php
declare(strict_types=1);

/**
 * Zentrale Marktreife-Logik fuer Buchungsquelle und Abrechnungsart.
 * Trennt Betriebs-/Belegungsrelevanz von interner StayPilot-Abrechnung.
 */
final class BookingAccountingService
{
    public const MODE_INTERNAL = 'internal';      // normale StayPilot-Abrechnung
    public const MODE_EXTERNAL = 'external';      // Portal/Import rechnet extern ab
    public const MODE_NONE = 'none';              // Personal, Eigentümer, Sperrung: keine Abrechnung
    public const MODE_PORTAL_LATER = 'portal_later'; // spaetere Portalabrechnung/Auswertung

    public static function validModes(): array
    {
        return [self::MODE_INTERNAL, self::MODE_EXTERNAL, self::MODE_NONE, self::MODE_PORTAL_LATER];
    }

    public static function normalizeMode(?string $mode, ?string $source = null): string
    {
        $mode = strtolower(trim((string)$mode));
        if (in_array($mode, self::validModes(), true)) return $mode;
        return self::modeFromSource((string)$source);
    }

    public static function modeFromSource(string $source): string
    {
        $s = strtolower(trim($source));
        if ($s === '') return self::MODE_INTERNAL;
        if (preg_match('/(personal|mitarbeiter|eigent|owner|sperr|block|wartung|technik|familie|privat)/u', $s)) return self::MODE_NONE;
        if (preg_match('/(booking|airbnb|expedia|vrbo|hotel.?spider|csv|xml|ical|import|portal|channel|avantio|smoobu|beds24|lodgify|hostaway)/u', $s)) return self::MODE_EXTERNAL;
        return self::MODE_INTERNAL;
    }

    public static function label(string $mode): string
    {
        return match(self::normalizeMode($mode)) {
            self::MODE_EXTERNAL => 'extern abgerechnet',
            self::MODE_NONE => 'keine Abrechnung',
            self::MODE_PORTAL_LATER => 'Portalabrechnung später',
            default => 'intern abrechnen',
        };
    }

    public static function isInternallyBillable(array $booking): bool
    {
        $mode = self::normalizeMode($booking['accounting_mode'] ?? null, $booking['source'] ?? null);
        $status = strtolower((string)($booking['status'] ?? ''));
        $deleted = !empty($booking['deleted_at']) && (string)$booking['deleted_at'] !== '0000-00-00 00:00:00';
        return $mode === self::MODE_INTERNAL && !$deleted && !in_array($status, ['cancelled','canceled','rejected','storniert','abgelehnt'], true);
    }

    public static function applyModeToBooking(PDO $pdo, int $bookingId, string $mode, ?string $reason = null): void
    {
        $mode = self::normalizeMode($mode);
        $reason = trim((string)$reason);
        $stmt = $pdo->prepare('UPDATE bookings SET accounting_mode=?, billing_excluded_reason=? WHERE id=?');
        $stmt->execute([$mode, $reason !== '' ? mb_substr($reason, 0, 255) : null, $bookingId]);
        if ($mode !== self::MODE_INTERNAL) {
            self::neutralizeInternalBilling($pdo, $bookingId, $mode, $reason !== '' ? $reason : self::label($mode));
        }
    }

    public static function neutralizeInternalBilling(PDO $pdo, int $bookingId, string $mode, string $reason): void
    {
        if (!self::tableExists($pdo, 'booking_payment_schedule')) return;
        $mode = self::normalizeMode($mode);
        if ($mode === self::MODE_INTERNAL) return;
        $reason = mb_substr($reason !== '' ? $reason : self::label($mode), 0, 500);
        $pdo->prepare("UPDATE booking_payment_schedule SET status='waived', paid_amount=0, waived_reason=COALESCE(NULLIF(waived_reason,''), ?) WHERE booking_id=? AND status NOT IN ('received','waived','cancelled','canceled','void','aufgehoben','storniert')")
            ->execute([$reason, $bookingId]);
        $pdo->prepare("UPDATE bookings SET payment_status=?, deposit_status=?, remaining_status=?, paid_amount=0 WHERE id=?")
            ->execute([$mode === self::MODE_EXTERNAL ? 'external' : 'not_billable', 'waived', 'waived', $bookingId]);
    }

    public static function tableExists(PDO $pdo, string $table): bool
    {
        try { $stmt = $pdo->prepare('SHOW TABLES LIKE ?'); $stmt->execute([$table]); return (bool)$stmt->fetchColumn(); }
        catch (Throwable) { return false; }
    }
}
