<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}


// Paystack Gateway class (class-paystack-gateway.php)
class Cladshati_Paystack_Gateway extends WC_Payment_Gateway {
    public function __construct() {
        $this->id = 'cladshati_paystack';
        $this->method_title = 'Paystack';
        $this->method_description = 'Accept payments through  Paystack';

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_cladshati_paystack', array($this, 'webhook'));
    }

       public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable Paystack Gateway',
                'default' => 'no'
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default' => 'Paystack'
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'This controls the description which the user sees during checkout.',
                'default' => 'Pay with your credit card via Paystack.'
            ),
            'test_secret_key' => array(
                'title' => 'Test Secret Key',
                'type' => 'text'
            ),
            'test_public_key' => array(
                'title' => 'Test Public Key',
                'type' => 'text'
            ),
            'live_secret_key' => array(
                'title' => 'Live Secret Key',
                'type' => 'text'
            ),
            'live_public_key' => array(
                'title' => 'Live Public Key',
                'type' => 'text'
            )
        );
    }

    public function process_payment($order_id) {
        global $woocommerce;
        $order = wc_get_order($order_id);

        // Paystack API endpoint
        $url = 'https://api.paystack.co/transaction/initialize';

        // Set up the payment data
        $amount = $order->get_total() * 100; // Paystack processes amount in kobo
        $email = $order->get_billing_email();
        $reference = 'CLADSHATI_' . $order_id . '_' . time();

        $body = array(
            'amount' => $amount,
            'email' => $email,
            'reference' => $reference,
            'callback_url' => home_url('wc-api/cladshati_paystack')
        );

        $headers = array(
            //live key
            'Authorization' => 'Bearer ' . $this->get_option('test_secret_key'),
            'Content-Type' => 'application/json'
        );

        $args = array(
            'body' => json_encode($body),
            'headers' => $headers,
            'timeout' => 60
        );

        $response = wp_remote_post($url, $args);

        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response));

            if ($body->status) {
                // Payment initialization successful
                $order->update_status('on-hold', __('Awaiting Paystack payment', 'cladshati-pay'));
                $order->add_order_note('Payment pending via Paystack. Transaction Reference: ' . $reference);
                $woocommerce->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $body->data->authorization_url
                );
            } else {
                wc_add_notice('Payment error: ' . $body->message, 'error');
                return;
            }
        } else {
            wc_add_notice('Connection error.', 'error');
            return;
        }
    }

    public function webhook() {
        $input = file_get_contents('php://input');
        $event = json_decode($input);

        if (!$event) {
            exit;
        }

        http_response_code(200);

        if ($event->event === 'charge.success') {
            $order = wc_get_order($event->data->reference);

            if (!$order) {
                exit;
            }

            $order->payment_complete($event->data->reference);
            $order->add_order_note('Payment successful via Paystack.');
        }

        exit;
    }
}