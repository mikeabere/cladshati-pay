<?php
/**
 * Plugin Name: Cladshati-Pay
 * Plugin URI: http://example.com/cladshati-pay
 * Description: A WordPress plugin that integrates Paystack for card payments and Daraja API for Mpesa payments.
 * Version: 1.0
 * Author: Mike Mwanzi
 * Author URI: http://example.com
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin main class
class Cladshati_Pay {
    public function __construct() {
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
    }

    public function init() {
        // Load dependencies
        require_once plugin_dir_path(__FILE__) . 'includes/class-paystack-gateway.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-mpesa-gateway.php';

        // Add payment gateways
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));

        // Add settings link on plugin page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'settings_link'));

        // Enqueue frontend scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Add AJAX action for M-Pesa processing
        add_action('wp_ajax_cladshati_process_mpesa', array($this, 'process_mpesa_ajax'));
        add_action('wp_ajax_nopriv_cladshati_process_mpesa', array($this, 'process_mpesa_ajax'));
    }

    public function add_gateways($gateways) {
        $gateways[] = 'Cladshati_Paystack_Gateway';
        $gateways[] = 'Cladshati_Mpesa_Gateway';
        return $gateways;
    }

    public function settings_link($links) {
        $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function enqueue_scripts() {
        wp_enqueue_style('cladshati-pay-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', array(), '1.0.0');
        wp_enqueue_script('cladshati-pay-script', plugin_dir_url(__FILE__) . 'assets/js/script.js', array('jquery'), '1.0.0', true);

        // Localize script with necessary data
        wp_localize_script('cladshati-pay-script', 'cladshati_pay_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cladshati_mpesa_nonce'),
            'paystack_public_key' => $this->get_paystack_public_key(),
            'order_received_url' => wc_get_endpoint_url('order-received', '', wc_get_page_permalink('checkout'))
        ));
    }

    private function get_paystack_public_key() {
        $paystack_settings = get_option('woocommerce_cladshati_paystack_settings', array());
        return isset($paystack_settings['test_mode']) && $paystack_settings['test_mode'] === 'yes'
            ? $paystack_settings['test_public_key']
            : $paystack_settings['live_public_key'];
    }

    public function process_mpesa_ajax() {
        check_ajax_referer('cladshati_mpesa_nonce', 'nonce');

        $phone_number = isset($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : '';

        if (empty($phone_number)) {
            wp_send_json_error(array('message' => 'Phone number is required.'));
        }

        // Here you would typically call your M-Pesa gateway's process_payment method
        // For this example, we'll just simulate a successful request
        $success = true;

        if ($success) {
            wp_send_json_success(array('message' => 'M-Pesa request sent successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to process M-Pesa payment.'));
        }
    }
}

// Initialize the plugin
new Cladshati_Pay();



