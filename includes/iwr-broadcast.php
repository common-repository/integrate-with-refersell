<?php
if ( ! defined( 'ABSPATH' ) ){
    exit;
}
add_action( 'updated_post_meta', 'rsn_distribute_updated_meta', 10, 4 );
function rsn_distribute_updated_meta($meta_id, $object_id, $meta_key, $_meta_value) {
	$post = get_post($object_id);
	iwr_distribute_saved_product( $object_id, $post);
}
add_action( 'save_post', 'iwr_distribute_saved_product', 10, 3 );
function iwr_distribute_saved_product( $post_id, $product, $update='' ) {
	if ( wp_is_post_revision( $post_id ) ){
		return;
	}
    if ( 'product' === $product->post_type ) {
	if($product->post_status == 'publish'){
		$product_unique_location = get_post_meta($product->ID, '_rsn_product_location', true);
		if(empty($product_unique_location)){
			update_post_meta($product->ID, '_rsn_product_location', home_url().'|'.$product->ID);
		}
		$product_images = iwr_get_product_images_for_broadcast($product->ID);
		$iwr_site_product_cat_ids = wp_get_post_terms($product->ID, 'product_cat', array("fields" => "ids"));
		$rsn_product_cat_ids = convert_site_product_cats_to_rsn_product_cats($iwr_site_product_cat_ids);
		$product_arr[]=array(
			'post_date'=>$product->post_date,
			'post_date_gmt'=>$product->post_date_gmt,
			'post_content'=>$product->post_content,
			'post_title'=>$product->post_title,
			'post_excerpt'=>$product->post_excerpt,
			'post_status'=>$product->post_status,
			'post_type'=>$product->post_type,
			'comment_status'=>$product->comment_status,
			'rsn_product_cats'=>$rsn_product_cat_ids,
			'guid'=>$product->guid,
			'metadata'=>get_metadata('post', $product->ID),
			'attachments'=>$product_images
		);
	}
	if(!empty($product_arr)){
	$distribute_to_sites = iwr_get_adjacent_peers();
	$product_data = integrate_rsn_encrypt(json_encode($product_arr));
	$t = microtime(true);
	$micro = sprintf("%06d",($t - floor($t)) * 1000000);
	$d = new DateTime( date('Y-m-d H:i:s.'.$micro, $t) );
	$current_timestamp_gmt = $d->format("Y-m-d H:i:s.u");
	$unique_message_id = $current_timestamp_gmt;
	global $wpdb;
	$unique_message_ids_table = $wpdb->prefix . 'refersell_unique_message_ids';
	$wpdb->insert( 
		$unique_message_ids_table,
		array( 
			'unique_id' => $unique_message_id
		), 
		array( 
			'%s'
		) 
	);
	//	var_dump($product_images);
	foreach($distribute_to_sites as $site){
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $site."/refersell-web-services/");
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
																'action' => 'rsn-distribute-product',
																'message_id'=> $unique_message_id,
																'website_url' => home_url(),
																'product_data' => $product_data
																)));
		$response = curl_exec ($ch);
		curl_close ($ch);
	}	
	}
	}elseif('product_variation' === $product->post_type){
		if($product->post_status == 'publish'){
			$product_unique_location = get_post_meta($product->ID, '_rsn_product_location', true);
			if(empty($product_unique_location)){
				update_post_meta($product->ID, '_rsn_product_location', home_url().'|'.$product->ID);
			}
			$parent_product_uniq_id = home_url().'|'.$product->post_parent;
			$product_images = iwr_get_product_images_for_broadcast($product->ID);
			$product_arr[]=array(
				'post_date'=>$product->post_date,
				'post_date_gmt'=>$product->post_date_gmt,
				'post_content'=>$product->post_content,
				'post_title'=>$product->post_title,
				'post_excerpt'=>$product->post_excerpt,
				'post_status'=>$product->post_status,
				'post_type'=>$product->post_type,
				'post_parent'=>$parent_product_uniq_id,
				'comment_status'=>$product->comment_status,
				'rsn_product_cats'=>'',
				'guid'=>$product->guid,
				'metadata'=>get_metadata('post', $product->ID),
				'attachments'=>$product_images,
				'parent_product_attributes'=>get_post_meta($product->post_parent,'_product_attributes',true)
			);
		}
		if(!empty($product_arr)){
			$distribute_to_sites = iwr_get_adjacent_peers();
			$product_data = integrate_rsn_encrypt(json_encode($product_arr));
			$t = microtime(true);
			$micro = sprintf("%06d",($t - floor($t)) * 1000000);
			$d = new DateTime( date('Y-m-d H:i:s.'.$micro, $t) );
			$current_timestamp_gmt = $d->format("Y-m-d H:i:s.u");
			$unique_message_id = $current_timestamp_gmt;
			global $wpdb;
			$unique_message_ids_table = $wpdb->prefix . 'refersell_unique_message_ids';
			$wpdb->insert( 
				$unique_message_ids_table,
				array( 
					'unique_id' => $unique_message_id
				), 
				array( 
					'%s'
				) 
			);
			foreach($distribute_to_sites as $site){
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $site."/refersell-web-services/");
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_HEADER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
																		'action' => 'rsn-distribute-product',
																		'message_id'=> $unique_message_id,
																		'website_url' => home_url(),
																		'product_data' => $product_data
																		)));
				$response = curl_exec ($ch);
				curl_close ($ch);
			//	var_dump($response);
			}
		}
	}
}
function iwr_get_product_images_for_broadcast($product_id){
	$product_featured_img_id = get_post_meta($product_id, '_thumbnail_id', true);
	$product_image_gallery = get_post_meta($product_id, '_product_image_gallery', true);
	if(!empty($product_image_gallery)){
		$product_image_gallery_ids = explode(',',$product_image_gallery);
	}else{
		$product_image_gallery_ids = array();
	}
	if(!empty($product_featured_img_id)){
		$product_image_gallery_ids[] = $product_featured_img_id;
	}
	$args = array(
	   'post_type' => 'attachment',
	   'numberposts' => -1,
	   'post__in' => $product_image_gallery_ids
	  );
	$attachments = get_posts( $args );
	$product_images=array();
	foreach($attachments as $attachment){
		$attachment_unique_location = get_post_meta($attachment->ID, '_rsn_product_location', true);
		if(empty($attachment_unique_location)){
			update_post_meta($attachment->ID, '_rsn_product_location', home_url().'|'.$attachment->ID);
		}
		if($attachment->ID == $product_featured_img_id){
			$product_images[] = array(
								'is_featured'=>true,
								'guid'=>$attachment->guid,
								'attachment_metadata'=>get_metadata('post', $attachment->ID)
							);
		}else{
			$product_images[] = array(
								'is_featured'=>false,
								'guid'=>$attachment->guid,
								'attachment_metadata'=>get_metadata('post', $attachment->ID)
							);
		}
	}
	return $product_images;
}

