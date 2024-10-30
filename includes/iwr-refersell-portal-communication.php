<?php 
if ( ! defined( 'ABSPATH' ) ){
    exit;
}
add_action('save_post', 'iwr_save_refund_request_meta', 1, 2); 
function iwr_save_refund_request_meta($post_id, $post) {
	if(isset($post->post_type)&&$post->post_type=='rs_refund_request'){
		if ( !(isset( $_POST['iwr_change_refund_status_nonce'] ) && wp_verify_nonce( $_POST['iwr_change_refund_status_nonce'], 'iwr_change_refund_status' ))) {
			return $post->ID;
		}

		// Is the user allowed to edit the post or page?
		if ( !current_user_can( 'edit_post', $post->ID ))
			return $post->ID;

		// OK, we're authenticated: we need to find and save the data
		// We'll put it into an array to make it easier to loop though.
		
		$rs_possible_refund_statusus = array('Pending','Granted','Rejected');
		$current_refund_status = get_post_meta($post->ID, 'rs-refund-status', true);
		if($current_refund_status!='Granted'){
			if($_POST['rs_refund_status']!='Granted'){
				if(in_array($_POST['rs_refund_status'], $rs_possible_refund_statusus)){
					$refund_title = $post->post_title;
					$order_id_parts = explode('#',explode(',',$refund_title)[0]);
					$refund_update_json = json_encode(array('refund_request_id'=>$post->ID, 'refund_order_id'=>$order_id_parts[1], 'new_refund_status'=> $_POST['rs_refund_status']));
					$rsn_pubKey = get_option('rsp_pubKey');
					openssl_public_encrypt($refund_update_json, $enc_refund_update, $rsn_pubKey);
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL,"https://www.portal.refersell.com/web-services/");
					curl_setopt($ch, CURLOPT_POST, 1);
					curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
																			'action' => 'update-refund-status',
																			'website_url' => home_url(),
																			'order_update' => base64_encode($enc_refund_update)
																			)));
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					$response = curl_exec ($ch);
					curl_close ($ch);
					$temp_array = json_decode($response);
					if(!empty($temp_array)){
						update_post_meta($post->ID, 'rs-refund-status', $_POST['rs_refund_status']);
					}
				}
			}
		}
		// Add values of $events_meta as custom fields
		/*
		foreach ($events_meta as $key => $value) { // Cycle through the $events_meta array!
			if( $post->post_type == 'revision' ) return; // Don't store custom data twice
			$value = implode(',', (array)$value); // If $value is an array, make it a CSV (unlikely)
			if(get_post_meta($post->ID, $key, FALSE)) { // If the custom field already has a value
				update_post_meta($post->ID, $key, $value);
			} else { // If the custom field doesn't have a value
				add_post_meta($post->ID, $key, $value);
			}
			if(!$value) delete_post_meta($post->ID, $key); // Delete if blank
		}*/
	}
}

add_action( 'woocommerce_order_status_changed', 'iwr_on_order_status_change', 10, 3);
function iwr_on_order_status_change($order_id, $old_status, $new_status){
	$update_order_json = json_encode(array('order_id'=>$order_id, 'new_status'=> $new_status));
	$rsn_pubKey = get_option('rsp_pubKey');
	openssl_public_encrypt($update_order_json, $enc_update_order, $rsn_pubKey);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,"https://www.portal.refersell.com/web-services/");
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
															'action' => 'update-order-status',
															'website_url' => home_url(),
															'order_update' => base64_encode($enc_update_order)
															)));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec ($ch);
	curl_close ($ch);
}
?>