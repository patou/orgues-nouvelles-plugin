<?php
/**
 * Hooks d'affichage liés aux numéros d'abonnement.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('on_render_issue_count_variation_field')) {
    function on_render_issue_count_variation_field($loop, $variation_data, $variation)
    {
        if (!function_exists('wc_get_product')) {
            return;
        }

        $product = wc_get_product($variation->ID);
        if (!$product || !$product->is_type(array('subscription', 'variable-subscription', 'subscription_variation'))) {
            return;
        }

        $value = get_post_meta($variation->ID, '_on_issue_count', true);
        if ('' === $value) {
            $value = 0;
        }

        woocommerce_wp_text_input(array(
            'id' => 'on_issue_count[' . $loop . ']',
            'label' => __('Nombre de numéros par abonnement', 'orgues-nouvelles'),
            'description' => __('Définissez combien de numéros sont inclus avant le prochain paiement.', 'orgues-nouvelles'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => array(
                'min' => 1,
                'step' => 1,
            ),
            'value' => absint($value),
        ));
    }
    add_action('woocommerce_product_after_variable_attributes', 'on_render_issue_count_variation_field', 15, 3);
}

if (!function_exists('on_save_issue_count_variation_field')) {
    function on_save_issue_count_variation_field($variation_id, $index)
    {
        if (!function_exists('wc_get_product')) {
            return;
        }

        $product = wc_get_product($variation_id);
        if (!$product || !$product->is_type(array('subscription', 'variable-subscription', 'subscription_variation'))) {
            return;
        }

        $values = isset($_POST['on_issue_count']) ? wp_unslash($_POST['on_issue_count']) : array();
        $raw_value = isset($values[$index]) ? $values[$index] : '';
        $issue_count = max(0, absint($raw_value));
        update_post_meta($variation_id, '_on_issue_count', $issue_count);
    }
    add_action('woocommerce_save_product_variation', 'on_save_issue_count_variation_field', 10, 2);
}

if (!function_exists('on_add_issue_count_to_price_html')) {
    function on_add_issue_count_to_price_html($price_html, $product)
    {
        if (!is_object($product) || !is_a($product, 'WC_Product')) {
            return $price_html;
        }

        if (!$product->is_type(array('subscription', 'variable-subscription', 'subscription_variation'))) {
            return $price_html;
        }

        $issue_count = on_get_product_issue_count($product);
        $issue_html = on_get_issue_count_suffix_html($issue_count);

        if ('' === $issue_html) {
            return $price_html;
        }

        return $price_html . $issue_html;
    }
    add_filter('woocommerce_get_price_html', 'on_add_issue_count_to_price_html', 25, 2);
}

if (!function_exists('on_add_issue_count_to_cart_price')) {
    function on_add_issue_count_to_cart_price($price_html, $cart_item, $cart_item_key = '')
    {
        $product = isset($cart_item['data']) ? $cart_item['data'] : null;

        if (!$product || !is_a($product, 'WC_Product')) {
            return $price_html;
        }

        if (!$product->is_type(array('subscription', 'variable-subscription', 'subscription_variation'))) {
            return $price_html;
        }

        $issue_count = on_get_product_issue_count($product);
        $issue_html = on_get_issue_count_suffix_html($issue_count);

        if ('' === $issue_html) {
            return $price_html;
        }

        return $price_html . $issue_html;
    }
    add_filter('woocommerce_cart_item_price', 'on_add_issue_count_to_cart_price', 20, 3);
}

if (!function_exists('on_add_issue_count_to_cart_subtotal')) {
    function on_add_issue_count_to_cart_subtotal($subtotal, $cart_item, $cart_item_key)
    {
        $product = isset($cart_item['data']) ? $cart_item['data'] : null;

        if (!$product || !is_a($product, 'WC_Product')) {
            return $subtotal;
        }

        if (!$product->is_type(array('subscription', 'variable-subscription', 'subscription_variation'))) {
            return $subtotal;
        }

        $issue_html = on_get_issue_count_suffix_html(on_get_product_issue_count($product));

        if ('' === $issue_html) {
            return $subtotal;
        }

        return $subtotal . $issue_html;
    }
    add_filter('woocommerce_cart_item_subtotal', 'on_add_issue_count_to_cart_subtotal', 20, 3);
}

if (!function_exists('on_add_issue_count_to_order_line_subtotal')) {
    function on_add_issue_count_to_order_line_subtotal($subtotal, $item, $order)
    {
        if (!is_object($item) || !is_callable(array($item, 'get_product'))) {
            return $subtotal;
        }

        $product = $item->get_product();

        if (!$product || !is_a($product, 'WC_Product')) {
            return $subtotal;
        }

        if (!$product->is_type(array('subscription', 'variable-subscription', 'subscription_variation'))) {
            return $subtotal;
        }

        $issue_html = on_get_issue_count_suffix_html(on_get_product_issue_count($product));

        if ('' === $issue_html) {
            return $subtotal;
        }

        return $subtotal . $issue_html;
    }
    add_filter('woocommerce_order_formatted_line_subtotal', 'on_add_issue_count_to_order_line_subtotal', 20, 3);
}

if (!function_exists('on_recurring_subtotal_issue_context')) {
    function on_recurring_subtotal_issue_context($action = 'get')
    {
        static $depth = 0;

        if ('start' === $action) {
            $depth++;
        } elseif ('stop' === $action) {
            $depth = max(0, $depth - 1);
        }

        return $depth > 0;
    }
}

if (!function_exists('on_start_recurring_subtotal_issue_context')) {
    function on_start_recurring_subtotal_issue_context($recurring_carts)
    {
        on_recurring_subtotal_issue_context('start');
    }
    add_action('woocommerce_subscriptions_recurring_totals_subtotals', 'on_start_recurring_subtotal_issue_context', 1);
}

if (!function_exists('on_stop_recurring_subtotal_issue_context')) {
    function on_stop_recurring_subtotal_issue_context($recurring_carts)
    {
        on_recurring_subtotal_issue_context('stop');
    }
    add_action('woocommerce_subscriptions_recurring_totals_subtotals', 'on_stop_recurring_subtotal_issue_context', 200);
}

if (!function_exists('on_add_issue_count_to_recurring_subtotal_details')) {
    function on_add_issue_count_to_recurring_subtotal_details($details, $cart)
    {
        if (!on_recurring_subtotal_issue_context('get')) {
            return $details;
        }

        if (!isset($details['recurring_amount'])) {
            return $details;
        }

        $issue_html = on_get_issue_count_suffix_html(on_get_recurring_cart_issue_count($cart));

        if ('' === $issue_html) {
            return $details;
        }

        $details['recurring_amount'] .= $issue_html;

        return $details;
    }
    add_filter('woocommerce_cart_subscription_string_details', 'on_add_issue_count_to_recurring_subtotal_details', 20, 2);
}

if (!function_exists('on_add_issue_count_to_recurring_total_html')) {
    function on_add_issue_count_to_recurring_total_html($html, $cart)
    {
        $issue_html = on_get_issue_count_suffix_html(on_get_recurring_cart_issue_count($cart));

        if ('' === $issue_html) {
            return $html;
        }

        return $html . $issue_html;
    }
    add_filter('wcs_cart_totals_order_total_html', 'on_add_issue_count_to_recurring_total_html', 20, 2);
}