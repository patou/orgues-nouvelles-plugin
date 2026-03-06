<?php
/**
 * Intégration WooCommerce Subscriptions
 * Affiche les numéros de magazines ON dans les abonnements
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Affiche les numéros ON dans la metabox de détails d'un abonnement
 */
add_action('wcs_subscription_schedule_after_billing_schedule', 'on_display_subscription_numeros', 10, 1);

function on_display_subscription_numeros($subscription) {
    if (!$subscription) {
        return;
    }

    // Récupérer les dates
    $start_date = $subscription->get_date('start');
    $next_payment_date = $subscription->get_date('next_payment');
    $end_date = $subscription->get_date('end');

    if (empty($start_date)) {
        return;
    }

    // Utiliser la date de prochain paiement ou de fin (la plus récente)
    $date_fin = $next_payment_date;
    if (!empty($end_date) && (!empty($next_payment_date) && $end_date < $next_payment_date || empty($next_payment_date))) {
        $date_fin = $end_date;
    }

    // Calculer les numéros
    $info = on_get_subscription_info($start_date, $date_fin);

    // Récupérer le membership lié à cet abonnement
    $user_memberships = wc_memberships_get_user_memberships($subscription->get_user_id());
    $linked_membership = null;
    
    foreach ($user_memberships as $membership) {
        if ($membership instanceof \WC_Memberships_Integration_Subscriptions_User_Membership) {
            $membership_subscription = $membership->get_subscription();
            if ($membership_subscription && $membership_subscription->get_id() == $subscription->get_id()) {
                $linked_membership = $membership;
                break;
            }
        }
    }

    // Afficher les numéros
    ?>
    <div class="on-subscription-numeros">
        <h3 style="margin-top: 0;"><?php _e('Numéros de Orgues-Nouvelles', 'orgues-nouvelles'); ?></h3>
        <table class="shop_table">
            <tbody>
                <tr>
                    <th><?php _e('Numéro de début:', 'orgues-nouvelles'); ?></th>
                    <td><strong>ON-<?php echo esc_html($info['numero_debut']); ?></strong> (<?php echo date_i18n('F Y', strtotime($info['mois_debut'] . '-01')); ?>)</td>
                </tr>
                <tr>
                    <th><?php _e('Numéro de fin:', 'orgues-nouvelles'); ?></th>
                    <td><strong>ON-<?php echo esc_html($info['numero_fin']); ?></strong> (<?php echo date_i18n('F Y', strtotime($info['mois_fin'] . '-01')); ?>)</td>
                </tr>
                <tr>
                    <th><?php _e('Nombre de numéros:', 'orgues-nouvelles'); ?></th>
                    <td><?php echo esc_html($info['nombre_numeros']); ?></td>
                </tr>
            </tbody>
        </table>
        <?php if ($linked_membership): ?>
        <p style="margin-top: 10px;">
            <a href="<?php echo esc_url(admin_url('post.php?post=' . $linked_membership->get_id() . '&action=edit')); ?>" class="button">
                <?php _e('Voir l\'adhésion', 'orgues-nouvelles'); ?>
            </a>
        </p>
        <?php endif; ?>
    </div>
    <?php

}

/**
 * Ajoute des colonnes personnalisées dans la liste des abonnements
 */
add_filter('manage_edit-shop_subscription_columns', 'on_add_subscription_columns', 20);

function on_add_subscription_columns($columns) {
    // Insérer les nouvelles colonnes après la colonne "Total"
    $new_columns = array();
    
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        
        if ($key === 'last_payment_date') {
            $new_columns['on_numero_debut'] = __('N° Début', 'orgues-nouvelles');
            $new_columns['on_numero_fin'] = __('N° Fin', 'orgues-nouvelles');
        }
    }
    
    return $new_columns;
}

/**
 * Remplit les colonnes personnalisées dans la liste des abonnements
 */
add_action('manage_shop_subscription_posts_custom_column', 'on_fill_subscription_columns', 10, 2);

