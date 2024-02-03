<?php
function on_membership_customize_columns( $columns ) {
    $new_columns = array();
    foreach ( $columns as $key => $name ) {
        $new_columns[ $key ] = $name;
        if ( 'member_since' == $key ) {
            $new_columns['numero_since'] = 'Numero début';
        }
        if ( 'expires' == $key ) {
            $new_columns['numero_end'] = 'Numero fin';
        }
    }
    return $new_columns;
}
add_filter( 'manage_edit-wc_user_membership_columns',          'on_membership_customize_columns' );
		

function on_user_membership_screen_columns( $column, $post_id ) {
    echo 'test';
    if ( 'member_since' === $column ) {
        $membership = wc_memberships_get_user_membership( $post_id );
        if ( $membership ) {
            $start_date = $membership->get_start_date();
            if ( $start_date ) {
                echo on_date_magazine_to_numero($start_date);
            } else {
                echo '<span class="na">&ndash;</span>';
            }
        }
    } elseif ( 'expires' === $column ) {
        $membership = wc_memberships_get_user_membership( $post_id );
        if ( $membership ) {
            $end_date = $membership->get_end_date();
            if ( $end_date ) {
                echo on_date_magazine_to_numero($end_date);
            } else {
                echo '<span class="na">&ndash;</span>';
            }
        }
    } 
}
add_action( 'manage_wc_user_membership_custom_column', 'on_user_membership_screen_columns', 5, 2 );