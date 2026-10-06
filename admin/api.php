<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/Integrations/IntegrationFactory.php';
require_once __DIR__ . '/api-v205.php';
require_once __DIR__ . '/api-v208.php';
require_once __DIR__ . '/api-v209.php';
require_once __DIR__ . '/api-v210.php';
require_once __DIR__ . '/api-v214.php';
require_once __DIR__ . '/api-v216.php';
require_once __DIR__ . '/api-v217.php';
require_once __DIR__ . '/api-v218.php';
require_once __DIR__ . '/api-v220.php';
require_once __DIR__ . '/api-v223.php';
require_once __DIR__ . '/api-v224.php';
require_once __DIR__ . '/api-v226.php';
require_once __DIR__ . '/api-v228.php';
require_once __DIR__ . '/api-v229.php';
require_once __DIR__ . '/api-v230.php';
require_once __DIR__ . '/api-v232.php';
require_once __DIR__ . '/api-v235.php';
require_once __DIR__ . '/api-v236.php';
require_once __DIR__ . '/api-v236-communication.php';
require_once __DIR__ . '/api-v236-tasks.php';
require_once __DIR__ . '/api-v236-delete-center.php';
require_once __DIR__ . '/api-v236-product-readiness.php';
require_once __DIR__ . '/api-v237-smart-arrival.php';
require_once __DIR__ . '/api-vermietung-sync.php';
$user = Auth::requireLogin();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'dashboard');
require_safe_api_method_v236110($action);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    verify_csrf();
}

try {
    authorize_action($action, $user);
    switch ($action) {
        case 'dashboard': dashboard();
        case 'housekeeping_dashboard_v210': housekeeping_dashboard_v210();
        case 'statistics': statistics_data();
        case 'statistics_v226': statistics_v226_data();
        case 'houses': list_houses();
        case 'house': get_house();
        case 'save_house': save_house();
        case 'delete_house': delete_house();
        case 'apartment_types': list_apartment_types();
        case 'apartment_type': get_apartment_type();
        case 'apartment_type_management_v216': apartment_type_management_v216();
        case 'apartment_type_editor_v216': apartment_type_editor_v216();
        case 'save_apartment_type_v216': save_apartment_type_v216();
        case 'save_amenity_v216': save_amenity_v216();
        case 'upload_type_image_v216': upload_type_image_v216();
        case 'delete_type_image_v216': delete_type_image_v216();
        case 'set_type_cover_v216': set_type_cover_v216();
        case 'save_type_image_meta_v216': save_type_image_meta_v216();
        case 'capacity_check_v216': capacity_check_v216();
        case 'cancellation_quote_v216': cancellation_quote_v216();
        case 'save_apartment_type': save_apartment_type();
        case 'delete_apartment_type': delete_apartment_type();
        case 'users': list_users();
        case 'role_matrix': role_matrix_v208();
        case 'user_access_v224': role_access_v224();
        case 'save_user_access_v224': save_user_access_v224();
        case 'clear_user_access_v224': clear_user_access_v224();
        case 'save_user': save_user();
        case 'delete_user': delete_user();
        case 'housekeeping_teams': housekeeping_teams_v208();
        case 'save_housekeeping_team': save_housekeeping_team_v208();
        case 'delete_housekeeping_team': delete_housekeeping_team_v208();
        case 'save_housekeeping_member': save_housekeeping_member_v209();
        case 'save_staff_account_v210': save_staff_account_v210();
        case 'deactivate_staff_v210': deactivate_staff_v210();
        case 'delete_housekeeping_member': delete_housekeeping_member_v208();
        case 'audit': audit_data();
        case 'generate_staypilot_export_key_v1': generate_staypilot_export_key_v1();
        case 'diagnostics': diagnostics_data();
        case 'backups': backups_data();
        case 'verify_latest_backup_v236112': verify_latest_backup_v236112();
        case 'create_backup': create_backup_action();
        case 'create_pre_update_backup_v236112': create_pre_update_backup_v236112();
        case 'client_error': client_error_action();
        case 'apartments': list_apartments();
        case 'bulk_create_apartments_v216': bulk_create_apartments_v216();
        case 'save_apartment': save_apartment();
        case 'delete_apartment': delete_apartment();
        case 'guests': list_guests();
        case 'guest_categories': guest_categories();
        case 'save_guest_category': save_guest_category();
        case 'delete_guest_category': delete_guest_category();
        case 'save_guest': save_guest();
        case 'delete_guest': delete_guest();
        case 'offers_v214': offers_v214();
        case 'offer_form_data_v214': offer_form_data_v214();
        case 'offer_v214': offer_v214();
        case 'offer_communication_v217': offer_communication_v217();
        case 'preview_offer_communication_v217': preview_offer_communication_v217();
        case 'save_offer_communication_v217': save_offer_communication_v217();
        case 'offer_quote_v214': offer_quote_v214();
        case 'save_missing_offer_price_v215': save_missing_offer_price_v215();
        case 'save_offer_v214': save_offer_v214();
        case 'revise_offer_v214': revise_offer_v214();
        case 'send_offer_v214': send_offer_v214();
        case 'archive_offer_v214': archive_offer_v214();
        case 'convert_offer_v214': convert_offer_v214();
        case 'offer_services_v214': offer_services_v214();
        case 'save_offer_service_v214': save_offer_service_v214();
        case 'delete_offer_service_v214': delete_offer_service_v214();
        case 'offer_content_blocks_v214': offer_content_blocks_v214();
        case 'save_offer_content_block_v214': save_offer_content_block_v214();
        case 'delete_offer_content_block_v214': delete_offer_content_block_v214();
        case 'offer_translations_v214': offer_translations_v214();
        case 'save_offer_translations_v214': save_offer_translations_v214();
        case 'save_offer_templates_v214': save_offer_templates_v214();
        case 'offer_settings_v214': offer_settings_v214();
        case 'save_offer_settings_v214': save_offer_settings_v214();
        case 'booking_confirmation_queue_v220': booking_confirmation_queue_v220();
        case 'booking_confirmation_data_v220': booking_confirmation_data_v220();
        case 'confirm_offer_booking_v220': confirm_offer_booking_v220();
        case 'booking_no_availability_action_v221': booking_no_availability_action_v221();
        case 'payment_attention_v220': payment_attention_v220();
        case 'billing_overview_v223': billing_overview_v223();
        case 'booking_billing_v223': booking_billing_v223();
        case 'billing_overview_v230': billing_overview_v230();
        case 'booking_billing_v230': booking_billing_v230();
        case 'save_booking_payment_v223': save_booking_payment_v223();
        case 'update_payment_schedule_v223': update_payment_schedule_v223();
        case 'create_invoice_v223': create_invoice_v223();
        case 'resend_customer_status_v223': resend_customer_status_v223();
        case 'whatsapp_booking_open_v223': whatsapp_booking_open_v223();
        case 'create_billing_document_v230': create_billing_document_v230();
        case 'send_billing_document_v230': send_billing_document_v230();
        case 'send_payment_reminder_v230': send_payment_reminder_v230();
        case 'billing_number_counters_v23651': billing_number_counters_v23651();
        case 'billing_number_counter_set_v23651': billing_number_counter_set_v23651();
        case 'save_booking_payment_v23652': save_booking_payment_v23652();
        case 'record_refund_v23653': record_refund_v23653();
        case 'task_center_today_v23656': task_center_today_v23656();
        case 'housekeeping_stale_tasks_v236105': housekeeping_stale_tasks_v236105();
        case 'save_internal_task_v23656': save_internal_task_v23656();
        case 'task_status_v23656': task_status_v23656();
        case 'task_event_status_v23661': task_event_status_v23661();
        case 'housekeeping_stale_task_action_v236105': housekeeping_stale_task_action_v236105();
        case 'delete_center_data_v23663': delete_center_data_v23663();
        case 'delete_center_soft_delete_v23663': delete_center_soft_delete_v23663();
        case 'delete_center_restore_v23663': delete_center_restore_v23663();
        case 'delete_center_change_pin_v23663': delete_center_change_pin_v23663();
        case 'delete_center_file_trash_v23664': delete_center_file_trash_v23664();
        case 'delete_center_file_restore_v23664': delete_center_file_restore_v23664();
        case 'delete_center_sync_status_v23697': delete_center_sync_status_v23697();
        case 'delete_center_global_sync_v23697': delete_center_global_sync_v23697();
        case 'pms_consistency_review_v236109': pms_consistency_review_v236109();
        case 'pms_consistency_action_v236109': pms_consistency_action_v236109();
        case 'pms_flow_review_v236113': pms_flow_review_v236113();
        case 'pms_flow_action_v236113': pms_flow_action_v236113();
        case 'pms_billing_review_v236114': pms_billing_review_v236114();
        case 'pms_housekeeping_review_v236115': pms_housekeeping_review_v236115();
        case 'pms_billing_action_v236114': pms_billing_action_v236114();
        case 'pms_housekeeping_action_v236115': pms_housekeeping_action_v236115();
        case 'pms_lifecycle_review_v236117': pms_lifecycle_review_v236117();
        case 'pms_lifecycle_action_v236117': pms_lifecycle_action_v236117();
        case 'checkin_overview_v228': checkin_overview_v228();
        case 'checkin_detail_v228': checkin_detail_v228();
        case 'send_checkin_request_v228': send_checkin_request_v228();
        case 'whatsapp_checkin_open_v228': whatsapp_checkin_open_v228();
        case 'upload_checkin_file_v228': upload_checkin_file_v228();
        case 'delete_checkin_file_v228': delete_checkin_file_v228();
        case 'review_checkin_v228': review_checkin_v228();
        case 'checkin_documents_v229': checkin_documents_v229();
        case 'generate_checkin_documents_v229': generate_checkin_documents_v229();
        case 'send_checkin_documents_v229': send_checkin_documents_v229();
        case 'checkin_communication_check_v229': checkin_communication_check_v229();
        case 'smart_arrival_export_preview_v237': smart_arrival_export_preview_v237();
        case 'smart_arrival_export_prepare_v237': smart_arrival_export_prepare_v237();
        case 'smart_arrival_export_mark_reported_v237': smart_arrival_export_mark_reported_v237();
        case 'smart_arrival_readiness_v238': smart_arrival_readiness_v238();
        case 'smart_arrival_manual_entry_save_v239': smart_arrival_manual_entry_save_v239();
        case 'smart_arrival_manual_entries_v239': smart_arrival_manual_entries_v239();
        case 'smart_arrival_paper_scan_link_v240': smart_arrival_paper_scan_link_v240();
        case 'site_editor_data_v218': site_editor_data_v218();
        case 'save_site_page_v218': save_site_page_v218();
        case 'delete_site_page_v218': delete_site_page_v218();
        case 'duplicate_site_page_v218': duplicate_site_page_v218();
        case 'save_site_block_v218': save_site_block_v218();
        case 'duplicate_site_block_v218': duplicate_site_block_v218();
        case 'delete_site_block_v218': delete_site_block_v218();
        case 'move_site_block_v218': move_site_block_v218();
        case 'save_site_design_v218': save_site_design_v218();
        case 'upload_site_media_v218': upload_site_media_v218();
        case 'save_site_media_meta_v218': save_site_media_meta_v218();
        case 'delete_site_media_v218': delete_site_media_v218();
        case 'bookings': list_bookings();
        case 'booking': get_booking();
        case 'travellers': travellers_data();
        case 'save_travellers': save_travellers();
        case 'police': police_data();
        case 'police_status': police_status();
        case 'save_booking': save_booking();
        case 'delete_booking': delete_booking();
        case 'set_booking_status': set_booking_status();
        case 'unassign_booking': unassign_booking();
        case 'price_quote': price_quote();
        case 'calendar': calendar_data();
        case 'move_preview': move_preview();
        case 'move_booking': move_booking();
        case 'resize_booking': resize_booking();
        case 'housekeeping': housekeeping_data();
        case 'generate_housekeeping': generate_housekeeping();
        case 'save_task': save_task_v209();
        case 'task_status': task_status_v209();
        case 'save_task_progress': save_task_progress_v209();
        case 'delete_task': delete_task();
        case 'whatsapp_task_preview': whatsapp_task_preview_v208();
        case 'whatsapp_task_open': whatsapp_task_open_v208();
        case 'send_task_email': send_task_email_v208();
        case 'send_housekeeping_list_v23624': send_housekeeping_list_v23624();
        case 'meals': meals_data();
        case 'pricing_v205': pricing_v205_data();
        case 'save_season_v205': save_season_v205();
        case 'delete_season_v205': delete_season_v205();
        case 'save_season_period_v205': save_season_period_v205();
        case 'delete_season_period_v205': delete_season_period_v205();
        case 'save_season_matrix_v205': save_season_matrix_v205();
        case 'save_type_minimums_v205': save_type_minimums_v205();
        case 'save_special_price_v205': save_special_price_v205();
        case 'delete_special_price_v205': delete_special_price_v205();
        case 'booking_channels': booking_channels_data();
        case 'save_booking_channel': save_booking_channel();
        case 'delete_booking_channel': delete_booking_channel();
        case 'price_check_v205': price_check_v205();
        case 'prices': prices_data();
        case 'save_prices': save_prices();
        case 'save_length_discount': save_length_discount();
        case 'delete_length_discount': delete_length_discount();
        case 'save_discount_code': save_discount_code();
        case 'delete_discount_code': delete_discount_code();
        case 'save_season': save_season();
        case 'delete_season': delete_season();
        case 'save_block': save_block();
        case 'delete_block': delete_block();
        case 'csv_preview_v205': csv_preview_v205();
        case 'csv_repreview_v205': csv_repreview_v205();
        case 'csv_import_v205': csv_import_v205();
        case 'csv_profiles': csv_profiles();
        case 'csv_preview': csv_preview();
        case 'csv_import': csv_import();
        case 'integrations': integrations_data();
        case 'save_integration': save_integration();
        case 'save_mappings': save_mappings();
        case 'test_integration': test_integration();
        case 'sync_integration': sync_integration();
        case 'sync_logs': sync_logs();
        case 'settings': settings_data();
        case 'smtp_settings': smtp_settings_v208();
        case 'save_smtp_settings': save_smtp_settings_v208();
        case 'test_smtp': test_smtp_v208();
        case 'send_test_email': send_test_email_v208();
        case 'communications': communications_v208();
        case 'communications_v232': communications_v232();
        case 'communication_center_v236': communication_center_v236();
        case 'mail_account_v236125': mail_account_v236125();
        case 'save_mail_account_v236125': save_mail_account_v236125();
        case 'test_imap_v236125': test_imap_v236125();
        case 'mail_inbox_v236125': mail_inbox_v236125();
        case 'mail_message_v236126': mail_message_v236126();
        case 'mail_folders_v236132': mail_folders_v236132();
        case 'mail_action_v236132': mail_action_v236132();
        case 'mail_sent_log_v236127': mail_sent_log_v236127();
        case 'email_templates_v236133': email_templates_v236133();
        case 'save_email_template_v236133': save_email_template_v236133();
        case 'delete_email_template_v236133': delete_email_template_v236133();
        case 'send_free_email_v236126': send_free_email_v236126();
        case 'whatsapp_templates_v236': whatsapp_templates_v236();
        case 'save_whatsapp_templates_v236': save_whatsapp_templates_v236();
        case 'whatsapp_settings_v236': whatsapp_settings_v236();
        case 'save_whatsapp_settings_v236': save_whatsapp_settings_v236();
        case 'communication_readiness_v236': communication_readiness_v236();
        case 'whatsapp_booking_prepare_v236': whatsapp_booking_prepare_v236();
        case 'communication_status_settings_v236': communication_status_settings_v236();
        case 'save_communication_status_settings_v236': save_communication_status_settings_v236();
        case 'send_customer_status_notification_v236': send_customer_status_notification_v236();
        case 'communication_automation_rules_v236': communication_automation_rules_v236();
        case 'save_communication_automation_rules_v236': save_communication_automation_rules_v236();
        case 'communication_automation_preview_v236': communication_automation_preview_v236();
        case 'direct_customer_email_context_v236': direct_customer_email_context_v236();
        case 'direct_customer_email_preview_v236': direct_customer_email_preview_v236();
        case 'send_direct_customer_email_v236': send_direct_customer_email_v236();
        case 'guest_portal_links_v232': guest_portal_links_v232();
        case 'bookings_v232': bookings_v232();
        case 'bookings_v235': bookings_v235();
        case 'data_repair_overview_v235': data_repair_overview_v235();
        case 'document_templates_v236': document_templates_v236();
        case 'document_template_v236': document_template_v236();
        case 'save_document_template_v236': save_document_template_v236();
        case 'archive_document_template_v236': archive_document_template_v236();
        case 'duplicate_document_template_v236': duplicate_document_template_v236();
        case 'preview_document_template_v236': preview_document_template_v236();
        case 'preview_document_template_draft_v236': preview_document_template_draft_v236();
        case 'send_document_template_test_email_v236': send_document_template_test_email_v236();
        case 'document_mail_attachments_v236': document_mail_attachments_v236();
        case 'save_document_mail_attachments_v236': save_document_mail_attachments_v236();
        case 'booking_repair_context_v235': booking_repair_context_v235();
        case 'assign_booking_apartment_v235': assign_booking_apartment_v235();
        case 'set_booking_clarification_v235': set_booking_clarification_v235();
        case 'reject_booking_clarification_v235': reject_booking_clarification_v235();
        case 'recalculate_payment_schedule_v235': recalculate_payment_schedule_v235();
        case 'send_booking_status_v235': send_booking_status_v235();
        case 'repair_offer_events_note_v235': repair_offer_events_note_v235();
        case 'notifications': notifications_v209();
        case 'notifications_read': notifications_read_v209();
        case 'housekeeping_release_queue': housekeeping_release_queue_v209();
        case 'housekeeping_final_release': housekeeping_final_release_v210();
        case 'guest_portal_link': guest_portal_link_v210();
        case 'housekeeping_incidents': housekeeping_incidents_v209();
        case 'housekeeping_incident_review': housekeeping_incident_review_v209();
        case 'guest_portal_contents': guest_portal_contents_v209();
        case 'save_guest_portal_content': save_guest_portal_content_v209();
        case 'delete_guest_portal_content': delete_guest_portal_content_v209();
        case 'public_guest_settings_v210': public_guest_settings_v210();
        case 'save_public_guest_settings_v210': save_public_guest_settings_v210();
        case 'save_settings': save_settings();
        case 'change_password': change_password();
        case 'booking_accounting_review_v236119': booking_accounting_review_v236119();
        case 'booking_accounting_action_v236119': booking_accounting_action_v236119();
        case 'pms_orphan_cleanup_review_v236122': pms_orphan_cleanup_review_v236122();
        case 'pms_orphan_cleanup_action_v236122': pms_orphan_cleanup_action_v236122();
        case 'pms_orphan_cleanup_delete_v236123': pms_orphan_cleanup_delete_v236123();
        case 'product_readiness_security_status_v236110': product_readiness_security_status_v236110();
        default: json_response(['ok'=>false,'message'=>'Unbekannte Aktion.'],404);
    }
} catch (MissingPriceException $e) {
    json_response(['ok'=>false,'message'=>$e->getMessage(),'code'=>$e->errorCode(),'details'=>$e->details(),'request_id'=>AppLogger::requestId()],$e->status());
} catch (HttpException $e) {
    json_response(['ok'=>false,'message'=>$e->getMessage(),'code'=>$e->errorCode(),'request_id'=>AppLogger::requestId()],$e->status());
} catch (RuntimeException $e) {
    AppLogger::info($e->getMessage(), ['action'=>$action], 'validation');
    json_response(['ok'=>false,'message'=>$e->getMessage(),'code'=>'validation_error','request_id'=>AppLogger::requestId()],422);
} catch (Throwable $e) {
    $requestId = AppLogger::error($e, ['action'=>$action,'input'=>request_data()], 'api');
    json_response(['ok'=>false,'message'=>'Die Aktion konnte wegen eines Serverfehlers nicht abgeschlossen werden.','code'=>'server_error','request_id'=>$requestId],500);
}


function is_mutating_admin_action_v236110(string $action): bool
{
    $readOnly = [
        'dashboard','statistics','statistics_v226','houses','house','apartment_types','apartment_type','apartment_type_management_v216','apartment_type_editor_v216','apartments','guests','guest_categories','bookings','booking','calendar','meals','prices','pricing_v205','booking_channels','price_check_v205','capacity_check_v216','cancellation_quote_v216','move_preview','settings','smart_arrival_export_preview_v237','smart_arrival_readiness_v238','smart_arrival_paper_scan_link_v240','audit','diagnostics','backups','verify_latest_backup_v236112','notifications','client_error',
        'offers_v214','offer_form_data_v214','offer_v214','offer_communication_v217','offer_services_v214','offer_content_blocks_v214','offer_translations_v214','offer_settings_v214','offer_quote_v214','booking_confirmation_queue_v220','booking_confirmation_data_v220','payment_attention_v220','billing_overview_v223','booking_billing_v223','billing_overview_v230','booking_billing_v230','billing_number_counters_v23651',
        'communications','communications_v232','communication_center_v236','mail_account_v236125','mail_inbox_v236125','mail_message_v236126','mail_folders_v236132','mail_sent_log_v236127','email_templates_v236133','whatsapp_templates_v236','whatsapp_settings_v236','communication_readiness_v236','communication_status_settings_v236','direct_customer_email_context_v236','guest_portal_links_v232','bookings_v232',
        'checkin_overview_v228','checkin_detail_v228','checkin_documents_v229','checkin_communication_check_v229','smart_arrival_export_preview_v237','smart_arrival_readiness_v238','smart_arrival_paper_scan_link_v240','housekeeping','housekeeping_dashboard_v210','housekeeping_teams','housekeeping_release_queue','housekeeping_incidents','guest_portal_link','guest_portal_contents','public_guest_settings_v210','task_center_today_v23656','housekeeping_stale_tasks_v236105','task_event_status_v23661','delete_center_data_v23663','delete_center_file_trash_v23664','delete_center_file_restore_v23664','delete_center_sync_status_v23697','pms_consistency_review_v236109','pms_flow_review_v236113','pms_billing_review_v236114','pms_housekeeping_review_v236115','pms_lifecycle_review_v236117','booking_accounting_review_v236119','booking_accounting_review_v236119','pms_orphan_cleanup_review_v236122','site_editor_data_v218','document_templates_v236','preview_document_template_v236','preview_document_template_draft_v236','product_readiness_security_status_v236110'
    ];
    if (in_array($action, $readOnly, true)) {
        return false;
    }
    $mutatingPrefixes = ['save_','delete_','create_','update_','send_','archive_','convert_','revise_','confirm_','reject_','assign_','set_','unassign_','resize_','upload_','record_','restore_','sync_','test_','generate_','review_','repair_','recalculate_','duplicate_','clear_','deactivate_','change_'];
    foreach ($mutatingPrefixes as $prefix) {
        if (str_starts_with($action, $prefix)) {
            return true;
        }
    }
    $mutatingExact = [
        'booking_no_availability_action_v221','police_status','move_booking','task_status','task_status_v23656','task_event_status_v23661','housekeeping_final_release','housekeeping_incident_review','notifications_read','delete_center_soft_delete_v23663','delete_center_restore_v23663','delete_center_change_pin_v23663','delete_center_file_restore_v23664','delete_center_global_sync_v23697','pms_consistency_action_v236109','pms_flow_action_v236113','pms_billing_action_v236114','pms_housekeeping_action_v236115','pms_lifecycle_action_v236117','booking_accounting_action_v236119','booking_accounting_action_v236119','pms_orphan_cleanup_action_v236122','pms_orphan_cleanup_delete_v236123','housekeeping_stale_task_action_v236105','save_document_template_v236','archive_document_template_v236','duplicate_document_template_v236','send_document_template_test_email_v236','save_document_mail_attachments_v236','smart_arrival_export_prepare_v237','smart_arrival_export_mark_reported_v237',
        'mail_action_v236132','billing_number_counter_set_v23651'
    ];
    return in_array($action, $mutatingExact, true);
}

function require_safe_api_method_v236110(string $action): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'GET' && is_mutating_admin_action_v236110($action)) {
        try {
            AuditLogger::record('security', 0, 'unsafe_get_blocked', null, ['action' => $action, 'method' => $method], 'Schreibende API-Aktion per GET blockiert');
        } catch (Throwable) {
            // Protokollfehler dürfen den Schutz nicht aufheben.
        }
        json_response(['ok' => false, 'message' => 'Diese Aktion ist aus Sicherheitsgründen nur per POST mit gültigem Sicherheitstoken erlaubt.', 'code' => 'unsafe_method_blocked', 'action' => $action], 405);
    }
}

function product_readiness_security_status_v236110(): never
{
    $known = [
        'save_guest','delete_guest','save_booking','delete_site_page_v218','delete_center_global_sync_v23697','pms_consistency_action_v236109','pms_flow_action_v236113','pms_billing_action_v236114','pms_housekeeping_action_v236115','pms_lifecycle_action_v236117','booking_accounting_action_v236119','booking_accounting_action_v236119','pms_orphan_cleanup_action_v236122','pms_orphan_cleanup_delete_v236123','create_invoice_v223','send_payment_reminder_v230','upload_site_media_v218','sync_integration','test_smtp'
    ];
    $protected = [];
    foreach ($known as $action) {
        $protected[$action] = is_mutating_admin_action_v236110($action);
    }
    json_response(['ok' => true, 'message' => 'API-Schreibschutz ist aktiv. Schreibende Aktionen werden per GET blockiert und benötigen POST + CSRF.', 'protected_actions' => $protected]);
}

