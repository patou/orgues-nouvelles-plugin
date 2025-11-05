<?php
/**
 * Orgues-Nouvelles Plugin
 *
 * @link              https://github.com/patou/orgues-nouvelles-plugin/
 * @since             1.0.0
 * @package           Orgues-Nouvelles Plugin
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
    require_once(__DIR__ . '/orgues-nouvelles.php');
    require_once(__DIR__ . '/export-import.php');
    require_once(__DIR__ . '/membership-numero-on.php');
    require_once(__DIR__ . '/shortcode.php');
    require_once(__DIR__ . '/mon-compte.php');
    require_once(__DIR__ . '/membership-require-shipping-address.php');
    require_once(__DIR__ . '/membership-profile-field-checkout-page.php');
    require_once(__DIR__ . '/search.php');
    require_once(__DIR__ . '/url-ressources.php');
    require_once(__DIR__ . '/membership-restricted-message.php');
    require_once(__DIR__ . '/on-admin.php');
    require_once(__DIR__ . '/on-last-magazine.php');
    require_once(__DIR__ . '/last-post-menu-item.php');
    require_once(__DIR__ . '/check-abonnement-france.php');
    require_once(__DIR__ . '/video-thumbnail.php');
    require_once(__DIR__ . '/complete-order.php');
    require_once(__DIR__ . '/justificatif-etudiant.php');
    require_once(__DIR__ . '/membership-import-semicolon.php');
    require_once(__DIR__ . '/membership-export-members.php');
    require_once(__DIR__ . '/edit-compte-gestionnaire.php');
    require_once(__DIR__ . '/update-subscriptions-price.php');
    require_once(__DIR__ . '/phone-orders.php');
    require_once(__DIR__ . '/advanced-order-export.php');
    require_once(__DIR__ . '/restrict-magazines.php');
    require_once(__DIR__ . '/product-free-for-plans.php');

    add_action('admin_enqueue_scripts', 'on_admin_script');
    add_action('wp_enqueue_scripts', 'on_css_script');
    add_action( 'elementor/widgets/register', 'on_register_elementor_widget' );   
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

function on_css_script($hook)
{
    wp_enqueue_style('on-style', plugin_dir_url(__FILE__) . 'style.css');
}


function on_register_elementor_widget( $widgets_manager ) {
    require_once(__DIR__ . '/widget/search-form-url.php');
    require_once(__DIR__ . '/widget/search-result-content.php');
	$widgets_manager->register( new \Search_Form_URL() );
    $widgets_manager->register( new \Search_Result_Content());
}

add_action( 'plugins_loaded', 'on_load_plugin' );
