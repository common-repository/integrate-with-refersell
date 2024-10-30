<?php
if ( ! defined( 'ABSPATH' ) ){
    exit;
}
add_action('admin_init', 'integrate_refersell_activation_redirect');

function integrate_refersell_activate() {
    update_option('integrate_refersell_activation_redirect', true);
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . 'refersell_peers';
	if($wpdb->get_var("SHOW TABLES LIKE '".$table_name."'") != $table_name) {
		$sql = "CREATE TABLE ".$table_name."(
				sno bigint(8) UNSIGNED NOT NULL AUTO_INCREMENT,
				id bigint(8) UNSIGNED NOT NULL,
				website_url text NOT NULL,
				UNIQUE KEY id (sno)
			)".$charset_collate.";";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
	$table_name = $wpdb->prefix . 'refersell_unique_message_ids';
	if($wpdb->get_var("SHOW TABLES LIKE '".$table_name."'") != $table_name) {
		$sql = "CREATE TABLE ".$table_name."(
				sno bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				unique_id varchar(32) NOT NULL,
				UNIQUE KEY id (sno)
			)".$charset_collate.";";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
	$all_products_ids = get_posts(array(
		'posts_per_page'=>-1,
		'post_type'=>'product',
		'fields'=>'ids')
	);
	$home_url = home_url().'|';
	foreach($all_products_ids as $product_id){
		$rsn_product_location = get_post_meta($product_id, '_rsn_product_location', true);
		if(empty($rsn_product_location)){
			update_post_meta($product_id, '_rsn_product_location', $home_url.$product_id);
		}
	}
}
function integrate_refersell_activation_redirect(){
	if (get_option('integrate_refersell_activation_redirect', false)) {
        delete_option('integrate_refersell_activation_redirect');
        wp_redirect(menu_page_url( 'integrate-refersell', false ));
    }  
}
add_filter( 'page_template', 'integrate_refersell_page_templates' );
function integrate_refersell_page_templates( $page_template ){
	$iwr_dir=WP_PLUGIN_DIR . '/integrate-with-refersell';
    if ( is_page( 'ReferSell Web Services' ) ) {
        $rsn_web_services_template = $iwr_dir.'/page-templates/rsn-web-services.php';
		return $rsn_web_services_template;
	}
	return $page_template;
}
