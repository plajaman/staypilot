SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'admin',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  failed_login_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role_active (role,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS user_permission_overrides (
  user_id INT UNSIGNED NOT NULL,
  capability VARCHAR(80) NOT NULL,
  allowed TINYINT(1) NOT NULL DEFAULT 1,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, capability),
  CONSTRAINT fk_permission_override_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_permission_override_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_permission_override_capability (capability, allowed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(120) PRIMARY KEY,
  setting_value LONGTEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS housekeeping_teams (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS housekeeping_members (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  phone VARCHAR(80) NULL,
  email VARCHAR(190) NULL,
  whatsapp_number VARCHAR(80) NULL,
  receives_whatsapp TINYINT(1) NOT NULL DEFAULT 1,
  receives_email TINYINT(1) NOT NULL DEFAULT 1,
  can_assign TINYINT(1) NOT NULL DEFAULT 0,
  can_reassign TINYINT(1) NOT NULL DEFAULT 0,
  can_inspect TINYINT(1) NOT NULL DEFAULT 0,
  can_mark_ready TINYINT(1) NOT NULL DEFAULT 0,
  can_report_incident TINYINT(1) NOT NULL DEFAULT 1,
  can_upload_photos TINYINT(1) NOT NULL DEFAULT 1,
  preferred_language VARCHAR(5) NOT NULL DEFAULT 'de',
  active TINYINT(1) NOT NULL DEFAULT 1,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_housekeeping_member_team FOREIGN KEY (team_id) REFERENCES housekeeping_teams(id) ON DELETE SET NULL,
  CONSTRAINT fk_housekeeping_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_housekeeping_members_team_active (team_id,active,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_settings (
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
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_mail_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_log (
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
  CONSTRAINT fk_communication_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_communication_entity (entity_type,entity_id,created_at),
  INDEX idx_communication_channel_status (channel,status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS houses (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS apartment_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  code VARCHAR(60) NOT NULL UNIQUE,
  max_occupancy SMALLINT UNSIGNED NOT NULL DEFAULT 2,
  standard_occupancy SMALLINT UNSIGNED NOT NULL DEFAULT 2,
  allow_capacity_override TINYINT(1) NOT NULL DEFAULT 1,
  public_active TINYINT(1) NOT NULL DEFAULT 1,
  default_adults SMALLINT UNSIGNED NOT NULL DEFAULT 2,
  default_children SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  bedrooms SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  beds SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  living_area DECIMAL(8,2) NULL,
  standard_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  default_min_stay SMALLINT UNSIGNED NOT NULL DEFAULT 1,
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
  cancel_free_until_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  cancel_tier1_from_days SMALLINT UNSIGNED NOT NULL DEFAULT 14,
  cancel_tier1_percent DECIMAL(5,2) NOT NULL DEFAULT 30,
  cancel_tier2_from_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  cancel_tier2_percent DECIMAL(5,2) NOT NULL DEFAULT 80,
  cancel_no_show_percent DECIMAL(5,2) NOT NULL DEFAULT 100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_types_active_sort (active,sort_order,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS apartments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  house_id INT UNSIGNED NULL,
  apartment_type_id INT UNSIGNED NULL,
  apartment_number VARCHAR(60) NULL,
  code VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  type VARCHAR(80) NOT NULL DEFAULT 'Ferienwohnung',
  status VARCHAR(30) NOT NULL DEFAULT 'active',
  max_guests SMALLINT UNSIGNED NOT NULL DEFAULT 2,
  bedrooms SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  bathrooms SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  cleaning_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  breakfast_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  half_board_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  parking_price_per_night DECIMAL(10,2) NOT NULL DEFAULT 0,
  pet_price_per_night DECIMAL(10,2) NOT NULL DEFAULT 0,
  extra_bed_price_per_night DECIMAL(10,2) NOT NULL DEFAULT 0,
  baby_bed_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  late_checkout_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  internet_access TINYINT(1) NOT NULL DEFAULT 1,
  full_address VARCHAR(255) NULL,
  floor VARCHAR(60) NULL,
  location_description VARCHAR(190) NULL,
  balcony TINYINT(1) NOT NULL DEFAULT 0,
  terrace TINYINT(1) NOT NULL DEFAULT 0,
  sea_view TINYINT(1) NOT NULL DEFAULT 0,
  parking_number VARCHAR(60) NULL,
  key_number VARCHAR(60) NULL,
  wifi_ssid VARCHAR(190) NULL,
  wifi_password_encrypted LONGTEXT NULL,
  price_adjustment_type VARCHAR(20) NOT NULL DEFAULT 'fixed',
  price_adjustment_value DECIMAL(10,2) NOT NULL DEFAULT 0,
  min_stay_override SMALLINT UNSIGNED NULL,
  cleaning_instructions TEXT NULL,
  out_of_service TINYINT(1) NOT NULL DEFAULT 0,
  owner_occupancy_allowed TINYINT(1) NOT NULL DEFAULT 1,
  internal_remarks TEXT NULL,
  color VARCHAR(20) NOT NULL DEFAULT '#2563eb',
  description TEXT NULL,
  amenities_json LONGTEXT NULL,
  image_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_apartments_house FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE SET NULL,
  CONSTRAINT fk_apartments_type FOREIGN KEY (apartment_type_id) REFERENCES apartment_types(id) ON DELETE SET NULL,
  INDEX idx_apartments_status (status),
  INDEX idx_apartments_house_type (house_id,apartment_type_id),
  INDEX idx_apartments_sort (sort_order, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guest_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  color VARCHAR(20) NOT NULL DEFAULT '#64748b',
  channel_category VARCHAR(30) NOT NULL DEFAULT 'direct',
  default_accounting_mode VARCHAR(30) NOT NULL DEFAULT 'internal',
  description VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_guest_categories_active_sort (active, sort_order, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(30) NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(120) NOT NULL,
  second_last_name VARCHAR(120) NULL,
  gender VARCHAR(20) NULL,
  nationality VARCHAR(100) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(80) NULL,
  address VARCHAR(190) NULL,
  postal_code VARCHAR(30) NULL,
  city VARCHAR(120) NULL,
  country VARCHAR(100) NULL,
  language VARCHAR(50) NOT NULL DEFAULT 'Deutsch',
  category_id INT UNSIGNED NULL,
  vip TINYINT(1) NOT NULL DEFAULT 0,
  own_color VARCHAR(20) NULL,
  repeat_guest TINYINT(1) NOT NULL DEFAULT 0,
  date_of_birth DATE NULL,
  company VARCHAR(160) NULL,
  passport_number VARCHAR(100) NULL,
  document_type VARCHAR(40) NULL,
  document_support_number VARCHAR(100) NULL,
  document_issue_date DATE NULL,
  document_country VARCHAR(100) NULL,
  place_of_birth VARCHAR(160) NULL,
  province VARCHAR(120) NULL,
  fixed_phone VARCHAR(80) NULL,
  emergency_contact_name VARCHAR(160) NULL,
  emergency_contact_phone VARCHAR(80) NULL,
  marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0,
  preferences TEXT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_guests_name (last_name, first_name),
  INDEX idx_guests_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_channels (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  code VARCHAR(60) NOT NULL UNIQUE,
  color VARCHAR(20) NOT NULL DEFAULT '#64748b',
  channel_category VARCHAR(30) NOT NULL DEFAULT 'direct',
  default_accounting_mode VARCHAR(30) NOT NULL DEFAULT 'internal',
  description VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_channels_active_sort (active,sort_order,name),
  INDEX idx_channels_accounting (default_accounting_mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_offer_id BIGINT UNSIGNED NULL,
  reference VARCHAR(80) NOT NULL UNIQUE,
  guest_id INT UNSIGNED NOT NULL,
  apartment_id INT UNSIGNED NULL,
  apartment_type_id INT UNSIGNED NULL,
  arrival DATE NOT NULL,
  departure DATE NOT NULL,
  planned_arrival_time TIME NULL,
  planned_departure_time TIME NULL,
  actual_checkin_at DATETIME NULL,
  actual_checkout_at DATETIME NULL,
  key_issued TINYINT(1) NOT NULL DEFAULT 0,
  damage_notes TEXT NULL,
  cleaning_status VARCHAR(30) NOT NULL DEFAULT 'open',
  adults SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  children SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  babies SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  pets SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  capacity_override TINYINT(1) NOT NULL DEFAULT 0,
  capacity_override_reason VARCHAR(500) NULL,
  child_ages_json LONGTEXT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'inquiry',
  confirmed_at DATETIME NULL,
  confirmed_by INT UNSIGNED NULL,
  confirmation_email_sent_at DATETIME NULL,
  source VARCHAR(80) NOT NULL DEFAULT 'Direkt',
  public_language VARCHAR(5) NULL,
  booking_channel_id INT UNSIGNED NULL,
  accounting_mode VARCHAR(30) NOT NULL DEFAULT 'internal',
  billing_excluded_reason VARCHAR(255) NULL,
  booking_color VARCHAR(20) NULL,
  total_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_status VARCHAR(30) NOT NULL DEFAULT 'open',
  deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  deposit_required TINYINT(1) NOT NULL DEFAULT 1,
  deposit_due_date DATE NULL,
  deposit_status VARCHAR(30) NOT NULL DEFAULT 'open',
  deposit_waived_reason VARCHAR(500) NULL,
  remaining_due_date DATE NULL,
  remaining_status VARCHAR(30) NOT NULL DEFAULT 'open',
  tourist_tax DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount_code VARCHAR(80) NULL,
  discount_code_id INT UNSIGNED NULL,
  parking_spaces SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  extra_beds SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  baby_beds SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  late_checkout TINYINT(1) NOT NULL DEFAULT 0,
  extra_cleaning TINYINT(1) NOT NULL DEFAULT 0,
  linen_change TINYINT(1) NOT NULL DEFAULT 0,
  early_arrival TINYINT(1) NOT NULL DEFAULT 0,
  other_services_json LONGTEXT NULL,
  payment_method VARCHAR(60) NULL,
  payment_reference VARCHAR(190) NULL,
  price_breakdown_json LONGTEXT NULL,
  cancellation_snapshot_json LONGTEXT NULL,
  cancellation_fee_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  cancellation_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  cancelled_at DATETIME NULL,
  special_price_type VARCHAR(30) NOT NULL DEFAULT 'none',
  special_price_value DECIMAL(10,2) NOT NULL DEFAULT 0,
  special_price_reason VARCHAR(255) NULL,
  price_locked TINYINT(1) NOT NULL DEFAULT 0,
  min_stay_override TINYINT(1) NOT NULL DEFAULT 0,
  min_stay_override_reason VARCHAR(255) NULL,
  police_status VARCHAR(30) NOT NULL DEFAULT 'open',
  police_sent_at DATETIME NULL,
  contract_signed_at DATETIME NULL,
  breakfast TINYINT(1) NOT NULL DEFAULT 0,
  breakfast_start_date DATE NULL,
  breakfast_end_date DATE NULL,
  half_board TINYINT(1) NOT NULL DEFAULT 0,
  half_board_start_date DATE NULL,
  half_board_end_date DATE NULL,
  is_upgrade TINYINT(1) NOT NULL DEFAULT 0,
  upgrade_from_apartment_id INT UNSIGNED NULL,
  original_apartment_id INT UNSIGNED NULL,
  upgrade_note VARCHAR(255) NULL,
  vehicle_plate VARCHAR(80) NULL,
  guest_request TEXT NULL,
  special_requests TEXT NULL,
  internal_notes TEXT NULL,
  notes TEXT NULL,
  external_provider VARCHAR(60) NULL,
  external_id VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bookings_guest FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE RESTRICT,
  CONSTRAINT fk_bookings_apartment FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE SET NULL,
  CONSTRAINT fk_bookings_apartment_type FOREIGN KEY (apartment_type_id) REFERENCES apartment_types(id) ON DELETE SET NULL,
  INDEX idx_bookings_accounting_mode (accounting_mode),
  INDEX idx_bookings_dates (arrival, departure),
  INDEX idx_bookings_apartment_dates (apartment_id, arrival, departure),
  INDEX idx_bookings_type_stay (apartment_type_id,arrival,departure,status),
  INDEX idx_bookings_status (status),
  INDEX idx_bookings_offer (source_offer_id),
  INDEX idx_bookings_payment_due (deposit_status,deposit_due_date,remaining_status,remaining_due_date),
  UNIQUE KEY uniq_booking_external (external_provider, external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_change_log (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS season_rules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  multiplier DECIMAL(8,3) NOT NULL DEFAULT 1.000,
  min_stay SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  priority INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_seasons_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seasons (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS season_periods (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS season_type_prices (
  season_id INT UNSIGNED NOT NULL,
  apartment_type_id INT UNSIGNED NOT NULL,
  nightly_price DECIMAL(10,2) NULL,
  min_stay SMALLINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (season_id,apartment_type_id),
  CONSTRAINT fk_stp_season FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
  CONSTRAINT fk_stp_type FOREIGN KEY (apartment_type_id) REFERENCES apartment_types(id) ON DELETE CASCADE,
  INDEX idx_stp_type (apartment_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS special_prices (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS availability_blocks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  apartment_id INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  reason VARCHAR(190) NULL,
  block_type VARCHAR(40) NOT NULL DEFAULT 'maintenance',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_blocks_apartment FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE,
  INDEX idx_blocks_dates (apartment_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS housekeeping_tasks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  apartment_id INT UNSIGNED NOT NULL,
  booking_id INT UNSIGNED NULL,
  task_date DATE NOT NULL,
  task_type VARCHAR(50) NOT NULL DEFAULT 'turnover',
  status VARCHAR(30) NOT NULL DEFAULT 'open',
  origin_type VARCHAR(40) NOT NULL DEFAULT 'manual',
  due_time TIME NULL,
  assigned_to VARCHAR(120) NULL,
  team_id INT UNSIGNED NULL,
  member_id INT UNSIGNED NULL,
  assigned_by INT UNSIGNED NULL,
  priority VARCHAR(20) NOT NULL DEFAULT 'normal',
  estimated_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  actual_minutes SMALLINT UNSIGNED NULL,
  linen_change TINYINT(1) NOT NULL DEFAULT 1,
  towel_change TINYINT(1) NOT NULL DEFAULT 1,
  checklist_json LONGTEXT NULL,
  checklist_done_json LONGTEXT NULL,
  supplies TEXT NULL,
  supervisor VARCHAR(120) NULL,
  notes TEXT NULL,
  completion_notes TEXT NULL,
  accepted_at DATETIME NULL,
  started_at DATETIME NULL,
  cleaning_completed_at DATETIME NULL,
  cleaning_completed_by INT UNSIGNED NULL,
  inspected_at DATETIME NULL,
  inspected_by INT UNSIGNED NULL,
  inspection_result VARCHAR(40) NULL,
  ready_reported_at DATETIME NULL,
  ready_reported_by INT UNSIGNED NULL,
  released_at DATETIME NULL,
  released_by INT UNSIGNED NULL,
  release_note TEXT NULL,
  release_blocked TINYINT(1) NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  whatsapp_status VARCHAR(30) NOT NULL DEFAULT 'not_prepared',
  whatsapp_opened_at DATETIME NULL,
  whatsapp_opened_by INT UNSIGNED NULL,
  email_status VARCHAR(30) NOT NULL DEFAULT 'not_sent',
  email_sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tasks_apartment FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE,
  CONSTRAINT fk_tasks_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_team FOREIGN KEY (team_id) REFERENCES housekeeping_teams(id) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_member FOREIGN KEY (member_id) REFERENCES housekeeping_members(id) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_cleaning_by FOREIGN KEY (cleaning_completed_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_inspected_by FOREIGN KEY (inspected_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_ready_by FOREIGN KEY (ready_reported_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_released_by FOREIGN KEY (released_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_whatsapp_user FOREIGN KEY (whatsapp_opened_by) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_generated_task (booking_id, task_date, task_type),
  INDEX idx_tasks_date_status (task_date, status),
  INDEX idx_tasks_team_date (team_id,task_date),
  INDEX idx_tasks_member_date (member_id,task_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS housekeeping_incidents (
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
  CONSTRAINT fk_incident_task FOREIGN KEY (task_id) REFERENCES housekeeping_tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_incident_apartment FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE,
  CONSTRAINT fk_incident_reported_user FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_incident_reviewed_user FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_incident_task_status (task_id,status),
  INDEX idx_incident_apartment_status (apartment_id,status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
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
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notifications_user_read (user_id,read_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guest_portal_access (
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
  CONSTRAINT fk_guest_portal_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_guest_portal_release_user FOREIGN KEY (released_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_guest_portal_validity (active,valid_from,valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guest_portal_contents (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meal_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NULL,
  service_date DATE NOT NULL,
  meal_type VARCHAR(40) NOT NULL,
  adults SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  children SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(30) NOT NULL DEFAULT 'planned',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_meals_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  INDEX idx_meals_date_type (service_date, meal_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS length_discounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  min_nights SMALLINT UNSIGNED NOT NULL,
  percent DECIMAL(6,2) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  label VARCHAR(120) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_length_min_nights (min_nights)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discount_codes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_travellers (
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
  CONSTRAINT fk_travellers_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  INDEX idx_travellers_booking (booking_id),
  INDEX idx_travellers_name (last_name,first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS integrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(60) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 0,
  mode VARCHAR(20) NOT NULL DEFAULT 'test',
  base_url VARCHAR(500) NULL,
  property_id VARCHAR(190) NULL,
  username VARCHAR(190) NULL,
  secret_encrypted LONGTEXT NULL,
  settings_json LONGTEXT NULL,
  last_sync_at DATETIME NULL,
  last_status VARCHAR(30) NULL,
  last_message TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS integration_mappings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(60) NOT NULL,
  entity_type VARCHAR(50) NOT NULL DEFAULT 'apartment',
  local_id INT UNSIGNED NOT NULL,
  external_id VARCHAR(190) NOT NULL,
  secondary_external_id VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_mapping (provider, entity_type, local_id),
  INDEX idx_mapping_external (provider, external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sync_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(60) NOT NULL,
  direction VARCHAR(20) NOT NULL,
  operation VARCHAR(80) NOT NULL,
  status VARCHAR(30) NOT NULL,
  http_code INT NULL,
  message TEXT NULL,
  request_excerpt MEDIUMTEXT NULL,
  response_excerpt MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sync_provider_date (provider, created_at),
  INDEX idx_sync_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS csv_profiles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  entity_type VARCHAR(40) NOT NULL,
  delimiter_char VARCHAR(10) NOT NULL DEFAULT ';',
  encoding_name VARCHAR(40) NOT NULL DEFAULT 'UTF-8',
  quote_char VARCHAR(5) NOT NULL DEFAULT '"',
  header_row SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  skip_rows SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  date_format VARCHAR(40) NULL,
  decimal_separator VARCHAR(5) NULL,
  thousands_separator VARCHAR(5) NOT NULL DEFAULT '.',
  update_mode VARCHAR(30) NOT NULL DEFAULT 'update',
  mapping_json LONGTEXT NOT NULL,
  defaults_json LONGTEXT NULL,
  value_mappings_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_csv_profile (entity_type, name),
  INDEX idx_csv_profiles_entity (entity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS csv_imports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(40) NOT NULL,
  filename VARCHAR(255) NOT NULL,
  total_rows INT NOT NULL DEFAULT 0,
  imported_rows INT NOT NULL DEFAULT 0,
  skipped_rows INT NOT NULL DEFAULT 0,
  errors_json LONGTEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_csv_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS audit_log (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_errors (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_backups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL UNIQUE,
  reason VARCHAR(190) NULL,
  size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  checksum_sha256 VARCHAR(64) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_backups_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(30) PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- StayPilot V2.1.1 – Angebotsmodul, gemeinsame Dokumentnummern und Katalogmigration
CREATE TABLE IF NOT EXISTS document_sequences (
  document_type VARCHAR(30) NOT NULL,
  document_year SMALLINT UNSIGNED NOT NULL,
  current_value INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(document_type,document_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offers (
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
  email_content_json LONGTEXT NULL,
  public_page_json LONGTEXT NULL,
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
  deleted_at DATETIME NULL,
  deleted_by INT UNSIGNED NULL,
  delete_reason TEXT NULL,
  restored_at DATETIME NULL,
  restored_by INT UNSIGNED NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_items (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_events (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_services (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_service_translations (
  service_id INT UNSIGNED NOT NULL,
  language VARCHAR(5) NOT NULL,
  name VARCHAR(160) NULL,
  description VARCHAR(500) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(service_id,language),
  CONSTRAINT fk_offer_service_trans_service FOREIGN KEY(service_id) REFERENCES offer_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_apartment_type_translations (
  apartment_type_id INT UNSIGNED NOT NULL,
  language VARCHAR(5) NOT NULL,
  name VARCHAR(160) NULL,
  description TEXT NULL,
  public_description_html LONGTEXT NULL,
  amenities_html LONGTEXT NULL,
  seo_title VARCHAR(190) NULL,
  seo_description VARCHAR(320) NULL,
  request_hint VARCHAR(500) NULL,
  image_alt VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(apartment_type_id,language),
  CONSTRAINT fk_offer_type_trans_type FOREIGN KEY(apartment_type_id) REFERENCES apartment_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_text_templates (
  language VARCHAR(5) PRIMARY KEY,
  email_subject VARCHAR(255) NULL,
  greeting TEXT NULL,
  intro TEXT NULL,
  validity TEXT NULL,
  closing TEXT NULL,
  signature LONGTEXT NULL,
  footer TEXT NULL,
  terms LONGTEXT NULL,
  payment_info LONGTEXT NULL,
  additional_label VARCHAR(160) NULL,
  additional_info LONGTEXT NULL,
  remaining_payment TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_content_blocks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL UNIQUE,
  internal_name VARCHAR(190) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  show_by_default TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_offer_content_blocks_active(active,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_content_block_translations (
  block_id INT UNSIGNED NOT NULL,
  language VARCHAR(5) NOT NULL,
  title VARCHAR(190) NULL,
  content_html LONGTEXT NULL,
  PRIMARY KEY(block_id,language),
  CONSTRAINT fk_offer_content_block_trans_block FOREIGN KEY(block_id) REFERENCES offer_content_blocks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS amenity_catalog (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL UNIQUE,
  icon VARCHAR(40) NOT NULL DEFAULT '✓',
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_amenity_active_sort(active,sort_order,code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS amenity_translations (
  amenity_id INT UNSIGNED NOT NULL,
  language VARCHAR(5) NOT NULL,
  label VARCHAR(160) NOT NULL,
  PRIMARY KEY(amenity_id,language),
  CONSTRAINT fk_amenity_translation_catalog FOREIGN KEY(amenity_id) REFERENCES amenity_catalog(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS apartment_type_amenities (
  apartment_type_id INT UNSIGNED NOT NULL,
  amenity_id INT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY(apartment_type_id,amenity_id),
  CONSTRAINT fk_type_amenity_type FOREIGN KEY(apartment_type_id) REFERENCES apartment_types(id) ON DELETE CASCADE,
  CONSTRAINT fk_type_amenity_catalog FOREIGN KEY(amenity_id) REFERENCES amenity_catalog(id) ON DELETE CASCADE,
  INDEX idx_type_amenity_sort(apartment_type_id,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS apartment_type_images (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS site_pages (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_page_translations (
  page_id INT UNSIGNED NOT NULL,
  language VARCHAR(5) NOT NULL,
  title VARCHAR(190) NULL,
  navigation_label VARCHAR(120) NULL,
  seo_title VARCHAR(190) NULL,
  seo_description VARCHAR(320) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(page_id,language),
  CONSTRAINT fk_site_page_translation_page FOREIGN KEY(page_id) REFERENCES site_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_page_blocks (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_media (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_page_block_translations (
  block_id BIGINT UNSIGNED NOT NULL,
  language VARCHAR(5) NOT NULL,
  title VARCHAR(190) NULL,
  content_json LONGTEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(block_id,language),
  CONSTRAINT fk_site_block_translation_block FOREIGN KEY(block_id) REFERENCES site_page_blocks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;


CREATE TABLE IF NOT EXISTS booking_payment_schedule (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_payments (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_payment_allocations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id BIGINT UNSIGNED NOT NULL,
  schedule_id BIGINT UNSIGNED NULL,
  amount DECIMAL(12,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_allocation_payment FOREIGN KEY(payment_id) REFERENCES booking_payments(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_allocation_schedule FOREIGN KEY(schedule_id) REFERENCES booking_payment_schedule(id) ON DELETE SET NULL,
  INDEX idx_payment_alloc_payment(payment_id),
  INDEX idx_payment_alloc_schedule(schedule_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_documents (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_customer_access (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS booking_checkins (
  booking_id INT UNSIGNED NOT NULL PRIMARY KEY,
  status VARCHAR(30) NOT NULL DEFAULT 'open',
  planned_arrival_time TIME NULL,
  vehicle_plate VARCHAR(60) NULL,
  special_requests LONGTEXT NULL,
  consent_privacy TINYINT(1) NOT NULL DEFAULT 0,
  consent_house_rules TINYINT(1) NOT NULL DEFAULT 0,
  signature_name VARCHAR(190) NULL,
  submitted_at DATETIME NULL,
  reviewed_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  review_note LONGTEXT NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_booking_checkins_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_checkins_reviewed_by FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_booking_checkins_updated_by FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_booking_checkins_status(status,submitted_at,reviewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_checkin_uploads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NULL,
  size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by_user_id INT UNSIGNED NULL,
  uploaded_by_guest TINYINT(1) NOT NULL DEFAULT 0,
  note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_booking_checkin_uploads_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_checkin_uploads_user FOREIGN KEY(uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_booking_checkin_uploads_booking(booking_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS checkin_text_templates (
  language VARCHAR(5) NOT NULL PRIMARY KEY,
  email_subject VARCHAR(255) NULL,
  email_intro LONGTEXT NULL,
  reminder_text LONGTEXT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_checkin_text_templates_user FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_confirmation_templates (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS smart_arrival_exports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  export_type VARCHAR(40) NOT NULL DEFAULT 'prepared_csv',
  region VARCHAR(40) NOT NULL DEFAULT 'catalonia_mossos',
  status VARCHAR(30) NOT NULL DEFAULT 'prepared',
  date_from DATE NULL,
  date_to DATE NULL,
  file_path VARCHAR(500) NULL,
  json_path VARCHAR(500) NULL,
  row_count INT UNSIGNED NOT NULL DEFAULT 0,
  prepared_by INT UNSIGNED NULL,
  prepared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  marked_reported_at DATETIME NULL,
  marked_reported_by INT UNSIGNED NULL,
  note VARCHAR(1000) NULL,
  CONSTRAINT fk_smart_arrival_exports_prepared_by FOREIGN KEY(prepared_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_smart_arrival_exports_reported_by FOREIGN KEY(marked_reported_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_smart_arrival_exports_status(status,prepared_at),
  INDEX idx_smart_arrival_exports_period(date_from,date_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS smart_arrival_export_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  export_id BIGINT UNSIGNED NOT NULL,
  booking_id INT UNSIGNED NOT NULL,
  traveller_id INT UNSIGNED NULL,
  item_status VARCHAR(30) NOT NULL DEFAULT 'prepared',
  validation_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_smart_arrival_export_items_export FOREIGN KEY(export_id) REFERENCES smart_arrival_exports(id) ON DELETE CASCADE,
  CONSTRAINT fk_smart_arrival_export_items_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_smart_arrival_export_items_traveller FOREIGN KEY(traveller_id) REFERENCES booking_travellers(id) ON DELETE SET NULL,
  INDEX idx_smart_arrival_export_items_booking(booking_id,traveller_id),
  INDEX idx_smart_arrival_export_items_status(item_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
