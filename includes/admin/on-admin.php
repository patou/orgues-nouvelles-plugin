<?php 


function on_modify_admin_bar( WP_Admin_Bar $wp_admin_bar ){

    $wp_admin_bar->add_node( array(
        'parent' => 'wp-logo',
        'id' => 'help-orgue-nouvelles',
        'title' => 'Aide orgues-nouvelles',
        'href' => 'https://docs.google.com/document/d/14lVdgJMgjQ-0PeOKe_ijU0xiAHEUweK5nghmU1vjX7s/edit?usp=sharing',
        'meta' => array(
          'target' => '_blank',
        ),
      ) );
}

add_action( 'admin_bar_menu', 'on_modify_admin_bar', 400 );

// Bypass reCaptcha Woo extension on admin pages
function on_bypass_recaptcha_pour_admin()
{
  if (is_admin()) {
    // Supprime la fonction de l'extension du hook
    remove_action('woocommerce_register_post', 'rcfwc_woo_register_check', 10, 3);
  }
}
add_action('admin_init', 'on_bypass_recaptcha_pour_admin');

// Add Polylang menu for non-admin users
add_action( 'admin_menu', function() {
	if ( ! current_user_can( 'loco_admin' ) && function_exists( 'PLL' ) ) {
		add_menu_page( 
			__( 'Strings translations', 'polylang' ),
			__( 'Translations', 'polylang' ),
			'edit_pages',
			'mlang_strings',
			array( PLL(), 'languages_page' ),
			'dashicons-translation'
		);
	}
} );