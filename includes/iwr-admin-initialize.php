<?php 
if ( ! defined( 'ABSPATH' ) ){
    exit;
}
add_action( 'admin_init', 'rsp_login_refersell' );
function rsp_login_refersell(){
	if(!empty($_POST['login-refersell'])){
		if(isset( $_POST['integrate_refersell_login_nonce'] ) && wp_verify_nonce( $_POST['integrate_refersell_login_nonce'], 'integrate_with_esellportal' )){
			if(!empty($_POST['refersell-uname'])&&!empty($_POST['refersell-pass'])){
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL,"https://www.portal.refersell.com/web-services/");
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
																		'action' => 'request-pubKey',
																		'website_url' => home_url()
																		)));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$response = curl_exec ($ch);
				curl_close ($ch);
				$response = json_decode($response);
				if(!empty($response)){
					update_option('last_ping_from_rsn_at', current_time('mysql'), false);	
					$pubKey=$response->public_key;
					update_option('rsp_pubKey', $pubKey, false);
					
					$credentials = array('uname' => $_POST['refersell-uname'],
										 'pass' => $_POST['refersell-pass']
										);
					$creds_json = json_encode($credentials);
					openssl_public_encrypt($creds_json, $encrypted, $response->public_key);
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL,"https://www.portal.refersell.com/web-services/");
					curl_setopt($ch, CURLOPT_POST, 1);
					curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
																			'action' => 'authenticate',
																			'website_url' => home_url(),
																			'credentials' => base64_encode($encrypted)
																			)));
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					$response = curl_exec ($ch);					
					curl_close ($ch);
					$authentication = json_decode($response);
					if($authentication->authentication=='successful'){
						openssl_public_decrypt(base64_decode($authentication->rsp_user_profile), $rsp_user_profile, $pubKey);
						update_option('rsp_user_profile', json_decode($rsp_user_profile,true), false);
						update_option('just_connected_to_esp', 'no-rsn-network-access-yet', false);
					}
				}
			}
		}
	}
	if(!empty($_POST['change-user-refersell'])){
		if(isset( $_POST['change_user_nonce'] ) && wp_verify_nonce( $_POST['change_user_nonce'], 'change_user_esellportal' )){
			update_option('rsp_user_profile', '', false);
			update_option('just_connected_to_esp', '', false);
		}
	}
	if(!empty($_POST['change-cat-pref-refersell'])){
		if(isset( $_POST['change_cat_pref_nonce'] ) && wp_verify_nonce( $_POST['change_cat_pref_nonce'], 'change_category_preference' )){
			$rsn_product_categories = get_option('rsn_product_categories', NULL);
			$rsn_cats_to_sync = $_POST['sync_cats'];
			if(empty($rsn_cats_to_sync)){
				$rsn_cats_to_sync = array();
			}
			//var_dump($rsn_cats_to_sync);
			foreach($rsn_product_categories as $rsn_cat_name=>$rsn_cat_attributes){
			//	var_dump(in_array($rsn_cat_name,$rsn_cats_to_sync));
				if(!in_array($rsn_cat_name,$rsn_cats_to_sync)){
					$rsn_product_categories[$rsn_cat_name]['sync'] = 'no';
				}else{
					if(!empty($rsn_product_categories[$rsn_cat_name]['sync'])){
						unset($rsn_product_categories[$rsn_cat_name]['sync']);
					}
				}
			}
			update_option('rsn_product_categories', $rsn_product_categories, false);
			sync_rsn_products();
		}
	}
	if(!empty($_POST['create-integrate-refersell-pages'])){
		if(isset( $_POST['create_iwr_page_nonce'] ) && wp_verify_nonce( $_POST['create_iwr_page_nonce'], 'create_web_services_page' )){
			if ( !get_page_by_title('ReferSell Web Services')){
				$post = array(
				  'post_title'    => 'ReferSell Web Services',
				  'post_content'  => '',
				  'post_status'   => 'publish',
				  'post_type'      => 'page'
				);
				wp_insert_post( $post, $wp_error ); 
			}
		}
	}
}
?>