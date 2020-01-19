<?php
/*
 * Plugin Name: WooCommerce Plaid Payment Gateway
 * Plugin URI: https://github.com/sabiqali/plaid_payment_gateway
 * Description: Take Plaid payments from your store.
 * Author: Sabiq Chaudhary
 * Author URI: http://github.com/sabiqali
 * Version: 1.0.1
 *
 */

 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

add_filter( 'woocommerce_payment_gateways', 'plaid_payment_add_gateway_class' );
function plaid_payment_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_plaid_payment_Gateway'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'plaid_payment_init_gateway_class' );
function plaid_payment_init_gateway_class() {
 
	class WC_plaid_payment_Gateway extends WC_Payment_Gateway {
 
 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {
 
            $this->id = 'plaid_payment'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Plaid Payment Gateway';
            $this->method_description = 'Description of Plaid payment gateway'; // will be displayed on the options page
        
            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );
        
            // Method with all the options fields
            $this->init_form_fields();
        
            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            //$this->testmode = 'yes' === $this->get_option( 'testmode' );
            //$this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
            //$this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
        
            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        
            // We need custom JavaScript to obtain a token
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
        
            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
 
 		}
 
		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
 		public function init_form_fields(){
 
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Plaid Payment Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'yes'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Plaid Checkout',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with your Bank Account securely by connecting with Plaid',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'no',
                    'desc_tip'    => true,
                ),
                /*'test_publishable_key' => array(
                    'title'       => 'Test Publishable Key',
                    'type'        => 'text'
                ),
                'test_private_key' => array(
                    'title'       => 'Test Private Key',
                    'type'        => 'password',
                ),*/
                'client_id' => array(
                    'title'       => 'Live Client ID',
                    'type'        => 'text'
                ),
                'public_key' => array(
                    'title'       => 'Live Public Key',
                    'type'        => 'text'
                ),
                'private_key' => array(
                    'title'       => 'Live Private Key',
                    'type'        => 'password'
                )
            );
 
	 	}
 
		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {
 
            // ok, let's display some description before the payment form
            if ( $this->description ) {
                // you can instructions for test mode, I mean test card numbers etc.
                if ( $this->testmode ) {
                    $this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#" target="_blank" rel="noopener noreferrer">documentation</a>.';
                    $this->description  = trim( $this->description );
                }
                // display the description with <p> tags etc.
                echo wpautop( wp_kses_post( $this->description ) );
            }
        
            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
        
            // Add this action hook if you want your custom payment gateway to support it
            do_action( 'woocommerce_credit_card_form_start', $this->id );
        
            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            echo <button id="link-button">Link Account</button>
                <script 
                src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.2.3/jquery.min.js"></script>
                <script 
                src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>
                <script type="text/javascript">
                (function($) {
                var handler = Plaid.create({
                    clientName: 'Plaid Quickstart',
                    // Optional, specify an array of ISO-3166-1 alpha-2 country
                    // codes to initialize Link; European countries will have GDPR
                    // consent panel
                    countryCodes: ['US'],
                    env: 'sandbox',
                    // Replace with your public_key from the Dashboard
                    key: $this->public_key,
                    product: ['transactions', 'auth'],
                    // Optional, use webhooks to get transaction and error updates
                    webhook: 'https://requestb.in',
                    // Optional, specify a language to localize Link
                    language: 'en',
                    // Optional, specify userLegalName and userEmailAddress to
                    // enable all Auth features
                    userLegalName: 'Sabiq Chaudhary',
                    userEmailAddress: 'sabiq.work@gmail.com',
                    onLoad: function() {
                    // Optional, called when Link loads
                    },
                    onSuccess: function(public_token, metadata) {
                    // Send the public_token to your app server.
                    // The metadata object contains info about the institution the
                    // user selected and the account ID or IDs, if the
                    // Select Account view is enabled.
                    $.post('/get_access_token', {
                        public_token: public_token,
                    });
                    },
                    onExit: function(err, metadata) {
                    // The user exited the Link flow.
                    if (err != null) {
                        // The user encountered a Plaid API error prior to exiting.
                    }
                    // metadata contains information about the institution
                    // that the user selected and the most recent API request IDs.
                    // Storing this information can be helpful for support.
                    },
                    onEvent: function(eventName, metadata) {
                    // Optionally capture Link flow events, streamed through
                    // this callback as your users connect an Item to Plaid.
                    // For example:
                    // eventName = "TRANSITION_VIEW"
                    // metadata  = {
                    //   link_session_id: "123-abc",
                    //   mfa_type:        "questions",
                    //   timestamp:       "2017-09-14T14:42:19.350Z",
                    //   view_name:       "MFA",
                    // }
                    }
                });
                
                $('#link-button').on('click', function(e) {
                    handler.open();
                });
                })(jQuery);
                </script>
                <div class="clear"></div>';
        
            do_action( 'woocommerce_credit_card_form_end', $this->id );
        
            echo '<div class="clear"></div></fieldset>';
 
		}
 
		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
	 	/*public function payment_scripts() {
 
		...
 
	 	}*/
 
		/*
 		 * Fields validation, more in Step 5
		 */
		/*public function validate_fields() {
 
		...
 
		}*/
 
		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {
 
            global $woocommerce;
        
            // we need it to get any order detailes
            $order = wc_get_order( $order_id );
        
        
            /*
            * Array with parameters for API interaction
            */
            /*$args = array(
        
                ...
        
            );*/
        
            /*
            * Your API interaction could be built with wp_remote_post()
            */
            //$response = wp_remote_post( '{payment processor endpoint}', $args );

             // some notes to customer (replace true with false to make it private)
             $order->add_order_note( 'Hey, your order is processing! Thank you!', true );
        
             // Empty cart
             $woocommerce->cart->empty_cart();
 
             // Redirect to the thank you page
             return array(
                 'result' => 'success',
                 'redirect' => $this->get_return_url( $order )
             );
        
        
            /*if( !is_wp_error( $response ) ) {
        
                $body = json_decode( $response['body'], true );
        
                // it could be different depending on your payment processor
                if ( $body['response']['responseCode'] == 'APPROVED' ) {
        
                    // we received the payment
                    $order->payment_complete();
                    $order->reduce_order_stock();
        
                    // some notes to customer (replace true with false to make it private)
                    $order->add_order_note( 'Hey, your order is paid! Thank you!', true );
        
                    // Empty cart
                    $woocommerce->cart->empty_cart();
        
                    // Redirect to the thank you page
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url( $order )
                    );
        
                } else {
                    wc_add_notice(  'Please try again.', 'error' );
                    return;
                }
        
            } else {
                wc_add_notice(  'Connection error.', 'error' );
                return;
            }*/
 
	 	}
 
		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		/*public function webhook() {
 
		...
 
	 	}*/
 	}
}