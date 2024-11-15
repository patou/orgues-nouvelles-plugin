<?php

// Afficher les variations d'un produit dans l'autocomplétion dans l'extension WooCommerce Phone Orders avec le hook wpo_autocomplete_product_custom_output
add_filter('wpo_autocomplete_product_custom_output', 'on_autocomplete_product_custom_output', 10, 2);
function on_autocomplete_product_custom_output($output, $product) {
    $data = array();
    $data['name'] = '<b>' .rawurldecode($product->get_name()) .'</b>';
    $data['price'] = $product->get_price_html();
    if ($product->is_type('variation')) {
        // Afficher les variations data du produit : taille, couleur, ...
        $data['variation'] = wc_get_formatted_variation($product, true);
    }

    return join('<br/>', array_filter($data));
}