function authorize_action(string $action, array $user): void
{
    $role = (string)($user['role'] ?? 'readonly');
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($action, ['client_error','change_password','notifications','notifications_read'], true)) return;
    if (str_starts_with($action, 'document_') || in_array($action, ['save_document_template_v236','archive_document_template_v236','duplicate_document_template_v236','preview_document_template_v236','preview_document_template_draft_v236','send_document_template_test_email_v236','save_document_mail_attachments_v236'], true)) {
        if (in_array($role, ['admin','manager'], true)) return;
        throw new ForbiddenException('Dokumente und Mailanhaenge duerfen nur durch Verwaltung oder Administratoren bearbeitet werden.');
    }
    if ($role === 'admin') return;

    $adminOnly = [
        'audit','generate_staypilot_export_key_v1',
        'users','role_matrix','user_access_v224','save_user_access_v224','clear_user_access_v224','save_user','delete_user',
        'integrations','save_integration','save_mappings','test_integration','sync_integration','sync_logs',
        'smtp_settings','save_smtp_settings','test_smtp','send_test_email','save_mail_account_v236125','test_imap_v236125','save_mail_account_v236125','test_imap_v236125','save_whatsapp_templates_v236','save_whatsapp_settings_v236','whatsapp_booking_prepare_v236','save_communication_status_settings_v236','send_customer_status_notification_v236','communication_automation_rules_v236','save_communication_automation_rules_v236','communication_automation_preview_v236',
    ];
    if (in_array($action, $adminOnly, true)) throw new ForbiddenException('Diese Funktion ist Administratoren vorbehalten.');

    $requiredCapability = function_exists('capability_for_action_v224') ? capability_for_action_v224($action, $method) : null;
    if ($requiredCapability !== null) {
        if (!Auth::canForUser($user, $requiredCapability)) {
            throw new ForbiddenException('FÃ¼r diese Aktion fehlt das Recht: ' . (Auth::capabilityCatalog()[$requiredCapability]['label'] ?? $requiredCapability) . '.');
        }
        // Ab V2.2.4 entscheidet bei gemappten Aktionen die Rollen-/Rechtematrix.
        // SpezialfÃ¤lle wie Benutzer, SMTP und Schnittstellen wurden oben weiterhin hart auf Admin begrenzt.
        return;
    }

    if ($method === 'GET') {
        $readByRole = [
            'manager' => [
                'dashboard','statistics','statistics_v226','houses','house','apartment_types','apartment_type','apartment_type_management_v216','apartment_type_editor_v216','capacity_check_v216','cancellation_quote_v216','audit','diagnostics','backups','verify_latest_backup_v236112',
                'apartments','guests','guest_categories','bookings','booking','offers_v214','offer_form_data_v214','offer_v214','offer_communication_v217','offer_services_v214','offer_content_blocks_v214','offer_translations_v214','offer_settings_v214','travellers','police','calendar','housekeeping','meals',
                'prices','pricing_v205','booking_channels','price_check_v205','csv_profiles','settings','smart_arrival_export_preview_v237','housekeeping_teams','communications','communications_v232','communication_center_v236','mail_account_v236125','mail_inbox_v236125','mail_message_v236126','mail_folders_v236132','mail_sent_log_v236127','email_templates_v236133','whatsapp_templates_v236','whatsapp_settings_v236','communication_readiness_v236','communication_status_settings_v236','direct_customer_email_context_v236','guest_portal_links_v232','bookings_v232',
                'task_center_today_v23656','housekeeping_stale_tasks_v236105','task_event_status_v23661','delete_center_data_v23663','delete_center_file_trash_v23664','delete_center_file_restore_v23664','delete_center_sync_status_v23697','housekeeping_release_queue','guest_portal_link','housekeeping_incidents','guest_portal_contents','housekeeping_dashboard_v210','public_guest_settings_v210','site_editor_data_v218','booking_confirmation_queue_v220','booking_confirmation_data_v220','payment_attention_v220','billing_overview_v223','booking_billing_v223','billing_overview_v230','booking_billing_v230','billing_number_counters_v23651','save_booking_payment_v23652','record_refund_v23653','bookings_v232','communications_v232','communication_center_v236','mail_account_v236125','mail_inbox_v236125','mail_message_v236126','mail_folders_v236132','mail_sent_log_v236127','email_templates_v236133','whatsapp_templates_v236','whatsapp_settings_v236','communication_readiness_v236','communication_status_settings_v236','direct_customer_email_context_v236','guest_portal_links_v232','checkin_overview_v228','checkin_detail_v228','checkin_documents_v229','checkin_communication_check_v229','smart_arrival_export_preview_v237','checkin_overview_v228','checkin_detail_v228','checkin_documents_v229','checkin_communication_check_v229','smart_arrival_export_preview_v237','checkin_overview_v228','checkin_detail_v228','checkin_documents_v229','checkin_communication_check_v229','smart_arrival_export_preview_v237',
            ],
            'reception' => [
                'dashboard','statistics','statistics_v226','houses','apartment_types','apartment_type_management_v216','apartment_type_editor_v216','apartments','guests','guest_categories','bookings','booking',
                'travellers','police','calendar','meals','offers_v214','offer_form_data_v214','offer_v214','offer_communication_v217','offer_services_v214','offer_content_blocks_v214','offer_translations_v214','offer_settings_v214','prices','pricing_v205','booking_channels','price_check_v205','settings','smart_arrival_export_preview_v237','housekeeping_dashboard_v210','housekeeping','housekeeping_teams','task_center_today_v23656','housekeeping_stale_tasks_v236105','task_event_status_v23661','housekeeping_release_queue','guest_portal_link','housekeeping_incidents','guest_portal_contents','public_guest_settings_v210','booking_confirmation_queue_v220','booking_confirmation_data_v220','payment_attention_v220','billing_overview_v223','booking_billing_v223','billing_overview_v230','booking_billing_v230','billing_number_counters_v23651','save_booking_payment_v23652','record_refund_v23653','bookings_v232','communications_v232','communication_center_v236','mail_account_v236125','mail_inbox_v236125','mail_message_v236126','mail_folders_v236132','mail_sent_log_v236127','email_templates_v236133','whatsapp_templates_v236','whatsapp_settings_v236','communication_readiness_v236','communication_status_settings_v236','direct_customer_email_context_v236','guest_portal_links_v232','checkin_overview_v228','checkin_detail_v228','checkin_documents_v229','checkin_communication_check_v229','smart_arrival_export_preview_v237','checkin_overview_v228','checkin_detail_v228','checkin_documents_v229','checkin_communication_check_v229','smart_arrival_export_preview_v237','checkin_overview_v228','checkin_detail_v228','checkin_documents_v229','checkin_communication_check_v229','smart_arrival_export_preview_v237',
            ],
            'housekeeping_manager' => ['housekeeping','housekeeping_teams','housekeeping_release_queue','housekeeping_incidents','settings'],
            'housekeeping' => ['housekeeping','settings'],
            'readonly' => [
                'dashboard','statistics','statistics_v226','houses','apartment_types','apartment_type_management_v216','apartment_type_editor_v216','apartments','bookings','booking','offers_v214','offer_v214','offer_communication_v217','booking_confirmation_queue_v220','booking_confirmation_data_v220','payment_attention_v220','billing_overview_v223','booking_billing_v223','billing_overview_v230','booking_billing_v230','billing_number_counters_v23651','save_booking_payment_v23652','record_refund_v23653','bookings_v232','communications_v232','communication_center_v236','mail_account_v236125','mail_inbox_v236125','mail_message_v236126','mail_folders_v236132','mail_sent_log_v236127','email_templates_v236133','whatsapp_templates_v236','whatsapp_settings_v236','communication_readiness_v236','communication_status_settings_v236','direct_customer_email_context_v236','guest_portal_links_v232','checkin_overview_v228','checkin_detail_v228','checkin_documents_v229','checkin_communication_check_v229','smart_arrival_export_preview_v237','checkin_overview_v228','checkin_detail_v228','checkin_documents_v229','checkin_communication_check_v229','smart_arrival_export_preview_v237','checkin_overview_v228','checkin_detail_v228','checkin_documents_v229','checkin_communication_check_v229','smart_arrival_export_preview_v237','calendar','meals',
                'prices','pricing_v205','booking_channels','price_check_v205','settings','smart_arrival_export_preview_v237','housekeeping_dashboard_v210',
            ],
        ];
        if (!in_array($action, $readByRole[$role] ?? [], true)) throw new ForbiddenException();
        return;
    }

    if ($role === 'manager') {
        $blocked = ['save_user','delete_user','save_integration','save_mappings','test_integration','sync_integration','save_smtp_settings','test_smtp'];
        if (!in_array($action,$blocked,true)) return;
    }
    if ($role === 'reception') {
        $allowed = [
            'save_guest','save_booking','set_booking_status','unassign_booking','save_travellers','police_status',
            'price_quote','price_check_v205','capacity_check_v216','cancellation_quote_v216','move_preview','move_booking','resize_booking','housekeeping_final_release','guest_portal_link',
            'save_task','task_status','housekeeping_incident_review','send_housekeeping_list_v23624',
            'save_booking_payment_v223','update_payment_schedule_v223','create_invoice_v223','resend_customer_status_v223','whatsapp_booking_open_v223','create_billing_document_v230','send_billing_document_v230','send_payment_reminder_v230','billing_number_counters_v23651','billing_number_counter_set_v23651','save_booking_payment_v23652','record_refund_v23653','task_center_today_v23656','housekeeping_stale_tasks_v236105','task_event_status_v23661','save_internal_task_v23656','task_status_v23656','task_event_status_v23661','housekeeping_stale_task_action_v236105','send_checkin_request_v228','whatsapp_checkin_open_v228','upload_checkin_file_v228','delete_checkin_file_v228','review_checkin_v228','generate_checkin_documents_v229','send_checkin_documents_v229','assign_booking_apartment_v235','set_booking_clarification_v235','reject_booking_clarification_v235','recalculate_payment_schedule_v235','send_booking_status_v235','save_mail_account_v236125','test_imap_v236125','save_whatsapp_templates_v236','save_whatsapp_settings_v236','whatsapp_booking_prepare_v236','save_communication_status_settings_v236','send_customer_status_notification_v236','direct_customer_email_preview_v236','send_direct_customer_email_v236','send_free_email_v236126','mail_action_v236132','save_email_template_v236133','delete_email_template_v236133','save_email_template_v236133','delete_email_template_v236133','send_free_email_v236126','mail_action_v236132','save_email_template_v236133','delete_email_template_v236133','save_email_template_v236133','delete_email_template_v236133','communication_automation_rules_v236','save_communication_automation_rules_v236','communication_automation_preview_v236','repair_offer_events_note_v235','smart_arrival_export_prepare_v237','smart_arrival_export_mark_reported_v237',
        ];
        if (in_array($action, $allowed, true)) return;
    }
    if (in_array($role,['manager','reception'],true) && in_array($action,[
        'offer_quote_v214','save_offer_v214','revise_offer_v214','send_offer_v214','archive_offer_v214','convert_offer_v214','preview_offer_communication_v217','save_offer_communication_v217','confirm_offer_booking_v220','booking_no_availability_action_v221','save_booking_payment_v223','update_payment_schedule_v223','create_invoice_v223','resend_customer_status_v223','whatsapp_booking_open_v223','create_billing_document_v230','send_billing_document_v230','send_payment_reminder_v230','billing_number_counters_v23651','billing_number_counter_set_v23651','save_booking_payment_v23652','record_refund_v23653','task_center_today_v23656','housekeeping_stale_tasks_v236105','task_event_status_v23661','save_internal_task_v23656','task_status_v23656','task_event_status_v23661','housekeeping_stale_task_action_v236105','send_checkin_request_v228','whatsapp_checkin_open_v228','upload_checkin_file_v228','delete_checkin_file_v228','review_checkin_v228','generate_checkin_documents_v229','send_checkin_documents_v229','assign_booking_apartment_v235','set_booking_clarification_v235','reject_booking_clarification_v235','recalculate_payment_schedule_v235','send_booking_status_v235','save_mail_account_v236125','test_imap_v236125','save_whatsapp_templates_v236','save_whatsapp_settings_v236','whatsapp_booking_prepare_v236','save_communication_status_settings_v236','send_customer_status_notification_v236','direct_customer_email_preview_v236','send_direct_customer_email_v236','send_free_email_v236126','mail_action_v236132','save_email_template_v236133','delete_email_template_v236133','save_email_template_v236133','delete_email_template_v236133','send_free_email_v236126','mail_action_v236132','save_email_template_v236133','delete_email_template_v236133','save_email_template_v236133','delete_email_template_v236133','communication_automation_rules_v236','save_communication_automation_rules_v236','communication_automation_preview_v236','repair_offer_events_note_v235'
    ],true)) return;
    if ($role === 'manager' && in_array($action,[
        'save_offer_service_v214','delete_offer_service_v214','save_offer_content_block_v214','delete_offer_content_block_v214','save_offer_translations_v214','save_offer_templates_v214','save_offer_settings_v214','save_missing_offer_price_v215',
        'save_site_page_v218','delete_site_page_v218','duplicate_site_page_v218','save_site_block_v218','duplicate_site_block_v218','delete_site_block_v218','move_site_block_v218','save_site_design_v218','upload_site_media_v218','save_site_media_meta_v218','delete_site_media_v218'
    ],true)) return;
    if ($role === 'housekeeping_manager' && in_array($action, ['save_task','task_status','housekeeping_incident_review','send_housekeeping_list_v23624','notifications_read'], true)) return;
    throw new ForbiddenException($role === 'readonly' ? 'Dieses Konto besitzt nur Leserechte.' : 'FÃ¼r diese Aktion fehlen die erforderlichen Rechte.');
}

