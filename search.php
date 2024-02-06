<?php 

function on_register_query_vars( $vars ) {
    $vars[] = 'numero';
    return $vars;
}
add_filter( 'query_vars', 'on_register_query_vars' );