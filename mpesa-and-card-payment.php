<?php
/*
Plugin Name: Mpesa & Card Payment Gateway
Description: A plugin to accept Mpesa and card (Visa, Mastercard) payments.
Version: 1.1
Author: Mike Mwanzi
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Include the Stripe PHP library
require_once('stripe-php/init.php');

// Hook to add a menu item for the plugin in the WordPress dashboard
add_action('admin_menu', 'mpesa_card_payment_gateway_menu');

function mpesa_card_payment_gateway_menu() {
    add_menu_page('Payment Gateway', 'Payments', 'manage_options', 'mpesa-card-payment-gateway', 'payment_gateway_settings_page');
}

function payment_gateway_settings_page() {
    ?>
    <div class="wrap">
        <h2>Payment Gateway Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('payment_gateway_settings');
            do_settings_sections('mpesa_card_payment_gateway');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'payment_gateway_settings');

function payment_gateway_settings() {
    // Mpesa settings
    register_setting('payment_gateway_settings', 'mpesa_consumer_key');
    register_setting('payment_gateway_settings', 'mpesa_consumer_secret');
    register_setting('payment_gateway_settings', 'mpesa_shortcode');
    register_setting('payment_gateway_settings', 'mpesa_passkey');

    // Stripe settings
    register_setting('payment_gateway_settings', 'stripe_publishable_key');
    register_setting('payment_gateway_settings', 'stripe_secret_key');

    add_settings_section('mpesa_settings_section', 'Mpesa API Settings', null, 'mpesa_card_payment_gateway');
    add_settings_section('stripe_settings_section', 'Stripe API Settings', null, 'mpesa_card_payment_gateway');

    add_settings_field('mpesa_consumer_key', 'Consumer Key', 'mpesa_consumer_key_callback', 'mpesa_card_payment_gateway', 'mpesa_settings_section');
    add_settings_field('mpesa_consumer_secret', 'Consumer Secret', 'mpesa_consumer_secret_callback', 'mpesa_card_payment_gateway', 'mpesa_settings_section');
    add_settings_field('mpesa_shortcode', 'Shortcode', 'mpesa_shortcode_callback', 'mpesa_card_payment_gateway', 'mpesa_settings_section');
    add_settings_field('mpesa_passkey', 'Passkey', 'mpesa_passkey_callback', 'mpesa_card_payment_gateway', 'mpesa_settings_section');

    add_settings_field('stripe_publishable_key', 'Stripe Publishable Key', 'stripe_publishable_key_callback', 'mpesa_card_payment_gateway', 'stripe_settings_section');
    add_settings_field('stripe_secret_key', 'Stripe Secret Key', 'stripe_secret_key_callback', 'mpesa_card_payment_gateway', 'stripe_settings_section');
}

function mpesa_consumer_key_callback() {
    $value = get_option('mpesa_consumer_key', '');
    echo '<input type="text" name="mpesa_consumer_key" value="' . esc_attr($value) . '" />';
}

function mpesa_consumer_secret_callback() {
    $value = get_option('mpesa_consumer_secret', '');
    echo '<input type="text" name="mpesa_consumer_secret" value="' . esc_attr($value) . '" />';
}

function mpesa_shortcode_callback() {
    $value = get_option('mpesa_shortcode', '');
    echo '<input type="text" name="mpesa_shortcode" value="' . esc_attr($value) . '" />';
}

function mpesa_passkey_callback() {
    $value = get_option('mpesa_passkey', '');
    echo '<input type="text" name="mpesa_passkey" value="' . esc_attr($value) . '" />';
}

function stripe_publishable_key_callback() {
    $value = get_option('stripe_publishable_key', '');
    echo '<input type="text" name="stripe_publishable_key" value="' . esc_attr($value) . '" />';
}

function stripe_secret_key_callback() {
    $value = get_option('stripe_secret_key', '');
    echo '<input type="text" name="stripe_secret_key" value="' . esc_attr($value) . '" />';
}

// Add Payment Processing Functions

//     Mpesa Payment Processing Function (already implemented):
//         We’ll use the mpesa_process_payment() function as provided earlier.



function mpesa_process_payment($amount, $phone_number) {
    $consumer_key = get_option('mpesa_consumer_key');
    $consumer_secret = get_option('mpesa_consumer_secret');
    $shortcode = get_option('mpesa_shortcode');
    $passkey = get_option('mpesa_passkey');
    
    $timestamp = date('YmdHis');
    $password = base64_encode($shortcode . $passkey . $timestamp);
    
    // Get the OAuth token
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $credentials = base64_encode($consumer_key . ':' . $consumer_secret);
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($curl);
    $result = json_decode($response);
    $access_token = $result->access_token;
    
    curl_close($curl);
    
    // Make the payment request
    $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    
    $curl_post_data = array(
        'BusinessShortCode' => $shortcode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone_number,
        'PartyB' => $shortcode,
        'PhoneNumber' => $phone_number,
        'CallBackURL' => home_url('/mpesa-callback'),
        'AccountReference' => 'TestPayment',
        'TransactionDesc' => 'Payment for testing'
    );
    
    $data_string = json_encode($curl_post_data);
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $access_token));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    return json_decode($response);
}

//  Stripe Payment Processing Function:
//  Add a function to process payments via Stripe:
function process_card_payment($amount, $token) {
    $stripe_secret_key = get_option('stripe_secret_key');
    
    \Stripe\Stripe::setApiKey($stripe_secret_key);
    
    try {
        $charge = \Stripe\Charge::create([
            'amount' => $amount * 100, // Amount in cents
            'currency' => 'usd',
            'description' => 'Payment Description',
            'source' => $token,
        ]);

        return $charge;

    } catch (\Stripe\Exception\CardException $e) {
        // Card was declined
        return $e->getError()->message;
    }
}

//Create a Payment Form for Both Mpesa and Card Payments
function combined_payment_form() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $payment_method = sanitize_text_field($_POST['payment_method']);
        $amount = sanitize_text_field($_POST['amount']);
        
        if ($payment_method === 'mpesa') {
            $phone_number = sanitize_text_field($_POST['phone_number']);
            $response = mpesa_process_payment($amount, $phone_number);
            
            if ($response->ResponseCode == '0') {
                echo '<p>Mpesa payment initiated successfully. Check your phone to complete the transaction.</p>';
            } else {
                echo '<p>Mpesa payment failed: ' . $response->errorMessage . '</p>';
            }
        } else if ($payment_method === 'card') {
            $token = sanitize_text_field($_POST['stripeToken']);
            $response = process_card_payment($amount, $token);
            
            if (is_string($response)) {
                echo '<p>Card payment failed: ' . $response . '</p>';
            } else {
                echo '<p>Card payment successful. Payment ID: ' . $response->id . '</p>';
            }
        }
    }
    
    $stripe_publishable_key = get_option('stripe_publishable_key');
    
    ?>
    <form method="post" id="payment-form">
        <label for="amount">Amount:</label>
        <input type="number" name="amount" required />
        
        <label for="payment_method">Payment Method:</label>
        <select name="payment_method" id="payment_method" onchange="togglePaymentFields()">
            <option value="mpesa">Mpesa</option>
            <option value="card">Card (Visa, Mastercard)</option>
        </select>
        
        <div id="mpesa-fields">
            <label for="phone_number">Phone Number:</label>
            <input type="text" name="phone_number" />
        </div>
        
        <div id="card-fields" style="display:none;">
            <div id="card-element"></div>
            <input type="hidden" name="stripeToken" id="stripeToken">
        </div>
        
        <button type="submit">Pay Now</button>
    </form>

    <script src="https://js.stripe.com/v3/"></script>
    <script>
        var stripe = Stripe('<?php echo $stripe_publishable_key; ?>');
        var elements = stripe.elements();
        var card = elements.create('card');
        card.mount('#card-element');

        function togglePaymentFields() {
            var method = document.getElementById('payment_method').value;
            if (method === 'mpesa') {
                document.getElementById('mpesa-fields').style.display = 'block';
                document.getElementById('card-fields').style.display = 'none';
            } else if (method === 'card') {
                document.getElementById('mpesa-fields').style.display = 'none';
                document.getElementById('card-fields').style.display = 'block';
            }
        }

        var form = document.getElementById('payment-form');
        form.addEventListener('submit', function(event) {
            if (document.getElementById('payment_method').value === 'card') {
                event.preventDefault();
                stripe.createToken(card).then(function(result) {
                    if (result.error) {
                        // Inform the user if there was an error.
                        console.error(result.error.message);
                    } else {
                        // Send the token to your server.
                        document.getElementById('stripeToken').value = result.token.id;
                        form.submit();
                    }
                });
            }
        });
    </script>
    <?php
}

add_shortcode('combined_payment_form', 'combined_payment_form');


?>
