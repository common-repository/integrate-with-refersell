<?php 
if ( ! defined( 'ABSPATH' ) ){
    exit;
}
add_action('plugins_loaded', 'woocommerce_rsn_gateway_init', 0);

    function woocommerce_rsn_gateway_init() {

        if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

    /**
     * Gateway class
     */
    class WC_Rsn_Gateway extends WC_Payment_Gateway {
        public function __construct(){

            // Go wild in here
            $this -> id           = 'rsn';
            $this -> method_title = __('ReferSell', 'integrate-refersell');
            $this -> icon         =  'data:image/png;base64,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';
            $this -> has_fields   = false;
            
            $this -> init_form_fields();
            $this -> init_settings();

            $this -> title            = $this -> settings['title'];
            $this -> description      = $this -> settings['description'];            
            $this -> liveurl  = 'https://www.portal.refersell.com/payment-initiate/';
            $this->notify_url = add_query_arg( 'wc-api', 'WC_Rsn_Gateway', home_url());
			$this->supports = array(
								  'products',
								  'refunds'
								);
            $this -> msg['message'] = "";
            $this -> msg['class']   = "";
            
            add_action( 'woocommerce_api_wc_rsn_gateway', array( $this, 'check_rsn_response' ) );

            add_action('valid-rsn-request', array($this, 'successful_request'));
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
            add_action('woocommerce_receipt_rsn', array($this, 'receipt_page'));
            add_action('woocommerce_thankyou_rsn',array($this, 'thankyou_page'));
        }

        function init_form_fields(){

            $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'integrate-refersell'),
                    'type' => 'checkbox',
                    'label' => __('Enable ReferSell Payment Module.', 'integrate-refersell'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Title:', 'integrate-refersell'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'integrate-refersell'),
                    'default' => __('ReferSell', 'integrate-refersell')),
                'description' => array(
                    'title' => __('Description:', 'integrate-refersell'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'integrate-refersell'),
                    'default' => __('Pay securely by Credit or Debit card or internet banking through ReferSell.', 'integrate-refersell'))
                );


}
        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
        public function admin_options(){
            echo '<h3>'.__('ReferSell Gateway', 'integrate-refersell').'</h3>';
            echo '<p>'.__('ReferSell Gateway provides hassle free integration with refersell.com').'</p>';
            echo '<table class="form-table">';
            $this -> generate_settings_html();
            echo '</table>';

        }
        
		
        function payment_fields(){
            if($this -> description) echo wpautop(wptexturize($this -> description));
        }
        /**
         * Receipt Page
         **/
        function receipt_page($order){

            echo '<p>'.__('Thank you for your order, please click the button below <br/>to pay through ReferSell.', 'integrate-refersell').'</p>';
            echo $this -> generate_rsn_form($order);
        }
        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
            $order = new WC_Order($order_id);
            return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ));
        }
		public function process_refund( $order_id, $amount = null ) {
			// Do your refund here. Refund $amount for the order with ID $order_id
			$refund_request_id = (int)$_POST['refund_reason'];
			if(empty($refund_request_id)){
				return false;
			}else{
				$refund_request = get_post($refund_request_id);
				if(empty($refund_request)){
					return false;
				}else{
					if($refund_request->post_type==='rs_refund_request'){
						$refund_status_meta = get_post_meta($refund_request_id, 'rs-refund-status', true);
						if($refund_status_meta==='Pending'){
						$refund_request_json = json_encode(array('refund_request_id'=>$refund_request_id, 'order_id'=> $order_id, 'refund_amount'=> $amount));
						$rsp_pubKey = get_option('rsp_pubKey');
						if(!empty($rsp_pubKey)){
							openssl_public_encrypt($refund_request_json, $encrypted_refund_request, $rsp_pubKey);
							$ch = curl_init();
							curl_setopt($ch, CURLOPT_URL, "https://www.portal.refersell.com/web-services/");
							curl_setopt($ch, CURLOPT_POST, 1);
							curl_setopt($ch, CURLOPT_HEADER, false);
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
							curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
																					'action' => 'initiate-refund',
																					'website_url' => home_url(),
																					'refund_request' => base64_encode($encrypted_refund_request)
																					)));
							$response = curl_exec ($ch);
							curl_close ($ch);
							$refund_status = json_decode($response);
							if($refund_status->status==='success'){
								update_post_meta($refund_request->ID, 'rs-refund-status', 'Granted');
								return true;
							}else{
								return false;
							}
						}else{
							return false;
						}
						}else{
							return false;
						}
					}else{
						return false;
					}
				}
			}
			
		}
     
        function check_rsn_response(){
            global $woocommerce;
			if(!empty($_POST['referrer_order_id'])){
				$order_id_parts = explode('_', $_POST['referrer_order_id']);
				$order_id = (int)$order_id_parts[0];
				if(!empty($order_id)){
					$order=new WC_Order($order_id);
					$order->cancel_order('Order Forwarded to respective vendors.');
				}
			}
			if(!empty($order)){
				$order->cancel_order('Order propagated to respective vendors');
			}
			$woocommerce -> cart -> empty_cart();
			$redirect_url = get_permalink(woocommerce_get_page_id('Shop'));
			wp_redirect( $redirect_url );
			exit;
		}

        public function generate_rsn_form($order_id){
            global $woocommerce;
            $order = new WC_Order($order_id);
			$items = $order->get_items();
			$order_contents = array();
			$home_url = home_url().'|';
			foreach($items as $item){
				$product_location = get_post_meta($item['product_id'], '_rsn_product_location', true);
				if(empty($product_location)){
					$product_location=$home_url.$item['product_id'];
					update_post_meta($item['product_id'], '_rsn_product_location', $product_location);
				}
				if(!empty($item['variation_id'])){
					$variation_location = get_post_meta($item['variation_id'], '_rsn_product_location', true);
					if(empty($variation_location)){
						$variation_location=$home_url.$item['variation_id'];
						update_post_meta($item['variation_id'], '_rsn_product_location', $variation_location);
					}
				}
				$order_contents[] = array(
											'name'=> $item['name'],
											'rsn_product_location'=>get_post_meta($item['product_id'], '_rsn_product_location', true),
											'rsn_product_variation_location'=>get_post_meta($item['variation_id'], '_rsn_product_location', true),
											'qty'=>$item['qty']
										);
			}
            $order_id = $order_id.'_'.date("ymds");
            $rsn_args = array(
                'amount'           => $order -> order_total,
                'order_id'         => $order_id,
				'order_contents'   => $order_contents,
                'redirect_url'     => $this->notify_url,
                'cancel_url'       => $this->notify_url,
                'first_name'       => $order -> billing_first_name,
				'last_name'		   => $order -> billing_last_name,
                'billing_address_1'=> trim($order -> billing_address_1, ','),
                'billing_address_2'=> trim($order -> billing_address_2, ','),
                'billing_country'  => wc()->countries -> countries [$order -> billing_country],
                'billing_state'    => $order -> billing_state,
                'billing_city'     => $order -> billing_city,
                'billing_zip'      => $order -> billing_postcode,
                'billing_tel'      => $order -> billing_phone,
                'billing_email'    => $order -> billing_email,
                'delivery_first_name'=> $order -> shipping_first_name,
                'delivery_last_name' => $order -> shipping_last_name,
                'delivery_address_1' => $order -> shipping_address_1,
                'delivery_address_2' => $order -> shipping_address_2,
                'delivery_country' => $order -> shipping_country,
                'delivery_state'   => $order -> shipping_state,
                'delivery_tel'     => $order -> shipping_phone,
                'delivery_city'    => $order -> shipping_city,
                'delivery_zip'     => $order -> shipping_postcode,
                'language'         => 'EN',
                'currency'         => get_woocommerce_currency()
                );
$public_key = get_option('rsp_pubKey', NULL);
$rsn_args_json = json_encode($rsn_args);
openssl_seal($rsn_args_json, $sealed, $ekeys, array($public_key));
$enc_key = array_pop($ekeys);
$key = base64_encode($enc_key);
$encrypted_data = base64_encode($sealed);
$rsn_args_array   = array();
$rsn_args_array[] = "<input type='hidden' name='enc_key' value='$key'/>";
$rsn_args_array[] = "<input type='hidden' name='enc_transaction_request' value='$encrypted_data'/>";
$rsn_args_array[] = "<input type='hidden' name='enc_transaction_referrer' value='".home_url()."'/>";

wc_enqueue_js( '
    $.blockUI({
        message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to ReferSell Gateway.', 'integrate-refersell' ) ) . '",
        baseZ: 99999,
        overlayCSS:
        {
            background: "#fff",
            opacity: 0.6
        },
        css: {
            padding:        "20px",
            zindex:         "9999999",
            textAlign:      "center",
            color:          "#555",
            border:         "3px solid #aaa",
            backgroundColor:"#fff",
            cursor:         "wait",
            lineHeight:     "24px",
        }
    });
jQuery("#submit_rsn_payment_form").click();
' );

$form = '<form action="' . esc_url( $this -> liveurl ) . '" method="post" id="rsn_payment_form" target="_top">
' . implode( '', $rsn_args_array ) . '
<!-- Button Fallback -->
<div class="payment_buttons">
<input type="submit" class="button alt" id="submit_rsn_payment_form" value="' . __( 'Pay via ReferSell', 'integrate-refersell' ) . '" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'woocommerce' ) . '</a>
</div>
<script type="text/javascript">
jQuery(".payment_buttons").hide();
</script>
</form>';
return $form;
}

}

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_rsn_gateway($methods) {
        $methods[] = 'WC_Rsn_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_rsn_gateway' );
}
?>