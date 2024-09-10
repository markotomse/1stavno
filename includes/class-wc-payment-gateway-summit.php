<?php

/**
 * summit Payments Gateway.
 *
 * Provides a summit Payments Payment Gateway.
 *
 * @class       WC_Gateway_summit
 * @extends     WC_Payment_Gateway
 * @version     2.1.0
 * @package     WooCommerce/Classes/Payment
 */
class WC_Gateway_summit extends WC_Payment_Gateway {

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
		//$this->title              = $this->get_option( 'title' );
		$this->title              = $this->get_option('checkout_title');
		$this->description        = $this->get_option( 'description' );
		$this->api_key_production = $this->get_option( 'api_key_production' );
		$this->api_key_test 	  = $this->get_option( 'api_key_test' );
		$this->installments_size  = $this->get_option( 'installments_size');
		$this->testing            = $this->get_option( 'testing' );
		$this->widget_id          = $this->get_option( 'widget_id' );
		$this->instructions       = $this->get_option( 'instructions' );
		$this->displayCatalogPrices       = $this->get_option( 'display_catalog_prices' );
		$this->displayProductPrices       = $this->get_option( 'display_product_prices' );
		$this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
		$this->enable_for_virtual = $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes';

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'change_payment_complete_order_status' ), 10, 3 );

		// Customer Emails.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties() {
		$this->id                 = 'summit';
		$this->icon               = apply_filters( 'woocommerce_summit_icon', plugins_url('../assets/temp.png', __FILE__ ) );
		$this->method_title       = __( '1Stavno', 'summit-payments-domain' );
		$this->api_key            = __( 'Add API Key', 'summit-payments-domain' );
		$this->widget_id          = __( 'Add Widget ID', 'summit-payments-domain' );
		$this->method_description = __( 'Vtičnik, ki omogoča prejemanje plačil preko 1Stavno.', 'summit-payments-domain' );
		$this->has_fields         = false;
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'testing'            => array(
				'title'       => __( 'Testiranje', 'summit-payments-domain' ),
				'label'       => __( 'Kljukica pomeni uporabo testnega API ključa.', 'summit-payments-domain' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
			),
			'api_key_production'             => array(
				'title'       => __( 'Produkcijski API Key ', 'summit-payments-domain' ),
				'type'        => 'text',
				'description' => __( 'Vaš produkcijski API key.', 'summit-payments-domain' ),
				'desc_tip'    => true,
			),
			'api_key_test'             => array(
				'title'       => __( 'Testni API Key ', 'summit-payments-domain' ),
				'type'        => 'text',
				'description' => __( 'Vaš testni API key.', 'summit-payments-domain' ),
				'desc_tip'    => true,
			),
			'display_catalog_prices'            => array(
				'title'       => __( 'Prikaži razrezane cene na strani kategorije.', 'summit-payments-domain' ),
				'label'       => __( 'Kljukica pomeni, da bodo razrezane cene prikazane.', 'summit-payments-domain' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
			),'display_product_prices'            => array(
				'title'       => __( 'Prikaži razrezane cene na strani produkta.', 'summit-payments-domain' ),
				'label'       => __( 'Kljukica pomeni, da bodo razrezane cene prikazane.', 'summit-payments-domain' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
			),
			'installments_size' => array(
				'title'       => __( 'Velikost razrezanih cen (px)', 'summit-payments-domain' ),
				'type'        => 'number',
				'description' => __( 'Velikost razrezanih cen v pixlih.', 'summit-payments-domain' ),
				'desc_tip'    => true,
				'default'     => 14,
			),
			'checkout_title'        => array(
				'title'       => __( 'Naziv plačilne metode', 'summit-payments-domain' ),
				'type'        => 'text',
				'description' => __( '', 'summit-payments-domain' ),
				'default'     => __( 'Nakup na obroke', 'summit-payments-domain' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __( 'Opis', 'summit-payments-domain' ),
				'type'        => 'textarea',
				'description' => __( '', 'summit-payments-domain' ),
				'default'     => __( 'Obročna plačila z 1Stavno.', 'summit-payments-domain' ),
				'desc_tip'    => true,
			),
			
			'instructions'       => array(
				'title'       => __( 'Navodila', 'summit-payments-domain' ),
				'type'        => 'text',
				'description' => __( 'Povezava do navodil na 1Stavno.si.', 'summit-payments-domain' ),
				'default'     => __( 'https://1stavno.si', 'summit-payments-domain' ),
				'desc_tip'    => true,
			)
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
			if ( ! isset( $_REQUEST['section'] ) || 'summit' !== $_REQUEST['section'] ) {
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
			$options[ $method->get_method_title() ][ $method->id ] = sprintf( __( 'Any &quot;%1$s&quot; method', 'summit-payments-domain' ), $method->get_method_title() );

			foreach ( $zones as $zone ) {

				$shipping_method_instances = $zone->get_shipping_methods();

				foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

					if ( $shipping_method_instance->id !== $method->id ) {
						continue;
					}

					$option_id = $shipping_method_instance->get_rate_id();

					// Translators: %1$s shipping method title, %2$s shipping method id.
					$option_instance_title = sprintf( __( '%1$s (#%2$s)', 'summit-payments-domain' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

					// Translators: %1$s zone name, %2$s shipping method instance name.
					$option_title = sprintf( __( '%1$s &ndash; %2$s', 'summit-payments-domain' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'summit-payments-domain' ), $option_instance_title );

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
	/*
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $order->get_total() > 0 ) {
			$this->summit_payment_processing( $order );
		}
	}*/
	function process_payment( $order_id ) {
		global $woocommerce;
		$order = new WC_Order( $order_id );
		$creditAmount=$order->get_total();
		
		// Mark as on-hold (we're awaiting the cheque)
		$order->update_status('wc-on-hold');
	
		// Remove cart
		//$woocommerce->cart->empty_cart();

		$successPage=$this->get_return_url( $order );
		$successPage=str_replace('http://','https://',$successPage);
		$redirectPage = plugin_dir_url( __FILE__ );
		$redirectPage .= "subpages/redirectPage.php?id=$order->id";
		$redirectPage = str_replace("http://","https://",$redirectPage);
		$redirectPage = str_replace('includes/','',$redirectPage);
		$successPage = $redirectPage."&status=success";
		$failedPage = $redirectPage."&status=failed";



		if($this->testing == "yes"){
			$url = 'https://pktest.takoleasy.si';
			$authToken = $this->api_key_test;
			$production = "0";
		}elseif($this->testing == "no"){
			$url = 'https://pk.takoleasy.si';
			$authToken = $this->api_key_production;
			$production = "1";
		}

		$url .= "/webpayment/rest/v1/creditapi/getWebCreditLink/json";
		$body = array(
			'AuthToken'       => $authToken,
			'errorPage'       => $failedPage,
			'successPage'     => $successPage,
			'referenceNumber' => $order_id,
			'creditAmount'    => $creditAmount
			
		);
		
		$args = array(
			'body'        => $body,
			'timeout'     => '7',
			'redirection' => '5',
			'blocking'    => true,
			'headers'     => array(),
			'cookies'     => array(),
		);
		$response = wp_remote_post( $url,$args);
		//
		global $wpdb;
		$tableName = $wpdb->prefix . '1Stavno_additionalInfo';
		$data = array('orderID'=>$order_id,'additionalInfo'=>"0",'production'=>$production);
		$wpdb->insert($tableName,$data);
		
		
		//
		$obj = $response['body'];
		// Return thankyou redirect
		return array(
			'result' => 'success',
			'redirect' => json_decode($obj)->{'data'}->{'Url'}
		);
		//$this->get_return_url( $order )
		
		
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
	 * Change payment complete order status to completed for summit orders.
	 *
	 * @since  3.1.0
	 * @param  string         $status Current order status.
	 * @param  int            $order_id Order ID.
	 * @param  WC_Order|false $order Order object.
	 * @return string
	 */
	public function change_payment_complete_order_status( $status, $order_id = 0, $order = false ) {
		if ( $order && 'summit' === $order->get_payment_method() ) {
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
}