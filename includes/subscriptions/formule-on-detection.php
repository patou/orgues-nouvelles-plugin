<?php
/**
 * Helpers pour détecter si une cart/order/subscription contient une formule ON.
 * Une formule ON signifie que l'abonnement inclut des magazines papiers.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('on_cart_has_formule_on_product')) {
    /**
     * Vérifie si le panier contient un produit qui génère une formule ON.
     * 
     * @return bool
     */
    function on_cart_has_formule_on_product() {
        if (!function_exists('WC') || !WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (!isset($cart_item['data'])) {
                continue;
            }

            $product = $cart_item['data'];
            if (!$product || !is_a($product, 'WC_Product')) {
                continue;
            }

            // Vérifier si le produit est un produit subscription
            if (!$product->is_type(array('subscription', 'variable-subscription', 'subscription_variation'))) {
                continue;
            }

            // Vérifier que ce produit génère bien une formule ON
            $formule = on_guess_subscription_formule_from_items_product($product);
            if ('ON' == $formule) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('on_order_has_formule_on_product')) {
    /**
     * Vérifie si une commande contient un produit qui génère une formule ON.
     * 
     * @param WC_Order|int $order
     * @return bool
     */
    function on_order_has_formule_on_product($order) {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order || !is_a($order, 'WC_Order')) {
            return false;
        }

        foreach ($order->get_items() as $item) {
            if (!is_object($item) || !is_callable(array($item, 'get_product'))) {
                continue;
            }

            $product = $item->get_product();
            if (!$product || !is_a($product, 'WC_Product')) {
                continue;
            }

            if (!$product->is_type(array('subscription', 'variable-subscription', 'subscription_variation'))) {
                continue;
            }

            $formule = on_guess_subscription_formule_from_items_product($product);
            if ('ON' == $formule) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('on_subscription_has_formule_on')) {
    /**
     * Vérifie si une subscription a une formule ON (abonnement papier).
     * 
     * @param WC_Subscription|int $subscription
     * @return bool
     */
    function on_subscription_has_formule_on($subscription) {
        if (is_numeric($subscription)) {
            $subscription = wcs_get_subscription($subscription);
        }

        if (!$subscription || !is_a($subscription, 'WC_Subscription')) {
            return false;
        }

        $formule = $subscription->get_meta('on_formule', true);
        if ('ON' == $formule) {
            return true;
        }

        // Fallback: vérifier les produits
        foreach ($subscription->get_items() as $item) {
            if (!is_object($item) || !is_callable(array($item, 'get_product'))) {
                continue;
            }

            $product = $item->get_product();
            if (!$product || !is_a($product, 'WC_Product')) {
                continue;
            }

            if (!$product->is_type(array('subscription', 'variable-subscription', 'subscription_variation'))) {
                continue;
            }

            return true;
        }

        return false;
    }
}

if (!function_exists('on_guess_subscription_formule_from_items_product')) {
    /**
     * Helper pour déterminer la formule ON d'un produit.
     * Réutilise la logique de on_guess_subscription_formule_from_items.
     * 
     * @param WC_Product $product
     * @return string Formule ON ou chaîne vide.
     */
    function on_guess_subscription_formule_from_items_product($product) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return '';
        }

        if (!is_callable(array($product, 'get_sku'))) {
            return '';
        }

        if (!function_exists('on_sanitize_subscription_formule')) {
            return '';
        }

        $sku = strtoupper(sanitize_text_field($product->get_sku()));
        $formule = on_sanitize_subscription_formule($sku);
        if ('' !== $formule) {
            return $formule;
        }

        $choices = array_keys(on_get_subscription_formule_choices());
        usort($choices, function ($a, $b) { return strlen($b) - strlen($a); });

        foreach ($choices as $choice) {
            if (false !== strpos($sku, $choice)) {
                return $choice;
            }
        }

        return '';
    }
}

if (!function_exists('on_get_user_formule_on_subscriptions')) {
    /**
     * Récupère tous les abonnements ON (papier) actifs d'un utilisateur.
     * 
     * @param int $user_id
     * @return array Liste des WC_Subscription avec formule ON.
     */
    function on_get_user_formule_on_subscriptions($user_id = 0) {
        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        if ($user_id <= 0) {
            return array();
        }

        if (!function_exists('wcs_get_subscriptions')) {
            return array();
        }

        $subscriptions = wcs_get_subscriptions(array(
            'customer_id' => $user_id,
            'subscription_status' => array('active', 'on-hold', 'pending'),
        ));

        $formule_on_subs = array();
        foreach ($subscriptions as $sub) {
            if (on_subscription_has_formule_on($sub)) {
                $formule_on_subs[] = $sub;
            }
        }

        return $formule_on_subs;
    }
}
