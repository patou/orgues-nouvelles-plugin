<?php
/**
 * Orgues-Nouvelles Plugin
 *
 * @link              https://www.rto.de
 * @since             1.0.0
 * @package           DynamicTags
 *
 * @wordpress-plugin
 * Plugin Name:       Orgues-Nouvelles Plugin
 * Plugin URI:        https://github.com/patou/orgues-nouvelles-plugin/
 * Description:       Plugin pour ajouter des fonctionnalités au site orgues nouvelles.
 * Version:           1.0.0
 * Author:            Patrice de Saint Steban
 * Author URI:        https://patou.dev
 * License:           Apache-2.0
 * License URI:       http://www.apache.org/licenses/LICENSE-2.0
 * Text Domain:       orgues-nouvelles
 * Domain Path:       /languages
 */

function on_load_plugin() {
    load_plugin_textdomain( 'orgues-nouvelles', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    require_once('orgues-nouvelles.php');
    require_once('export-import.php');
    require_once('membership-numero-on.php');
    require_once('shortcode.php');
    require_once('mon-compte.php');
    require_once('membership-require-shipping-address.php');
    require_once('membership-profile-field-checkout-page.php');
    require_once('search.php');
    require_once('url-ressources.php');
    require_once('membership-restricted-message.php');
    require_once('on-admin.php');
    require_once('on-last-magazine.php');
    require_once('last-post-menu-item.php');
    require_once('check-abonnement-france.php');
    require_once('video-thumbnail.php');
    require_once('complete-order.php');
    require_once('membership-import-semicolon.php');
    require_once('edit-compte-gestionnaire.php');
    require_once('update-subscriptions-price.php');
    require_once('phone-orders.php');
    require_once('advanced-order-export.php');

    add_action('admin_enqueue_scripts', 'on_admin_script');
    add_action( 'elementor/widgets/register', 'on_register_search_form_url_widget' );   
}

function on_admin_script($hook)
{
    wp_enqueue_style('on-style', plugin_dir_url(__FILE__) . 'admin.css');
    /**if ($hook == 'user-edit.php') {
        wp_enqueue_style('on-edit-user-style', plugin_dir_url(__FILE__) . 'edit-user/style.css');

        // Enqueue le script
        wp_enqueue_script('on-edit-user-script', plugin_dir_url(__FILE__) . 'edit-user/script.js', array(), null, true);
    }*/
}

function on_register_search_form_url_widget( $widgets_manager ) {
    require_once('search-form-url.php');
	$widgets_manager->register( new \Search_Form_URL() );
}

add_action( 'plugins_loaded', 'on_load_plugin' );
