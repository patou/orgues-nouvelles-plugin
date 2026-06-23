<?php
/**
 * Helpers métier liés aux numéros d'abonnement.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('on_get_product_issue_count')) {
    function on_get_product_issue_count($product)
    {
        if (is_numeric($product)) {
            $product = wc_get_product($product);
        }

        if (!$product || !is_a($product, 'WC_Product')) {
            return 0;
        }

        $issue_count = (int) get_post_meta($product->get_id(), '_on_issue_count', true);
        if ($issue_count <= 0 && $product->get_parent_id()) {
            $issue_count = (int) get_post_meta($product->get_parent_id(), '_on_issue_count', true);
        }

        if ($issue_count <= 0) {
            $issue_count = (int) apply_filters('on_default_subscription_issue_count', 4, $product);
        }

        return max(0, $issue_count);
    }
}

if (!function_exists('on_get_issue_count_suffix_html')) {
    function on_get_issue_count_suffix_html($issue_count)
    {
        $issue_count = (int) $issue_count;

        if ($issue_count <= 0) {
            return '';
        }

        $issue_text = sprintf(
            _n('%s numéro', '%s numéros', $issue_count, 'orgues-nouvelles'),
            number_format_i18n($issue_count)
        );

        return sprintf(' <span class="on-price-issue-count">- %s</span>', esc_html($issue_text));
    }
}

if (!function_exists('on_get_subscription_magazine_quantity')) {
    function on_get_subscription_magazine_quantity($subscription)
    {
        if (!is_object($subscription) || !is_a($subscription, 'WC_Subscription')) {
            return 1;
        }

        $total_quantity = 0;

        foreach ($subscription->get_items() as $item) {
            if (!is_object($item) || !is_callable(array($item, 'get_quantity'))) {
                continue;
            }

            $quantity = (int) $item->get_quantity();
            if ($quantity > 0) {
                $total_quantity += $quantity;
            }
        }

        return max(1, $total_quantity);
    }
}

if (!function_exists('on_get_subscription_issue_count')) {
    function on_get_subscription_issue_count($subscription)
    {
        if (!is_object($subscription) || !is_a($subscription, 'WC_Subscription')) {
            return 0;
        }

        foreach ($subscription->get_items() as $item) {
            $product = $item->get_product();
            if (!$product || !$product->is_type(array('subscription', 'variable-subscription', 'subscription_variation'))) {
                continue;
            }

            $issue_count = on_get_product_issue_count($product);
            if ($issue_count > 0) {
                return $issue_count;
            }
        }

        return 0;
    }
}

if (!function_exists('on_get_recurring_cart_issue_count')) {
    function on_get_recurring_cart_issue_count($cart)
    {
        if (!is_a($cart, 'WC_Cart')) {
            return 0;
        }

        $issue_count = 0;

        foreach ($cart->get_cart() as $cart_item) {
            $product = isset($cart_item['data']) ? $cart_item['data'] : null;

            if (!$product || !is_a($product, 'WC_Product')) {
                continue;
            }

            if (!$product->is_type(array('subscription', 'variable-subscription', 'subscription_variation'))) {
                continue;
            }

            $item_issue_count = on_get_product_issue_count($product);

            if ($item_issue_count <= 0) {
                continue;
            }

            $quantity = isset($cart_item['quantity']) ? max(1, (int) $cart_item['quantity']) : 1;
            $issue_count += $item_issue_count * $quantity;
        }

        return $issue_count;
    }
}