add_action( 'admin_init', 'iwr_admin_init' );
function iwr_admin_init() {
    add_action( 'delete_post', 'iwr_notify_peers_about_delete', 10 );
}
add_action( 'wp_trash_post', 'iwr_notify_peers_about_delete',20);  
function iwr_notify_peers_about_delete( $post_id) {
	$rsn_product_location = get_post_meta($post_id, '_rsn_product_location', true);
	if(!empty($rsn_product_location)){
		$rsn_product_location_parts = explode('|', $rsn_product_location);
		if($rsn_product_location_parts[0]==home_url()){
		$distribute_to_sites = iwr_get_adjacent_peers();
		$product_data = integrate_rsn_encrypt($rsn_product_location);
		$t = microtime(true);
		$micro = sprintf("%06d",($t - floor($t)) * 1000000);
		$d = new DateTime( date('Y-m-d H:i:s.'.$micro, $t) );
		$current_timestamp_gmt = $d->format("Y-m-d H:i:s.u");
		$unique_message_id = $current_timestamp_gmt;
		global $wpdb;
		$unique_message_ids_table = $wpdb->prefix . 'refersell_unique_message_ids';
		$wpdb->insert( 
			$unique_message_ids_table,
			array( 
				'unique_id' => $unique_message_id
			), 
			array( 
				'%s'
			) 
		);
		foreach($distribute_to_sites as $site){
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $site."/refersell-web-services/");
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HEADER, false);
			  
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
																	'action' => 'rsn-delete-product',
																	'message_id'=> $unique_message_id,
																	'website_url' => home_url(),
																	'product_data' => $product_data
																	)));
			curl_exec ($ch);
			curl_close ($ch);
		}
	}
	}
}

add_action('untrash_post', 'iwr_sync_untrash_with_network' );
function iwr_sync_untrash_with_network($post_id){
	$rsn_product_location = get_post_meta($post_id, '_rsn_product_location', true);
	if(!empty($rsn_product_location)){
		$rsn_product_location_parts = explode('|', $rsn_product_location);
		if($rsn_product_location_parts[0]==home_url()){
			iwr_distribute_saved_product( $rsn_product_location_parts[1], get_post($rsn_product_location_parts[1]));
		}else{
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $rsn_product_location_parts[0]."/refersell-web-services/");
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
																	'action' => 'rsn-get-product',
																	'website_url' => home_url(),
																	'product_id' => $rsn_product_location_parts[1]
																	)));
			$response = curl_exec ($ch);
			curl_close ($ch);
			$product_details = json_decode($response, true);
			if($product_details['product_exists']){
				unset($product_details['product_exists']);
				$rsn_product = array_pop($product_details);
				set_transient( '_rsn_restoring_product_'.$post_id, 1, 10 );
				insert_rsn_product($rsn_product);
			}
		}
	}
}
function iwr_get_adjacent_peers(){
	global $wpdb;
	$rsn_peers_table = $wpdb->prefix . 'refersell_peers';
	$this_site_sno = $wpdb->get_var($wpdb->prepare("SELECT sno FROM $rsn_peers_table WHERE website_url = %s", home_url()));
	$distribute_to_seniors = $wpdb->get_col("SELECT website_url FROM $rsn_peers_table WHERE sno < $this_site_sno ORDER BY sno DESC LIMIT 5");
	$distribute_to_juniors = $wpdb->get_col("SELECT website_url FROM $rsn_peers_table WHERE sno > $this_site_sno ORDER BY sno ASC LIMIT 5");
	$distribute_to_sites = array_merge($distribute_to_seniors, $distribute_to_juniors);
	return $distribute_to_sites;
}
function convert_site_product_cats_to_rsn_product_cats($iwr_site_product_cat_ids){
	$rsn_product_categories = get_option('rsn_product_categories', NULL);
	$rsn_product_cat_ids = array();
	if(!empty($rsn_product_categories['count'])){
		unset($rsn_product_categories['count']);
	}
	//var_dump($rsn_product_categories);
	foreach($iwr_site_product_cat_ids as $iwr_site_product_cat_id){
		foreach($rsn_product_categories as $rsn_product_category){
			if($rsn_product_category['term_id']==$iwr_site_product_cat_id){
				$rsn_product_cat_ids[]=$rsn_product_category['rsn_cat_id'];
			}
		}
	}
	return $rsn_product_cat_ids;
}
