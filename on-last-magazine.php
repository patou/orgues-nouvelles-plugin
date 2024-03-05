<?php

function on_get_latest_permalink_by_menu_order( $post_type ) {
    $args = array(
      'post_type' => $post_type,
      'order' => 'DESC',
      'orderby' => 'menu_order',
      'posts_per_page' => 1,
    );
  
    $query = new WP_Query( $args );
    try {
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                return get_permalink();
            }
        }
    } finally {
        wp_reset_postdata();
    }
  }