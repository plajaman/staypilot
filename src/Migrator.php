<?php
declare(strict_types=1);

final class Migrator
{
    public static function run(): void
    {
        $pdo = db();
        $target = '2.3.6.6';
        $current = '';
        try {
            $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='schema_version' LIMIT 1");
            $current = (string)($stmt ? $stmt->fetchColumn() : '');
        } catch (Throwable) {
            // Frische oder sehr alte Installation.
        }
        $schemaComplete = self::requiredSchemaComplete();
        if ($current !== '' && version_compare($current, $target, '>')) {
            if (!$schemaComplete) {
                throw new RuntimeException('Die Datenbank meldet das neuere Schema ' . $current . ', ist aber unvollständig. Eine automatische Rückstufung wurde verhindert.');
            }
            return;
        }
        if ($current !== '' && version_compare($current, $target, '>=') && $schemaComplete) return;

        $hasExistingData = self::tableExists('settings') && (
            self::tableExists('bookings') || self::tableExists('guests') || self::tableExists('apartments')
        );
        $autoBackup = true;
        if (self::tableExists('settings')) {
            try { $autoBackup = (bool)setting('auto_pre_update_backup', true); }
            catch (Throwable) { $autoBackup = true; }
        }
        $needsUpgradeBackup = $hasExistingData && ($current === '' || version_compare($current, $target, '<') || !$schemaComplete);
        if ($needsUpgradeBackup && $autoBackup) {
            $fromLabel = $current !== '' ? str_replace('.', '-', $current) : 'schema-unbekannt';
            try {
                BackupManager::create('pre-migration-' . $fromLabel . '-to-2-3-6-6');
            } catch (Throwable $e) {
                AppLogger::error($e, ['from' => $current ?: 'unknown', 'to' => $target], 'migration');
                throw new RuntimeException('Das Update wurde aus Sicherheitsgründen abgebrochen, weil die automatische Datensicherung nicht erstellt werden konnte: ' . $e->getMessage());
            }
        }

        self::migrate110($pdo);
        self::migrate120($pdo);
        self::migrate200($pdo);
        self::migrate205($pdo);
        self::migrate208($pdo);
        self::migrate209($pdo);
        self::migrate210($pdo);
        self::migrate2100($pdo);
        self::migrate211($pdo);
        self::migrate212($pdo);
        self::migrate213($pdo);
        self::migrate214($pdo);
        self::migrate220($pdo);
        self::migrate224($pdo);
        self::migrate228($pdo);
        self::migrate234($pdo);
        self::migrate2365($pdo);
        self::migrate2366($pdo);

        $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')
            ->execute(['schema_version',$target]);
        try {
            $pdo->prepare('INSERT INTO schema_migrations(version,applied_at) VALUES(?,NOW()) ON DUPLICATE KEY UPDATE applied_at=VALUES(applied_at)')->execute([$target]);
        } catch (Throwable $e) {
            AppLogger::error($e, ['version' => $target], 'migration');
        }
    }

    /**
     * Repariert das bekannte V2.0.0-Schema idempotent, auch wenn die gespeicherte
     * Schema-Version bereits 2.0.0 lautet. Wird ausschließlich vom gesicherten
     * Datenbank-Hilfsprogramm nach einer eigenen Vorab-Sicherung verwendet.
     */
    public static function repairKnownSchema(): void
    {
        $pdo = db();
        $target = '2.3.6.6';
        $current = '';
        try {
            $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='schema_version' LIMIT 1");
            $current = (string)($stmt ? $stmt->fetchColumn() : '');
        } catch (Throwable) {
            $current = '';
        }
        if ($current !== '' && version_compare($current, $target, '>')) {
            throw new RuntimeException('Die Datenbank verwendet bereits das neuere Schema ' . $current . '. Dieses Hilfsprogramm darf es nicht zurückstufen.');
        }

        self::migrate110($pdo);
        self::migrate120($pdo);
        self::migrate200($pdo);
        self::migrate205($pdo);
        self::migrate208($pdo);
        self::migrate209($pdo);
        self::migrate210($pdo);
        self::migrate2100($pdo);
        self::migrate211($pdo);
        self::migrate212($pdo);
        self::migrate213($pdo);
        self::migrate214($pdo);
        self::migrate220($pdo);
        self::migrate224($pdo);
        self::migrate228($pdo);
        self::migrate234($pdo);
        self::migrate2365($pdo);
        self::migrate2366($pdo);

        $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')
            ->execute(['schema_version',$target]);
        $pdo->prepare('INSERT INTO schema_migrations(version,applied_at) VALUES(?,NOW()) ON DUPLICATE KEY UPDATE applied_at=VALUES(applied_at)')
            ->execute([$target]);
    }

