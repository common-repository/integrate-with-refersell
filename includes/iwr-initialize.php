<?php
if ( ! defined( 'ABSPATH' ) ){
    exit;
}
add_action( 'init', 'integrate_refersell_initialize' );
function integrate_refersell_initialize() {
	complete_integration_with_refersell();
	$last_rsn_ping_at = get_option('last_ping_from_rsn_at', NULL);
	if(!empty($last_rsn_ping_at)){
		$time_elapsed = ((strtotime(current_time('mysql'))-strtotime($last_rsn_ping_at))/3600); 
		if($time_elapsed>24){
			get_rsn_peers_list();
		}
	}	
	$labels = array(
		'name'               => _x( 'Refunds', 'post type general name', 'esellportal' ),
		'singular_name'      => _x( 'Refund', 'post type singular name', 'esellportal' ),
		'menu_name'          => _x( 'Refunds', 'admin menu', 'esellportal' ),
		'name_admin_bar'     => _x( 'Refunds', 'add new on admin bar', 'esellportal' ),
		'add_new'            => _x( 'Add New', 'Refund Requests', 'esellportal' ),
		'add_new_item'       => __( 'Add New Refund Requests', 'esellportal' ),
		'new_item'           => __( 'New Refund Requests', 'esellportal' ),
		'edit_item'          => __( 'Edit Refund Requests', 'esellportal' ),
		'view_item'          => __( 'View Refund Requests', 'esellportal' ),
		'all_items'          => __( 'All Refund Requests', 'esellportal' ),
		'search_items'       => __( 'Search Refund Requests', 'esellportal' ),
		'parent_item_colon'  => __( 'Parent Refund Requests:', 'esellportal' ),
		'not_found'          => __( 'No Refund Request found.', 'esellportal' ),
		'not_found_in_trash' => __( 'No Refund Request found in Trash.', 'esellportal' )
	);

	$args = array(
		'labels'             => $labels,
		'public'             => false,
		'menu_icon'   		 => 'dashicons-warning',
		'publicly_queryable' => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => false,
		'rewrite'            => array( 'slug' => 'rs-refund-request' ),
		'capability_type'    => 'post',
		'capabilities' => array(
								'create_posts' => false, // Removes support for the "Add New" function
							),
		'map_meta_cap' 		 => true,
		'has_archive'        => false,
		'hierarchical'       => false,
		'menu_position'      => 56,
		'supports'           => array( 'title', 'editor' )
	);
	register_post_type( 'rs_refund_request', $args );	
}
add_action( 'admin_menu', 'rs_refund_request_count', 999 );
function rs_refund_request_count() {
	global $menu;
	$post_types=array('rs_refund_request');
	//var_dump($menu);
	$pending_refunds_count = count(get_posts(array(
									'post_type'=>'rs_refund_request',
									'numberposts'=>-1,
									'meta_key'=>'rs-refund-status',
									'meta_value'=>'Pending',
									'fields'=>'ids'
							)));
	if(!empty($pending_refunds_count)){
		foreach($menu as $menu_position=>$admin_menu_items){
			if($admin_menu_items[2]==="edit.php?post_type=rs_refund_request"&&$admin_menu_items[0]==='Refunds'){
				$menu[$menu_position][0] .= sprintf('&nbsp;<span class="update-plugins count-%1$s" style="background-color:red;color:white"><span class="plugin-count">%1$s</span></span>',$pending_refunds_count );
			}
		}	
	}
}
add_filter( 'manage_edit-rs_refund_request_columns', 'rs_refund_request_columns' ) ;
function rs_refund_request_columns($refund_columns){
	$refund_columns['refund_id'] ='Refund ID';
	$refund_columns['status'] ='Status';
	return $refund_columns;
}
add_action( 'manage_rs_refund_request_posts_custom_column', 'display_refund_status_in_column', 10, 2 );
function display_refund_status_in_column( $column, $post_id ) {
	if($column==='status'){
		if(empty($_GET['refund_status'])){?>
			<a href='edit.php?<?php echo $_SERVER['QUERY_STRING'].'&refund_status='.get_post_meta($post_id, 'rs-refund-status', true);?>'><?php echo get_post_meta($post_id, 'rs-refund-status', true);?></a>
	<?php }else{?>
			<a href='edit.php?<?php echo $_SERVER['QUERY_STRING'];?>'><?php echo get_post_meta($post_id, 'rs-refund-status', true);?></a>
	<?php	}
	}elseif($column==='refund_id'){
		echo $post_id;
	}
}
add_filter( 'manage_edit-rs_refund_request_sortable_columns', 'rs_refund_request_sortable_columns' );
function rs_refund_request_sortable_columns( $refund_columns ) {
	$refund_columns['status'] ='Status';
	return $refund_columns;
}
/* Only run our customization on the 'edit.php' page in the admin. */
add_action( 'load-edit.php', 'edit_rs_refund_request_load' );

function edit_rs_refund_request_load() {
	add_filter( 'request', 'sort_rs_refund_request' );
}

/* Sorts the movies. */
function sort_rs_refund_request( $vars ) {
	/* Check if we're viewing the 'movie' post type. */
	if ( isset( $vars['post_type'] ) && 'rs_refund_request' == $vars['post_type'] ) {

		/* Check if 'orderby' is set to 'duration'. */
		if ( isset( $vars['orderby'] ) && 'Status' == $vars['orderby'] ) {
			/* Merge the query vars with our custom variables. */
			$vars = array_merge(
				$vars,
				array(
					'meta_key' => 'rs-refund-status',
					'orderby' => 'meta_value'
				)
			);
		}
		if ( !empty( $_GET['refund_status'] ) ) {
			/* Merge the query vars with our custom variables. */
			if(empty($vars['meta_key'])){
				$vars = array_merge(
					$vars,
					array(
						'meta_key' => 'rs-refund-status'
					)
				);
			}
			$vars = array_merge(
				$vars,
				array(
					'meta_value' => (string)$_GET['refund_status']
				)
			);
		}
	}
	return $vars;
}
add_action( 'add_meta_boxes', 'add_rs_refund_request_metaboxes' );
function add_rs_refund_request_metaboxes(){
	add_meta_box('rs_refund_request_status', 'Refund Status', 'rs_refund_request_status_ui', 'rs_refund_request', 'side', 'default');
}
function rs_refund_request_status_ui(){
	global $post;
	$refund_status = get_post_meta($post->ID,'rs-refund-status',true);
	echo "<div class='misc-pub-section'>Refund ID: <strong>".$post->ID ."</strong></div>";
	echo "<div class='misc-pub-section'><input type='radio' name='rs_refund_status' value='Pending' ";if($refund_status==='Pending'){echo "checked='checked'";} echo "/>&nbsp;Pending</div>";
	echo "<div class='misc-pub-section'><input type='radio' name='rs_refund_status' value='Granted' ";if($refund_status==='Granted'){echo "checked='checked'";} echo "/>&nbsp;Granted</div>";
	echo "<div class='misc-pub-section'><input type='radio' name='rs_refund_status' value='Rejected' ";if($refund_status==='Rejected'){echo "checked='Rejected'";} echo "/>&nbsp;Rejected</div>";
	wp_nonce_field( 'iwr_change_refund_status', 'iwr_change_refund_status_nonce' ); 
}