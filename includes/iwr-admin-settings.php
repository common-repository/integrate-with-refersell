<?php 
if ( ! defined( 'ABSPATH' ) ){
    exit;
}
add_action('admin_menu', 'register_integrate_refersell_settings');
function register_integrate_refersell_settings() {
	add_submenu_page( 'options-general.php', 'Integrate refersell', 'Integrate refersell', 'manage_options', 'integrate-refersell', 'integrate_refersell_settings_ui' );
}

function integrate_refersell_settings_ui(){
	echo '<div class="wrap">';
		echo '<h2>Integrate with ReferSell</h2>';
		echo '<div class="integrate-refersell-admin-half-col" style="width: 50%; min-width:320px; float:left;">';
		$rsp_user_profile = get_option('rsp_user_profile',NULL);
		if(empty($rsp_user_profile)){
			echo '<form method="POST" action="" name="form">';
				wp_nonce_field( 'integrate_with_esellportal', 'integrate_refersell_login_nonce' ); 
				echo '<p>Please login using your credentials of <a href="https://www.portal.refersell.com/">ReferSell Portal</a>.</p>';
				echo '<h3 class="title">Login to refersell</h3>';
				echo '<table class="form-table">';
					echo '<tbody>';
						echo '<tr>
								<th scope="row"><label for="refersell-uname">Username</label></th>
								<td><input type="text" class="regular-text" placeholder="Enter your refersell username" aria-describedby="refersell-uname" id="refersell-uname" name="refersell-uname"/>
								<p id="refersell-uname-desc" class="description">Enter your username used in refersell portal.</p></td>
							</tr>';
						echo '<tr>
								<th scope="row"><label for="refersell-pass">Password</label></th>
								<td><input type="password" class="regular-text" placeholder="Enter your refersell password" aria-describedby="refersell-pass" id="refersell-pass" name="refersell-pass"/>
								<p id="refersell-pass-desc" class="description">Enter your password used in refersell portal.</p></td>
							</tr>';
					echo '</tbody>';
				echo '</table>';
			echo '<p class="submit"><input type="submit" value="Login" class="button button-primary" id="submit" name="login-refersell"></p>';
			echo '</form>';
		}else{
			echo '<h3 class="title">refersell Profile</h3>';
			echo '<table class="form-table">';
				echo '<tbody>';
					echo '<tr>
							<th scope="row">User id</th>
							<td>'.$rsp_user_profile['user-id'].'</td>
						</tr>';
					echo '<tr>
							<th scope="row">Username</th>
							<td>'.$rsp_user_profile['user-name'].'</td>
						</tr>';
				echo '</tbody>';
			echo '</table>';
			echo '<form method="POST" action="" name="form">';
				wp_nonce_field( 'change_user_esellportal', 'change_user_nonce' ); 
				echo '<p class="submit"><input type="submit" value="Forget" class="button button-primary" id="submit" name="change-user-refersell"></p>';
			echo '</form>';
		
		$just_connected_status = get_option('just_connected_to_esp',NULL);
		//var_dump($just_connected_status);
		echo '<h3 class="title">refersell Connection Status</h3>';
		if(!($just_connected_status=='no-rsn-products-sync-yet'||$just_connected_status=='rsn-connection-complete')){
		//	echo '<script>location.reload();</script>';
		}
		echo '<table class="form-table">';
			echo '<tbody>';
		switch($just_connected_status){
			case 'no-rsn-network-access-yet':
				echo '<tr>
						<th scope="row">Network Access</th>
						<td>Awaiting</td>
					</tr>';
				echo '<tr>
						<th scope="row">Peers List</th>
						<td>Awaiting</td>
					</tr>';
				echo '<tr>
						<th scope="row">refersell categories list</th>
						<td>Awaiting</td>
					</tr>';
				echo '<tr>
						<th scope="row">Product sync</th>
						<td>Awaiting</td>
					</tr>';
			break;
			case 'no-rsn-peers-list-yet':
				echo '<tr>
						<th scope="row">Network Access</th>
						<td>Granted</td>
					</tr>';
				echo '<tr>
						<th scope="row">Peers List</th>
						<td>Awaiting</td>
					</tr>';
				echo '<tr>
						<th scope="row">refersell categories list</th>
						<td>Awaiting</td>
					</tr>';
				echo '<tr>
						<th scope="row">Product sync</th>
						<td>Awaiting</td>
					</tr>';
			break;
			case 'no-rsn-categories-list-yet':
				echo '<tr>
						<th scope="row">Network Access</th>
						<td>Granted</td>
					</tr>';
				echo '<tr>
						<th scope="row">Peers List</th>
						<td>Got it</td>
					</tr>';
				echo '<tr>
						<th scope="row">refersell categories list</th>
						<td>Awaiting</td>
					</tr>';
				echo '<tr>
						<th scope="row">Product sync</th>
						<td>Awaiting</td>
					</tr>';
			break;
			case 'no-rsn-products-sync-yet':
				echo '<tr>
						<th scope="row">Network Access</th>
						<td>Granted</td>
					</tr>';
				echo '<tr>
						<th scope="row">Peers List</th>
						<td>Got it</td>
					</tr>';
				echo '<tr>
						<th scope="row">refersell categories list</th>
						<td>Got it</td>
					</tr>';
				echo '<tr>
						<th scope="row">Product sync</th>
						<td>Awaiting</td>
					</tr>';
			break;
			case 'rsn-connection-complete':
				echo '<tr>
						<th scope="row">Network Access</th>
						<td>Granted</td>
					</tr>';
				echo '<tr>
						<th scope="row">Peers List</th>
						<td>Got it</td>
					</tr>';
				echo '<tr>
						<th scope="row">refersell categories list</th>
						<td>Got it</td>
					</tr>';
				echo '<tr>
						<th scope="row">Product sync</th>
						<td>Synced</td>
					</tr>';
			break;
			default:
			break;
		}
			echo '</tbody>';
		echo '</table>';
		echo '</div>';
		echo '<div class="integrate-refersell-admin-half-col" style="width: 50%; min-width:320px; float:left;">';
		if ( !get_page_by_title('refersell Web Services')){
			echo "<h3 class='title'>Create 'Web Services' page</h3>";
			echo '<form method="POST" action="" name="form">';
				wp_nonce_field( 'create_web_services_page', 'create_iwr_page_nonce' ); 
				echo '<p class="submit"><input type="submit" value="Create \'Web Services\' Page" class="button button-primary" name="create-integrate-refersell-pages"></p>';
			echo '</form>';
		}
		$rsn_product_categories = get_option('rsn_product_categories', NULL);
	/*	var_dump($_POST);
		echo '<br/>';
		var_dump($rsn_product_categories);*/
		if(!empty($rsn_product_categories)){
			echo '<h3 class="title">Select categories to sync</h3>';
			echo '<form method="POST" action="" name="form">';
				echo '<div id="categories-list">';
					echo '<ul></ul>';
				echo '</div>';
				
				echo '<script>';
					echo 'jQuery(document).ready(function(){
						';
						echo 'var cat_list ='.json_encode($rsn_product_categories).';
						';
						echo 'jQuery.each(cat_list, function(category, parameters){
							';
							echo "if(parameters['sync']){
									sync = '';
								}else{
									sync = 'checked';
								}
								console.log(sync);
								";
							echo "if(parameters['parent']){
								";
								echo "parent_cat_id = cat_list[parameters['parent']]['rsn_cat_id'];
								";								
								echo "target_list = jQuery('li[data-cat-id=\"'+parent_cat_id+'\"] ul').html();
								";
								echo "if(jQuery.isEmptyObject(target_list)){
									";
									echo "jQuery('li[data-cat-id=\"'+parent_cat_id+'\"]').append('<ul><li data-cat-id=\"'+parameters['rsn_cat_id']+'\"><input type=\"checkbox\" name=\"sync_cats[]\" value=\"'+category+'\" '+sync+'/>'+category+'</li></ul>');
									";
								echo "}else{
									";
									echo "jQuery('li[data-cat-id=\"'+parent_cat_id+'\"] ul').first().append('<li data-cat-id=\"'+parameters['rsn_cat_id']+'\"><input type=\"checkbox\" name=\"sync_cats[]\" value=\"'+category+'\" '+sync+'/>'+category+'</li>');
									";
								echo '}
								';
							echo '}else{
								';
								echo "jQuery('#categories-list ul').first().append('<li data-cat-id=\"'+parameters['rsn_cat_id']+'\"><input type=\"checkbox\" name=\"sync_cats[]\" value=\"'+category+'\" '+sync+'/>'+category+'</li>');
								";
							echo "}
							";
						echo '});
						';
					echo '});
					';
				echo '</script>';
				wp_nonce_field( 'change_category_preference', 'change_cat_pref_nonce' ); 
				echo '<p class="submit"><input type="submit" value="Sync" class="button button-primary" id="submit" name="change-cat-pref-refersell"></p>';
			echo '</form>';
		}
	}	
		echo '</div>';
	echo '</div>';
}

add_action('admin_head', 'integrate_refersell_admin_styles');
function integrate_refersell_admin_styles() {
  echo '<style>
	.integrate-refersell-admin-half-col{
		width: 50%;
		min-width:320px; 
		float:left;
	}
	#categories-list ul{
		margin-left: 25px;
	}
	#categories-list ul:first-child{
		margin-left: 0px;
	}
	#categories-list ul li{
		margin-bottom:0;
	}
	#categories-list input[type="hidden"]{
		height:auto;
	}
  </style>';
}
?>