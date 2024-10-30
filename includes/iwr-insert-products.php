<?php

function insert_rsn_product($rsn_product){
	if(empty($rsn_product['metadata']['_rsn_product_location'])){
		$rsn_product['metadata']['_rsn_product_location'] = '';
	}
	if($rsn_product['post_type']=='product'){
		$args = array(
			   'post_type' => 'product',
			   'post_status' => 'any',
			   'meta_query' => array(
				   array(
					   'key' => '_rsn_product_location',
					   'value' => $rsn_product['metadata']['_rsn_product_location'][0]
				   )
			   ),
			   'fields' => 'ids'
			 );
	 // perform the query
		$rsn_product_already_exists = get_posts( $args );
		$args['post_status']='trash';
		$rsn_product_in_trash = get_posts( $args );
	//	var_dump($rsn_product['metadata']['_rsn_product_location']);
	//	exit;
		$args = array(
					'post_content'   => $rsn_product['post_content'],
					'post_title'     => $rsn_product['post_title'], 
					'post_status'    => 'publish',
					'post_type'      => 'product',
					'guid'           => $rsn_product['guid'],
					'post_excerpt'   => $rsn_product['post_excerpt'],
					'post_date'      => $rsn_product['post_date'],
					'post_date_gmt'  => $rsn_product['post_date_gmt'],
					'comment_status' => $rsn_product['comment_status'],
				);
		
		$rsn_product_cat_ids = $rsn_product['rsn_product_cats'];
		if(empty($rsn_product_cat_ids)){
			$rsn_product_cat_ids=array();
		}
		$rsn_product_cat_ids = $rsn_product['rsn_product_cats'];
		$iwr_site_product_cat_ids = convert_rsn_product_cats_to_site_product_cats($rsn_product_cat_ids);
		if(empty($iwr_site_product_cat_ids['no-sync'])){
			if(empty($rsn_product_in_trash)){
				if(empty($rsn_product_already_exists)){
					$rsn_product_id = wp_insert_post($args);
				}else{
					$args['ID'] = $rsn_product_already_exists[0];
					$rsn_product_id = wp_update_post($args);
				}
			}
		}else{
			foreach($rsn_product_already_exists as $delete_product_id){
				wp_delete_post( $delete_product_id);
			}
			foreach($rsn_product_in_trash as $delete_product_id){
				wp_delete_post( $delete_product_id);
			}
		}
		if(!empty($rsn_product_id)){
			wp_set_post_terms( $rsn_product_id, $iwr_site_product_cat_ids, 'product_cat');
			iwr_insert_porduct_attachments($rsn_product_id, $rsn_product['attachments']);
			iwr_insert_product_metadata($rsn_product_id, $rsn_product['metadata']);
		}
	}elseif($rsn_product['post_type']=='product_variation'){
		$parent_product_uniq_id = $rsn_product['post_parent'];
		$args = array(
			   'post_type' => 'product',
			   'post_status' => 'any',
			   'meta_query' => array(
				   array(
					   'key' => '_rsn_product_location',
					   'value' => $parent_product_uniq_id
				   )
			   ),
			   'fields' => 'ids'
			 );
		$rsn_product_already_exists = get_posts( $args );
		$args['post_status']='trash';
		$rsn_product_in_trash = get_posts( $args );
		if(!empty($rsn_product_already_exists)||!empty($rsn_product_in_trash)){
			if(!empty($rsn_product_already_exists)){
				$parent_product_id = array_pop($rsn_product_already_exists);
			}elseif(!empty($rsn_product_in_trash)){
				$parent_product_id = array_pop($rsn_product_in_trash);
			}
			add_filter( 'wp_insert_post_data', 'iwr_restrict_product_editing', '99', 2 );
			wp_set_object_terms($parent_product_id, 'variable', 'product_type');
			$rsn_variation_id = iwr_process_product_variation_metadata($parent_product_id, $rsn_product);
			iwr_insert_porduct_attachments($rsn_variation_id, $rsn_product['attachments']);
		//	var_dump($rsn_product['parent_product_attributes']);
			iwr_generate_variation_metadata($parent_product_id);
		}else{
			return false;
		}
	}
}
function iwr_get_mime_type($iwr_attachment_url){
	$iwr_guid_parts = explode('/', $iwr_attachment_url);
	$iwr_filename = array_pop($iwr_guid_parts);
	$iwr_filename_parts = explode('.', $iwr_filename);
	$iwr_filename_extension = array_pop($iwr_filename_parts);
	$iwr_filename = array_pop($iwr_filename_parts);
	$iwr_mime_by_extension = '';
	if($iwr_filename_extension=='jpg'){
		$iwr_attachment_mime_type = 'image/jpeg';
	}else{
		$iwr_attachment_mime_type = 'image/'.$iwr_filename_extension;
	}
	$iwr_curl_mime_type = iwr_get_url_mime_type($iwr_attachment_url);
	if($iwr_curl_mime_type==$iwr_attachment_mime_type){
		return array('name'=>$iwr_filename, 'mime'=>$iwr_curl_mime_type);
	}else{
		return false;
	}
}
function iwr_get_url_mime_type($url){
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_exec($ch);
	$remote_url_mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	$curl_mime_parts = explode(';', $remote_url_mime);
	return $curl_mime_parts[0];
}
function iwr_insert_porduct_attachments($rsn_product_id, $iwr_attachments){
	/*$attachments = get_posts( array(
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'post_parent'    => $rsn_product_id,
		'fields' 		 => 'ids'
    ) );
    foreach ( $attachments as $attachment ) {
        wp_delete_attachment( $attachment ) ;;
    }*/
	$iwr_product_gallery=array();
	foreach($iwr_attachments as $iwr_attachment){
		unset($existing_attachment_id);
		if(!empty($iwr_attachment['attachment_metadata']['_rsn_product_location'][0])){
			$existing_attachments = get_posts(array(
						'meta_key'=>'_rsn_product_location',
						'meta_value'=>$iwr_attachment['attachment_metadata']['_rsn_product_location'][0],
						'post_type'=>'attachment',
						'fields'=>'ids'
					));
			$existing_attachment_id =array_pop($existing_attachments);
		}
		//var_dump($iwr_attachment['attachment_metadata']['_rsn_product_location'][0]);
		$iwr_verified_mime_type = iwr_get_mime_type($iwr_attachment['guid']);
		if($iwr_verified_mime_type!==false){
			$attachment = array(
				'guid'           => $iwr_attachment['guid'], 
				'post_mime_type' => $iwr_verified_mime_type['mime'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', $iwr_verified_mime_type['name'] ),
				'post_content'   => '',
				'post_status'    => 'inherit'
			);
			if(!empty($existing_attachment_id)){
				$attachment['ID'] = $existing_attachment_id;
				$attach_id = wp_insert_attachment( $attachment, '', $rsn_product_id );
			}else{
				$attach_id = wp_insert_attachment( $attachment, '', $rsn_product_id );
			}
			if(!empty($attach_id)){
				if(!empty($iwr_attachment['attachment_metadata'])){
					foreach($iwr_attachment['attachment_metadata'] as $meta_key=>$meta_value){
						update_post_meta($attach_id, $meta_key, array_pop($meta_value));
					}
				}
				if(!empty($iwr_attachment['is_featured'])&&$iwr_attachment['is_featured']){
					set_post_thumbnail( $rsn_product_id, $attach_id );
				}else{
					$iwr_product_gallery[]=$attach_id;
				}
			}
		}		
	}
	if(!empty($iwr_product_gallery)){ 
		update_post_meta($rsn_product_id, '_product_image_gallery', implode(',',$iwr_product_gallery));
	}
}
function iwr_insert_product_metadata($rsn_product_id, $metadata){
	$iwr_metakeys_to_skip = array('_thumbnail_id', '_product_image_gallery', '_featured', '_edit_lock');
	update_post_meta($rsn_product_id, '_featured', 'no');
	foreach($metadata as $meta_key=>$meta_value){
		if(!strpos('variation', $meta_key)){
			if(!in_array($meta_key, $iwr_metakeys_to_skip)){
				update_post_meta($rsn_product_id, $meta_key, array_pop($meta_value));
			}
		}
	}
}
function convert_rsn_product_cats_to_site_product_cats($rsn_product_cat_ids){
	$rsn_product_categories = get_option('rsn_product_categories', NULL);
	$iwr_site_product_cat_ids = array();
	unset($rsn_product_categories['count']);
	foreach($rsn_product_cat_ids as $rsn_product_cat_id){
		foreach($rsn_product_categories as $rsn_product_category){
			if($rsn_product_category['rsn_cat_id']==$rsn_product_cat_id){
				$iwr_site_product_cat_ids[] = $rsn_product_category['term_id'];
				if(!empty($rsn_product_category['sync'])){
					$nosync=true;
				}
			}
			
		}
	}
	if(isset($nosync)){
		$iwr_site_product_cat_ids['no-sync']=true;
	}
	return $iwr_site_product_cat_ids;
}
function iwr_process_product_variation_metadata($parent_product_id, $rsn_product){
	if(!empty($rsn_product['metadata']['_rsn_product_location'])){
		$variation_uniq_id = $rsn_product['metadata']['_rsn_product_location'][0];
	}else{
		exit;
	}
	$args = array(
		   'post_type' => 'product_variation',
		   'post_status' => 'any',
		   'meta_query' => array(
			   array(
				   'key' => '_rsn_product_location',
				   'value' => $variation_uniq_id
			   )
		   ),
		   'fields' => 'ids'
		 );
	$rsn_product_already_exists = get_posts( $args );	
	$args = array(
					'post_content'   => $rsn_product['post_content'],
					'post_title'     => $rsn_product['post_title'], 
					'post_status'    => 'publish',
					'post_type'      => 'product_variation',
					'post_parent'	 => $parent_product_id,
					'guid'           => $rsn_product['guid'],
					'post_excerpt'   => $rsn_product['post_excerpt'],
					'post_date'      => $rsn_product['post_date'],
					'post_date_gmt'  => $rsn_product['post_date_gmt'],
					'comment_status' => $rsn_product['comment_status'],
				);
	remove_filter( 'wp_insert_post_data', 'iwr_restrict_product_editing', '99');
	if(empty($rsn_product_already_exists)){
		$rsn_variation_id = wp_insert_post($args);
	}else{
		$args['ID'] = $rsn_product_already_exists[0];
		$rsn_variation_id = wp_update_post($args);
	}
	update_post_meta($rsn_variation_id, '_rsn_product_location', $variation_uniq_id);
	$iwr_metakeys_to_skip = array('_thumbnail_id', '_product_image_gallery', '_featured', '_edit_lock');
	$product_attribute_names = array_keys($rsn_product['parent_product_attributes']);
	$replace_keys=array();
	//var_dump($product_attribute_names);
	foreach($rsn_product['metadata'] as $meta_key => $meta_value){
		if(substr($meta_key, 0, 10)=='attribute_'){
			$replace=false;
			$variation_taxonomy = substr($meta_key, 10);
			$search = array_search ($variation_taxonomy, $product_attribute_names);
			if(	$search!==false ){
				$replace=true;
			}
			$variation_term = $meta_value[0];
			if(substr($meta_key, 0, 13)=='attribute_pa_'){
				$replace=true;
				$variation_taxonomy = substr($meta_key, 13);
			}
			if($replace){
				$replace_keys[$product_attribute_names[$search]]='pa_'.$variation_taxonomy;
			}
			if(!taxonomy_exists('pa_'.$variation_taxonomy)&&!taxonomy_exists($variation_taxonomy)){
				global $wpdb;
				$woo_attribute_tax_table = $wpdb->prefix .'woocommerce_attribute_taxonomies';
				$variation_tax_exists = $wpdb->get_var($wpdb->prepare("SELECT attribute_id FROM $woo_attribute_tax_table WHERE attribute_name = %s",$variation_taxonomy));
				$name = $label = $variation_taxonomy;
				if(empty($variation_tax_exists)){
					$wpdb->insert(
						$woo_attribute_tax_table,
						array(
							'attribute_name'=>$variation_taxonomy,
							'attribute_label'=>$variation_taxonomy,
							'attribute_type'=>'select',
							'attribute_orderby'=>'menu_order',
							'attribute_public'=>0
						),
						array(
							'%s',
							'%s',
							'%s',
							'%s',
							'%d'
						)
					);
					delete_transient('wc_attribute_taxonomies');
					$taxonomy_data = array(
						'hierarchical'          => true,
						'update_count_callback' => '_update_post_term_count',
						'labels'                => array(
								'name'              => $label,
								'singular_name'     => $label,
								'search_items'      => sprintf( __( 'Search %s', 'woocommerce' ), $label ),
								'all_items'         => sprintf( __( 'All %s', 'woocommerce' ), $label ),
								'parent_item'       => sprintf( __( 'Parent %s', 'woocommerce' ), $label ),
								'parent_item_colon' => sprintf( __( 'Parent %s:', 'woocommerce' ), $label ),
								'edit_item'         => sprintf( __( 'Edit %s', 'woocommerce' ), $label ),
								'update_item'       => sprintf( __( 'Update %s', 'woocommerce' ), $label ),
								'add_new_item'      => sprintf( __( 'Add New %s', 'woocommerce' ), $label ),
								'new_item_name'     => sprintf( __( 'New %s', 'woocommerce' ), $label ),
								'not_found'         => sprintf( __( 'No &quot;%s&quot; found', 'woocommerce' ), $label ),
							),
						'show_ui'            => true,
						'show_in_quick_edit' => false,
						'show_in_menu'       => false,
						'show_in_nav_menus'  => false,
						'meta_box_cb'        => false,
						'query_var'          => 1 === $tax->attribute_public,
						'rewrite'            => false,
						'sort'               => false,
						'public'             => 1 === $tax->attribute_public,
						'show_in_nav_menus'  => 1 === $tax->attribute_public && apply_filters( 'woocommerce_attribute_show_in_nav_menus', false, $name ),
						'capabilities'       => array(
							'manage_terms' => 'manage_product_terms',
							'edit_terms'   => 'edit_product_terms',
							'delete_terms' => 'delete_product_terms',
							'assign_terms' => 'assign_product_terms',
						)
					);
					register_taxonomy( 'pa_'.$name, apply_filters( "woocommerce_taxonomy_objects_{$name}", array( 'product' ) ), apply_filters( "woocommerce_taxonomy_args_{$name}", $taxonomy_data ) );
				}
			}
				$variation_term_exists = term_exists( $variation_term, 'pa_'.$variation_taxonomy);
				if(empty($variation_term_exists['term_id'])){
					wp_insert_term( $variation_term, 'pa_'.$variation_taxonomy);
				}	
				$existing_term_objects = wp_get_object_terms( $parent_product_id, 'pa_'.$variation_taxonomy);
				$existing_terms = wp_list_pluck($existing_term_objects, 'slug');
				if(!in_array($variation_term, $existing_terms)){
					$existing_terms[]=$variation_term;
					wp_set_object_terms($parent_product_id, $existing_terms, 'pa_'.$variation_taxonomy);
				}
				//wp_delete_object_term_relationships( $rsn_variation_id, 'pa_'.$variation_taxonomy);
				wp_set_object_terms($rsn_variation_id, $variation_term, 'pa_'.$variation_taxonomy);
				update_post_meta($rsn_variation_id, 'attribute_pa_'.$variation_taxonomy, $variation_term);
		}elseif(!in_array($meta_key, $iwr_metakeys_to_skip)){
			update_post_meta($rsn_variation_id, $meta_key, array_pop($meta_value));
		}
	}
	$parent_product_attributes = $rsn_product['parent_product_attributes'];
	foreach($replace_keys as $replace_this=>$with_this){
		if($replace_this!=$with_this){
			$parent_product_attributes[$with_this]=$rsn_product['parent_product_attributes'][$replace_this];
			unset($parent_product_attributes[$replace_this]);
			$parent_product_attributes[$with_this]['name']=$with_this;
		}
	}
	foreach($parent_product_attributes as $attribute=>$attribute_meta){
		$parent_product_attributes[$attribute]['is_taxonomy']=1;
	}
	//var_dump($parent_product_attributes);
	update_post_meta($parent_product_id,'_product_attributes',$parent_product_attributes);
	add_filter( 'wp_insert_post_data', 'iwr_restrict_product_editing', '99', 2 );
	return $rsn_variation_id;
}
function iwr_generate_variation_metadata($parent_product_id){
	global $wpdb;
	$all_variation_post_ids = get_posts(array(
		'numberposts'=>-1,
		'post_type'=>'product_variation',
		'post_parent'=>$parent_product_id,
		'fields'=>'ids'
	));
	$variation_ids_csv = implode(',',$all_variation_post_ids);
	$_min_variation_price=$wpdb->get_var("SELECT MIN(meta_value) FROM $wpdb->postmeta WHERE meta_key = '_price' AND post_id IN ($variation_ids_csv)");
	$_max_variation_price=$wpdb->get_var("SELECT MAX(meta_value) FROM $wpdb->postmeta WHERE meta_key = '_price' AND post_id IN ($variation_ids_csv)");
	$_min_price_variation_id=$wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_price' AND meta_value = '$_min_variation_price' AND post_id IN ($variation_ids_csv)");
	$_max_price_variation_id=$wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_price' AND meta_value = '$_max_variation_price' AND post_id IN ($variation_ids_csv)");
	$_min_variation_regular_price=$wpdb->get_var("SELECT MIN(meta_value) FROM $wpdb->postmeta WHERE meta_key = '_regular_price' AND post_id IN ($variation_ids_csv)");
	$_max_variation_regular_price=$wpdb->get_var("SELECT MAX(meta_value) FROM $wpdb->postmeta WHERE meta_key = '_regular_price' AND post_id IN ($variation_ids_csv)");
	$_min_regular_price_variation_id=$wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_regular_price' AND meta_value = '$_min_variation_regular_price' AND post_id IN ($variation_ids_csv)");
	$_max_regular_price_variation_id=$wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_regular_price' AND meta_value = '$_max_variation_regular_price' AND post_id IN ($variation_ids_csv)");
	$_min_variation_sale_price=$wpdb->get_var("SELECT MIN(meta_value) FROM $wpdb->postmeta WHERE meta_key = '_sale_price' AND post_id IN ($variation_ids_csv)");
	$_max_variation_sale_price=$wpdb->get_var("SELECT MAX(meta_value) FROM $wpdb->postmeta WHERE meta_key = '_sale_price' AND post_id IN ($variation_ids_csv)");
	$_min_sale_price_variation_id=$wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_sale_price' AND meta_value = '$_min_variation_sale_price' AND post_id IN ($variation_ids_csv)");
	$_max_sale_price_variation_id=$wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_sale_price' AND meta_value = '$_max_variation_sale_price' AND post_id IN ($variation_ids_csv)");
	update_post_meta($parent_product_id, '_min_variation_price', $_min_variation_price);
	update_post_meta($parent_product_id, '_max_variation_price', $_max_variation_price);
	update_post_meta($parent_product_id, '_min_price_variation_id', $_min_price_variation_id);
	update_post_meta($parent_product_id, '_max_price_variation_id', $_max_price_variation_id);
	update_post_meta($parent_product_id, '_min_variation_regular_price', $_min_variation_regular_price);
	update_post_meta($parent_product_id, '_max_variation_regular_price', $_max_variation_regular_price);
	update_post_meta($parent_product_id, '_min_regular_price_variation_id', $_min_regular_price_variation_id);
	update_post_meta($parent_product_id, '_max_regular_price_variation_id', $_max_regular_price_variation_id);
	update_post_meta($parent_product_id, '_min_variation_sale_price', $_min_variation_sale_price);
	update_post_meta($parent_product_id, '_max_variation_sale_price', $_max_variation_sale_price);
	update_post_meta($parent_product_id, '_min_sale_price_variation_id', $_min_sale_price_variation_id);
	update_post_meta($parent_product_id, '_max_sale_price_variation_id', $_max_sale_price_variation_id);	
}