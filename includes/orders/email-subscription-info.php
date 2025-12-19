<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_email_after_order_table', 'on_add_subscription_info_to_email', 10, 4);

function on_add_subscription_info_to_email($order, $sent_to_admin, $plain_text, $email) {
    if (!function_exists('wcs_get_subscriptions_for_order')) {
        return;
    }

    $subscriptions = wcs_get_subscriptions_for_order($order->get_id(), array('order_type' => 'any'));

    if (empty($subscriptions)) {
        return;
    }

    foreach ($subscriptions as $subscription) {
        $start_date = $subscription->get_date('start');
        $next_payment_date = $subscription->get_date('next_payment');
        $end_date = $subscription->get_date('end');

        if (empty($start_date)) {
            continue;
        }

        // Determine effective end date
        $effective_end_date = $next_payment_date;
        if (!empty($end_date) && (!empty($next_payment_date) && $end_date < $next_payment_date || empty($next_payment_date))) {
            $effective_end_date = $end_date;
        }
        
        // Fallback if no end date is set yet (e.g. new subscription)
        if (empty($effective_end_date)) {
             $effective_end_date = $start_date;
        }

        $info = on_get_subscription_info($start_date, $effective_end_date);

        echo '<h2>' . __('Informations sur votre abonnement', 'orgues-nouvelles') . '</h2>';
        echo '<ul>';
        echo '<li>' . sprintf(
            __('Numéro de début : ON-%s (%s)', 'orgues-nouvelles'), 
            $info['numero_debut'],
            date_i18n('F Y', strtotime($info['mois_debut'] . '-01'))
        ) . '</li>';
        echo '<li>' . sprintf(
            __('Numéro de fin : ON-%s (%s)', 'orgues-nouvelles'), 
            $info['numero_fin'],
            date_i18n('F Y', strtotime($info['mois_fin'] . '-01'))
        ) . '</li>';
        echo '<li>' . sprintf(
            __('Nombre de numéros : %s', 'orgues-nouvelles'), 
            $info['nombre_numeros']
        ) . '</li>';
        echo '</ul>';
    }
}
