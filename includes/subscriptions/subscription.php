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
 * Ajoute la meta box affichant les numéros ON sur les abonnements.
 */
add_action('add_meta_boxes_shop_subscription', 'on_register_subscription_numeros_metabox');

function on_register_subscription_numeros_metabox() {
    add_meta_box(
        'on-subscription-numeros',
        __('Orgues-Nouvelles', 'orgues-nouvelles'),
        'on_render_subscription_numeros_metabox',
        'shop_subscription',
        'normal',
        'high'
    );
}

function on_render_subscription_numeros_metabox($post) {
    if (!function_exists('wcs_get_subscription')) {
        return;
    }

    $subscription = wcs_get_subscription($post->ID);

    if (!$subscription instanceof WC_Subscription) {
        return;
    }

    on_display_subscription_numeros($subscription);
}

function on_display_subscription_numeros($subscription) {
    if (!$subscription instanceof WC_Subscription) {
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
    $formule_value = on_sanitize_subscription_formule($subscription->get_meta('on_formule', true));
    if ('' === $formule_value) {
        $formule_value = on_guess_subscription_formule_from_items($subscription);
    }
    $formule_choices = on_get_subscription_formule_choices();
    $magazine_quantity = on_get_subscription_magazine_quantity($subscription);
    $renewal_info = on_get_subscription_renewal_info($subscription);

    // Afficher les numéros
    ?>
    <div class="on-subscription-numeros">
        <div class="on-subscription-numeros-column on-subscription-numeros-column-summary">
            <h3><?php _e('Numéros de Orgues-Nouvelles', 'orgues-nouvelles'); ?></h3>
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
                        <th><?php _e('Exemplaires à envoyer:', 'orgues-nouvelles'); ?></th>
                        <td><?php echo esc_html($magazine_quantity); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Nombre de numéros:', 'orgues-nouvelles'); ?></th>
                        <td><?php echo esc_html($info['nombre_numeros']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Renouvellements effectués:', 'orgues-nouvelles'); ?></th>
                        <td><?php 
                            echo esc_html($renewal_info['paid_renewals']);
                            if ($renewal_info['pending_renewals'] > 0) {
                                echo ' (' . esc_html($renewal_info['pending_renewals']) . ' ' . esc_html(__('en attente', 'orgues-nouvelles')) . ')';
                            }
                        ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Numéros par renouvellement:', 'orgues-nouvelles'); ?></th>
                        <td><?php echo esc_html($renewal_info['issues_per_renewal']); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="on-subscription-numeros-column on-subscription-numeros-column-fields">
            <div class="on-subscription-number-fields wcs-date-input">
                <p class="form-field form-field-wide">
                    <label for="on-formule"><?php _e('Formule', 'orgues-nouvelles'); ?></label>
                    <select name="on-formule" id="on-formule" class="postform">
                        <option value=""><?php esc_html_e('Sélectionnez une formule', 'orgues-nouvelles'); ?></option>
                        <?php foreach ($formule_choices as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($formule_value, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="description"><?php _e('Valeur utilisée pour suivre la formule d\'abonnement.', 'orgues-nouvelles'); ?></span>
                </p>
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
        </div>
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
            $new_columns['on_magazine_quantity'] = __('Exemplaires', 'orgues-nouvelles');
        }
    }
    
    return $new_columns;
}

/**
 * Remplit les colonnes personnalisées dans la liste des abonnements
 */
add_action('manage_shop_subscription_posts_custom_column', 'on_fill_subscription_columns', 10, 2);

function on_fill_subscription_columns($column, $post_id) {
    if ($column === 'on_numero_debut' || $column === 'on_numero_fin' || $column === 'on_magazine_quantity') {
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

        if ($column === 'on_magazine_quantity') {
            echo esc_html(on_get_subscription_magazine_quantity($subscription));
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

if (!function_exists('on_get_subscription_magazine_quantity')) {
    /**
     * Retourne le nombre total d'exemplaires à envoyer pour un abonnement.
     *
     * @param \WC_Subscription $subscription Subscription instance.
     *
     * @return int
     */
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

if (!function_exists('on_get_product_issue_count')) {
    /**
     * Retourne le nombre de numéros configuré sur un produit (variation ou parent).
     *
     * @param \WC_Product|int $product Product instance or ID.
     *
     * @return int
     */
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
    /**
     * Retourne le suffixe HTML affichant le nombre de numéros.
     *
     * @param int $issue_count Number of issues.
     *
     * @return string
     */
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

        return sprintf(
            ' <span class="on-price-issue-count">- %s</span>',
            esc_html($issue_text)
        );
    }
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

            $issue_count = on_get_product_issue_count($product);
            if ($issue_count > 0) {
                return $issue_count;
            }
        }

        return 0;
    }
}

if (!function_exists('on_get_subscription_renewal_info')) {
    /**
     * Récupère le nombre de renouvellements effectués et le nombre de numéros par renouvellement.
     *
     * @param \WC_Subscription $subscription Subscription instance.
     *
     * @return array {
     *     'paid_renewals' => int,         // Nombre de commandes de renouvellement payées
     *     'pending_renewals' => int,      // Nombre de commandes de renouvellement en attente de paiement
     *     'issues_per_renewal' => int,    // Nombre de numéros par cycle/renouvellement
     * }
     */
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

        // Récupérer le nombre de numéros par cycle
        $issues_per_renewal = max(0, (int) on_get_subscription_issue_count($subscription));

        // Compter les commandes de renouvellement liées à cette subscription.
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

if (!function_exists('on_get_recurring_cart_issue_count')) {
    /**
     * Calcule le nombre total de numéros représenté dans un panier récurrent.
     *
     * @param \WC_Cart $cart Cart instance.
     *
     * @return int
     */
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

if (!function_exists('on_add_issue_count_to_price_html')) {
    /**
     * Ajoute le nombre de numéros après le prix des abonnements.
     *
     * @param string      $price_html HTML du prix.
     * @param \WC_Product $product    Produit courant.
     */
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
    /**
     * Ajoute le nombre de numéros après le prix dans le panier.
     *
     * @param string $price_html Current price HTML.
     * @param array  $cart_item  Cart item data.
     */
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
    /**
     * Ajoute le nombre de numéros après le sous-total des articles du panier.
     */
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
    /**
     * Ajoute le nombre de numéros aux lignes d'une commande.
     */
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
    /**
     * Gère l'état de contexte lors du rendu des sous-totaux récurrents.
     *
     * @param string $action start|stop|get.
     *
     * @return bool
     */
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
    /**
     * Ajoute le suffixe aux sous-totaux récurrents.
     */
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
    /**
     * Ajoute le suffixe au total récurrent.
     */
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

if (!function_exists('on_get_subscription_formule_choices')) {
    /**
     * Retourne les choix disponibles pour la formule.
     *
     * @return array
     */
    function on_get_subscription_formule_choices()
    {
        $choices = array(
            'ON' => __('ON', 'orgues-nouvelles'),
            'ONED' => __('ONED', 'orgues-nouvelles'),
            'ONEDA' => __('ONEDA', 'orgues-nouvelles'),
        );

        return (array) apply_filters('on_subscription_formule_choices', $choices);
    }
}

if (!function_exists('on_sanitize_subscription_formule')) {
    /**
     * Nettoie et valide la valeur de la formule.
     *
     * @param mixed $value Raw value.
     *
     * @return string
     */
    function on_sanitize_subscription_formule($value)
    {
        if (!is_scalar($value)) {
            return '';
        }

        $value = strtoupper(sanitize_text_field((string) $value));
        $choices = array_keys(on_get_subscription_formule_choices());

        return in_array($value, $choices, true) ? $value : '';
    }
}

if (!function_exists('on_guess_subscription_formule_from_items')) {
    /**
     * Devine la formule à partir du SKU du premier produit d'abonnement.
     *
     * @param \WC_Subscription $subscription Subscription instance.
     *
     * @return string
     */
    function on_guess_subscription_formule_from_items($subscription)
    {
        if (!$subscription instanceof \WC_Subscription) {
            return '';
        }

        foreach ($subscription->get_items() as $item) {
            $product = $item->get_product();

            if (!$product || !is_callable(array($product, 'get_sku'))) {
                continue;
            }

            $sku = strtoupper(sanitize_text_field($product->get_sku()));

            // Correspondance exacte d'abord.
            $formule = on_sanitize_subscription_formule($sku);
            if ('' !== $formule) {
                return $formule;
            }

            // Correspondance partielle : chercher si un choix est contenu dans le SKU.
            // Trier par longueur décroissante pour éviter que "ON" ne corresponde avant "ONED" ou "ONEDA".
            $choices = array_keys(on_get_subscription_formule_choices());
            usort($choices, function ($a, $b) { return strlen($b) - strlen($a); });

            foreach ($choices as $choice) {
                if (false !== strpos($sku, $choice)) {
                    return $choice;
                }
            }
        }

        return '';
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
            'number-start' => array(
                'meta_key' => 'number-start',
                'type' => 'int',
            ),
            'number-end' => array(
                'meta_key' => 'number-end',
                'type' => 'int',
            ),
            'on-formule' => array(
                'meta_key' => 'on_formule',
                'type' => 'formule',
            ),
        );

        $has_changes = false;

        foreach ($fields as $field_name => $field_config) {
            if (!isset($_POST[$field_name])) {
                continue;
            }

            $raw_value = wp_unslash($_POST[$field_name]);

            if ($raw_value === '' || $raw_value === null) {
                $subscription->delete_meta_data($field_config['meta_key']);
                $has_changes = true;
                continue;
            }

            if ('formule' === $field_config['type']) {
                $value = on_sanitize_subscription_formule($raw_value);

                if ('' === $value) {
                    $subscription->delete_meta_data($field_config['meta_key']);
                    $has_changes = true;
                    continue;
                }
            } else {
                $value = max(0, absint($raw_value));
            }

            $subscription->update_meta_data($field_config['meta_key'], $value);
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

        $start_date = $subscription->get_date('start');
        if (empty($start_date)) {
            return;
        }

        $has_changes = false;

        // Initialiser les bornes de numéros si elles ne sont pas encore définies.
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

        // Initialiser la formule si elle n'est pas encore définie.
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

