<?php /*
Plugin Name: Integrate with ReferSell
Plugin URI: http://www.refersell.com/
Description: Integrate your woocommerce website with ReferSell.
Version: 0.1
Author: Monke Tech
Author URI: http://monketech.com/
License:
Text Domain: integrate-refersell
*/
if ( ! defined( 'ABSPATH' ) ){
    exit;
}
register_activation_hook(__FILE__, 'integrate_refersell_activate');
define( 'INTEGRATE_WITH_REFERSELL_PATH', plugin_dir_path( __FILE__ ) );
include( INTEGRATE_WITH_REFERSELL_PATH . 'includes/iwr-admin-initialize.php');
include( INTEGRATE_WITH_REFERSELL_PATH . 'includes/iwr-initialize.php');
include( INTEGRATE_WITH_REFERSELL_PATH . 'includes/iwr-refersell-portal-communication.php');
include( INTEGRATE_WITH_REFERSELL_PATH . 'includes/iwr-broadcast.php');
include( INTEGRATE_WITH_REFERSELL_PATH . 'includes/iwr-modify-media-urls.php');
include( INTEGRATE_WITH_REFERSELL_PATH . 'includes/iwr-integration.php');
include( INTEGRATE_WITH_REFERSELL_PATH . 'includes/iwr-insert-products.php');
include( INTEGRATE_WITH_REFERSELL_PATH . 'includes/iwr-rules.php');
include( INTEGRATE_WITH_REFERSELL_PATH . 'includes/iwr-admin-settings.php');
include( INTEGRATE_WITH_REFERSELL_PATH . 'includes/iwr-activation.php');
include( INTEGRATE_WITH_REFERSELL_PATH . 'includes/iwr-cryptography.php');
include( INTEGRATE_WITH_REFERSELL_PATH . 'includes/iwr-payment-gateway.php');