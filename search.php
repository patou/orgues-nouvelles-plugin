<?php 

function on_register_query_vars( $vars ) {
    $vars[] = 'numero';
    return $vars;
}
add_filter( 'query_vars', 'on_register_query_vars' );

function on_pre_get_posts( $query ) {
    // check if the user is requesting an admin page 
    // or current query is not the main query
    if ( is_admin() || ! $query->is_main_query() ){
        return;
    }

    // edit the query only when post type is 'accommodation'
    // if it isn't, return
    if ( !is_post_type_archive( 'ressource' ) ){
        return;
    }

    $meta_query = array();

    // add meta_query elements
    if( !empty( get_query_var( 'numero' ) ) ){
        $meta_query[] = array( 'key' => 'numero', 'value' => get_query_var( 'numero' ), 'compare' => '=' );
    }

    if( count( $meta_query ) > 0 ){
        $query->set( 'meta_query', $meta_query );
    }
}
add_action( 'pre_get_posts', 'on_pre_get_posts', 1 );