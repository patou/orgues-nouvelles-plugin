<?php
/**
 * Force l'adresse de livraison obligatoire pour les abonnements papier (formule ON).
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('woocommerce_cart_needs_shipping_address', 'on_cart_needs_shipping_for_formule_on');
add_filter('woocommerce_order_needs_shipping_address', 'on_order_needs_shipping_for_formule_on', 10, 3);

if (!function_exists('on_cart_needs_shipping_for_formule_on')) {
    /**
     * Force l'adresse de livraison si le panier contient un produit formule ON.
     */
    function on_cart_needs_shipping_for_formule_on($needs_shipping_address) {
        // Si c'est déjà nécessaire, ne pas modifier
        if ($needs_shipping_address) {
            return $needs_shipping_address;
        }

        // Vérifier si le panier contient formule ON
        if (on_cart_has_formule_on_product()) {
            return true;
        }

        return $needs_shipping_address;
    }
}

if (!function_exists('on_order_needs_shipping_for_formule_on')) {
    /**
     * Force l'adresse de livraison pour une commande avec formule ON.
     */
    function on_order_needs_shipping_for_formule_on($needs_shipping_address, $hide, $order) {
        // Si c'est déjà nécessaire, ne pas modifier
        if ($needs_shipping_address) {
            return $needs_shipping_address;
        }

        // Vérifier si la commande contient formule ON
        if (on_order_has_formule_on_product($order)) {
            return true;
        }

        return $needs_shipping_address;
    }
}
