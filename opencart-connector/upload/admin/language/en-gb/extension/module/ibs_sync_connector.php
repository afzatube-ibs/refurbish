<?php

$_['heading_title']    = 'IBS Sync Connector';
$_['text_extension']   = 'Extensions';
$_['text_success']     = 'Success: You have modified IBS Sync Connector settings!';
$_['text_edit']        = 'Edit IBS Sync Connector';
$_['text_enabled']     = 'Enabled';
$_['text_disabled']    = 'Disabled';
$_['text_read_only']   = 'Read-only API — no product, stock, or order writes.';
$_['text_generate']    = 'Generate new token';
$_['text_queue_help']  = 'Select OpenCart order statuses exposed to IBS ERP. The orders API filters by status_ids only (no warehouse or product matching). IBS maps each queue status to an SFM workflow status in Sync Settings.';
$_['text_bridge_readonly'] = 'Fixed by Dispatch Location extension — not editable here.';

$_['entry_status']       = 'Status';
$_['entry_api_token']    = 'API Token';
$_['entry_bridge_table'] = 'Dispatch bridge table';
$_['entry_max_limit']    = 'Max rows per page (cap 20)';
$_['entry_queue_statuses'] = 'Supplier Order Queue Statuses';

$_['help_api_token']    = 'Copy this token into IBS ERP → System → Sync/API Settings. Keep it secret.';
$_['help_bridge_table'] = 'Read-only. Always dispatch_location_product (Dispatch Location extension).';
$_['help_endpoints']    = 'Catalog API routes (append api_token): connection_test, version, products, order_queue_statuses, orders, orders_audit.';

$_['button_save']     = 'Save';
$_['button_cancel']   = 'Cancel';
$_['button_generate'] = 'Generate Token';

$_['error_permission'] = 'Warning: You do not have permission to modify IBS Sync Connector!';
$_['error_queue_empty'] = 'Warning: Select at least one Supplier Order Queue status when the connector is enabled.';
