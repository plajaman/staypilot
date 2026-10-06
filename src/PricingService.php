<?php
declare(strict_types=1);

/**
 * StayPilot V2.0.5 pricing/minimum-stay resolver.
 *
 * The service is deliberately read-only. Existing stored booking totals are never
 * changed by defining new prices. It is only used when a quote/recalculation is
 * explicitly requested or when a new booking has no stored total yet.
 */
final class PricingService
{
    private static ?array $seasons = null;
    private static ?array $legacyRules = null;
    private static ?array $specialPrices = null;
    private static ?array $seasonTypePrices = null;

    public static function resetCaches(): void
    {
        self::$seasons = null;
        self::$legacyRules = null;
        self::$specialPrices = null;
        self::$seasonTypePrices = null;
    }

    public static function accommodation(int $apartmentId, string $arrival, string $departure): array
    {
        $apartment = self::apartment($apartmentId);
        if (!$apartment || !valid_date($arrival) || !valid_date($departure) || $arrival >= $departure) {
            return ['amount' => 0.0, 'nights' => 0, 'nightly' => [], 'minimum_stay' => 1, 'minimum_source' => 'Globaler Standard'];
        }

        $nightly = [];
        $amount = 0.0;
        $requiredMinStay = max(1, (int)setting('default_min_stay', 1));
        $minimumSource = 'Globaler Standard';

        for ($day = new DateTimeImmutable($arrival), $end = new DateTimeImmutable($departure); $day < $end; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $resolved = self::nightlyRate($apartment, $date);
            $amount += $resolved['price'];
            $nightly[] = $resolved;
            if ($resolved['min_stay'] > $requiredMinStay) {
                $requiredMinStay = $resolved['min_stay'];
                $minimumSource = $resolved['min_stay_source'];
            }
        }

        return [
            'amount' => round($amount, 2),
            'nights' => count($nightly),
            'nightly' => $nightly,
            'minimum_stay' => $requiredMinStay,
            'minimum_source' => $minimumSource,
            'apartment' => $apartment,
        ];
    }

    /**
     * Berechnet einen Aufenthalt ausschließlich auf Grundlage eines Wohnungstyps.
     * Damit können Angebote erstellt werden, bevor eine konkrete Apartmentnummer
     * feststeht. Apartmentbezogene Preisabweichungen und apartmentbezogene
     * Sonderpreise werden hierbei bewusst nicht angewendet.
     */
    public static function accommodationForType(int $typeId, string $arrival, string $departure): array
    {
        $stmt = db()->prepare('SELECT * FROM apartment_types WHERE id=? AND active=1 LIMIT 1');
        $stmt->execute([$typeId]);
        $type = $stmt->fetch();
        if (!$type || !valid_date($arrival) || !valid_date($departure) || $arrival >= $departure) {
            return ['amount'=>0.0,'nights'=>0,'nightly'=>[],'minimum_stay'=>1,'minimum_source'=>'Globaler Standard','apartment_type'=>$type ?: null];
        }

        $nightly = [];
        $amount = 0.0;
        $requiredMinStay = max(1, (int)setting('default_min_stay', 1));
        $minimumSource = 'Globaler Standard';
        $typeMinimum = (int)($type['default_min_stay'] ?? 0);
        if ($typeMinimum > 0) {
            $requiredMinStay = $typeMinimum;
            $minimumSource = 'Wohnungstyp ' . (string)$type['name'];
        }

        for ($day = new DateTimeImmutable($arrival), $end = new DateTimeImmutable($departure); $day < $end; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $base = max(0.0, (float)($type['standard_price'] ?? 0));
            $source = 'Standardpreis Wohnungstyp';
            $seasonId = null;
            $seasonName = null;
            $minStay = $requiredMinStay;
            $minStaySource = $minimumSource;

            $season = self::seasonForDate($typeId, $date);
            if ($season) {
                $seasonId = (int)$season['season_id'];
                $seasonName = (string)$season['season_name'];
                if ((float)($season['nightly_price'] ?? 0) > 0) {
                    $base = max(0.0, (float)$season['nightly_price']);
                    $source = 'Saisonpreis · ' . $seasonName;
                } else {
                    $legacyMultiplier = (float)($season['legacy_multiplier'] ?? 0);
                    if ($legacyMultiplier > 0) {
                        $base *= $legacyMultiplier;
                        $source = 'Bestehende Saisonregel · ' . $seasonName;
                    }
                }
                $seasonMin = (int)($season['type_min_stay'] ?: $season['period_min_stay'] ?: $season['season_min_stay']);
                if ($seasonMin > 0) {
                    $minStay = $seasonMin;
                    $minStaySource = !empty($season['type_min_stay']) ? 'Saison/Typ ' . $seasonName : 'Saison ' . $seasonName;
                }
            } else {
                $legacy = self::legacyRuleForDate($date);
                if ($legacy) {
                    $base *= max(0.0, (float)$legacy['multiplier']);
                    $source = 'Bestehende Saisonregel · ' . $legacy['name'];
                    $legacyMin = max(1, (int)$legacy['min_stay']);
                    if ($legacyMin > 0) {
                        $minStay = $legacyMin;
                        $minStaySource = 'Bestehende Saisonregel ' . $legacy['name'];
                    }
                }
            }

            $resolved = [
                'date'=>$date,'price'=>round(max(0.0,$base),2),'source'=>$source,
                'season_id'=>$seasonId,'season_name'=>$seasonName,
                'special_price_id'=>null,'special_price_name'=>null,
                'min_stay'=>max(1,$minStay),'min_stay_source'=>$minStaySource,
            ];
            $nightly[] = $resolved;
            $amount += $resolved['price'];
            if ($resolved['min_stay'] > $requiredMinStay) {
                $requiredMinStay = $resolved['min_stay'];
                $minimumSource = $resolved['min_stay_source'];
            }
        }

        return [
            'amount'=>round($amount,2),'nights'=>count($nightly),'nightly'=>$nightly,
            'minimum_stay'=>$requiredMinStay,'minimum_source'=>$minimumSource,'apartment_type'=>$type,
        ];
    }

