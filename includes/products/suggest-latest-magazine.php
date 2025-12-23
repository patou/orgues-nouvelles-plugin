<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Suggère le dernier magazine dans le panier si un abonnement est présent
 */
function on_suggest_latest_magazine_in_cart() {
    if (!class_exists('WooCommerce') || !class_exists('WC_Subscriptions_Product')) {
        return;
    }

    $cart = WC()->cart;
    if ($cart->is_empty()) {
        return;
    }

    // 1. Vérifier si un abonnement est dans le panier
    $has_subscription = false;
    foreach ($cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        if (WC_Subscriptions_Product::is_subscription($product)) {
            $has_subscription = true;
            break;
        }
    }

    if (!$has_subscription) {
        return;
    }

    // 2. Trouver le dernier magazine
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => 1,
        'tax_query'      => array(
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => 'magazine', // Assurez-vous que le slug est correct
            ),
        ),
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return;
    }

    $latest_magazine = null;
    while ($query->have_posts()) {
        $query->the_post();
        $latest_magazine = wc_get_product(get_the_ID());
    }
    wp_reset_postdata();

    if (!$latest_magazine) {
        return;
    }

    // 3. Vérifier si le dernier magazine est déjà dans le panier
    foreach ($cart->get_cart() as $cart_item) {
        if ($cart_item['product_id'] == $latest_magazine->get_id()) {
            return; // Déjà dans le panier
        }
    }

    // 4. Afficher la suggestion
    $message = sprintf(
        __('Vous vous abonnez ? Profitez-en pour ajouter le dernier numéro <strong>%s</strong> à votre commande ! <a href="%s" class="button">Ajouter au panier</a>', 'orgues-nouvelles'),
        $latest_magazine->get_name(),
        esc_url($latest_magazine->add_to_cart_url())
    );

    wc_print_notice($message, 'notice');
}

add_action('woocommerce_before_cart', 'on_suggest_latest_magazine_in_cart');
