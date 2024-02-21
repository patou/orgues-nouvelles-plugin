<?php

function on_wc_memberships_message_products_merge_tag_replacement( $products_merge_tag, $products, $message, $args ) {
    return '<a href="/product-category/abonnement/">abonnement à Orgues-Nouvelles</a>';
}
add_filter( 'wc_memberships_message_products_merge_tag_replacement', 'on_wc_memberships_message_products_merge_tag_replacement', 10, 4 );