    public static function minimumStay(int $apartmentId, string $arrival, string $departure): array
    {
        $result = self::accommodation($apartmentId, $arrival, $departure);
        return [
            'nights' => (int)$result['nights'],
            'required' => (int)$result['minimum_stay'],
            'source' => (string)$result['minimum_source'],
            'valid' => (int)$result['nights'] >= (int)$result['minimum_stay'],
        ];
    }

    private static function apartment(int $id): ?array
    {
        $stmt = db()->prepare("SELECT a.*,t.name apartment_type_name,t.standard_price type_standard_price,
            t.default_min_stay type_default_min_stay,h.name house_name
            FROM apartments a
            LEFT JOIN apartment_types t ON t.id=a.apartment_type_id
            LEFT JOIN houses h ON h.id=a.house_id
            WHERE a.id=? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function nightlyRate(array $apartment, string $date): array
    {
        $apartmentBase = max(0.0, (float)($apartment['base_price'] ?? 0));
        $typeBase = max(0.0, (float)($apartment['type_standard_price'] ?? 0));
        $base = $apartmentBase > 0 ? $apartmentBase : $typeBase;
        $source = $apartmentBase > 0 ? 'Apartment-Grundpreis' : 'Standardpreis Wohnungstyp';
        $seasonId = null;
        $seasonName = null;
        $minStay = max(1, (int)setting('default_min_stay', 1));
        $minStaySource = 'Globaler Standard';

        $typeMin = (int)($apartment['type_default_min_stay'] ?? 0);
        if ($typeMin > 0) {
            $minStay = $typeMin;
            $minStaySource = 'Wohnungstyp ' . ($apartment['apartment_type_name'] ?? '');
        }

        $season = self::seasonForDate((int)($apartment['apartment_type_id'] ?? 0), $date);
        if ($season) {
            $seasonId = (int)$season['season_id'];
            $seasonName = (string)$season['season_name'];
            if ((float)($season['nightly_price'] ?? 0) > 0) {
                $base = max(0.0, (float)$season['nightly_price']);
                $source = 'Saisonpreis · ' . $seasonName;
            } else {
                $legacyMultiplier = (float)($season['legacy_multiplier'] ?? 0);
                if ($legacyMultiplier > 0 && $base > 0) {
                    $base *= $legacyMultiplier;
                    $source = 'Bestehende Saisonregel · ' . $seasonName;
                }
            }
            $seasonMin = (int)($season['type_min_stay'] ?: $season['period_min_stay'] ?: $season['season_min_stay']);
            if ($seasonMin > 0) {
                $minStay = $seasonMin;
                $minStaySource = !empty($season['type_min_stay'])
                    ? 'Saison/Typ ' . $seasonName
                    : 'Saison ' . $seasonName;
            }
        } else {
            $legacy = self::legacyRuleForDate($date);
            if ($legacy && $base > 0) {
                $base *= max(0.0, (float)$legacy['multiplier']);
                $source = 'Bestehende Saisonregel · ' . $legacy['name'];
                $legacyMin = max(1, (int)$legacy['min_stay']);
                if ($legacyMin > 0) {
                    $minStay = $legacyMin;
                    $minStaySource = 'Bestehende Saisonregel ' . $legacy['name'];
                }
            }
        }

        // Eine konkrete Wohnung übernimmt zunächst den Typ-/Saisonpreis und erhält
        // danach zuverlässig ihre individuelle feste oder prozentuale Abweichung.
        $adjustmentType = (string)($apartment['price_adjustment_type'] ?? 'fixed');
        $adjustmentValue = (float)($apartment['price_adjustment_value'] ?? 0);
        if ($adjustmentValue !== 0.0 && $base > 0) {
            $base = $adjustmentType === 'percent'
                ? $base * (1 + ($adjustmentValue / 100))
                : $base + $adjustmentValue;
            $base = max(0.0, $base);
            $source .= $adjustmentType === 'percent'
                ? ' · Apartmentabweichung ' . ($adjustmentValue >= 0 ? '+' : '') . $adjustmentValue . ' %'
                : ' · Apartmentabweichung ' . ($adjustmentValue >= 0 ? '+' : '') . number_format($adjustmentValue, 2, ',', '.') . ' €';
        }

        $apartmentMin = (int)($apartment['min_stay_override'] ?? 0);
        if ($apartmentMin > 0) {
            $minStay = $apartmentMin;
            $minStaySource = 'Apartment ' . ($apartment['code'] ?? '');
        }

        // Ein ausdrücklich hinterlegter Sonderpreis bleibt die letzte und damit
        // höchste Preisregel. Prozent-/Betragsanpassungen bauen auf dem bereits
        // aufgelösten Typ-/Saison-/Apartmentpreis auf.
        $special = self::specialForDate($apartment, $date);
        $specialId = null;
        $specialName = null;
        if ($special) {
            $specialId = (int)$special['id'];
            $specialName = (string)$special['name'];
            $mode = (string)$special['price_mode'];
            $value = (float)$special['price_value'];
            if ($mode === 'fixed_nightly') {
                $base = max(0.0, $value);
            } elseif ($mode === 'percent') {
                $base = max(0.0, $base * (1 + ($value / 100)));
            } elseif ($mode === 'fixed_adjustment') {
                $base = max(0.0, $base + $value);
            }
            $source = 'Sonderpreis · ' . $specialName;
            $specialMin = (int)($special['min_stay'] ?? 0);
            if ($specialMin > 0) {
                $minStay = $specialMin;
                $minStaySource = 'Sonderpreis ' . $specialName;
            }
        }

        return [
            'date' => $date,
            'price' => round(max(0.0, $base), 2),
            'source' => $source,
            'season_id' => $seasonId,
            'season_name' => $seasonName,
            'special_price_id' => $specialId,
            'special_price_name' => $specialName,
            'min_stay' => max(1, $minStay),
            'min_stay_source' => $minStaySource,
        ];
    }

    private static function seasonForDate(int $typeId, string $date): ?array
    {
        if (self::$seasons === null) {
            self::$seasons = db()->query("SELECT sp.id period_id,sp.season_id,sp.start_date,sp.end_date,
                sp.min_stay period_min_stay,s.name season_name,s.color,s.priority,s.default_min_stay season_min_stay,
                s.legacy_multiplier,s.active
                FROM season_periods sp
                JOIN seasons s ON s.id=sp.season_id
                WHERE s.active=1
                ORDER BY s.priority DESC,sp.start_date,sp.id")->fetchAll();
        }
        if (self::$seasonTypePrices === null) {
            self::$seasonTypePrices = db()->query('SELECT * FROM season_type_prices')->fetchAll();
        }
        foreach (self::$seasons as $row) {
            if ($date < $row['start_date'] || $date > $row['end_date']) continue;
            $row['apartment_type_id'] = null;
            $row['nightly_price'] = null;
            $row['type_min_stay'] = null;
            foreach (self::$seasonTypePrices as $price) {
                if ((int)$price['season_id'] === (int)$row['season_id'] && (int)$price['apartment_type_id'] === $typeId) {
                    $row['apartment_type_id'] = (int)$price['apartment_type_id'];
                    $row['nightly_price'] = $price['nightly_price'];
                    $row['type_min_stay'] = $price['min_stay'];
                    break;
                }
            }
            return $row;
        }
        return null;
    }

    private static function legacyRuleForDate(string $date): ?array
    {
        if (self::$legacyRules === null) {
            self::$legacyRules = db()->query('SELECT * FROM season_rules ORDER BY priority DESC,id DESC')->fetchAll();
        }
        foreach (self::$legacyRules as $rule) {
            if ($date >= $rule['start_date'] && $date <= $rule['end_date']) return $rule;
        }
        return null;
    }

    private static function specialForDate(array $apartment, string $date): ?array
    {
        if (self::$specialPrices === null) {
            self::$specialPrices = db()->query("SELECT * FROM special_prices WHERE active=1 ORDER BY priority DESC,id DESC")->fetchAll();
        }
        $weekday = (int)(new DateTimeImmutable($date))->format('N');
        foreach (self::$specialPrices as $row) {
            if ($date < $row['start_date'] || $date > $row['end_date']) continue;
            $weekdays = json_decode((string)($row['weekdays_json'] ?? ''), true);
            if (is_array($weekdays) && $weekdays && !in_array($weekday, array_map('intval', $weekdays), true)) continue;
            $scope = (string)$row['scope_type'];
            if ($scope === 'apartment' && (int)$row['apartment_id'] !== (int)$apartment['id']) continue;
            if ($scope === 'apartment_type' && (int)$row['apartment_type_id'] !== (int)($apartment['apartment_type_id'] ?? 0)) continue;
            if ($scope === 'house' && (int)$row['house_id'] !== (int)($apartment['house_id'] ?? 0)) continue;
            if (!in_array($scope, ['all','house','apartment_type','apartment'], true)) continue;
            return $row;
        }
        return null;
    }
}
