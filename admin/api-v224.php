<?php
declare(strict_types=1);

/**
 * V2.2.4: Mitarbeiter-, Rollen- und Rechtezentrale.
 */

function role_access_v224(): never
{
    $catalog = Auth::capabilityCatalog();
    $roles = Auth::roleMatrix();
    $rows = db()->query("SELECT id,name,email,role,active,last_login_at,locked_until FROM users ORDER BY active DESC, name")->fetchAll();
    $users = [];
    foreach ($rows as $row) {
        $base = Auth::baseCapabilitiesForRole((string)$row['role']);
        $effective = Auth::effectiveCapabilitiesFor($row);
        $overrides = Auth::permissionOverrides((int)$row['id']);
        $users[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'email' => (string)$row['email'],
            'role' => (string)$row['role'],
            'active' => (int)$row['active'],
            'last_login_at' => $row['last_login_at'],
            'locked_until' => $row['locked_until'],
            'base_capabilities' => array_values($base),
            'effective_capabilities' => array_values($effective),
            'overrides' => $overrides,
        ];
    }
    json_response([
        'ok' => true,
        'users' => $users,
        'roles' => $roles,
        'capabilities' => $catalog,
        'current_user_id' => (int)(Auth::user()['id'] ?? 0),
    ]);
}

function save_user_access_v224(): never
{
    $d = request_data();
    $userId = (int)($d['user_id'] ?? 0);
    $stmt = db()->prepare('SELECT id,name,email,role,active FROM users WHERE id=? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) throw new NotFoundException('Benutzer nicht gefunden.');

    $current = Auth::user();
    $catalog = Auth::capabilityCatalog();
    $incoming = $d['overrides'] ?? [];
    if (!is_array($incoming)) throw new ValidationException('Ungültige Rechteauswahl.');

    $oldOverrides = Auth::permissionOverrides($userId);
    $newOverrides = [];
    foreach ($catalog as $capability => $_meta) {
        $mode = (string)($incoming[$capability] ?? 'inherit');
        if (!in_array($mode, ['inherit','allow','deny'], true)) {
            throw new ValidationException('Ungültiger Rechtewert für ' . $capability . '.');
        }
        if ($mode !== 'inherit') $newOverrides[$capability] = $mode;
    }

    if ($userId === (int)($current['id'] ?? 0)) {
        foreach (['users_manage','settings_manage','system_view'] as $critical) {
            if (($newOverrides[$critical] ?? '') === 'deny') {
                throw new ConflictException('Das eigene Konto darf nicht von Benutzer-/Systemrechten ausgeschlossen werden.');
            }
        }
    }

    db()->beginTransaction();
    try {
        db()->prepare('DELETE FROM user_permission_overrides WHERE user_id=?')->execute([$userId]);
        if ($newOverrides) {
            $insert = db()->prepare('INSERT INTO user_permission_overrides(user_id,capability,allowed,updated_by) VALUES(?,?,?,?)');
            foreach ($newOverrides as $capability => $mode) {
                $insert->execute([$userId, $capability, $mode === 'allow' ? 1 : 0, (int)($current['id'] ?? 0)]);
            }
        }
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }

    AuditLogger::record('user', $userId, 'permissions_update', ['overrides' => $oldOverrides], ['overrides' => $newOverrides], 'Individuelle Benutzerrechte gespeichert');
    json_response(['ok' => true, 'message' => 'Rechte wurden gespeichert.', 'user_id' => $userId]);
}

function clear_user_access_v224(): never
{
    $d = request_data();
    $userId = (int)($d['user_id'] ?? 0);
    $stmt = db()->prepare('SELECT id,name,email,role FROM users WHERE id=? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) throw new NotFoundException('Benutzer nicht gefunden.');
    $old = Auth::permissionOverrides($userId);
    db()->prepare('DELETE FROM user_permission_overrides WHERE user_id=?')->execute([$userId]);
    AuditLogger::record('user', $userId, 'permissions_reset', ['overrides' => $old], ['overrides' => []], 'Benutzerrechte auf Rollenstandard zurückgesetzt');
    json_response(['ok' => true, 'message' => 'Rechte wurden auf den Rollenstandard zurückgesetzt.']);
}

function capability_for_action_v224(string $action, string $method): ?string
{
    $method = strtoupper($method);
    $write = $method !== 'GET';

    $map = [
        'dashboard' => 'dashboard_view',
        'statistics' => 'reports_view', 'statistics_v226' => 'reports_view',
        'calendar' => 'calendar_view',
        'houses' => 'masterdata_view', 'house' => 'masterdata_view', 'apartment_types' => 'masterdata_view', 'apartment_type' => 'masterdata_view',
        'apartment_type_management_v216' => 'masterdata_view', 'apartment_type_editor_v216' => 'masterdata_view', 'apartments' => 'masterdata_view',
        'prices' => 'prices_view', 'pricing_v205' => 'prices_view', 'booking_channels' => 'prices_view', 'price_check_v205' => 'prices_view',
        'guests' => 'guests_view', 'guest_categories' => 'guests_view', 'travellers' => 'guests_view', 'police' => 'guests_view', 'meals' => 'meals_manage',
        'bookings' => 'bookings_view', 'booking' => 'bookings_view', 'bookings_v235' => 'bookings_view', 'booking_repair_context_v235' => 'bookings_view', 'data_repair_overview_v235' => 'system_view',
        'offers_v214' => 'offers_view', 'offer_v214' => 'offers_view', 'offer_form_data_v214' => 'offers_view', 'offer_communication_v217' => 'offers_view',
        'booking_confirmation_queue_v220' => 'offers_view', 'booking_confirmation_data_v220' => 'offers_view',
        'billing_overview_v223' => 'billing_view', 'booking_billing_v223' => 'billing_view', 'billing_overview_v230' => 'billing_view', 'booking_billing_v230' => 'billing_view', 'payment_attention_v220' => 'billing_view',
        'housekeeping' => 'housekeeping_view', 'housekeeping_teams' => 'housekeeping_view', 'housekeeping_release_queue' => 'housekeeping_view', 'housekeeping_incidents' => 'housekeeping_view', 'housekeeping_dashboard_v210' => 'housekeeping_view',
        'guest_portal_contents' => 'website_view', 'public_guest_settings_v210' => 'website_view', 'site_editor_data_v218' => 'website_view',
        'communications' => 'communications_view', 'communications_v232' => 'communications_view', 'communication_center_v236' => 'communications_view', 'direct_customer_email_context_v236' => 'communications_view',
        'settings' => 'settings_view', 'diagnostics' => 'system_view', 'audit' => 'system_view', 'backups' => 'backups_manage',
        'pms_consistency_review_v236109' => 'system_view',
        'pms_flow_review_v236113' => 'bookings_view',
        'pms_billing_review_v236114' => 'billing_view',
        'pms_housekeeping_review_v236115' => 'housekeeping_view',
        'product_readiness_security_status_v236110' => 'system_view',
        'csv_profiles' => 'import_export_manage',
    ];
    if (!$write) return $map[$action] ?? null;

    $writeMap = [
        'save_house' => 'masterdata_manage', 'delete_house' => 'masterdata_manage', 'save_apartment_type' => 'masterdata_manage', 'delete_apartment_type' => 'masterdata_manage',
        'save_apartment_type_v216' => 'masterdata_manage', 'save_amenity_v216' => 'masterdata_manage', 'upload_type_image_v216' => 'masterdata_manage', 'delete_type_image_v216' => 'masterdata_manage', 'set_type_cover_v216' => 'masterdata_manage', 'save_type_image_meta_v216' => 'masterdata_manage', 'bulk_create_apartments_v216' => 'masterdata_manage', 'save_apartment' => 'masterdata_manage', 'delete_apartment' => 'masterdata_manage',
        'save_season_v205' => 'prices_manage', 'delete_season_v205' => 'prices_manage', 'save_season_period_v205' => 'prices_manage', 'delete_season_period_v205' => 'prices_manage', 'save_season_matrix_v205' => 'prices_manage', 'save_type_minimums_v205' => 'prices_manage', 'save_special_price_v205' => 'prices_manage', 'delete_special_price_v205' => 'prices_manage', 'save_booking_channel' => 'prices_manage', 'delete_booking_channel' => 'prices_manage', 'save_prices' => 'prices_manage', 'save_length_discount' => 'prices_manage', 'delete_length_discount' => 'prices_manage', 'save_discount_code' => 'prices_manage', 'delete_discount_code' => 'prices_manage', 'save_season' => 'prices_manage', 'delete_season' => 'prices_manage', 'save_block' => 'prices_manage', 'delete_block' => 'prices_manage', 'save_missing_offer_price_v215' => 'prices_manage',
        'save_guest' => 'guests_manage', 'delete_guest' => 'guests_manage', 'save_guest_category' => 'guests_manage', 'delete_guest_category' => 'guests_manage', 'save_travellers' => 'guests_manage', 'police_status' => 'police_manage',
        'save_booking' => 'bookings_manage', 'delete_booking' => 'bookings_manage', 'set_booking_status' => 'bookings_manage', 'unassign_booking' => 'bookings_manage', 'move_preview' => 'bookings_manage', 'move_booking' => 'bookings_manage', 'resize_booking' => 'bookings_manage', 'assign_booking_apartment_v235' => 'bookings_manage', 'set_booking_clarification_v235' => 'bookings_manage', 'reject_booking_clarification_v235' => 'bookings_manage', 'send_booking_status_v235' => 'communications_manage',
        'offer_quote_v214' => 'offers_manage', 'save_offer_v214' => 'offers_manage', 'revise_offer_v214' => 'offers_manage', 'send_offer_v214' => 'offers_manage', 'archive_offer_v214' => 'offers_manage', 'convert_offer_v214' => 'offers_manage', 'preview_offer_communication_v217' => 'offers_manage', 'save_offer_communication_v217' => 'offers_manage', 'confirm_offer_booking_v220' => 'offers_manage', 'booking_no_availability_action_v221' => 'offers_manage',
        'save_offer_service_v214' => 'offers_manage', 'delete_offer_service_v214' => 'offers_manage', 'save_offer_content_block_v214' => 'offers_manage', 'delete_offer_content_block_v214' => 'offers_manage', 'save_offer_translations_v214' => 'offers_manage', 'save_offer_templates_v214' => 'offers_manage', 'save_offer_settings_v214' => 'offers_manage',
        'save_booking_payment_v223' => 'billing_manage', 'update_payment_schedule_v223' => 'billing_manage', 'create_invoice_v223' => 'billing_manage', 'resend_customer_status_v223' => 'billing_manage', 'whatsapp_booking_open_v223' => 'billing_manage', 'create_billing_document_v230' => 'billing_manage', 'send_billing_document_v230' => 'billing_manage', 'send_payment_reminder_v230' => 'billing_manage', 'recalculate_payment_schedule_v235' => 'billing_manage',
        'save_task' => 'housekeeping_manage', 'task_status' => 'housekeeping_manage', 'save_task_progress' => 'housekeeping_manage', 'delete_task' => 'housekeeping_manage', 'generate_housekeeping' => 'housekeeping_manage', 'save_housekeeping_team' => 'housekeeping_manage', 'delete_housekeeping_team' => 'housekeeping_manage', 'save_housekeeping_member' => 'housekeeping_manage', 'save_staff_account_v210' => 'housekeeping_manage', 'deactivate_staff_v210' => 'housekeeping_manage', 'delete_housekeeping_member' => 'housekeeping_manage', 'housekeeping_final_release' => 'housekeeping_release', 'housekeeping_incident_review' => 'housekeeping_inspect', 'guest_portal_link' => 'housekeeping_release', 'whatsapp_task_preview' => 'communications_manage', 'whatsapp_task_open' => 'communications_manage', 'send_task_email' => 'communications_manage', 'direct_customer_email_preview_v236' => 'communications_manage', 'send_direct_customer_email_v236' => 'communications_manage',
        'save_guest_portal_content' => 'website_manage', 'delete_guest_portal_content' => 'website_manage', 'save_public_guest_settings_v210' => 'website_manage', 'save_site_page_v218' => 'website_manage', 'delete_site_page_v218' => 'website_manage', 'duplicate_site_page_v218' => 'website_manage', 'save_site_block_v218' => 'website_manage', 'delete_site_block_v218' => 'website_manage', 'move_site_block_v218' => 'website_manage', 'save_site_design_v218' => 'website_manage', 'upload_site_media_v218' => 'website_manage', 'save_site_media_meta_v218' => 'website_manage', 'delete_site_media_v218' => 'website_manage',
        'save_settings' => 'settings_manage', 'repair_offer_events_note_v235' => 'system_view',
        'pms_consistency_action_v236109' => 'system_view',
        'pms_flow_action_v236113' => 'bookings_manage',
        'pms_billing_action_v236114' => 'billing_manage',
        'pms_housekeeping_action_v236115' => 'housekeeping_view',
        'csv_preview_v205' => 'import_export_manage', 'csv_repreview_v205' => 'import_export_manage', 'csv_import_v205' => 'import_export_manage', 'csv_preview' => 'import_export_manage', 'csv_import' => 'import_export_manage', 'create_backup' => 'backups_manage',
    ];
    return $writeMap[$action] ?? $map[$action] ?? null;
}
