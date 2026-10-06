<?php
declare(strict_types=1);

final class Validator
{
    public static function text(array $data, string $key, string $label, int $max = 255, bool $required = false): string
    {
        $value = trim((string)($data[$key] ?? ''));
        if ($required && $value === '') {
            throw new ValidationException($label . ' ist erforderlich.');
        }
        if (mb_strlen($value) > $max) {
            throw new ValidationException($label . ' darf höchstens ' . $max . ' Zeichen enthalten.');
        }
        return $value;
    }

    public static function email(array $data, string $key, string $label = 'E-Mail', bool $required = false): string
    {
        $value = self::text($data, $key, $label, 190, $required);
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException($label . ' ist ungültig.');
        }
        return $value;
    }

    public static function integer(array $data, string $key, string $label, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
    {
        $raw = $data[$key] ?? 0;
        if ($raw === '' || filter_var($raw, FILTER_VALIDATE_INT) === false) {
            throw new ValidationException($label . ' muss eine ganze Zahl sein.');
        }
        $value = (int)$raw;
        if ($value < $min || $value > $max) {
            throw new ValidationException($label . ' liegt außerhalb des erlaubten Bereichs.');
        }
        return $value;
    }

    public static function decimal(array $data, string $key, string $label, float $min = 0.0, ?float $max = null): float
    {
        $raw = str_replace(',', '.', trim((string)($data[$key] ?? '0')));
        if ($raw === '' || !is_numeric($raw)) {
            throw new ValidationException($label . ' muss eine Zahl sein.');
        }
        $value = (float)$raw;
        if ($value < $min || ($max !== null && $value > $max)) {
            throw new ValidationException($label . ' liegt außerhalb des erlaubten Bereichs.');
        }
        return round($value, 2);
    }

    public static function date(array $data, string $key, string $label, bool $required = false): ?string
    {
        $value = trim((string)($data[$key] ?? ''));
        if ($value === '') {
            if ($required) throw new ValidationException($label . ' ist erforderlich.');
            return null;
        }
        if (!valid_date($value)) throw new ValidationException($label . ' ist ungültig.');
        return $value;
    }

    public static function oneOf(array $data, string $key, string $label, array $allowed, string $default = ''): string
    {
        $value = trim((string)($data[$key] ?? $default));
        if (!in_array($value, $allowed, true)) {
            throw new ValidationException($label . ' enthält einen ungültigen Wert.');
        }
        return $value;
    }
}