    private static function migrate110(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS guest_categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            color VARCHAR(20) NOT NULL DEFAULT '#64748b',
            description VARCHAR(255) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_guest_categories_active_sort (active, sort_order, name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::addColumn('guests','category_id',"INT UNSIGNED NULL AFTER language");
        self::addColumn('guests','vip',"TINYINT(1) NOT NULL DEFAULT 0 AFTER category_id");
        self::addColumn('guests','date_of_birth',"DATE NULL AFTER vip");
        self::addColumn('guests','company',"VARCHAR(160) NULL AFTER date_of_birth");
        self::addColumn('guests','passport_number',"VARCHAR(100) NULL AFTER company");

        self::addColumn('bookings','planned_arrival_time',"TIME NULL AFTER departure");
        self::addColumn('bookings','planned_departure_time',"TIME NULL AFTER planned_arrival_time");
        self::addColumn('bookings','actual_checkin_at',"DATETIME NULL AFTER planned_departure_time");
        self::addColumn('bookings','actual_checkout_at',"DATETIME NULL AFTER actual_checkin_at");
        self::addColumn('bookings','babies',"SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER children");
        self::addColumn('bookings','pets',"SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER babies");
        self::addColumn('bookings','payment_status',"VARCHAR(30) NOT NULL DEFAULT 'open' AFTER paid_amount");
        self::addColumn('bookings','deposit_amount',"DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER payment_status");
        self::addColumn('bookings','tourist_tax',"DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER deposit_amount");
        self::addColumn('bookings','discount_amount',"DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER tourist_tax");
        self::addColumn('bookings','breakfast_start_date',"DATE NULL AFTER breakfast");
        self::addColumn('bookings','breakfast_end_date',"DATE NULL AFTER breakfast_start_date");
        self::addColumn('bookings','half_board_start_date',"DATE NULL AFTER half_board");
        self::addColumn('bookings','half_board_end_date',"DATE NULL AFTER half_board_start_date");
        self::addColumn('bookings','is_upgrade',"TINYINT(1) NOT NULL DEFAULT 0 AFTER half_board_end_date");
        self::addColumn('bookings','upgrade_from_apartment_id',"INT UNSIGNED NULL AFTER is_upgrade");
        self::addColumn('bookings','upgrade_note',"VARCHAR(255) NULL AFTER upgrade_from_apartment_id");
        self::addColumn('bookings','vehicle_plate',"VARCHAR(80) NULL AFTER upgrade_note");
        self::addColumn('bookings','guest_request',"TEXT NULL AFTER vehicle_plate");
        self::addColumn('bookings','internal_notes',"TEXT NULL AFTER guest_request");

        $pdo->exec("CREATE TABLE IF NOT EXISTS booking_change_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id INT UNSIGNED NOT NULL,
            action VARCHAR(60) NOT NULL,
            old_values_json LONGTEXT NULL,
            new_values_json LONGTEXT NULL,
            note VARCHAR(255) NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_booking_change_booking_date (booking_id, created_at),
            INDEX idx_booking_change_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $defaults=[
            ['Standard','#64748b','Normaler Gast',10],
            ['Stammgast','#2563eb','Wiederkehrender Gast',20],
            ['VIP','#7c3aed','Besondere Betreuung',30],
            ['Gruppe','#0f9f6e','Gruppen- oder Vereinsreise',40],
            ['Geschäftlich','#f59e0b','Geschäftsreise',50],
        ];
        $stmt=$pdo->prepare('INSERT INTO guest_categories(name,color,description,sort_order) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)');
        foreach($defaults as $row){$stmt->execute($row);}
    }

    private static function migrate120(PDO $pdo): void
    {
        self::addColumn('apartments','parking_price_per_night',"DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER half_board_price");
        self::addColumn('apartments','pet_price_per_night',"DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER parking_price_per_night");
        self::addColumn('apartments','extra_bed_price_per_night',"DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER pet_price_per_night");
        self::addColumn('apartments','baby_bed_fee',"DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER extra_bed_price_per_night");
        self::addColumn('apartments','late_checkout_fee',"DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER baby_bed_fee");
        self::addColumn('apartments','internet_access',"TINYINT(1) NOT NULL DEFAULT 1 AFTER late_checkout_fee");
        self::addColumn('apartments','full_address',"VARCHAR(255) NULL AFTER internet_access");

        self::addColumn('guests','title',"VARCHAR(30) NULL FIRST");
        self::addColumn('guests','second_last_name',"VARCHAR(120) NULL AFTER last_name");
        self::addColumn('guests','gender',"VARCHAR(20) NULL AFTER second_last_name");
        self::addColumn('guests','nationality',"VARCHAR(100) NULL AFTER gender");
        self::addColumn('guests','document_type',"VARCHAR(40) NULL AFTER passport_number");
        self::addColumn('guests','document_support_number',"VARCHAR(100) NULL AFTER document_type");
        self::addColumn('guests','document_issue_date',"DATE NULL AFTER document_support_number");
        self::addColumn('guests','document_country',"VARCHAR(100) NULL AFTER document_issue_date");
        self::addColumn('guests','place_of_birth',"VARCHAR(160) NULL AFTER document_country");
        self::addColumn('guests','province',"VARCHAR(120) NULL AFTER place_of_birth");
        self::addColumn('guests','fixed_phone',"VARCHAR(80) NULL AFTER province");
        self::addColumn('guests','emergency_contact_name',"VARCHAR(160) NULL AFTER fixed_phone");
        self::addColumn('guests','emergency_contact_phone',"VARCHAR(80) NULL AFTER emergency_contact_name");
        self::addColumn('guests','marketing_opt_in',"TINYINT(1) NOT NULL DEFAULT 0 AFTER emergency_contact_phone");

        self::addColumn('bookings','discount_code',"VARCHAR(80) NULL AFTER discount_amount");
        self::addColumn('bookings','discount_code_id',"INT UNSIGNED NULL AFTER discount_code");
        self::addColumn('bookings','parking_spaces',"SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER discount_code_id");
        self::addColumn('bookings','extra_beds',"SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER parking_spaces");
        self::addColumn('bookings','baby_beds',"SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER extra_beds");
        self::addColumn('bookings','late_checkout',"TINYINT(1) NOT NULL DEFAULT 0 AFTER baby_beds");
        self::addColumn('bookings','payment_method',"VARCHAR(60) NULL AFTER late_checkout");
        self::addColumn('bookings','payment_reference',"VARCHAR(190) NULL AFTER payment_method");
        self::addColumn('bookings','price_breakdown_json',"LONGTEXT NULL AFTER payment_reference");
        self::addColumn('bookings','police_status',"VARCHAR(30) NOT NULL DEFAULT 'open' AFTER price_breakdown_json");
        self::addColumn('bookings','police_sent_at',"DATETIME NULL AFTER police_status");
        self::addColumn('bookings','contract_signed_at',"DATETIME NULL AFTER police_sent_at");

        self::addColumn('housekeeping_tasks','estimated_minutes',"SMALLINT UNSIGNED NOT NULL DEFAULT 60 AFTER priority");
        self::addColumn('housekeeping_tasks','linen_change',"TINYINT(1) NOT NULL DEFAULT 1 AFTER estimated_minutes");
        self::addColumn('housekeeping_tasks','towel_change',"TINYINT(1) NOT NULL DEFAULT 1 AFTER linen_change");
        self::addColumn('housekeeping_tasks','checklist_json',"LONGTEXT NULL AFTER towel_change");
        self::addColumn('housekeeping_tasks','supplies',"TEXT NULL AFTER checklist_json");
        self::addColumn('housekeeping_tasks','supervisor',"VARCHAR(120) NULL AFTER supplies");

        self::addColumn('booking_travellers','document_issue_date',"DATE NULL AFTER document_type");
        self::addColumn('booking_travellers','document_country',"VARCHAR(100) NULL AFTER document_issue_date");
        self::addColumn('booking_travellers','place_of_birth',"VARCHAR(160) NULL AFTER document_country");
        self::addColumn('booking_travellers','province',"VARCHAR(120) NULL AFTER place_of_birth");

        $pdo->exec("CREATE TABLE IF NOT EXISTS length_discounts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            min_nights SMALLINT UNSIGNED NOT NULL,
            percent DECIMAL(6,2) NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            label VARCHAR(120) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_length_min_nights (min_nights)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS discount_codes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(80) NOT NULL UNIQUE,
            description VARCHAR(190) NULL,
            discount_type VARCHAR(20) NOT NULL DEFAULT 'percent',
            discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
            start_date DATE NULL,
            end_date DATE NULL,
            min_nights SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            apartment_id INT UNSIGNED NULL,
            max_uses INT UNSIGNED NULL,
            used_count INT UNSIGNED NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_discount_active_dates (active,start_date,end_date),
            INDEX idx_discount_apartment (apartment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS booking_travellers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id INT UNSIGNED NOT NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(120) NOT NULL,
            second_last_name VARCHAR(120) NULL,
            gender VARCHAR(20) NULL,
            document_number VARCHAR(100) NULL,
            document_support_number VARCHAR(100) NULL,
            document_type VARCHAR(40) NULL,
            document_issue_date DATE NULL,
            document_country VARCHAR(100) NULL,
            place_of_birth VARCHAR(160) NULL,
            province VARCHAR(120) NULL,
            nationality VARCHAR(100) NULL,
            date_of_birth DATE NULL,
            address VARCHAR(190) NULL,
            city VARCHAR(120) NULL,
            country VARCHAR(100) NULL,
            postal_code VARCHAR(30) NULL,
            fixed_phone VARCHAR(80) NULL,
            mobile_phone VARCHAR(80) NULL,
            email VARCHAR(190) NULL,
            relationship_to_primary VARCHAR(100) NULL,
            minor TINYINT(1) NOT NULL DEFAULT 0,
            signature_status VARCHAR(30) NOT NULL DEFAULT 'open',
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_travellers_booking (booking_id),
            INDEX idx_travellers_name (last_name,first_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $discounts=[
            [7,5.00,'Ab 7 Nächten',10],
            [14,10.00,'Ab 14 Nächten',20],
            [21,15.00,'Ab 21 Nächten',30],
        ];
        $stmt=$pdo->prepare('INSERT INTO length_discounts(min_nights,percent,label,sort_order) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE min_nights=VALUES(min_nights)');
        foreach($discounts as $row){$stmt->execute($row);}
    }

    private static function migrate200(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS houses (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            code VARCHAR(40) NOT NULL UNIQUE,
            address VARCHAR(255) NULL,
            contact_name VARCHAR(160) NULL,
            phone VARCHAR(80) NULL,
            email VARCHAR(190) NULL,
            default_checkin_time TIME NULL DEFAULT '16:00:00',
            default_checkout_time TIME NULL DEFAULT '10:00:00',
            cleaning_team VARCHAR(160) NULL,
            breakfast_available TINYINT(1) NOT NULL DEFAULT 0,
            half_board_available TINYINT(1) NOT NULL DEFAULT 0,
            internal_notes TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_houses_active_sort (active,sort_order,name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS apartment_types (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            code VARCHAR(60) NOT NULL UNIQUE,
            max_occupancy SMALLINT UNSIGNED NOT NULL DEFAULT 2,
            default_adults SMALLINT UNSIGNED NOT NULL DEFAULT 2,
            default_children SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            bedrooms SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            beds SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            living_area DECIMAL(8,2) NULL,
            standard_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            cleaning_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
            standard_cleaning_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
            amenities_json LONGTEXT NULL,
            description TEXT NULL,
            photos_json LONGTEXT NULL,
            breakfast_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            half_board_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            parking_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            pet_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            extra_bed_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            baby_bed_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            discounts_json LONGTEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_types_active_sort (active,sort_order,name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::addColumn('users','last_login_at',"DATETIME NULL AFTER active");
        self::addColumn('users','failed_login_count',"SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_login_at");
        self::addColumn('users','locked_until',"DATETIME NULL AFTER failed_login_count");

        self::addColumn('apartments','house_id',"INT UNSIGNED NULL AFTER id");
        self::addColumn('apartments','apartment_type_id',"INT UNSIGNED NULL AFTER house_id");
        self::addColumn('apartments','apartment_number',"VARCHAR(60) NULL AFTER apartment_type_id");
        self::addColumn('apartments','floor',"VARCHAR(60) NULL AFTER full_address");
        self::addColumn('apartments','location_description',"VARCHAR(190) NULL AFTER floor");
        self::addColumn('apartments','balcony',"TINYINT(1) NOT NULL DEFAULT 0 AFTER location_description");
        self::addColumn('apartments','terrace',"TINYINT(1) NOT NULL DEFAULT 0 AFTER balcony");
        self::addColumn('apartments','sea_view',"TINYINT(1) NOT NULL DEFAULT 0 AFTER terrace");
        self::addColumn('apartments','parking_number',"VARCHAR(60) NULL AFTER sea_view");
        self::addColumn('apartments','key_number',"VARCHAR(60) NULL AFTER parking_number");
        self::addColumn('apartments','wifi_ssid',"VARCHAR(190) NULL AFTER key_number");
        self::addColumn('apartments','wifi_password_encrypted',"LONGTEXT NULL AFTER wifi_ssid");
        self::addColumn('apartments','price_adjustment_type',"VARCHAR(20) NOT NULL DEFAULT 'fixed' AFTER wifi_password_encrypted");
        self::addColumn('apartments','price_adjustment_value',"DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER price_adjustment_type");
        self::addColumn('apartments','cleaning_instructions',"TEXT NULL AFTER price_adjustment_value");
        self::addColumn('apartments','out_of_service',"TINYINT(1) NOT NULL DEFAULT 0 AFTER cleaning_instructions");
        self::addColumn('apartments','owner_occupancy_allowed',"TINYINT(1) NOT NULL DEFAULT 1 AFTER out_of_service");
        self::addColumn('apartments','internal_remarks',"TEXT NULL AFTER owner_occupancy_allowed");

        self::addColumn('guests','own_color',"VARCHAR(20) NULL AFTER vip");
        self::addColumn('guests','repeat_guest',"TINYINT(1) NOT NULL DEFAULT 0 AFTER own_color");

        self::addColumn('bookings','booking_color',"VARCHAR(20) NULL AFTER source");
        self::addColumn('bookings','original_apartment_id',"INT UNSIGNED NULL AFTER upgrade_from_apartment_id");
        self::addColumn('bookings','key_issued',"TINYINT(1) NOT NULL DEFAULT 0 AFTER actual_checkout_at");
        self::addColumn('bookings','damage_notes',"TEXT NULL AFTER key_issued");
        self::addColumn('bookings','cleaning_status',"VARCHAR(30) NOT NULL DEFAULT 'open' AFTER damage_notes");
        self::addColumn('bookings','extra_cleaning',"TINYINT(1) NOT NULL DEFAULT 0 AFTER late_checkout");
        self::addColumn('bookings','linen_change',"TINYINT(1) NOT NULL DEFAULT 0 AFTER extra_cleaning");
        self::addColumn('bookings','early_arrival',"TINYINT(1) NOT NULL DEFAULT 0 AFTER linen_change");
        self::addColumn('bookings','other_services_json',"LONGTEXT NULL AFTER early_arrival");
        self::addColumn('bookings','special_requests',"TEXT NULL AFTER guest_request");

        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(80) NOT NULL,
            entity_id VARCHAR(80) NULL,
            action VARCHAR(60) NOT NULL,
            old_values_json LONGTEXT NULL,
            new_values_json LONGTEXT NULL,
            note VARCHAR(500) NULL,
            user_id INT UNSIGNED NULL,
            user_name VARCHAR(160) NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_entity (entity_type,entity_id,created_at),
            INDEX idx_audit_user_date (user_id,created_at),
            INDEX idx_audit_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS app_errors (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_id VARCHAR(40) NOT NULL,
            source VARCHAR(50) NOT NULL DEFAULT 'php',
            level VARCHAR(30) NOT NULL DEFAULT 'error',
            message TEXT NOT NULL,
            file_name VARCHAR(500) NULL,
            line_number INT NULL,
            url VARCHAR(1000) NULL,
            http_method VARCHAR(20) NULL,
            user_id INT UNSIGNED NULL,
            context_json LONGTEXT NULL,
            resolved_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_errors_created (created_at),
            INDEX idx_errors_request (request_id),
            INDEX idx_errors_source (source,level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS system_backups (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            reason VARCHAR(190) NULL,
            size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            checksum_sha256 VARCHAR(64) NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_backups_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(30) PRIMARY KEY,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $typeDefaults = [
            ['1 BM','1-BM',2,2,0,1,2,10], ['2 BM','2-BM',4,2,2,2,4,20], ['3/4 BM','3-4-BM',4,2,2,2,4,30],
            ['4 GB','4-GB',4,4,0,2,4,40], ['4 PM','4-PM',4,2,2,2,4,50], ['5 PM','5-PM',5,3,2,2,5,60],
            ['4 CM','4-CM',4,2,2,2,4,70], ['3/4 SM','3-4-SM',4,2,2,2,4,80], ['3/4 Plus TM','3-4-PLUS-TM',4,2,2,2,4,90],
            ['3/4 TM','3-4-TM',4,2,2,2,4,100]
        ];
        $stmt = $pdo->prepare('INSERT INTO apartment_types(name,code,max_occupancy,default_adults,default_children,bedrooms,beds,sort_order) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)');
        foreach ($typeDefaults as $row) $stmt->execute($row);

        if (self::tableExists('apartments')) {
            $legacyTypes = $pdo->query("SELECT DISTINCT type FROM apartments WHERE (apartment_type_id IS NULL OR apartment_type_id=0) AND type IS NOT NULL AND TRIM(type)<>''")->fetchAll(PDO::FETCH_COLUMN);
            $findByName = $pdo->prepare('SELECT id FROM apartment_types WHERE name=? LIMIT 1');
            $findByCode = $pdo->prepare('SELECT name FROM apartment_types WHERE code=? LIMIT 1');
            $insert = $pdo->prepare('INSERT INTO apartment_types(name,code,max_occupancy,default_adults,bedrooms,beds,sort_order) VALUES(?,?,?,?,?,?,999)');
            foreach ($legacyTypes as $legacy) {
                $name = trim((string)$legacy);
                $findByName->execute([$name]);
                if ($findByName->fetchColumn()) continue;
                $base = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-')) ?: 'LEGACY';
                $base = mb_substr($base, 0, 50);
                $code = $base;
                $suffix = 1;
                while (true) {
                    $findByCode->execute([$code]);
                    $existingName = $findByCode->fetchColumn();
                    if (!$existingName) break;
                    $suffix++;
                    $code = mb_substr($base, 0, 52) . '-' . $suffix;
                }
                $insert->execute([$name,$code,2,2,1,2]);
            }
            $pdo->exec("UPDATE apartments a JOIN apartment_types t ON t.name=a.type SET a.apartment_type_id=t.id WHERE a.apartment_type_id IS NULL");
            $pdo->exec("UPDATE apartments SET apartment_number=code WHERE apartment_number IS NULL OR apartment_number=''");
        }

        $settings = [
            'auto_pre_update_backup' => '1', 'backup_retention_count' => '12', 'session_idle_minutes' => '120',
            'cron_last_run_at' => '', 'smtp_host' => '', 'smtp_port' => '587'
        ];
        $stmt = $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key)');
        foreach ($settings as $key => $value) $stmt->execute([$key,$value]);
    }


    private static function migrate205(PDO $pdo): void
    {
        self::addColumn('apartment_types','default_min_stay',"SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER standard_price");
        self::addColumn('apartments','min_stay_override',"SMALLINT UNSIGNED NULL AFTER price_adjustment_value");
        self::addColumn('guests','preferences',"TEXT NULL AFTER marketing_opt_in");
        self::addColumn('bookings','booking_channel_id',"INT UNSIGNED NULL AFTER source");
        self::addColumn('bookings','special_price_type',"VARCHAR(30) NOT NULL DEFAULT 'none' AFTER price_breakdown_json");
        self::addColumn('bookings','special_price_value',"DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER special_price_type");
        self::addColumn('bookings','special_price_reason',"VARCHAR(255) NULL AFTER special_price_value");
        self::addColumn('bookings','price_locked',"TINYINT(1) NOT NULL DEFAULT 0 AFTER special_price_reason");
        self::addColumn('bookings','min_stay_override',"TINYINT(1) NOT NULL DEFAULT 0 AFTER price_locked");
        self::addColumn('bookings','min_stay_override_reason',"VARCHAR(255) NULL AFTER min_stay_override");

        $pdo->exec("CREATE TABLE IF NOT EXISTS seasons (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL UNIQUE,
            color VARCHAR(20) NOT NULL DEFAULT '#2563eb',
            priority INT NOT NULL DEFAULT 0,
            default_min_stay SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            legacy_multiplier DECIMAL(8,3) NULL,
            notes TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_seasons_active_priority (active,priority,name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS season_periods (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            season_id INT UNSIGNED NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            min_stay SMALLINT UNSIGNED NULL,
            notes VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_period_season FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
            INDEX idx_period_dates (start_date,end_date),
            INDEX idx_period_season (season_id,start_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS season_type_prices (
            season_id INT UNSIGNED NOT NULL,
            apartment_type_id INT UNSIGNED NOT NULL,
            nightly_price DECIMAL(10,2) NULL,
            min_stay SMALLINT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (season_id,apartment_type_id),
            CONSTRAINT fk_stp_season FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
            CONSTRAINT fk_stp_type FOREIGN KEY (apartment_type_id) REFERENCES apartment_types(id) ON DELETE CASCADE,
            INDEX idx_stp_type (apartment_type_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS special_prices (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            scope_type VARCHAR(30) NOT NULL DEFAULT 'all',
            house_id INT UNSIGNED NULL,
            apartment_type_id INT UNSIGNED NULL,
            apartment_id INT UNSIGNED NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            weekdays_json VARCHAR(80) NULL,
            price_mode VARCHAR(30) NOT NULL DEFAULT 'fixed_nightly',
            price_value DECIMAL(10,2) NOT NULL DEFAULT 0,
            min_stay SMALLINT UNSIGNED NULL,
            priority INT NOT NULL DEFAULT 100,
            notes TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_special_house FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
            CONSTRAINT fk_special_type FOREIGN KEY (apartment_type_id) REFERENCES apartment_types(id) ON DELETE CASCADE,
            CONSTRAINT fk_special_apartment FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE,
            INDEX idx_special_dates (active,start_date,end_date,priority),
            INDEX idx_special_scope (scope_type,house_id,apartment_type_id,apartment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS booking_channels (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL UNIQUE,
            code VARCHAR(60) NOT NULL UNIQUE,
            color VARCHAR(20) NOT NULL DEFAULT '#64748b',
            description VARCHAR(255) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_channels_active_sort (active,sort_order,name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::addColumn('csv_profiles','encoding_name',"VARCHAR(40) NOT NULL DEFAULT 'UTF-8' AFTER delimiter_char");
        self::addColumn('csv_profiles','quote_char',"VARCHAR(5) NOT NULL DEFAULT '\"' AFTER encoding_name");
        self::addColumn('csv_profiles','header_row',"SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER quote_char");
        self::addColumn('csv_profiles','skip_rows',"SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER header_row");
        self::addColumn('csv_profiles','thousands_separator',"VARCHAR(5) NOT NULL DEFAULT '.' AFTER decimal_separator");
        self::addColumn('csv_profiles','update_mode',"VARCHAR(30) NOT NULL DEFAULT 'update' AFTER thousands_separator");
        self::addColumn('csv_profiles','value_mappings_json',"LONGTEXT NULL AFTER defaults_json");

        // Bestehende Saisonregeln werden nur ergänzt, niemals gelöscht oder verändert.
        if (self::tableExists('season_rules')) {
            $rows = $pdo->query('SELECT * FROM season_rules ORDER BY id')->fetchAll();
            $findSeason = $pdo->prepare('SELECT id FROM seasons WHERE name=? LIMIT 1');
            $insertSeason = $pdo->prepare('INSERT INTO seasons(name,color,priority,default_min_stay,legacy_multiplier,notes,active) VALUES(?,?,?,?,?,?,1)');
            $periodExists = $pdo->prepare('SELECT COUNT(*) FROM season_periods WHERE season_id=? AND start_date=? AND end_date=?');
            $insertPeriod = $pdo->prepare('INSERT INTO season_periods(season_id,start_date,end_date,min_stay,notes) VALUES(?,?,?,?,?)');
            foreach ($rows as $row) {
                $findSeason->execute([$row['name']]);
                $seasonId = (int)($findSeason->fetchColumn() ?: 0);
                if (!$seasonId) {
                    $insertSeason->execute([$row['name'],'#2563eb',(int)$row['priority'],max(1,(int)$row['min_stay']),(float)$row['multiplier'],'Aus bestehender Saisonregel übernommen']);
                    $seasonId = (int)$pdo->lastInsertId();
                }
                $periodExists->execute([$seasonId,$row['start_date'],$row['end_date']]);
                if (!(int)$periodExists->fetchColumn()) {
                    $insertPeriod->execute([$seasonId,$row['start_date'],$row['end_date'],max(1,(int)$row['min_stay']),'Aus bestehender Saisonregel übernommen']);
                }
            }
        }

        $seasonCount = (int)$pdo->query('SELECT COUNT(*) FROM seasons')->fetchColumn();
        if ($seasonCount === 0) {
            $seedSeason = $pdo->prepare('INSERT INTO seasons(name,color,priority,default_min_stay,notes,active) VALUES(?,?,?,?,?,1)');
            $seedSeason->execute(['Vorsaison','#0ea5e9',10,1,'Zeiträume und Preise bitte festlegen']);
            $seedSeason->execute(['Zwischensaison','#f59e0b',20,1,'Zeiträume und Preise bitte festlegen']);
            $seedSeason->execute(['Hauptsaison','#ef4444',30,1,'Zeiträume und Preise bitte festlegen']);
        }

        $defaults = [
            ['Direkt','DIREKT','#2563eb','Direkte Buchung',10],
            ['Webseite','WEBSEITE','#0f9f6e','Eigene Webseite',20],
            ['Booking.com','BOOKING','#1d4ed8','Booking.com',30],
            ['Airbnb','AIRBNB','#ef4444','Airbnb',40],
            ['AGR','AGR','#7c3aed','AGR / Vermittlung',50],
            ['Passant','PASSANT','#f59e0b','Laufkundschaft',60],
            ['Telefon','TELEFON','#0891b2','Telefonische Buchung',70],
            ['E-Mail','EMAIL','#64748b','Buchung per E-Mail',80],
        ];
        $insertChannel = $pdo->prepare('INSERT INTO booking_channels(name,code,color,description,sort_order) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)');
        foreach ($defaults as $row) $insertChannel->execute($row);

        if (self::tableExists('bookings')) {
            $sources = $pdo->query("SELECT DISTINCT TRIM(source) source FROM bookings WHERE source IS NOT NULL AND TRIM(source)<>''")->fetchAll(PDO::FETCH_COLUMN);
            $findName = $pdo->prepare('SELECT id FROM booking_channels WHERE name=? LIMIT 1');
            $findCode = $pdo->prepare('SELECT COUNT(*) FROM booking_channels WHERE code=?');
            $create = $pdo->prepare('INSERT INTO booking_channels(name,code,color,description,sort_order) VALUES(?,?,?,?,999)');
            foreach ($sources as $source) {
                $source = trim((string)$source);
                $findName->execute([$source]);
                if ($findName->fetchColumn()) continue;
                $base = strtoupper(trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', $source), '-')) ?: 'KANAL';
                $base = substr($base, 0, 50);
                $code = $base;
                $suffix = 1;
                while (true) {
                    $findCode->execute([$code]);
                    if (!(int)$findCode->fetchColumn()) break;
                    $suffix++;
                    $code = substr($base,0,50) . '-' . $suffix;
                }
                $create->execute([$source,$code,'#64748b','Aus vorhandener Buchungsquelle übernommen']);
            }
            $pdo->exec("UPDATE bookings b JOIN booking_channels c ON c.name=b.source SET b.booking_channel_id=c.id WHERE b.booking_channel_id IS NULL");
        }

        $settings = [
            'default_min_stay' => '1',
            'allow_gap_booking_override' => '1',
        ];
        $stmt = $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key)');
        foreach ($settings as $key => $value) $stmt->execute([$key,$value]);
    }

    private static function migrate208(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS housekeeping_teams (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            code VARCHAR(60) NOT NULL UNIQUE,
            phone VARCHAR(80) NULL,
            email VARCHAR(190) NULL,
            whatsapp_number VARCHAR(80) NULL,
            color VARCHAR(20) NOT NULL DEFAULT '#0f9f6e',
            active TINYINT(1) NOT NULL DEFAULT 1,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_housekeeping_teams_active (active,name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS housekeeping_members (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            team_id INT UNSIGNED NULL,
            user_id INT UNSIGNED NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            phone VARCHAR(80) NULL,
            email VARCHAR(190) NULL,
            whatsapp_number VARCHAR(80) NULL,
            receives_whatsapp TINYINT(1) NOT NULL DEFAULT 1,
            receives_email TINYINT(1) NOT NULL DEFAULT 1,
            active TINYINT(1) NOT NULL DEFAULT 1,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_housekeeping_members_team_active (team_id,active,name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS mail_settings (
            id TINYINT UNSIGNED PRIMARY KEY,
            active TINYINT(1) NOT NULL DEFAULT 0,
            host VARCHAR(255) NULL,
            port SMALLINT UNSIGNED NOT NULL DEFAULT 587,
            encryption VARCHAR(20) NOT NULL DEFAULT 'tls',
            username VARCHAR(255) NULL,
            password_encrypted LONGTEXT NULL,
            auth_method VARCHAR(20) NOT NULL DEFAULT 'login',
            from_name VARCHAR(190) NULL,
            from_email VARCHAR(190) NULL,
            reply_to VARCHAR(190) NULL,
            timeout_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 15,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS communication_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            channel VARCHAR(30) NOT NULL,
            entity_type VARCHAR(60) NOT NULL,
            entity_id VARCHAR(80) NULL,
            recipient_name VARCHAR(160) NULL,
            recipient_address VARCHAR(255) NULL,
            subject VARCHAR(255) NULL,
            message_excerpt VARCHAR(1500) NULL,
            message_body LONGTEXT NULL,
            message_sha256 VARCHAR(64) NULL,
            status VARCHAR(40) NOT NULL,
            detail VARCHAR(1000) NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_communication_entity (entity_type,entity_id,created_at),
            INDEX idx_communication_channel_status (channel,status,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::addColumn('communication_log','message_body',"LONGTEXT NULL AFTER message_excerpt");

        self::addColumn('housekeeping_tasks','team_id',"INT UNSIGNED NULL AFTER assigned_to");
        self::addColumn('housekeeping_tasks','member_id',"INT UNSIGNED NULL AFTER team_id");
        self::addColumn('housekeeping_tasks','actual_minutes',"SMALLINT UNSIGNED NULL AFTER estimated_minutes");
        self::addColumn('housekeeping_tasks','checklist_done_json',"LONGTEXT NULL AFTER checklist_json");
        self::addColumn('housekeeping_tasks','completion_notes',"TEXT NULL AFTER notes");
        self::addColumn('housekeeping_tasks','completed_at',"DATETIME NULL AFTER completion_notes");
        self::addColumn('housekeeping_tasks','whatsapp_status',"VARCHAR(30) NOT NULL DEFAULT 'not_prepared' AFTER completed_at");
        self::addColumn('housekeeping_tasks','whatsapp_opened_at',"DATETIME NULL AFTER whatsapp_status");
        self::addColumn('housekeeping_tasks','whatsapp_opened_by',"INT UNSIGNED NULL AFTER whatsapp_opened_at");
        self::addColumn('housekeeping_tasks','email_status',"VARCHAR(30) NOT NULL DEFAULT 'not_sent' AFTER whatsapp_opened_by");
        self::addColumn('housekeeping_tasks','email_sent_at',"DATETIME NULL AFTER email_status");
        self::addIndex('housekeeping_tasks','idx_tasks_team_date','team_id,task_date');
        self::addIndex('housekeeping_tasks','idx_tasks_member_date','member_id,task_date');

        $defaults = [
            'housekeeping_guest_name_mode' => 'initials',
            'housekeeping_show_booking_reference' => '0',
            'housekeeping_show_guest_request' => '0',
        ];
        $stmt = $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key)');
        foreach ($defaults as $key => $value) $stmt->execute([$key,$value]);

        // Bestehende freie Zuständigkeitsnamen werden nicht überschrieben. Soweit eindeutig,
        // werden sie als Team/Mitarbeiter übernommen und den Aufgaben zugeordnet.
        if (self::tableExists('housekeeping_tasks')) {
            $names = $pdo->query("SELECT DISTINCT TRIM(assigned_to) name FROM housekeeping_tasks WHERE assigned_to IS NOT NULL AND TRIM(assigned_to)<>''")->fetchAll(PDO::FETCH_COLUMN);
            $find = $pdo->prepare('SELECT id FROM housekeeping_members WHERE name=? LIMIT 1');
            $insert = $pdo->prepare('INSERT INTO housekeeping_members(name,active,notes) VALUES(?,1,?)');
            $assign = $pdo->prepare('UPDATE housekeeping_tasks SET member_id=? WHERE member_id IS NULL AND assigned_to=?');
            foreach ($names as $name) {
                $name = trim((string)$name);
                $find->execute([$name]);
                $memberId = (int)($find->fetchColumn() ?: 0);
                if (!$memberId) {
                    $insert->execute([$name,'Aus bisherigem Freitextfeld „Zuständig“ übernommen']);
                    $memberId = (int)$pdo->lastInsertId();
                }
                $assign->execute([$memberId,$name]);
            }
        }
    }


    private static function migrate209(PDO $pdo): void
    {
        foreach ([
            'can_assign' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER receives_email",
            'can_reassign' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER can_assign",
            'can_inspect' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER can_reassign",
            'can_mark_ready' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER can_inspect",
            'can_report_incident' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER can_mark_ready",
            'can_upload_photos' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER can_report_incident",
            'preferred_language' => "VARCHAR(5) NOT NULL DEFAULT 'de' AFTER can_upload_photos",
        ] as $column => $definition) self::addColumn('housekeeping_members',$column,$definition);

        foreach ([
            'origin_type' => "VARCHAR(40) NOT NULL DEFAULT 'manual' AFTER status",
            'due_time' => "TIME NULL AFTER origin_type",
            'assigned_by' => "INT UNSIGNED NULL AFTER member_id",
            'accepted_at' => "DATETIME NULL AFTER completion_notes",
            'started_at' => "DATETIME NULL AFTER accepted_at",
            'cleaning_completed_at' => "DATETIME NULL AFTER started_at",
            'cleaning_completed_by' => "INT UNSIGNED NULL AFTER cleaning_completed_at",
            'inspected_at' => "DATETIME NULL AFTER cleaning_completed_by",
            'inspected_by' => "INT UNSIGNED NULL AFTER inspected_at",
            'inspection_result' => "VARCHAR(40) NULL AFTER inspected_by",
            'ready_reported_at' => "DATETIME NULL AFTER inspection_result",
            'ready_reported_by' => "INT UNSIGNED NULL AFTER ready_reported_at",
            'released_at' => "DATETIME NULL AFTER ready_reported_by",
            'released_by' => "INT UNSIGNED NULL AFTER released_at",
            'release_note' => "TEXT NULL AFTER released_by",
            'release_blocked' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER release_note",
        ] as $column => $definition) self::addColumn('housekeeping_tasks',$column,$definition);

        $pdo->exec("CREATE TABLE IF NOT EXISTS housekeeping_incidents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            task_id INT UNSIGNED NOT NULL,
            apartment_id INT UNSIGNED NOT NULL,
            category VARCHAR(50) NOT NULL,
            severity VARCHAR(20) NOT NULL DEFAULT 'normal',
            description TEXT NOT NULL,
            apartment_usable TINYINT(1) NOT NULL DEFAULT 1,
            photos_json LONGTEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            reported_by INT UNSIGNED NULL,
            reviewed_by INT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            resolution_note TEXT NULL,
            resolved_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_incident_task_status (task_id,status),
            INDEX idx_incident_apartment_status (apartment_id,status,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            type VARCHAR(60) NOT NULL,
            title VARCHAR(190) NOT NULL,
            message VARCHAR(1000) NULL,
            entity_type VARCHAR(60) NULL,
            entity_id VARCHAR(80) NULL,
            target_url VARCHAR(500) NULL,
            read_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notifications_user_read (user_id,read_at,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS guest_portal_access (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id INT UNSIGNED NOT NULL UNIQUE,
            token_hash CHAR(64) NOT NULL UNIQUE,
            token_encrypted LONGTEXT NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            valid_from DATETIME NULL,
            valid_until DATETIME NULL,
            released_at DATETIME NULL,
            released_by INT UNSIGNED NULL,
            message_de TEXT NULL,
            message_es TEXT NULL,
            message_en TEXT NULL,
            last_viewed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_guest_portal_validity (active,valid_from,valid_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS guest_portal_contents (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            scope_type VARCHAR(30) NOT NULL DEFAULT 'global',
            scope_id INT UNSIGNED NULL,
            language VARCHAR(5) NOT NULL DEFAULT 'de',
            title VARCHAR(190) NOT NULL,
            body TEXT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_guest_content_scope (scope_type,scope_id,language,active,sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::addIndex('housekeeping_tasks','idx_tasks_workflow','status,task_date,due_time');
        self::addIndex('housekeeping_tasks','idx_tasks_release','status,released_at');

        // Alte Werte werden nur in die neue Statuskette überführt; erledigte Daten bleiben erhalten.
        $pdo->exec("UPDATE housekeeping_tasks SET status='assigned' WHERE status='planned'");
        $pdo->exec("UPDATE housekeeping_tasks SET status='cleaning_done',cleaning_completed_at=COALESCE(cleaning_completed_at,completed_at) WHERE status='done'");
        $pdo->exec("UPDATE housekeeping_tasks SET origin_type='booking_departure' WHERE booking_id IS NOT NULL AND (origin_type IS NULL OR origin_type='manual')");

        $defaults = [
            'housekeeping_poll_seconds' => '20',
            'guest_portal_poll_seconds' => '20',
            'guest_portal_waiting_de' => 'Ihre Wohnung wird derzeit vorbereitet. Bitte prüfen Sie den Status später erneut.',
            'guest_portal_ready_de' => 'Ihre Wohnung ist jetzt bezugsbereit. Sie können den Schlüssel an der Rezeption abholen.',
            'guest_portal_waiting_es' => 'Su alojamiento se está preparando. Vuelva a consultar el estado en unos instantes.',
            'guest_portal_ready_es' => 'Su alojamiento ya está listo. Puede recoger la llave en recepción.',
        ];
        $stmt = $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key)');
        foreach ($defaults as $key => $value) $stmt->execute([$key,$value]);
    }


    private static function migrate210(PDO $pdo): void
    {
        $defaults = [
            'guest_public_display_hours' => '12',
            'guest_public_title_de' => 'Bezugsbereite Wohnungen',
            'guest_public_title_es' => 'Apartamentos listos',
            'guest_public_title_en' => 'Apartments ready',
            'guest_public_empty_de' => 'Zurzeit wurde noch keine Wohnung für die Schlüsselabholung freigegeben.',
            'guest_public_empty_es' => 'Actualmente no hay ningún apartamento liberado para recoger la llave.',
            'guest_public_empty_en' => 'No apartment has currently been released for key collection.',
            'guest_public_show_house' => '1',
        ];
        $stmt = $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key)');
        foreach ($defaults as $key => $value) $stmt->execute([$key,$value]);

        // Doppelte automatische Abreiseaufträge aus älteren Versionen sicher bereinigen.
        // Erhalten bleibt der fachlich am weitesten fortgeschrittene Datensatz. Nur noch
        // unberührte offene/zugewiesene Dubletten werden storniert; bearbeitete Aufgaben
        // werden niemals automatisch gelöscht oder zurückgesetzt.
        if (self::tableExists('housekeeping_tasks')) {
            $groups = $pdo->query("SELECT booking_id FROM housekeeping_tasks
                WHERE booking_id IS NOT NULL AND task_type='turnover' AND origin_type='booking_departure'
                GROUP BY booking_id HAVING COUNT(*)>1")->fetchAll(PDO::FETCH_COLUMN);
            $findTasks = $pdo->prepare("SELECT id,status FROM housekeeping_tasks
                WHERE booking_id=? AND task_type='turnover' AND origin_type='booking_departure'
                ORDER BY FIELD(status,'released','ready_reported','inspection_passed','cleaning_done','inspection_required','rework_required','in_progress','accepted','assigned','open','blocked','cancelled'),id");
            $cancel = $pdo->prepare("UPDATE housekeeping_tasks
                SET status='cancelled',notes=CONCAT(COALESCE(notes,''),CASE WHEN COALESCE(notes,'')='' THEN '' ELSE '\n' END,?)
                WHERE id=? AND status IN ('open','assigned','cancelled')");
            foreach ($groups as $bookingId) {
                $findTasks->execute([(int)$bookingId]);
                $tasks = $findTasks->fetchAll();
                $keep = $tasks[0] ?? null;
                if (!$keep) continue;
                foreach (array_slice($tasks, 1) as $duplicate) {
                    if (!in_array((string)$duplicate['status'], ['open','assigned','cancelled'], true)) continue;
                    $cancel->execute(['Als doppelt erkannter automatischer Abreiseauftrag storniert; Originalauftrag #'.(int)$keep['id'].' bleibt erhalten.', (int)$duplicate['id']]);
                }
            }
        }

        // Fehlende eindeutige Zuordnungen werden nicht erfunden. Bestehende Verknüpfungen bleiben unverändert.
        // Die kombinierte Mitarbeiteranlage übernimmt neue und künftig bearbeitete Konten atomar.
    }

    private static function migrate2100(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS document_sequences (
            document_type VARCHAR(30) NOT NULL,
            document_year SMALLINT UNSIGNED NOT NULL,
            current_value INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(document_type,document_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS offers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            offer_number VARCHAR(80) NOT NULL UNIQUE,
            parent_offer_id BIGINT UNSIGNED NULL,
            revision_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            guest_id INT UNSIGNED NULL,
            guest_name VARCHAR(240) NOT NULL,
            guest_email VARCHAR(190) NULL,
            guest_phone VARCHAR(80) NULL,
            guest_address VARCHAR(500) NULL,
            language VARCHAR(5) NOT NULL DEFAULT 'de',
            apartment_type_id INT UNSIGNED NULL,
            apartment_id INT UNSIGNED NULL,
            arrival DATE NOT NULL,
            departure DATE NOT NULL,
            adults SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            children SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            babies SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            pets SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            valid_until DATE NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'EUR',
            subtotal_net DECIMAL(12,2) NOT NULL DEFAULT 0,
            vat_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
            vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            tourist_tax DECIMAL(12,2) NOT NULL DEFAULT 0,
            discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            deposit_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
            deposit_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            deposit_due_date DATE NULL,
            remaining_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            personal_message TEXT NULL,
            internal_notes TEXT NULL,
            email_subject VARCHAR(255) NULL,
            document_options_json LONGTEXT NULL,
            document_snapshot_json LONGTEXT NULL,
            calculation_input_json LONGTEXT NOT NULL,
            price_snapshot_json LONGTEXT NOT NULL,
            public_token_hash CHAR(64) NOT NULL UNIQUE,
            public_token_encrypted LONGTEXT NOT NULL,
            sent_at DATETIME NULL,
            viewed_at DATETIME NULL,
            accepted_at DATETIME NULL,
            declined_at DATETIME NULL,
            converted_at DATETIME NULL,
            archived_at DATETIME NULL,
            booking_id INT UNSIGNED NULL UNIQUE,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_offers_status_date(status,created_at),
            INDEX idx_offers_guest(guest_id,created_at),
            INDEX idx_offers_stay(arrival,departure),
            INDEX idx_offers_type(apartment_type_id,apartment_id),
            INDEX idx_offers_parent(parent_offer_id,revision_number),
            CONSTRAINT fk_offers_guest FOREIGN KEY(guest_id) REFERENCES guests(id) ON DELETE SET NULL,
            CONSTRAINT fk_offers_type FOREIGN KEY(apartment_type_id) REFERENCES apartment_types(id) ON DELETE SET NULL,
            CONSTRAINT fk_offers_apartment FOREIGN KEY(apartment_id) REFERENCES apartments(id) ON DELETE SET NULL,
            CONSTRAINT fk_offers_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
            CONSTRAINT fk_offers_created FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_offers_updated FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::addColumn('offers','deposit_due_date',"DATE NULL AFTER deposit_amount");
        self::addColumn('offers','email_subject',"VARCHAR(255) NULL AFTER internal_notes");
        self::addColumn('offers','document_options_json',"LONGTEXT NULL AFTER email_subject");
        self::addColumn('offers','document_snapshot_json',"LONGTEXT NULL AFTER document_options_json");
        self::addColumn('offers','calculation_input_json',"LONGTEXT NULL AFTER document_snapshot_json");

        $pdo->exec("CREATE TABLE IF NOT EXISTS offer_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            offer_id BIGINT UNSIGNED NOT NULL,
            item_type VARCHAR(40) NOT NULL,
            description VARCHAR(255) NOT NULL,
            quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
            unit VARCHAR(50) NOT NULL,
            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            vat_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            source_type VARCHAR(50) NULL,
            source_id BIGINT UNSIGNED NULL,
            metadata_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_offer_items_offer FOREIGN KEY(offer_id) REFERENCES offers(id) ON DELETE CASCADE,
            INDEX idx_offer_items_offer(offer_id,sort_order,id),
            INDEX idx_offer_items_source(source_type,source_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS offer_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            offer_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(60) NOT NULL,
            note VARCHAR(255) NULL,
            old_values_json LONGTEXT NULL,
            new_values_json LONGTEXT NULL,
            user_id INT UNSIGNED NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_offer_events_offer FOREIGN KEY(offer_id) REFERENCES offers(id) ON DELETE CASCADE,
            CONSTRAINT fk_offer_events_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_offer_events_offer(offer_id,created_at),
            INDEX idx_offer_events_type(event_type,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS offer_services (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(40) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            description VARCHAR(500) NULL,
            unit_mode VARCHAR(30) NOT NULL DEFAULT 'once',
            default_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            vat_rate DECIMAL(6,2) NOT NULL DEFAULT 10,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_offer_services_active(active,sort_order,name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS offer_service_translations (
            service_id INT UNSIGNED NOT NULL,
            language VARCHAR(5) NOT NULL,
            name VARCHAR(160) NULL,
            description VARCHAR(500) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(service_id,language),
            CONSTRAINT fk_offer_service_trans_service FOREIGN KEY(service_id) REFERENCES offer_services(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS offer_apartment_type_translations (
            apartment_type_id INT UNSIGNED NOT NULL,
            language VARCHAR(5) NOT NULL,
            name VARCHAR(160) NULL,
            description TEXT NULL,
            amenities_html LONGTEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(apartment_type_id,language),
            CONSTRAINT fk_offer_type_trans_type FOREIGN KEY(apartment_type_id) REFERENCES apartment_types(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS offer_text_templates (
            language VARCHAR(5) PRIMARY KEY,
            email_subject VARCHAR(255) NULL,
            intro TEXT NULL,
            validity TEXT NULL,
            closing TEXT NULL,
            footer TEXT NULL,
            terms LONGTEXT NULL,
            payment_info LONGTEXT NULL,
            additional_label VARCHAR(160) NULL,
            additional_info LONGTEXT NULL,
            remaining_payment TEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::addColumn('offer_text_templates','email_subject',"VARCHAR(255) NULL AFTER language");
        self::addColumn('offer_text_templates','payment_info',"LONGTEXT NULL AFTER terms");
        self::addColumn('offer_text_templates','additional_label',"VARCHAR(160) NULL AFTER payment_info");
        self::addColumn('offer_text_templates','additional_info',"LONGTEXT NULL AFTER additional_label");
        self::addColumn('offer_text_templates','remaining_payment',"TEXT NULL AFTER additional_info");

        $pdo->exec("CREATE TABLE IF NOT EXISTS offer_content_blocks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(60) NOT NULL UNIQUE,
            internal_name VARCHAR(190) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            show_by_default TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_offer_content_blocks_active(active,sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS offer_content_block_translations (
            block_id INT UNSIGNED NOT NULL,
            language VARCHAR(5) NOT NULL,
            title VARCHAR(190) NULL,
            content_html LONGTEXT NULL,
            PRIMARY KEY(block_id,language),
            CONSTRAINT fk_offer_content_block_trans_block FOREIGN KEY(block_id) REFERENCES offer_content_blocks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $defaults = [
            'offer_validity_days'=>'7','offer_deposit_percent'=>'30','offer_tourist_tax_per_person_night'=>'0',
            'offer_tourist_tax_max_nights'=>'7','offer_tourist_tax_adults_only'=>'1','offer_vat_rate'=>'10',
            'offer_prices_include_vat'=>'1','offer_number_prefix'=>'ANG','offer_currency'=>'EUR',
            'offer_logo_url'=>'','offer_bank_account_holder'=>'','offer_bank_iban'=>'','offer_bank_bic'=>'',
            'offer_bank_name'=>'','offer_company_extra'=>'','offer_company_website'=>'',
        ];
        $set=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key)');
        foreach($defaults as $key=>$value)$set->execute([$key,$value]);

        $templates = [
            'de'=>['Ihr Angebot {number}','Vielen Dank für Ihr Interesse. Gerne unterbreiten wir Ihnen folgendes Angebot.','Dieses Angebot ist bis {date} gültig.','Wir freuen uns, von Ihnen zu hören.','Diese Nachricht wurde mit StayPilot erstellt.','Es gelten die im Angebot aufgeführten Leistungen und Bedingungen.','Bitte verwenden Sie für Zahlungen die angegebenen Bankdaten und nennen Sie die Angebotsnummer als Verwendungszweck.','Zusätzliche Informationen','','Der Restbetrag von {amount} ist gemäß den vereinbarten Zahlungsbedingungen fällig.'],
            'en'=>['Your offer {number}','Thank you for your interest. We are pleased to submit the following offer.','This offer is valid until {date}.','We look forward to hearing from you.','This message was created with StayPilot.','The services and conditions stated in this offer apply.','Please use the bank details provided for payments and quote the offer number as the payment reference.','Additional information','','The remaining amount of {amount} is due in accordance with the agreed payment terms.'],
            'es'=>['Su oferta {number}','Gracias por su interés. Nos complace presentarle la siguiente oferta.','Esta oferta es válida hasta el {date}.','Esperamos tener noticias suyas.','Este mensaje ha sido creado con StayPilot.','Se aplican los servicios y condiciones indicados en esta oferta.','Para los pagos, utilice los datos bancarios indicados y señale el número de oferta como concepto.','Información adicional','','El importe restante de {amount} vence según las condiciones de pago acordadas.'],
            'pt'=>['A sua oferta {number}','Obrigado pelo seu interesse. Temos o prazer de apresentar a seguinte oferta.','Esta oferta é válida até {date}.','Aguardamos o seu contacto.','Esta mensagem foi criada com StayPilot.','Aplicam-se os serviços e condições indicados nesta oferta.','Para pagamentos, utilize os dados bancários indicados e mencione o número da oferta como referência.','Informações adicionais','','O valor restante de {amount} vence de acordo com as condições de pagamento acordadas.'],
            'fr'=>['Votre offre {number}','Merci de votre intérêt. Nous avons le plaisir de vous présenter l’offre suivante.','Cette offre est valable jusqu’au {date}.','Nous nous réjouissons de votre réponse.','Ce message a été créé avec StayPilot.','Les prestations et conditions indiquées dans cette offre s’appliquent.','Pour les paiements, veuillez utiliser les coordonnées bancaires indiquées et mentionner le numéro de l’offre.','Informations complémentaires','','Le solde de {amount} est dû selon les conditions de paiement convenues.'],
            'it'=>['La Sua offerta {number}','Grazie per il Suo interesse. Siamo lieti di presentarLe la seguente offerta.','Questa offerta è valida fino al {date}.','Restiamo in attesa di un Suo riscontro.','Questo messaggio è stato creato con StayPilot.','Si applicano i servizi e le condizioni indicati nella presente offerta.','Per i pagamenti utilizzi i dati bancari indicati e riporti il numero dell’offerta come causale.','Informazioni aggiuntive','','L’importo residuo di {amount} è dovuto secondo le condizioni di pagamento concordate.'],
            'ca'=>['La seva oferta {number}','Gràcies pel seu interès. Ens complau presentar-li l’oferta següent.','Aquesta oferta és vàlida fins al {date}.','Esperem la seva resposta.','Aquest missatge s’ha creat amb StayPilot.','S’apliquen els serveis i les condicions indicats en aquesta oferta.','Per als pagaments, utilitzi les dades bancàries indicades i faci constar el número de l’oferta.','Informació addicional','','L’import restant de {amount} venç segons les condicions de pagament acordades.'],
        ];
        $tpl=$pdo->prepare('INSERT INTO offer_text_templates(language,email_subject,intro,validity,closing,footer,terms,payment_info,additional_label,additional_info,remaining_payment) VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE language=VALUES(language)');
        $fill=$pdo->prepare("UPDATE offer_text_templates SET email_subject=COALESCE(NULLIF(email_subject,''),?),intro=COALESCE(NULLIF(intro,''),?),validity=COALESCE(NULLIF(validity,''),?),closing=COALESCE(NULLIF(closing,''),?),footer=COALESCE(NULLIF(footer,''),?),terms=COALESCE(NULLIF(terms,''),?),payment_info=COALESCE(NULLIF(payment_info,''),?),additional_label=COALESCE(NULLIF(additional_label,''),?),remaining_payment=COALESCE(NULLIF(remaining_payment,''),?) WHERE language=?");
        foreach($templates as $lang=>$row){
            $tpl->execute([$lang,...$row]);
            $fill->execute([$row[0],$row[1],$row[2],$row[3],$row[4],$row[5],$row[6],$row[7],$row[9],$lang]);
        }
    }

    /**
     * Prüft die für V2.1.0 zwingend benötigten Tabellen und Spalten.
     * Dadurch wird eine unvollständig ausgeführte Migration auch dann repariert,
     * wenn settings.schema_version bereits versehentlich auf den Zielstand gesetzt wurde.
     */
    private static function migrate211(PDO $pdo): void
    {
        $flag='offer_catalog_v211_imported';
        $check=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1');
        $check->execute([$flag]);
        if((string)($check->fetchColumn()?:'')==='1')return;

        // Frühere Fassung importierte hier automatisch database/offer_catalog_v211.json –
        // das sind die echten Stammdaten (Wohnungstypen, Saisons, Preise) einer anderen,
        // konkreten Ferienanlage des Entwicklers. Für jede neue Installation war das
        // fremdes Beispieldatenmaterial statt echter Testdaten. Der automatische Import
        // ist deshalb entfernt; die Migration bleibt nur als Kompatibilitäts-Marker
        // bestehen, damit bestehende Installationen ihren Schema-Stand behalten.
        $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$flag,'1']);
    }


    /**
     * V2.1.2 – zentrale Wohnungstyp-Inhalte, öffentliche Typanfragen,
     * Ausstattungs-Icons, Bildverwaltung, Kapazitätsausnahmen und Stornoregeln.
     * Alle Änderungen sind additiv; bestehende Buchungen und Apartments bleiben unverändert.
     */
    private static function migrate212(PDO $pdo): void
    {
        $hadStandardOccupancy = self::tableExists('apartment_types') && self::columnExists('apartment_types','standard_occupancy');
        self::addColumn('apartment_types','standard_occupancy',"SMALLINT UNSIGNED NOT NULL DEFAULT 2 AFTER max_occupancy");
        self::addColumn('apartment_types','allow_capacity_override',"TINYINT(1) NOT NULL DEFAULT 1 AFTER standard_occupancy");
        self::addColumn('apartment_types','public_active',"TINYINT(1) NOT NULL DEFAULT 1 AFTER allow_capacity_override");
        self::addColumn('apartment_types','cancel_free_until_days',"SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER discounts_json");
        self::addColumn('apartment_types','cancel_tier1_from_days',"SMALLINT UNSIGNED NOT NULL DEFAULT 14 AFTER cancel_free_until_days");
        self::addColumn('apartment_types','cancel_tier1_percent',"DECIMAL(5,2) NOT NULL DEFAULT 30 AFTER cancel_tier1_from_days");
        self::addColumn('apartment_types','cancel_tier2_from_days',"SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER cancel_tier1_percent");
        self::addColumn('apartment_types','cancel_tier2_percent',"DECIMAL(5,2) NOT NULL DEFAULT 80 AFTER cancel_tier2_from_days");
        self::addColumn('apartment_types','cancel_no_show_percent',"DECIMAL(5,2) NOT NULL DEFAULT 100 AFTER cancel_tier2_percent");

        // Beim erstmaligen Ergänzen der Spalte wird die vorhandene Standardbelegung
        // einmalig aus den bisherigen Erwachsenen-/Kinderwerten abgeleitet. Bei
        // späteren idempotenten Läufen bleiben manuell gepflegte Werte unangetastet.
        if (self::tableExists('apartment_types')) {
            if (!$hadStandardOccupancy) {
                $pdo->exec("UPDATE apartment_types SET standard_occupancy=LEAST(GREATEST(1,max_occupancy),GREATEST(1,default_adults+default_children))");
            } else {
                $pdo->exec("UPDATE apartment_types SET standard_occupancy=LEAST(GREATEST(1,max_occupancy),GREATEST(1,standard_occupancy)) WHERE standard_occupancy=0 OR standard_occupancy>max_occupancy");
            }
        }

        self::addColumn('offer_apartment_type_translations','public_description_html',"LONGTEXT NULL AFTER description");
        self::addColumn('offer_apartment_type_translations','seo_title',"VARCHAR(190) NULL AFTER amenities_html");
        self::addColumn('offer_apartment_type_translations','seo_description',"VARCHAR(320) NULL AFTER seo_title");
        self::addColumn('offer_apartment_type_translations','request_hint',"VARCHAR(500) NULL AFTER seo_description");
        self::addColumn('offer_apartment_type_translations','image_alt',"VARCHAR(255) NULL AFTER request_hint");
        if (self::tableExists('offer_apartment_type_translations')) {
            $pdo->exec("UPDATE offer_apartment_type_translations SET public_description_html=description WHERE (public_description_html IS NULL OR public_description_html='') AND description IS NOT NULL");
        }

        self::addColumn('bookings','apartment_type_id',"INT UNSIGNED NULL AFTER apartment_id");
        self::addColumn('bookings','capacity_override',"TINYINT(1) NOT NULL DEFAULT 0 AFTER pets");
        self::addColumn('bookings','capacity_override_reason',"VARCHAR(500) NULL AFTER capacity_override");
        self::addColumn('bookings','child_ages_json',"LONGTEXT NULL AFTER capacity_override_reason");
        self::addColumn('bookings','public_language',"VARCHAR(5) NULL AFTER source");
        self::addColumn('bookings','cancellation_snapshot_json',"LONGTEXT NULL AFTER price_breakdown_json");
        self::addColumn('bookings','cancellation_fee_percent',"DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER cancellation_snapshot_json");
        self::addColumn('bookings','cancellation_fee_amount',"DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER cancellation_fee_percent");
        self::addColumn('bookings','cancelled_at',"DATETIME NULL AFTER cancellation_fee_amount");
        self::addIndex('bookings','idx_bookings_type_stay','apartment_type_id,arrival,departure,status');
        if (self::tableExists('bookings') && self::columnExists('bookings','apartment_type_id')) {
            $pdo->exec("UPDATE bookings b JOIN apartments a ON a.id=b.apartment_id SET b.apartment_type_id=a.apartment_type_id WHERE b.apartment_type_id IS NULL AND a.apartment_type_id IS NOT NULL");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS amenity_catalog (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(60) NOT NULL UNIQUE,
            icon VARCHAR(40) NOT NULL DEFAULT '✓',
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_amenity_active_sort(active,sort_order,code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS amenity_translations (
            amenity_id INT UNSIGNED NOT NULL,
            language VARCHAR(5) NOT NULL,
            label VARCHAR(160) NOT NULL,
            PRIMARY KEY(amenity_id,language),
            CONSTRAINT fk_amenity_translation_catalog FOREIGN KEY(amenity_id) REFERENCES amenity_catalog(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS apartment_type_amenities (
            apartment_type_id INT UNSIGNED NOT NULL,
            amenity_id INT UNSIGNED NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY(apartment_type_id,amenity_id),
            CONSTRAINT fk_type_amenity_type FOREIGN KEY(apartment_type_id) REFERENCES apartment_types(id) ON DELETE CASCADE,
            CONSTRAINT fk_type_amenity_catalog FOREIGN KEY(amenity_id) REFERENCES amenity_catalog(id) ON DELETE CASCADE,
            INDEX idx_type_amenity_sort(apartment_type_id,sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS apartment_type_images (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            apartment_type_id INT UNSIGNED NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            thumb_path VARCHAR(500) NULL,
            seo_filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NULL,
            mime_type VARCHAR(80) NOT NULL,
            width INT UNSIGNED NOT NULL DEFAULT 0,
            height INT UNSIGNED NOT NULL DEFAULT 0,
            size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            alt_text_json LONGTEXT NULL,
            is_cover TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_type_image_type FOREIGN KEY(apartment_type_id) REFERENCES apartment_types(id) ON DELETE CASCADE,
            CONSTRAINT fk_type_image_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_type_images_type_cover(apartment_type_id,is_cover,sort_order,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $amenities = [
            ['wifi','📶',['de'=>'WLAN','en'=>'Wi-Fi','es'=>'Wi-Fi','pt'=>'Wi-Fi','fr'=>'Wi-Fi','it'=>'Wi-Fi','ca'=>'Wi-Fi']],
            ['pool','🏊',['de'=>'Pool','en'=>'Pool','es'=>'Piscina','pt'=>'Piscina','fr'=>'Piscine','it'=>'Piscina','ca'=>'Piscina']],
            ['parking','🅿️',['de'=>'Parkplatz','en'=>'Parking','es'=>'Aparcamiento','pt'=>'Estacionamento','fr'=>'Parking','it'=>'Parcheggio','ca'=>'Aparcament']],
            ['air_conditioning','❄️',['de'=>'Klimaanlage','en'=>'Air conditioning','es'=>'Aire acondicionado','pt'=>'Ar condicionado','fr'=>'Climatisation','it'=>'Aria condizionata','ca'=>'Aire condicionat']],
            ['balcony','🌅',['de'=>'Balkon','en'=>'Balcony','es'=>'Balcón','pt'=>'Varanda','fr'=>'Balcon','it'=>'Balcone','ca'=>'Balcó']],
            ['terrace','🌿',['de'=>'Terrasse','en'=>'Terrace','es'=>'Terraza','pt'=>'Terraço','fr'=>'Terrasse','it'=>'Terrazza','ca'=>'Terrassa']],
            ['sea_view','🌊',['de'=>'Meerblick','en'=>'Sea view','es'=>'Vistas al mar','pt'=>'Vista para o mar','fr'=>'Vue mer','it'=>'Vista mare','ca'=>'Vistes al mar']],
            ['kitchen','🍳',['de'=>'Küche','en'=>'Kitchen','es'=>'Cocina','pt'=>'Cozinha','fr'=>'Cuisine','it'=>'Cucina','ca'=>'Cuina']],
            ['washing_machine','🧺',['de'=>'Waschmaschine','en'=>'Washing machine','es'=>'Lavadora','pt'=>'Máquina de lavar','fr'=>'Lave-linge','it'=>'Lavatrice','ca'=>'Rentadora']],
            ['dishwasher','🍽️',['de'=>'Spülmaschine','en'=>'Dishwasher','es'=>'Lavavajillas','pt'=>'Máquina de lavar louça','fr'=>'Lave-vaisselle','it'=>'Lavastoviglie','ca'=>'Rentaplats']],
            ['television','📺',['de'=>'Fernseher','en'=>'Television','es'=>'Televisión','pt'=>'Televisão','fr'=>'Télévision','it'=>'Televisore','ca'=>'Televisió']],
            ['pets','🐾',['de'=>'Haustiere möglich','en'=>'Pets allowed','es'=>'Se admiten mascotas','pt'=>'Animais permitidos','fr'=>'Animaux admis','it'=>'Animali ammessi','ca'=>'S\'admeten mascotes']],
            ['elevator','🛗',['de'=>'Aufzug','en'=>'Lift','es'=>'Ascensor','pt'=>'Elevador','fr'=>'Ascenseur','it'=>'Ascensore','ca'=>'Ascensor']],
            ['accessible','♿',['de'=>'Barrierearm','en'=>'Accessible','es'=>'Accesible','pt'=>'Acessível','fr'=>'Accessible','it'=>'Accessibile','ca'=>'Accessible']],
            ['safe','🔐',['de'=>'Safe','en'=>'Safe','es'=>'Caja fuerte','pt'=>'Cofre','fr'=>'Coffre-fort','it'=>'Cassaforte','ca'=>'Caixa forta']],
            ['baby_cot','👶',['de'=>'Babybett','en'=>'Baby cot','es'=>'Cuna','pt'=>'Berço','fr'=>'Lit bébé','it'=>'Culla','ca'=>'Bressol']],
        ];
        $find=$pdo->prepare('SELECT id FROM amenity_catalog WHERE code=? LIMIT 1');
        $insert=$pdo->prepare('INSERT INTO amenity_catalog(code,icon,active,sort_order) VALUES(?,?,1,?)');
        $update=$pdo->prepare('UPDATE amenity_catalog SET icon=?,active=1,sort_order=? WHERE id=?');
        $translation=$pdo->prepare('INSERT INTO amenity_translations(amenity_id,language,label) VALUES(?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label)');
        foreach($amenities as $index=>$amenity){
            [$code,$icon,$labels]=$amenity;$find->execute([$code]);$id=(int)($find->fetchColumn()?:0);
            if($id)$update->execute([$icon,($index+1)*10,$id]);
            else{$insert->execute([$code,$icon,($index+1)*10]);$id=(int)$pdo->lastInsertId();}
            foreach($labels as $language=>$label)$translation->execute([$id,$language,$label]);
        }

        $defaults=[
            'offer_tourist_tax_min_age'=>'16',
            'public_booking_default_language'=>'de',
            'public_booking_languages'=>json_encode(['de','en','es','fr','it','pt','ca'],JSON_UNESCAPED_UNICODE),
            'public_booking_show_prices'=>'1',
        ];
        $stmt=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=setting_value');
        foreach($defaults as $key=>$value)$stmt->execute([$key,$value]);
    }


    /**
     * V2.1.3 – bearbeitbare E-Mail-Kommunikation und visueller Editor
     * für die öffentliche Angebotsseite. Bestehende Preis- und Buchungsdaten
     * bleiben unverändert; neue Inhalte werden ausschließlich additiv gespeichert.
     */
    private static function migrate213(PDO $pdo): void
    {
        self::addColumn('offers','email_content_json',"LONGTEXT NULL AFTER email_subject");
        self::addColumn('offers','public_page_json',"LONGTEXT NULL AFTER email_content_json");
        self::addColumn('offer_text_templates','greeting',"TEXT NULL AFTER email_subject");
        self::addColumn('offer_text_templates','signature',"LONGTEXT NULL AFTER closing");

        $greetings = [
            'de'=>'Guten Tag {guest},','en'=>'Dear {guest},','es'=>'Estimado/a {guest},',
            'pt'=>'Caro/a {guest},','fr'=>'Bonjour {guest},','it'=>'Gentile {guest},','ca'=>'Benvolgut/da {guest},',
        ];
        $stmt=$pdo->prepare("UPDATE offer_text_templates SET greeting=COALESCE(NULLIF(greeting,''),?), signature=COALESCE(NULLIF(signature,''),NULLIF(footer,'')) WHERE language=?");
        foreach($greetings as $language=>$greeting)$stmt->execute([$greeting,$language]);

        foreach(['storage/backups','storage/tmp','storage/type-images','storage/type-images/thumbs','storage/site-images','storage/site-images/thumbs'] as $folder){
            $path=root_path($folder);
            if(!is_dir($path) && !@mkdir($path,0775,true) && !is_dir($path)){
                throw new RuntimeException('Der benötigte Ordner '.$folder.' konnte nicht angelegt werden.');
            }
        }
    }


    /**
     * V2.1.4 – mehrsprachiger Frontend- und Buchungsseiten-Editor.
     * Die öffentlichen Seiten nutzen weiterhin die bestehende Preis-, Typ- und
     * Buchungslogik. Es werden ausschließlich neue CMS-Tabellen und Einstellungen
     * ergänzt; vorhandene Angebote, Buchungen und Apartments bleiben unverändert.
     */
    private static function migrate214(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_pages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            system_key VARCHAR(60) NULL UNIQUE,
            slug VARCHAR(160) NOT NULL UNIQUE,
            title_fallback VARCHAR(190) NOT NULL,
            page_type VARCHAR(40) NOT NULL DEFAULT 'standard',
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            show_header TINYINT(1) NOT NULL DEFAULT 0,
            show_footer TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            protected TINYINT(1) NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_site_pages_status_sort(status,sort_order),
            INDEX idx_site_pages_navigation(show_header,show_footer,status,sort_order),
            CONSTRAINT fk_site_pages_created_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_site_pages_updated_by FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS site_page_translations (
            page_id INT UNSIGNED NOT NULL,
            language VARCHAR(5) NOT NULL,
            title VARCHAR(190) NULL,
            navigation_label VARCHAR(120) NULL,
            seo_title VARCHAR(190) NULL,
            seo_description VARCHAR(320) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(page_id,language),
            CONSTRAINT fk_site_page_translation_page FOREIGN KEY(page_id) REFERENCES site_pages(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS site_page_blocks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            page_id INT UNSIGNED NOT NULL,
            block_type VARCHAR(40) NOT NULL,
            system_key VARCHAR(80) NULL,
            settings_json LONGTEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            locked TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_site_blocks_page FOREIGN KEY(page_id) REFERENCES site_pages(id) ON DELETE CASCADE,
            CONSTRAINT fk_site_blocks_created_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_site_blocks_updated_by FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_site_blocks_page_sort(page_id,active,sort_order,id),
            UNIQUE KEY uq_site_block_system(page_id,system_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS site_media (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            file_path VARCHAR(500) NOT NULL,
            thumb_path VARCHAR(500) NULL,
            seo_filename VARCHAR(255) NOT NULL UNIQUE,
            original_name VARCHAR(255) NULL,
            mime_type VARCHAR(80) NOT NULL DEFAULT 'image/webp',
            width INT UNSIGNED NOT NULL DEFAULT 0,
            height INT UNSIGNED NOT NULL DEFAULT 0,
            size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            alt_text_json LONGTEXT NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_site_media_created_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_site_media_created(created_at,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS site_page_block_translations (
            block_id BIGINT UNSIGNED NOT NULL,
            language VARCHAR(5) NOT NULL,
            title VARCHAR(190) NULL,
            content_json LONGTEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(block_id,language),
            CONSTRAINT fk_site_block_translation_block FOREIGN KEY(block_id) REFERENCES site_page_blocks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $design = [
            'logo_url'=>'','favicon_url'=>'','primary'=>'#2563eb','secondary'=>'#0f766e',
            'background'=>'#f4f7fb','surface'=>'#ffffff','text'=>'#172033','muted'=>'#62708a',
            'header_background'=>'#101827','header_text'=>'#ffffff','footer_background'=>'#101827','footer_text'=>'#dbe5f3',
            'font'=>'system','content_width'=>1180,'radius'=>18,'button_style'=>'rounded','card_shadow'=>1,
            'sticky_header'=>1,'show_admin_link'=>0,'phone'=>'','email'=>'','address'=>'','facebook_url'=>'','instagram_url'=>'','copyright'=>'',
        ];
        $defaults = [
            'site_design_json'=>json_encode($design,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'site_labels_json'=>json_encode([],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'site_editor_enabled'=>'1',
        ];
        $settingStmt=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=setting_value');
        foreach($defaults as $key=>$value)$settingStmt->execute([$key,$value]);

        $pages = [
            ['home','startseite','Startseite','home','published',1,0,10,1],
            ['types','wohnungstypen','Wohnungstypen','types','published',1,0,20,1],
            ['contact','kontakt','Kontakt','contact','published',1,1,30,1],
            ['terms','bedingungen','Buchungsbedingungen','legal','published',0,1,80,1],
            ['privacy','datenschutz','Datenschutz','legal','published',0,1,90,1],
            ['imprint','impressum','Impressum','legal','published',0,1,100,1],
        ];
        $pageFind=$pdo->prepare('SELECT id FROM site_pages WHERE system_key=? OR slug=? ORDER BY system_key=? DESC LIMIT 1');
        $pageInsert=$pdo->prepare('INSERT INTO site_pages(system_key,slug,title_fallback,page_type,status,show_header,show_footer,sort_order,protected) VALUES(?,?,?,?,?,?,?,?,?)');
        $pageUpdate=$pdo->prepare('UPDATE site_pages SET system_key=COALESCE(system_key,?),title_fallback=COALESCE(NULLIF(title_fallback,\'\'),?),protected=GREATEST(protected,?) WHERE id=?');
        $ids=[];
        foreach($pages as $row){
            [$key,$slug,$title,$type,$status,$header,$footer,$sort,$protected]=$row;
            $pageFind->execute([$key,$slug,$key]);$id=(int)($pageFind->fetchColumn()?:0);
            if(!$id){$pageInsert->execute($row);$id=(int)$pdo->lastInsertId();}
            else{$pageUpdate->execute([$key,$title,$protected,$id]);}
            $ids[$key]=$id;
        }

        $translations = [
            'de'=>['home'=>['Startseite','Startseite'],'types'=>['Wohnungstypen','Wohnungen'],'contact'=>['Kontakt','Kontakt'],'terms'=>['Buchungsbedingungen','Bedingungen'],'privacy'=>['Datenschutz','Datenschutz'],'imprint'=>['Impressum','Impressum']],
            'en'=>['home'=>['Home','Home'],'types'=>['Accommodation types','Accommodation'],'contact'=>['Contact','Contact'],'terms'=>['Booking terms','Terms'],'privacy'=>['Privacy policy','Privacy'],'imprint'=>['Legal notice','Legal notice']],
            'es'=>['home'=>['Inicio','Inicio'],'types'=>['Tipos de alojamiento','Alojamientos'],'contact'=>['Contacto','Contacto'],'terms'=>['Condiciones de reserva','Condiciones'],'privacy'=>['Protección de datos','Privacidad'],'imprint'=>['Aviso legal','Aviso legal']],
            'fr'=>['home'=>['Accueil','Accueil'],'types'=>['Types de logements','Logements'],'contact'=>['Contact','Contact'],'terms'=>['Conditions de réservation','Conditions'],'privacy'=>['Protection des données','Confidentialité'],'imprint'=>['Mentions légales','Mentions légales']],
            'it'=>['home'=>['Home','Home'],'types'=>['Tipi di alloggio','Alloggi'],'contact'=>['Contatto','Contatto'],'terms'=>['Condizioni di prenotazione','Condizioni'],'privacy'=>['Privacy','Privacy'],'imprint'=>['Note legali','Note legali']],
            'pt'=>['home'=>['Início','Início'],'types'=>['Tipos de alojamento','Alojamentos'],'contact'=>['Contacto','Contacto'],'terms'=>['Condições de reserva','Condições'],'privacy'=>['Proteção de dados','Privacidade'],'imprint'=>['Aviso legal','Aviso legal']],
            'ca'=>['home'=>['Inici','Inici'],'types'=>['Tipus d’allotjament','Allotjaments'],'contact'=>['Contacte','Contacte'],'terms'=>['Condicions de reserva','Condicions'],'privacy'=>['Protecció de dades','Privacitat'],'imprint'=>['Avís legal','Avís legal']],
        ];
        $trStmt=$pdo->prepare('INSERT INTO site_page_translations(page_id,language,title,navigation_label,seo_title,seo_description) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=COALESCE(NULLIF(title,\'\'),VALUES(title)),navigation_label=COALESCE(NULLIF(navigation_label,\'\'),VALUES(navigation_label))');
        foreach($translations as $language=>$entries){foreach($entries as $key=>$values){$title=$values[0];$nav=$values[1];$trStmt->execute([$ids[$key],$language,$title,$nav,$title,null]);}}

        $blockFind=$pdo->prepare('SELECT id FROM site_page_blocks WHERE page_id=? AND system_key=? LIMIT 1');
        $blockInsert=$pdo->prepare('INSERT INTO site_page_blocks(page_id,block_type,system_key,settings_json,active,locked,sort_order) VALUES(?,?,?,?,1,?,?)');
        $blockTr=$pdo->prepare('INSERT INTO site_page_block_translations(block_id,language,title,content_json) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE title=COALESCE(NULLIF(title,\'\'),VALUES(title)),content_json=COALESCE(NULLIF(content_json,\'\'),VALUES(content_json))');
        $seedBlock=function(string $pageKey,string $systemKey,string $type,int $sort,bool $locked,array $settings,array $contentByLanguage) use($pdo,$ids,$blockFind,$blockInsert,$blockTr):void{
            $pageId=$ids[$pageKey];$blockFind->execute([$pageId,$systemKey]);$blockId=(int)($blockFind->fetchColumn()?:0);
            if(!$blockId){$blockInsert->execute([$pageId,$type,$systemKey,json_encode($settings,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$locked?1:0,$sort]);$blockId=(int)$pdo->lastInsertId();}
            foreach($contentByLanguage as $language=>$content){$title=(string)($content['title']??'');$blockTr->execute([$blockId,$language,$title,json_encode($content,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);}
        };
        $seedBlock('home','home-hero','hero',10,false,['height'=>'large','alignment'=>'left','background_image'=>''],[
            'de'=>['title'=>'Ihr Urlaub beginnt hier','subtitle'=>'Entdecken Sie passende Ferienwohnungen und senden Sie direkt eine unverbindliche Anfrage.','button_label'=>'Verfügbarkeit prüfen','button_url'=>'#booking-search'],
            'en'=>['title'=>'Your holiday starts here','subtitle'=>'Discover suitable holiday accommodation and send a non-binding request directly.','button_label'=>'Check availability','button_url'=>'#booking-search'],
            'es'=>['title'=>'Sus vacaciones empiezan aquí','subtitle'=>'Descubra alojamientos adecuados y envíe directamente una solicitud sin compromiso.','button_label'=>'Comprobar disponibilidad','button_url'=>'#booking-search'],
            'fr'=>['title'=>'Vos vacances commencent ici','subtitle'=>'Découvrez nos logements et envoyez directement une demande sans engagement.','button_label'=>'Vérifier la disponibilité','button_url'=>'#booking-search'],
            'it'=>['title'=>'La vostra vacanza inizia qui','subtitle'=>'Scoprite gli alloggi adatti e inviate direttamente una richiesta non vincolante.','button_label'=>'Verifica disponibilità','button_url'=>'#booking-search'],
            'pt'=>['title'=>'As suas férias começam aqui','subtitle'=>'Descubra alojamentos adequados e envie diretamente um pedido sem compromisso.','button_label'=>'Verificar disponibilidade','button_url'=>'#booking-search'],
            'ca'=>['title'=>'Les vostres vacances comencen aquí','subtitle'=>'Descobriu allotjaments adequats i envieu directament una sol·licitud sense compromís.','button_label'=>'Comprovar disponibilitat','button_url'=>'#booking-search'],
        ]);
        $seedBlock('home','home-search','booking_search',20,true,['layout'=>'wide'],array_fill_keys(['de','en','es','fr','it','pt','ca'],['title'=>'','subtitle'=>'']));
        $seedBlock('home','home-types','type_grid',30,false,['limit'=>6,'show_description'=>1,'show_amenities'=>1],[
            'de'=>['title'=>'Unsere Wohnungstypen','subtitle'=>'Wählen Sie den Typ, der zu Ihrem Aufenthalt passt.'],'en'=>['title'=>'Our accommodation types','subtitle'=>'Choose the accommodation that suits your stay.'],'es'=>['title'=>'Nuestros alojamientos','subtitle'=>'Elija el tipo que mejor se adapte a su estancia.'],'fr'=>['title'=>'Nos logements','subtitle'=>'Choisissez le type adapté à votre séjour.'],'it'=>['title'=>'I nostri alloggi','subtitle'=>'Scegliete il tipo più adatto al vostro soggiorno.'],'pt'=>['title'=>'Os nossos alojamentos','subtitle'=>'Escolha o tipo adequado à sua estadia.'],'ca'=>['title'=>'Els nostres allotjaments','subtitle'=>'Trieu el tipus més adequat per a la vostra estada.'],
        ]);
        $seedBlock('types','types-header','hero',10,false,['height'=>'medium','alignment'=>'center','background_image'=>''],[
            'de'=>['title'=>'Unsere Wohnungstypen','subtitle'=>'Ausstattung, Bilder und Informationen im Überblick.','button_label'=>'Verfügbarkeit prüfen','button_url'=>'index.php#booking-search'],
            'en'=>['title'=>'Our accommodation types','subtitle'=>'Facilities, images and information at a glance.','button_label'=>'Check availability','button_url'=>'index.php#booking-search'],
            'es'=>['title'=>'Nuestros alojamientos','subtitle'=>'Equipamiento, imágenes e información de un vistazo.','button_label'=>'Comprobar disponibilidad','button_url'=>'index.php#booking-search'],
            'fr'=>['title'=>'Nos logements','subtitle'=>'Équipements, images et informations en un coup d’œil.','button_label'=>'Vérifier la disponibilité','button_url'=>'index.php#booking-search'],
            'it'=>['title'=>'I nostri alloggi','subtitle'=>'Dotazioni, immagini e informazioni in sintesi.','button_label'=>'Verifica disponibilità','button_url'=>'index.php#booking-search'],
            'pt'=>['title'=>'Os nossos alojamentos','subtitle'=>'Equipamentos, imagens e informações num relance.','button_label'=>'Verificar disponibilidade','button_url'=>'index.php#booking-search'],
            'ca'=>['title'=>'Els nostres allotjaments','subtitle'=>'Equipament, imatges i informació d’un cop d’ull.','button_label'=>'Comprovar disponibilitat','button_url'=>'index.php#booking-search'],
        ]);
        $seedBlock('types','types-grid','type_grid',20,true,['limit'=>0,'show_description'=>1,'show_amenities'=>1],array_fill_keys(['de','en','es','fr','it','pt','ca'],['title'=>'','subtitle'=>'']));
        $legalDefaults=[
            'contact'=>[
                'de'=>['Kontakt','Tragen Sie hier Ihre Adresse, Telefonnummer, E-Mail und Anreisehinweise ein.'],'en'=>['Contact','Enter your address, telephone number, email and arrival information here.'],'es'=>['Contacto','Introduzca aquí su dirección, teléfono, correo electrónico e información de llegada.'],'fr'=>['Contact','Indiquez ici votre adresse, téléphone, e-mail et les informations d’arrivée.'],'it'=>['Contatto','Inserite qui indirizzo, telefono, e-mail e informazioni sull’arrivo.'],'pt'=>['Contacto','Introduza aqui a morada, telefone, e-mail e informações de chegada.'],'ca'=>['Contacte','Introduïu aquí l’adreça, el telèfon, el correu electrònic i la informació d’arribada.'],
            ],
            'terms'=>[
                'de'=>['Buchungsbedingungen','Ergänzen Sie hier Ihre verbindlichen Buchungs- und Zahlungsbedingungen.'],'en'=>['Booking terms','Add your binding booking and payment terms here.'],'es'=>['Condiciones de reserva','Añada aquí sus condiciones vinculantes de reserva y pago.'],'fr'=>['Conditions de réservation','Ajoutez ici vos conditions contractuelles de réservation et de paiement.'],'it'=>['Condizioni di prenotazione','Aggiungete qui le condizioni vincolanti di prenotazione e pagamento.'],'pt'=>['Condições de reserva','Adicione aqui as condições vinculativas de reserva e pagamento.'],'ca'=>['Condicions de reserva','Afegiu aquí les condicions vinculants de reserva i pagament.'],
            ],
            'privacy'=>[
                'de'=>['Datenschutz','Ergänzen und prüfen Sie hier Ihre Datenschutzerklärung.'],'en'=>['Privacy policy','Add and review your privacy policy here.'],'es'=>['Protección de datos','Añada y revise aquí su política de privacidad.'],'fr'=>['Protection des données','Ajoutez et vérifiez ici votre politique de confidentialité.'],'it'=>['Privacy','Aggiungete e verificate qui l’informativa sulla privacy.'],'pt'=>['Proteção de dados','Adicione e verifique aqui a política de privacidade.'],'ca'=>['Protecció de dades','Afegiu i reviseu aquí la política de privacitat.'],
            ],
            'imprint'=>[
                'de'=>['Impressum','Ergänzen Sie hier die gesetzlich erforderlichen Anbieterangaben.'],'en'=>['Legal notice','Add the legally required provider information here.'],'es'=>['Aviso legal','Añada aquí la información legal obligatoria del proveedor.'],'fr'=>['Mentions légales','Ajoutez ici les informations légales obligatoires du prestataire.'],'it'=>['Note legali','Aggiungete qui le informazioni legali obbligatorie del fornitore.'],'pt'=>['Aviso legal','Adicione aqui as informações legais obrigatórias do fornecedor.'],'ca'=>['Avís legal','Afegiu aquí la informació legal obligatòria del proveïdor.'],
            ],
        ];
        foreach($legalDefaults as $key=>$copyByLanguage){$content=[];foreach($copyByLanguage as $language=>$copy)$content[$language]=['title'=>$copy[0],'content_html'=>'<p>'.htmlspecialchars($copy[1],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>'];$seedBlock($key,$key.'-content',$key==='contact'?'contact':'rich_text',10,$key!=='contact',[],$content);}
    }


    private static function migrate220(PDO $pdo): void
    {
        self::addColumn('bookings','source_offer_id',"BIGINT UNSIGNED NULL AFTER id");
        self::addColumn('bookings','confirmed_at',"DATETIME NULL AFTER status");
        self::addColumn('bookings','confirmed_by',"INT UNSIGNED NULL AFTER confirmed_at");
        self::addColumn('bookings','confirmation_email_sent_at',"DATETIME NULL AFTER confirmed_by");
        self::addColumn('bookings','deposit_required',"TINYINT(1) NOT NULL DEFAULT 1 AFTER deposit_amount");
        self::addColumn('bookings','deposit_due_date',"DATE NULL AFTER deposit_required");
        self::addColumn('bookings','deposit_status',"VARCHAR(30) NOT NULL DEFAULT 'open' AFTER deposit_due_date");
        self::addColumn('bookings','deposit_waived_reason',"VARCHAR(500) NULL AFTER deposit_status");
        self::addColumn('bookings','remaining_due_date',"DATE NULL AFTER deposit_waived_reason");
        self::addColumn('bookings','remaining_status',"VARCHAR(30) NOT NULL DEFAULT 'open' AFTER remaining_due_date");

        self::addIndex('bookings','idx_bookings_offer','source_offer_id');
        self::addIndex('bookings','idx_bookings_payment_due','deposit_status,deposit_due_date,remaining_status,remaining_due_date');

        $pdo->exec("CREATE TABLE IF NOT EXISTS booking_payment_schedule (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id INT UNSIGNED NOT NULL,
            installment_type VARCHAR(30) NOT NULL,
            label VARCHAR(190) NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            due_date DATE NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            waived_reason VARCHAR(500) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_payment_schedule_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            UNIQUE KEY uq_payment_schedule_booking_type(booking_id,installment_type),
            INDEX idx_payment_schedule_due(status,due_date),
            INDEX idx_payment_schedule_booking(booking_id,sort_order,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS booking_payments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id INT UNSIGNED NOT NULL,
            payment_number VARCHAR(80) NULL UNIQUE,
            payment_date DATE NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            payment_method VARCHAR(60) NOT NULL DEFAULT 'bank_transfer',
            reference VARCHAR(190) NULL,
            note TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'received',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_booking_payments_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE RESTRICT,
            CONSTRAINT fk_booking_payments_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_booking_payments_booking(booking_id,payment_date,id),
            INDEX idx_booking_payments_status(status,payment_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS booking_payment_allocations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            payment_id BIGINT UNSIGNED NOT NULL,
            schedule_id BIGINT UNSIGNED NULL,
            amount DECIMAL(12,2) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_payment_allocation_payment FOREIGN KEY(payment_id) REFERENCES booking_payments(id) ON DELETE CASCADE,
            CONSTRAINT fk_payment_allocation_schedule FOREIGN KEY(schedule_id) REFERENCES booking_payment_schedule(id) ON DELETE SET NULL,
            INDEX idx_payment_alloc_payment(payment_id),
            INDEX idx_payment_alloc_schedule(schedule_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS booking_documents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id INT UNSIGNED NOT NULL,
            document_type VARCHAR(40) NOT NULL,
            document_number VARCHAR(80) NULL,
            language VARCHAR(5) NOT NULL DEFAULT 'de',
            title VARCHAR(190) NOT NULL,
            html_snapshot LONGTEXT NOT NULL,
            pdf_path VARCHAR(500) NULL,
            checksum_sha256 CHAR(64) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'generated',
            generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_booking_documents_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            CONSTRAINT fk_booking_documents_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY uq_booking_document_number(document_number),
            INDEX idx_booking_documents_booking(booking_id,document_type,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS booking_customer_access (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id INT UNSIGNED NOT NULL UNIQUE,
            token_hash CHAR(64) NOT NULL UNIQUE,
            token_encrypted LONGTEXT NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            valid_until DATETIME NULL,
            last_viewed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_booking_customer_access_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            INDEX idx_booking_customer_access_active(active,valid_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS booking_confirmation_templates (
            language VARCHAR(5) NOT NULL PRIMARY KEY,
            email_subject VARCHAR(255) NULL,
            greeting LONGTEXT NULL,
            intro LONGTEXT NULL,
            additional_info LONGTEXT NULL,
            closing LONGTEXT NULL,
            signature LONGTEXT NULL,
            pdf_title VARCHAR(190) NULL,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_booking_confirmation_template_user FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $settings = [
            'booking_confirmation_email_enabled'=>'1',
            'booking_default_deposit_due_days'=>'7',
            'booking_default_remaining_due_days'=>'14',
            'booking_customer_access_days_after_departure'=>'30',
            'booking_confirmation_pdf_enabled'=>'1',
        ];
        $settingStmt=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=setting_value');
        foreach($settings as $key=>$value)$settingStmt->execute([$key,$value]);

        $templates = [
            'de'=>['Ihre Buchungsbestätigung {reference}','Guten Tag {guest},','vielen Dank. Ihre Buchung wurde geprüft und verbindlich bestätigt.','Im sicheren Gastbereich finden Sie den aktuellen Zahlungsstand, Ihre Dokumente und weitere Informationen für den Aufenthalt.','Wir freuen uns auf Ihren Aufenthalt.','Ihr StayPilot-Team','Buchungsbestätigung'],
            'en'=>['Your booking confirmation {reference}','Hello {guest},','thank you. Your booking has been reviewed and confirmed.','Your secure guest area contains the current payment status, documents and further information for your stay.','We look forward to welcoming you.','Your StayPilot team','Booking confirmation'],
            'es'=>['Confirmación de reserva {reference}','Hola {guest},','gracias. Su reserva ha sido revisada y confirmada.','En su área segura encontrará el estado de pago, los documentos y más información para su estancia.','Esperamos darle la bienvenida.','Su equipo StayPilot','Confirmación de reserva'],
            'fr'=>['Confirmation de réservation {reference}','Bonjour {guest},','merci. Votre réservation a été vérifiée et confirmée.','Votre espace sécurisé contient l’état des paiements, les documents et d’autres informations pour votre séjour.','Nous nous réjouissons de vous accueillir.','Votre équipe StayPilot','Confirmation de réservation'],
            'it'=>['Conferma di prenotazione {reference}','Buongiorno {guest},','grazie. La prenotazione è stata verificata e confermata.','Nell’area ospite sicura trova lo stato dei pagamenti, i documenti e altre informazioni sul soggiorno.','Siamo lieti di accoglierla.','Il team StayPilot','Conferma di prenotazione'],
            'pt'=>['Confirmação de reserva {reference}','Olá {guest},','obrigado. A sua reserva foi verificada e confirmada.','Na área segura encontra o estado dos pagamentos, documentos e mais informações para a estadia.','Esperamos recebê-lo em breve.','A sua equipa StayPilot','Confirmação de reserva'],
            'ca'=>['Confirmació de reserva {reference}','Hola {guest},','gràcies. La seva reserva ha estat revisada i confirmada.','A l’àrea segura trobarà l’estat dels pagaments, els documents i més informació per a l’estada.','Esperem donar-li la benvinguda.','El seu equip StayPilot','Confirmació de reserva'],
        ];
        $templateStmt=$pdo->prepare('INSERT INTO booking_confirmation_templates(language,email_subject,greeting,intro,additional_info,closing,signature,pdf_title) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE language=VALUES(language)');
        foreach($templates as $language=>$row)$templateStmt->execute([$language,...$row]);

        $documentDir=root_path('storage/documents/booking-confirmations');
        if(!is_dir($documentDir) && !@mkdir($documentDir,0775,true) && !is_dir($documentDir)){
            throw new RuntimeException('Der Dokumentordner storage/documents/booking-confirmations konnte nicht angelegt werden.');
        }
        foreach([root_path('storage/documents'),$documentDir] as $directory){
            $index=rtrim($directory,'/\\').DIRECTORY_SEPARATOR.'index.html';
            if(!is_file($index))@file_put_contents($index,'');
        }
        $documentHtaccess=root_path('storage/documents/.htaccess');
        if(!is_file($documentHtaccess))@file_put_contents($documentHtaccess,"Require all denied\nDeny from all\n");
    }


    private static function migrate224(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_permission_overrides (
            user_id INT UNSIGNED NOT NULL,
            capability VARCHAR(80) NOT NULL,
            allowed TINYINT(1) NOT NULL DEFAULT 1,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, capability),
            CONSTRAINT fk_permission_override_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_permission_override_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_permission_override_capability (capability, allowed)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }


    private static function migrate228(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS booking_checkins (
            booking_id INT UNSIGNED NOT NULL PRIMARY KEY,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            planned_arrival_time TIME NULL,
            vehicle_plate VARCHAR(80) NULL,
            special_requests TEXT NULL,
            consent_privacy TINYINT(1) NOT NULL DEFAULT 0,
            consent_house_rules TINYINT(1) NOT NULL DEFAULT 0,
            signature_name VARCHAR(190) NULL,
            submitted_at DATETIME NULL,
            reviewed_at DATETIME NULL,
            reviewed_by INT UNSIGNED NULL,
            review_note VARCHAR(1000) NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_booking_checkins_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            CONSTRAINT fk_booking_checkins_reviewed_by FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_booking_checkins_updated_by FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_booking_checkins_status(status,submitted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS booking_checkin_uploads (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id INT UNSIGNED NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
            uploaded_by_user_id INT UNSIGNED NULL,
            uploaded_by_guest TINYINT(1) NOT NULL DEFAULT 0,
            note VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_checkin_upload_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            CONSTRAINT fk_checkin_upload_user FOREIGN KEY(uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_checkin_upload_booking(booking_id,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS checkin_text_templates (
            language VARCHAR(5) NOT NULL PRIMARY KEY,
            email_subject VARCHAR(255) NULL,
            email_intro LONGTEXT NULL,
            reminder_text LONGTEXT NULL,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_checkin_template_user FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $defaults = [
            ['de','Online-Check-in für Ihre Buchung {{reference}}','Bitte füllen Sie den Online-Check-in vor Ihrer Anreise aus. Sie können auch ein Foto oder PDF eines handschriftlichen Formulars hochladen.','Ihr Online-Check-in ist noch offen. Bitte ergänzen Sie die fehlenden Angaben.'],
            ['en','Online check-in for your booking {{reference}}','Please complete your online check-in before arrival. You may also upload a photo or PDF of a handwritten form.','Your online check-in is still open. Please complete the missing details.'],
            ['es','Check-in online para su reserva {{reference}}','Por favor complete el check-in online antes de su llegada. También puede subir una foto o PDF de un formulario escrito a mano.','Su check-in online sigue pendiente. Por favor complete los datos que faltan.'],
        ];
        $stmt = $pdo->prepare('INSERT INTO checkin_text_templates(language,email_subject,email_intro,reminder_text) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE language=VALUES(language)');
        foreach ($defaults as $row) $stmt->execute($row);

        $dir = root_path('storage/checkin');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $ht = root_path('storage/checkin/.htaccess');
        if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
    }


    /**
     * V2.3.4 ist bewusst keine neue Pflicht-Schema-Version, sondern eine
     * idempotente Reparatur: In einigen Installationen fehlte offer_events.note,
     * obwohl die Diagnose diese Spalte bereits erwartete. Die Spalte speichert
     * kurze Ablaufnotizen zu Angeboten, Alternativen, Rückfragen und Upgrades.
     */
    private static function migrate234(PDO $pdo): void
    {
        self::addColumn('offer_events','note',"VARCHAR(255) NULL AFTER event_type");
    }


    /**
     * V2.3.6.5 – Zusatzfelder für die öffentliche Wohnungstyp-Bildergalerie.
     * Diese Migration ist bewusst klein und idempotent: Wenn die Spalten bereits
     * existieren, passiert nichts. Dadurch kann ein abgebrochener Update-Versuch
     * sicher erneut gestartet werden.
     */
    private static function migrate2365(PDO $pdo): void
    {
        self::addColumn('apartment_type_images','title_text_json',"LONGTEXT NULL AFTER alt_text_json");
        self::addColumn('apartment_type_images','caption_text_json',"LONGTEXT NULL AFTER title_text_json");
        if (self::columnExists('apartment_type_images','title_text_json')) {
            $pdo->exec("UPDATE apartment_type_images SET title_text_json='{}' WHERE title_text_json IS NULL OR title_text_json='' ");
        }
        if (self::columnExists('apartment_type_images','caption_text_json')) {
            $pdo->exec("UPDATE apartment_type_images SET caption_text_json='{}' WHERE caption_text_json IS NULL OR caption_text_json='' ");
        }
    }


    /**
     * V2.3.6.6 – Marktreife-Grundlogik: Buchungsquelle und Abrechnungsart.
     * Externe Portal-/Importbuchungen und Personalbelegungen bleiben betriebs- und
     * kalenderrelevant, werden aber nicht automatisch in der internen Abrechnung geführt.
     */
    private static function migrate2366(PDO $pdo): void
    {
        self::addColumn('booking_channels','channel_category',"VARCHAR(30) NOT NULL DEFAULT 'direct' AFTER color");
        self::addColumn('booking_channels','default_accounting_mode',"VARCHAR(30) NOT NULL DEFAULT 'internal' AFTER channel_category");
        self::addColumn('bookings','accounting_mode',"VARCHAR(30) NOT NULL DEFAULT 'internal' AFTER booking_channel_id");
        self::addColumn('bookings','billing_excluded_reason',"VARCHAR(255) NULL AFTER accounting_mode");
        self::addIndex('bookings','idx_bookings_accounting_mode','accounting_mode');
        self::addIndex('booking_channels','idx_channels_accounting','default_accounting_mode');

        $defaults = [
            ['Direkt','DIREKT','#2563eb','direct','internal','Direkte Buchung',10],
            ['Webseite','WEBSEITE','#0f9f6e','web','internal','Eigene Webseite',20],
            ['Booking.com','BOOKING','#1d4ed8','portal','external','Booking.com – extern abgerechnet',30],
            ['Airbnb','AIRBNB','#ef4444','portal','external','Airbnb – extern abgerechnet',40],
            ['Expedia','EXPEDIA','#7c3aed','portal','external','Expedia / Vrbo – extern abgerechnet',45],
            ['Hotel-Spider','HOTEL-SPIDER','#0ea5e9','portal','external','Hotel-Spider / Channel-Manager',48],
            ['CSV-Import','CSV','#64748b','import','external','CSV-Import – standardmäßig extern abgerechnet',55],
            ['XML-Import','XML','#475569','import','external','XML-Import – standardmäßig extern abgerechnet',56],
            ['Personal','PERSONAL','#f97316','internal_use','none','Personal-/Mitarbeiterbelegung ohne Abrechnung',85],
            ['Eigentümer','OWNER','#a855f7','internal_use','none','Eigentümer-/Privatbelegung ohne Abrechnung',86],
            ['Sperrung','BLOCK','#334155','block','none','Kalenderblock/Sperrbelegung ohne Abrechnung',90],
        ];
        $stmt = $pdo->prepare('INSERT INTO booking_channels(name,code,color,channel_category,default_accounting_mode,description,sort_order,active) VALUES(?,?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE channel_category=VALUES(channel_category),default_accounting_mode=VALUES(default_accounting_mode),description=VALUES(description),color=VALUES(color)');
        foreach ($defaults as $row) $stmt->execute($row);

        if (self::tableExists('bookings')) {
            $pdo->exec("UPDATE bookings b LEFT JOIN booking_channels c ON c.id=b.booking_channel_id SET b.accounting_mode=CASE WHEN c.default_accounting_mode IN ('internal','external','none','portal_later') THEN c.default_accounting_mode WHEN LOWER(COALESCE(b.source,'')) REGEXP 'booking|airbnb|expedia|vrbo|hotel.?spider|csv|xml|ical|import|portal|channel' THEN 'external' WHEN LOWER(COALESCE(b.source,'')) REGEXP 'personal|mitarbeiter|eigent|owner|sperr|block|wartung|technik|familie|privat' THEN 'none' ELSE COALESCE(NULLIF(b.accounting_mode,''),'internal') END WHERE b.accounting_mode IS NULL OR b.accounting_mode='' OR b.accounting_mode='internal'");
            $pdo->exec("UPDATE bookings SET billing_excluded_reason=CASE WHEN accounting_mode='external' THEN COALESCE(NULLIF(billing_excluded_reason,''),'Externes Portal/Import – nicht intern abrechnen') WHEN accounting_mode='none' THEN COALESCE(NULLIF(billing_excluded_reason,''),'Nur Belegung – keine Abrechnung') ELSE billing_excluded_reason END WHERE accounting_mode IN ('external','none')");
            if (self::tableExists('booking_payment_schedule')) {
                $pdo->exec("UPDATE booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id SET s.status='waived',s.paid_amount=0,s.waived_reason=COALESCE(NULLIF(s.waived_reason,''),CASE WHEN b.accounting_mode='external' THEN 'Extern abgerechnet' ELSE 'Keine interne Abrechnung' END) WHERE b.accounting_mode IN ('external','none') AND s.status NOT IN ('received','waived','cancelled','canceled','void','aufgehoben','storniert')");
            }
            $pdo->exec("UPDATE bookings SET payment_status=CASE WHEN accounting_mode='external' THEN 'external' WHEN accounting_mode='none' THEN 'not_billable' ELSE payment_status END, deposit_status=CASE WHEN accounting_mode IN ('external','none') THEN 'waived' ELSE deposit_status END, remaining_status=CASE WHEN accounting_mode IN ('external','none') THEN 'waived' ELSE remaining_status END WHERE accounting_mode IN ('external','none')");
        }
    }

    private static function requiredSchemaComplete(): bool
    {
        $requiredTables = [
            'settings', 'apartment_types', 'apartments', 'guests', 'bookings', 'csv_profiles',
            'seasons', 'season_periods', 'season_type_prices', 'special_prices', 'booking_channels',
            'housekeeping_teams', 'housekeeping_members', 'mail_settings', 'communication_log', 'housekeeping_tasks',
            'housekeeping_incidents', 'notifications', 'guest_portal_access', 'guest_portal_contents',
            'document_sequences','offers','offer_items','offer_events','offer_services','offer_service_translations','offer_apartment_type_translations','offer_text_templates','offer_content_blocks','offer_content_block_translations','amenity_catalog','amenity_translations','apartment_type_amenities','apartment_type_images',
            'site_pages','site_page_translations','site_page_blocks','site_page_block_translations','site_media',
            'booking_payment_schedule','booking_payments','booking_payment_allocations','booking_documents','booking_customer_access','booking_confirmation_templates','user_permission_overrides','booking_checkins','booking_checkin_uploads','checkin_text_templates',
        ];
        foreach ($requiredTables as $table) {
            if (!self::tableExists($table)) return false;
        }

        $requiredColumns = [
            'apartment_types' => ['default_min_stay','standard_occupancy','allow_capacity_override','public_active','cancel_free_until_days','cancel_tier1_from_days','cancel_tier1_percent','cancel_tier2_from_days','cancel_tier2_percent','cancel_no_show_percent'],
            'apartments' => ['min_stay_override'],
            'guests' => ['preferences'],
            'bookings' => [
                'apartment_type_id','capacity_override','capacity_override_reason','child_ages_json','public_language','cancellation_snapshot_json','cancellation_fee_percent','cancellation_fee_amount','cancelled_at',
                'booking_channel_id','accounting_mode','billing_excluded_reason', 'special_price_type', 'special_price_value',
                'special_price_reason', 'price_locked', 'min_stay_override',
                'min_stay_override_reason','source_offer_id','confirmed_at','confirmed_by','confirmation_email_sent_at','deposit_required','deposit_due_date','deposit_status','deposit_waived_reason','remaining_due_date','remaining_status',
            ],
            'housekeeping_tasks' => [
                'team_id','member_id','actual_minutes','checklist_done_json','completion_notes','completed_at',
                'whatsapp_status','whatsapp_opened_at','whatsapp_opened_by','email_status','email_sent_at',
                'origin_type','due_time','assigned_by','cleaning_completed_at','cleaning_completed_by','inspected_at','inspected_by',
                'inspection_result','ready_reported_at','ready_reported_by','released_at','released_by','release_note','release_blocked',
            ],
            'housekeeping_members' => ['team_id','user_id','name','whatsapp_number','email','active','can_assign','can_reassign','can_inspect','can_mark_ready','can_report_incident','can_upload_photos','preferred_language'],
            'mail_settings' => ['host','port','encryption','password_encrypted','from_email','active'],
            'communication_log' => ['channel','entity_type','entity_id','recipient_address','message_body','status','created_at'],
            'csv_profiles' => [
                'encoding_name', 'quote_char', 'header_row', 'skip_rows',
                'thousands_separator', 'update_mode', 'value_mappings_json',
            ],
            'offers' => ['offer_number','guest_name','language','apartment_type_id','apartment_id','status','valid_until','total_amount','deposit_due_date','email_subject','email_content_json','public_page_json','document_options_json','document_snapshot_json','calculation_input_json','price_snapshot_json','public_token_hash','public_token_encrypted','booking_id'],
            'offer_items' => ['offer_id','item_type','description','quantity','unit_price','line_total','source_type','source_id'],
            'offer_events' => ['offer_id','event_type','note','created_at'],
            'offer_text_templates' => ['language','email_subject','greeting','intro','validity','closing','signature','footer','terms','payment_info','additional_label','additional_info','remaining_payment'],
            'offer_content_blocks' => ['code','internal_name','active','show_by_default','sort_order'],
            'offer_content_block_translations' => ['block_id','language','title','content_html'],
            'offer_apartment_type_translations' => ['apartment_type_id','language','name','description','amenities_html','public_description_html','seo_title','seo_description','request_hint','image_alt'],
            'site_pages' => ['id','system_key','slug','title_fallback','page_type','status','show_header','show_footer','sort_order','protected','created_by','updated_by','created_at','updated_at'],
            'site_page_translations' => ['page_id','language','title','navigation_label','seo_title','seo_description','updated_at'],
            'site_page_blocks' => ['id','page_id','block_type','system_key','settings_json','active','locked','sort_order','created_by','updated_by','created_at','updated_at'],
            'site_page_block_translations' => ['block_id','language','title','content_json','updated_at'],
            'site_media' => ['id','file_path','thumb_path','seo_filename','original_name','mime_type','width','height','size_bytes','alt_text_json','created_by','created_at','updated_at'],
            'apartment_type_images' => ['title_text_json','caption_text_json'],

            'booking_payment_schedule' => ['booking_id','installment_type','label','amount','due_date','status','paid_amount','waived_reason'],
            'booking_payments' => ['booking_id','payment_date','amount','payment_method','reference','status'],
            'booking_documents' => ['booking_id','document_type','document_number','language','title','html_snapshot','pdf_path','checksum_sha256','status'],
            'booking_customer_access' => ['booking_id','token_hash','token_encrypted','active','valid_until','last_viewed_at'],
            'booking_confirmation_templates' => ['language','email_subject','greeting','intro','additional_info','closing','signature','pdf_title'],
            'user_permission_overrides' => ['user_id','capability','allowed','updated_by','updated_at'],
            'booking_checkins' => ['booking_id','status','planned_arrival_time','vehicle_plate','special_requests','consent_privacy','consent_house_rules','signature_name','submitted_at','reviewed_at','reviewed_by','review_note'],
            'booking_checkin_uploads' => ['booking_id','file_path','original_name','mime_type','size_bytes','uploaded_by_guest','created_at'],
            'checkin_text_templates' => ['language','email_subject','email_intro','reminder_text'],
        ];
        foreach ($requiredColumns as $table => $columns) {
            foreach ($columns as $column) {
                if (!self::columnExists($table, $column)) return false;
            }
        }

        // Tabellen allein reichen nicht: Auch die unverzichtbaren Startwerte
        // und alle sieben Sprachvorlagen müssen vorhanden sein.
        try {
            $requiredSettings = [
                'offer_validity_days','offer_deposit_percent','offer_vat_rate','offer_prices_include_vat',
                'offer_number_prefix','offer_currency','offer_logo_url','offer_bank_account_holder',
                'offer_bank_iban','offer_bank_bic','offer_bank_name','offer_company_extra','offer_company_website','offer_tourist_tax_min_age','public_booking_default_language','public_booking_languages','public_booking_show_prices',
                'site_design_json','site_labels_json','site_editor_enabled','booking_confirmation_email_enabled','booking_default_deposit_due_days','booking_default_remaining_due_days','booking_customer_access_days_after_departure','booking_confirmation_pdf_enabled',
            ];
            $marks = implode(',', array_fill(0, count($requiredSettings), '?'));
            $stmt = db()->prepare("SELECT COUNT(DISTINCT setting_key) FROM settings WHERE setting_key IN ($marks)");
            $stmt->execute($requiredSettings);
            if ((int)$stmt->fetchColumn() !== count($requiredSettings)) return false;

            $languages = ['de','en','es','pt','fr','it','ca'];
            $marks = implode(',', array_fill(0, count($languages), '?'));
            $stmt = db()->prepare("SELECT COUNT(DISTINCT language) FROM offer_text_templates WHERE language IN ($marks)");
            $stmt->execute($languages);
            if ((int)$stmt->fetchColumn() !== count($languages)) return false;
            $stmt = db()->prepare("SELECT COUNT(DISTINCT language) FROM booking_confirmation_templates WHERE language IN ($marks)");
            $stmt->execute($languages);
            if ((int)$stmt->fetchColumn() !== count($languages)) return false;
        } catch (Throwable) {
            return false;
        }
        return true;
    }

    private static function addColumn(string $table,string $column,string $definition): void
    {
        // Einige neue Module besitzen ihre Tabelle in älteren Installationen noch nicht.
        // In diesem Fall wird die Spalte nicht vorzeitig per ALTER TABLE angelegt;
        // das nachfolgende CREATE TABLE enthält bereits das vollständige aktuelle Schema.
        if(!self::tableExists($table)){
            return;
        }
        if(!self::columnExists($table,$column)){
            db()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }

    private static function addIndex(string $table,string $index,string $columns): void
    {
        if (!self::tableExists($table)) return;
        $stmt=db()->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
        $stmt->execute([$table,$index]);
        if (!(int)$stmt->fetchColumn()) db()->exec("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columns})");
    }

    private static function tableExists(string $table): bool
    {
        $stmt=db()->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn()>0;
    }

    private static function columnExists(string $table,string $column): bool
    {
        $stmt=db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table,$column]);
        return (int)$stmt->fetchColumn()>0;
    }
}
