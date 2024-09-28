<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Mpesa Gateway class (class-mpesa-gateway.php)
class Cladshati_Mpesa_Gateway extends WC_Payment_Gateway {
    public function __construct() {
        $this->id = 'cladshati_mpesa';
        $this->method_title = 'M-Pesa';
        $this->method_description = 'Accept payments through M-Pesa';

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_cladshati_mpesa', array($this, 'callback'));
    }

      public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable M-Pesa Gateway',
                'default' => 'no'
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default' => 'M-Pesa'
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'This controls the description which the user sees during checkout.',
                'default' => 'Pay with M-Pesa.'
            ),
            'consumer_key' => array(
                'title' => 'Consumer Key',
                'type' => 'text'
            ),
            'consumer_secret' => array(
                'title' => 'Consumer Secret',
                'type' => 'text'
            ),
            'shortcode' => array(
                'title' => 'Shortcode',
                'type' => 'text'
            ),
              'passkey' => array(
                'title' => 'Passkey',
                'type' => 'text'
            )
             
        );
    }

       private function initiate_stk_push($phone, $amount, $account_reference, $transaction_desc, $callback_url) {
        $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        $access_token = $this->get_access_token();

        $timestamp = date('YmdHis');
        $password = base64_encode($this->get_option('shortcode') . $this->get_option('passkey') . $timestamp);

        $curl_post_data = array(
            'BusinessShortCode' => $this->get_option('shortcode'),
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $this->get_option('shortcode'),
            'PhoneNumber' => $phone,
            'CallBackURL' => $callback_url,
            'AccountReference' => $account_reference,
            'TransactionDesc' => $transaction_desc
        );

        $headers = array(
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curl_post_data));

        $curl_response = curl_exec($curl);
        curl_close($curl);

        return json_decode($curl_response, true);
    }

    private function get_access_token() {
        $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        $credentials = base64_encode($this->get_option('consumer_key') . ':' . $this->get_option('consumer_secret'));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $curl_response = curl_exec($curl);
        curl_close($curl);

        $response = json_decode($curl_response);
        return $response->access_token;
    }

    public function process_payment($order_id) {
         //global $woocommerce;
        $order = wc_get_order($order_id);
        $phone = $order->get_billing_phone();

        // Remove any non-digit characters from the phone number
        $phone = preg_replace('/\D/', '', $phone);

        // Ensure the phone number starts with 254
        if (substr($phone, 0, 3) !== '254') {
            $phone = '254' . substr($phone, -9);
        }

        $amount = $order->get_total();
        $account_reference = 'CLADSHATI_' . $order_id;
        $transaction_desc = 'Payment for order ' . $order_id;

        $callback_url = home_url('wc-api/cladshati_mpesa');

        $result = $this->initiate_stk_push($phone, $amount, $account_reference, $transaction_desc, $callback_url);

        if ($result['ResponseCode'] === '0') {
            // STK push initiated successfully
            $order->update_status('on-hold', __('Awaiting M-Pesa payment', 'cladshati-pay'));
            $order->add_order_note('M-Pesa STK push initiated. CheckoutRequestID: ' . $result['CheckoutRequestID']);
            WC()->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        } else {
            wc_add_notice('M-Pesa payment failed. ' . $result['ResponseDescription'], 'error');
            return;
        }
    }

    // private function initiate_stk_push($phone, $amount, $account_reference, $transaction_desc, $callback_url) {
    //     $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    //     $access_token = $this->get_access_token();

    //     $timestamp = date('YmdHis');
    //     $password = base64_encode($this->get_option('shortcode') . $this->get_option('passkey') . $timestamp);

    //     $curl_post_data = array(
    //         'BusinessShortCode' => $this->get_option('shortcode'),
    //         'Password' => $password,
    //         'Timestamp' => $timestamp,
    //         'TransactionType' => 'CustomerPayBillOnline',
    //         'Amount' => $amount,
    //         'PartyA' => $phone,
    //         'PartyB' => $this->get_option('shortcode'),
    //         'PhoneNumber' => $phone,
    //         'CallBackURL' => $callback_url,
    //         'AccountReference' => $account_reference,
    //         'TransactionDesc' => $transaction_desc
    //     );

    //     $headers = array(
    //         'Authorization: Bearer ' . $access_token,
    //         'Content-Type: application/json'
    //     );

    //     $curl = curl_init();
    //     curl_setopt($curl, CURLOPT_URL, $url);
    //     curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    //     curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    //     curl_setopt($curl, CURLOPT_POST, true);
    //     curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curl_post_data));

    //     $curl_response = curl_exec($curl);
    //     curl_close($curl);

    //     return json_decode($curl_response, true);
    // }

    // private function get_access_token() {
    //     $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    //     $curl = curl_init();
    //     curl_setopt($curl, CURLOPT_URL, $url);
    //     $credentials = base64_encode($this->get_option('consumer_key') . ':' . $this->get_option('consumer_secret'));
    //     curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
    //     curl_setopt($curl, CURLOPT_HEADER, false);
    //     curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    //     curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    //     $curl_response = curl_exec($curl);
    //     curl_close($curl);

    //     $response = json_decode($curl_response);
    //     return $response->access_token;
    // }

    public function callback() {
        $callbackJSONData = file_get_contents('php://input');
        $callbackData = json_decode($callbackJSONData);

        $resultCode = $callbackData->Body->stkCallback->ResultCode;
        $resultDesc = $callbackData->Body->stkCallback->ResultDesc;
        $merchantRequestID = $callbackData->Body->stkCallback->MerchantRequestID;
        $checkoutRequestID = $callbackData->Body->stkCallback->CheckoutRequestID;

        // Find the order by MerchantRequestID (which we set as CLADSHATI_{order_id} earlier)
        $order_id = str_replace('CLADSHATI_', '', $merchantRequestID);
        $order = wc_get_order($order_id);

        if ($order) {
            if ($resultCode == 0) {
                // Payment successful
                $order->payment_complete();
                $order->add_order_note('M-Pesa payment successful. Transaction ID: ' . $checkoutRequestID);
            } else {
                // Payment failed
                $order->update_status('failed', 'M-Pesa payment failed: ' . $resultDesc);
            }
        }

        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Callback received successfully']);
        exit;
    }
}