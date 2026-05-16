<?php
/**
 * Cycle de vie des abonnements.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('on_get_subscription_renewal_info')) {
    function on_get_subscription_renewal_info($subscription)
    {
        $paid_renewals = 0;
        $pending_renewals = 0;
        $issues_per_renewal = 0;

        if (!$subscription instanceof WC_Subscription) {
            return array(
                'paid_renewals' => $paid_renewals,
                'pending_renewals' => $pending_renewals,
                'issues_per_renewal' => $issues_per_renewal,
            );
        }

        $issues_per_renewal = max(0, (int) on_get_subscription_issue_count($subscription));

        $related_orders = $subscription->get_related_orders('renewal');
        if (is_array($related_orders)) {
            foreach ($related_orders as $related_order) {
                $order = $related_order instanceof WC_Order ? $related_order : wc_get_order($related_order);
                if (!$order || !is_a($order, 'WC_Order')) {
                    continue;
                }

                if ($order->is_paid()) {
                    $paid_renewals++;
                } elseif ($order->has_status('pending')) {
                    $pending_renewals++;
                }
            }
        }

        return array(
            'paid_renewals' => $paid_renewals,
            'pending_renewals' => $pending_renewals,
            'issues_per_renewal' => $issues_per_renewal,
        );
    }
}

if (!function_exists('on_initialize_subscription_number_bounds')) {
    function on_initialize_subscription_number_bounds($subscription)
    {
        if (!$subscription instanceof \WC_Subscription) {
            return;
        }

        $start_date = $subscription->get_date('start');
        if (empty($start_date)) {
            return;
        }

        $has_changes = false;

        $existing_start = $subscription->get_meta('number-start', true);
        $existing_end   = $subscription->get_meta('number-end', true);
        if ($existing_start === '' || $existing_start === null || $existing_end === '' || $existing_end === null) {
            $info         = on_get_subscription_info($start_date, $start_date);
            $numero_start = isset($info['numero_debut']) ? (int) $info['numero_debut'] : null;

            if ($numero_start !== null) {
                $issue_count = max(1, (int) on_get_subscription_issue_count($subscription));
                $numero_end  = $numero_start + max(0, $issue_count - 1);

                $subscription->update_meta_data('number-start', $numero_start);
                $subscription->update_meta_data('number-end', $numero_end);
                $has_changes = true;
            }
        }

        $existing_formule = $subscription->get_meta('on_formule', true);
        if ($existing_formule === '' || $existing_formule === null) {
            $formule = on_guess_subscription_formule_from_items($subscription);
            if ('' !== $formule) {
                $subscription->update_meta_data('on_formule', $formule);
                $has_changes = true;
            }
        }

        if ($has_changes) {
            $subscription->save();
        }
    }

    add_action('woocommerce_checkout_subscription_created', 'on_initialize_subscription_number_bounds', 20, 1);
    add_action('woocommerce_admin_created_subscription', 'on_initialize_subscription_number_bounds', 20, 1);
    add_action('woocommerce_subscription_payment_complete', 'on_initialize_subscription_number_bounds', 20, 1);
}

if (!function_exists('on_extend_subscription_number_bounds_on_renewal')) {
    function on_extend_subscription_number_bounds_on_renewal($subscription)
    {
        if (!$subscription instanceof \WC_Subscription) {
            return;
        }

        $issue_count = (int) on_get_subscription_issue_count($subscription);
        if ($issue_count <= 0) {
            return;
        }

        $current_end = $subscription->get_meta('number-end', true);
        if ($current_end === '' || $current_end === null) {
            on_initialize_subscription_number_bounds($subscription);
            $current_end = $subscription->get_meta('number-end', true);
            if ($current_end === '' || $current_end === null) {
                return;
            }
        }

        $new_end = max(0, (int) $current_end) + $issue_count;
        $subscription->update_meta_data('number-end', $new_end);
        $subscription->save();
    }

    add_action('woocommerce_subscription_renewal_payment_complete', 'on_extend_subscription_number_bounds_on_renewal', 20, 2);
}