<?php
/**
 * Sparco Payment Gateway.
 *
 * Provides a Sparco Payment Gateway class.
 *
 * @class       WC_Gateway_Sparco
 * @extends     WC_Payment_Gateway
 * @version     0.1.0
 * @package     WooCommerce\Classes\Payment
 */
class WC_Gateway_Sparco extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		// Setup general properties.
		$this->setup_properties();

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Get settings.
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->pub_key        	  = $this->get_option( 'pub_key' );
		$this->sec_key            = $this->get_option( 'sec_key' );
		$this->instructions       = $this->get_option( 'instructions' );
		$this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
		$this->enable_for_virtual = $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes';

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'change_payment_complete_order_status' ), 10, 3 );

		// webhook
        add_action( 'woocommerce_api_wc_gateway_sparco', array( $this, 'check_response' ) );

        $this->sparco_api_base_url = 'https://checkout.sparco.io';

	}

	public function check_response() {

		if ( ( strtoupper( $_SERVER['REQUEST_METHOD'] ) != 'POST' ) ) {
			exit;
		}

		$body = file_get_contents( 'php://input' );

		if ( $this->isJSON( $body ) ) {
			$_POST = (array) json_decode( $body );
		}

		$res = [];

        $webhook_payload = wp_unslash( $_POST ); 

		try {
			$signature = new SparcoSignature($this->pub_key, $this->sec_key);
			$sig_verification_results = $signature->verify($webhook_payload);
			$webhook_payload_verified = $sig_verification_results['isVerified'];
			$res['webhook_payload_verified'] = $webhook_payload_verified;

			if($webhook_payload_verified){
				$merchant_ref = $webhook_payload['merchantReference'];

				$order_id = array_slice(explode('-',$merchant_ref), -1)[0];
				$order = wc_get_order( $order_id );
				
				$isError = $webhook_payload['isError'];
				$status = $webhook_payload['status'];

				if('PROCESSING' === $status){
					$order->update_status('processing', 'Payment processing. Via Webhook');
					$res['status'] = 'PROCESSING';
				}
				if('TXN_AUTH_SUCCESSFUL' === $status){
					$order->payment_complete();
					$res['status'] = 'PROCESSED';
				}
				if('TXN_AUTH_UNSUCCESSFUL' === $status){
					$order->update_status('failed', 'Payment failed. Via Webhook');
					$res['status'] = 'NOT_PROCESSED';
				}
				
			}
			// $payload['sig_verification_results'] = $sig_verification_results;
		} catch (Exception $e) {
			$res['errMsg'] = $e->getMessage();
		}

		wp_send_json($res, $status_code = 201);

	}

	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties() {
		$this->id                 = 'sparco';
		// $this->icon               = apply_filters( 'woocommerce_cod_icon', '' );
		$this->order_button_text = __( 'Proceed to Sparco', 'sparco-gateway' );
		$this->icon=apply_filters(
			'woocommerce_sparco_icon', plugins_url('../assets/logo-icon.png', __FILE__)
		);
		$this->method_title       = __( 'Sparco Payment Gateway', 'sparco-gateway' );
		$this->pub_key            = __( 'Add Sparco Public Key', 'sparco-gateway' );
		$this->sec_key       	  = __( 'Add Sparco Secret Key', 'sparco-gateway' );
		$this->method_description = __( 'Take payments via Visa, Mastercard, MTN, Airtel or Zamtel', 'sparco-gateway' );
		$this->supports           = array(
			'products'
		);
		$this->has_fields         = false;
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __( 'Enable/Disable', 'sparco-gateway' ),
				'label'       => __( 'Enable Sparco Payment Gateway', 'sparco-gateway' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'pub_key'              => array(
				'title'       => __( 'Public Key', 'sparco-gateway' ),
				'type'        => 'text',
				'description' => __( 'Add your Sparco Public Key.', 'sparco-gateway' ),
				'default'     => __( '', 'sparco-gateway' ),
				'desc_tip'    => true,
			),
			'sec_key'              => array(
				'title'       => __( 'Secret Key', 'sparco-gateway' ),
				'type'        => 'password',
				'description' => __( 'Add your Sparco Public Secret Key.', 'sparco-gateway' ),
				'default'     => __( '', 'sparco-gateway' ),
				'desc_tip'    => true,
			),
			'title'              => array(
				'title'       => __( 'Title', 'sparco-gateway' ),
				'type'        => 'text',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'sparco-gateway' ),
				'default'     => __( 'Sparco(Card & Mobile Money)', 'sparco-gateway' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __( 'Description', 'sparco-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your website.', 'sparco-gateway' ),
				'default'     => __( 'Pay with Visa, Mastercard or Mobile Money.', 'sparco-gateway' ),
				'desc_tip'    => true,
			),
			'instructions'       => array(
				'title'       => __( 'Instructions', 'sparco-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page.', 'sparco-gateway' ),
				'default'     => __( 'Pay with Visa, Mastercard or Mobile Money.', 'sparco-gateway' ),
				'desc_tip'    => true,
			),

			'enable_for_methods' => array(
				'title'             => __( 'Enable for shipping methods', 'sparco-gateway' ),
				'type'              => 'multiselect',
				'class'             => 'wc-enhanced-select',
				'css'               => 'width: 400px;',
				'default'           => '',
				'description'       => __( 'If Sparco is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'sparco-gateway' ),
				'options'           => $this->load_shipping_method_options(),
				'desc_tip'          => true,
				'custom_attributes' => array(
					'data-placeholder' => __( 'Select shipping methods', 'sparco-gateway' ),
				),
			),
			'enable_for_virtual' => array(
				'title'   => __( 'Accept for virtual orders', 'sparco-gateway' ),
				'label'   => __( 'Accept payments if the order is virtual', 'sparco-gateway' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			),

		);
	}

	/**
	 * Check If The Gateway Is Available For Use.
	 *
	 * @return bool
	 */
	public function is_available() {
		$order          = null;
		$needs_shipping = false;

		// Test if shipping is needed first.
		if ( WC()->cart && WC()->cart->needs_shipping() ) {
			$needs_shipping = true;
		} elseif ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
			$order    = wc_get_order( $order_id );

			// Test if order needs shipping.
			if ( 0 < count( $order->get_items() ) ) {
				foreach ( $order->get_items() as $item ) {
					$_product = $item->get_product();
					if ( $_product && $_product->needs_shipping() ) {
						$needs_shipping = true;
						break;
					}
				}
			}
		}

		$needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );

		// Virtual order, with virtual disabled.
		if ( ! $this->enable_for_virtual && ! $needs_shipping ) {
			return false;
		}

		// Only apply if all packages are being shipped via chosen method, or order is virtual.
		if ( ! empty( $this->enable_for_methods ) && $needs_shipping ) {
			$order_shipping_items            = is_object( $order ) ? $order->get_shipping_methods() : false;
			$chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

			if ( $order_shipping_items ) {
				$canonical_rate_ids = $this->get_canonical_order_shipping_item_rate_ids( $order_shipping_items );
			} else {
				$canonical_rate_ids = $this->get_canonical_package_rate_ids( $chosen_shipping_methods_session );
			}

			if ( ! count( $this->get_matching_rates( $canonical_rate_ids ) ) ) {
				return false;
			}
		}

		return parent::is_available();
	}

	/**
	 * Checks to see whether or not the admin settings are being accessed by the current request.
	 *
	 * @return bool
	 */
	private function is_accessing_settings() {
		if ( is_admin() ) {
			// phpcs:disable WordPress.Security.NonceVerification
			if ( ! isset( $_REQUEST['page'] ) || 'wc-settings' !== $_REQUEST['page'] ) {
				return false;
			}
			if ( ! isset( $_REQUEST['tab'] ) || 'checkout' !== $_REQUEST['tab'] ) {
				return false;
			}
			if ( ! isset( $_REQUEST['section'] ) || 'sparco' !== $_REQUEST['section'] ) {
				return false;
			}
			// phpcs:enable WordPress.Security.NonceVerification

			return true;
		}

		return false;
	}

	/**
	 * Loads all of the shipping method options for the enable_for_methods field.
	 *
	 * @return array
	 */
	private function load_shipping_method_options() {
		// Since this is expensive, we only want to do it if we're actually on the settings page.
		if ( ! $this->is_accessing_settings() ) {
			return array();
		}

		$data_store = WC_Data_Store::load( 'shipping-zone' );
		$raw_zones  = $data_store->get_zones();

		foreach ( $raw_zones as $raw_zone ) {
			$zones[] = new WC_Shipping_Zone( $raw_zone );
		}

		$zones[] = new WC_Shipping_Zone( 0 );

		$options = array();
		foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

			$options[ $method->get_method_title() ] = array();

			// Translators: %1$s shipping method name.
			$options[ $method->get_method_title() ][ $method->id ] = sprintf( __( 'Any &quot;%1$s&quot; method', 'sparco-gateway' ), $method->get_method_title() );

			foreach ( $zones as $zone ) {

				$shipping_method_instances = $zone->get_shipping_methods();

				foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

					if ( $shipping_method_instance->id !== $method->id ) {
						continue;
					}

					$option_id = $shipping_method_instance->get_rate_id();

					// Translators: %1$s shipping method title, %2$s shipping method id.
					$option_instance_title = sprintf( __( '%1$s (#%2$s)', 'sparco-gateway' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

					// Translators: %1$s zone name, %2$s shipping method instance name.
					$option_title = sprintf( __( '%1$s &ndash; %2$s', 'sparco-gateway' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'sparco-gateway' ), $option_instance_title );

					$options[ $method->get_method_title() ][ $option_id ] = $option_title;
				}
			}
		}

		return $options;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
	 * @return array $canonical_rate_ids    Rate IDs in a canonical format.
	 */
	private function get_canonical_order_shipping_item_rate_ids( $order_shipping_items ) {

		$canonical_rate_ids = array();

		foreach ( $order_shipping_items as $order_shipping_item ) {
			$canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
		}

		return $canonical_rate_ids;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
	 * @return array $canonical_rate_ids  Rate IDs in a canonical format.
	 */
	private function get_canonical_package_rate_ids( $chosen_package_rate_ids ) {

		$shipping_packages  = WC()->shipping()->get_packages();
		$canonical_rate_ids = array();

		if ( ! empty( $chosen_package_rate_ids ) && is_array( $chosen_package_rate_ids ) ) {
			foreach ( $chosen_package_rate_ids as $package_key => $chosen_package_rate_id ) {
				if ( ! empty( $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ] ) ) {
					$chosen_rate          = $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ];
					$canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
				}
			}
		}

		return $canonical_rate_ids;
	}

	/**
	 * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
	 *
	 * @since  3.4.0
	 *
	 * @param array $rate_ids Rate ids to check.
	 * @return boolean
	 */
	private function get_matching_rates( $rate_ids ) {
		// First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
		return array_unique( array_merge( array_intersect( $this->enable_for_methods, $rate_ids ), array_intersect( $this->enable_for_methods, array_unique( array_map( 'wc_get_string_before_colon', $rate_ids ) ) ) ) );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$redirect_url = $this->get_return_url( $order );
		if ( $order->get_total() > 0 ) {

            $sparco_redirect_url = $this->sparco_payment_processing( $order);
            if($sparco_redirect_url){
                $redirect_url = $sparco_redirect_url;
            }

		} 
	

		return array(
			'result'   => 'success',
			'redirect' => $redirect_url,
		);
		
	}

	// Custom Payment Processor
	private function sparco_payment_processing($order)
	{
			
			$sparco_redirect_url = null;
			$order_id = $order->get_data()['id'];

			$merchant_ref = 'WC-'  . uniqid() . '-' . $order_id;

			update_post_meta( $order_id, 'sparco_merchant_ref', $merchant_ref );
 
			$body = [
				'transactionName'=>"Order-$order_id",
				'amount'=>$order->get_total(),
				'currency'=>$order->get_currency(),
				'transactionReference'=> $merchant_ref,
				'customerFirstName'=>$order->get_billing_first_name(),
				'customerLastName'=>$order->get_billing_last_name(),
				'customerEmail'=>$order->get_billing_email(),
				'customerPhone'=>$order->get_billing_phone(),
				'customerAddr'=>$order->get_shipping_address_1(),
				'customerCity'=>$order->get_shipping_city(),
				'customerState'=>$order->get_shipping_state(),
				'customerCountryCode'=>$order->get_billing_country(),
				'customerPostalCode'=>'',
				'merchantPublicKey'=>$this->pub_key,
				'webhookUrl'=> WC()->api_request_url( 'WC_Gateway_Sparco' ),
				'returnUrl'=> $this->get_return_url($order),
				'autoReturn'=> true,

			];
			 
			$body = wp_json_encode( $body );
			 
			$options = [
				'body'        => $body,
				'headers'     => [
					'Content-Type' => 'application/json',
				],
				'timeout'     => 60,
				'redirection' => 5,
				'blocking'    => true,
				'data_format' => 'body',
            ];
            
            $sparco_check_out_api_url = $this->sparco_api_base_url . '/gateway/api/v1/checkout';
			 
			$response = wp_remote_post( $sparco_check_out_api_url, $options );
		
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();

				return "Something went wrong: $error_message";
			} 


			if(200 === wp_remote_retrieve_response_code($response)){

				$res = json_decode(wp_remote_retrieve_body( $response ));
                
                $sparco_redirect_url = $res->paymentUrl;

				if ($res->isError){
					$order->update_status('failed', 'Payment failed. Reasin 1.');
				}else{
					$order->update_status('on-hold', 'Waiting for payment');
				}

			}else{
				$order->update_status('failed', 'Payment failed. Reason 2.');
			}
			

			return $sparco_redirect_url;

		
	}



	/**
	 * Output for the order received page.
	 */
	public function thankyou_page() {
		if ( $this->instructions ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
		}
	}

	/**
	 * Change payment complete order status to completed for Sparco orders.
	 *
	 * @since  3.1.0
	 * @param  string         $status Current order status.
	 * @param  int            $order_id Order ID.
	 * @param  WC_Order|false $order Order object.
	 * @return string
	 */
	public function change_payment_complete_order_status( $status, $order_id = 0, $order = false ) {
		if ( $order && 'sparco' === $order->get_payment_method() ) {
			$status = 'completed';
		}
		return $status;
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin  Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
		}
	}


	public function isJSON( $string ) {
		return is_string( $string ) && is_array( json_decode( $string, true ) ) ? true : false;
	}
}