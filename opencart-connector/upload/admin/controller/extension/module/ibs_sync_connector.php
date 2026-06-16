<?php

/**
 * IBS Sync Connector — admin settings (OpenCart settings table only; no product/order writes).
 */
class ControllerExtensionModuleIbsSyncConnector extends Controller
{
    private $error = [];

    public function index()
    {
        $this->load->language('extension/module/ibs_sync_connector');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->load->model('localisation/order_status');

        if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate()) {
            $this->request->post['module_ibs_sync_connector_bridge_table'] = 'dispatch_location_product';
            if (!isset($this->request->post['module_ibs_sync_connector_queue_status_ids'])
                || !is_array($this->request->post['module_ibs_sync_connector_queue_status_ids'])) {
                $this->request->post['module_ibs_sync_connector_queue_status_ids'] = [];
            }
            $this->model_setting_setting->editSetting('module_ibs_sync_connector', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link(
                'extension/module/ibs_sync_connector',
                'user_token=' . $this->session->data['user_token'],
                true
            ));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true),
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/ibs_sync_connector', 'user_token=' . $this->session->data['user_token'], true),
        ];

        $data['action'] = $this->url->link('extension/module/ibs_sync_connector', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['generate'] = $this->url->link('extension/module/ibs_sync_connector/generate', 'user_token=' . $this->session->data['user_token'], true);

        $data['module_ibs_sync_connector_status'] = $this->request->post['module_ibs_sync_connector_status']
            ?? $this->config->get('module_ibs_sync_connector_status');
        $data['module_ibs_sync_connector_api_token'] = $this->request->post['module_ibs_sync_connector_api_token']
            ?? $this->config->get('module_ibs_sync_connector_api_token');
        $data['module_ibs_sync_connector_bridge_table'] = 'dispatch_location_product';
        $data['module_ibs_sync_connector_max_limit'] = $this->request->post['module_ibs_sync_connector_max_limit']
            ?? ($this->config->get('module_ibs_sync_connector_max_limit') ?: 20);

        $savedQueueIds = $this->config->get('module_ibs_sync_connector_queue_status_ids');
        if (!is_array($savedQueueIds)) {
            $savedQueueIds = [];
        }
        $postedQueueIds = $this->request->post['module_ibs_sync_connector_queue_status_ids'] ?? null;
        $data['module_ibs_sync_connector_queue_status_ids'] = is_array($postedQueueIds)
            ? $postedQueueIds
            : $savedQueueIds;

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $catalogBase = (defined('HTTPS_CATALOG') && HTTPS_CATALOG) ? HTTPS_CATALOG : HTTP_CATALOG;
        $token = (string) $data['module_ibs_sync_connector_api_token'];
        $data['connection_test_url'] = $catalogBase . 'index.php?route=api/ibs/connection_test&api_token=' . rawurlencode($token);
        $data['products_url'] = $catalogBase . 'index.php?route=api/ibs/products&api_token=' . rawurlencode($token) . '&page=1&limit=20';
        $data['order_queue_statuses_url'] = $catalogBase . 'index.php?route=api/ibs/order_queue_statuses&api_token=' . rawurlencode($token);
        $data['orders_url'] = $catalogBase . 'index.php?route=api/ibs/orders&api_token=' . rawurlencode($token) . '&page=1&limit=20';
        $data['orders_audit_url'] = $catalogBase . 'index.php?route=api/ibs/orders_audit&api_token=' . rawurlencode($token) . '&status_ids[]=25&page=1&limit=20';
        $data['orders_audit_fallback_url'] = $catalogBase . 'index.php?route=api/ibs/orders/audit&api_token=' . rawurlencode($token) . '&status_ids[]=25&page=1&limit=20';

        if (!defined('IBS_SYNC_CONNECTOR_VERSION')) {
            require_once DIR_SYSTEM . 'library/ibs/connector_version.php';
        }
        if (!defined('IBS_SYNC_CONNECTOR_BUILD')) {
            require_once DIR_SYSTEM . 'library/ibs/connector_build.php';
        }
        $data['connector_version'] = IBS_SYNC_CONNECTOR_VERSION;
        $data['connector_build'] = defined('IBS_SYNC_CONNECTOR_BUILD') ? IBS_SYNC_CONNECTOR_BUILD : 'unknown';

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/ibs_sync_connector', $data));
    }

    public function generate()
    {
        $this->load->language('extension/module/ibs_sync_connector');
        $this->load->model('setting/setting');

        if (!$this->user->hasPermission('modify', 'extension/module/ibs_sync_connector')) {
            $this->response->redirect($this->url->link('extension/module/ibs_sync_connector', 'user_token=' . $this->session->data['user_token'], true));
        }

        $settings = $this->model_setting_setting->getSetting('module_ibs_sync_connector');
        $settings['module_ibs_sync_connector_api_token'] = bin2hex(random_bytes(24));
        if (!isset($settings['module_ibs_sync_connector_status'])) {
            $settings['module_ibs_sync_connector_status'] = 1;
        }
        $this->model_setting_setting->editSetting('module_ibs_sync_connector', $settings);

        $this->session->data['success'] = $this->language->get('text_success');
        $this->response->redirect($this->url->link('extension/module/ibs_sync_connector', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function install()
    {
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('module_ibs_sync_connector', [
            'module_ibs_sync_connector_status' => 1,
            'module_ibs_sync_connector_api_token' => bin2hex(random_bytes(24)),
            'module_ibs_sync_connector_bridge_table' => 'dispatch_location_product',
            'module_ibs_sync_connector_max_limit' => 20,
            'module_ibs_sync_connector_queue_status_ids' => [],
            'module_ibs_sync_connector_order_field_map' => json_encode([
                'courier_status' => ['courier_status', 'shipping_status'],
                'consignment_id' => ['consignment_id', 'tracking_number', 'tracking_no'],
                'courier_name' => ['courier_name', 'shipping_method'],
            ]),
        ]);
    }

    public function uninstall()
    {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_ibs_sync_connector');
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/module/ibs_sync_connector')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $enabled = (int) ($this->request->post['module_ibs_sync_connector_status'] ?? 0) === 1;
        $queueIds = $this->request->post['module_ibs_sync_connector_queue_status_ids'] ?? [];
        if ($enabled && (!is_array($queueIds) || $queueIds === [])) {
            $this->error['warning'] = $this->language->get('error_queue_empty');
        }

        return $this->error === [] || !isset($this->error['warning']);
    }
}
