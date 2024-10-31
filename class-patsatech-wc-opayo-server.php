<?php
/**
 * Plugin Name: PatSaTECH's Opayo Server Gateway for WooCommerce
 * Plugin URI: http://www.patsatech.com/
 * Description: WooCommerce Plugin for accepting payment through Opayo Server Gateway.
 * Version: 1.0.3
 * Author: PatSaTECH
 * Author URI: http://www.patsatech.com
 * Contributors: patsatech
 * Requires at least: 6.0
 * Tested up to: 6.4.3
 * WC requires at least: 6.0.0
 * WC tested up to: 8.2.2
 *
 * Text Domain: patsatech-wc-opayo-server
 * Domain Path: /lang/
 *
 * @package Opayo Server Gateway for WooCommerce
 * @author PatSaTECH
 */

add_action( 'plugins_loaded', 'patsatech_wc_opayo_server_init', 0 );

/**
 * Init
 *
 * @return void
 */
function patsatech_wc_opayo_server_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return; }

	load_plugin_textdomain( 'patsatech-wc-opayo-server', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );

	/**
	 * PatSaTECH_WC_Opayo_Server
	 */
	class PatSaTECH_WC_Opayo_Server extends WC_Payment_Gateway {

		/**
		 * __construct
		 *
		 * @return void
		 */
		public function __construct() {
			global $woocommerce;

			$this->id           = 'opayoserver';
			$this->method_title = esc_html__( 'Opayo Server', 'patsatech-wc-opayo-server' );
			$this->icon         = apply_filters( 'woocommerce_opayoserver_icon', '' );
			$this->has_fields   = false;
			$this->notify_url   = add_query_arg( 'wc-api', 'woocommerce_opayoserver', home_url( '/' ) );

			$default_card_type_options = array(
				'VISA' => 'VISA',
				'MC'   => 'MasterCard',
				'AMEX' => 'American Express',
				'DISC' => 'Discover',
				'DC'   => 'Diner\'s Club',
				'JCB'  => 'JCB Card',
			);

			$this->card_type_options = apply_filters( 'woocommerce_opayoserver_card_types', $default_card_type_options );

			// load form fields.
			$this->patsatech_wc_opayo_server_init_form_fields();

			// initialise settings.
			$this->init_settings();

			// variables.
			$this->title       = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->vendor_name = $this->settings['vendorname'];
			$this->mode        = $this->settings['mode'];
			$this->transtype   = $this->settings['transtype'];
			$this->paymentpage = $this->settings['paymentpage'];
			$this->iframe      = $this->settings['iframe'];
			$this->cardtypes   = $this->settings['cardtypes'];

			if ( 'test' === $this->mode ) {
				$this->gateway_url = 'https://sandbox.opayo.eu.elavon.com/gateway/service/vspserver-register.vsp';
			} elseif ( 'live' === $this->mode ) {
				$this->gateway_url = 'https://live.opayo.eu.elavon.com/gateway/service/vspserver-register.vsp';
			}

			// Actions.
			add_action( 'init', array( $this, 'patsatech_wc_opayo_server_successful_request' ) );
			add_action( 'woocommerce_api_woocommerce_opayoserver', array( $this, 'patsatech_wc_opayo_server_successful_request' ) );
			add_action( 'woocommerce_receipt_opayoserver', array( $this, 'patsatech_wc_opayo_server_receipt_page' ) );
			add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		}

		/**
		 * Get Icons
		 *
		 * @return string
		 */
		public function get_icon() {
			global $woocommerce;

			$icon = '';
			if ( $this->icon ) {
				// default behavior.
				$icon = '<img src="' . $this->patsatech_wc_opayo_server_force_ssl( $this->icon ) . '" alt="' . $this->title . '" />';
			} elseif ( $this->cardtypes ) {
				// display icons for the selected card types.
				$icon = '';
				foreach ( $this->cardtypes as $cardtype ) {
					if ( file_exists( plugin_dir_path( __FILE__ ) . '/images/card-' . strtolower( $cardtype ) . '.png' ) ) {
						$icon .= '<img src="' . $this->patsatech_wc_opayo_server_force_ssl( plugins_url( '/images/card-' . strtolower( $cardtype ) . '.png', __FILE__ ) ) . '" alt="' . strtolower( $cardtype ) . '" />';
					}
				}
			}

			return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
		}

		/**
		 * Admin Panel Options
		 **/
		public function admin_options() {
			?>
			<h3><?php esc_html_e( 'Opayo Server', 'patsatech-wc-opayo-server' ); ?></h3>
			<p><?php esc_html_e( 'Opayo Server works by processing Credit Cards on site. So users do not leave your site to enter their payment information.', 'patsatech-wc-opayo-server' ); ?></p>
			<table class="form-table">
			<?php
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
			?>
			</table><!--/.form-table-->
			<?php
		}

		/**
		 * Initialise Gateway Settings Form Fields
		 */
		public function patsatech_wc_opayo_server_init_form_fields() {

			// array to generate admin form.
			$this->form_fields = array(
				'enabled'     => array(
					'title'    => esc_html__( 'Enable/Disable', 'patsatech-wc-opayo-server' ),
					'type'     => 'checkbox',
					'label'    => esc_html__( 'Enable Opayo Server', 'patsatech-wc-opayo-server' ),
					'default'  => 'yes',
					'desc_tip' => true,
				),
				'title'       => array(
					'title'       => esc_html__( 'Title', 'patsatech-wc-opayo-server' ),
					'type'        => 'text',
					'description' => esc_html__( 'This is the title displayed to the user during checkout.', 'patsatech-wc-opayo-server' ),
					'default'     => esc_html__( 'Opayo Server', 'patsatech-wc-opayo-server' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => esc_html__( 'Description', 'patsatech-wc-opayo-server' ),
					'type'        => 'textarea',
					'description' => esc_html__( 'This is the description which the user sees during checkout.', 'patsatech-wc-opayo-server' ),
					'default'     => esc_html__( 'Payment via Opayo, Please enter your credit or debit card below.', 'patsatech-wc-opayo-server' ),
					'desc_tip'    => true,
				),
				'vendorname'  => array(
					'title'       => esc_html__( 'Vendor Name', 'patsatech-wc-opayo-server' ),
					'type'        => 'text',
					'description' => esc_html__( 'Please enter your vendor name provided by Opayo.', 'patsatech-wc-opayo-server' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'mode'        => array(
					'title'       => esc_html__( 'Mode Type', 'patsatech-wc-opayo-server' ),
					'type'        => 'select',
					'options'     => array(
						'test' => 'Test',
						'live' => 'Live',
					),
					'default'     => 'test',
					'description' => esc_html__( 'Select Test or Live modes.', 'patsatech-wc-opayo-server' ),
					'desc_tip'    => true,
				),
				'paymentpage' => array(
					'title'       => esc_html__( 'Payment Page Type', 'patsatech-wc-opayo-server' ),
					'type'        => 'select',
					'options'     => array(
						'LOW'    => 'LOW',
						'NORMAL' => 'NORMAL',
					),
					'default'     => 'low',
					'description' => esc_html__( 'This is used to indicate what type of payment page should be displayed. <br>LOW returns simpler payment pages which have only one step and minimal formatting. Designed to run in i-Frames. <br>NORMAL returns the normal card selection screen. We suggest you disable i-Frame if you select NORMAL.', 'patsatech-wc-opayo-server' ),
					'desc_tip'    => true,
				),
				'iframe'      => array(
					'title'       => esc_html__( 'Enable/Disable', 'patsatech-wc-opayo-server' ),
					'type'        => 'checkbox',
					'label'       => esc_html__( 'Enable i-Frame Mode', 'patsatech-wc-opayo-server' ),
					'default'     => 'yes',
					'description' => esc_html__( 'Make sure your site is SSL Protected before using this feature.', 'patsatech-wc-opayo-server' ),
					'desc_tip'    => true,
				),
				'transtype'   => array(
					'title'       => esc_html__( 'Transaction Type', 'patsatech-wc-opayo-server' ),
					'type'        => 'select',
					'options'     => array(
						'PAYMENT'      => esc_html__( 'Payment', 'patsatech-wc-opayo-server' ),
						'DEFFERRED'    => esc_html__( 'Deferred', 'patsatech-wc-opayo-server' ),
						'AUTHENTICATE' => esc_html__( 'Authenticate', 'patsatech-wc-opayo-server' ),
					),
					'description' => esc_html__( 'Select Payment, Deferred or Authenticated.', 'patsatech-wc-opayo-server' ),
					'desc_tip'    => true,
				),
				'cardtypes'   => array(
					'title'       => esc_html__( 'Accepted Cards', 'patsatech-wc-opayo-server' ),
					'class'       => 'wc-enhanced-select',
					'type'        => 'multiselect',
					'description' => esc_html__( 'Select which card types to accept.', 'patsatech-wc-opayo-server' ),
					'default'     => 'VISA',
					'options'     => $this->card_type_options,
					'desc_tip'    => true,
				),
			);
		}

		/**
		 * Process Payment
		 *
		 * @param  mixed $order_id // WooCommerce Order ID.
		 * @return array
		 */
		public function process_payment( $order_id ) {
			global $woocommerce;

			// Use wc_get_order to ensure compatibility with HPOS.
			$order = wc_get_order( $order_id );

			$time_stamp = gmdate( 'ymdHis' );
			$orderid    = $this->vendor_name . '-' . $time_stamp . '-' . $order_id;

			$basket = '';

			// Cart Contents
			$item_loop = 0;


			foreach ( $order->get_items() as $item_id => $item ) {
				$item_loop++;

				$product_id      = $item->get_product_id();
				$variation_id    = $item->get_variation_id();
				$product         = $item->get_product(); // Product object gives you access to all product data
				$product_name    = $item->get_name();
				$quantity        = $item->get_quantity();
				$subtotal        = $item->get_subtotal();
				$total           = $item->get_total();
				$tax_subtotal    = $item->get_subtotal_tax();
				$tax_class       = $item->get_tax_class();
				$tax_status      = $item->get_tax_status();
				$all_meta_data   = $item->get_meta_data();
				//$your_meta_data  = $item->get_meta( '_your_meta_key', true );
				$product_type    = $item->get_type();

				
				$item_cost = $item->get_subtotal()/$quantity;
				$item_total_inc_tax = 0;
				$item_total = $item->get_subtotal();
				//$item_sub_total =

				$item_tax = 0;
				if($item_loop > 1){
					$basket .= ':';
				}

				$sku              = $product ? $product->get_sku() : '';

				$basket .= str_replace(':',' = ',$sku).str_replace(':',' = ',$product_name).':'.$quantity.':'.$item_cost.':'.$item_tax.':'.number_format( $item_cost+$item_tax, 2, '.', '' ).':'.$item_total;

			 
			}



			// Fees
			if ( sizeof( $order->get_fees() ) > 0 ) {
				foreach ( $order->get_fees() as $order_item ) {
					$item_loop++;

					$basket .= ':'.str_replace(':',' = ',$order_item['name']).':1:'.$order_item['line_total'].':---:'.$order_item['line_total'].':'.$order_item['line_total'];
				}
			}

			// Shipping Cost item - paypal only allows shipping per item, we want to send shipping for the order
			if ( $order->get_total_shipping() > 0 ) {
				$item_loop++;

				$ship_exc_tax = number_format( $order->get_total_shipping(), 2, '.', '' );

				$basket .= ':'.__( 'Shipping via', 'woo-acceptpay' ) . ' ' . str_replace(':',' = ',ucwords( $order->get_shipping_method() )).':1:'.$ship_exc_tax.':'.$order->get_shipping_tax().':'.number_format( $ship_exc_tax+$order->get_shipping_tax(), 2, '.', '' ).':'.number_format( $order->get_total_shipping()+$order->get_shipping_tax(), 2, '.', '' );
			}

			// Discount
			if ( $order->get_total_discount() > 0 ){
				$item_loop++;

				$basket .= ':Discount:---:---:---:---:-'.$order->get_total_discount();
			}
			
			// Tax
			if ( $order->get_total_tax() > 0 ) {
				$item_loop++;

				$basket .= ':Tax:---:---:---:---:'.$order->get_total_tax();
			}

			$item_loop++;

			$basket .= ':Order Total:---:---:---:---:'.$order->get_total();

			$basket = $item_loop.':'.$basket;

			$sd_arg['Basket'] 					= $basket;

			$sd_arg['ReferrerID']        = 'CC923B06-40D5-4713-85C1-700D690550BF';
			$sd_arg['Amount']            = $order->get_total();
			$sd_arg['CustomerEMail']     = $order->get_billing_email();
			$sd_arg['BillingSurname']    = $order->get_billing_last_name();
			$sd_arg['BillingFirstnames'] = $order->get_billing_first_name();
			$sd_arg['BillingAddress1']   = $order->get_billing_address_1();
			$sd_arg['BillingAddress2']   = $order->get_billing_address_2();
			$sd_arg['BillingCity']       = $order->get_billing_city();

			if ( 'US' === $order->get_billing_country() ) {
				$sd_arg['BillingState'] = $order->get_billing_state();
			} else {
				$sd_arg['BillingState'] = '';
			}

			$sd_arg['BillingPostCode'] = $order->get_billing_postcode();
			$sd_arg['BillingCountry']  = $order->get_billing_country();
			$sd_arg['BillingPhone']    = $order->get_billing_phone();

			if ( true === $this->patsatech_wc_opayo_server_cart_has_virtual_product( $order ) ) {
				$sd_arg['DeliverySurname']    = $order->get_billing_last_name();
				$sd_arg['DeliveryFirstnames'] = $order->get_billing_first_name();
				$sd_arg['DeliveryAddress1']   = $order->get_billing_address_1();
				$sd_arg['DeliveryAddress2']   = $order->get_billing_address_2();
				$sd_arg['DeliveryCity']       = $order->get_billing_city();

				if ( 'US' === $order->get_billing_country() ) {
					$sd_arg['DeliveryState'] = $order->get_billing_state();
				} else {
					$sd_arg['DeliveryState'] = '';
				}

				$sd_arg['DeliveryPostCode'] = $order->get_billing_postcode();
				$sd_arg['DeliveryCountry']  = $order->get_billing_country();

			} else {
				$sd_arg['DeliverySurname']    = $order->get_shipping_last_name();
				$sd_arg['DeliveryFirstnames'] = $order->get_shipping_first_name();
				$sd_arg['DeliveryAddress1']   = $order->get_shipping_address_1();
				$sd_arg['DeliveryAddress2']   = $order->get_shipping_address_2();
				$sd_arg['DeliveryCity']       = $order->get_shipping_city();

				if ( 'US' === $order->get_billing_country() ) {
					$sd_arg['DeliveryState'] = $order->get_billing_state();
				} else {
					$sd_arg['DeliveryState'] = '';
				}

				$sd_arg['DeliveryPostCode'] = $order->get_shipping_postcode();
				$sd_arg['DeliveryCountry']  = $order->get_shipping_country();
			}

			// translators:Order Number.
			$sd_arg['Description']     = sprintf( esc_html__( 'Order #%s', 'patsatech-wc-opayo-server' ), $order->get_id() );
			$sd_arg['Currency']        = get_woocommerce_currency();
			$sd_arg['VPSProtocol']     = 3.00;
			$sd_arg['Vendor']          = $this->vendor_name;
			$sd_arg['TxType']          = $this->transtype;
			$sd_arg['VendorTxCode']    = $orderid;
			$sd_arg['Profile']         = $this->paymentpage;
			$sd_arg['NotificationURL'] = $this->notify_url;

			$post_values = '';
			foreach ( $sd_arg as $key => $value ) {
				$post_values .= "$key=" . rawurlencode( $value ) . '&';
			}
			$post_values = rtrim( $post_values, '& ' );

			$response = wp_remote_post(
				$this->gateway_url,
				array(
					'body'      => $post_values,
					'method'    => 'POST',
					'headers'   => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
					'sslverify' => false,
				)
			);

			if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
				$resp  = array();
				$lines = preg_split( '/\r\n|\r|\n/', $response['body'] );
				foreach ( $lines as $line ) {
						$key_value = preg_split( '/=/', $line, 2 );
					if ( count( $key_value ) > 1 ) {
						$resp[ trim( $key_value[0] ) ] = trim( $key_value[1] );
					}
				}

				if ( isset( $resp['Status'] ) ) {
					$order->update_meta_data( 'Status', sanitize_text_field( $resp['Status'] ) );
				}

				if ( isset( $resp['StatusDetail'] ) ) {
					$order->update_meta_data( 'StatusDetail', sanitize_text_field( $resp['StatusDetail'] ) );
				}

				if ( isset( $resp['VPSTxId'] ) ) {
					$order->update_meta_data( 'VPSTxId', sanitize_text_field( $resp['VPSTxId'] ) );
				}

				if ( isset( $resp['CAVV'] ) ) {
					$order->update_meta_data( 'CAVV', sanitize_text_field( $resp['CAVV'] ) );
				}

				if ( isset( $resp['SecurityKey'] ) ) {
					$order->update_meta_data( 'SecurityKey', sanitize_text_field( $resp['SecurityKey'] ) );
				}

				if ( isset( $resp['TxAuthNo'] ) ) {
					$order->update_meta_data( 'TxAuthNo', sanitize_text_field( $resp['TxAuthNo'] ) );
				}

				if ( isset( $resp['AVSCV2'] ) ) {
					$order->update_meta_data( 'AVSCV2', sanitize_text_field( $resp['AVSCV2'] ) );
				}

				if ( isset( $resp['AddressResult'] ) ) {
					$order->update_meta_data( 'AddressResult', sanitize_text_field( $resp['AddressResult'] ) );
				}

				if ( isset( $resp['PostCodeResult'] ) ) {
					$order->update_meta_data( 'PostCodeResult', sanitize_text_field( $resp['PostCodeResult'] ) );
				}

				if ( isset( $resp['CV2Result'] ) ) {
					$order->update_meta_data( 'CV2Result', sanitize_text_field( $resp['CV2Result'] ) );
				}

				if ( isset( $resp['3DSecureStatus'] ) ) {
					$order->update_meta_data( '3DSecureStatus', sanitize_text_field( $resp['3DSecureStatus'] ) );
				}

				if ( isset( $orderid ) ) {
					$order->update_meta_data( 'VendorTxCode', sanitize_text_field( $orderid ) );
				}

				$order->save(); // Don't forget to save the changes.

				if ( 'OK' === $resp['Status'] ) {

					$order->add_order_note( $resp['StatusDetail'] );

					set_transient( 'opayo_server_next_url', $resp['NextURL'] );

					$redirect = $order->get_checkout_payment_url( true );

					return array(
						'result'   => 'success',
						'redirect' => $redirect,
					);

				} else {

					if ( isset( $resp['StatusDetail'] ) ) {
						wc_add_notice( sprintf( 'Transaction Failed. %s - %s', $resp['Status'], $resp['StatusDetail'] ), 'error' );
					} else {
						wc_add_notice( sprintf( 'Transaction Failed with %s - unknown error.', $resp['Status'] ), 'error' );
					}
				}
			} else {
				wc_add_notice( esc_html__( 'Gateway Error. Please Notify the Store Owner about this error.', 'patsatech-wc-opayo-server' ) . $response['body'], 'error' );
			}
		}

		/**
		 * Receipt Page
		 *
		 * @param  mixed $order_id //Receipt Page.
		 * @return void
		 */
		public function patsatech_wc_opayo_server_receipt_page( $order_id ) {
			global $woocommerce;

			$order = new WC_Order( $order_id );

			if ( 'yes' === $this->iframe ) {
				echo '<iframe src="' . esc_url( get_transient( 'opayo_server_next_url' ) ) . '" name="opayoserver_payment_form" width="100%" height="900px" scrolling="no" ></iframe>';
			} else {

				echo '<p>' . esc_html__( 'Thank you for your order.', 'patsatech-wc-opayo-server' ) . '</p>';

				wc_enqueue_js(
					'
					jQuery("body").block({
							message: "<img src=\"' . esc_url( $woocommerce->plugin_url() ) . '/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />' . esc_html__( 'Thank you for your order. We are now redirecting you to verify your card.', 'patsatech-wc-opayo-server' ) . '",
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
					jQuery("#submit_opayoserver_payment_form").click();
				'
				);

				echo '<form action="' . esc_url( get_transient( 'opayo_server_next_url' ) ) . '" method="post" id="opayoserver_payment_form">
					<input type="submit" class="button alt" id="submit_opayoserver_payment_form" value="' . esc_html__( 'Submit', 'patsatech-wc-opayo-server' ) . '" />
					<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . esc_html__( 'Cancel order &amp; restore cart', 'patsatech-wc-opayo-server' ) . '</a>
					</form>';

			}

		}

		/**
		 * Successful Payment!
		 **/
		public function patsatech_wc_opayo_server_successful_request() {
			global $woocommerce;

			$eoln             = chr( 13 ) . chr( 10 );
			$params           = array();
			$params['Status'] = 'INVALID';

			$status_detail = '';

			if ( isset( $_POST['StatusDetail'] ) ) {
				$status_detail = wp_strip_all_tags( sanitize_text_field( wp_unslash( $_POST['StatusDetail'] ) ) );
			}

			$status = '';

			if ( isset( $_POST['Status'] ) ) {
				$status = wp_strip_all_tags( sanitize_text_field( wp_unslash( $_POST['Status'] ) ) );
			}

			if ( isset( $_POST['VendorTxCode'] ) ) {

				$vendor_tx_code = explode( '-', wp_strip_all_tags( wp_unslash( $_POST['VendorTxCode'] ) ) );

				$order = new WC_Order( $vendor_tx_code[2] );

				if ( 'OK' === $status ) {
					$params       = array(
						'Status'       => 'OK',
						'StatusDetail' => esc_html__( 'Transaction acknowledged.', 'patsatech-wc-opayo-server' ),
					);
					$redirect_url = $this->get_return_url( $order );
					$order->add_order_note( esc_html__( 'Opayo Server payment completed', 'patsatech-wc-opayo-server' ) . ' ( ' . esc_html__( 'Transaction ID: ', 'patsatech-wc-opayo-server' ) . wp_strip_all_tags( wp_unslash( $_POST['VendorTxCode'] ) ) . ' )' );
					$order->payment_complete();
				} elseif ( 'ABORT' === $status ) {
					$params = array(
						'Status'       => 'INVALID',
						'StatusDetail' => esc_html__( 'Transaction aborted - ', 'patsatech-wc-opayo-server' ) . $status_detail,
					);
					wc_add_notice( esc_html__( 'Aborted by user.', 'patsatech-wc-opayo-server' ), 'error' );
					$redirect_url = get_permalink( woocommerce_get_page_id( 'checkout' ) );
				} elseif ( 'ERROR' === $status ) {
					$params       = array(
						'Status'       => 'INVALID',
						'StatusDetail' => esc_html__( 'Transaction errored - ', 'patsatech-wc-opayo-server' ) . $status_detail,
					);
					$redirect_url = $order->get_cancel_order_url();
				} else {
					$params       = array(
						'Status'       => 'INVALID',
						'StatusDetail' => esc_html__( 'Transaction failed - ', 'patsatech-wc-opayo-server' ) . $status_detail,
					);
					$redirect_url = $order->get_cancel_order_url();
				}
			} else {
				$params['StatusDetail'] = esc_html__( 'Opayo Server, No VendorTxCode posted.', 'patsatech-wc-opayo-server' );
			}

			$params['RedirectURL'] = esc_url( $this->patsatech_wc_opayo_server_force_ssl( $redirect_url ) );

			if ( 'yes' === $this->iframe ) {
				$params['RedirectURL'] = add_query_arg( 'page', $redirect_url, $this->patsatech_wc_opayo_server_force_ssl( $this->notify_url ) );

			} else {
				$params['RedirectURL'] = esc_url( $this->patsatech_wc_opayo_server_force_ssl( $redirect_url ) );
			}

			$param_string = '';
			foreach ( $params as $key => $value ) {
				$param_string .= $key . '=' . $value . $eoln;
			}

			if ( isset( $_GET['amp;page'] ) || isset( $_GET['page'] ) ) {

				if ( isset( $_GET['amp;page'] ) ) {
					$page = sanitize_text_field( wp_unslash( $_GET['amp;page'] ) );
				} else {
					$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
				}

				ob_clean();

				echo '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">' .
					'<html><head>' .
					'<script type="text/javascript"> function OnLoadEvent() { document.form.submit(); }</script>' .
					'<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />' .
					'<title>3D-Secure Redirect</title></head>' .
					'<body OnLoad="OnLoadEvent();">' .
					'<form name="form" action="' . esc_url( sanitize_text_field( wp_unslash( $page ) ) ) . '" method="POST"  target="_top" >' .
					'<noscript>' .
					'<center><p>Please click button below to Authenticate your card</p><input type="submit" value="Go"/></p></center>' .
					'</noscript>' .
					'</form></body></html>';

			} else {

				ob_clean();
				echo esc_attr( $param_string );
			}

			exit();

		}

		/**
		 * Check if the cart contains virtual product
		 *
		 * @param  mixed $order //Check if the cart contains virtual product.
		 * @return string
		 */
		public function patsatech_wc_opayo_server_cart_has_virtual_product( $order ) {
			global $woocommerce;

			$has_virtual_products = false;

			$virtual_products = 0;

			$products = $order->get_items();

			foreach ( $products as $item ) {
				$product = $item->get_product();
				// Update $has_virtual_product if product is virtual.
				if ( $product->is_virtual() || $product->is_downloadable() ) {
					++$virtual_products;
				}
			}

			if ( count( $products ) === $virtual_products ) {
				$has_virtual_products = true;
			}

			return $has_virtual_products;
		}

		/**
		 * Force SSL
		 *
		 * @param  mixed $url //Force SSL.
		 * @return string
		 */
		public function patsatech_wc_opayo_server_force_ssl( $url ) {
			if ( 'yes' === get_option( 'woocommerce_force_ssl_checkout' ) ) {
				$url = str_replace( 'http:', 'https:', $url );
			}
			return $url;
		}
	}

	/**
	 * Add the gateway to WooCommerce.
	 *
	 * @param  mixed $methods //Add the gateway to WooCommerce.
	 * @return array
	 */
	function patsatech_wc_opayo_server_add( $methods ) {
		$methods[] = 'PatSaTECH_WC_Opayo_Server';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'patsatech_wc_opayo_server_add' );


	add_action('before_woocommerce_init', function(){

		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	
		}
	
	});

}
