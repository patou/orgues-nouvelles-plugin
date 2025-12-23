<?php

/**
 * Ajoute automatiquement le dernier magazine au panier lors de l'ajout d'un abonnement
 * et le rend gratuit si un abonnement est présent.
 */

// 1. Ajouter le dernier magazine lors de l'ajout d'un abonnement
add_action('woocommerce_add_to_cart', 'on_auto_add_latest_magazine_with_subscription', 10, 6);

function on_auto_add_latest_magazine_with_subscription($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    // Vérifier si le produit ajouté est un abonnement
    $product = wc_get_product($product_id);
    if (!$product || !in_array($product->get_type(), ['subscription', 'variable_subscription'])) {
        return;
    }

    // Récupérer le dernier magazine
    $latest_magazine = on_get_latest_magazine_product();
    if (!$latest_magazine) {
        return;
    }

    $latest_magazine_id = $latest_magazine->get_id();

    // Vérifier si le magazine est déjà dans le panier
    $found = false;
    foreach (WC()->cart->get_cart() as $item) {
        if ($item['product_id'] == $latest_magazine_id) {
            $found = true;
            break;
        }
    }

    // Si pas dans le panier, l'ajouter
    if (!$found) {
        WC()->cart->add_to_cart($latest_magazine_id);
        
        // Ajouter un message de notification
        wc_add_notice(sprintf(__('Le dernier numéro (%s) a été ajouté gratuitement à votre panier avec votre abonnement.', 'orgues-nouvelles'), $latest_magazine->get_name()), 'success');
    }
}

// 2. Rendre le dernier magazine gratuit si un abonnement est dans le panier
add_action('woocommerce_before_calculate_totals', 'on_set_latest_magazine_free_with_subscription', 10, 1);

function on_set_latest_magazine_free_with_subscription($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    // Vérifier si un abonnement est présent dans le panier
    $has_subscription = false;
    foreach ($cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        if ($product && in_array($product->get_type(), ['subscription', 'variable_subscription'])) {
            $has_subscription = true;
            break;
        }
    }

    if (!$has_subscription) {
        return;
    }

    // Récupérer le dernier magazine
    $latest_magazine = on_get_latest_magazine_product();
    if (!$latest_magazine) {
        return;
    }
    $latest_magazine_id = $latest_magazine->get_id();

    // Parcourir le panier pour mettre le prix à 0 pour le dernier magazine
    foreach ($cart->get_cart() as $cart_item) {
        if ($cart_item['product_id'] == $latest_magazine_id) {
            $cart_item['data']->set_price(0);
        }
    }
}
