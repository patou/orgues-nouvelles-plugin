<?php
/**
 * Colonnes personnalisées des abonnements.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('manage_edit-shop_subscription_columns', 'on_add_subscription_columns', 20);

if (!function_exists('on_add_subscription_columns')) {
    function on_add_subscription_columns($columns) {
        $new_columns = array();

        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;

            if ($key === 'last_payment_date') {
                $new_columns['on_numero_debut'] = __('N° Début', 'orgues-nouvelles');
                $new_columns['on_numero_fin'] = __('N° Fin', 'orgues-nouvelles');
                $new_columns['on_magazine_quantity'] = __('Exemplaires', 'orgues-nouvelles');
            }
        }

        return $new_columns;
    }
}

add_action('manage_shop_subscription_posts_custom_column', 'on_fill_subscription_columns', 10, 2);

if (!function_exists('on_fill_subscription_columns')) {
    function on_fill_subscription_columns($column, $post_id) {
        if ($column === 'on_numero_debut' || $column === 'on_numero_fin' || $column === 'on_magazine_quantity') {
            $subscription = wcs_get_subscription($post_id);

            if (!$subscription) {
                echo '—';
                return;
            }

            $start_date = $subscription->get_date('start');
            $next_payment_date = $subscription->get_date('next_payment');
            $end_date = $subscription->get_date('end');
            $overrides = on_get_subscription_number_overrides($subscription);

            if (empty($start_date)) {
                echo '—';
                return;
            }

            if ($column === 'on_magazine_quantity') {
                echo esc_html(on_get_subscription_magazine_quantity($subscription));
                return;
            }

            if ($column === 'on_numero_debut') {
                $info = on_get_subscription_info($start_date, $start_date, $overrides);
                echo '<strong>ON-' . esc_html($info['numero_debut']) . '</strong>';
            }

            if ($column === 'on_numero_fin') {
                $date_fin = $next_payment_date;
                if (!empty($end_date) && (!empty($next_payment_date) && $end_date < $next_payment_date || empty($next_payment_date))) {
                    $date_fin = $end_date;
                }

                $info = on_get_subscription_info($start_date, $date_fin ?: $start_date, $overrides);
                echo '<strong>ON-' . esc_html($info['numero_fin']) . '</strong>';
            }
        }
    }
}

add_filter('manage_edit-shop_subscription_sortable_columns', 'on_make_subscription_columns_sortable');

if (!function_exists('on_make_subscription_columns_sortable')) {
    function on_make_subscription_columns_sortable($columns) {
        $columns['on_numero_debut'] = 'start_date';
        $columns['on_numero_fin'] = 'next_payment_date';
        return $columns;
    }
}