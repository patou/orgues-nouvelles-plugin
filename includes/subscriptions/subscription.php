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
    $overrides = on_get_subscription_number_overrides($subscription);

    if (empty($start_date)) {
        return;
    }

    // Utiliser la date de prochain paiement ou de fin (la plus récente)
    $date_fin = $next_payment_date;
    if (!empty($end_date) && (!empty($next_payment_date) && $end_date < $next_payment_date || empty($next_payment_date))) {
        $date_fin = $end_date;
    }

    // Calculer les numéros
    $info = on_get_subscription_info($start_date, $date_fin ?: $start_date, $overrides);
    $manual_number_start = isset($overrides['numero_debut']) ? $overrides['numero_debut'] : '';
    $manual_number_end = isset($overrides['numero_fin']) ? $overrides['numero_fin'] : '';

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
        <div class="on-subscription-number-fields wcs-date-input">
            <p class="form-field form-field-wide">
                <label for="number-start"><?php _e('Numéro de début personnalisé', 'orgues-nouvelles'); ?></label>
                <input type="number" class="short" name="number-start" id="number-start" min="0" step="1" value="<?php echo esc_attr($manual_number_start); ?>" />
                <span class="description"><?php _e('Laissez vide pour utiliser le calcul automatique effectué à partir des dates d\'abonnement.', 'orgues-nouvelles'); ?></span>
            </p>
            <p class="form-field form-field-wide">
                <label for="number-end"><?php _e('Numéro de fin personnalisé', 'orgues-nouvelles'); ?></label>
                <input type="number" class="short" name="number-end" id="number-end" min="0" step="1" value="<?php echo esc_attr($manual_number_end); ?>" />
                <span class="description"><?php _e('Laissez vide pour que le numéro de fin soit déterminé automatiquement.', 'orgues-nouvelles'); ?></span>
            </p>
        </div>
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
        $overrides = on_get_subscription_number_overrides($subscription);

        if (empty($start_date)) {
            echo '—';
            return;
        }

        if ($column === 'on_numero_debut') {
            $info = on_get_subscription_info($start_date, $start_date, $overrides);
            echo '<strong>ON-' . esc_html($info['numero_debut']) . '</strong>';
        }

        if ($column === 'on_numero_fin') {
            // Utiliser la date de prochain paiement ou de fin (la plus récente)
            $date_fin = $next_payment_date;
            if (!empty($end_date) && (!empty($next_payment_date) && $end_date < $next_payment_date || empty($next_payment_date))) {
                $date_fin = $end_date;
            }
            
            $info = on_get_subscription_info($start_date, $date_fin ?: $start_date, $overrides);
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

            return $issue_count > 0 ? $issue_count : apply_filters('on_default_subscription_issue_count', 4);
        }

        return 0;
    }
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

if (!function_exists('on_get_subscription_number_overrides')) {
    /**
     * Retourne les éventuels numéros personnalisés stockés sur l'abonnement.
     *
     * @param \WC_Subscription $subscription Subscription instance.
     *
     * @return array
     */
    function on_get_subscription_number_overrides($subscription)
    {
        if (!$subscription || !is_a($subscription, 'WC_Subscription')) {
            return array();
        }

        $overrides = array();
        $numero_start = $subscription->get_meta('number-start', true);
        if ($numero_start !== '' && null !== $numero_start) {
            $overrides['numero_debut'] = max(0, (int) $numero_start);
        }

        $numero_end = $subscription->get_meta('number-end', true);
        if ($numero_end !== '' && null !== $numero_end) {
            $overrides['numero_fin'] = max(0, (int) $numero_end);
        }

        return $overrides;
    }
}

if (!function_exists('on_save_subscription_number_fields')) {
    /**
     * Sauvegarde les numéros personnalisés dans la méta de l'abonnement via l'API WooCommerce.
     *
     * @param int               $post_id      Subscription post ID.
     * @param \WC_Subscription $subscription Subscription object.
     */
    function on_save_subscription_number_fields($post_id, $subscription)
    {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!$subscription instanceof \WC_Subscription) {
            $subscription = function_exists('wcs_get_subscription') ? wcs_get_subscription($post_id) : null;
        }

        if (!$subscription) {
            return;
        }

        $fields = array(
            'number-start' => 'number-start',
            'number-end' => 'number-end',
        );

        $has_changes = false;

        foreach ($fields as $field_name => $meta_key) {
            if (!isset($_POST[$field_name])) {
                continue;
            }

            $raw_value = wp_unslash($_POST[$field_name]);

            if ($raw_value === '' || $raw_value === null) {
                $subscription->delete_meta_data($meta_key);
                $has_changes = true;
                continue;
            }

            $value = max(0, absint($raw_value));
            $subscription->update_meta_data($meta_key, $value);
            $has_changes = true;
        }

        if ($has_changes) {
            $subscription->save();
        }
    }
    add_action('woocommerce_process_shop_subscription_meta', 'on_save_subscription_number_fields', 10, 2);
}

if (!function_exists('on_initialize_subscription_number_bounds')) {
    /**
     * Initialise automatiquement les numéros de début/fin lors de la création d'un abonnement.
     *
     * @param \WC_Subscription $subscription Subscription instance.
     */
    function on_initialize_subscription_number_bounds($subscription)
    {
        if (!$subscription instanceof \WC_Subscription) {
            return;
        }

        $existing_start = $subscription->get_meta('number-start', true);
        $existing_end = $subscription->get_meta('number-end', true);
        if ($existing_start !== '' && $existing_start !== null && $existing_end !== '' && $existing_end !== null) {
            return;
        }

        $start_date = $subscription->get_date('start');
        if (empty($start_date)) {
            return;
        }

        $info = on_get_subscription_info($start_date, $start_date);
        $numero_start = isset($info['numero_debut']) ? (int) $info['numero_debut'] : null;
        if ($numero_start === null) {
            return;
        }

        $issue_count = max(1, (int) on_get_subscription_issue_count($subscription));
        $numero_end = $numero_start + max(0, $issue_count - 1);

        $subscription->update_meta_data('number-start', $numero_start);
        $subscription->update_meta_data('number-end', $numero_end);
        $subscription->save();
    }

    add_action('woocommerce_checkout_subscription_created', 'on_initialize_subscription_number_bounds', 20, 1);
    add_action('woocommerce_admin_created_subscription', 'on_initialize_subscription_number_bounds', 20, 1);
    add_action('woocommerce_subscription_payment_complete', 'on_initialize_subscription_number_bounds', 20, 1);
}

if (!function_exists('on_extend_subscription_number_bounds_on_renewal')) {
    /**
     * Étend le numéro de fin lorsqu'un renouvellement est facturé.
     *
     * @param \WC_Subscription $subscription Subscription instance.
     */
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

