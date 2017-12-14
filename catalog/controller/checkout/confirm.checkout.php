<?php
class ControllerCheckoutConfirm extends Controller {

    public function index() {

        $redirect = '';
        $data = array();
        $data = array_merge($data, $this->load->language('order/order'));
        $this->document->setTitle($this->language->get('heading_title2'));

        if ($this->cart->hasShipping()) {

            // Validate if shipping address has been set.
            $this->load->model('account/address');

            $shipping_address = $this->session->data['guest']['shipping_address'];

            if (empty($shipping_address)) {
                $redirect = $this->url->link('checkout/checkout/guest', '', 'SSL');
            }
        } else {
            unset($this->session->data['shipping_method']);
            unset($this->session->data['shipping_methods']);
        }

        // Validate if payment address has been set.
        $this->load->model('account/address');

        // Validate if payment method has been set.

        if (!isset($this->session->data['payment_method'])) {
            $redirect = $this->url->link('checkout/checkout/guest', '', 'SSL');
        }

        if (!$this->cart->hasStock() && (!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning'))) {
            $data['error_warning']['error_stock'] = $this->language->get('error_stock');
        } elseif (isset($this->session->data['error'])) {
            $data['error_warning'] = $this->session->data['error'];

            unset($this->session->data['error']);
        } else {
            $data['error_warning'] = '';
        }

        if (!empty($this->session->data['error_payment_failure'])) {
            $data['error_warning']['error_payment_failure'] = $this->session->data['error_payment_failure'];
            unset($this->session->data['error_payment_failure']);
        }

        if ($this->config->get('config_customer_price') && !$this->customer->isLogged()) {
            $data['attention'] = sprintf($this->language->get('text_login'), $this->url->link('account/login'), $this->url->link('account/register'));
        } else {
            $data['attention'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];

            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        // Validate minimum quantity requirments.
        $products = $this->cart->getProducts();

        foreach ($products as $product) {
            $product_total = 0;

            foreach ($products as $product_2) {
                if ($product_2['product_id'] == $product['product_id']) {
                    $product_total += $product_2['quantity'];
                }
            }

            if ($product['minimum'] > $product_total) {
                break;
            }
        }

        if (!$redirect) {
            $totals = array();
            $total = 0;
            $taxes = $this->cart->getTaxes();

            // Because __call can not keep var references so we put them into an array.
            $total_data = array(
                'totals' => &$totals,
                'taxes'  => &$taxes,
                'total'  => &$total
            );
            $this->load->model('extension/extension');

            $sort_order = array();

            $results = $this->model_extension_extension->getExtensions('total');

            foreach ($results as $key => $value) {
                $sort_order[$key] = $this->config->get($value['code'] . '_sort_order');
            }

            array_multisort($sort_order, SORT_ASC, $results);
            foreach ($results as $result) {
                if ($this->config->get($result['code'] . '_status')) {
                    $this->load->model('extension/total/' . $result['code']);

                    $this->{'model_extension_total_' . $result['code']}->getTotal($total_data, $total, $taxes);
                }
            }

            $sort_order = array();
            foreach ($totals as $key => $value) {
                $sort_order[$key] = $value['sort_order'];
            }

            array_multisort($sort_order, SORT_ASC, $totals);

            $this->language->load('checkout/checkout');

            $order_data = array();
            $order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
            $order_data['store_id'] = $this->config->get('config_store_id');
            $order_data['store_name'] = $this->config->get('config_name');

            $order_data['serial'] = $this->session->data['guest']['serial'];

            if ($order_data['store_id']) {
                $order_data['store_url'] = $this->config->get('config_url');
            } else {
                $order_data['store_url'] = HTTP_SERVER;
            }

            if ($this->customer->isLogged()) {
                $this->load->model('account/customer');

                $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
                $order_data['customer_id'] = $this->customer->getId();
                $order_data['customer_group_id'] = $customer_info['customer_group_id'];
                $order_data['firstname'] = $customer_info['firstname'];
                $order_data['lastname'] = $customer_info['lastname'];
                $order_data['email'] = $customer_info['email'];
                $order_data['telephone'] = $customer_info['telephone'];
                $order_data['fax'] = $customer_info['fax'];
                $order_data['custom_field'] = unserialize($customer_info['custom_field']);
                $this->load->model('account/address');
            } elseif (isset($this->session->data['guest'])) {
                $order_data['customer_id'] = 0;
                $order_data['customer_group_id'] = $this->session->data['guest']['customer_group_id'];
                $order_data['firstname'] = $this->session->data['guest']['firstname'];
                $order_data['lastname'] = $this->session->data['guest']['lastname'];

                $order_data['email'] = $this->session->data['guest']['email'];
                $order_data['telephone'] = $this->session->data['guest']['telephone'];
                $order_data['fax'] = $this->session->data['guest']['fax'];
            }

            $payment_address = $this->session->data['guest']['payment'];

            $order_data['payment_firstname'] = $payment_address['firstname'];
            $order_data['payment_lastname'] = $payment_address['lastname'];
            $order_data['payment_company'] = $payment_address['company'];
            $order_data['payment_company_id'] = $payment_address['company_id'];
            $order_data['payment_tax_id'] = $payment_address['tax_id'];
            $order_data['payment_address_1'] = $payment_address['address_1'];
            $order_data['payment_address_2'] = $payment_address['address_2'];
            $order_data['payment_city'] = $payment_address['city'];
            $order_data['payment_postcode'] = $payment_address['postcode'];
            $order_data['payment_zone'] = $payment_address['zone'];
            $order_data['payment_zone_id'] = $payment_address['zone_id'];
            $order_data['payment_country'] = $payment_address['country'];
            $order_data['payment_country_id'] = $payment_address['country_id'];
            $order_data['payment_address_format'] = $payment_address['address_format'];
            $order_data['customer_group_id'] = $payment_address['customer_group_id'];
            $order_data['company_name'] = $payment_address['company_name'];
            $order_data['reg_num'] = $payment_address['reg_num'];
            $order_data['vat_num'] = $payment_address['vat_num'];
            $order_data['bank_name'] = $payment_address['bank_name'];
            $order_data['bank_code'] = $payment_address['bank_code'];
            $order_data['bank_account'] = $payment_address['bank_account'];
            $order_data['address_2'] = $payment_address['address_2'];

            if (isset($this->session->data['payment_method']['title'])) {
                $order_data['payment_method'] = $this->session->data['payment_method']['title'];
            } else {
                $order_data['payment_method'] = '';
            }
            //pr($this->session->data['shipping_method']);
            if (isset($this->session->data['payment_method']['code'])) {
                $order_data['payment_code'] = $this->session->data['payment_method']['code'];
            } else {
                $order_data['payment_code'] = '';
            }

            if ($this->cart->hasShipping()) {
                if (!$this->customer->isLogged() && isset($this->session->data['guest'])) {
                    $shipping_address = $this->session->data['guest']['shipping'];
                }
                $order_data['shipping_firstname'] = $shipping_address['firstname'];
                $order_data['shipping_lastname'] = $shipping_address['lastname'];
                $order_data['shipping_company'] = $shipping_address['company'];
                $order_data['shipping_address_1'] = $shipping_address['address_1'];
                $order_data['shipping_address_2'] = (isset($shipping_address['address_2']) ? $shipping_address['address_2'] : '');
                $order_data['shipping_city'] = $shipping_address['city'];
                $order_data['shipping_postcode'] = $shipping_address['postcode'];
                $order_data['shipping_zone'] = $shipping_address['zone'];
                $order_data['shipping_zone_id'] = $shipping_address['zone_id'];
                $order_data['shipping_country'] = $shipping_address['country'];
                $order_data['shipping_country_id'] = $shipping_address['country_id'];
                $order_data['shipping_address_format'] = $shipping_address['address_format'];

                if (isset($this->session->data['shipping_method']['title'])) {
                    $order_data['shipping_method'] = $this->session->data['shipping_method']['title'];
                } else {
                    $order_data['shipping_method'] = '';
                }

                if (isset($this->session->data['shipping_method']['code'])) {
                    $order_data['shipping_code'] = $this->session->data['shipping_method']['code'];
                } else {
                    $order_data['shipping_code'] = '';
                }
            } else {
                $order_data['shipping_firstname'] = '';
                $order_data['shipping_lastname'] = '';
                $order_data['shipping_company'] = '';
                $order_data['shipping_address_1'] = '';
                $order_data['shipping_address_2'] = '';
                $order_data['shipping_city'] = '';
                $order_data['shipping_postcode'] = '';
                $order_data['shipping_zone'] = '';
                $order_data['shipping_zone_id'] = '';
                $order_data['shipping_country'] = '';
                $order_data['shipping_country_id'] = '';
                $order_data['shipping_address_format'] = '';
                $order_data['shipping_method'] = '';
                $order_data['shipping_code'] = '';
            }

            $product_data = array();

            foreach ($this->cart->getProducts() as $product) {
                $option_data = array();
                foreach ($product['option'] as $option) {

                    $option_data[] = array(
                        'product_option_id'       => $option['product_option_id'],
                        'product_option_value_id' => $option['product_option_value_id'],
                        'option_id'               => $option['option_id'],
                        'option_value_id'         => $option['option_value_id'],
                        'name'                    => $option['name'],
                        'value'                   => $option['value'],
                        'type'                    => $option['type']
                    );
                }
                $product_data[] = array(
                    'product_id' => $product['product_id'],
                    'name'       => $product['name'],
                    'model'      => $product['model'],
                    'option'     => $option_data,
                    'download'   => $product['download'],
                    'quantity'   => $product['quantity'],
                    'subtract'   => $product['subtract'],
                    'price'      => $product['price'],
                    'total'      => $product['total'],
                    'tax'        => $this->tax->getTax($product['price'], $product['tax_class_id']),
                    'reward'     => $product['reward']
                );
            }

            // Gift Voucher
            $voucher_data = array();

            if (!empty($this->session->data['vouchers'])) {
                foreach ($this->session->data['vouchers'] as $voucher) {
                    $voucher_data[] = array(
                        'description'      => $voucher['description'],
                        'code'             => substr(md5(mt_rand()), 0, 10),
                        'to_name'          => $voucher['to_name'],
                        'to_email'         => $voucher['to_email'],
                        'from_name'        => $voucher['from_name'],
                        'from_email'       => $voucher['from_email'],
                        'voucher_theme_id' => $voucher['voucher_theme_id'],
                        'message'          => $voucher['message'],
                        'amount'           => $voucher['amount']
                    );
                }
            }

            $order_data['products'] = $product_data;
            $order_data['vouchers'] = $voucher_data;
            $order_data['totals'] = $total_data['totals'];
            $order_data['comment'] = empty($this->session->data['comment']) ? '' : $this->session->data['comment'];
            $order_data['total'] = $total;


            if (isset($this->request->cookie['tracking'])) {
                $order_data['tracking'] = $this->request->cookie['tracking'];
                $subtotal = $this->cart->getSubTotal();

                // Affiliate
                $this->load->model('affiliate/affiliate');

                $affiliate_info = $this->model_affiliate_affiliate->getAffiliateByCode($this->request->cookie['tracking']);

                if ($affiliate_info) {
                    $order_data['affiliate_id'] = $affiliate_info['affiliate_id'];
                    $order_data['commission'] = ($subtotal / 100) * $affiliate_info['commission'];
                } else {
                    $order_data['affiliate_id'] = 0;
                    $order_data['commission'] = 0;
                }

                // Marketing
                $this->load->model('checkout/marketing');

                $marketing_info = $this->model_checkout_marketing->getMarketingByCode($this->request->cookie['tracking']);

                if ($marketing_info) {
                    $order_data['marketing_id'] = $marketing_info['marketing_id'];
                } else {
                    $order_data['marketing_id'] = 0;
                }
            } else {
                $order_data['affiliate_id'] = 0;
                $order_data['commission'] = 0;
                $order_data['marketing_id'] = 0;
                $order_data['tracking'] = '';
            }

            $order_data['language_id'] = $this->config->get('config_language_id');
            $order_data['currency_id'] = $this->currency->getId($this->session->data['currency']);
            $order_data['currency_code'] = $this->session->data['currency'];
            $order_data['currency_value'] = $this->currency->getValue($this->session->data['currency']);

            $order_data['ip'] = $this->request->server['REMOTE_ADDR'];

            if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
                $order_data['forwarded_ip'] = $this->request->server['HTTP_X_FORWARDED_FOR'];
            } elseif (!empty($this->request->server['HTTP_CLIENT_IP'])) {
                $order_data['forwarded_ip'] = $this->request->server['HTTP_CLIENT_IP'];
            } else {
                $order_data['forwarded_ip'] = '';
            }

            if (isset($this->request->server['HTTP_USER_AGENT'])) {
                $order_data['user_agent'] = $this->request->server['HTTP_USER_AGENT'];
            } else {
                $order_data['user_agent'] = '';
            }
            if (isset($this->request->server['HTTP_ACCEPT_LANGUAGE'])) {
                $order_data['accept_language'] = $this->request->server['HTTP_ACCEPT_LANGUAGE'];
            } else {
                $order_data['accept_language'] = '';
            }

            $this->load->model('checkout/order');

            prd($order_data);

            $this->session->data['order_id'] = $this->model_checkout_order->addOrder($order_data);

            $data['column_name'] = $this->language->get('column_name');
            $data['column_model'] = $this->language->get('column_model');
            $data['column_quantity'] = $this->language->get('column_quantity');
            $data['column_price'] = $this->language->get('column_price');
            $data['column_total'] = $this->language->get('column_total');

            $data['products'] = array();

            $this->load->model('tool/upload');

            foreach ($this->cart->getProducts() as $product) {
                $option_data = array();

                foreach ($product['option'] as $option) {
                    if ($option['type'] != 'file') {
                        $value = $option['value'];
                    } else {

                        $upload_info = $this->model_tool_upload->getUploadByCode($option['value']);
                        if ($upload_info) {
                            $value = $upload_info['name'];
                        } else {
                            $value = '';
                        }
                    }
                    $option_data[] = array(
                        'name'  => $option['name'],
                        'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
                    );
                }

                $recurring = '';

                if ($product['recurring']) {
                    $frequencies = array(
                        'day'        => $this->language->get('text_day'),
                        'week'       => $this->language->get('text_week'),
                        'semi_month' => $this->language->get('text_semi_month'),
                        'month'      => $this->language->get('text_month'),
                        'year'       => $this->language->get('text_year'),
                    );

                    if ($product['recurring']['trial']) {
                        $recurring = sprintf($this->language->get('text_trial_description'), $this->currency->format($this->tax->calculate($product['recurring']['trial_price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax'))), $product['recurring']['trial_cycle'], $frequencies[$product['recurring']['trial_frequency']], $product['recurring']['trial_duration']) . ' ';
                    }

                    if ($product['recurring']['duration']) {
                        $recurring .= sprintf($this->language->get('text_payment_description'), $this->currency->format($this->tax->calculate($product['recurring']['price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax'))), $product['recurring']['cycle'], $frequencies[$product['recurring']['frequency']], $product['recurring']['duration']);
                    } else {
                        $recurring .= sprintf($this->language->get('text_payment_cancel'), $this->currency->format($this->tax->calculate($product['recurring']['price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax'))), $product['recurring']['cycle'], $frequencies[$product['recurring']['frequency']], $product['recurring']['duration']);
                    }
                }

                $data['products'][] = array(
                    'product_id'         => $product['product_id'],
                    'name'               => $product['name'],
                    'model'              => $product['model'],
                    'option'             => $option_data,
                    'quantity'           => $product['quantity'],
                    'subtract'           => $product['subtract'],
                    'recurring'          => $recurring,
                    'price'              => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
                    'total'              => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) * $product['quantity'], $this->session->data['currency']),
                    'href'               => $this->url->link('product/product', 'product_id=' . $product['product_id']),
                    'price_no_vat'       => $this->currency->format($product['price'], $this->session->data['currency']),
                    'price_no_vat_total' => $this->currency->format($product['price'] * $product['quantity'], $this->session->data['currency']),
                );
            }

            // Gift Voucher
            $data['vouchers'] = array();

            $data['totals'] = array();

            foreach ($order_data['totals'] as $total) {
                $data['totals'][] = array(
                    'title' => $total['title'],
                    'text'  => $this->currency->format($total['value'], $this->session->data['currency']),
                );
            }

            $data['payment'] = $this->load->controller('extension/payment/' . explode('.',$this->session->data['payment_method']['code'])[0]);
        } else {
            $data['redirect'] = $redirect;
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');

        $this->hook->getHook('checkout/confirm/index/after', $data);
//prd($data);
        $this->response->setOutput($this->load->view('checkout/confirm', $data));
    }

}
?>