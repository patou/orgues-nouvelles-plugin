<?php
/**
 * Metabox d'édition des abonnements.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes_shop_subscription', 'on_register_subscription_numeros_metabox');

if (!function_exists('on_register_subscription_numeros_metabox')) {
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
}

if (!function_exists('on_render_subscription_numeros_metabox')) {
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
}

if (!function_exists('on_display_subscription_numeros')) {
    function on_display_subscription_numeros($subscription) {
        if (!$subscription instanceof WC_Subscription) {
            return;
        }

        $start_date = $subscription->get_date('start');
        $next_payment_date = $subscription->get_date('next_payment');
        $end_date = $subscription->get_date('end');
        $overrides = on_get_subscription_number_overrides($subscription);

        if (empty($start_date)) {
            return;
        }

        $date_fin = $next_payment_date;
        if (!empty($end_date) && (!empty($next_payment_date) && $end_date < $next_payment_date || empty($next_payment_date))) {
            $date_fin = $end_date;
        }

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
}

if (!function_exists('on_get_subscription_number_overrides')) {
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
        }

        return '';
    }
}

if (!function_exists('on_save_subscription_number_fields')) {
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
            'number-start' => array('meta_key' => 'number-start', 'type' => 'int'),
            'number-end' => array('meta_key' => 'number-end', 'type' => 'int'),
            'on-formule' => array('meta_key' => 'on_formule', 'type' => 'formule'),
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