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

    // edit the query only when post type is 'ressource'
    // if it isn't, return
    /*if ( !is_post_type_archive( 'ressource' ) ){
        return;
    }*/

    $meta_query = $query->get( 'meta_query' );

    if ( !is_array( $meta_query ) ){
        $meta_query = array();
    }

    // add meta_query elements
    if( !empty( get_query_var( 'numero' ) ) ){
        $meta_query[] = array( 'key' => 'numero', 'value' => get_query_var( 'numero' ), 'compare' => '=' );
    }

    if ($query->is_post_type_archive( 'ressource' ) || $query->is_tax( 'type_de_ressource' )) {
        $numeros = on_liste_numeros();
        if (!empty($numeros)) {
            $meta_query[] = array(
                'key' => 'numero',
                'value' => $numeros,
                'compare' => 'IN'
            );
        }
    }

    if( count( $meta_query ) > 0 ){
        $query->set( 'meta_query', $meta_query );
    }
}
add_action( 'pre_get_posts', 'on_pre_get_posts', 1 );

// Add Bold to searched term
function on_highlight_results($text)
{
    if (is_search() && !is_admin()) {
        $sr = get_query_var('s');
        $keys = explode(" ", $sr);
        $keys = array_filter($keys);
        $regEx = '\'(?!((<.*?)|(<a.*?)))(\b' . implode('|', $keys) . '\b)(?!(([^<>]*?)>)|([^>]*?</a>))\'iu';
        $text = preg_replace($regEx, '<strong class="search-highlight">\0</strong>', $text);
    }
    return $text;
}
add_filter('the_excerpt', 'on_highlight_results');
add_filter('the_title', 'on_highlight_results');