function fetch_row(string $table, int $id): ?array
{
    $allowed = ['houses','apartment_types','apartments','guests','bookings','users'];
    if (!in_array($table, $allowed, true)) throw new InvalidArgumentException('UngÃ¼ltige Tabelle.');
    $stmt = db()->prepare("SELECT * FROM `{$table}` WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function list_houses(): never
{
    $rows = db()->query("SELECT h.*,(SELECT COUNT(*) FROM apartments a WHERE a.house_id=h.id) apartment_count FROM houses h ORDER BY h.sort_order,h.name")->fetchAll();
    json_response(['ok'=>true,'houses'=>$rows]);
}

function get_house(): never
{
    $id = (int)($_GET['id'] ?? 0);
    $row = fetch_row('houses',$id);
    if (!$row) throw new NotFoundException('Haus nicht gefunden.');
    json_response(['ok'=>true,'house'=>$row]);
}

function save_house(): never
{
    $d = request_data(); $id = (int)($d['id'] ?? 0);
    $old = $id ? fetch_row('houses',$id) : null;
    if ($id && !$old) throw new NotFoundException('Haus nicht gefunden.');
    $name = Validator::text($d,'name','Name',160,true);
    $code = strtoupper(Validator::text($d,'code','Kurzcode',40,true));
    $email = Validator::email($d,'email','E-Mail');
    $checkin = nullable_time($d['default_checkin_time'] ?? null);
    $checkout = nullable_time($d['default_checkout_time'] ?? null);
    $values = [$name,$code,Validator::text($d,'address','Adresse',255),Validator::text($d,'contact_name','Ansprechpartner',160),Validator::text($d,'phone','Telefon',80),$email,$checkin,$checkout,Validator::text($d,'cleaning_team','Putzteam',160),normalize_bool($d['breakfast_available']??0),normalize_bool($d['half_board_available']??0),Validator::text($d,'internal_notes','Interne Hinweise',10000),normalize_bool($d['active']??0),(int)($d['sort_order']??0)];
    try {
        if ($id) db()->prepare('UPDATE houses SET name=?,code=?,address=?,contact_name=?,phone=?,email=?,default_checkin_time=?,default_checkout_time=?,cleaning_team=?,breakfast_available=?,half_board_available=?,internal_notes=?,active=?,sort_order=? WHERE id=?')->execute([...$values,$id]);
        else { db()->prepare('INSERT INTO houses(name,code,address,contact_name,phone,email,default_checkin_time,default_checkout_time,cleaning_team,breakfast_available,half_board_available,internal_notes,active,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($values); $id=(int)db()->lastInsertId(); }
    } catch (PDOException $e) {
        if ((string)$e->getCode()==='23000') throw new ConflictException('Dieser Haus-Kurzcode ist bereits vergeben.');
        throw $e;
    }
    $new = fetch_row('houses',$id); AuditLogger::record('house',$id,$old?'update':'create',$old,$new,'Haus gespeichert');
    json_response(['ok'=>true,'message'=>'Haus gespeichert.','id'=>$id]);
}

function delete_house(): never
{
    $id=(int)(request_data()['id']??0); $old=fetch_row('houses',$id); if(!$old)throw new NotFoundException('Haus nicht gefunden.');
    $stmt=db()->prepare('SELECT COUNT(*) FROM apartments WHERE house_id=?');$stmt->execute([$id]);
    if((int)$stmt->fetchColumn()>0)throw new ConflictException('Dem Haus sind Apartments zugeordnet. Setzen Sie es stattdessen auf inaktiv.');
    db()->prepare('DELETE FROM houses WHERE id=?')->execute([$id]);AuditLogger::record('house',$id,'delete',$old,null,'Haus gelÃ¶scht');
    json_response(['ok'=>true,'message'=>'Haus gelÃ¶scht.']);
}

function list_apartment_types(): never
{
    $rows=db()->query("SELECT t.*,(SELECT COUNT(*) FROM apartments a WHERE a.apartment_type_id=t.id) apartment_count FROM apartment_types t ORDER BY t.sort_order,t.name")->fetchAll();
    foreach($rows as &$row){$row['amenities']=json_decode((string)$row['amenities_json'],true)?:[];$row['photos']=json_decode((string)$row['photos_json'],true)?:[];$row['discounts']=json_decode((string)$row['discounts_json'],true)?:[];}
    json_response(['ok'=>true,'apartment_types'=>$rows]);
}

function get_apartment_type(): never
{
    $id=(int)($_GET['id']??0);$row=fetch_row('apartment_types',$id);if(!$row)throw new NotFoundException('Wohnungstyp nicht gefunden.');
    $row['amenities']=json_decode((string)$row['amenities_json'],true)?:[];$row['photos']=json_decode((string)$row['photos_json'],true)?:[];$row['discounts']=json_decode((string)$row['discounts_json'],true)?:[];
    json_response(['ok'=>true,'apartment_type'=>$row]);
}

function save_apartment_type(): never
{
    $d=request_data();$id=(int)($d['id']??0);$old=$id?fetch_row('apartment_types',$id):null;if($id&&!$old)throw new NotFoundException('Wohnungstyp nicht gefunden.');
    $name=Validator::text($d,'name','Bezeichnung',160,true);$code=strtoupper(Validator::text($d,'code','Kurzcode',60,true));
    $amenities=array_values(array_filter(array_map('trim',preg_split('/[,;\n]+/',(string)($d['amenities']??'')))));
    $photos=array_values(array_filter(array_map('trim',preg_split('/[\n]+/',(string)($d['photos']??'')))));
    $values=[$name,$code,max(1,(int)($d['max_occupancy']??2)),max(0,(int)($d['default_adults']??2)),max(0,(int)($d['default_children']??0)),max(0,(int)($d['bedrooms']??1)),max(0,(int)($d['beds']??1)),($d['living_area']??'')===''?null:max(0,(float)$d['living_area']),max(0,(float)($d['standard_price']??0)),max(1,(int)($d['default_min_stay']??1)),max(0,(float)($d['cleaning_fee']??0)),max(0,(int)($d['standard_cleaning_minutes']??60)),json_encode($amenities,JSON_UNESCAPED_UNICODE),Validator::text($d,'description','Beschreibung',20000),json_encode($photos,JSON_UNESCAPED_UNICODE),max(0,(float)($d['breakfast_price']??0)),max(0,(float)($d['half_board_price']??0)),max(0,(float)($d['parking_price']??0)),max(0,(float)($d['pet_price']??0)),max(0,(float)($d['extra_bed_price']??0)),max(0,(float)($d['baby_bed_price']??0)),($old['discounts_json']??json_encode([],JSON_UNESCAPED_UNICODE)),normalize_bool($d['active']??0),(int)($d['sort_order']??0)];
    try{
        if($id)db()->prepare('UPDATE apartment_types SET name=?,code=?,max_occupancy=?,default_adults=?,default_children=?,bedrooms=?,beds=?,living_area=?,standard_price=?,default_min_stay=?,cleaning_fee=?,standard_cleaning_minutes=?,amenities_json=?,description=?,photos_json=?,breakfast_price=?,half_board_price=?,parking_price=?,pet_price=?,extra_bed_price=?,baby_bed_price=?,discounts_json=?,active=?,sort_order=? WHERE id=?')->execute([...$values,$id]);
        else{db()->prepare('INSERT INTO apartment_types(name,code,max_occupancy,default_adults,default_children,bedrooms,beds,living_area,standard_price,default_min_stay,cleaning_fee,standard_cleaning_minutes,amenities_json,description,photos_json,breakfast_price,half_board_price,parking_price,pet_price,extra_bed_price,baby_bed_price,discounts_json,active,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($values);$id=(int)db()->lastInsertId();}
    }catch(PDOException $e){if((string)$e->getCode()==='23000')throw new ConflictException('Dieser Wohnungstyp-Kurzcode ist bereits vergeben.');throw $e;}
    $new=fetch_row('apartment_types',$id);AuditLogger::record('apartment_type',$id,$old?'update':'create',$old,$new,'Wohnungstyp gespeichert');
    json_response(['ok'=>true,'message'=>'Wohnungstyp gespeichert.','id'=>$id]);
}

function delete_apartment_type(): never
{
    $id=(int)(request_data()['id']??0);$old=fetch_row('apartment_types',$id);if(!$old)throw new NotFoundException('Wohnungstyp nicht gefunden.');
    $stmt=db()->prepare('SELECT COUNT(*) FROM apartments WHERE apartment_type_id=?');$stmt->execute([$id]);if((int)$stmt->fetchColumn()>0)throw new ConflictException('Dieser Wohnungstyp wird von Apartments verwendet. Setzen Sie ihn stattdessen auf inaktiv.');
    db()->prepare('DELETE FROM apartment_types WHERE id=?')->execute([$id]);AuditLogger::record('apartment_type',$id,'delete',$old,null,'Wohnungstyp gelÃ¶scht');json_response(['ok'=>true,'message'=>'Wohnungstyp gelÃ¶scht.']);
}

function list_users(): never
{
    $rows=db()->query("SELECT u.id,u.name,u.email,u.role,u.active,u.last_login_at,u.failed_login_count,u.locked_until,u.created_at,u.updated_at,(SELECT hm.id FROM housekeeping_members hm WHERE hm.user_id=u.id LIMIT 1) housekeeping_member_id,(SELECT hm.name FROM housekeeping_members hm WHERE hm.user_id=u.id LIMIT 1) housekeeping_member_name FROM users u ORDER BY u.active DESC,u.name")->fetchAll();
    json_response(['ok'=>true,'users'=>$rows,'roles'=>Auth::ROLES]);
}

function save_user(): never
{
    $d=request_data();$id=(int)($d['id']??0);$old=$id?fetch_row('users',$id):null;if($id&&!$old)throw new NotFoundException('Benutzer nicht gefunden.');
    $name=Validator::text($d,'name','Name',120,true);$email=mb_strtolower(Validator::email($d,'email','E-Mail',true));$role=Validator::oneOf($d,'role','Rolle',Auth::ROLES,'readonly');$active=normalize_bool($d['active']??0);$password=(string)($d['password']??'');$housekeepingMemberId=in_array($role,['housekeeping','housekeeping_manager'],true)?((int)($d['housekeeping_member_id']??0)?:null):null;
    $current=Auth::user();
    if($id===(int)($current['id']??0)&&!$active)throw new ConflictException('Das eigene Benutzerkonto kann nicht deaktiviert werden.');
    if($old&&$old['role']==='admin'&&(int)$old['active']===1&&($role!=='admin'||!$active)){$admins=(int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1")->fetchColumn();if($admins<=1)throw new ConflictException('Der letzte aktive Administrator kann weder deaktiviert noch herabgestuft werden.');}
    if(!$id&&mb_strlen($password)<8)throw new ValidationException('FÃ¼r einen neuen Benutzer ist ein Passwort mit mindestens 8 Zeichen erforderlich.');
    if($password!==''&&mb_strlen($password)<8)throw new ValidationException('Das Passwort muss mindestens 8 Zeichen lang sein.');
    try{
        if($id){$sql='UPDATE users SET name=?,email=?,role=?,active=?,failed_login_count=0,locked_until=NULL';$params=[$name,$email,$role,$active];if($password!==''){$sql.=',password_hash=?';$params[]=password_hash($password,PASSWORD_DEFAULT);}$sql.=' WHERE id=?';$params[]=$id;db()->prepare($sql)->execute($params);}
        else{db()->prepare('INSERT INTO users(name,email,password_hash,role,active) VALUES(?,?,?,?,?)')->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$role,$active]);$id=(int)db()->lastInsertId();}
    }catch(PDOException $e){if((string)$e->getCode()==='23000')throw new ConflictException('Diese E-Mail-Adresse ist bereits vergeben.');throw $e;}
    db()->prepare('UPDATE housekeeping_members SET user_id=NULL WHERE user_id=?')->execute([$id]);
    if($housekeepingMemberId){$stmt=db()->prepare('SELECT id FROM housekeeping_members WHERE id=? AND active=1 LIMIT 1');$stmt->execute([$housekeepingMemberId]);if(!$stmt->fetchColumn())throw new ValidationException('Der gewÃ¤hlte Housekeeping-Mitarbeiter ist nicht aktiv.');db()->prepare('UPDATE housekeeping_members SET user_id=NULL WHERE id=? AND user_id IS NOT NULL AND user_id<>?')->execute([$housekeepingMemberId,$id]);db()->prepare('UPDATE housekeeping_members SET user_id=? WHERE id=?')->execute([$id,$housekeepingMemberId]);}
    $new=fetch_row('users',$id);$new['housekeeping_member_id']=$housekeepingMemberId;AuditLogger::record('user',$id,$old?'update':'create',$old,$new,'Benutzer und RollenverknÃ¼pfung gespeichert');json_response(['ok'=>true,'message'=>'Benutzer gespeichert.','id'=>$id]);
}

function delete_user(): never
{
    $id=(int)(request_data()['id']??0);$current=Auth::user();if($id===(int)($current['id']??0))throw new ConflictException('Das eigene Benutzerkonto kann nicht deaktiviert werden.');
    $old=fetch_row('users',$id);if(!$old)throw new NotFoundException('Benutzer nicht gefunden.');
    if($old['role']==='admin'&&(int)$old['active']===1){$admins=(int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1")->fetchColumn();if($admins<=1)throw new ConflictException('Der letzte aktive Administrator kann nicht deaktiviert werden.');}
    db()->prepare('UPDATE users SET active=0 WHERE id=?')->execute([$id]);AuditLogger::record('user',$id,'deactivate',$old,fetch_row('users',$id),'Benutzer deaktiviert');json_response(['ok'=>true,'message'=>'Benutzer deaktiviert.']);
}

function audit_data(): never
{
    $entity=trim((string)($_GET['entity_type']??''));$limit=max(10,min(500,(int)($_GET['limit']??200)));$sql='SELECT * FROM audit_log';$params=[];if($entity!==''){$sql.=' WHERE entity_type=?';$params[]=$entity;}$sql.=' ORDER BY id DESC LIMIT '.$limit;$stmt=db()->prepare($sql);$stmt->execute($params);json_response(['ok'=>true,'entries'=>$stmt->fetchAll()]);
}

function diagnostics_data(): never
{
    json_response(['ok'=>true,'diagnostics'=>SystemDiagnostics::run()]);
}

function backups_data(): never
{
    json_response(['ok'=>true,'backups'=>BackupManager::list()]);
}

function create_backup_action(): never
{
    $result=BackupManager::create('manual');AuditLogger::record('system_backup',$result['filename'],'create',null,$result,'Manuelle Datensicherung');json_response(['ok'=>true,'message'=>'Datensicherung wurde erstellt.','backup'=>$result]);
}

function verify_latest_backup_v236112(): never
{
    $result = BackupManager::verifyLatest();
    AuditLogger::record('system_backup', (string)($result['filename'] ?? 'latest'), 'verify', null, $result, 'Backup-Lesbarkeitsprüfung');
    json_response(['ok'=>!empty($result['ok']), 'verification'=>$result, 'message'=>!empty($result['ok']) ? 'Letztes Backup ist lesbar und plausibel.' : 'Backup-Prüfung hat Hinweise gefunden.']);
}

function create_pre_update_backup_v236112(): never
{
    $result = BackupManager::create('pre-update-manual');
    $verification = BackupManager::verify((string)$result['filename']);
    AuditLogger::record('system_backup', $result['filename'], 'create_pre_update', null, ['backup'=>$result,'verification'=>$verification], 'Manuelle Vor-Update-Sicherung mit Verifikation');
    json_response(['ok'=>!empty($verification['ok']), 'message'=>!empty($verification['ok']) ? 'Vor-Update-Sicherung wurde erstellt und verifiziert.' : 'Vor-Update-Sicherung wurde erstellt, die Verifikation hat Hinweise gefunden.', 'backup'=>$result, 'verification'=>$verification]);
}

function client_error_action(): never
{
    $d=request_data();$message=Validator::text($d,'message','Fehlermeldung',4000,true);$requestId=AppLogger::error($message,['stack'=>$d['stack']??'','url'=>$d['url']??'','user_agent'=>$d['user_agent']??''],'javascript');json_response(['ok'=>true,'request_id'=>$requestId]);
}

function booking_select(): string
{
    return "SELECT b.*,TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,g.email guest_email,g.phone guest_phone,
            g.category_id guest_category_id,g.vip guest_vip,g.preferences guest_preferences,gc.name guest_category_name,gc.color guest_category_color,
            bc.name booking_channel_name,bc.color booking_channel_color,
            a.name apartment_name,a.code apartment_code,a.color apartment_color,
            COALESCE(at.name,aat.name) apartment_type_name,COALESCE(at.code,aat.code) apartment_type_code,
            COALESCE(b.apartment_type_id,a.apartment_type_id) effective_apartment_type_id,
            ua.name upgrade_from_apartment_name,ua.code upgrade_from_apartment_code,
            (SELECT COUNT(*) FROM booking_travellers bt WHERE bt.booking_id=b.id) traveller_count
            FROM bookings b JOIN guests g ON g.id=b.guest_id AND (g.deleted_at IS NULL OR g.deleted_at='0000-00-00 00:00:00')
            LEFT JOIN guest_categories gc ON gc.id=g.category_id
            LEFT JOIN booking_channels bc ON bc.id=b.booking_channel_id
            LEFT JOIN apartments a ON a.id=b.apartment_id
            LEFT JOIN apartment_types at ON at.id=b.apartment_type_id
            LEFT JOIN apartment_types aat ON aat.id=a.apartment_type_id
            LEFT JOIN apartments ua ON ua.id=b.upgrade_from_apartment_id";
}


function booking_active_condition_v236128(string $alias='b'): string
{
    return "($alias.deleted_at IS NULL OR $alias.deleted_at='0000-00-00 00:00:00') AND LOWER(COALESCE($alias.status,'')) NOT IN ('cancelled','canceled','storniert','deleted','gelöscht','rejected','abgelehnt','declined','void')";
}

function booking_active_guest_condition_v236128(string $alias='g'): string
{
    return "($alias.deleted_at IS NULL OR $alias.deleted_at='0000-00-00 00:00:00')";
}

function dashboard(): never
{
    if (function_exists('ensure_delete_center_v23663')) ensure_delete_center_v23663();
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-01', strtotime('+1 month'));
    $active = booking_active_condition_v236128('b');
    $guestActive = booking_active_guest_condition_v236128('g');
    $apartmentCount = (int)db()->query("SELECT COUNT(*) FROM apartments WHERE status='active'")->fetchColumn();
    $guestCount = (int)db()->query("SELECT COUNT(*) FROM guests WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00')")->fetchColumn();

    $waiting = (int)db()->query("SELECT COUNT(*) FROM bookings b JOIN guests g ON g.id=b.guest_id WHERE b.apartment_id IS NULL AND $active AND $guestActive")->fetchColumn();

    $stmt = db()->prepare("SELECT COUNT(*) bookings,COALESCE(SUM(CASE WHEN COALESCE(b.accounting_mode,'internal')='internal' THEN b.total_price ELSE 0 END),0) revenue
        FROM bookings b JOIN guests g ON g.id=b.guest_id
        WHERE $active AND $guestActive AND b.arrival<? AND b.departure>?");
    $stmt->execute([$monthEnd,$monthStart]);
    $month = $stmt->fetch();

    $stmt = db()->prepare("SELECT COALESCE(b.accounting_mode,'internal') mode,COUNT(*) count,COALESCE(SUM(b.total_price),0) total
        FROM bookings b JOIN guests g ON g.id=b.guest_id
        WHERE $active AND $guestActive AND b.arrival<? AND b.departure>?
        GROUP BY COALESCE(b.accounting_mode,'internal')");
    $stmt->execute([$monthEnd,$monthStart]);
    $accountingSplit=['internal'=>['count'=>0,'total'=>0.0],'external'=>['count'=>0,'total'=>0.0],'none'=>['count'=>0,'total'=>0.0],'portal_later'=>['count'=>0,'total'=>0.0]];
    foreach($stmt->fetchAll() as $r){$m=(string)($r['mode']?:'internal');if(!isset($accountingSplit[$m]))$accountingSplit[$m]=['count'=>0,'total'=>0.0];$accountingSplit[$m]=['count'=>(int)$r['count'],'total'=>(float)$r['total']];}

    $stmt = db()->prepare("SELECT COALESCE(SUM(GREATEST(0,DATEDIFF(LEAST(b.departure,?),GREATEST(b.arrival,?)))),0)
        FROM bookings b JOIN guests g ON g.id=b.guest_id
        WHERE b.apartment_id IS NOT NULL AND $active AND $guestActive AND b.arrival<? AND b.departure>?");
    $stmt->execute([$monthEnd,$monthStart,$monthEnd,$monthStart]);
    $bookedNights = (int)$stmt->fetchColumn();
    $days = (int)date('t');
    $occupancy = $apartmentCount > 0 ? round($bookedNights / ($apartmentCount * $days) * 100) : 0;

    $stmt = db()->prepare(booking_select() . " WHERE b.arrival BETWEEN ? AND ? AND " . booking_active_condition_v236128('b') . " ORDER BY b.arrival LIMIT 12");
    $stmt->execute([$today,date('Y-m-d',strtotime('+14 days'))]);
    $arrivals = $stmt->fetchAll();
    $stmt = db()->prepare(booking_select() . " WHERE b.departure BETWEEN ? AND ? AND " . booking_active_condition_v236128('b') . " ORDER BY b.departure LIMIT 12");
    $stmt->execute([$today,date('Y-m-d',strtotime('+14 days'))]);
    $departures = $stmt->fetchAll();

    $stmt = db()->prepare("SELECT h.*,a.name apartment_name FROM housekeeping_tasks h JOIN apartments a ON a.id=h.apartment_id LEFT JOIN bookings b ON b.id=h.booking_id LEFT JOIN guests g ON g.id=b.guest_id WHERE h.task_date BETWEEN ? AND ? AND h.status NOT IN ('released','cancelled')" . housekeeping_active_booking_filter_v23689('b','h') . " AND (h.booking_id IS NULL OR $guestActive) ORDER BY h.task_date,h.priority DESC LIMIT 15");
    $stmt->execute([$today,date('Y-m-d',strtotime('+7 days'))]);
    $tasks = $stmt->fetchAll();

    $sourceRows = db()->query("SELECT b.source,COUNT(*) count FROM bookings b JOIN guests g ON g.id=b.guest_id WHERE $active AND $guestActive GROUP BY b.source ORDER BY count DESC LIMIT 8")->fetchAll();
    $acceptedOffers=BookingWorkflowService::queue();
    $paymentAttention=BookingWorkflowService::paymentAttention();
    json_response(['ok'=>true,'stats'=>[
        'apartments'=>$apartmentCount,'guests'=>$guestCount,'waiting'=>$waiting,'bookings'=>(int)$month['bookings'],'revenue'=>(float)$month['revenue'],'occupancy'=>$occupancy,'booked_nights'=>$bookedNights,
        'accepted_offers'=>count($acceptedOffers),'payments_overdue'=>(int)$paymentAttention['overdue'],'payments_due_soon'=>(int)$paymentAttention['due_soon'],
        'internal_bookings'=>(int)($accountingSplit['internal']['count']??0),'external_bookings'=>(int)($accountingSplit['external']['count']??0),'no_accounting_bookings'=>(int)($accountingSplit['none']['count']??0),'portal_later_bookings'=>(int)($accountingSplit['portal_later']['count']??0),'accounting_split'=>$accountingSplit
    ],'arrivals'=>$arrivals,'departures'=>$departures,'tasks'=>$tasks,'sources'=>$sourceRows,'accepted_offers'=>$acceptedOffers,'payment_attention'=>$paymentAttention]);
}

function statistics_data(): never
{
    if (function_exists('ensure_delete_center_v23663')) ensure_delete_center_v23663();
    $from=(string)($_GET['from']??date('Y-m-01',strtotime('-5 months')));
    $to=(string)($_GET['to']??date('Y-m-t'));
    if(!valid_date($from)||!valid_date($to)||$from>$to)throw new RuntimeException('Statistikzeitraum ist ungÃ¼ltig.');
    $end=(new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');
    $days=max(1,nights($from,$end));
    $activeApartments=(int)db()->query("SELECT COUNT(*) FROM apartments WHERE status='active'")->fetchColumn();

    $stmt=db()->prepare("SELECT
        COUNT(*) total_bookings,
        SUM(CASE WHEN status IN ('cancelled','rejected') THEN 1 ELSE 0 END) cancelled_bookings,
        COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','rejected') THEN
            total_price * GREATEST(0,DATEDIFF(LEAST(departure,?),GREATEST(arrival,?))) / GREATEST(1,DATEDIFF(departure,arrival))
            ELSE 0 END),0) revenue,
        COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','rejected') THEN
            paid_amount * GREATEST(0,DATEDIFF(LEAST(departure,?),GREATEST(arrival,?))) / GREATEST(1,DATEDIFF(departure,arrival))
            ELSE 0 END),0) paid,
        COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','rejected') THEN GREATEST(0,DATEDIFF(LEAST(departure,?),GREATEST(arrival,?))) ELSE 0 END),0) booked_nights,
        COALESCE(AVG(CASE WHEN status NOT IN ('cancelled','rejected') THEN DATEDIFF(departure,arrival) END),0) avg_stay
        FROM bookings WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND LOWER(COALESCE(status,'')) NOT IN ('deleted','gelöscht','cancelled','canceled','storniert','rejected','abgelehnt','declined','void') AND EXISTS(SELECT 1 FROM guests gx WHERE gx.id=bookings.guest_id AND (gx.deleted_at IS NULL OR gx.deleted_at='0000-00-00 00:00:00')) AND arrival<? AND departure>?");
    $stmt->execute([$end,$from,$end,$from,$end,$from,$end,$from]);
    $sum=$stmt->fetch()?:[];
    $bookedNights=(int)($sum['booked_nights']??0);
    $revenue=(float)($sum['revenue']??0);
    $capacity=max(1,$activeApartments*$days);
    $occupancy=round($bookedNights/$capacity*100,1);
    $adr=$bookedNights>0?round($revenue/$bookedNights,2):0;
    $revpar=$capacity>0?round($revenue/$capacity,2):0;
    $totalBookings=(int)($sum['total_bookings']??0);
    $cancelled=(int)($sum['cancelled_bookings']??0);

    $stmt=db()->prepare("SELECT source,COUNT(*) bookings,
        COALESCE(SUM(total_price * GREATEST(0,DATEDIFF(LEAST(departure,?),GREATEST(arrival,?))) / GREATEST(1,DATEDIFF(departure,arrival))),0) revenue
        FROM bookings WHERE LOWER(COALESCE(status,'')) NOT IN ('cancelled','canceled','storniert','deleted','gelöscht','rejected','abgelehnt','declined','void') AND (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND EXISTS(SELECT 1 FROM guests gx WHERE gx.id=bookings.guest_id AND (gx.deleted_at IS NULL OR gx.deleted_at='0000-00-00 00:00:00')) AND arrival<? AND departure>?
        GROUP BY source ORDER BY revenue DESC,bookings DESC");
    $stmt->execute([$end,$from,$end,$from]);$sources=$stmt->fetchAll();

    $stmt=db()->prepare("SELECT a.id,a.code,a.name,
        COUNT(DISTINCT b.id) bookings,
        COALESCE(SUM(GREATEST(0,DATEDIFF(LEAST(b.departure,?),GREATEST(b.arrival,?)))),0) booked_nights,
        COALESCE(SUM(b.total_price * GREATEST(0,DATEDIFF(LEAST(b.departure,?),GREATEST(b.arrival,?))) / GREATEST(1,DATEDIFF(b.departure,b.arrival))),0) revenue
        FROM apartments a
        LEFT JOIN bookings b ON b.apartment_id=a.id AND LOWER(COALESCE(b.status,'')) NOT IN ('cancelled','canceled','storniert','deleted','gelöscht','rejected','abgelehnt','declined','void') AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND EXISTS(SELECT 1 FROM guests gx WHERE gx.id=b.guest_id AND (gx.deleted_at IS NULL OR gx.deleted_at='0000-00-00 00:00:00')) AND b.arrival<? AND b.departure>?
        WHERE a.status='active'
        GROUP BY a.id,a.code,a.name ORDER BY revenue DESC,a.sort_order,a.name");
    $stmt->execute([$end,$from,$end,$from,$end,$from]);$apartments=$stmt->fetchAll();
    foreach($apartments as &$row){
        $row['occupancy']=round(((int)$row['booked_nights'])/$days*100,1);
        $row['adr']=(int)$row['booked_nights']>0?round((float)$row['revenue']/(int)$row['booked_nights'],2):0;
    }

    $months=[];
    $cursor=(new DateTimeImmutable($from))->modify('first day of this month');
    $last=(new DateTimeImmutable($to))->modify('first day of this month');
    $stmt=db()->prepare("SELECT
        COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','rejected') THEN
            total_price * GREATEST(0,DATEDIFF(LEAST(departure,?),GREATEST(arrival,?))) / GREATEST(1,DATEDIFF(departure,arrival))
            ELSE 0 END),0) revenue,
        COUNT(CASE WHEN status NOT IN ('cancelled','rejected') THEN 1 END) bookings
        FROM bookings WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND LOWER(COALESCE(status,'')) NOT IN ('deleted','gelöscht','cancelled','canceled','storniert','rejected','abgelehnt','declined','void') AND EXISTS(SELECT 1 FROM guests gx WHERE gx.id=bookings.guest_id AND (gx.deleted_at IS NULL OR gx.deleted_at='0000-00-00 00:00:00')) AND arrival<? AND departure>?");
    while($cursor<=$last){
        $mStart=$cursor->format('Y-m-d');
        $mEnd=$cursor->modify('first day of next month')->format('Y-m-d');
        $stmt->execute([$mEnd,$mStart,$mEnd,$mStart]);$m=$stmt->fetch()?:[];
        $months[]=['month'=>$cursor->format('Y-m'),'label'=>$cursor->format('m/Y'),'revenue'=>(float)($m['revenue']??0),'bookings'=>(int)($m['bookings']??0)];
        $cursor=$cursor->modify('first day of next month');
    }

    json_response(['ok'=>true,'from'=>$from,'to'=>$to,'summary'=>[
        'bookings'=>$totalBookings,'cancelled'=>$cancelled,
        'cancellation_rate'=>$totalBookings>0?round($cancelled/$totalBookings*100,1):0,
        'revenue'=>$revenue,'paid'=>(float)($sum['paid']??0),
        'outstanding'=>max(0,$revenue-(float)($sum['paid']??0)),
        'booked_nights'=>$bookedNights,'occupancy'=>$occupancy,'adr'=>$adr,'revpar'=>$revpar,
        'avg_stay'=>round((float)($sum['avg_stay']??0),1)
    ],'sources'=>$sources,'apartments'=>$apartments,'months'=>$months]);
}

function list_apartments(): never
{
    $rows = db()->query("SELECT a.*,h.name house_name,h.code house_code,t.name apartment_type_name,t.code apartment_type_code,
        (SELECT COUNT(*) FROM bookings b WHERE b.apartment_id=a.id AND b.status NOT IN ('cancelled','rejected') AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00')) booking_count
        FROM apartments a
        LEFT JOIN houses h ON h.id=a.house_id
        LEFT JOIN apartment_types t ON t.id=a.apartment_type_id
        ORDER BY COALESCE(h.sort_order,999),COALESCE(h.name,''),a.sort_order,a.name")->fetchAll();
    foreach ($rows as &$row) {
        $row['amenities'] = json_decode((string)$row['amenities_json'],true) ?: [];
        $row['wifi_password_present'] = !empty($row['wifi_password_encrypted']);
        unset($row['wifi_password_encrypted']);
    }
    json_response(['ok'=>true,'apartments'=>$rows]);
}

function save_apartment(): never
{
    $d=request_data(); $id=(int)($d['id']??0); $old=$id?fetch_row('apartments',$id):null;
    if($id&&!$old)throw new NotFoundException('Apartment nicht gefunden.');
    $houseId=(int)($d['house_id']??($old['house_id']??0))?:null;
    $typeId=(int)($d['apartment_type_id']??($old['apartment_type_id']??0))?:null;
    if(!$id&&(!$houseId||!$typeId))throw new ValidationException('FÃ¼r ein neues Apartment mÃ¼ssen Haus und Wohnungstyp gewÃ¤hlt werden.');
    $apartmentNumber=Validator::text($d,'apartment_number','Apartmentnummer',60,$id===0);
    $code=strtoupper(Validator::text($d,'code','Interne Kennung',60,false));
    if($code===''&&$houseId&&$apartmentNumber!==''){
        $stmt=db()->prepare('SELECT code FROM houses WHERE id=?');$stmt->execute([$houseId]);$houseCode=(string)$stmt->fetchColumn();
        if($houseCode!=='')$code=strtoupper($houseCode.'-'.$apartmentNumber);
    }
    if($code==='')throw new ValidationException('Die interne Kennung ist erforderlich.');
    $name=Validator::text($d,'name','Anzeigename',160,false);
    if($name==='')$name=$code;
    $typeName=Validator::text($d,'type','Typbezeichnung',80,false);
    $typeDefaults=[];
    if($typeId){$stmt=db()->prepare('SELECT * FROM apartment_types WHERE id=?');$stmt->execute([$typeId]);$typeDefaults=$stmt->fetch()?:[];if(!$typeDefaults)throw new ValidationException('Der gewÃ¤hlte Wohnungstyp existiert nicht.');$typeName=(string)$typeDefaults['name'];}
    if($houseId){$stmt=db()->prepare('SELECT COUNT(*) FROM houses WHERE id=?');$stmt->execute([$houseId]);if(!(int)$stmt->fetchColumn())throw new ValidationException('Das gewÃ¤hlte Haus existiert nicht.');}
    $amenities=array_values(array_filter(array_map('trim',preg_split('/[,;\n]+/',(string)($d['amenities']??'')))));
    $basePrice=($d['base_price']??'')===''?(float)($typeDefaults['standard_price']??0):max(0,(float)$d['base_price']);
    $cleaningFee=($d['cleaning_fee']??'')===''?(float)($typeDefaults['cleaning_fee']??0):max(0,(float)$d['cleaning_fee']);
    $breakfast=($d['breakfast_price']??'')===''?(float)($typeDefaults['breakfast_price']??0):max(0,(float)$d['breakfast_price']);
    $halfBoard=($d['half_board_price']??'')===''?(float)($typeDefaults['half_board_price']??0):max(0,(float)$d['half_board_price']);
    $wifiEncrypted=$old['wifi_password_encrypted']??null;
    if(array_key_exists('wifi_password',$d)&&trim((string)$d['wifi_password'])!=='')$wifiEncrypted=Crypto::encrypt(trim((string)$d['wifi_password']));
    if(normalize_bool($d['clear_wifi_password']??0))$wifiEncrypted=null;
    $values=[
        $houseId,$typeId,$apartmentNumber,$code,$name,$typeName,Validator::oneOf($d,'status','Status',['active','inactive','maintenance'],'active'),
        max(1,(int)($d['max_guests']??($typeDefaults['max_occupancy']??2))),max(0,(int)($d['bedrooms']??($typeDefaults['bedrooms']??1))),max(0,(int)($d['bathrooms']??1)),
        $basePrice,$cleaningFee,$breakfast,$halfBoard,max(0,(float)($d['parking_price_per_night']??($typeDefaults['parking_price']??0))),max(0,(float)($d['pet_price_per_night']??($typeDefaults['pet_price']??0))),
        max(0,(float)($d['extra_bed_price_per_night']??($typeDefaults['extra_bed_price']??0))),max(0,(float)($d['baby_bed_fee']??($typeDefaults['baby_bed_price']??0))),max(0,(float)($d['late_checkout_fee']??0)),
        normalize_bool($d['internet_access']??0),Validator::text($d,'full_address','Adresse',255),Validator::text($d,'floor','Etage',60),Validator::text($d,'location_description','Lage',190),
        normalize_bool($d['balcony']??0),normalize_bool($d['terrace']??0),normalize_bool($d['sea_view']??0),Validator::text($d,'parking_number','Parkplatznummer',60),Validator::text($d,'key_number','SchlÃ¼sselnummer',60),
        Validator::text($d,'wifi_ssid','WLAN-Name',190),$wifiEncrypted,Validator::oneOf($d,'price_adjustment_type','Preisabweichung',['fixed','percent'],'fixed'),(float)($d['price_adjustment_value']??0),
        trim((string)($d['min_stay_override']??''))===''?null:max(1,(int)$d['min_stay_override']),Validator::text($d,'cleaning_instructions','Reinigungshinweise',20000),normalize_bool($d['out_of_service']??0),normalize_bool($d['owner_occupancy_allowed']??0),Validator::text($d,'internal_remarks','Interne Bemerkungen',20000),
        Validator::text($d,'color','Kalenderfarbe',20)?:'#2563eb',Validator::text($d,'description','Beschreibung',20000),json_encode($amenities,JSON_UNESCAPED_UNICODE),Validator::text($d,'image_url','Bild-URL',500),(int)($d['sort_order']??0)
    ];
    $columns='house_id=?,apartment_type_id=?,apartment_number=?,code=?,name=?,type=?,status=?,max_guests=?,bedrooms=?,bathrooms=?,base_price=?,cleaning_fee=?,breakfast_price=?,half_board_price=?,parking_price_per_night=?,pet_price_per_night=?,extra_bed_price_per_night=?,baby_bed_fee=?,late_checkout_fee=?,internet_access=?,full_address=?,floor=?,location_description=?,balcony=?,terrace=?,sea_view=?,parking_number=?,key_number=?,wifi_ssid=?,wifi_password_encrypted=?,price_adjustment_type=?,price_adjustment_value=?,min_stay_override=?,cleaning_instructions=?,out_of_service=?,owner_occupancy_allowed=?,internal_remarks=?,color=?,description=?,amenities_json=?,image_url=?,sort_order=?';
    try{
        if($id)db()->prepare('UPDATE apartments SET '.$columns.' WHERE id=?')->execute([...$values,$id]);
        else{db()->prepare('INSERT INTO apartments(house_id,apartment_type_id,apartment_number,code,name,type,status,max_guests,bedrooms,bathrooms,base_price,cleaning_fee,breakfast_price,half_board_price,parking_price_per_night,pet_price_per_night,extra_bed_price_per_night,baby_bed_fee,late_checkout_fee,internet_access,full_address,floor,location_description,balcony,terrace,sea_view,parking_number,key_number,wifi_ssid,wifi_password_encrypted,price_adjustment_type,price_adjustment_value,min_stay_override,cleaning_instructions,out_of_service,owner_occupancy_allowed,internal_remarks,color,description,amenities_json,image_url,sort_order) VALUES('.implode(',',array_fill(0,count($values),'?')).')')->execute($values);$id=(int)db()->lastInsertId();}
    }catch(PDOException $e){if((string)$e->getCode()==='23000')throw new ConflictException('Die interne Kennung ist bereits vergeben oder Stammdaten sind ungÃ¼ltig.');throw $e;}
    $new=fetch_row('apartments',$id);AuditLogger::record('apartment',$id,$old?'update':'create',$old,$new,'Apartment gespeichert');
    json_response(['ok'=>true,'message'=>'Apartment gespeichert.','id'=>$id]);
}

function delete_apartment(): never
{
    $id=(int)(request_data()['id']??0); if(!$id) throw new ValidationException('Apartment fehlt.');
    $old=fetch_row('apartments',$id);if(!$old)throw new NotFoundException('Apartment nicht gefunden.');
    $stmt=db()->prepare('SELECT COUNT(*) FROM bookings WHERE apartment_id=?');$stmt->execute([$id]);
    if((int)$stmt->fetchColumn()>0) throw new ConflictException('Das Apartment hat Buchungen und kann nicht gelÃ¶scht werden. Setzen Sie es stattdessen auf inaktiv oder auÃŸer Betrieb.');
    db()->prepare('DELETE FROM apartments WHERE id=?')->execute([$id]);AuditLogger::record('apartment',$id,'delete',$old,null,'Apartment gelÃ¶scht');
    json_response(['ok'=>true,'message'=>'Apartment gelÃ¶scht.']);
}

function list_guests(): never
{
    if (function_exists('ensure_delete_center_v23663')) ensure_delete_center_v23663();
    $q=trim((string)($_GET['q']??''));
    // Whitelist statt freiem ORDER BY, da sort/dir aus dem Query-String kommen (SQL-Injection-Schutz).
    $sortColumns=['name'=>'g.last_name %DIR%,g.first_name %DIR%','category'=>'gc.name %DIR%','email'=>'g.email %DIR%','city'=>'g.city %DIR%','language'=>'g.language %DIR%','bookings'=>'booking_count %DIR%'];
    $sort=(string)($_GET['sort']??'name');
    if(!isset($sortColumns[$sort]))$sort='name';
    $dir=strtoupper((string)($_GET['dir']??'asc'))==='DESC'?'DESC':'ASC';
    $orderBy=str_replace('%DIR%',$dir,$sortColumns[$sort]);
    $sql="SELECT g.*,gc.name category_name,gc.color category_color,(SELECT COUNT(*) FROM bookings b WHERE b.guest_id=g.id AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00')) booking_count FROM guests g LEFT JOIN guest_categories gc ON gc.id=g.category_id WHERE (g.deleted_at IS NULL OR g.deleted_at='0000-00-00 00:00:00')";$params=[];
    if($q!==''){
        $sql.=" AND (CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,'')) LIKE ? OR g.email LIKE ? OR g.phone LIKE ? OR g.fixed_phone LIKE ? OR gc.name LIKE ? OR g.passport_number LIKE ? OR g.company LIKE ? OR g.city LIKE ?)";
        $like='%'.$q.'%';$params=[$like,$like,$like,$like,$like,$like,$like,$like];
    }
    $sql.=" ORDER BY $orderBy LIMIT 1000";$stmt=db()->prepare($sql);$stmt->execute($params);
    json_response(['ok'=>true,'guests'=>$stmt->fetchAll()]);
}

function save_guest(): never
{
    $d=request_data();$id=(int)($d['id']??0);$old=$id?fetch_row('guests',$id):null;if($id&&!$old)throw new NotFoundException('Gast nicht gefunden.');
    $first=Validator::text($d,'first_name','Vorname',100,true);$last=Validator::text($d,'last_name','Nachname',120,true);
    $email=Validator::email($d,'email','E-Mail');$dob=nullable_date($d['date_of_birth']??null);$issueDate=nullable_date($d['document_issue_date']??null);$categoryId=(int)($d['category_id']??0)?:null;
    if($categoryId){$stmt=db()->prepare('SELECT COUNT(*) FROM guest_categories WHERE id=?');$stmt->execute([$categoryId]);if(!(int)$stmt->fetchColumn())throw new ValidationException('Die gewÃ¤hlte Gastkategorie existiert nicht.');}
    $values=[
        Validator::text($d,'title','Anrede',30)?:null,$first,$last,Validator::text($d,'second_last_name','Zweiter Nachname',120)?:null,
        Validator::text($d,'gender','Geschlecht',20)?:null,Validator::text($d,'nationality','NationalitÃ¤t',100)?:null,$email?:null,
        Validator::text($d,'phone','Mobiltelefon',80)?:null,Validator::text($d,'address','Adresse',190)?:null,Validator::text($d,'postal_code','Postleitzahl',30)?:null,
        Validator::text($d,'city','Ort',120)?:null,Validator::text($d,'country','Land',100)?:null,Validator::text($d,'language','Sprache',50)?:'Deutsch',
        $categoryId,normalize_bool($d['vip']??0),Validator::text($d,'own_color','Eigene Farbe',20)?:null,normalize_bool($d['repeat_guest']??0),$dob,
        Validator::text($d,'company','Firma',160)?:null,Validator::text($d,'passport_number','Dokumentnummer',100)?:null,Validator::text($d,'document_type','Dokumentart',40)?:null,
        Validator::text($d,'document_support_number','Supportnummer',100)?:null,$issueDate,Validator::text($d,'document_country','Ausstellungsland',100)?:null,
        Validator::text($d,'place_of_birth','Geburtsort',160)?:null,Validator::text($d,'province','Provinz',120)?:null,Validator::text($d,'fixed_phone','Festnetz',80)?:null,
        Validator::text($d,'emergency_contact_name','Notfallkontakt',160)?:null,Validator::text($d,'emergency_contact_phone','Notfalltelefon',80)?:null,
        normalize_bool($d['marketing_opt_in']??0),Validator::text($d,'preferences','Allgemeine WÃ¼nsche und Vorlieben',20000),Validator::text($d,'notes','Interne Gastnotizen',20000)
    ];
    if($id){
        db()->prepare('UPDATE guests SET title=?,first_name=?,last_name=?,second_last_name=?,gender=?,nationality=?,email=?,phone=?,address=?,postal_code=?,city=?,country=?,language=?,category_id=?,vip=?,own_color=?,repeat_guest=?,date_of_birth=?,company=?,passport_number=?,document_type=?,document_support_number=?,document_issue_date=?,document_country=?,place_of_birth=?,province=?,fixed_phone=?,emergency_contact_name=?,emergency_contact_phone=?,marketing_opt_in=?,preferences=?,notes=? WHERE id=?')->execute([...$values,$id]);
    }else{
        db()->prepare('INSERT INTO guests(title,first_name,last_name,second_last_name,gender,nationality,email,phone,address,postal_code,city,country,language,category_id,vip,own_color,repeat_guest,date_of_birth,company,passport_number,document_type,document_support_number,document_issue_date,document_country,place_of_birth,province,fixed_phone,emergency_contact_name,emergency_contact_phone,marketing_opt_in,preferences,notes) VALUES('.implode(',',array_fill(0,count($values),'?')).')')->execute($values);$id=(int)db()->lastInsertId();
    }
    $new=fetch_row('guests',$id);AuditLogger::record('guest',$id,$old?'update':'create',$old,$new,'Gast gespeichert');json_response(['ok'=>true,'message'=>'Gast gespeichert.','id'=>$id]);
}

function guest_categories(): never
{
    $rows=db()->query("SELECT c.*,(SELECT COUNT(*) FROM guests g WHERE g.category_id=c.id) guest_count FROM guest_categories c ORDER BY c.sort_order,c.name")->fetchAll();
    json_response(['ok'=>true,'categories'=>$rows]);
}
function save_guest_category(): never
{
    $d=request_data();$id=(int)($d['id']??0);$old=$id?fetch_guest_category_v208($id):null;$name=trim((string)($d['name']??''));if($name==='')throw new RuntimeException('Der Kategoriename ist erforderlich.');
    $values=[$name,trim((string)($d['color']??'#64748b')),trim((string)($d['description']??'')),normalize_bool($d['active']??1),(int)($d['sort_order']??0)];
    if($id){db()->prepare('UPDATE guest_categories SET name=?,color=?,description=?,active=?,sort_order=? WHERE id=?')->execute([...$values,$id]);}
    else{db()->prepare('INSERT INTO guest_categories(name,color,description,active,sort_order) VALUES(?,?,?,?,?)')->execute($values);$id=(int)db()->lastInsertId();}
    AuditLogger::record('guest_category',$id,$old?'update':'create',$old,fetch_guest_category_v208($id),'Gastkategorie gespeichert');json_response(['ok'=>true,'message'=>'Gastkategorie gespeichert.','id'=>$id]);
}
function delete_guest_category(): never
{
    $id=(int)(request_data()['id']??0);$old=fetch_guest_category_v208($id);if(!$old)throw new NotFoundException('Gastkategorie nicht gefunden.');$stmt=db()->prepare('SELECT COUNT(*) FROM guests WHERE category_id=?');$stmt->execute([$id]);if((int)$stmt->fetchColumn()>0)throw new RuntimeException('Die Kategorie ist GÃ¤sten zugeordnet und kann nicht gelÃ¶scht werden.');
    db()->prepare('DELETE FROM guest_categories WHERE id=?')->execute([$id]);AuditLogger::record('guest_category',$id,'delete',$old,null,'Gastkategorie gelÃ¶scht');json_response(['ok'=>true,'message'=>'Gastkategorie gelÃ¶scht.']);
}
function fetch_guest_category_v208(int $id): ?array{$stmt=db()->prepare('SELECT * FROM guest_categories WHERE id=? LIMIT 1');$stmt->execute([$id]);$r=$stmt->fetch();return $r?:null;}

function delete_guest(): never
{
    throw new ValidationException('Direktes Löschen ist deaktiviert. Bitte Löschcenter mit PIN verwenden, damit der Gast logisch gelöscht und wiederherstellbar bleibt.');
}

function list_bookings(): never
{
    if (function_exists('ensure_delete_center_v23663')) ensure_delete_center_v23663();
    $from=(string)($_GET['from']??date('Y-m-01',strtotime('-6 months')));$to=(string)($_GET['to']??date('Y-m-d',strtotime('+18 months')));$q=trim((string)($_GET['q']??''));$status=trim((string)($_GET['status']??''));$accounting=trim((string)($_GET['accounting']??''));
    $sql=booking_select()." WHERE b.arrival<? AND b.departure>? AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00')";$params=[$to,$from];
    if($status!==''){$sql.=' AND b.status=?';$params[]=$status;}
    if($accounting!==''&&in_array($accounting,['internal','external','none','portal_later'],true)){$sql.=" AND COALESCE(b.accounting_mode,'internal')=?";$params[]=$accounting;}
    if($q!==''){$sql.=" AND (b.reference LIKE ? OR CONCAT(g.first_name,' ',g.last_name) LIKE ? OR a.name LIKE ? OR COALESCE(at.name,aat.name) LIKE ? OR b.source LIKE ?)";$like='%'.$q.'%';array_push($params,$like,$like,$like,$like,$like);}
    $sql.=' ORDER BY b.arrival DESC LIMIT 2000';$stmt=db()->prepare($sql);$stmt->execute($params);
    json_response(['ok'=>true,'bookings'=>$stmt->fetchAll()]);
}

function get_booking(): never
{
    $id=(int)($_GET['id']??0);$stmt=db()->prepare(booking_select()." WHERE b.id=? AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00')");$stmt->execute([$id]);$row=$stmt->fetch();if(!$row)json_response(['ok'=>false,'message'=>'Buchung nicht gefunden oder logisch gelöscht.'],404);
    $row['child_ages']=json_decode((string)($row['child_ages_json']??''),true)?:[];
    $row['cancellation_snapshot']=json_decode((string)($row['cancellation_snapshot_json']??''),true)?:[];
    $stmt=db()->prepare('SELECT l.*,u.name user_name FROM booking_change_log l LEFT JOIN users u ON u.id=l.created_by WHERE l.booking_id=? ORDER BY l.id DESC LIMIT 30');$stmt->execute([$id]);
    json_response(['ok'=>true,'booking'=>$row,'history'=>$stmt->fetchAll()]);
}


function travellers_data(): never
{
    $bookingId=(int)($_GET['booking_id']??0);
    if(!$bookingId)throw new RuntimeException('Buchung fehlt.');
    $stmt=db()->prepare(booking_select().' WHERE b.id=?');$stmt->execute([$bookingId]);$booking=$stmt->fetch();
    if(!$booking)throw new RuntimeException('Buchung nicht gefunden.');
    $stmt=db()->prepare('SELECT * FROM booking_travellers WHERE booking_id=? ORDER BY is_primary DESC,id');
    $stmt->execute([$bookingId]);$rows=$stmt->fetchAll();
    if(!$rows){
        $stmt=db()->prepare("SELECT first_name,last_name,second_last_name,gender,passport_number document_number,
            document_support_number,document_type,document_issue_date,document_country,place_of_birth,province,
            nationality,date_of_birth,address,city,country,postal_code,fixed_phone,phone mobile_phone,email
            FROM guests WHERE id=?");
        $stmt->execute([(int)$booking['guest_id']]);$g=$stmt->fetch();
        if($g){$g['id']=0;$g['booking_id']=$bookingId;$g['is_primary']=1;$g['relationship_to_primary']='Hauptgast';$g['minor']=0;$g['signature_status']='open';$g['notes']='';$rows[]=$g;}
    }
    json_response(['ok'=>true,'booking'=>$booking,'travellers'=>$rows]);
}

function save_travellers(): never
{
    $d=request_data();$bookingId=(int)($d['booking_id']??0);$rows=$d['travellers']??[];
    if(!$bookingId||!is_array($rows))throw new RuntimeException('Buchung oder Reisedaten fehlen.');
    $oldBooking=fetch_booking_row($bookingId);
    if(!$oldBooking)throw new RuntimeException('Buchung nicht gefunden.');
    db()->beginTransaction();
    try{
        db()->prepare('DELETE FROM booking_travellers WHERE booking_id=?')->execute([$bookingId]);
        $insert=db()->prepare('INSERT INTO booking_travellers(booking_id,is_primary,first_name,last_name,second_last_name,gender,document_number,document_support_number,document_type,document_issue_date,document_country,place_of_birth,province,nationality,date_of_birth,address,city,country,postal_code,fixed_phone,mobile_phone,email,relationship_to_primary,minor,signature_status,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $primarySeen=false;$count=0;$missing=0;
        foreach($rows as $i=>$r){
            if(!is_array($r))continue;
            $first=trim((string)($r['first_name']??''));$last=trim((string)($r['last_name']??''));
            if($first===''&&$last==='')continue;
            if($first===''||$last==='')throw new RuntimeException('Bei jeder reisenden Person sind Vor- und Nachname erforderlich.');
            $isPrimary=normalize_bool($r['is_primary']??0);
            if($isPrimary&&$primarySeen)$isPrimary=0;
            if($isPrimary)$primarySeen=true;
            $dob=nullable_date($r['date_of_birth']??null);
            $minor=normalize_bool($r['minor']??0);
            if($dob){$age=(new DateTimeImmutable($dob))->diff(new DateTimeImmutable('today'))->y;if($age<18)$minor=1;}
            foreach(['gender','document_number','document_type','nationality','address','postal_code','city','country'] as $requiredKey){
                if(trim((string)($r[$requiredKey]??''))==='')$missing++;
            }
            if(!$dob)$missing++;
            if($minor===1&&trim((string)($r['relationship_to_primary']??''))==='')$missing++;
            $insert->execute([
                $bookingId,$isPrimary,$first,$last,
                trim((string)($r['second_last_name']??''))?:null,
                trim((string)($r['gender']??''))?:null,
                trim((string)($r['document_number']??''))?:null,
                trim((string)($r['document_support_number']??''))?:null,
                trim((string)($r['document_type']??''))?:null,
                nullable_date($r['document_issue_date']??null),
                trim((string)($r['document_country']??''))?:null,
                trim((string)($r['place_of_birth']??''))?:null,
                trim((string)($r['province']??''))?:null,
                trim((string)($r['nationality']??''))?:null,
                $dob,
                trim((string)($r['address']??''))?:null,
                trim((string)($r['city']??''))?:null,
                trim((string)($r['country']??''))?:null,
                trim((string)($r['postal_code']??''))?:null,
                trim((string)($r['fixed_phone']??''))?:null,
                trim((string)($r['mobile_phone']??''))?:null,
                trim((string)($r['email']??''))?:null,
                trim((string)($r['relationship_to_primary']??''))?:null,
                $minor,
                trim((string)($r['signature_status']??'open'))?:'open',
                trim((string)($r['notes']??''))
            ]);
            $count++;
        }
        if($count>0&&!$primarySeen){db()->prepare('UPDATE booking_travellers SET is_primary=1 WHERE booking_id=? ORDER BY id LIMIT 1')->execute([$bookingId]);}
        $policeStatus=$count>0&&$missing===0?'ready':'open';
        db()->prepare('UPDATE bookings SET police_status=?,police_sent_at=NULL WHERE id=?')->execute([$policeStatus,$bookingId]);
        log_booking_change($bookingId,'travellers_saved',$oldBooking,fetch_booking_row($bookingId),$count.' Reisende gespeichert; '.$missing.' Pflichtangaben fehlen');
        db()->commit();
    }catch(Throwable $e){db()->rollBack();throw $e;}
    json_response(['ok'=>true,'message'=>$count.' reisende Person(en) gespeichert.'.($missing?' '.$missing.' Pflichtangabe(n) fehlen.':' Meldedaten sind vollstÃ¤ndig.'),'count'=>$count,'missing_fields'=>$missing]);
}

function police_data(): never
{
    $from=(string)($_GET['from']??date('Y-m-d'));$to=(string)($_GET['to']??date('Y-m-d',strtotime('+30 days')));
    $status=trim((string)($_GET['status']??''));
    if(!valid_date($from)||!valid_date($to)||$from>$to)throw new RuntimeException('Zeitraum ungÃ¼ltig.');
    $sql=booking_select()." WHERE " . booking_active_condition_v236128('b') . " AND b.arrival BETWEEN ? AND ?";
    $params=[$from,$to];
    if($status!==''){$sql.=' AND b.police_status=?';$params[]=$status;}
    $sql.=' ORDER BY b.arrival,a.name,guest_name';
    $stmt=db()->prepare($sql);$stmt->execute($params);$bookings=$stmt->fetchAll();
    foreach($bookings as &$b){
        $stmt=db()->prepare('SELECT * FROM booking_travellers WHERE booking_id=? ORDER BY is_primary DESC,id');
        $stmt->execute([(int)$b['id']]);$b['travellers']=$stmt->fetchAll();
        $required=['first_name','last_name','gender','document_number','document_type','nationality','date_of_birth','address','postal_code','city','country'];
        $missing=0;
        foreach($b['travellers'] as $traveller){
            foreach($required as $key){if(trim((string)($traveller[$key]??''))==='')$missing++;}
            if((int)($traveller['minor']??0)===1 && trim((string)($traveller['relationship_to_primary']??''))==='')$missing++;
        }
        if(!$b['travellers'])$missing++;
        $b['missing_fields']=$missing;
    }
    json_response(['ok'=>true,'from'=>$from,'to'=>$to,'bookings'=>$bookings]);
}

function police_status(): never
{
    $d=request_data();$id=(int)($d['id']??0);$status=trim((string)($d['status']??'open'));
    $allowed=['open','ready','sent','error'];
    if(!$id)throw new RuntimeException('Buchung fehlt.');
    if(!in_array($status,$allowed,true))throw new RuntimeException('Meldestatus ungÃ¼ltig.');
    $old=fetch_booking_row($id);if(!$old)throw new RuntimeException('Buchung nicht gefunden.');
    $sent=$status==='sent'?date('Y-m-d H:i:s'):null;
    db()->prepare('UPDATE bookings SET police_status=?,police_sent_at=? WHERE id=?')->execute([$status,$sent,$id]);
    log_booking_change($id,'police_status',$old,fetch_booking_row($id),'Meldestatus: '.$status);AuditLogger::record('booking',$id,'police_status',$old,fetch_booking_row($id),'Meldestatus geÃ¤ndert');
    json_response(['ok'=>true,'message'=>'Meldestatus aktualisiert.']);
}

function save_booking(): never
{
    $d=request_data();$id=(int)($d['id']??0);$guestId=(int)($d['guest_id']??0);
    $apartmentId=(int)($d['apartment_id']??0)?:null;$typeId=(int)($d['apartment_type_id']??0)?:null;$arrival=(string)($d['arrival']??'');$departure=(string)($d['departure']??'');
    if(!$guestId||!valid_date($arrival)||!valid_date($departure)||$arrival>=$departure)throw new ValidationException('Gast und gÃ¼ltiger Zeitraum sind erforderlich.');
    $old=$id?fetch_booking_row($id):null;if($id&&!$old)throw new NotFoundException('Buchung nicht gefunden.');
    $stmt=db()->prepare('SELECT COUNT(*) FROM guests WHERE id=?');$stmt->execute([$guestId]);if(!(int)$stmt->fetchColumn())throw new ValidationException('Der gewÃ¤hlte Gast existiert nicht.');
    if($apartmentId){$stmt=db()->prepare('SELECT status,out_of_service,apartment_type_id FROM apartments WHERE id=?');$stmt->execute([$apartmentId]);$apartment=$stmt->fetch();if(!$apartment)throw new ValidationException('Das gewÃ¤hlte Apartment existiert nicht.');if((!$old||((int)($old['apartment_id']??0)!==$apartmentId))&&($apartment['status']!=='active'||(int)$apartment['out_of_service']===1))throw new ConflictException('Das gewÃ¤hlte Apartment ist nicht aktiv oder auÃŸer Betrieb.');$apartmentTypeId=(int)($apartment['apartment_type_id']??0)?:null;if($typeId&&$apartmentTypeId&&$typeId!==$apartmentTypeId)throw new ValidationException('Das konkrete Apartment gehÃ¶rt nicht zum gewÃ¤hlten Wohnungstyp.');$typeId=$typeId?:$apartmentTypeId;}
    if(!$typeId)throw new ValidationException('Bitte einen Wohnungstyp auswÃ¤hlen. Die konkrete Apartmentnummer kann spÃ¤ter intern zugeordnet werden.');
    BookingPolicyService::type($typeId);
    if($apartmentId&&booking_conflict($apartmentId,$arrival,$departure,$id?:null))throw new ConflictException('In diesem Zeitraum ist das Apartment bereits belegt oder gesperrt. Nutzen Sie im Belegungskalender die KonfliktauflÃ¶sung.');

    $reference=trim((string)($d['reference']??''))?:generate_reference();
    $breakfast=normalize_bool($d['breakfast']??0);$halfBoard=normalize_bool($d['half_board']??0);
    $breakfastStart=nullable_date($d['breakfast_start_date']??null);$breakfastEnd=nullable_date($d['breakfast_end_date']??null);
    $halfStart=nullable_date($d['half_board_start_date']??null);$halfEnd=nullable_date($d['half_board_end_date']??null);
    validate_service_range($breakfast,$breakfastStart,$breakfastEnd,$arrival,$departure,'FrÃ¼hstÃ¼ck');
    validate_service_range($halfBoard,$halfStart,$halfEnd,$arrival,$departure,'Halbpension');

    $status=Validator::oneOf($d,'status','Status',['inquiry','confirmed','checked_in','checked_out','cancelled','rejected'],'inquiry');
    if(!$apartmentId&&!in_array($status,['cancelled','rejected'],true)&&type_pool_available_count($typeId,$arrival,$departure,$id?:null)<=0){
        throw new ConflictException('FÃ¼r diesen Wohnungstyp ist im gewÃ¤hlten Zeitraum kein freies Apartment mehr vorhanden. Bitte Zeitraum oder Wohnungstyp prÃ¼fen.');
    }
    $actualCheckin=nullable_datetime($d['actual_checkin_at']??null);$actualCheckout=nullable_datetime($d['actual_checkout_at']??null);
    if($status==='checked_in'&&!$actualCheckin)$actualCheckin=date('Y-m-d H:i:s');
    if($status==='checked_out'&&!$actualCheckout)$actualCheckout=date('Y-m-d H:i:s');

    $adults=max(1,(int)($d['adults']??1));$children=max(0,(int)($d['children']??0));
    $babies=max(0,(int)($d['babies']??0));$pets=max(0,(int)($d['pets']??0));
    $capacityOverride=normalize_bool($d['capacity_override']??0);$capacityOverrideReason=trim((string)($d['capacity_override_reason']??''));
    $capacity=BookingPolicyService::capacityCheck($typeId,$adults,$children,$babies,(bool)$capacityOverride,$capacityOverrideReason);
    $childAges=BookingPolicyService::childAges($d['child_ages']??$d['child_ages_json']??[],$children,true);
    $childAgesJson=json_encode($childAges,JSON_UNESCAPED_UNICODE);
    $parking=max(0,(int)($d['parking_spaces']??0));$extraBeds=max(0,(int)($d['extra_beds']??0));
    $babyBeds=max(0,(int)($d['baby_beds']??0));$lateCheckout=normalize_bool($d['late_checkout']??0);
    $manualDiscount=max(0,(float)($d['discount_amount']??0));$touristTax=max(0,(float)($d['tourist_tax']??0));
    $discountCode=strtoupper(trim((string)($d['discount_code']??'')));

    $channelId=(int)($d['booking_channel_id']??0)?:null;
    $source=trim((string)($d['source']??'Direkt'))?:'Direkt';
    if($channelId){
        $stmt=db()->prepare('SELECT id,name,active,default_accounting_mode FROM booking_channels WHERE id=?');$stmt->execute([$channelId]);$channel=$stmt->fetch();
        if(!$channel)throw new ValidationException('Der gewÃ¤hlte Buchungskanal existiert nicht.');
        if(!(int)$channel['active']&&(!$old||(int)($old['booking_channel_id']??0)!==$channelId))throw new ValidationException('Der gewÃ¤hlte Buchungskanal ist nicht aktiv.');
        $source=(string)$channel['name'];
    }
    $accountingMode = BookingAccountingService::normalizeMode((string)($d['accounting_mode'] ?? ($channel['default_accounting_mode'] ?? '')), $source);
    $billingExcludedReason = trim((string)($d['billing_excluded_reason'] ?? ''));
    if ($accountingMode !== BookingAccountingService::MODE_INTERNAL && $billingExcludedReason === '') {
        $billingExcludedReason = $accountingMode === BookingAccountingService::MODE_EXTERNAL ? 'Externes Portal/Import – nicht intern abrechnen' : 'Nur Belegung – keine Abrechnung';
    }

    $specialType=Validator::oneOf($d,'special_price_type','Sonderpreisart',['none','fixed_total','fixed_nightly','percent_discount','fixed_discount','surcharge'],'none');
    $specialValue=max(0,(float)str_replace(',','.',(string)($d['special_price_value']??0)));
    $specialReason=trim((string)($d['special_price_reason']??''))?:null;
    if($specialType!=='none'&&$specialValue<=0)throw new ValidationException('FÃ¼r den Sonderpreis muss ein Wert grÃ¶ÃŸer als null eingetragen werden.');
    if($specialType!=='none'&&!$specialReason)throw new ValidationException('Bitte den Grund fÃ¼r den Sonderpreis eintragen.');
    $priceLocked=normalize_bool($d['price_locked']??0);
    $minStayOverride=normalize_bool($d['min_stay_override']??0);
    $minStayOverrideReason=trim((string)($d['min_stay_override_reason']??''))?:null;

    $minimum=null;
    if($apartmentId&&!in_array($status,['cancelled','rejected'],true)){
        $minimum=PricingService::minimumStay($apartmentId,$arrival,$departure);
        if(!$minimum['valid']&&!$minStayOverride){
            throw new ValidationException('Der gewÃ¤hlte Aufenthalt hat '.$minimum['nights'].' NÃ¤chte. Erforderlich sind mindestens '.$minimum['required'].' NÃ¤chte ('.$minimum['source'].').');
        }
        if(!$minimum['valid']&&$minStayOverride&&!$minStayOverrideReason){
            throw new ValidationException('Bitte begrÃ¼nden, warum der Mindestaufenthalt unterschritten wird.');
        }
    }

    $breakfastDays=$breakfastStart&&$breakfastEnd?max(0,nights($breakfastStart,(new DateTimeImmutable($breakfastEnd))->modify('+1 day')->format('Y-m-d'))):($breakfast?nights($arrival,$departure):0);
    $halfDays=$halfStart&&$halfEnd?max(0,nights($halfStart,(new DateTimeImmutable($halfEnd))->modify('+1 day')->format('Y-m-d'))):($halfBoard?nights($arrival,$departure):0);
    $recalculate=normalize_bool($d['recalculate_price']??0);
    $totalRaw=trim((string)($d['total_price']??''));
    $priceDetails=null;
    if($apartmentId){
        $priceDetails=calculate_price_details($apartmentId,$arrival,$departure,[
            'parking_spaces'=>$parking,'pets'=>$pets,'extra_beds'=>$extraBeds,'baby_beds'=>$babyBeds,
            'late_checkout'=>$lateCheckout,'adults'=>$adults,'children'=>$children,
            'breakfast'=>$breakfast,'half_board'=>$halfBoard,'breakfast_days'=>$breakfastDays,'half_board_days'=>$halfDays,
            'discount_code'=>$discountCode,'manual_discount'=>$manualDiscount,'tourist_tax'=>$touristTax,
            'special_price_type'=>$specialType,'special_price_value'=>$specialValue
        ]);
    }elseif(($recalculate||$totalRaw==='')&&!in_array($status,['cancelled','rejected'],true)){
        $quote=OfferService::calculate([
            'apartment_type_id'=>$typeId,'arrival'=>$arrival,'departure'=>$departure,
            'adults'=>$adults,'children'=>$children,'babies'=>$babies,'pets'=>$pets,'child_ages'=>$childAges,
            'capacity_override'=>$capacityOverride,'capacity_override_reason'=>$capacityOverrideReason,
            'parking_spaces'=>$parking,'extra_beds'=>$extraBeds,'baby_beds'=>$babyBeds,
            'breakfast'=>$breakfast,'half_board'=>$halfBoard,'breakfast_days'=>$breakfastDays,'half_board_days'=>$halfDays,
            'discount_code'=>$discountCode,'manual_discount'=>$manualDiscount,'manual_discount_reason'=>'Interne Buchungsbearbeitung',
            'min_stay_override'=>$minStayOverride,
        ]);
        $priceDetails=$quote;$priceDetails['total']=(float)$quote['total_amount'];
        $minimum=['valid'=>(bool)$quote['minimum_valid'],'nights'=>(int)$quote['nights'],'required'=>(int)$quote['minimum_stay'],'source'=>(string)$quote['minimum_source']];
        $touristTax=(float)$quote['tourist_tax'];$manualDiscount=(float)$quote['discount_amount'];
    }
    if($priceLocked&&$old&&!$recalculate&&$totalRaw==='')$totalRaw=(string)$old['total_price'];
    $total=($recalculate||$totalRaw==='')?(float)($priceDetails['total']??0):(float)str_replace(',','.',$totalRaw);
    $discountCodeId=$priceDetails['discount_code_id']??null;
    $priceBreakdown=$priceDetails?json_encode($priceDetails,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null;

    $paid=max(0,(float)($d['paid_amount']??0));$paymentStatus=trim((string)($d['payment_status']??''));
    if($paymentStatus==='')$paymentStatus=$paid<=0?'open':($paid+0.005>=$total?'paid':'partial');
    $policeStatus=trim((string)($d['police_status']??($old['police_status']??'open')));
    if(!in_array($policeStatus,['open','ready','sent','error'],true))$policeStatus='open';
    $policeSentAt=$policeStatus==='sent'?($old['police_sent_at']??date('Y-m-d H:i:s')):null;
    $cancellationSnapshot=$old&&trim((string)($old['cancellation_snapshot_json']??''))!==''?(string)$old['cancellation_snapshot_json']:json_encode(BookingPolicyService::cancellationSnapshot($typeId),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $publicLanguage=array_key_exists((string)($d['public_language']??''),OfferService::LANGUAGES)?(string)$d['public_language']:($old['public_language']??null);

    $values=[
        $reference,$guestId,$apartmentId,$typeId,$arrival,$departure,
        nullable_time($d['planned_arrival_time']??null),nullable_time($d['planned_departure_time']??null),
        $actualCheckin,$actualCheckout,$adults,$children,$babies,$pets,$capacityOverride,$capacityOverrideReason?:null,$childAgesJson,$status,
        $source,$publicLanguage,$channelId,$accountingMode,$billingExcludedReason?:null,$total,$paid,$paymentStatus,
        max(0,(float)($d['deposit_amount']??0)),$touristTax,$manualDiscount,
        $discountCode?:null,$discountCodeId,$parking,$extraBeds,$babyBeds,$lateCheckout,
        trim((string)($d['payment_method']??''))?:null,trim((string)($d['payment_reference']??''))?:null,
        $priceBreakdown,$cancellationSnapshot,$specialType,$specialValue,$specialReason,$priceLocked,$minStayOverride,$minStayOverrideReason,
        $policeStatus,$policeSentAt,nullable_datetime($d['contract_signed_at']??null),
        $breakfast,$breakfastStart,$breakfastEnd,$halfBoard,$halfStart,$halfEnd,
        normalize_bool($d['is_upgrade']??0),(int)($d['upgrade_from_apartment_id']??0)?:null,
        trim((string)($d['upgrade_note']??''))?:null,trim((string)($d['vehicle_plate']??''))?:null,
        trim((string)($d['guest_request']??'')),trim((string)($d['special_requests']??'')),trim((string)($d['internal_notes']??'')),trim((string)($d['notes']??''))
    ];

    db()->beginTransaction();
    try{
        if(!$apartmentId&&!in_array($status,['cancelled','rejected'],true)){
            // Sperrt den Wohnungstyp-Pool wÃ¤hrend der letzten VerfÃ¼gbarkeitsprÃ¼fung.
            // So kÃ¶nnen zwei parallele interne VorgÃ¤nge nicht denselben letzten Platz erhalten.
            $lock=db()->prepare("SELECT id FROM apartments WHERE apartment_type_id=? AND status='active' AND out_of_service=0 FOR UPDATE");
            $lock->execute([$typeId]);$lock->fetchAll();
            $lock=db()->prepare("SELECT id FROM bookings WHERE apartment_type_id=? AND status NOT IN ('cancelled','rejected') AND (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND arrival<? AND departure>? FOR UPDATE");
            $lock->execute([$typeId,$departure,$arrival]);$lock->fetchAll();
            if(type_pool_available_count($typeId,$arrival,$departure,$id?:null)<=0){
                throw new ConflictException('FÃ¼r diesen Wohnungstyp ist im gewÃ¤hlten Zeitraum inzwischen kein freies Apartment mehr vorhanden.');
            }
        }
        if($apartmentId&&!in_array($status,['cancelled','rejected'],true)){
            // Wohnungszeile selbst sperren: existiert immer, serialisiert daher auch dann,
            // wenn im Zielzeitraum noch keine Buchung liegt (leerer Zeitraum ergibt bei
            // MariaDB keine Gap-Lock auf die bookings-Tabelle).
            db()->prepare('SELECT id FROM apartments WHERE id=? FOR UPDATE')->execute([$apartmentId]);
            // Zusaetzlich Ã¼berschneidende Buchungen des Apartments sperren und den Konflikt direkt vor dem Schreiben in derselben Transaktion erneut prÃ¼fen.
            $lockSql="SELECT id FROM bookings WHERE apartment_id=? AND status NOT IN ('cancelled','rejected') AND (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND arrival<? AND departure>?".($id?' AND id<>?':'').' FOR UPDATE';
            $lockParams=$id?[$apartmentId,$departure,$arrival,$id]:[$apartmentId,$departure,$arrival];
            $lock=db()->prepare($lockSql);$lock->execute($lockParams);$lock->fetchAll();
            if(booking_conflict($apartmentId,$arrival,$departure,$id?:null)){
                throw new ConflictException('Das Apartment wurde inzwischen belegt oder gesperrt. Bitte Kalender neu laden.');
            }
        }
        if($id){
            $stmt=db()->prepare('UPDATE bookings SET reference=?,guest_id=?,apartment_id=?,apartment_type_id=?,arrival=?,departure=?,planned_arrival_time=?,planned_departure_time=?,actual_checkin_at=?,actual_checkout_at=?,adults=?,children=?,babies=?,pets=?,capacity_override=?,capacity_override_reason=?,child_ages_json=?,status=?,source=?,public_language=?,booking_channel_id=?,accounting_mode=?,billing_excluded_reason=?,total_price=?,paid_amount=?,payment_status=?,deposit_amount=?,tourist_tax=?,discount_amount=?,discount_code=?,discount_code_id=?,parking_spaces=?,extra_beds=?,baby_beds=?,late_checkout=?,payment_method=?,payment_reference=?,price_breakdown_json=?,cancellation_snapshot_json=?,special_price_type=?,special_price_value=?,special_price_reason=?,price_locked=?,min_stay_override=?,min_stay_override_reason=?,police_status=?,police_sent_at=?,contract_signed_at=?,breakfast=?,breakfast_start_date=?,breakfast_end_date=?,half_board=?,half_board_start_date=?,half_board_end_date=?,is_upgrade=?,upgrade_from_apartment_id=?,upgrade_note=?,vehicle_plate=?,guest_request=?,special_requests=?,internal_notes=?,notes=? WHERE id=?');
            $stmt->execute([...$values,$id]);
        }else{
            $stmt=db()->prepare('INSERT INTO bookings(reference,guest_id,apartment_id,apartment_type_id,arrival,departure,planned_arrival_time,planned_departure_time,actual_checkin_at,actual_checkout_at,adults,children,babies,pets,capacity_override,capacity_override_reason,child_ages_json,status,source,public_language,booking_channel_id,accounting_mode,billing_excluded_reason,total_price,paid_amount,payment_status,deposit_amount,tourist_tax,discount_amount,discount_code,discount_code_id,parking_spaces,extra_beds,baby_beds,late_checkout,payment_method,payment_reference,price_breakdown_json,cancellation_snapshot_json,special_price_type,special_price_value,special_price_reason,price_locked,min_stay_override,min_stay_override_reason,police_status,police_sent_at,contract_signed_at,breakfast,breakfast_start_date,breakfast_end_date,half_board,half_board_start_date,half_board_end_date,is_upgrade,upgrade_from_apartment_id,upgrade_note,vehicle_plate,guest_request,special_requests,internal_notes,notes) VALUES('.implode(',',array_fill(0,count($values),'?')).')');
            $stmt->execute($values);$id=(int)db()->lastInsertId();
        }
        if($accountingMode !== BookingAccountingService::MODE_INTERNAL){
            BookingAccountingService::neutralizeInternalBilling(db(), $id, $accountingMode, $billingExcludedReason);
        }
        if(array_key_exists('guest_category_id',$d)){db()->prepare('UPDATE guests SET category_id=? WHERE id=?')->execute([(int)$d['guest_category_id']?:null,$guestId]);}
        $oldCodeId=(int)($old['discount_code_id']??0);$newCodeId=(int)($discountCodeId??0);
        if($oldCodeId!==$newCodeId){
            if($oldCodeId)db()->prepare('UPDATE discount_codes SET used_count=GREATEST(0,used_count-1) WHERE id=?')->execute([$oldCodeId]);
            if($newCodeId){
                // Atomar erhÃ¶hen und max_uses direkt in derselben Transaktion erneut prÃ¼fen, damit zwei gleichzeitige Buchungen einen fast ausgeschÃ¶pften Code nicht gemeinsam Ã¼berziehen kÃ¶nnen.
                $codeStmt=db()->prepare('UPDATE discount_codes SET used_count=used_count+1 WHERE id=? AND (max_uses IS NULL OR used_count<max_uses)');
                $codeStmt->execute([$newCodeId]);
                if($codeStmt->rowCount()===0){
                    throw new ConflictException('Der Rabattcode ist inzwischen ausgeschÃ¶pft. Bitte Buchung ohne diesen Code speichern oder einen anderen Code wÃ¤hlen.');
                }
            }
        }
        $note=$old?'Buchung bearbeitet':'Buchung angelegt';
        if($minimum&&!$minimum['valid']&&$minStayOverride)$note.=' Â· Mindestaufenthalt ausnahmsweise unterschritten: '.$minStayOverrideReason;
        if($specialType!=='none')$note.=' Â· Sonderpreis: '.$specialReason;
        if($capacity['exceeds_standard'])$note.=' Â· Belegungsausnahme bestÃ¤tigt'.($capacityOverrideReason?': '.$capacityOverrideReason:'');
        log_booking_change($id,$old?'booking_updated':'booking_created',$old,fetch_booking_row($id),$note);
        db()->commit();
    }catch(PDOException $e){db()->rollBack();if((string)$e->getCode()==='23000')throw new ConflictException('Buchungsnummer oder externe Referenz ist bereits vergeben.');throw $e;}catch(Throwable $e){db()->rollBack();throw $e;}
    AuditLogger::record('booking',$id,$old?'update':'create',$old,fetch_booking_row($id),$old?'Buchung bearbeitet':'Buchung angelegt');
    try { HousekeepingWorkflow::upsertDepartureTask($id); } catch (Throwable $e) { AppLogger::error($e,['booking_id'=>$id],'housekeeping-auto-task'); }
    json_response(['ok'=>true,'message'=>'Buchung gespeichert.','id'=>$id,'price_details'=>$priceDetails,'minimum_stay'=>$minimum]);
}


function type_pool_available_count(int $typeId,string $arrival,string $departure,?int $ignoreBookingId=null): int
{
    $ignoreSql=$ignoreBookingId?' AND b.id<>?':'';
    $params=[$typeId,$departure,$arrival];
    if($ignoreBookingId)$params[]=$ignoreBookingId;
    $params[]=$departure;$params[]=$arrival;
    $sql="SELECT COUNT(*) FROM apartments a
          WHERE a.apartment_type_id=? AND a.status='active' AND a.out_of_service=0
          AND NOT EXISTS(
              SELECT 1 FROM bookings b
              WHERE b.apartment_id=a.id AND b.status NOT IN ('cancelled','rejected')
              AND b.arrival<? AND b.departure>? AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00'){$ignoreSql}
          )
          AND NOT EXISTS(
              SELECT 1 FROM availability_blocks bl
              WHERE bl.apartment_id=a.id AND bl.start_date<? AND bl.end_date>?
          )";
    $stmt=db()->prepare($sql);$stmt->execute($params);$free=(int)$stmt->fetchColumn();
    $sql="SELECT COUNT(*) FROM bookings
          WHERE apartment_id IS NULL AND apartment_type_id=? AND status NOT IN ('cancelled','rejected')
          AND (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND arrival<? AND departure>?".($ignoreBookingId?' AND id<>?':'');
    $params=[$typeId,$departure,$arrival];if($ignoreBookingId)$params[]=$ignoreBookingId;
    $stmt=db()->prepare($sql);$stmt->execute($params);
    return max(0,$free-(int)$stmt->fetchColumn());
}


function delete_booking(): never
{
    throw new ValidationException('Direktes Löschen ist deaktiviert. Bitte Löschcenter mit PIN verwenden, damit die Buchung logisch gelöscht und wiederherstellbar bleibt.');
}

function set_booking_status(): never
{
    $d=request_data();$id=(int)($d['id']??0);$status=trim((string)($d['status']??''));$allowed=['inquiry','confirmed','checked_in','checked_out','cancelled','rejected'];if(!in_array($status,$allowed,true))throw new RuntimeException('Status ungÃ¼ltig.');
    $old=fetch_booking_row($id);if(!$old)throw new RuntimeException('Buchung nicht gefunden.');$checkin=$old['actual_checkin_at'];$checkout=$old['actual_checkout_at'];if($status==='checked_in'&&!$checkin)$checkin=date('Y-m-d H:i:s');if($status==='checked_out'&&!$checkout)$checkout=date('Y-m-d H:i:s');
    $cancelledAt=$old['cancelled_at']??null;$feePercent=(float)($old['cancellation_fee_percent']??0);$feeAmount=(float)($old['cancellation_fee_amount']??0);
    if($status==='cancelled'){$snapshot=json_decode((string)($old['cancellation_snapshot_json']??''),true)?:((int)($old['apartment_type_id']??0)?BookingPolicyService::cancellationSnapshot((int)$old['apartment_type_id']):[]);$quote=BookingPolicyService::cancellationQuote($snapshot,(string)$old['arrival'],date('Y-m-d'),(float)$old['total_price']);$cancelledAt=date('Y-m-d H:i:s');$feePercent=(float)$quote['percent'];$feeAmount=(float)$quote['amount'];}
    elseif($status!=='cancelled'){$cancelledAt=null;$feePercent=0;$feeAmount=0;}
    db()->prepare('UPDATE bookings SET status=?,actual_checkin_at=?,actual_checkout_at=?,cancelled_at=?,cancellation_fee_percent=?,cancellation_fee_amount=? WHERE id=?')->execute([$status,$checkin,$checkout,$cancelledAt,$feePercent,$feeAmount,$id]);$note='Status: '.status_text($status).($status==='cancelled'?' Â· Stornokosten '.$feePercent.' % = '.number_format($feeAmount,2,',','.').' â‚¬':'');log_booking_change($id,'status_changed',$old,fetch_booking_row($id),$note);AuditLogger::record('booking',$id,'status_change',$old,fetch_booking_row($id),$note);try{HousekeepingWorkflow::upsertDepartureTask($id);}catch(Throwable $e){AppLogger::error($e,['booking_id'=>$id],'housekeeping-auto-task');}json_response(['ok'=>true,'message'=>'Status auf â€ž'.status_text($status).'â€œ gesetzt.','cancellation_fee_percent'=>$feePercent,'cancellation_fee_amount'=>$feeAmount]);
}
function unassign_booking(): never
{
    $id=(int)(request_data()['id']??0);$old=fetch_booking_row($id);if(!$old)throw new RuntimeException('Buchung nicht gefunden.');db()->beginTransaction();try{db()->prepare('UPDATE bookings SET apartment_id=NULL WHERE id=?')->execute([$id]);db()->prepare("DELETE FROM housekeeping_tasks WHERE booking_id=? AND task_type='turnover' AND status IN ('open','planned','assigned')")->execute([$id]);log_booking_change($id,'unassigned',$old,fetch_booking_row($id),'In Nicht zugeordnet verschoben');db()->commit();AuditLogger::record('booking',$id,'unassign',$old,fetch_booking_row($id),'Buchung nicht zugeordnet');}catch(Throwable $e){db()->rollBack();throw $e;}json_response(['ok'=>true,'message'=>'Buchung ist jetzt nicht zugeordnet. Offene Wechselreinigungen wurden entfernt.']);
}
function price_quote(): never
{
    $d=$_SERVER['REQUEST_METHOD']==='GET'?$_GET:request_data();
    $apartmentId=(int)($d['apartment_id']??0);
    $typeId=(int)($d['apartment_type_id']??0);
    $arrival=(string)($d['arrival']??'');
    $departure=(string)($d['departure']??'');
    if((!$apartmentId&&!$typeId)||!valid_date($arrival)||!valid_date($departure)||$arrival>=$departure){
        throw new RuntimeException('Wohnungstyp und gÃ¼ltiger Zeitraum erforderlich. Eine konkrete Apartmentnummer ist optional.');
    }

    // Bei typbezogenen Buchungen kommt dieselbe serverseitige Preislogik wie bei
    // Angeboten zum Einsatz. Damit kÃ¶nnen Buchungen bereits vor der internen
    // Zuteilung einer konkreten Apartmentnummer zuverlÃ¤ssig kalkuliert werden.
    if(!$apartmentId){
        $quote=OfferService::calculate([
            'apartment_type_id'=>$typeId,
            'arrival'=>$arrival,
            'departure'=>$departure,
            'adults'=>(int)($d['adults']??1),
            'children'=>(int)($d['children']??0),
            'babies'=>(int)($d['babies']??0),
            'pets'=>(int)($d['pets']??0),
            'child_ages'=>$d['child_ages']??[],
            'parking_spaces'=>(int)($d['parking_spaces']??0),
            'extra_beds'=>(int)($d['extra_beds']??0),
            'baby_beds'=>(int)($d['baby_beds']??0),
            'late_checkout'=>normalize_bool($d['late_checkout']??0),
            'breakfast'=>normalize_bool($d['breakfast']??0),
            'half_board'=>normalize_bool($d['half_board']??0),
            'breakfast_days'=>(int)($d['breakfast_days']??0),
            'half_board_days'=>(int)($d['half_board_days']??0),
            'discount_code'=>(string)($d['discount_code']??''),
            'manual_discount'=>(float)($d['discount_amount']??0),
            'manual_discount_reason'=>(float)($d['discount_amount']??0)>0?'Interne Buchungsbearbeitung':'',
            'capacity_override'=>normalize_bool($d['capacity_override']??0),
            'capacity_override_reason'=>(string)($d['capacity_override_reason']??''),
            'min_stay_override'=>normalize_bool($d['min_stay_override']??0),
            'deposit_percent'=>0,
        ]);
        $details=[
            'nights'=>(int)$quote['nights'],
            'accommodation'=>0.0,'cleaning'=>0.0,'parking'=>0.0,'pets'=>0.0,
            'extra_beds'=>0.0,'baby_beds'=>0.0,'late_checkout'=>0.0,
            'breakfast'=>0.0,'half_board'=>0.0,
            'length_discount'=>0.0,'code_discount'=>0.0,
            'manual_discount'=>0.0,'tourist_tax'=>(float)($quote['tourist_tax']??0),
            'total'=>(float)$quote['total_amount'],
            'items'=>$quote['items']??[],
            'capacity'=>$quote['capacity']??[],
            'minimum_stay'=>(int)($quote['minimum_stay']??1),
            'scope'=>'apartment_type',
        ];
        foreach((array)($quote['items']??[]) as $item){
            $type=(string)($item['item_type']??'');$amount=(float)($item['line_total']??0);
            if($type==='accommodation')$details['accommodation']+=$amount;
            elseif($type==='cleaning')$details['cleaning']+=$amount;
            elseif($type==='parking')$details['parking']+=$amount;
            elseif($type==='pet')$details['pets']+=$amount;
            elseif($type==='extra_bed')$details['extra_beds']+=$amount;
            elseif($type==='baby_bed')$details['baby_beds']+=$amount;
            elseif($type==='late_checkout')$details['late_checkout']+=$amount;
            elseif($type==='breakfast')$details['breakfast']+=$amount;
            elseif($type==='half_board')$details['half_board']+=$amount;
            elseif($type==='discount'){
                $source=(string)($item['source_type']??'');$discount=abs($amount);
                if($source==='length_discount')$details['length_discount']+=$discount;
                elseif($source==='discount_code')$details['code_discount']+=$discount;
                else $details['manual_discount']+=$discount;
            }
        }
        json_response(['ok'=>true,'price'=>$quote['total_amount'],'details'=>$details,'quote'=>$quote]);
    }

    $details=calculate_price_details($apartmentId,$arrival,$departure,[
        'parking_spaces'=>(int)($d['parking_spaces']??0),'pets'=>(int)($d['pets']??0),
        'extra_beds'=>(int)($d['extra_beds']??0),'baby_beds'=>(int)($d['baby_beds']??0),
        'late_checkout'=>normalize_bool($d['late_checkout']??0),'adults'=>(int)($d['adults']??0),
        'children'=>(int)($d['children']??0),'breakfast'=>normalize_bool($d['breakfast']??0),
        'half_board'=>normalize_bool($d['half_board']??0),'breakfast_days'=>(int)($d['breakfast_days']??0),
        'half_board_days'=>(int)($d['half_board_days']??0),'discount_code'=>(string)($d['discount_code']??''),
        'manual_discount'=>(float)($d['discount_amount']??0),'tourist_tax'=>(float)($d['tourist_tax']??0),
        'special_price_type'=>(string)($d['special_price_type']??'none'),'special_price_value'=>(float)($d['special_price_value']??0)
    ]);
    json_response(['ok'=>true,'price'=>$details['total'],'details'=>$details]);
}

function calendar_data(): never
{
    $start=(string)($_GET['start']??date('Y-m-01'));$days=max(7,min(180,(int)($_GET['days']??31)));if(!valid_date($start))$start=date('Y-m-01');$end=(new DateTimeImmutable($start))->modify('+'.$days.' days')->format('Y-m-d');
    $apts=db()->query("SELECT a.*,t.name apartment_type_name,t.code apartment_type_code,t.sort_order type_sort_order FROM apartments a LEFT JOIN apartment_types t ON t.id=a.apartment_type_id WHERE a.status='active' ORDER BY COALESCE(t.sort_order,9999),COALESCE(t.name,'Ohne Typ'),a.sort_order,a.name")->fetchAll();$stmt=db()->prepare(booking_select()." WHERE b.arrival<? AND b.departure>? AND b.status NOT IN ('rejected') AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') ORDER BY b.arrival");$stmt->execute([$end,$start]);$bookings=$stmt->fetchAll();
    $stmt=db()->prepare('SELECT bl.*,a.name apartment_name FROM availability_blocks bl JOIN apartments a ON a.id=bl.apartment_id WHERE bl.start_date<? AND bl.end_date>? ORDER BY bl.start_date');$stmt->execute([$end,$start]);$blocks=$stmt->fetchAll();$waiting=db()->query(booking_select()." WHERE b.apartment_id IS NULL AND b.status NOT IN ('cancelled','rejected') AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') ORDER BY b.arrival LIMIT 500")->fetchAll();
    json_response(['ok'=>true,'start'=>$start,'end'=>$end,'days'=>$days,'apartments'=>$apts,'bookings'=>$bookings,'blocks'=>$blocks,'waiting'=>$waiting]);
}

function move_preview(): never
{
    $d=request_data();$proposal=build_move_proposal($d);$conflicts=get_booking_conflicts($proposal['apartment_id'],$proposal['arrival'],$proposal['departure'],$proposal['id']);
    $minimum=$proposal['apartment_id']?PricingService::minimumStay((int)$proposal['apartment_id'],$proposal['arrival'],$proposal['departure']):null;
    $old=$proposal['old'];
    $details=$proposal['apartment_id']?calculate_price_details((int)$proposal['apartment_id'],$proposal['arrival'],$proposal['departure'],booking_price_options_from_row($old)):null;
    json_response(['ok'=>true,'proposal'=>$proposal,'conflicts'=>$conflicts['bookings'],'blocks'=>$conflicts['blocks'],'calculated_price'=>$details['total']??0,'minimum_stay'=>$minimum]);
}

function move_booking(): never
{
    $d=request_data();
    $proposal=build_move_proposal($d);
    $old=$proposal['old'];
    $conflicts=get_booking_conflicts($proposal['apartment_id'],$proposal['arrival'],$proposal['departure'],$proposal['id']);
    $action=trim((string)($d['conflict_action']??'reject'));
    if(!in_array($action,['reject','unassign_conflicts'],true))throw new RuntimeException('Unbekannte KonfliktauflÃ¶sung.');
    if($conflicts['blocks'])throw new ConflictException('Der Zielzeitraum enthÃ¤lt eine Sperre oder Wartung.');
    if($conflicts['bookings']&&$action!=='unassign_conflicts')throw new ConflictException('Der Zielzeitraum ist belegt. Bitte KonfliktauflÃ¶sung auswÃ¤hlen.');

    $priceMode=trim((string)($d['price_mode']??'keep'));
    if(!in_array($priceMode,['keep','recalculate'],true))throw new RuntimeException('Unbekannte Preisbehandlung.');
    $minimum=$proposal['apartment_id']?PricingService::minimumStay((int)$proposal['apartment_id'],$proposal['arrival'],$proposal['departure']):null;
    if($minimum&&!$minimum['valid']&&!(int)($old['min_stay_override']??0))throw new ConflictException('Der neue Zeitraum unterschreitet den Mindestaufenthalt von '.$minimum['required'].' NÃ¤chten ('.$minimum['source'].').');
    $newPrice=$priceMode==='recalculate'&&$proposal['apartment_id']?(float)calculate_price_details((int)$proposal['apartment_id'],$proposal['arrival'],$proposal['departure'],booking_price_options_from_row($old))['total']:(float)$old['total_price'];
    $markUpgrade=normalize_bool($d['mark_upgrade']??0)&&$proposal['apartment_id']&&(int)$proposal['apartment_id']!==(int)$old['apartment_id'];
    $upgradeFrom=$markUpgrade?((int)$old['apartment_id']?:((int)$old['upgrade_from_apartment_id']?:null)):$old['upgrade_from_apartment_id'];
    $upgradeFlag=$markUpgrade?1:(int)$old['is_upgrade'];
    $upgradeNote=trim((string)($d['upgrade_note']??''))?:$old['upgrade_note'];

    $adjustRelated=normalize_bool($d['adjust_related']??1);
    $dateChanged=$proposal['arrival']!==$old['arrival']||$proposal['departure']!==$old['departure'];
    $breakfastStart=$old['breakfast_start_date'];
    $breakfastEnd=$old['breakfast_end_date'];
    $halfStart=$old['half_board_start_date'];
    $halfEnd=$old['half_board_end_date'];
    if($adjustRelated&&$dateChanged){
        $delta=(int)(new DateTimeImmutable($old['arrival']))->diff(new DateTimeImmutable($proposal['arrival']))->format('%r%a');
        [$breakfastStart,$breakfastEnd]=shift_service_range($breakfastStart,$breakfastEnd,$delta,$proposal['arrival'],$proposal['departure']);
        [$halfStart,$halfEnd]=shift_service_range($halfStart,$halfEnd,$delta,$proposal['arrival'],$proposal['departure']);
    }

    db()->beginTransaction();
    $displaced=0;
    try{
        if($proposal['apartment_id']){
            // Wohnungszeile selbst sperren: existiert immer, serialisiert daher auch dann,
            // wenn im Zielzeitraum noch keine Buchung liegt (leerer Zeitraum ergibt bei
            // MariaDB keine Gap-Lock auf die bookings-Tabelle).
            db()->prepare('SELECT id FROM apartments WHERE id=? FOR UPDATE')->execute([$proposal['apartment_id']]);
        }
        // Konflikte direkt vor dem Schreiben in derselben Transaktion erneut prÃ¼fen.
        $conflicts=get_booking_conflicts($proposal['apartment_id'],$proposal['arrival'],$proposal['departure'],$proposal['id']);
        if($conflicts['blocks'])throw new ConflictException('Der Zielzeitraum wurde inzwischen gesperrt.');
        if($conflicts['bookings']&&$action!=='unassign_conflicts')throw new ConflictException('Das Ziel wurde inzwischen belegt. Bitte Kalender neu laden.');
        if($conflicts['bookings']&&$action==='unassign_conflicts'){
            foreach($conflicts['bookings'] as $conflict){
                $conflictId=(int)$conflict['id'];
                $conflictOld=fetch_booking_row($conflictId);
                db()->prepare('UPDATE bookings SET apartment_id=NULL WHERE id=?')->execute([$conflictId]);
                db()->prepare("DELETE FROM housekeeping_tasks WHERE booking_id=? AND task_type='turnover' AND status IN ('open','planned','assigned')")->execute([$conflictId]);
                $conflictNew=fetch_booking_row($conflictId);
                log_booking_change($conflictId,'displaced_to_waiting',$conflictOld,$conflictNew,'Durch Kalender-Verschiebung verdrÃ¤ngt');
                AuditLogger::record('booking',$conflictId,'displaced_to_waiting',$conflictOld,$conflictNew,'Durch Kalender-Verschiebung verdrÃ¤ngt');
                $displaced++;
            }
        }
        $targetTypeId=null;
        if($proposal['apartment_id']){$typeStmt=db()->prepare('SELECT apartment_type_id FROM apartments WHERE id=?');$typeStmt->execute([$proposal['apartment_id']]);$targetTypeId=(int)($typeStmt->fetchColumn()?:0)?:null;}
        $targetTypeId=$targetTypeId?:((int)($old['apartment_type_id']??0)?:null);
        $statement=db()->prepare('UPDATE bookings SET apartment_id=?,apartment_type_id=?,arrival=?,departure=?,total_price=?,breakfast_start_date=?,breakfast_end_date=?,half_board_start_date=?,half_board_end_date=?,is_upgrade=?,upgrade_from_apartment_id=?,upgrade_note=? WHERE id=?');
        $statement->execute([$proposal['apartment_id'],$targetTypeId,$proposal['arrival'],$proposal['departure'],$newPrice,$breakfastStart,$breakfastEnd,$halfStart,$halfEnd,$upgradeFlag,$upgradeFrom,$upgradeNote,$proposal['id']]);
        $new=fetch_booking_row($proposal['id']);
        if(!$new)throw new RuntimeException('Die Buchung konnte nach dem Speichern nicht erneut gelesen werden.');
        $storedApartment=(int)($new['apartment_id']??0)?:null;
        if($storedApartment!==$proposal['apartment_id']||(string)$new['arrival']!==$proposal['arrival']||(string)$new['departure']!==$proposal['departure']){
            throw new RuntimeException('Die Verschiebung wurde von der Datenbank nicht vollstÃ¤ndig Ã¼bernommen.');
        }
        if($adjustRelated&&$proposal['apartment_id']){
            db()->prepare("UPDATE housekeeping_tasks SET apartment_id=?,task_date=? WHERE booking_id=? AND task_type='turnover' AND status IN ('open','planned','assigned')")
                ->execute([$proposal['apartment_id'],$proposal['departure'],$proposal['id']]);
        }
        log_booking_change($proposal['id'],'calendar_move',$old,$new,'Kalender-Verschiebung bestÃ¤tigt');
        AuditLogger::record('booking',$proposal['id'],'calendar_move',$old,$new,'Kalender-Verschiebung bestÃ¤tigt');
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    try{HousekeepingWorkflow::upsertDepartureTask($proposal['id']);}catch(Throwable $e){AppLogger::error($e,['booking_id'=>$proposal['id']],'housekeeping-auto-task');}
    json_response([
        'ok'=>true,
        'message'=>'Buchung verschoben.'.($displaced?' '.$displaced.' bisherige Buchung(en) wurden Nicht zugeordnet.':''),
        'arrival'=>$proposal['arrival'],
        'departure'=>$proposal['departure'],
        'displaced'=>$displaced,
        'price'=>$newPrice,
        'booking'=>$new,
    ]);
}

function resize_booking(): never
{
    $d=request_data();
    $d['apply_apartment']=0;
    $d['apply_dates']=1;
    $d['target_arrival']=$d['arrival']??'';
    $d['target_departure']=$d['departure']??'';
    $proposal=build_move_proposal($d);
    $old=$proposal['old'];
    $conflicts=get_booking_conflicts($proposal['apartment_id'],$proposal['arrival'],$proposal['departure'],$proposal['id']);
    if($conflicts['blocks']||$conflicts['bookings'])throw new ConflictException('Der neue Zeitraum Ã¼berschneidet sich mit einer Belegung oder Sperre.');
    $priceMode=trim((string)($d['price_mode']??'keep'));
    $minimum=$proposal['apartment_id']?PricingService::minimumStay((int)$proposal['apartment_id'],$proposal['arrival'],$proposal['departure']):null;
    if($minimum&&!$minimum['valid']&&!(int)($old['min_stay_override']??0))throw new ConflictException('Der neue Zeitraum unterschreitet den Mindestaufenthalt von '.$minimum['required'].' NÃ¤chten ('.$minimum['source'].').');
    $newPrice=$priceMode==='recalculate'&&$proposal['apartment_id']?(float)calculate_price_details((int)$proposal['apartment_id'],$proposal['arrival'],$proposal['departure'],booking_price_options_from_row($old))['total']:(float)$old['total_price'];

    db()->beginTransaction();
    try{
        if($proposal['apartment_id']){
            // Wohnungszeile selbst sperren: existiert immer, serialisiert daher auch dann,
            // wenn im Zielzeitraum noch keine Buchung liegt (leerer Zeitraum ergibt bei
            // MariaDB keine Gap-Lock auf die bookings-Tabelle).
            db()->prepare('SELECT id FROM apartments WHERE id=? FOR UPDATE')->execute([$proposal['apartment_id']]);
            // Zusaetzlich Ã¼berschneidende Buchungen des Apartments sperren.
            $lock=db()->prepare("SELECT id FROM bookings WHERE apartment_id=? AND status NOT IN ('cancelled','rejected') AND (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND arrival<? AND departure>? AND id<>? FOR UPDATE");
            $lock->execute([$proposal['apartment_id'],$proposal['departure'],$proposal['arrival'],$proposal['id']]);$lock->fetchAll();
        }
        // Konflikte direkt vor dem Schreiben in derselben Transaktion erneut prÃ¼fen.
        $conflicts=get_booking_conflicts($proposal['apartment_id'],$proposal['arrival'],$proposal['departure'],$proposal['id']);
        if($conflicts['blocks']||$conflicts['bookings'])throw new ConflictException('Der Zielzeitraum wurde inzwischen belegt oder gesperrt. Bitte Kalender neu laden.');
        db()->prepare('UPDATE bookings SET arrival=?,departure=?,total_price=? WHERE id=?')->execute([$proposal['arrival'],$proposal['departure'],$newPrice,$proposal['id']]);
        $new=fetch_booking_row($proposal['id']);
        log_booking_change($proposal['id'],'calendar_resize',$old,$new,'Aufenthalt im Kalender angepasst');
        AuditLogger::record('booking',$proposal['id'],'calendar_resize',$old,$new,'Aufenthalt im Kalender angepasst');
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    try{HousekeepingWorkflow::upsertDepartureTask($proposal['id']);}catch(Throwable $e){AppLogger::error($e,['booking_id'=>$proposal['id']],'housekeeping-auto-task');}
    json_response(['ok'=>true,'message'=>'Aufenthalt angepasst.','price'=>$newPrice]);
}


function housekeeping_cancel_deleted_booking_tasks_v23689(): int
{
    if (!function_exists('dc_table_exists_v23663') || !dc_table_exists_v23663('housekeeping_tasks') || !dc_table_exists_v23663('bookings')) return 0;
    if (!function_exists('dc_column_exists_v23663') || !dc_column_exists_v23663('bookings','deleted_at')) return 0;
    $sql = "UPDATE housekeeping_tasks h
        JOIN bookings b ON b.id=h.booking_id
        SET h.status='cancelled',
            h.notes=CONCAT(COALESCE(h.notes,''),CASE WHEN COALESCE(h.notes,'')='' THEN '' ELSE '\n' END,'Automatisch ausgeblendet/storniert: zugehörige Buchung ist im Löschcenter gelöscht, storniert oder abgelehnt.'),
            h.updated_at=NOW()
        WHERE h.booking_id IS NOT NULL
          AND (b.deleted_at IS NOT NULL OR b.status IN ('cancelled','rejected'))
          AND h.status IN ('open','planned','assigned','accepted')";
    $stmt = db()->prepare($sql);
    $stmt->execute();
    return $stmt->rowCount();
}

function housekeeping_active_booking_filter_v23689(string $bookingAlias = 'b', string $taskAlias = 'h'): string
{
    return " AND ({$taskAlias}.booking_id IS NULL OR ({$bookingAlias}.id IS NOT NULL AND ({$bookingAlias}.deleted_at IS NULL OR {$bookingAlias}.deleted_at='0000-00-00 00:00:00') AND LOWER(COALESCE({$bookingAlias}.status,'')) NOT IN ('cancelled','canceled','storniert','deleted','gelöscht','rejected','abgelehnt','declined','void')) OR {$taskAlias}.status='cancelled')";
}

function housekeeping_data(): never
{
    $from=(string)($_GET['from']??date('Y-m-d'));$to=(string)($_GET['to']??date('Y-m-d',strtotime('+30 days')));
    if(!valid_date($from)||!valid_date($to)||$from>$to)throw new RuntimeException('Zeitraum ungÃ¼ltig.');
    housekeeping_cancel_deleted_booking_tasks_v23689();
    $user=Auth::user();$role=(string)($user['role']??'readonly');
    $sql="SELECT h.*,a.name apartment_name,a.code apartment_code,a.house_id,a.apartment_type_id,a.key_number,a.parking_number,a.cleaning_instructions AS cleaning_notes,
        hs.name house_name,at.name apartment_type_name,
        b.reference,b.guest_request,b.adults,b.children,b.babies,b.pets,
        TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,
        hm.name member_name,hm.email member_email,hm.whatsapp_number member_whatsapp,
        ht.name team_name,ht.email team_email,ht.whatsapp_number team_whatsapp
        FROM housekeeping_tasks h JOIN apartments a ON a.id=h.apartment_id
        LEFT JOIN houses hs ON hs.id=a.house_id
        LEFT JOIN apartment_types at ON at.id=a.apartment_type_id
        LEFT JOIN bookings b ON b.id=h.booking_id LEFT JOIN guests g ON g.id=b.guest_id
        LEFT JOIN housekeeping_members hm ON hm.id=h.member_id
        LEFT JOIN housekeeping_teams ht ON ht.id=COALESCE(h.team_id,hm.team_id)
        WHERE h.task_date BETWEEN ? AND ?" . housekeeping_active_booking_filter_v23689('b','h');$params=[$from,$to];
    $identity=null;
    if($role==='housekeeping'){
        $idStmt=db()->prepare('SELECT id,team_id,name FROM housekeeping_members WHERE user_id=? AND active=1 LIMIT 1');$idStmt->execute([(int)$user['id']]);$identity=$idStmt->fetch()?:null;
        if(!$identity){$sql.=' AND 1=0';}
        else{$sql.=" AND h.status<>'cancelled' AND (h.member_id=? OR (h.member_id IS NULL AND h.team_id=?))";$params[]=(int)$identity['id'];$params[]=(int)($identity['team_id']??0);}
    }else{
        $requestedStatus=trim((string)($_GET['status']??''));
        $statusGroup=trim((string)($_GET['status_group']??''));
        $statusGroups=[
            'open'=>['open'],
            'assigned'=>['assigned','accepted','in_progress'],
            'control'=>['cleaning_done','inspection_required','rework_required'],
            'ready'=>['inspection_passed'],
            'release'=>['ready_reported'],
            'done'=>['released'],
        ];
        if($statusGroup!=='' && isset($statusGroups[$statusGroup])){
            $groupValues=$statusGroups[$statusGroup];
            $sql.=' AND h.status IN ('.implode(',',array_fill(0,count($groupValues),'?')).')';
            foreach($groupValues as $groupValue)$params[]=$groupValue;
        }elseif($requestedStatus==='')$sql.=" AND h.status<>'cancelled'";
        foreach(['apartment_id','house_id','apartment_type_id','status','task_type','assigned_to','priority','team_id','member_id'] as $key){
            $value=trim((string)($_GET[$key]??''));if($value==='')continue;
            if($key==='house_id' || $key==='apartment_type_id'){$sql.=' AND a.'.$key.'=?';$params[]=(int)$value;}
            elseif(in_array($key,['apartment_id','team_id','member_id'],true)){$sql.=' AND h.'.$key.'=?';$params[]=(int)$value;}
            else{$sql.=' AND h.'.$key.'=?';$params[]=$value;}
        }
    }
    $sql.=" ORDER BY h.task_date,FIELD(h.priority,'urgent','high','normal','low'),a.sort_order,a.name";
    $stmt=db()->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
    foreach($rows as &$row){
        $row['checklist']=json_decode((string)($row['checklist_json']??''),true)?:[];
        $row['checklist_done']=json_decode((string)($row['checklist_done_json']??''),true)?:[];
        if($role==='housekeeping'){
            $row['guest_name']=PrivacyService::housekeepingName($row['guest_name']??'');
            $row['reference']=PrivacyService::housekeepingReference($row['reference']??'');
            $row['guest_request']=PrivacyService::housekeepingRequest($row['guest_request']??'');
            unset($row['member_email'],$row['member_whatsapp'],$row['team_email'],$row['team_whatsapp']);
        }
    }
    unset($row);
    $staff=[];$teams=[];$members=[];$apartments=[];
    if($role!=='housekeeping'){
        $staff=db()->query("SELECT DISTINCT assigned_to FROM housekeeping_tasks WHERE assigned_to IS NOT NULL AND assigned_to<>'' ORDER BY assigned_to")->fetchAll(PDO::FETCH_COLUMN);
        $teams=db()->query("SELECT id,name,code,color,email,whatsapp_number,active FROM housekeeping_teams WHERE active=1 ORDER BY name")->fetchAll();
        $members=db()->query("SELECT id,team_id,name,email,whatsapp_number,active FROM housekeeping_members WHERE active=1 ORDER BY name")->fetchAll();
        $apartments=db()->query("SELECT id,name,code,house_id,apartment_type_id FROM apartments WHERE status='active' ORDER BY sort_order,name")->fetchAll();
        $houses=db()->query("SELECT id,name FROM houses WHERE active=1 ORDER BY sort_order,name")->fetchAll();
        $apartmentTypes=db()->query("SELECT id,name FROM apartment_types WHERE active=1 ORDER BY sort_order,name")->fetchAll();
    }
    json_response(['ok'=>true,'from'=>$from,'to'=>$to,'tasks'=>$rows,'staff'=>$staff,'teams'=>$teams,'members'=>$members,'apartments'=>$apartments,
        'houses'=>$houses??[],'apartment_types'=>$apartmentTypes??[],
        'privacy'=>['name_mode'=>(string)setting('housekeeping_guest_name_mode','initials'),'show_reference'=>(bool)setting('housekeeping_show_booking_reference',false),'show_guest_request'=>(bool)setting('housekeeping_show_guest_request',false)],
        'identity'=>$identity]);
}

function send_housekeeping_list_v23624(): never
{
    $d=request_data();
    $to=trim((string)($d['email']??''));
    if(!filter_var($to,FILTER_VALIDATE_EMAIL))throw new ValidationException('Bitte eine gültige E-Mail-Adresse für die Putzliste eintragen.');
    $from=(string)($d['from']??date('Y-m-d'));$toDate=(string)($d['to']??$from);
    if(!valid_date($from)||!valid_date($toDate)||$from>$toDate)throw new ValidationException('Zeitraum ungültig.');
    $params=[$from,$toDate];
    $sql="SELECT h.*,a.code apartment_code,a.name apartment_name,hs.name house_name,at.name apartment_type_name,b.reference,TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,hm.name member_name,ht.name team_name
        FROM housekeeping_tasks h JOIN apartments a ON a.id=h.apartment_id
        LEFT JOIN houses hs ON hs.id=a.house_id LEFT JOIN apartment_types at ON at.id=a.apartment_type_id
        LEFT JOIN bookings b ON b.id=h.booking_id LEFT JOIN guests g ON g.id=b.guest_id
        LEFT JOIN housekeeping_members hm ON hm.id=h.member_id LEFT JOIN housekeeping_teams ht ON ht.id=COALESCE(h.team_id,hm.team_id)
        WHERE h.task_date BETWEEN ? AND ? AND h.status<>'cancelled'" . housekeeping_active_booking_filter_v23689('b','h');
    foreach(['apartment_id','house_id','apartment_type_id','status','task_type','assigned_to','priority','team_id','member_id'] as $key){
        $value=trim((string)($d[$key]??''));if($value==='')continue;
        if($key==='house_id'||$key==='apartment_type_id'){$sql.=' AND a.'.$key.'=?';$params[]=(int)$value;}
        elseif(in_array($key,['apartment_id','team_id','member_id'],true)){$sql.=' AND h.'.$key.'=?';$params[]=(int)$value;}
        else{$sql.=' AND h.'.$key.'=?';$params[]=$value;}
    }
    $sql.=" ORDER BY h.task_date,FIELD(h.priority,'urgent','high','normal','low'),a.sort_order,a.name";
    $stmt=db()->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
    $subject='Putzliste '.date('d.m.Y',strtotime($from)).' bis '.date('d.m.Y',strtotime($toDate));
    $text=$subject."\n\n";
    $html='<div style="font-family:Arial,sans-serif;line-height:1.45"><h2>'.e($subject).'</h2><table style="border-collapse:collapse;width:100%;font-size:13px"><thead><tr><th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Datum</th><th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Wohnung</th><th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Aufgabe</th><th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Zuständig</th><th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Status</th></tr></thead><tbody>';
    foreach($rows as $r){
        $assigned=(string)($r['member_name']?:$r['team_name']?:$r['assigned_to']?:'Noch offen');
        $line=date('d.m.Y',strtotime((string)$r['task_date'])).' · '.$r['apartment_code'].' '.$r['apartment_name'].' · '.HousekeepingWorkflow::taskTypeLabel((string)$r['task_type'],'de').' · '.$assigned.' · '.HousekeepingWorkflow::statusLabel((string)$r['status'],'de');
        $text.=$line."\n";
        $html.='<tr><td style="border:1px solid #cbd5e1;padding:6px">'.e(date('d.m.Y',strtotime((string)$r['task_date']))).'</td><td style="border:1px solid #cbd5e1;padding:6px"><b>'.e($r['apartment_code'].' '.$r['apartment_name']).'</b><br><span style="color:#64748b">'.e(($r['house_name']??'').' '.($r['apartment_type_name']??'')).'</span></td><td style="border:1px solid #cbd5e1;padding:6px">'.e(HousekeepingWorkflow::taskTypeLabel((string)$r['task_type'],'de')).'<br>'.nl2br(e((string)($r['notes']??''))).'</td><td style="border:1px solid #cbd5e1;padding:6px">'.e($assigned).'</td><td style="border:1px solid #cbd5e1;padding:6px">'.e(HousekeepingWorkflow::statusLabel((string)$r['status'],'de')).'</td></tr>';
    }
    if(!$rows){$html.='<tr><td colspan="5" style="border:1px solid #cbd5e1;padding:10px">Keine Aufgaben im gewählten Zeitraum.</td></tr>'; $text.='Keine Aufgaben im gewählten Zeitraum.';}
    $html.='</tbody></table><p style="color:#64748b">Aus StayPilot gesendet. Der bestehende Putzplan bleibt der führende Ablauf; diese E-Mail ist nur eine Arbeitskopie.</p></div>';
    try{$result=SmtpMailer::send($to,$subject,$text,$html);CommunicationLogger::record('email','housekeeping_list',null,'Putzliste',$to,$subject,$text,'sent',$result['message_id']??'');json_response(['ok'=>true,'message'=>'Putzliste wurde per E-Mail gesendet.']);}
    catch(Throwable $e){CommunicationLogger::record('email','housekeeping_list',null,'Putzliste',$to,$subject,$text,'failed',$e->getMessage());throw $e;}
}

function generate_housekeeping(): never
{
    $d=request_data();$from=(string)($d['from']??date('Y-m-d'));$to=(string)($d['to']??date('Y-m-d',strtotime('+30 days')));
    if(!valid_date($from)||!valid_date($to)||$from>$to)throw new RuntimeException('Zeitraum ungÃ¼ltig.');
    $stmt=db()->prepare("SELECT b.id FROM bookings b WHERE b.apartment_id IS NOT NULL AND b.status NOT IN ('cancelled','rejected') AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND b.departure BETWEEN ? AND ? ORDER BY b.departure,b.id");
    $stmt->execute([$from,$to]);$ids=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));$created=0;$updated=0;
    $exists=db()->prepare("SELECT id FROM housekeeping_tasks WHERE booking_id=? AND task_type='turnover' LIMIT 1");
    foreach($ids as $bookingId){$exists->execute([$bookingId]);$had=(bool)$exists->fetchColumn();HousekeepingWorkflow::upsertDepartureTask($bookingId);$had?$updated++:$created++;}
    AuditLogger::record('housekeeping_task',null,'generate',null,['from'=>$from,'to'=>$to,'created'=>$created,'updated'=>$updated],'ReinigungsauftrÃ¤ge aus Abreisen abgeglichen');
    json_response(['ok'=>true,'message'=>$created.' neue Aufgabe(n) erstellt, '.$updated.' vorhandene Aufgabe(n) abgeglichen.']);
}

function save_task(): never
{
    $d=request_data();$id=(int)($d['id']??0);$old=$id?fetch_housekeeping_row_v208('housekeeping_tasks',$id):null;if($id&&!$old)throw new NotFoundException('Aufgabe nicht gefunden.');
    $checklist=$d['checklist']??[];if(is_string($checklist))$checklist=array_values(array_filter(array_map('trim',preg_split('/[\r\n;]+/',$checklist))));if(!is_array($checklist))$checklist=[];
    $teamId=(int)($d['team_id']??0)?:null;$memberId=(int)($d['member_id']??0)?:null;$assigned=trim((string)($d['assigned_to']??''))?:null;
    if($memberId){$stmt=db()->prepare('SELECT id,team_id,name FROM housekeeping_members WHERE id=? AND active=1 LIMIT 1');$stmt->execute([$memberId]);$member=$stmt->fetch();if(!$member)throw new ValidationException('Der gewÃ¤hlte Mitarbeiter ist nicht aktiv.');$assigned=(string)$member['name'];$teamId=$teamId?:((int)($member['team_id']??0)?:null);}
    elseif($teamId){$stmt=db()->prepare('SELECT id,name FROM housekeeping_teams WHERE id=? AND active=1 LIMIT 1');$stmt->execute([$teamId]);$team=$stmt->fetch();if(!$team)throw new ValidationException('Das gewÃ¤hlte Team ist nicht aktiv.');$assigned=(string)$team['name'];}
    $values=[(int)($d['apartment_id']??0),(int)($d['booking_id']??0)?:null,(string)($d['task_date']??''),trim((string)($d['task_type']??'turnover')),trim((string)($d['status']??'open')),
        $assigned,$teamId,$memberId,trim((string)($d['priority']??'normal')),max(0,(int)($d['estimated_minutes']??60)),($d['actual_minutes']??'')===''?null:max(0,(int)$d['actual_minutes']),
        normalize_bool($d['linen_change']??0),normalize_bool($d['towel_change']??0),json_encode(array_values($checklist),JSON_UNESCAPED_UNICODE),trim((string)($d['supplies']??'')),trim((string)($d['supervisor']??''))?:null,trim((string)($d['notes']??'')),trim((string)($d['completion_notes']??''))];
    if(!$values[0]||!valid_date($values[2]))throw new RuntimeException('Wohnung und Datum sind erforderlich.');
    if($id){$stmt=db()->prepare('UPDATE housekeeping_tasks SET apartment_id=?,booking_id=?,task_date=?,task_type=?,status=?,assigned_to=?,team_id=?,member_id=?,priority=?,estimated_minutes=?,actual_minutes=?,linen_change=?,towel_change=?,checklist_json=?,supplies=?,supervisor=?,notes=?,completion_notes=? WHERE id=?');$stmt->execute([...$values,$id]);}
    else{$stmt=db()->prepare('INSERT INTO housekeeping_tasks(apartment_id,booking_id,task_date,task_type,status,assigned_to,team_id,member_id,priority,estimated_minutes,actual_minutes,linen_change,towel_change,checklist_json,supplies,supervisor,notes,completion_notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute($values);$id=(int)db()->lastInsertId();}
    $new=fetch_housekeeping_row_v208('housekeeping_tasks',$id);AuditLogger::record('housekeeping_task',$id,$old?'update':'create',$old,$new,'Reinigungsaufgabe gespeichert');
    json_response(['ok'=>true,'message'=>'Aufgabe gespeichert.','id'=>$id]);
}

function task_status(): never
{
    $d=request_data();$id=(int)($d['id']??0);$old=fetch_housekeeping_row_v208('housekeeping_tasks',$id);if(!$old)throw new NotFoundException('Aufgabe nicht gefunden.');ensure_housekeeping_task_access_v208($old);
    $status=trim((string)($d['status']??'open'));if(!in_array($status,['open','planned','in_progress','done'],true))throw new ValidationException('UngÃ¼ltiger Status.');
    $completed=$status==='done'?'NOW()':'NULL';db()->prepare("UPDATE housekeeping_tasks SET status=?,completed_at={$completed} WHERE id=?")->execute([$status,$id]);$new=fetch_housekeeping_row_v208('housekeeping_tasks',$id);AuditLogger::record('housekeeping_task',$id,'status',$old,$new,'Aufgabenstatus aktualisiert');json_response(['ok'=>true,'message'=>'Status aktualisiert.']);
}
function delete_task(): never
{
    $d=request_data();
    $id=(int)($d['id']??0);
    $old=fetch_housekeeping_row_v208('housekeeping_tasks',$id);
    if(!$old)throw new NotFoundException('Aufgabe nicht gefunden.');
    if(!in_array((string)$old['status'],['open','assigned','cancelled'],true)){
        throw new ConflictException('Ein bereits angenommener oder bearbeiteter Auftrag kann nicht gelÃ¶scht werden. Bitte stornieren oder im Verlauf dokumentieren.');
    }
    db()->prepare('DELETE FROM housekeeping_tasks WHERE id=?')->execute([$id]);
    AuditLogger::record('housekeeping_task',$id,'delete',$old,null,'Reinigungsaufgabe gelÃ¶scht');
    json_response(['ok'=>true,'message'=>'Aufgabe gelÃ¶scht.']);
}

function booking_price_options_from_row(array $row): array
{
    $nightCount=max(0,nights((string)$row['arrival'],(string)$row['departure']));
    return [
        'parking_spaces'=>(int)($row['parking_spaces']??0),'pets'=>(int)($row['pets']??0),
        'extra_beds'=>(int)($row['extra_beds']??0),'baby_beds'=>(int)($row['baby_beds']??0),
        'late_checkout'=>(int)($row['late_checkout']??0),'adults'=>(int)($row['adults']??0),'children'=>(int)($row['children']??0),
        'breakfast'=>(int)($row['breakfast']??0),'half_board'=>(int)($row['half_board']??0),
        'breakfast_days'=>(int)($row['breakfast']??0)?$nightCount:0,'half_board_days'=>(int)($row['half_board']??0)?$nightCount:0,
        'discount_code'=>(string)($row['discount_code']??''),'manual_discount'=>(float)($row['discount_amount']??0),
        'tourist_tax'=>(float)($row['tourist_tax']??0),'special_price_type'=>(string)($row['special_price_type']??'none'),
        'special_price_value'=>(float)($row['special_price_value']??0),'allow_invalid_discount_code'=>1,
    ];
}

function meals_data(): never
{
    $requestedFrom=(string)($_GET['from']??$_GET['date']??date('Y-m-d'));
    $to=(string)($_GET['to']??date('Y-m-d',strtotime($requestedFrom.' +7 days')));
    $showPast=normalize_bool($_GET['show_past']??0);
    $includeUnassigned=normalize_bool($_GET['include_unassigned']??0);
    if(!valid_date($requestedFrom))$requestedFrom=date('Y-m-d');
    if(!valid_date($to)||$to<$requestedFrom)$to=$requestedFrom;
    $from=$requestedFrom;
    if(!$showPast && $from<date('Y-m-d'))$from=date('Y-m-d');
    if($from>$to)$from=$to;
    $maxTo=(new DateTimeImmutable($from))->modify('+62 days')->format('Y-m-d');
    if($to>$maxTo)$to=$maxTo;
    $queryEnd=(new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');

    $stmt=db()->prepare(booking_select()." WHERE b.status NOT IN ('cancelled','rejected') AND b.arrival<? AND b.departure>? ORDER BY a.name,guest_name");
    $stmt->execute([$queryEnd,$from]);
    $raw=$stmt->fetchAll();
    $bookings=[];$warnings=[];$hiddenUnassigned=0;$hiddenNoMeals=0;
    foreach($raw as $r){
        $hasAny=false;
        for($day=new DateTimeImmutable($from),$end=new DateTimeImmutable($to);$day<=$end;$day=$day->modify('+1 day')){
            $date=$day->format('Y-m-d');
            if(booking_has_breakfast_on($r,$date)||booking_has_half_board_on($r,$date)){$hasAny=true;break;}
        }
        if(!$hasAny){$hiddenNoMeals++;continue;}
        if(!$includeUnassigned && empty($r['apartment_id'])){$hiddenUnassigned++;continue;}
        $r['meal_warnings']=meal_booking_warnings($r);
        $r['effective_breakfast_start']=meal_breakfast_start($r);
        $r['effective_breakfast_end']=meal_breakfast_end($r);
        $r['effective_half_board_start']=meal_half_start($r);
        $r['effective_half_board_end']=meal_half_end($r);
        $bookings[]=$r;
    }
    if($hiddenUnassigned>0)$warnings[]=['title'=>'Nicht zugeordnete Buchungen ausgeblendet','message'=>$hiddenUnassigned.' Buchung(en) haben Frühstück/HP, sind aber keiner Wohnung zugeordnet und erscheinen deshalb nicht im Belegungskalender. Oben kannst du sie einblenden.'];
    if($hiddenNoMeals>0)$warnings[]=['title'=>'Buchungen ohne Leistung im Zeitraum ausgeblendet','message'=>$hiddenNoMeals.' Buchung(en) überschneiden den Zeitraum, haben aber in den ausgewählten Tagen kein Frühstück/keine Halbpension.'];
    if(!$showPast && $requestedFrom<$from)$warnings[]=['title'=>'Vergangene Tage ausgeblendet','message'=>'Der gewählte Zeitraum beginnt in der Vergangenheit. Angezeigt und gedruckt wird ab heute.'];

    $days=[];$periodSummary=['breakfast_adults'=>0,'breakfast_children'=>0,'half_adults'=>0,'half_children'=>0];
    for($day=new DateTimeImmutable($from),$end=new DateTimeImmutable($to);$day<=$end;$day=$day->modify('+1 day')){
        $date=$day->format('Y-m-d');$breakfast=[];$half=[];$summary=['breakfast_adults'=>0,'breakfast_children'=>0,'half_adults'=>0,'half_children'=>0];
        foreach($bookings as $r){
            if(booking_has_breakfast_on($r,$date)){$breakfast[]=$r;$summary['breakfast_adults']+=(int)$r['adults'];$summary['breakfast_children']+=(int)$r['children'];}
            if(booking_has_half_board_on($r,$date)){$half[]=$r;$summary['half_adults']+=(int)$r['adults'];$summary['half_children']+=(int)$r['children'];}
        }
        foreach($summary as $k=>$v)$periodSummary[$k]+=$v;
        $days[]=['date'=>$date,'breakfast'=>$breakfast,'half_board'=>$half,'summary'=>$summary];
    }
    json_response(['ok'=>true,'requested_from'=>$requestedFrom,'from'=>$from,'to'=>$to,'show_past'=>$showPast,'include_unassigned'=>$includeUnassigned,'days'=>$days,'bookings'=>$bookings,'summary'=>$periodSummary,'warnings'=>$warnings]);
}

function meal_breakfast_start(array $booking): string{return (string)($booking['breakfast_start_date']?:date('Y-m-d',strtotime($booking['arrival'].' +1 day')));}
function meal_breakfast_end(array $booking): string{return (string)($booking['breakfast_end_date']?:$booking['departure']);}
function meal_half_start(array $booking): string{return (string)($booking['half_board_start_date']?:$booking['arrival']);}
function meal_half_end(array $booking): string{return (string)($booking['half_board_end_date']?:date('Y-m-d',strtotime($booking['departure'].' -1 day')));}
function meal_booking_warnings(array $booking): array
{
    $warnings=[];
    if(empty($booking['apartment_id']))$warnings[]='nicht im Kalender zugeordnet';
    if((int)($booking['breakfast']??0)){
        $s=meal_breakfast_start($booking);$e=meal_breakfast_end($booking);
        if($s<$booking['arrival']||$e>$booking['departure']||$s>$e)$warnings[]='Frühstückszeitraum prüfen';
    }
    if((int)($booking['half_board']??0)){
        $s=meal_half_start($booking);$e=meal_half_end($booking);
        if($s<$booking['arrival']||$e>=$booking['departure']||$s>$e)$warnings[]='HP-Zeitraum prüfen';
    }
    return $warnings;
}
function booking_has_breakfast_on(array $booking,string $date): bool{if(!(int)$booking['breakfast'])return false;$start=meal_breakfast_start($booking);$end=meal_breakfast_end($booking);return $date>=$start&&$date<=$end;}
function booking_has_half_board_on(array $booking,string $date): bool{if(!(int)$booking['half_board'])return false;$start=meal_half_start($booking);$end=meal_half_end($booking);return $date>=$start&&$date<=$end;}

function prices_data(): never
{
    json_response(['ok'=>true,
        'apartments'=>db()->query("SELECT id,code,name,base_price,cleaning_fee,breakfast_price,half_board_price,parking_price_per_night,pet_price_per_night,extra_bed_price_per_night,baby_bed_fee,late_checkout_fee FROM apartments ORDER BY sort_order,name")->fetchAll(),
        'seasons'=>db()->query('SELECT * FROM season_rules ORDER BY priority DESC,start_date')->fetchAll(),
        'blocks'=>db()->query('SELECT bl.*,a.name apartment_name FROM availability_blocks bl JOIN apartments a ON a.id=bl.apartment_id ORDER BY start_date DESC LIMIT 500')->fetchAll(),
        'length_discounts'=>db()->query('SELECT * FROM length_discounts ORDER BY min_nights')->fetchAll(),
        'discount_codes'=>db()->query('SELECT d.*,a.name apartment_name FROM discount_codes d LEFT JOIN apartments a ON a.id=d.apartment_id ORDER BY d.active DESC,d.code')->fetchAll()
    ]);
}
function save_prices(): never
{
    $d=request_data();$rows=$d['apartments']??[];
    if(!is_array($rows))throw new RuntimeException('Preisdaten fehlen.');
    $stmt=db()->prepare('UPDATE apartments SET base_price=?,cleaning_fee=?,breakfast_price=?,half_board_price=?,parking_price_per_night=?,pet_price_per_night=?,extra_bed_price_per_night=?,baby_bed_fee=?,late_checkout_fee=? WHERE id=?');
    foreach($rows as $r){$stmt->execute([
        max(0,(float)($r['base_price']??0)),max(0,(float)($r['cleaning_fee']??0)),
        max(0,(float)($r['breakfast_price']??0)),max(0,(float)($r['half_board_price']??0)),
        max(0,(float)($r['parking_price_per_night']??0)),max(0,(float)($r['pet_price_per_night']??0)),
        max(0,(float)($r['extra_bed_price_per_night']??0)),max(0,(float)($r['baby_bed_fee']??0)),
        max(0,(float)($r['late_checkout_fee']??0)),(int)($r['id']??0)
    ]);}
    json_response(['ok'=>true,'message'=>'Alle Preise gespeichert.']);
}

function save_length_discount(): never
{
    $d=request_data();$id=(int)($d['id']??0);$min=max(1,(int)($d['min_nights']??1));$percent=max(0,min(100,(float)($d['percent']??0)));
    $values=[$min,$percent,normalize_bool($d['active']??0),trim((string)($d['label']??''))?:null,(int)($d['sort_order']??$min)];
    if($id)db()->prepare('UPDATE length_discounts SET min_nights=?,percent=?,active=?,label=?,sort_order=? WHERE id=?')->execute([...$values,$id]);
    else db()->prepare('INSERT INTO length_discounts(min_nights,percent,active,label,sort_order) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE percent=VALUES(percent),active=VALUES(active),label=VALUES(label),sort_order=VALUES(sort_order)')->execute($values);
    json_response(['ok'=>true,'message'=>'Staffelrabatt gespeichert.']);
}
function delete_length_discount(): never
{
    $id=(int)(request_data()['id']??0);db()->prepare('DELETE FROM length_discounts WHERE id=?')->execute([$id]);json_response(['ok'=>true,'message'=>'Staffelrabatt gelÃ¶scht.']);
}
function save_discount_code(): never
{
    $d=request_data();$id=(int)($d['id']??0);$code=strtoupper(trim((string)($d['code']??'')));
    if($code===''||!preg_match('/^[A-Z0-9_-]{2,80}$/',$code))throw new RuntimeException('Rabattcode: nur Buchstaben, Zahlen, Bindestrich und Unterstrich verwenden.');
    $type=(string)($d['discount_type']??'percent');if(!in_array($type,['percent','fixed'],true))$type='percent';
    $value=max(0,(float)($d['discount_value']??0));if($type==='percent'&&$value>100)throw new RuntimeException('Prozentrabatt darf hÃ¶chstens 100 % betragen.');
    $start=nullable_date($d['start_date']??null);$end=nullable_date($d['end_date']??null);if($start&&$end&&$start>$end)throw new RuntimeException('GÃ¼ltigkeitszeitraum ist ungÃ¼ltig.');
    $maxUses=trim((string)($d['max_uses']??''))===''?null:max(1,(int)$d['max_uses']);
    $values=[$code,trim((string)($d['description']??''))?:null,$type,$value,$start,$end,max(1,(int)($d['min_nights']??1)),(int)($d['apartment_id']??0)?:null,$maxUses,normalize_bool($d['active']??0)];
    if($id)db()->prepare('UPDATE discount_codes SET code=?,description=?,discount_type=?,discount_value=?,start_date=?,end_date=?,min_nights=?,apartment_id=?,max_uses=?,active=? WHERE id=?')->execute([...$values,$id]);
    else db()->prepare('INSERT INTO discount_codes(code,description,discount_type,discount_value,start_date,end_date,min_nights,apartment_id,max_uses,active) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute($values);
    json_response(['ok'=>true,'message'=>'Rabattcode gespeichert.']);
}
function delete_discount_code(): never
{
    $id=(int)(request_data()['id']??0);$stmt=db()->prepare('SELECT used_count FROM discount_codes WHERE id=?');$stmt->execute([$id]);if((int)$stmt->fetchColumn()>0)throw new RuntimeException('Verwendete Rabattcodes werden zur Nachvollziehbarkeit nicht gelÃ¶scht. Deaktivieren Sie den Code.');
    db()->prepare('DELETE FROM discount_codes WHERE id=?')->execute([$id]);json_response(['ok'=>true,'message'=>'Rabattcode gelÃ¶scht.']);
}

function save_season(): never{$d=request_data();$id=(int)($d['id']??0);$values=[trim((string)($d['name']??'')),(string)($d['start_date']??''),(string)($d['end_date']??''),(float)($d['multiplier']??1),(int)($d['min_stay']??1),(int)($d['priority']??0)];if($values[0]===''||!valid_date($values[1])||!valid_date($values[2])||$values[1]>$values[2])throw new RuntimeException('Saisonangaben ungÃ¼ltig.');if($id){db()->prepare('UPDATE season_rules SET name=?,start_date=?,end_date=?,multiplier=?,min_stay=?,priority=? WHERE id=?')->execute([...$values,$id]);}else{db()->prepare('INSERT INTO season_rules(name,start_date,end_date,multiplier,min_stay,priority) VALUES(?,?,?,?,?,?)')->execute($values);}json_response(['ok'=>true,'message'=>'Saison gespeichert.']);}
function delete_season(): never{$d=request_data();db()->prepare('DELETE FROM season_rules WHERE id=?')->execute([(int)($d['id']??0)]);json_response(['ok'=>true,'message'=>'Saison gelÃ¶scht.']);}
function save_block(): never{$d=request_data();$id=(int)($d['id']??0);$values=[(int)($d['apartment_id']??0),(string)($d['start_date']??''),(string)($d['end_date']??''),trim((string)($d['reason']??'')),trim((string)($d['block_type']??'maintenance'))];if(!$values[0]||!valid_date($values[1])||!valid_date($values[2])||$values[1]>=$values[2])throw new RuntimeException('Sperrzeit ungÃ¼ltig.');if(booking_conflict($values[0],$values[1],$values[2]))throw new RuntimeException('Die Sperre Ã¼berschneidet sich mit einer Buchung oder vorhandenen Sperre.');if($id){db()->prepare('UPDATE availability_blocks SET apartment_id=?,start_date=?,end_date=?,reason=?,block_type=? WHERE id=?')->execute([...$values,$id]);}else{db()->prepare('INSERT INTO availability_blocks(apartment_id,start_date,end_date,reason,block_type) VALUES(?,?,?,?,?)')->execute($values);}json_response(['ok'=>true,'message'=>'Sperrzeit gespeichert.']);}
function delete_block(): never{$d=request_data();db()->prepare('DELETE FROM availability_blocks WHERE id=?')->execute([(int)($d['id']??0)]);json_response(['ok'=>true,'message'=>'Sperre gelÃ¶scht.']);}

function fetch_booking_row(int $id): ?array{$stmt=db()->prepare('SELECT * FROM bookings WHERE id=?');$stmt->execute([$id]);$row=$stmt->fetch();return $row?:null;}
function log_booking_change(int $bookingId,string $action,?array $old,?array $new,string $note=''): void{$current=Auth::user();$stmt=db()->prepare('INSERT INTO booking_change_log(booking_id,action,old_values_json,new_values_json,note,created_by) VALUES(?,?,?,?,?,?)');$stmt->execute([$bookingId,$action,$old?json_encode($old,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,$new?json_encode($new,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,$note,$current['id']??null]);}
function nullable_date(mixed $value): ?string{$value=trim((string)$value);if($value==='')return null;if(!valid_date($value))throw new RuntimeException('Ein eingegebenes Datum ist ungÃ¼ltig.');return $value;}
function nullable_time(mixed $value): ?string{$value=trim((string)$value);if($value==='')return null;if(!preg_match('/^([01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/',$value))throw new RuntimeException('Eine eingegebene Uhrzeit ist ungÃ¼ltig.');return strlen($value)===5?$value.':00':$value;}
function nullable_datetime(mixed $value): ?string{$value=trim((string)$value);if($value==='')return null;$value=str_replace('T',' ',$value);foreach(['!Y-m-d H:i','!Y-m-d H:i:s'] as $format){$dt=DateTimeImmutable::createFromFormat($format,$value);if($dt)return $dt->format('Y-m-d H:i:s');}throw new RuntimeException('Ein Check-in-/Check-out-Zeitpunkt ist ungÃ¼ltig.');}
function validate_service_range(int $active,?string $start,?string $end,string $arrival,string $departure,string $label): void{if(!$active)return;if(($start===null)!==($end===null))throw new RuntimeException($label.': Start und Ende gemeinsam ausfÃ¼llen.');if($start!==null&&($start>$end||$start<$arrival||$end>$departure))throw new RuntimeException($label.': Zeitraum muss innerhalb des Aufenthalts liegen.');}
function shift_service_range(?string $start,?string $end,int $delta,string $arrival,string $departure): array
{
    if($start===null&&$end===null)return [null,null];
    $shift=static function(?string $date) use ($delta): ?string {if($date===null)return null;$prefix=$delta>=0?'+':'';return (new DateTimeImmutable($date))->modify($prefix.$delta.' days')->format('Y-m-d');};
    $start=$shift($start);$end=$shift($end);
    if($start!==null&&$start<$arrival)$start=$arrival;
    if($end!==null&&$end>$departure)$end=$departure;
    if(($start===null)!==($end===null)||($start!==null&&$start>$end))return [null,null];
    return [$start,$end];
}
function build_move_proposal(array $d): array
{
    $id=(int)($d['id']??0);
    $old=fetch_booking_row($id);
    if(!$old)throw new RuntimeException('Buchung nicht gefunden.');
    $applyApartment=array_key_exists('apply_apartment',$d)?normalize_bool($d['apply_apartment']):1;
    $applyDates=array_key_exists('apply_dates',$d)?normalize_bool($d['apply_dates']):1;
    $targetApartment=(int)($d['apartment_id']??$d['target_apartment_id']??0)?:null;
    if($applyApartment&&$targetApartment===null)throw new RuntimeException('Bitte ein Zielapartment auswÃ¤hlen.');
    $apartmentId=$applyApartment?$targetApartment:((int)$old['apartment_id']?:null);
    $arrival=(string)$old['arrival'];
    $departure=(string)$old['departure'];
    if($applyDates){
        $targetArrival=(string)($d['target_arrival']??$d['arrival']??'');
        if(!valid_date($targetArrival))throw new RuntimeException('UngÃ¼ltiges Zieldatum.');
        $targetDeparture=trim((string)($d['target_departure']??$d['departure']??''));
        if($targetDeparture!==''){
            if(!valid_date($targetDeparture)||$targetArrival>=$targetDeparture)throw new RuntimeException('UngÃ¼ltiger Zielzeitraum.');
            $arrival=$targetArrival;
            $departure=$targetDeparture;
        }else{
            $duration=max(1,nights((string)$old['arrival'],(string)$old['departure']));
            $arrival=$targetArrival;
            $departure=(new DateTimeImmutable($arrival))->modify('+'.$duration.' days')->format('Y-m-d');
        }
    }
    $apartmentName='Nicht zugeordnet';
    if($apartmentId){
        $stmt=db()->prepare('SELECT id,name,status,out_of_service FROM apartments WHERE id=?');
        $stmt->execute([$apartmentId]);
        $apartment=$stmt->fetch();
        if(!$apartment)throw new RuntimeException('Das Zielapartment existiert nicht mehr.');
        if($applyApartment&&((string)$apartment['status']!=='active'||(int)($apartment['out_of_service']??0)===1))throw new ConflictException('Das Zielapartment ist nicht aktiv oder auÃŸer Betrieb.');
        $apartmentName=(string)$apartment['name'];
    }
    return ['id'=>$id,'old'=>$old,'apartment_id'=>$apartmentId,'apartment_name'=>$apartmentName,'arrival'=>$arrival,'departure'=>$departure,'nights'=>nights($arrival,$departure)];
}
function get_booking_conflicts(?int $apartmentId,string $arrival,string $departure,int $ignoreId): array{if(!$apartmentId)return ['bookings'=>[],'blocks'=>[]];$stmt=db()->prepare(booking_select()." WHERE b.apartment_id=? AND b.status NOT IN ('cancelled','rejected') AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND b.arrival<? AND b.departure>? AND b.id<>? ORDER BY b.arrival");$stmt->execute([$apartmentId,$departure,$arrival,$ignoreId]);$bookings=$stmt->fetchAll();$stmt=db()->prepare('SELECT * FROM availability_blocks WHERE apartment_id=? AND start_date<? AND end_date>? ORDER BY start_date');$stmt->execute([$apartmentId,$departure,$arrival]);return ['bookings'=>$bookings,'blocks'=>$stmt->fetchAll()];}
function status_text(string $status): string{return ['inquiry'=>'Anfrage','confirmed'=>'BestÃ¤tigt','checked_in'=>'Eingecheckt','checked_out'=>'Abgereist','cancelled'=>'Storniert','rejected'=>'Abgelehnt'][$status]??$status;}
function category_id_by_name(string $name): ?int{if($name==='')return null;$stmt=db()->prepare('SELECT id FROM guest_categories WHERE name=?');$stmt->execute([$name]);$id=$stmt->fetchColumn();if($id)return (int)$id;db()->prepare('INSERT INTO guest_categories(name,color,active,sort_order) VALUES(?,\'#64748b\',1,999)')->execute([$name]);return (int)db()->lastInsertId();}

function csv_profiles(): never
{
    json_response(['ok'=>true,'profiles'=>db()->query('SELECT * FROM csv_profiles ORDER BY entity_type,name')->fetchAll(),'targets'=>csv_target_definitions()]);
}
function csv_target_definitions(): array
{
    return [
        'apartments'=>['code'=>'Wohnungscode *','name'=>'Name *','type'=>'Typ','status'=>'Status','max_guests'=>'Max. GÃ¤ste','bedrooms'=>'Schlafzimmer','bathrooms'=>'BÃ¤der','base_price'=>'Grundpreis','cleaning_fee'=>'Reinigung','breakfast_price'=>'FrÃ¼hstÃ¼ckspreis','half_board_price'=>'HP-Preis','parking_price_per_night'=>'Parkplatz/Nacht','pet_price_per_night'=>'Haustier/Nacht','extra_bed_price_per_night'=>'Zustellbett/Nacht','baby_bed_fee'=>'Babybett einmalig','late_checkout_fee'=>'Late Check-out','full_address'=>'Adresse','color'=>'Farbe','description'=>'Beschreibung','amenities'=>'Ausstattung'],
        'guests'=>['title'=>'Anrede','first_name'=>'Vorname *','last_name'=>'Nachname *','second_last_name'=>'Zweiter Nachname','full_name'=>'VollstÃ¤ndiger Name','gender'=>'Geschlecht','nationality'=>'NationalitÃ¤t','email'=>'E-Mail','phone'=>'Mobiltelefon','fixed_phone'=>'Festnetz','address'=>'Adresse','postal_code'=>'PLZ','city'=>'Ort','province'=>'Provinz','country'=>'Land','language'=>'Sprache','category'=>'Kategorie','vip'=>'VIP','date_of_birth'=>'Geburtsdatum','place_of_birth'=>'Geburtsort','company'=>'Firma','passport_number'=>'Dokumentnummer','document_type'=>'Dokumentart','document_support_number'=>'Supportnummer','document_issue_date'=>'Ausstellungsdatum','document_country'=>'Ausstellungsland','emergency_contact_name'=>'Notfallkontakt','emergency_contact_phone'=>'Notfalltelefon','marketing_opt_in'=>'Marketing-Einwilligung','preferences'=>'Allgemeine GastwÃ¼nsche','notes'=>'Notizen'],
        'bookings'=>['reference'=>'Referenz','guest_name'=>'Gastname *','guest_email'=>'Gast-E-Mail','guest_phone'=>'Gast-Telefon','guest_category'=>'Gastkategorie','apartment_code'=>'Wohnungscode','apartment_name'=>'Wohnungsname','arrival'=>'Anreise *','departure'=>'Abreise *','planned_arrival_time'=>'Anreisezeit','planned_departure_time'=>'Abreisezeit','adults'=>'Erwachsene','children'=>'Kinder','babies'=>'Babys','pets'=>'Haustiere','status'=>'Status','source'=>'Buchungskanal / Quelle','special_requests'=>'Besondere Anforderungen','total_price'=>'Gesamtpreis','paid_amount'=>'Bezahlt','payment_status'=>'Zahlungsstatus','deposit_amount'=>'Anzahlung','tourist_tax'=>'Kurtaxe','discount_amount'=>'Rabatt','breakfast'=>'FrÃ¼hstÃ¼ck','breakfast_start_date'=>'FrÃ¼hstÃ¼ck von','breakfast_end_date'=>'FrÃ¼hstÃ¼ck bis','half_board'=>'Halbpension','half_board_start_date'=>'HP von','half_board_end_date'=>'HP bis','is_upgrade'=>'Upgrade','upgrade_note'=>'Upgrade-Hinweis','vehicle_plate'=>'Kennzeichen','guest_request'=>'Gastwunsch','internal_notes'=>'Interne Notiz','notes'=>'Notizen','external_id'=>'Externe ID']
    ];
}
function csv_preview(): never
{
    if(empty($_FILES['file'])||$_FILES['file']['error']!==UPLOAD_ERR_OK)throw new RuntimeException('CSV-Datei konnte nicht hochgeladen werden.');$file=$_FILES['file'];
    if((int)$file['size']>(int)config()['max_csv_size'])throw new RuntimeException('CSV-Datei ist zu groÃŸ.');
    $entity=(string)($_POST['entity_type']??'bookings');if(!isset(csv_target_definitions()[$entity]))throw new RuntimeException('Unbekannter Importtyp.');
    $raw=file_get_contents($file['tmp_name']);if($raw===false)throw new RuntimeException('Datei konnte nicht gelesen werden.');
    $enc=mb_detect_encoding($raw,['UTF-8','Windows-1252','ISO-8859-1'],true)?:'UTF-8';if($enc!=='UTF-8')$raw=mb_convert_encoding($raw,'UTF-8',$enc);
    $firstLine=strtok($raw,"\r\n");$best=';';$bestCount=0;foreach([';',',',"\t",'|'] as $delimiter){$count=count(str_getcsv((string)$firstLine,$delimiter));if($count>$bestCount){$bestCount=$count;$best=$delimiter;}}
    $token=bin2hex(random_bytes(16));$path=root_path('storage/uploads/import_'.$token.'.csv');if(file_put_contents($path,$raw,LOCK_EX)===false)throw new RuntimeException('TemporÃ¤re Importdatei konnte nicht gespeichert werden.');
    $h=fopen($path,'rb');$headers=fgetcsv($h,0,$best)?:[];$headers=array_map(fn($v)=>trim((string)$v),$headers);$rows=[];for($i=0;$i<5&&($row=fgetcsv($h,0,$best))!==false;$i++)$rows[]=$row;fclose($h);
    json_response(['ok'=>true,'token'=>$token,'filename'=>basename($file['name']),'entity_type'=>$entity,'delimiter'=>$best==='\t'?'TAB':$best,'encoding'=>$enc,'headers'=>$headers,'rows'=>$rows,'targets'=>csv_target_definitions()[$entity]]);
}
function csv_import(): never
{
    global $user;$d=request_data();$token=preg_replace('/[^a-f0-9]/','',(string)($d['token']??''));$entity=(string)($d['entity_type']??'');$path=root_path('storage/uploads/import_'.$token.'.csv');if(strlen($token)!==32||!is_file($path)||!isset(csv_target_definitions()[$entity]))throw new RuntimeException('Importdatei ist abgelaufen oder ungÃ¼ltig.');
    $delimiter=(string)($d['delimiter']??';');if($delimiter==='TAB')$delimiter="\t";$mapping=$d['mapping']??[];if(is_string($mapping))$mapping=json_decode($mapping,true)?:[];$defaults=$d['defaults']??[];if(is_string($defaults))$defaults=json_decode($defaults,true)?:[];
    $dateFormat=(string)($d['date_format']??'Y-m-d');$decimal=(string)($d['decimal_separator']??',');$profileName=trim((string)($d['profile_name']??''));
    $h=fopen($path,'rb');$headers=fgetcsv($h,0,$delimiter)?:[];$index=[];foreach($headers as $i=>$header)$index[trim((string)$header)]=$i;
    $total=$imported=$skipped=0;$errors=[];db()->beginTransaction();
    try{while(($row=fgetcsv($h,0,$delimiter))!==false){$total++;if($total>(int)config()['max_csv_rows'])throw new RuntimeException('Maximale Zeilenzahl Ã¼berschritten.');if(count(array_filter($row,fn($v)=>trim((string)$v)!==''))===0)continue;$record=[];foreach($mapping as $target=>$source){if($source!==''&&isset($index[$source]))$record[$target]=trim((string)($row[$index[$source]]??''));}foreach($defaults as $k=>$v){if(!isset($record[$k])||$record[$k]==='')$record[$k]=$v;}try{import_csv_record($entity,$record,$dateFormat,$decimal);$imported++;}catch(Throwable $e){$skipped++;if(count($errors)<30)$errors[]='Zeile '.($total+1).': '.$e->getMessage();}}
        if($profileName!==''){$stmt=db()->prepare('INSERT INTO csv_profiles(name,entity_type,delimiter_char,date_format,decimal_separator,mapping_json,defaults_json) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE delimiter_char=VALUES(delimiter_char),date_format=VALUES(date_format),decimal_separator=VALUES(decimal_separator),mapping_json=VALUES(mapping_json),defaults_json=VALUES(defaults_json)');$stmt->execute([$profileName,$entity,$delimiter,$dateFormat,$decimal,json_encode($mapping,JSON_UNESCAPED_UNICODE),json_encode($defaults,JSON_UNESCAPED_UNICODE)]);}
        $stmt=db()->prepare('INSERT INTO csv_imports(entity_type,filename,total_rows,imported_rows,skipped_rows,errors_json,created_by) VALUES(?,?,?,?,?,?,?)');$stmt->execute([$entity,basename($path),$total,$imported,$skipped,json_encode($errors,JSON_UNESCAPED_UNICODE),(int)$user['id']]);db()->commit();
    }catch(Throwable $e){db()->rollBack();fclose($h);@unlink($path);throw $e;}fclose($h);@unlink($path);json_response(['ok'=>true,'message'=>$imported.' DatensÃ¤tze importiert, '.$skipped.' Ã¼bersprungen.','imported'=>$imported,'skipped'=>$skipped,'errors'=>$errors]);
}
function parse_csv_date(string $value,string $format): string{$value=trim($value);foreach(array_unique([$format,'Y-m-d','d.m.Y','d/m/Y','m/d/Y']) as $fmt){$d=DateTimeImmutable::createFromFormat('!'.$fmt,$value);if($d&&$d->format($fmt)===$value)return $d->format('Y-m-d');}throw new RuntimeException('Datum nicht lesbar: '.$value);}
function csv_number(mixed $v,string $decimal): float{$s=preg_replace('/[^0-9,\.\-]/','',(string)$v);if($decimal===',')$s=str_replace(['. ','.'],['',''],$s);$s=str_replace(',','.',$s);return (float)$s;}
function import_csv_record(string $entity,array $r,string $dateFormat,string $decimal): void
{
    if($entity==='apartments'){
        if(empty($r['code'])||empty($r['name']))throw new RuntimeException('Code oder Name fehlt.');
        $sql='INSERT INTO apartments(code,name,type,status,max_guests,bedrooms,bathrooms,base_price,cleaning_fee,breakfast_price,half_board_price,parking_price_per_night,pet_price_per_night,extra_bed_price_per_night,baby_bed_fee,late_checkout_fee,full_address,color,description,amenities_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),type=VALUES(type),status=VALUES(status),max_guests=VALUES(max_guests),bedrooms=VALUES(bedrooms),bathrooms=VALUES(bathrooms),base_price=VALUES(base_price),cleaning_fee=VALUES(cleaning_fee),breakfast_price=VALUES(breakfast_price),half_board_price=VALUES(half_board_price),parking_price_per_night=VALUES(parking_price_per_night),pet_price_per_night=VALUES(pet_price_per_night),extra_bed_price_per_night=VALUES(extra_bed_price_per_night),baby_bed_fee=VALUES(baby_bed_fee),late_checkout_fee=VALUES(late_checkout_fee),full_address=VALUES(full_address),color=VALUES(color),description=VALUES(description),amenities_json=VALUES(amenities_json)';
        db()->prepare($sql)->execute([strtoupper($r['code']),$r['name'],$r['type']??'Ferienwohnung',$r['status']??'active',(int)($r['max_guests']??2),(int)($r['bedrooms']??1),(int)($r['bathrooms']??1),csv_number($r['base_price']??0,$decimal),csv_number($r['cleaning_fee']??0,$decimal),csv_number($r['breakfast_price']??0,$decimal),csv_number($r['half_board_price']??0,$decimal),csv_number($r['parking_price_per_night']??0,$decimal),csv_number($r['pet_price_per_night']??0,$decimal),csv_number($r['extra_bed_price_per_night']??0,$decimal),csv_number($r['baby_bed_fee']??0,$decimal),csv_number($r['late_checkout_fee']??0,$decimal),$r['full_address']??null,$r['color']??'#2563eb',$r['description']??'',json_encode(array_filter(array_map('trim',preg_split('/[,;|]+/',$r['amenities']??''))),JSON_UNESCAPED_UNICODE)]);return;
    }
    if($entity==='guests'){
        $first=trim($r['first_name']??'');$last=trim($r['last_name']??'');if(($first===''||$last==='')&&!empty($r['full_name'])){$parts=preg_split('/\s+/',trim($r['full_name']),2);$first=$parts[0]??'';$last=$parts[1]??'-';}if($first===''||$last==='')throw new RuntimeException('Gastname fehlt.');
        $categoryId=category_id_by_name(trim($r['category']??''));$dob=!empty($r['date_of_birth'])?parse_csv_date((string)$r['date_of_birth'],$dateFormat):null;$issue=!empty($r['document_issue_date'])?parse_csv_date((string)$r['document_issue_date'],$dateFormat):null;$email=trim($r['email']??'');
        if($email!==''){$stmt=db()->prepare('SELECT id FROM guests WHERE email=?');$stmt->execute([$email]);$id=$stmt->fetchColumn();}else{$id=false;}
        $values=[$r['title']??null,$first,$last,$r['second_last_name']??null,$r['gender']??null,$r['nationality']??null,$email?:null,$r['phone']??null,$r['address']??null,$r['postal_code']??null,$r['city']??null,$r['country']??null,$r['language']??'Deutsch',$categoryId,normalize_bool($r['vip']??0),$dob,$r['company']??null,$r['passport_number']??null,$r['document_type']??null,$r['document_support_number']??null,$issue,$r['document_country']??null,$r['place_of_birth']??null,$r['province']??null,$r['fixed_phone']??null,$r['emergency_contact_name']??null,$r['emergency_contact_phone']??null,normalize_bool($r['marketing_opt_in']??0),$r['notes']??''];
        if($id){db()->prepare('UPDATE guests SET title=?,first_name=?,last_name=?,second_last_name=?,gender=?,nationality=?,email=?,phone=?,address=?,postal_code=?,city=?,country=?,language=?,category_id=?,vip=?,date_of_birth=?,company=?,passport_number=?,document_type=?,document_support_number=?,document_issue_date=?,document_country=?,place_of_birth=?,province=?,fixed_phone=?,emergency_contact_name=?,emergency_contact_phone=?,marketing_opt_in=?,notes=? WHERE id=?')->execute([...$values,(int)$id]);}
        else{db()->prepare('INSERT INTO guests(title,first_name,last_name,second_last_name,gender,nationality,email,phone,address,postal_code,city,country,language,category_id,vip,date_of_birth,company,passport_number,document_type,document_support_number,document_issue_date,document_country,place_of_birth,province,fixed_phone,emergency_contact_name,emergency_contact_phone,marketing_opt_in,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($values);}return;
    }
    if($entity==='bookings'){$name=trim($r['guest_name']??'');if($name==='')throw new RuntimeException('Gastname fehlt.');$email=trim($r['guest_email']??'');$guestId=null;if($email!==''){$stmt=db()->prepare('SELECT id FROM guests WHERE email=?');$stmt->execute([$email]);$guestId=$stmt->fetchColumn();}if(!$guestId){$parts=preg_split('/\s+/',trim($name),2);db()->prepare('INSERT INTO guests(first_name,last_name,email,phone,language,category_id) VALUES(?,?,?,?,?,?)')->execute([$parts[0]??'Gast',$parts[1]??'-',$email?:null,$r['guest_phone']??null,'Deutsch',category_id_by_name(trim($r['guest_category']??''))]);$guestId=(int)db()->lastInsertId();}$aptId=null;if(!empty($r['apartment_code'])){$stmt=db()->prepare('SELECT id FROM apartments WHERE code=?');$stmt->execute([strtoupper($r['apartment_code'])]);$aptId=$stmt->fetchColumn();}elseif(!empty($r['apartment_name'])){$stmt=db()->prepare('SELECT id FROM apartments WHERE name=?');$stmt->execute([$r['apartment_name']]);$aptId=$stmt->fetchColumn();}$aptTypeId=null;if($aptId){$stmt=db()->prepare('SELECT apartment_type_id FROM apartments WHERE id=?');$stmt->execute([(int)$aptId]);$aptTypeId=(int)($stmt->fetchColumn()?:0)?:null;}else{$typeName=trim((string)($r['apartment_type']??$r['apartment_type_name']??$r['type']??''));if($typeName!==''){$stmt=db()->prepare('SELECT id FROM apartment_types WHERE name=? OR code=? LIMIT 1');$stmt->execute([$typeName,$typeName]);$aptTypeId=(int)($stmt->fetchColumn()?:0)?:null;}}$arrival=parse_csv_date((string)($r['arrival']??''),$dateFormat);$departure=parse_csv_date((string)($r['departure']??''),$dateFormat);if($arrival>=$departure)throw new RuntimeException('Abreise liegt nicht nach Anreise.');if($aptId&&booking_conflict((int)$aptId,$arrival,$departure))throw new RuntimeException('Doppelbelegung fÃ¼r '.$name.'.');$external=trim($r['external_id']??'');$reference=trim($r['reference']??'')?:generate_reference();$provider=$external!==''?'csv':null;$bfStart=!empty($r['breakfast_start_date'])?parse_csv_date((string)$r['breakfast_start_date'],$dateFormat):null;$bfEnd=!empty($r['breakfast_end_date'])?parse_csv_date((string)$r['breakfast_end_date'],$dateFormat):null;$hpStart=!empty($r['half_board_start_date'])?parse_csv_date((string)$r['half_board_start_date'],$dateFormat):null;$hpEnd=!empty($r['half_board_end_date'])?parse_csv_date((string)$r['half_board_end_date'],$dateFormat):null;$sql='INSERT INTO bookings(reference,guest_id,apartment_id,apartment_type_id,arrival,departure,planned_arrival_time,planned_departure_time,adults,children,babies,pets,status,source,total_price,paid_amount,payment_status,deposit_amount,tourist_tax,discount_amount,breakfast,breakfast_start_date,breakfast_end_date,half_board,half_board_start_date,half_board_end_date,is_upgrade,upgrade_note,vehicle_plate,guest_request,internal_notes,notes,external_provider,external_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE guest_id=VALUES(guest_id),apartment_id=VALUES(apartment_id),arrival=VALUES(arrival),departure=VALUES(departure),planned_arrival_time=VALUES(planned_arrival_time),planned_departure_time=VALUES(planned_departure_time),adults=VALUES(adults),children=VALUES(children),babies=VALUES(babies),pets=VALUES(pets),status=VALUES(status),source=VALUES(source),total_price=VALUES(total_price),paid_amount=VALUES(paid_amount),payment_status=VALUES(payment_status),deposit_amount=VALUES(deposit_amount),tourist_tax=VALUES(tourist_tax),discount_amount=VALUES(discount_amount),breakfast=VALUES(breakfast),breakfast_start_date=VALUES(breakfast_start_date),breakfast_end_date=VALUES(breakfast_end_date),half_board=VALUES(half_board),half_board_start_date=VALUES(half_board_start_date),half_board_end_date=VALUES(half_board_end_date),is_upgrade=VALUES(is_upgrade),upgrade_note=VALUES(upgrade_note),vehicle_plate=VALUES(vehicle_plate),guest_request=VALUES(guest_request),internal_notes=VALUES(internal_notes),notes=VALUES(notes)';db()->prepare($sql)->execute([$reference,(int)$guestId,$aptId?:null,$aptTypeId,$arrival,$departure,nullable_time($r['planned_arrival_time']??null),nullable_time($r['planned_departure_time']??null),max(1,(int)($r['adults']??1)),max(0,(int)($r['children']??0)),max(0,(int)($r['babies']??0)),max(0,(int)($r['pets']??0)),$r['status']??'confirmed',$r['source']??'CSV',csv_number($r['total_price']??0,$decimal),csv_number($r['paid_amount']??0,$decimal),$r['payment_status']??'open',csv_number($r['deposit_amount']??0,$decimal),csv_number($r['tourist_tax']??0,$decimal),csv_number($r['discount_amount']??0,$decimal),normalize_bool($r['breakfast']??0),$bfStart,$bfEnd,normalize_bool($r['half_board']??0),$hpStart,$hpEnd,normalize_bool($r['is_upgrade']??0),$r['upgrade_note']??null,$r['vehicle_plate']??null,$r['guest_request']??null,$r['internal_notes']??null,$r['notes']??'',$provider,$external?:null]);return;}
}

function integrations_data(): never
{
    $rows=db()->query('SELECT id,provider,active,mode,base_url,property_id,username,settings_json,last_sync_at,last_status,last_message,updated_at,CASE WHEN secret_encrypted IS NULL OR secret_encrypted=\'\' THEN 0 ELSE 1 END has_secret FROM integrations ORDER BY provider')->fetchAll();
    $apartments=db()->query("SELECT id,code,name FROM apartments WHERE status='active' ORDER BY sort_order,name")->fetchAll();$mappings=db()->query("SELECT * FROM integration_mappings WHERE entity_type='apartment'")->fetchAll();
    json_response(['ok'=>true,'integrations'=>$rows,'apartments'=>$apartments,'mappings'=>$mappings]);
}
function save_integration(): never
{
    $d=request_data();$provider=(string)($d['provider']??'');if(!in_array($provider,['booking_com','hotel_spider'],true))throw new RuntimeException('Provider ungÃ¼ltig.');$settings=$d['settings']??[];if(is_string($settings))$settings=json_decode($settings,true)?:[];
    $stmt=db()->prepare('SELECT secret_encrypted FROM integrations WHERE provider=?');$stmt->execute([$provider]);$old=$stmt->fetchColumn();$secret=trim((string)($d['secret']??''));$encrypted=$secret!==''?Crypto::encrypt($secret):($old?:null);
    $stmt=db()->prepare('INSERT INTO integrations(provider,active,mode,base_url,property_id,username,secret_encrypted,settings_json) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE active=VALUES(active),mode=VALUES(mode),base_url=VALUES(base_url),property_id=VALUES(property_id),username=VALUES(username),secret_encrypted=VALUES(secret_encrypted),settings_json=VALUES(settings_json)');$stmt->execute([$provider,normalize_bool($d['active']??0),in_array($d['mode']??'test',['test','live'],true)?$d['mode']:'test',trim((string)($d['base_url']??'')),trim((string)($d['property_id']??'')),trim((string)($d['username']??'')),$encrypted,json_encode($settings,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    json_response(['ok'=>true,'message'=>'Schnittstelle gespeichert.']);
}
function save_mappings(): never
{
    $d=request_data();$provider=(string)($d['provider']??'');$rows=$d['mappings']??[];db()->prepare("DELETE FROM integration_mappings WHERE provider=? AND entity_type='apartment'")->execute([$provider]);$stmt=db()->prepare("INSERT INTO integration_mappings(provider,entity_type,local_id,external_id,secondary_external_id) VALUES(?,'apartment',?,?,?)");foreach($rows as $r){if(trim((string)($r['external_id']??''))!=='')$stmt->execute([$provider,(int)$r['local_id'],trim((string)$r['external_id']),trim((string)($r['secondary_external_id']??''))?:null]);}json_response(['ok'=>true,'message'=>'Zimmer-Mapping gespeichert.']);
}
function integration_row(string $provider): array{$stmt=db()->prepare('SELECT * FROM integrations WHERE provider=?');$stmt->execute([$provider]);$r=$stmt->fetch();if(!$r)throw new RuntimeException('Schnittstelle nicht gefunden.');return $r;}
function test_integration(): never
{
    $d=request_data();$provider=(string)($d['provider']??'');$row=integration_row($provider);$connector=IntegrationFactory::make($row);try{$result=$connector->test();log_sync($provider,'out','connection_test',$result['ok']?'success':'warning',$result['message'],$result['http_code']??null);db()->prepare('UPDATE integrations SET last_status=?,last_message=? WHERE provider=?')->execute([$result['ok']?'success':'warning',$result['message'],$provider]);json_response(['ok'=>(bool)$result['ok'],'message'=>$result['message']]);}catch(Throwable $e){log_sync($provider,'out','connection_test','error',$e->getMessage());db()->prepare('UPDATE integrations SET last_status=\'error\',last_message=? WHERE provider=?')->execute([$e->getMessage(),$provider]);throw $e;}
}
function sync_integration(): never
{
    $d=request_data();$provider=(string)($d['provider']??'');$direction=(string)($d['direction']??'pull');$row=integration_row($provider);if(!(int)$row['active'])throw new RuntimeException('Schnittstelle ist nicht aktiviert.');$connector=IntegrationFactory::make($row);try{if($direction==='push'){$result=$connector->pushAvailability(date('Y-m-d'),date('Y-m-d',strtotime('+365 days')));}else{$result=$connector->pullReservations();}log_sync($provider,$direction,$direction==='pull'?'reservations':'availability','success',$result['message']??'Erfolgreich',200,$result['request']??'',$result['raw']??'');db()->prepare('UPDATE integrations SET last_sync_at=NOW(),last_status=\'success\',last_message=? WHERE provider=?')->execute([$result['message']??'Erfolgreich',$provider]);json_response(['ok'=>true,'message'=>$result['message']??'Synchronisation abgeschlossen.']);}catch(Throwable $e){log_sync($provider,$direction,$direction==='pull'?'reservations':'availability','error',$e->getMessage());db()->prepare('UPDATE integrations SET last_status=\'error\',last_message=? WHERE provider=?')->execute([$e->getMessage(),$provider]);throw $e;}
}
function sync_logs(): never{$provider=trim((string)($_GET['provider']??''));$sql='SELECT * FROM sync_logs';$params=[];if($provider!==''){$sql.=' WHERE provider=?';$params[]=$provider;}$sql.=' ORDER BY id DESC LIMIT 200';$stmt=db()->prepare($sql);$stmt->execute($params);json_response(['ok'=>true,'logs'=>$stmt->fetchAll()]);}

function settings_data(): never
{
    $keys=['property_name','legal_name','tax_id','police_registration_number','full_address','postal_code','city','province','country','currency','checkin_time','checkout_time','contact_email','contact_phone','public_booking_enabled','accent_color','inquiry_notification_emails','vermietung_sync_enabled','vermietung_sync_url','housekeeping_guest_name_mode','housekeeping_show_booking_reference','housekeeping_show_guest_request','smart_arrival_enabled','smart_arrival_document_capture','smart_arrival_keep_document_images','smart_arrival_auto_fill_registration','smart_arrival_housekeeping_link','smart_arrival_vehicle_enabled','smart_arrival_pets_enabled','smart_arrival_mrz_helper','smart_arrival_ocr_prepare','smart_arrival_export_enabled','smart_arrival_export_region','smart_arrival_export_format','smart_arrival_mark_reported_enabled','smart_arrival_readiness_enabled','smart_arrival_arrival_reminder_hint','smart_arrival_manual_form_enabled','smart_arrival_manual_form_photo_upload','smart_arrival_paper_scan_enabled','smart_arrival_paper_scan_public_link','smart_arrival_legal_note'];
    $defaults=['vermietung_sync_enabled'=>0,'vermietung_sync_url'=>'https://quartier-schweizer.de','smart_arrival_enabled'=>1,'smart_arrival_document_capture'=>1,'smart_arrival_keep_document_images'=>0,'smart_arrival_auto_fill_registration'=>1,'smart_arrival_housekeeping_link'=>0,'smart_arrival_vehicle_enabled'=>1,'smart_arrival_pets_enabled'=>0,'smart_arrival_mrz_helper'=>1,'smart_arrival_ocr_prepare'=>0,'smart_arrival_export_enabled'=>0,'smart_arrival_export_region'=>'catalonia_mossos','smart_arrival_export_format'=>'csv_semicolon','smart_arrival_mark_reported_enabled'=>0,'smart_arrival_readiness_enabled'=>1,'smart_arrival_arrival_reminder_hint'=>1,'smart_arrival_manual_form_enabled'=>1,'smart_arrival_manual_form_photo_upload'=>0,'smart_arrival_paper_scan_enabled'=>1,'smart_arrival_paper_scan_public_link'=>1,'smart_arrival_legal_note'=>'Datenschutz-Hinweis: Ausweisbilder sollten nur verwendet werden, um die gesetzlich erforderlichen Meldedaten zu uebernehmen und zu pruefen. Dauerhafte Bildkopien nur aktivieren, wenn dies betrieblich und rechtlich wirklich notwendig ist.'];
    $out=[];foreach($keys as $k)$out[$k]=setting($k,$defaults[$k]??'');
    $out['vermietung_sync_key_set']=trim((string)setting('vermietung_sync_key',''))!=='';
    $out['staypilot_export_key_set']=trim((string)setting('staypilot_export_key_hash',''))!=='';
    $current=Auth::user();
    $system=['version'=>config()['app_version'],'php'=>PHP_VERSION,'timezone'=>date_default_timezone_get()];
    if(($current['role']??'')==='admin'){
        $token=(string)(local_config()['cron_token']??'');
        $system['cron_sync_url']=$token!==''?'../cron/sync.php?token='.$token:'';
        $system['cron_backup_url']=$token!==''?'../cron/backup.php?token='.$token:'';
    }
    json_response(['ok'=>true,'settings'=>$out,'account'=>['name'=>$current['name']??'','email'=>$current['email']??'','role'=>$current['role']??''],'system'=>$system]);
}
function save_settings(): never
{
    $d=request_data();$allowed=['property_name','legal_name','tax_id','police_registration_number','full_address','postal_code','city','province','country','currency','checkin_time','checkout_time','contact_email','contact_phone','accent_color','housekeeping_guest_name_mode','smart_arrival_export_region','smart_arrival_export_format','smart_arrival_legal_note','inquiry_notification_emails','vermietung_sync_url'];
    $old=[];$new=[];
    foreach($allowed as $k){$old[$k]=setting($k,'');if(array_key_exists($k,$d)){save_setting($k,trim((string)$d[$k]));$new[$k]=trim((string)$d[$k]);}else{$new[$k]=$old[$k];}}
    $old['public_booking_enabled']=setting('public_booking_enabled',0);$new['public_booking_enabled']=normalize_bool($d['public_booking_enabled']??0);save_setting('public_booking_enabled',$new['public_booking_enabled']);
    $old['vermietung_sync_enabled']=setting('vermietung_sync_enabled',0);$new['vermietung_sync_enabled']=normalize_bool($d['vermietung_sync_enabled']??0);save_setting('vermietung_sync_enabled',$new['vermietung_sync_enabled']);
    if(trim((string)($d['vermietung_sync_key']??''))!==''){save_setting('vermietung_sync_key',trim((string)$d['vermietung_sync_key']));$new['vermietung_sync_key']='[geändert]';}
    foreach(['housekeeping_show_booking_reference','housekeeping_show_guest_request','smart_arrival_enabled','smart_arrival_document_capture','smart_arrival_keep_document_images','smart_arrival_auto_fill_registration','smart_arrival_housekeeping_link','smart_arrival_vehicle_enabled','smart_arrival_pets_enabled','smart_arrival_mrz_helper','smart_arrival_ocr_prepare','smart_arrival_export_enabled','smart_arrival_mark_reported_enabled','smart_arrival_readiness_enabled','smart_arrival_arrival_reminder_hint','smart_arrival_manual_form_enabled','smart_arrival_manual_form_photo_upload','smart_arrival_paper_scan_enabled','smart_arrival_paper_scan_public_link'] as $key){$old[$key]=setting($key,0);$new[$key]=normalize_bool($d[$key]??0);save_setting($key,$new[$key]);}
    $mode=in_array(($d['housekeeping_guest_name_mode']??'initials'),['hidden','initials','first_initial','full'],true)?(string)$d['housekeeping_guest_name_mode']:'initials';save_setting('housekeeping_guest_name_mode',$mode);$new['housekeeping_guest_name_mode']=$mode;
    AuditLogger::record('settings','main','update',$old,$new,'Betriebseinstellungen gespeichert');
    json_response(['ok'=>true,'message'=>'Einstellungen gespeichert.']);
}
function change_password(): never
{
    $d=request_data();
    $currentPassword=(string)($d['current_password']??'');
    $newPassword=(string)($d['new_password']??'');
    $confirmPassword=(string)($d['confirm_password']??'');
    if(strlen($newPassword)<8){throw new RuntimeException('Das neue Passwort muss mindestens 8 Zeichen haben.');}
    if($newPassword!==$confirmPassword){throw new RuntimeException('Die beiden neuen PasswÃ¶rter stimmen nicht Ã¼berein.');}
    $user=Auth::user();
    if(!$user){throw new RuntimeException('Nicht angemeldet.');}
    $stmt=db()->prepare('SELECT password_hash FROM users WHERE id=? AND active=1 LIMIT 1');
    $stmt->execute([(int)$user['id']]);
    $hash=$stmt->fetchColumn();
    if(!$hash||!password_verify($currentPassword,(string)$hash)){throw new RuntimeException('Das bisherige Passwort ist nicht korrekt.');}
    db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($newPassword,PASSWORD_DEFAULT),(int)$user['id']]);
    session_regenerate_id(true);
    AuditLogger::record('user',(int)$user['id'],'password_change',null,null,'Eigenes Passwort geÃ¤ndert');
    json_response(['ok'=>true,'message'=>'Passwort wurde geÃ¤ndert.']);
}
