<?php
class ControllerExtensionModuleLokkisonaCustomInvoicePro extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/lokkisona_custom_invoice_pro');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_lokkisona_custom_invoice_pro', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)),
            array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)),
            array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/lokkisona_custom_invoice_pro', 'user_token=' . $this->session->data['user_token'], true))
        );

        $data['action'] = $this->url->link('extension/module/lokkisona_custom_invoice_pro', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['module_lokkisona_custom_invoice_pro_status'] = isset($this->request->post['module_lokkisona_custom_invoice_pro_status']) ? $this->request->post['module_lokkisona_custom_invoice_pro_status'] : $this->config->get('module_lokkisona_custom_invoice_pro_status');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/lokkisona_custom_invoice_pro_form', $data));
    }

    public function invoice() {
        $this->load->language('extension/module/lokkisona_custom_invoice_pro');
        $this->load->model('extension/module/lokkisona_custom_invoice_pro');
        $this->load->model('tool/image');

        $order_ids = array();
        if (isset($this->request->get['order_id'])) {
            $order_ids[] = (int)$this->request->get['order_id'];
        }
        if (isset($this->request->get['selected'])) {
            $selected = is_array($this->request->get['selected']) ? $this->request->get['selected'] : explode(',', $this->request->get['selected']);
            foreach ($selected as $order_id) {
                $order_ids[] = (int)$order_id;
            }
        }
        $order_ids = array_values(array_unique(array_filter($order_ids)));

        $data['title'] = 'Custom Invoice by PIT';
        $data['invoices'] = array();
        $data['print_now'] = true;

        foreach ($order_ids as $order_id) {
            $order = $this->model_extension_module_lokkisona_custom_invoice_pro->getOrder($order_id);
            if (!$order) { continue; }

            $products = array();
            foreach ($this->model_extension_module_lokkisona_custom_invoice_pro->getOrderProducts($order_id) as $product) {
                $options = array();
                foreach ($product['options'] as $option) {
                    $options[] = array(
                        'name' => $option['name'],
                        'value' => $option['value'],
                        'image' => $this->resizeImage($option['image'], 80, 80)
                    );
                }

                $ordered_option_image = '';
                foreach ($product['options'] as $raw_option) {
                    if (!empty($raw_option['image'])) { $ordered_option_image = $raw_option['image']; break; }
                }

                $products[] = array(
                    'image' => $this->resizeImage($ordered_option_image ? $ordered_option_image : (isset($product['product_image']) ? $product['product_image'] : ''), 120, 120),
                    'name' => $product['name'],
                    'model' => $product['model'],
                    'options' => $options,
                    'quantity' => (int)$product['quantity'],
                    'price' => $this->formatMoney($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order),
                    'total' => $this->formatMoney($product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order)
                );
            }

            $totals = array();
            foreach ($this->model_extension_module_lokkisona_custom_invoice_pro->getOrderTotals($order_id) as $total) {
                $totals[] = array(
                    'title' => $total['title'],
                    'code' => $total['code'],
                    'text' => $this->formatMoney($total['value'], $order)
                );
            }

            $steadfast = $this->model_extension_module_lokkisona_custom_invoice_pro->getSteadfastInfo($order_id);
            $qr_payload = $this->getQrPayload($steadfast);

            $data['invoices'][] = array(
                'store_name' => $this->config->get('config_name') ?: 'Lokkisona Baby Store',
                'store_logo' => $this->resizeImage($this->config->get('config_logo'), 220, 90),
                'store_address' => $this->config->get('config_address') ?: 'House 1, Block C, Dhaka Udyan Main Road, Mohammadpur, Dhaka',
                'store_phone' => '01932263545',
                'store_email' => $this->config->get('config_email') ?: 'support@lokkisona.com',
                'store_url' => 'www.lokkisona.com',
                'order_id' => $order_id,
                'invoice_no' => $this->formatInvoiceNo($order),
                'order_date' => date($this->language->get('date_format_short'), strtotime($order['date_added'])),
                'customer_name' => trim($order['firstname'] . ' ' . $order['lastname']),
                'customer_phone' => $order['telephone'],
                                'shipping_address' => $this->formatAddress($order, 'shipping'),
                'payment_method' => $order['payment_method'],
                'payment_logo' => $this->getPaymentLogoType($order['payment_method']),
                'shipping_method' => $this->getCourierName($order['shipping_method'], $steadfast),
                'payment_status' => $this->getPaymentStatus($order['payment_method']),
                'products' => $products,
                'totals' => $totals,
                'steadfast' => $steadfast,
                'qr_payload' => $qr_payload
            );
        }

        $this->response->setOutput($this->load->view('extension/module/lokkisona_custom_invoice_pro_invoice', $data));
    }

    public function bulk() {
        $selected = array();
        if (isset($this->request->post['selected']) && is_array($this->request->post['selected'])) {
            $selected = array_map('intval', $this->request->post['selected']);
        } elseif (isset($this->request->get['selected'])) {
            $selected = array_map('intval', (array)$this->request->get['selected']);
        }
        $this->response->redirect($this->url->link('extension/module/lokkisona_custom_invoice_pro/invoice', 'user_token=' . $this->session->data['user_token'] . '&selected=' . implode(',', array_filter($selected)), true));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/lokkisona_custom_invoice_pro')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }

    private function resizeImage($image, $width, $height) {
        if ($image && is_file(DIR_IMAGE . $image)) {
            return $this->model_tool_image->resize($image, $width, $height);
        }
        return $this->model_tool_image->resize('placeholder.png', $width, $height);
    }

    private function formatInvoiceNo($order) {
        if (!empty($order['invoice_no'])) {
            return $order['invoice_prefix'] . $order['invoice_no'];
        }
        return 'Pending';
    }

    private function formatAddress($order, $type) {
        $parts = array();
        $keys = array($type . '_firstname', $type . '_lastname', $type . '_company', $type . '_address_1', $type . '_address_2', $type . '_city', $type . '_zone', $type . '_postcode', $type . '_country');
        foreach ($keys as $key) {
            if (isset($order[$key]) && trim($order[$key]) !== '') {
                $parts[] = trim($order[$key]);
            }
        }
        return implode(', ', array_unique($parts));
    }


    private function formatMoney($value, $order) {
        $text = $this->currency->format($value, $order['currency_code'], $order['currency_value']);
        $text = str_replace(array('৳', 'Tk.', 'Tk', 'BDT'), '', $text);
        return trim($text);
    }


    private function getPaymentLogoType($payment_method) {
        $payment_method = strtolower((string)$payment_method);
        if (strpos($payment_method, 'bkash') !== false || strpos($payment_method, 'b-kash') !== false) {
            return 'bkash';
        }
        if (strpos($payment_method, 'cash') !== false || strpos($payment_method, 'cod') !== false || strpos($payment_method, 'delivery') !== false) {
            return 'cod';
        }
        return 'other';
    }

    private function getCourierName($shipping_method, $steadfast) {
        if (!empty($steadfast['consignment_id']) || !empty($steadfast['parcel_id']) || !empty($steadfast['tracking_code'])) {
            return 'Steadfast Courier';
        }
        return $shipping_method ? $shipping_method : 'Not available';
    }

    private function getPaymentStatus($payment_method) {
        $payment_method = strtolower((string)$payment_method);
        if (strpos($payment_method, 'bkash') !== false || strpos($payment_method, 'b-kash') !== false || strpos($payment_method, 'paid') !== false) {
            return 'Paid';
        }
        return 'Due';
    }

    private function getQrPayload($steadfast) {
        if (!empty($steadfast['qr_code'])) { return $steadfast['qr_code']; }
        if (!empty($steadfast['tracking_url'])) { return $steadfast['tracking_url']; }
        if (!empty($steadfast['tracking_code'])) { return $steadfast['tracking_code']; }
        if (!empty($steadfast['consignment_id'])) { return $steadfast['consignment_id']; }
        if (!empty($steadfast['parcel_id'])) { return $steadfast['parcel_id']; }
        return '';
    }
}
