<?php 
add_filter( 'pre_insert_term', 'integrate_refersell_prevent_add_product_category', 20, 2 );
function integrate_refersell_prevent_add_product_category( $term, $taxonomy ) {
	if($taxonomy=='product_cat'){
		return new WP_Error( 'invalid_term', 'Please use the existing product categories instead. <a href="#">Request for a new category.</a>' );
	}else{
		return $term;
	}
}
add_action( 'delete_term_taxonomy', 'integrate_refersell_prevent_delete_product_category', 10);
function integrate_refersell_prevent_delete_product_category($term_id) {
	$term = get_term_by( 'id', $term_id, 'product_cat');
    if( !empty($term) ){ 
        wp_die( 'Action prohibited by integrate refersell plugin' );
	}
}
add_action( 'edit_term_taxonomies', 'integrate_refersell_prevent_loosing_parent_product_category', 10, 1 );
function integrate_refersell_prevent_loosing_parent_product_category($term_ids){
	foreach($term_ids as $term_id){
		$term   = get_term_by( 'id', $term_id, 'product_cat' );
		if(!empty($term)){
			wp_die( 'Action prohibited by integrate refersell plugin' );
		}
	}
}
add_action( 'edit_terms', 'integrate_refersell_prevent_editing_product_category', 10, 2 ); 
function integrate_refersell_prevent_editing_product_category($term_id, $taxonomy){
	 if($taxonomy=='product_cat'){
		wp_die('Action prohibited by integrate refersell plugin.');
		exit;
	}
}

add_action('add_term_relationship', 'iwr_restrict_illegal_term_update', 10, 1);
function iwr_restrict_illegal_term_update($object_id){
	$rsn_product_location = get_post_meta($object_id, '_rsn_product_location', true);
	if(!empty($rsn_product_location)){
		$rsn_product_location_parts = explode('|', $rsn_product_location);
		if($rsn_product_location_parts[0]!=home_url()){
			wp_die('You can only edit the products created by you.');
		}
	}
}

add_filter( "update_product_variation_metadata", 'iwr_restrict_illegal_meta_update', 10, 4);
add_action( "update_product_variation_meta", 'iwr_restrict_illegal_meta_update', 10, 4);
add_filter( "update_product_metadata", 'iwr_restrict_illegal_meta_update', 10, 4);
add_action( "update_product_meta", 'iwr_restrict_illegal_meta_update', 10, 4);
function iwr_restrict_illegal_meta_update($meta_id, $object_id, $meta_key, $_meta_value ){
	$rsn_product_location = get_post_meta($object_id, '_rsn_product_location', true);
	if(!empty($rsn_product_location)){
		$rsn_product_location_parts = explode('|', $rsn_product_location);
		if($rsn_product_location_parts[0]!=home_url()){
			wp_die('You can only edit the products created by you.');
			return false;
		}
	}
}
add_filter( 'wp_insert_post_data', 'iwr_restrict_product_editing', '99', 2 );
function iwr_restrict_product_editing( $data , $postarr ) {
	if ( false === ( $restoring_product = get_transient( '_rsn_restoring_product_'.$postarr['ID'] ) ) ) {
		if($postarr['post_type']=='product'||$postarr['post_type']=='product_variation'){
			$rsn_product_location = get_post_meta($postarr['ID'], '_rsn_product_location', true);
			if(!empty($rsn_product_location)){
				$rsn_product_location_parts = explode('|', $rsn_product_location);
				if(($rsn_product_location_parts[0]!=home_url())&&($postarr['post_status']!='trash')&&!empty($postarr['post_status'])){
					wp_die('You can only edit the products created by you.');
				}
			}
		}
	}
    return $data;
}

?>