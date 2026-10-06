<?php
declare(strict_types=1);

final class PrivacyService
{
    public static function housekeepingName(?string $name, ?string $mode = null): string
    {
        $name = trim((string)$name);
        if ($name === '') return '';
        $mode = $mode ?: (string)setting('housekeeping_guest_name_mode', 'initials');
        $parts = array_values(array_filter(preg_split('/\s+/u', $name) ?: []));
        if ($mode === 'full') return $name;
        if ($mode === 'hidden') return 'Gastdaten verborgen';
        if ($mode === 'first_initial') {
            $first = $parts[0] ?? '';
            $last = $parts[count($parts)-1] ?? '';
            return trim($first . ($last !== '' ? ' ' . self::initial($last) . '.' : ''));
        }
        $initials = array_map(static fn(string $part): string => self::initial($part) . '.', array_slice($parts, 0, 3));
        return implode(' ', $initials) ?: 'Gast';
    }

    private static function initial(string $value): string
    {
        if (function_exists('mb_substr')) {
            $char = mb_substr($value, 0, 1, 'UTF-8');
            return function_exists('mb_strtoupper') ? mb_strtoupper($char, 'UTF-8') : strtoupper($char);
        }
        if (preg_match('/^./us', $value, $match)) return strtoupper($match[0]);
        return strtoupper(substr($value, 0, 1));
    }

    public static function housekeepingReference(?string $reference): string
    {
        if (!(bool)setting('housekeeping_show_booking_reference', false)) return '';
        return trim((string)$reference);
    }

    public static function housekeepingRequest(?string $request): string
    {
        if (!(bool)setting('housekeeping_show_guest_request', false)) return '';
        return trim((string)$request);
    }
}
