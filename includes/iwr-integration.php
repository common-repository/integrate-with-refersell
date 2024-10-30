<?php 
if ( ! defined( 'ABSPATH' ) ){
    exit;
}
function complete_integration_with_refersell(){
	$just_connected_status = get_option('just_connected_to_esp',NULL);
	//var_dump($just_connected_status);
	switch($just_connected_status){
		case 'no-rsn-network-access-yet':
			get_rsn_network_access_key();
		break;
		case 'no-rsn-peers-list-yet':
			get_rsn_peers_list();
		//	update_option('just_connected_to_esp', 'no-rsn-categories-list-yet', false);
		break;
		case 'no-rsn-categories-list-yet':
			get_rsn_categories_list();
		//	update_option('just_connected_to_esp', 'no-rsn-products-sync-yet', false);
		break;
		case 'no-rsn-products-sync-yet':
		//	update_option('just_connected_to_esp', 'rsn-connection-complete', false);
		break;
		default:
			return true;
		break;
	}
}
function get_rsn_network_access_key(){
	$public_key = get_option('rsp_pubKey', NULL);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,"https://www.portal.refersell.com/web-services/");
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('website_url' => home_url(), 'action' => 'request-network-access-key')));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec ($ch);
	curl_close ($ch);
	$response = json_decode($response);
	$s = openssl_public_decrypt(base64_decode($response->rsn_network_access_key), $rsn_network_access_key, $public_key);
	if(!empty($rsn_network_access_key)){
		update_option('rsn-network-access-key',$rsn_network_access_key,false);
		update_option('just_connected_to_esp', 'no-rsn-peers-list-yet', false);
	}
}
function get_rsn_peers_list(){
	global $wpdb;
	$rsn_peers_table = $wpdb->prefix . 'refersell_peers';
	$public_key = get_option('rsp_pubKey', NULL);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,"https://www.portal.refersell.com/web-services/");
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('website_url' => home_url(), 'action' => 'request-peers-list')));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec ($ch);
	curl_close ($ch);
	$enc_peers = json_decode($response);
	$refersell_peers_json = rsn_public_open($enc_peers->refersell_peers, $public_key, $enc_peers->e_rsp_crypt_key);
	//openssl_public_decrypt(base64_decode(json_decode($response)->refersell_peers), $refersell_peers_json, $public_key);
	$refersell_peers = json_decode($refersell_peers_json);
	if(!empty($refersell_peers)){
		update_option('last_ping_from_rsn_at', current_time('mysql'), false);	
		$wpdb->query("TRUNCATE TABLE $rsn_peers_table");
		foreach($refersell_peers as $peer){
			$wpdb->insert( 
							$rsn_peers_table, 
							array( 
								'id' => $peer->id, 
								'website_url' => $peer->website_url
							), 
							array( 
								'%d',
								'%s'
							) 
						);
		}
	}
	update_option('just_connected_to_esp', 'no-rsn-categories-list-yet', false);
}
function get_rsn_categories_list(){
	$public_key = get_option('rsp_pubKey', NULL);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,"https://www.portal.refersell.com/web-services/");
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('website_url' => home_url(), 'action' => 'request-categories-list')));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec ($ch);
	curl_close ($ch);
	openssl_public_decrypt(base64_decode(json_decode($response)->product_cat_list), $rsn_product_cats_json, $public_key);
	$rsn_product_cats = json_decode($rsn_product_cats_json, true);
	if(!empty($rsn_product_cats)){
	remove_filter( 'pre_insert_term', 'integrate_refersell_prevent_add_product_category', 20 );
	foreach($rsn_product_cats as $rsn_cat_name => $rsn_cat_attributes){
		if(empty($rsn_cat_attributes['parent'])){
			$rsn_term = term_exists( $rsn_cat_name, 'product_cat' );
			if(empty($rsn_term)){
				$iwr_insert_term = wp_insert_term( $rsn_cat_name, 'product_cat');
				$rsn_product_cats[$rsn_cat_name]['term_id'] = $iwr_insert_term['term_id'];
			}else{
				$rsn_product_cats[$rsn_cat_name]['term_id'] = $rsn_term['term_id'];
			}
		}else{
			$rsn_term = term_exists( $rsn_cat_name, 'product_cat' );
			if(empty($rsn_term)){
				$rsn_parent_term = term_exists( $rsn_cat_attributes['parent'], 'product_cat' );
				if(!empty($rsn_parent_term)){
					
					$iwr_insert_term = wp_insert_term(
					  $rsn_cat_name, // the term 
					  'product_cat', // the taxonomy
					  array(
						'parent'=> $rsn_product_cats[$rsn_cat_attributes['parent']]['term_id']
					  )
					);
					if(!is_wp_error($iwr_insert_term)){
						$rsn_product_cats[$rsn_cat_name]['term_id'] = $iwr_insert_term['term_id'];
					}
				}
			}else{
				$rsn_product_cats[$rsn_cat_name]['term_id'] = $rsn_term['term_id'];
			}
		}
	}
	add_filter( 'pre_insert_term', 'integrate_refersell_prevent_add_product_category', 20, 2 );
	update_option('rsn_product_categories', $rsn_product_cats, false);
	update_option('just_connected_to_esp', 'no-rsn-products-sync-yet', false);
	}
}
function sync_rsn_products(){
	global $wpdb;
	$peers_table = $wpdb->prefix .'refersell_peers';
	$rsn_peer_id = $wpdb->get_var($wpdb->prepare("SELECT sno FROM $peers_table WHERE website_url = %s", home_url()));
	$rsn_product_categories = get_option('rsn_product_categories', NULL);
	$rsn_product_categories_json = json_encode($rsn_product_categories);
	$rsn_product_categories_json_encrypted = integrate_rsn_encrypt($rsn_product_categories_json);
	$i=1;
	if(!empty($rsn_product_categories)){
		$rsn_products["sync_status"]='failed';
		do{
			$older_rsn_peer = $wpdb->get_row("SELECT * FROM $peers_table WHERE sno<$rsn_peer_id ORDER BY sno DESC LIMIT 1");
			if(!empty($older_rsn_peer)){
				$rsn_peer_id = $older_rsn_peer->sno;
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $older_rsn_peer->website_url .'/refersell-web-services/');
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array( 'action' => 'request-products', 'product-categories'=>$rsn_product_categories_json_encrypted)));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$response = curl_exec ($ch);
				curl_close ($ch);
				$rsn_products = json_decode($response,true);
			}
		}while($rsn_products["sync_status"]=='failed'&&!empty($older_rsn_peer));
		if($rsn_products["sync_status"]=='success'){
			unset($rsn_products["sync_status"]);
			foreach($rsn_products as $rsn_product){
				insert_rsn_product($rsn_product);
			}
		}
		update_option('just_connected_to_esp', 'rsn-connection-complete', false);
	}
}
?>