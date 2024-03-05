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
