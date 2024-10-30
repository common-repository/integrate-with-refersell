<?php 
if ( ! defined( 'ABSPATH' ) ){
    exit;
}
$public_key = get_option('rsp_pubKey', NULL);
if(!empty($_POST['action'])){
	$action_request = $_POST['action'];
}
$payload = array();
switch($action_request){
	case 'update-network-access-key':
		openssl_public_decrypt(base64_decode($_POST['encrypted_net_access_key']), $rsn_network_access_key, $public_key);
		if(!empty($rsn_network_access_key)){
			update_option('rsn-network-access-key',$rsn_network_access_key,false);
		}
		update_option('last_ping_from_rsn_at', current_time('mysql'), false);
		$payload=array('active'=>true);
	break;
	case 'request-products':
		$requested_rsn_product_categories_json = integrate_rsn_decrypt($_POST['product-categories']);
		$rsn_product_categories = get_option('rsn_product_categories', NULL);
		if(!empty($requested_rsn_product_categories_json)){
			$requested_rsn_product_categories = json_decode($requested_rsn_product_categories_json, true);
			$rsn_cat_ids=array();
			if(empty($requested_rsn_product_categories)){
				$requested_rsn_product_categories = array();
			}
			foreach($requested_rsn_product_categories as $req_rsn_cat_name=>$req_rsn_cat_attr){
				if((!empty($req_rsn_cat_attr['sync'])&&empty($rsn_product_categories[$req_rsn_cat_name]['sync']))||(empty($req_rsn_cat_attr['sync'])&&!empty($rsn_product_categories[$req_rsn_cat_name]['sync']))){
					$payload['sync_status'] = 'failed';
				}
				if(empty($req_rsn_cat_attr['sync'])){
					$rsn_cat_ids[] = $rsn_product_categories[$req_rsn_cat_name]['term_id'];
				}
			}
			if(empty($payload['sync_status'])){
				$products = get_posts(array(
						'posts_per_page'=>-1,
						'post_type' => 'product',
						'tax_query' => array(
										array(
											'taxonomy' => 'product_cat',
											'field'    => 'term_id',
											'terms'    => $rsn_cat_ids,
											'include_children' => false											
										)						
									)
						));
				$product_arr = array();
				foreach($products as $product){
					if($product->post_status == 'publish'){
						$args = array(
						   'post_type' => 'attachment',
						   'numberposts' => -1,
						   'post_parent' => $product->ID,
						  );
						$attachments = get_posts( $args );
						$product_featured_img_id = get_post_meta($product->ID, '_thumbnail_id', true);
						$product_images=array();
						foreach($attachments as $attachment){
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
						$iwr_site_product_cat_ids = wp_get_post_terms($product->ID, 'product_cat', array("fields" => "ids"));
						$rsn_product_cat_ids = convert_site_product_cats_to_rsn_product_cats($iwr_site_product_cat_ids);
						$product_arr[]=array(
							'post_date'=>$product->post_date,
							'post_date_gmt'=>$product->post_date_gmt,
							'post_content'=>$product->post_content,
							'post_title'=>$product->post_title,
							'post_excerpt'=>$product->post_excerpt,
							'post_status'=>$product->post_status,
							'comment_status'=>$product->comment_status,
							'rsn_product_cats'=>$rsn_product_cat_ids,
							'guid'=>$product->guid,
							'metadata'=>get_metadata('post', $product->ID),
							'attachments'=>$product_images
						);
					}
				}
				$product_arr['sync_status'] = 'success';
				$payload = $product_arr; 
			}
		}
	break;
	case 'rsn-distribute-product':
		$product_data = integrate_rsn_decrypt($_POST['product_data']);
		if(!empty($product_data)){
			global $wpdb;
			$unique_message_ids_table = $wpdb->prefix . 'refersell_unique_message_ids';
			$message_id_exists = $wpdb->get_var($wpdb->prepare("SELECT sno FROM $unique_message_ids_table WHERE unique_id=%s",$_POST['message_id']));
			if(empty($message_id_exists)){
				$wpdb->insert( 
					$unique_message_ids_table,
					array( 
						'unique_id' => $_POST['message_id']
					), 
					array( 
						'%s'
					) 
				);
				$product_data = json_decode($product_data, true);
			$rsn_product_location = $product_data[0]['metadata']['_rsn_product_location']['0'];
			if(!empty($rsn_product_location)){
				$rsn_product_location_parts = explode('|', $rsn_product_location);
				if($rsn_product_location_parts[0]==home_url()){
					$distribute_to_sites = iwr_get_adjacent_peers();
					$t = microtime(true);
					$micro = sprintf("%06d",($t - floor($t)) * 1000000);
					$d = new DateTime( date('Y-m-d H:i:s.'.$micro, $t) );
					$current_timestamp_gmt = $d->format("Y-m-d H:i:s.u");
					$unique_message_id = $current_timestamp_gmt;
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
																				'product_data' => $_POST['product_data']
																				)));
						curl_exec ($ch);
						curl_close ($ch);
					}
					exit;
				}
				if($product_data[0]['post_type']=='product'){
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $rsn_product_location_parts[0].'?p='.$rsn_product_location_parts[1]);
					curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
					curl_close ($ch);
				}else{
					$httpcode=200;
				}
				if($httpcode!=404){
			remove_action( 'save_post', 'iwr_distribute_saved_product', 10);
			remove_filter( 'wp_insert_post_data', 'iwr_restrict_product_editing', '99');
			remove_action('add_term_relationship', 'iwr_restrict_illegal_term_update', 10);
			remove_filter( "update_product_variation_metadata", 'iwr_restrict_illegal_meta_update', 10);
			remove_action( "update_product_variation_meta", 'iwr_restrict_illegal_meta_update', 10);
			remove_filter( "update_product_metadata", 'iwr_restrict_illegal_meta_update', 10);
			remove_action( "update_product_meta", 'iwr_restrict_illegal_meta_update', 10);
			foreach($product_data as $rsn_product){
				insert_rsn_product($rsn_product);
			}			
			add_action('add_term_relationship', 'iwr_restrict_illegal_term_update', 10, 1);
			add_filter( "update_product_variation_metadata", 'iwr_restrict_illegal_meta_update', 10, 4);
			add_action( "update_product_variation_meta", 'iwr_restrict_illegal_meta_update', 10, 4);
			add_filter( "update_product_metadata", 'iwr_restrict_illegal_meta_update', 10, 4);
			add_action( "update_product_meta", 'iwr_restrict_illegal_meta_update', 10, 4);
			add_action( 'save_post', 'iwr_distribute_saved_product', 10, 3 );
			add_filter( 'wp_insert_post_data', 'iwr_restrict_product_editing', '99', 2 );
			$rsn_peers_table = $wpdb->prefix . 'refersell_peers';
			$data_coming_site_sno = $wpdb->get_var($wpdb->prepare("SELECT sno FROM $rsn_peers_table WHERE website_url = %s", $_POST['website_url']));
			$this_site_sno = $wpdb->get_var($wpdb->prepare("SELECT sno FROM $rsn_peers_table WHERE website_url = %s", home_url()));
			$distribute_to_juniors=array();
			$distribute_to_seniors=array();
			if($data_coming_site_sno<$this_site_sno){
				$distribute_to_juniors = $wpdb->get_col("SELECT website_url FROM $rsn_peers_table WHERE sno > $this_site_sno ORDER BY sno ASC LIMIT 5");
			}else{
				$distribute_to_seniors = $wpdb->get_col("SELECT website_url FROM $rsn_peers_table WHERE sno < $this_site_sno ORDER BY sno DESC LIMIT 5");
			}			
			$distribute_to_sites = array_merge($distribute_to_seniors, $distribute_to_juniors);
			foreach($distribute_to_sites as $site){
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $site."/refersell-web-services/");
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_HEADER, false);
				  
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
																		'action' => 'rsn-distribute-product',
																		'message_id'=> $_POST['message_id'],
																		'website_url' => home_url(),
																		'product_data' => $_POST['product_data']
																		)));
				curl_exec ($ch);
				curl_close ($ch);
			}
			$payload = array('distributed');
			}}
			}
		}
	break;
	case 'rsn-delete-product':
		$product_data = integrate_rsn_decrypt($_POST['product_data']);
		$product_data = (string)$product_data;
		if(!empty($product_data)){
			global $wpdb;
			$unique_message_ids_table = $wpdb->prefix . 'refersell_unique_message_ids';
			$message_id_exists = $wpdb->get_var($wpdb->prepare("SELECT sno FROM $unique_message_ids_table WHERE unique_id=%s",$_POST['message_id']));
			if(empty($message_id_exists)){
				$wpdb->insert( 
					$unique_message_ids_table,
					array( 
						'unique_id' => $_POST['message_id']
					), 
					array( 
						'%s'
					) 
				);
				$product_data_parts = explode('|', $product_data);
				if($product_data_parts[0]==home_url()){
					$product = get_post($product_data_parts[1]);
					if(!empty($product)){
						iwr_distribute_saved_product( $product->ID, $product);
						exit;
					}
				} 
				$products_to_delete = get_posts(array(
								'post_type'=>'product',
								'numberposts'=>-1,
								'post_status'=> 'any',
								'meta_key'=>'_rsn_product_location',
								'meta_value'=>$product_data,
								'fields'=>'ids'
							)
						);
				$products_to_delete= array_merge($products_to_delete, get_posts(array(
								'post_type'=>'product',
								'numberposts'=>-1,
								'post_status'=> 'trash',
								'meta_key'=>'_rsn_product_location',
								'meta_value'=>$product_data,
								'fields'=>'ids'
							)
						));
				if(!empty($product_data_parts)){
						remove_action( 'delete_post', 'iwr_notify_peers_about_delete');
						foreach($products_to_delete as $product_id){
							$attachments = get_posts( array(
								'post_type'      => 'attachment',
								'posts_per_page' => -1,
								'post_status'    => 'any',
								'post_parent'    => $product_id,
								'fields' 		 => 'ids'
							) );
							foreach ( $attachments as $attachment ) {
								wp_delete_attachment( $attachment ) ;;
							}
							wp_delete_post( $product_id, true); 
						}
						add_action( 'delete_post', 'iwr_notify_peers_about_delete');
						global $wpdb;
						$rsn_peers_table = $wpdb->prefix . 'refersell_peers';
						$data_coming_site_sno = $wpdb->get_var($wpdb->prepare("SELECT sno FROM $rsn_peers_table WHERE website_url = %s", $_POST['website_url']));
						$this_site_sno = $wpdb->get_var($wpdb->prepare("SELECT sno FROM $rsn_peers_table WHERE website_url = %s", home_url()));
						$distribute_to_juniors=array();
						$distribute_to_seniors=array();
						if($data_coming_site_sno<$this_site_sno){
							$distribute_to_juniors = $wpdb->get_col("SELECT website_url FROM $rsn_peers_table WHERE sno > $this_site_sno ORDER BY sno ASC LIMIT 5");
						}else{
							$distribute_to_seniors = $wpdb->get_col("SELECT website_url FROM $rsn_peers_table WHERE sno < $this_site_sno ORDER BY sno DESC LIMIT 5");
						}	
						$distribute_to_sites = array_merge($distribute_to_seniors, $distribute_to_juniors);
						foreach($distribute_to_sites as $site){
							$ch = curl_init();
							curl_setopt($ch, CURLOPT_URL, $site."/refersell-web-services/");
							curl_setopt($ch, CURLOPT_POST, 1);
							curl_setopt($ch, CURLOPT_HEADER, false);
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
							curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
																					'action' => 'rsn-delete-product',
																					'message_id'=> $_POST['message_id'],
																					'website_url' => home_url(),
																					'product_data' => $_POST['product_data']
																					)));
							curl_exec ($ch);
							curl_close ($ch);
						}
						$payload = array('deleted');
				}
			}
		}
	break;
	case 'rsn-get-product':
		$product_id = $_POST['product_id'];
		$product = get_post($product_id);
		if(!empty($product)){
			if($product->post_type=='product' && $product->post_status == 'publish'){
				$product_arr = array();
			$args = array(
			   'post_type' => 'attachment',
			   'numberposts' => -1,
			   'post_parent' => $product->ID,
			  );
			$attachments = get_posts( $args );
			$product_featured_img_id = get_post_meta($product->ID, '_thumbnail_id', true);
			$product_images=array();
			foreach($attachments as $attachment){
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
			$iwr_site_product_cat_ids = wp_get_post_terms($product->ID, 'product_cat', array("fields" => "ids"));
			$rsn_product_cat_ids = convert_site_product_cats_to_rsn_product_cats($iwr_site_product_cat_ids);
			$product_arr[]=array(
				'post_date'=>$product->post_date,
				'post_date_gmt'=>$product->post_date_gmt,
				'post_content'=>$product->post_content,
				'post_title'=>$product->post_title,
				'post_excerpt'=>$product->post_excerpt,
				'post_status'=>$product->post_status,
				'comment_status'=>$product->comment_status,
				'rsn_product_cats'=>$rsn_product_cat_ids,
				'guid'=>$product->guid,
				'metadata'=>get_metadata('post', $product->ID),
				'attachments'=>$product_images
			);
			$product_arr['product_exists']=true;
			}else{
				$product_arr = array('product_exists'=>false);
			}
		}else{
				$product_arr = array('product_exists'=>false);
			}
			$payload = $product_arr;
	break;
	case 'update-rsn-product-cats':
		 $encrypted_rsn_product_cats_json = $_POST['encrypted_rsn_product_cats'];
		 openssl_public_decrypt(base64_decode($encrypted_rsn_product_cats_json), $rsn_product_cats_json, $public_key);
		 $global_rsn_product_cats = json_decode($rsn_product_cats_json, true);
		 unset($global_rsn_product_cats['count']);
		 $site_rsn_product_cats = get_option('rsn_product_categories');
		// var_dump($site_rsn_product_cats);
		 remove_filter( 'pre_insert_term', 'integrate_refersell_prevent_add_product_category', 20 );
		 remove_action( 'edit_term_taxonomies', 'integrate_refersell_prevent_loosing_parent_product_category', 10);
		 remove_action( 'edit_terms', 'integrate_refersell_prevent_editing_product_category', 10);
		 if(!empty($global_rsn_product_cats)&&!empty($site_rsn_product_cats)){
			 foreach($global_rsn_product_cats as $global_rsn_cat_name => $global_rsn_cat_attributes){
				 foreach($site_rsn_product_cats as $site_rsn_cat_name => $site_rsn_cat_attributes){
					 if($global_rsn_cat_attributes['rsn_cat_id']==$site_rsn_cat_attributes['rsn_cat_id']){
						 if(empty($global_rsn_cat_attributes['parent'])){
							 if($global_rsn_cat_name!=$site_rsn_cat_name){
								wp_update_term( $site_rsn_cat_attributes['term_id'], 'product_cat', array('name' => $global_rsn_cat_name) );
								$global_rsn_product_cats[$global_rsn_cat_name]['term_id'] = $site_rsn_cat_attributes['term_id'];
							 }else{
								$global_rsn_product_cats[$global_rsn_cat_name]['term_id'] = $site_rsn_cat_attributes['term_id'];
							 }						 
						 }else{
							 $global_rsn_parent_term = term_exists( $global_rsn_cat_attributes['parent'], 'product_cat' );
							 if(!empty($global_rsn_parent_term)){
								 if($global_rsn_cat_name!=$site_rsn_cat_name){
									wp_update_term( $site_rsn_cat_attributes['term_id'], 'product_cat', array('name' => $global_rsn_cat_name, 'parent'=>$global_rsn_product_cats[$global_rsn_cat_attributes['parent']]['term_id']) );
									$global_rsn_product_cats[$global_rsn_cat_name]['term_id'] = $site_rsn_cat_attributes['term_id'];
								 }else{
									wp_update_term( $site_rsn_cat_attributes['term_id'], 'product_cat', array('parent'=>$global_rsn_product_cats[$global_rsn_cat_attributes['parent']]['term_id']) );
									$global_rsn_product_cats[$global_rsn_cat_name]['term_id'] = $site_rsn_cat_attributes['term_id'];
								 }	
							 }
						 }
						$rsn_product_cat_exits = true;
					 }
				 }
				 if(!isset($rsn_product_cat_exits)){
					 //create term with parent
					 if(empty($global_rsn_cat_attributes['parent'])){
						 $iwr_insert_term = wp_insert_term( $global_rsn_cat_name, 'product_cat');
						 $global_rsn_product_cats[$global_rsn_cat_name]['term_id'] = $iwr_insert_term['term_id'];
					 }else{
						  $global_rsn_parent_term = term_exists( $global_rsn_cat_attributes['parent'], 'product_cat' );
						  if(!empty($global_rsn_parent_term)){
							  $iwr_insert_term = wp_insert_term(
								  $global_rsn_cat_name, // the term 
								  'product_cat', // the taxonomy
								  array(
									'parent'=> $global_rsn_product_cats[$global_rsn_cat_attributes['parent']]['term_id']
								  )
								);
							if(!is_wp_error($iwr_insert_term)){
								$global_rsn_product_cats[$global_rsn_cat_name]['term_id'] = $iwr_insert_term['term_id'];
							}
						  }
					 }
				 }
			 }	
			 update_option('rsn_product_categories', $global_rsn_product_cats, false);			 
		 }else{
			 update_option('just_connected_to_esp', 'no-rsn-categories-list-yet', false);
		 }
		 add_filter( 'pre_insert_term', 'integrate_refersell_prevent_add_product_category', 20, 2 );
		 add_action( 'edit_term_taxonomies', 'integrate_refersell_prevent_loosing_parent_product_category', 10, 1 );
		 add_action( 'edit_terms', 'integrate_refersell_prevent_editing_product_category', 10, 2 );
	break;
	case 'update-rsn-peers':
		$rsp_pubKey = get_option('rsp_pubKey');
		$refersell_peers_json = rsn_public_open($_POST['encrypted_rsn_peers'], $rsp_pubKey, $_POST['enc_key']);
		$refersell_peers = json_decode($refersell_peers_json);
		if(!empty($refersell_peers)){
			global $wpdb;
			$rsn_peers_table = $wpdb->prefix . 'refersell_peers';
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
	break;
	case 'create-order':
		if(!empty($_POST['enc-key'])&&!empty($_POST['order'])){
			global $woocommerce;
			$req_order_json = rsn_public_open($_POST['order'], $public_key, $_POST['enc-key']);
			$req_order = json_decode($req_order_json,true);
			if(!empty($req_order)){
				if(!empty($req_order['shipping_address'])){
					$address = $req_order['shipping_address'];
					unset($req_order['shipping_address']);
					$order = wc_create_order();
					$order->set_address( $address, 'billing' );
					$order->set_address( $address, 'shipping' );
					$products = array();
					foreach($req_order['order'] as $product){
						if(empty($product['variation_id'])){
							$product_data = new WC_Product($product['product_id']);
							$has_stock = $product_data->has_enough_stock( $product['quantity'] );
							$product_thumb = $product_data->get_image(array(50,50)); 
							$product_name = $product_data->get_formatted_name();
							$product_rate = $product_data->get_price();
							if($has_stock){
								$order->add_product( get_product( $product['product_id'] ), $product['quantity'] );
								$product_quantity = $product['quantity'];
							}else{
								$product_quantity = 'out of stock';
							}		
							$products[$product['product_id']]=array('product_thumb'=>$product_thumb, 'product_name'=>$product_name, 'product_rate'=>$product_rate, 'quantitity'=>$product_quantity);
						}else{
						//	var_dump($product);
						//$woocommerce->cart->add_to_cart( 259, 1, 260);
						
				//		var_dump($product['variation_id']);
							$product_variation = new WC_Product_Variation($product['variation_id']);
							$has_stock = $product_variation->has_enough_stock( $product['quantity'] );
							$product_thumb = $product_variation->get_image(array(50,50)); 
							$product_name = $product_variation->get_formatted_name();
							$product_rate = $product_variation->get_price();
							if($has_stock){
							/*	$membershipProduct = new WC_Product_Variable($product['product_id']);
								$theMemberships = $membershipProduct->get_available_variations();
								var_dump($theMemberships[0]["attributes"]);*/
								$args=array();
								foreach($product_variation->get_variation_attributes() as $attribute=>$attribute_value){
									$args['variation'][$attribute]=$attribute_value;
								}
								$order->add_product($product_variation, $product['quantity'], $args);
							
								$product_quantity = $product['quantity'];
							}else{
								$product_quantity = 'out of stock';
							}	
							$products[$product['variation_id']]=array('product_thumb'=>$product_thumb, 'product_name'=>$product_name, 'product_rate'=>$product_rate, 'quantitity'=>$product_quantity);
						}
					}
					$shipping_methods=$woocommerce->shipping->load_shipping_methods();
					$selected_shipping_method = $shipping_methods['flat_rate'];
					$class_cost = $selected_shipping_method->get_option('class_cost_' . $shipping_class->term_id);
				//	var_dump($selected_shipping_method);
					  $order->add_shipping((object)array (
							'id' => $selected_shipping_method->id,
							'label'    => $selected_shipping_method->title,
							'cost'     => (float)$selected_shipping_method->cost,
							'taxes'    => array(),
							'calc_tax'  => 'per_order'
						));

				//	$woocommerce->cart->calculate_shipping();
				//	$woocommerce->cart->calculate_totals();
				//	var_dump($woocommerce->cart);
				//	var_dump($woocommerce->cart->get_total());
				//	$x=$woocommerce->checkout->create_order();
				//	var_dump($x);	
				//	$woocommerce->cart->empty_cart(true);
				//	var_dump($order->get_shipping_methods());
					$order->calculate_totals();
					$total = $order->get_total();
				//	var_dump($total);
					$order_id = $order->id;
					$summary=array('order_id'=>home_url().'|'.$order_id,'total'=>$total, 'products'=>$products);
					$summary_json = json_encode($summary);
					openssl_seal($summary_json, $sealed, $ekeys, array($public_key));
					$enc_key = array_pop($ekeys);
					$payload=array('payload'=>base64_encode($sealed), 'enc_key'=>base64_encode($enc_key));
				}
			}
		}
	break;
	case 'process-rsn-remote-order':
		if(!empty($_POST['payload'])){
			$enc_payload_json = base64_decode($_POST['payload']);
			openssl_public_decrypt($enc_payload_json, $payload_json, $public_key);
			$payload = json_decode($payload_json, true);
			if(!empty($payload)){
				global $woocommerce;
				$order = new WC_Order( $payload['order_id'] );
			//	var_dump($order);
				if($payload['payment_method']=='Cash On Delivery'){
					$order->reduce_order_stock();
					$order->update_status( 'processing' );
					update_post_meta( $payload['order_id'], '_payment_method', 'rsn' );
					update_post_meta( $payload['order_id'], '_payment_method_title', 'Cash on Delivery' );
					$payload = array('success');
				}elseif($payload['payment_method']=='ReferSell'){
					$order->reduce_order_stock();
					$order->update_status( 'processing' );
					update_post_meta( $payload['order_id'], '_payment_method', 'rsn' );
					update_post_meta( $payload['order_id'], '_payment_method_title', 'ReferSell' );
					$payload = array('success');
				}
			}
		}
	break;
	case 'request-refund':
		$refund_request_json = rsn_public_open($_POST['refund_request'], $public_key, $_POST['enc-key']);
		$refund_request=json_decode($refund_request_json, true);
	//	var_dump($refund_request);
		$total_refunds = 0;
		$refund_content = '<table>
								<tr>
									<th>S.no</th>
									<th>Product ID</th>
									<th>Product Name</th>
									<th>Refund Quantity</th>
								</tr>
								';
		$default_refund_status_id = get_option('default-refund-status'); 
		$i=1;
		foreach($refund_request["refund_products"] as $refund_product){
			$product_type = get_post_type ($refund_product["refund_product_id"]);
			if($product_type=='product_variation'){
				$product = new WC_Product_Variation($refund_product["refund_product_id"]);
			}else{
				$product = new WC_Product($refund_product["refund_product_id"]);
			}
			$total_refunds +=$refund_product["refund_quantity"];
			$refund_content.='<tr>
								<td>'.$i.'</td>
								<td>'.$refund_product["refund_product_id"].'</td>
								<td>'.$product->get_formatted_name().'</td>
								<td>'.$refund_product["refund_quantity"].'</td>
							</tr>';
			$i++;
		}
		$refund_content.='</table><br/>';
		$refund_content.='<p>Reason: '.$refund_request['refund_reason'].'</p>';
		$refund_content.='<p>Customer Name: '.$refund_request['refund_user_name'].'</p>';
		$refund_content.='<p>Customer Email: '.$refund_request['refund_user_email'].'</p>';
		$refund_content.='<p>Customer Mobile: '.$refund_request['refund_user_phone'].'</p>';
		$new_refund = array(
		  'post_title'    => 'Order: #'.$refund_request['refund_order_id'].', '.$total_refunds.' items',
		  'post_content'  => $refund_content,
		  'post_status'   => 'publish',
		  'post_type'	  => 'rs_refund_request'
		  );
		 
		// Insert the post into the database
		$refund_id = wp_insert_post( $new_refund );
		if(!is_wp_error($refund_id)){
			add_post_meta($refund_id, 'rs-refund-status', 'Pending');
			$payload = array('refund_id'=>$refund_id);
		}else{
			$payload = array($refund_id->get_error_message());
		}
	break;
	default:
	break;
}
echo json_encode($payload);
die;