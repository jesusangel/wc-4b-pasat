<?php
/*  Copyright 2013  Jesús Ángel del Pozo Domínguez  (email : jesusangel.delpozo@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 3, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Plugin Name: Pasarela de pago 4B PASAT Internet para WooCommerce
 * Plugin URI: http://tel.abloque.com/4b_pasat.html
 * Description: Pasarela de pago 4B PASAT Internet para WooCommerce
 * Version: 0.2
 * Author: Jesús Ángel del Pozo Domínguez
 * Author URI: http://tel.abloque.com
 * License: GPL3
 *
 * Text Domain: wc_4b_pasat_payment_gateway
 * Domain Path: /languages/
 *
 */

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	add_action('plugins_loaded', 'init_wc_4b_pasat_payment_gateway', 0);
	
	function init_wc_4b_pasat_payment_gateway() {
	 
	    if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }
	    
		/**
		 * 4B-Pasat Standard Payment Gateway
		 *
		 * Provides a 4B-Pasat Standard Payment Gateway.
		 *
		 * @class 		WC_4B_PASAT
		 * @extends		WC_Payment_Gateway
		 * @version		0.1
		 * @package		
		 * @author 		Jesús Ángel del Pozo Domínguez
		 */
		   
		class WC_4B_PASAT extends WC_Payment_Gateway {
			
			var $notify_url;
		
		    /**
		     * Constructor for the gateway.
		     *
		     * @access public
		     * @return void
		     */
			public function __construct() {
				global $woocommerce;
		
				$this->id				= '4b_pasat';
				$this->icon 			= home_url() . '/wp-content/plugins/' . dirname( plugin_basename( __FILE__ ) ) . '/assets/images/icons/cuatrob.png'; 
				$this->has_fields 		= false;
				$this->liveurl 			= 'https://tpv.4b.es/tpvv/teargral.exe';
				$this->testurl 			= 'https://tpv2.4b.es/simulador/teargral.exe';
				$this->method_title     = __( '4B PASAT Internet', 'wc_4b_pasat_payment_gateway' );
				$this->method_description = __( 'Pay with credit card using 4B PASAT Internet', 'wc_4b_pasat_payment_gateway' );
				$this->notify_url		= str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_4B_PASAT', home_url( '/' ) ) );
				$this->details_url		= add_query_arg( 'details', 1, $this->notify_url );
				$this->authorized_url	= add_query_arg( 'authorized', 1, $this->notify_url );
				$this->denied_url		= add_query_arg( 'authorized', 0, $this->notify_url );
	
		        // Set up localisation
	            $this->load_plugin_textdomain();
	                
				// Load the form fields.
				$this->init_form_fields();
		
				// Load the settings.
				$this->init_settings();
		
				// Define user set variables
				$this->title			= $this->settings['title'];
				$this->description		= $this->settings['description'];
				$this->owner_name		= $this->settings['owner_name'];
				$this->store			= $this->settings['store'];
				$this->testmode			= $this->settings['testmode'];
				$this->currency_id		= $this->settings['currency_id'];
				$this->language			= $this->settings['language'];
				$this->testmode			= $this->settings['testmode'];
				$this->debug			= $this->settings['debug'];	
				$this->ips_simulation	= explode( ',', $this->settings['ips_simulation'] );
				$this->ips_active		= explode( ',', $this->settings['ips_active'] );		
		
				// Logs
				if ( 'yes' == $this->debug )
					$this->log = $woocommerce->logger();
		
				// Actions
				if ( version_compare( WOOCOMMERCE_VERSION, '2.0', '<' ) ) {
					// Check for gateway messages using WC 1.X format
					add_action( 'init', array( $this, 'check_notification' ) );
					add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
				} else {
					// Payment listener/API hook (WC 2.X) 
					add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_notification' ) );
					add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				}
				add_action('valid-4b_pasat-standard-notification', array( $this, 'successful_request' ) );
				add_action('woocommerce_receipt_4b_pasat', array( $this, 'receipt_page' ) );
				
				if ( !$this->is_valid_for_use() ) $this->enabled = false;
		    }
		    
			/**
	         * Localisation.
	         *
	         * @access public
	         * @return void
	         */
	        function load_plugin_textdomain() {
                // Note: the first-loaded translation file overrides any following ones if the same translation is present
                $locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce' );
                $variable_lang = ( get_option( 'woocommerce_informal_localisation_type' ) == 'yes' ) ? 'informal' : 'formal';
                load_textdomain( 'wc_4b_pasat_payment_gateway', WP_LANG_DIR.'/wc_4b_pasat_payment_gateway/wc_4b_pasat_payment_gateway-'.$locale.'.mo' );
                load_plugin_textdomain( 'wc_4b_pasat_payment_gateway', false, dirname( plugin_basename( __FILE__ ) ).'/languages/'.$variable_lang );
                load_plugin_textdomain( 'wc_4b_pasat_payment_gateway', false, dirname( plugin_basename( __FILE__ ) ).'/languages' );
	        }
		
		
		    /**
		     * Check if this gateway is enabled and available in the user's country
		     *
		     * @access public
		     * @return bool
		     */
		    function is_valid_for_use() {
		        //if (!in_array(get_woocommerce_currency(), array('AUD', 'BRL', 'CAD', 'MXN', 'NZD', 'HKD', 'SGD', 'USD', 'EUR', 'JPY', 'TRY', 'NOK', 'CZK', 'DKK', 'HUF', 'ILS', 'MYR', 'PHP', 'PLN', 'SEK', 'CHF', 'TWD', 'THB', 'GBP', 'RMB'))) return false;
		
		        return true;
		    }
		
			/**
			 * Admin Panel Options
			 * - Options for bits like 'title' and availability on a country-by-country basis
			 *
			 * @since 1.0.0
			 */
			public function admin_options() {
		
		    	?>
		    	<h3><?php _e('4B-Pasat', 'wc_4b_pasat_payment_gateway'); ?></h3>
		    	<p><?php _e('4B-Pasat works by sending the user to 4B-Pasat to enter their payment information.', 'wc_4b_pasat_payment_gateway'); ?></p>
	
		    	<?php if ( $this->is_valid_for_use() ) : ?>
					<table class="form-table">
					<?php 
		    			// Generate the HTML For the settings form.
		    			$this->generate_settings_html();
		    		?>
		    		</table><!--/.form-table-->
				<?php else : ?>
		            <div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'wc_4b_pasat_payment_gateway' ); ?></strong>: <?php _e( '4B-Pasat does not support your store currency.', 'wc_4b_pasat_payment_gateway' ); ?></p></div>
		        <?php
		        	endif;
		    }
		
		
		    /**
		     * Initialise Gateway Settings Form Fields
		     *
		     * @access public
		     * @return void
		     */
		    function init_form_fields() {
		
		    	$this->form_fields = array(
					'enabled' => array(
									'title' => __( 'Enable/Disable', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'checkbox',
									'label' => __( 'Enable 4B-Pasat', 'wc_4b_pasat_payment_gateway' ),
									'default' => 'yes'
								),
					'title' => array(
									'title' => __( 'Title', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'text',
									'description' => __( 'This controls the title which the user sees during checkout.', 'wc_4b_pasat_payment_gateway' ),
									'default' => __( '4B-Pasat', 'wc_4b_pasat_payment_gateway' )
								),
					'owner_name' => array(
									'title' => __( 'Owner name', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'text',
									'description' => __( 'Name and surname of the owner.', 'wc_4b_pasat_payment_gateway' ),
									'default' => ''
								),
					'commerce_name' => array(
									'title' => __( 'Commerce name', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'text',
									'description' => __( 'The commerce name.', 'wc_4b_pasat_payment_gateway' ),
									'default' => ''
								),
					'description' => array(
									'title' => __( 'Description', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'textarea',
									'description' => __( 'This controls the description which the user sees during checkout.', 'wc_4b_pasat_payment_gateway' ),
									'default' => __("Pay with your credit card via 4B-Pasat", 'wc_4b_pasat_payment_gateway')
								),
					'store' => array(
									'title' => __( 'Store code', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'text',
									'description' => __( 'Please enter your store code. You\'ll find it in 4B gateway', 'wc_4b_pasat_payment_gateway' ),
									'default' => ''
								),
					'ips_simulation' => array(
									'title' => __( 'Allowed IPs (simulation mode)', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'text',
									'description' => __( 'Enter allowed IPs for notifications. Separate them using commas', 'wc_4b_pasat_payment_gateway' ),
									'default' => '194.224.159.57'
								),
					'ips_active' => array(
									'title' => __( 'Allowed IPs (active mode)', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'text',
									'description' => __( 'Enter allowed IPs for notifications. Separate them using commas', 'wc_4b_pasat_payment_gateway' ),
									'default' => '194.224.159.47'
								),
					'language' => array(
									'title' => __( 'Language', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'select',
									'description' => __( 'Please enter the gateway language.', 'wc_4b_pasat_payment_gateway' ),
									'options' => array('es' => 'Español', 'ca' => 'Catalán', 'en' => 'Inglés', 'de' => 'Alemán', 'fr' => 'Francés'),
									'default' => 'es'
								),			
					'currency_id' => array(
									'title' => __( 'Currency identifier', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'select',
									'description' => __( 'Please enter your 4B-Pasat currency identifier; this is needed in order to take payment.', 'wc_4b_pasat_payment_gateway' ),
									'options' => array('978' => 'EUR (Euro)', '840' => 'USD (US Dollar)', '826' => 'GBP (British Pound)', '392' => 'JPY (Japanesse Yen)'),
									'default' => '978'
								),
					'urls' => array(
									'title' => __( 'URLs', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'title',
									'description' => __( 'You should copy this URLs and paste them into 4B gateway configuration. Do not edit them!', 'wc_4b_pasat_payment_gateway' )
								),
					'home_url' => array(
									'title' => __( 'Home URL', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'text',
									'description' => __( 'Do not edit this field! Copy and paste it in 4B configuration', 'wc_4b_pasat_payment_gateway' ),
									'default' => home_url( '/' )
								),
					'details_url' => array(
									'title' => __( 'Order details URL', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'text',
									'description' => __( 'Do not edit this field! Copy and paste it in 4B configuration', 'wc_4b_pasat_payment_gateway' ),
									'default' => $this->details_url
								),
					'authorized_url' => array(
									'title' => __( 'Completed (atuhorized) URL', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'text',
									'description' => __( 'Do not edit this field! Copy and paste it in 4B configuration', 'wc_4b_pasat_payment_gateway' ),
									'default' => $this->authorized_url
								),
					'denied_url' => array(
									'title' => __( 'Not completed (denied) URL', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'text',
									'description' => __( 'Do not edit this field! Copy and paste it in 4B configuration', 'wc_4b_pasat_payment_gateway' ),
									'default' => $this->denied_url
								),
					'testing' => array(
									'title' => __( 'Gateway Testing', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'title',
									'description' => '',
								),
					'testmode' => array(
									'title' => __( '4B-Pasat sandbox', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'checkbox',
									'label' => __( 'Enable 4B-Pasat sandbox', 'wc_4b_pasat_payment_gateway' ),
									'default' => 'yes',
									'description' => sprintf( __( '4B-Pasat sandbox can be used to test payments.', 'wc_4b_pasat_payment_gateway' ) ),
								),
					'debug' => array(
									'title' => __( 'Debug Log', 'wc_4b_pasat_payment_gateway' ),
									'type' => 'checkbox',
									'label' => __( 'Enable logging', 'wc_4b_pasat_payment_gateway' ),
									'default' => 'no',
									'description' => __( 'Log 4B-Pasat events, inside <code>woocommerce/logs/4b_pasat.txt</code>' ),
								)
					);
		    }

		    /**
		     * 
		     * Checks if source IP is allowed 
		     */
		    private function _check_source_ip() {
		    	if ( 'yes' == $this->debug ) {
		    		$ips = $this->ips_simulation;
		    	} else {
		    		$ips = $this->ips_active;
		    	}
		    	
		    	if ( !is_array($ips) || 0 == count( $ips ) ) {
		    		return true;
		    	}
		    	
		    	if ( 'yes' == $this->debug )
					$this->log->add( '4b_pasat', 'Comprobando IPs autorizadas: '. join( ', ', $ips ) );
		    	
		    	if ( in_array( $_SERVER['REMOTE_ADDR'], array_map('trim', $ips) ) ) {
		    		return true;
	    		} else {
	    			return false;
	    		}
		    }
		
			/**
			 * Get 4B-Pasat Args for passing to the TPV server
			 *
			 * @access public
			 * @param mixed $order
			 * @return array
			 */
			function get_4b_pasat_init_args( $order ) {
				global $woocommerce;
		
				$order_id = $order->id;
		
				if ( 'yes' == $this->debug )
					$this->log->add( '4b_pasat', 'Conectando con la pasarela 4B para el pedido #' . $order_id . '. URL de notificación: ' . $this->notify_url );
				
				// 4B-Pasat Args
				$wc_4b_pasat_args = array(
					'order'		=> str_pad($order_id, 4, '0', STR_PAD_LEFT),	// 12 / num{4}char{8}
					'store'		=> $this->store,								// Store key
					'idioma'	=> $this->language								// es, ca, en, fr, o de
				);
				
			
				$wc_4b_pasat_args = apply_filters( 'woocommerce_4b_pasat_args', $wc_4b_pasat_args );
		
				return $wc_4b_pasat_args;
			}

			/*
			function get_4b_pasat_digest($wc_4b_pasat_args) {			
				if ( $this->extended_sha1_algorithm == 'yes' ) {
					$string = "{$wc_4b_pasat_args['Ds_Merchant_Amount']}{$wc_4b_pasat_args['Ds_Merchant_Order']}{$wc_4b_pasat_args['Ds_Merchant_MerchantCode']}{$wc_4b_pasat_args['Ds_Merchant_Currency']}{$wc_4b_pasat_args['Ds_Merchant_TransactionType']}{$wc_4b_pasat_args['Ds_Merchant_MerchantURL']}{$this->secret_key}";
					$digest = sha1( $string );
				} else {
					$string = "{$wc_4b_pasat_args['Ds_Merchant_Amount']}{$wc_4b_pasat_args['Ds_Merchant_Order']}{$wc_4b_pasat_args['Ds_Merchant_MerchantCode']}{$wc_4b_pasat_args['Ds_Merchant_Currency']}{$this->secret_key}";
					$digest = sha1( $string );
				}
				
				if ( 'yes' == $this->debug )
					$this->log->add('4b_pasat', sprintf( __( 'Digest calculation for %s is %s.', 'wc_4b_pasat_payment_gateway' ), $string, $digest ) );
				
				return $digest;
			}
			*/
		
		    /**
			 * Generate the 4b_pasat button link
		     *
		     * @access public
		     * @param mixed $order_id
		     * @return string
		     */
		    function generate_4b_pasat_form( $order_id ) {
				global $woocommerce;
		
				$order = new WC_Order( $order_id );
		
				if ( $this->testmode == 'yes' ):
					$wc_4b_pasat_adr = $this->testurl . '?test=1&';
				else :
					$wc_4b_pasat_adr = $this->liveurl . '?';
				endif;
		
				$wc_4b_pasat_args = $this->get_4b_pasat_init_args( $order );
				
				if ( 'yes' == $this->debug )
					$this->log->add( '4b_pasat', 'Enviando datos iniciales a 4B-Pasat ' . print_r( $wc_4b_pasat_args, true ));
		
				$wc_4b_pasat_args_array = array();
		
				foreach ($wc_4b_pasat_args as $key => $value) {
					$wc_4b_pasat_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
				}
		
				$woocommerce->add_inline_js('
					jQuery("body").block({
							message: "<img src=\"' . esc_url( apply_filters( 'woocommerce_ajax_loader_url', $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif' ) ) . '\" alt=\"Redirecting&hellip;\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to 4B-Pasat to make payment.', 'wc_4b_pasat_payment_gateway').'",
							overlayCSS:
							{
								background: "#fff",
								opacity: 0.6
							},
							css: {
						        padding:        20,
						        textAlign:      "center",
						        color:          "#555",
						        border:         "3px solid #aaa",
						        backgroundColor:"#fff",
						        cursor:         "wait",
						        lineHeight:		"32px"
						    }
						});
					jQuery("#submit_4b_pasat_payment_form").click();
				');
		
				return '<form action="'.esc_url( $wc_4b_pasat_adr ).'" method="post" id="4b_pasat_payment_form" target="_top">
						' . implode('', $wc_4b_pasat_args_array) . '
						<input type="submit" class="button-alt" id="submit_4b_pasat_payment_form" value="'.__('Pay via 4B-Pasat', 'wc_4b_pasat_payment_gateway').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'wc_4b_pasat_payment_gateway').'</a>
					</form>';
		
			}
		
		
		    /**
		     * Process the payment and return the result
		     *
		     * @access public
		     * @param int $order_id
		     * @return array
		     */
			function process_payment( $order_id ) {
		
				$order = new WC_Order( $order_id );
		
				/*
				if ( ! $this->form_submission_method ) {
		
					$wc_4b_pasat_args = $this->get_4b_pasat_args( $order );
		
					$wc_4b_pasat_args = http_build_query( $wc_4b_pasat_args, '', '&' );
		
					if ( $this->testmode == 'yes' ):
						$wc_4b_pasat_adr = $this->testurl . '?test=1&';
					else :
						$wc_4b_pasat_adr = $this->liveurl . '?';
					endif;
		
					return array(
						'result' 	=> 'success',
						'redirect'	=> $wc_4b_pasat_adr . $wc_4b_pasat_args
					);
		
				} else {
				*/
		
				return array(
					'result' 	=> 'success',
					'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
				);
		/*
				}
				*/
		
			}
		
		
		    /**
		     * Output for the order received page.
		     *
		     * @access public
		     * @return void
		     */
			function receipt_page( $order ) {
		
				echo '<p>'.__('Thank you for your order, please click the button below to pay with 4B-Pasat.', 'wc_4b_pasat_payment_gateway').'</p>';
		
				echo $this->generate_4b_pasat_form( $order );
		
			}
		
			/**
			 * Check 4B-Pasat notification
			 **/
			function check_payment_notification() {
				global $woocommerce;
		
				if ( 'yes' == $this->debug )
					$this->log->add( '4b_pasat', 'Checking notification is valid...' );
		
		    	// Get received values from post data
				$received_values = (array) stripslashes_deep( $_GET );
			
		        if ( 'yes' == $this->debug )
		        	$this->log->add( '4b_pasat', 'Received data: ' . print_r($received_values, true) );
		        
				// 	Check for the store code
	        	if ( $received_values['store'] != $this->store ) {
	        		if ( 'yes' == $this->debug )
	        			$this->log->add( '4b_pasat', "Received store code does not match" );
	        		
	        		return false;
	        	}
	        	
				// Check for allowed IPs
	        	if ( false == $this->_check_source_ip() ) {
	        		if ( 'yes' == $this->debug )
	        			$this->log->add( '4b_pasat', "Source IP {$_SERVER['REMOTE_ADDR']} not allowed" );
	        			
	        		return false;
	        	}
		        	        	
	        	// TODO: check mac
		        /*
		        $string = "{$received_values['Ds_Amount']}{$received_values['Ds_Order']}{$received_values['Ds_MerchantCode']}{$received_values['Ds_Currency']}{$received_values['Ds_Response']}{$this->secret_key}";
		        $digest = sha1( $string ); 
		
		        // check to see if the response is valid
		        if ( strcasecmp ( $digest, $received_values['Ds_Signature'] ) == 0 ) {
		            if ( 'yes' == $this->debug )
		            	$this->log->add( '4b_pasat', 'Received valid notification from 4B-Pasat' );
		            return true;
		        }
		        */
	        	
	        	return true;
		
		        /*
	        	if ( 'yes' == $this->debug )
		        	$this->log->add( '4b_pasat', "Received invalid notification from 4B-Pasat.\nString: {$string}\nDigest: {$digest}\nDs_Signature: {$received_values['Ds_Signature']}" );
		
		        return false;
		        */
		    }
		
		
			/**
			 * Send order details
			 *
			 * @access public
			 * @return void
			 */
			function get_order_details( $order_id, $store ) {
		
		        	$_GET = stripslashes_deep($_GET);
		        	
		        	// Check for the store code
		        	if ( $_GET['store'] != $this->store ) {
		        		if ( 'yes' == $this->debug )
		        			$this->log->add( '4b_pasat', "Received store code does not match" );
		        		
		        		return false;
		        	}
		        	
					// Check for allowed IPs
		        	if ( false == $this->_check_source_ip() ) {
		        		if ( 'yes' == $this->debug )
		        			$this->log->add( '4b_pasat', "Source IP {$_SERVER['REMOTE_ADDR']} not allowed" );
		        			
		        		return false;
		        	}
		        	
		        	// TODO: check mac

					$order = new WC_Order( (int) $order_id );

					$importe = $order->get_total();
					if ( $this->currency_id == 978 ) {
						$importe = $importe * 100;      // For Euros, last two digits are decimals
					}

					$return_string = "M{$this->currency_id}{$importe}\n";

					$items = $order->get_items();

					if ( $items_count = count($items) ) {
						$return_string .= "{$items_count}\n";
						foreach ( $items as $item ) {
							$importe_item = $item['line_subtotal'];
							if ( $this->currency_id == 978 ) {
								$importe_item = $importe_item * 100;      // For Euros, last two digits are decimals
							}

							$return_string .= "{$item['product_id']}\n{$item['name']}\n{$item['qty']}\n{$importe_item}\n";
						}
					} else {
						$return_string = "M{$this->currency_id}0000\n0";
					}

					return $return_string;
			}

			/**
			 * Check for 4B-Pasat notification
			 *
			 * @access public
			 * @return void
			 */
			function check_notification() {
		
				if ( isset($_GET['details']) && 1 == $_GET['details'] ) {
					if ( 'yes' == $this->debug )
		        		$this->log->add( '4b_pasat', "Received order details request.\nFrom: {$_SERVER['REMOTE_ADDR']}\nData:\n" . print_r($_GET, true) );
		        		
		        	$order_details = $this->get_order_details( $_GET['order'], $_GET['store'] );
		        	
		        	if ( 'yes' == $this->debug )
		        		$this->log->add( '4b_pasat', "Sending order details.\nData:\n{$order_details}" );
					
					// We send the output via die function (see WC_API->api_request)
		        	ob_end_clean();
					header('HTTP/1.1 200 OK');
					die( $order_details );
				} else if ( isset($_GET['authorized']) ) {
					@ob_clean();
		
	        		if ( $this->check_payment_notification() ) {
	        			header('HTTP/1.1 200 OK');
	            			do_action("valid-4b_pasat-standard-notification", $_GET);
					} else {
						wp_die("4B-Pasat notification Failure");
	       			}
	       		}
			}
		
			/**
			 * Successful Payment!
			 *
			 * @access public
			 * @param array $data
			 * @return void
			 */
			function successful_request( $data ) {
				global $woocommerce;
		
				// Ds_Order holds post ID
			    if ( !empty( $data['pszPurchorderNum'] ) ) {
		
					$order_id = (int) $data['pszPurchorderNum'];
					$order = new WC_Order( $order_id );
		
			        if ( $this->store != $data['store'] ) {
			        	if ( 'yes' == $this->debug ) $this->log->add( '4b_pasat', 'Error: store code does not match.' );
			        	exit;
			        }
		
			        if ( 'yes' == $this->debug )
			        	$this->log->add( '4b_pasat', 'Payment status: ' . $data['result'] . '. 0 -> authorized, 2 -> failed');
				      
			        // We are here so lets check status and do actions
			        $result = (int) $data['result'];
			        if ( $result == 0 ) {	// Authorized transaction
		
			            	// Check order not already completed
			            	if ($order->status == 'completed') :
			            		 if ( 'yes' == $this->debug ) $this->log->add( '4b_pasat', 'Aborting, Order #' . $order_id . ' is already complete.' );
			            		 exit;
			            	endif;
			            	
			            	// Store payment Details
			            	if ( ! empty( $data['pszTxnDate'] ) )
			            		update_post_meta( $order_id, 'Payment date', $data['pszTxnDate'] );
			            	if ( ! empty( $data['tipotrans'] ) )
			            		update_post_meta( $order_id, 'Transaction type', $data['tipotrans'] );
			            	if ( ! empty( $data['pszApprovalCode'] ) )
			            		update_post_meta( $order_id, 'Authorisation code', $data['pszApprovalCode'] );
			            	if ( ! empty( $data['pszTxnID'] ) )
			            		update_post_meta( $order_id, 'Transaction ID', $data['pszTxnID'] );

			            	// Payment completed
			            	$order->add_order_note( sprintf( __("4B-Pasat payment completed\nPayment date: %s\nTransaction type: %s\nAuthorisation code: %s\nTransaction ID: %s", 'wc_4b_pasat_payment_gateway'), $data['pszTxnDate'], $data['tipotrans'], $data['pszApprovalCode'], $data['pszTxnID']) );
			            	$order->payment_complete();

			            	if ( 'yes' == $this->debug )
			            		$this->log->add( '4b_pasat', 'Payment complete.' );
			        } else {
			        	// Order failed
			        	$message = sprintf( __( 'Payment via 4b_pasat failed with code %s (%s).', 'wc_4b_pasat_payment_gateway'), $data['coderror'], $data['deserror'] ); 
						$order->update_status('failed', $message );
						if ( 'yes' == $this->debug )
			            	$this->log->add( '4b_pasat', 'Payment failed.' );
			        }
			               
					exit;
			    }
			}
		}
	    
	    /**
		 * Add the gateway to WooCommerce
		 *
		 * @access public
		 * @param array $methods
		 * @package
		 * @return array
		 */
		function add_4b_pasat_gateway( $methods ) {
			$methods[] = 'WC_4B_PASAT';
			return $methods;
		}
		
		add_filter('woocommerce_payment_gateways', 'add_4b_pasat_gateway' );
	}
}