function on_fill_subscription_columns($column, $post_id) {
    if ($column === 'on_numero_debut' || $column === 'on_numero_fin') {
        $subscription = wcs_get_subscription($post_id);
        
        if (!$subscription) {
            echo '—';
            return;
        }

        // Récupérer les dates
        $start_date = $subscription->get_date('start');
        $next_payment_date = $subscription->get_date('next_payment');
        $end_date = $subscription->get_date('end');

        if (empty($start_date)) {
            echo '—';
            return;
        }

        if ($column === 'on_numero_debut') {
            $info = on_get_subscription_info($start_date, '');
            echo '<strong>ON-' . esc_html($info['numero_debut']) . '</strong>';
        }

        if ($column === 'on_numero_fin') {
            // Utiliser la date de prochain paiement ou de fin (la plus récente)
            $date_fin = $next_payment_date;
            if (!empty($end_date) && (!empty($next_payment_date) && $end_date < $next_payment_date || empty($next_payment_date))) {
                $date_fin = $end_date;
            }
            
            $info = on_get_subscription_info($start_date, $date_fin);
            echo '<strong>ON-' . esc_html($info['numero_fin']) . '</strong>';
        }
    }
}

/**
 * Rend les colonnes personnalisées triables
 */
add_filter('manage_edit-shop_subscription_sortable_columns', 'on_make_subscription_columns_sortable');

function on_make_subscription_columns_sortable($columns) {
    $columns['on_numero_debut'] = 'start_date';
    $columns['on_numero_fin'] = 'next_payment_date';
    return $columns;
}

if (!function_exists('on_get_subscription_issue_count')) {
    /**
     * Récupère le nombre de numéros associés à la variation d'abonnement.
     *
     * @param \WC_Subscription $subscription Subscription instance.
     */
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

            $issue_count = (int) get_post_meta($product->get_id(), '_on_issue_count', true);
            if ($issue_count <= 0 && $product->get_parent_id()) {
                $issue_count = (int) get_post_meta($product->get_parent_id(), '_on_issue_count', true);
            }

            return $issue_count > 0 ? $issue_count : 4;
        }

        return 0;
    }
}

if (!function_exists('on_adjust_subscription_next_payment_date')) {
    /**
     * Ajuste la date du prochain paiement pour couvrir le nombre de numéros défini sur la variation.
     *
     * @param string          $next_payment Date calculée par WooCommerce Subscriptions.
     * @param \WC_Subscription $subscription Subscription instance.
     */
    function on_adjust_subscription_next_payment_date($next_payment, $subscription)
    {
        if (!is_object($subscription) || !is_a($subscription, 'WC_Subscription')) {
            return $next_payment;
        }

        $issue_count = on_get_subscription_issue_count($subscription);
        if ($issue_count <= 0) {
            return $next_payment;
        }

        $cycle_start = $subscription->get_date('last_payment');
        if (empty($cycle_start)) {
            $cycle_start = $subscription->get_date('start');
        }

        if (empty($cycle_start)) {
            return $next_payment;
        }

        $cycle_start_date = substr($cycle_start, 0, 10);
        $cycle_window = on_calculate_issue_cycle_window($cycle_start_date, $issue_count);
        $target_timestamp = on_issue_payment_cutoff_timestamp($cycle_window['next_issue']);

        if (empty($target_timestamp)) {
            return $next_payment;
        }

        $current_timestamp = $next_payment ? strtotime($next_payment) : 0;
        if ($current_timestamp && $current_timestamp >= $target_timestamp) {
            return $next_payment;
        }

        return gmdate('Y-m-d H:i:s', $target_timestamp);
    }

    // WooCommerce Subscriptions fires the plural hook name; the singular alias is never triggered.
    add_filter('woocommerce_subscription_calculated_next_payment_date', 'on_adjust_subscription_next_payment_date', 10, 2);
}

if (!function_exists('on_render_issue_count_variation_field')) {
    /**
     * Ajoute un champ numérique permettant de définir le nombre de numéros délivrés par une variation.
     */
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

    /**
     * Sauvegarde la valeur définie sur la variation.
     */
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
