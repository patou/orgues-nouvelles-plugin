<?php
/**
 * Filtre de type d'export pour WooCommerce Order Export.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('on_add_export_type_filter')) {
    /**
    * Ajoute l'option visuelle en haut de l'écran de configuration de l'export.
     *
     * @param array $settings Paramètres du profil d'export.
     * @return void
     */
    function on_add_export_type_filter($settings)
    {
        $current_type = isset($settings['custom_export_type']) ? $settings['custom_export_type'] : 'shop_order';
        ?>
        <div class="my-block">
            <div class="my-block-heading">
                <h4 class="panel-title"><?php esc_html_e('Type d\'export de données', 'orgues-nouvelles'); ?></h4>
            </div>
            <div class="my-block-body">
                <p><?php esc_html_e('Choisissez le type d\'entité principale à extraire de la base de données :', 'orgues-nouvelles'); ?></p>
                <label style="margin-right: 20px; font-weight: normal;">
                    <input type="radio" name="settings[custom_export_type]" value="shop_order" <?php checked($current_type, 'shop_order'); ?>>
                    <strong><?php esc_html_e('Commandes standard', 'orgues-nouvelles'); ?></strong> <?php esc_html_e('(Ventes et renouvellements)', 'orgues-nouvelles'); ?>
                </label>
                <label style="font-weight: normal;">
                    <input type="radio" name="settings[custom_export_type]" value="shop_subscription" <?php checked($current_type, 'shop_subscription'); ?>>
                    <strong><?php esc_html_e('Contrats d\'Abonnement', 'orgues-nouvelles'); ?></strong> <?php esc_html_e('(Profils globaux WooCommerce Subscriptions)', 'orgues-nouvelles'); ?>
                </label>
            </div>
        </div>
        <br />
        <?php
    }

    add_action('woe_settings_form_view_top', 'on_add_export_type_filter');
}

if (!function_exists('on_get_subscription_export_statuses')) {
    /**
     * Retourne les statuts disponibles pour filtrer les contrats d'abonnement.
     *
     * @return array
     */
    function on_get_subscription_export_statuses()
    {
        if (function_exists('wcs_get_subscription_statuses')) {
            return (array) wcs_get_subscription_statuses();
        }

        return (array) wc_get_order_statuses();
    }
}

if (!function_exists('on_get_selected_subscription_statuses')) {
    /**
     * Lit et nettoie la sélection des statuts d'abonnement dans les paramètres d'export.
     *
     * @param array $settings Paramètres du profil d'export.
     * @return array
     */
    function on_get_selected_subscription_statuses($settings)
    {
        $selected = isset($settings['on_subscription_statuses']) && is_array($settings['on_subscription_statuses'])
            ? $settings['on_subscription_statuses']
            : array();

        $selected = array_map('sanitize_key', $selected);
        $selected = array_filter($selected);

        $allowed_statuses = array_keys(on_get_subscription_export_statuses());

        return array_values(array_intersect($selected, $allowed_statuses));
    }
}

if (!function_exists('on_get_subscription_export_plan_choices')) {
    /**
     * Retourne les plans utilisables dans les filtres d'export.
     *
     * @return array
     */
    function on_get_subscription_export_plan_choices()
    {
        if (!function_exists('on_get_subscription_formule_choices')) {
            return array();
        }

        $choices = (array) on_get_subscription_formule_choices();
        $normalized = array();

        foreach ($choices as $key => $label) {
            $normalized_key = strtolower(sanitize_key($key));
            if ('' === $normalized_key) {
                continue;
            }

            $normalized[$normalized_key] = $label;
        }

        return $normalized;
    }
}

if (!function_exists('on_render_subscription_export_destinations_filters')) {
    /**
     * Affiche les filtres dédiés aux contrats d'abonnement dans la colonne destinations.
     *
     * @param array $settings Paramètres du profil d'export.
     * @return void
     */
    function on_render_subscription_export_destinations_filters($settings)
    {
        $current_type = isset($settings['custom_export_type']) ? $settings['custom_export_type'] : 'shop_order';

        $allowed_plans = on_get_subscription_export_plan_choices();

        $selected_plan = isset($settings['on_subscription_plan']) ? strtolower(sanitize_text_field((string) $settings['on_subscription_plan'])) : '';
        if (!isset($allowed_plans[$selected_plan])) {
            $selected_plan = '';
        }

        $selected_issue_number = isset($settings['on_subscription_issue_number'])
            ? absint($settings['on_subscription_issue_number'])
            : '';

        $selected_statuses = on_get_selected_subscription_statuses($settings);
        $status_choices = on_get_subscription_export_statuses();
        ?>
        <div id="on-subscription-export-filters" class="my-block" <?php if ('shop_subscription' !== $current_type) : ?>style="display:none;"<?php endif; ?>>
            <span class="my-hide-next">
                <?php esc_html_e('Filtres contrats d\'abonnement', 'orgues-nouvelles'); ?>
                <span class="ui-icon ui-icon-triangle-1-s my-icon-triangle"></span>
            </span>
            <div class="hide">
                <div>
                    <span class="wc-oe-header"><?php esc_html_e('Statuts', 'orgues-nouvelles'); ?></span>
                    <select name="settings[on_subscription_statuses][]" class="select2-i18n" multiple="multiple" style="width: 100%; max-width: 25%;">
                        <?php foreach ($status_choices as $status_key => $status_label) : ?>
                            <option value="<?php echo esc_attr($status_key); ?>" <?php selected(in_array($status_key, $selected_statuses, true), true); ?>>
                                <?php echo esc_html($status_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-top:10px;">
                    <label for="on-subscription-plan-filter" class="wc-oe-header"><?php esc_html_e('Plan', 'orgues-nouvelles'); ?></label>
                    <select id="on-subscription-plan-filter" name="settings[on_subscription_plan]" style="width: 100%; max-width: 25%;">
                        <option value=""><?php esc_html_e('Tous les plans', 'orgues-nouvelles'); ?></option>
                        <?php foreach ($allowed_plans as $plan_value => $plan_label) : ?>
                            <option value="<?php echo esc_attr($plan_value); ?>" <?php selected($selected_plan, $plan_value); ?>>
                                <?php echo esc_html($plan_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-top:10px;">
                    <label for="on-subscription-issue-number-filter" class="wc-oe-header"><?php esc_html_e('Numéro de magazine', 'orgues-nouvelles'); ?></label>
                    <input type="number" id="on-subscription-issue-number-filter" name="settings[on_subscription_issue_number]" min="0" step="1" value="<?php echo esc_attr($selected_issue_number); ?>" style="width: 100%; max-width: 180px;" />
                    <p class="description"><?php esc_html_e('Filtre les contrats où le numéro est compris entre number-start et number-end.', 'orgues-nouvelles'); ?></p>
                </div>
            </div>
        </div>
        <br />
        <script>
            jQuery(function($) {
                function onToggleSubscriptionFilters() {
                    var selectedType = $('input[name="settings[custom_export_type]"]:checked').val();
                    if (selectedType === 'shop_subscription') {
                        $('#on-subscription-export-filters').show();
                    } else {
                        $('#on-subscription-export-filters').hide();
                    }
                }

                $(document).on('change', 'input[name="settings[custom_export_type]"]', onToggleSubscriptionFilters);
                onToggleSubscriptionFilters();
            });
        </script>
        <?php
    }

    add_action('woe_settings_form_view_destinations', 'on_render_subscription_export_destinations_filters', 5, 1);
}

if (!function_exists('on_save_export_type_filter')) {
    /**
     * Sauvegarde le type d'export choisi dans la configuration du profil.
     *
     * @param array $settings Paramètres du profil d'export.
     * @return array
     */
    function on_save_export_type_filter($settings)
    {
        $allowed_plans = array_keys(on_get_subscription_export_plan_choices());
        $posted_settings = isset($_POST['settings']) && is_array($_POST['settings']) ? wp_unslash($_POST['settings']) : array();

        if (isset($posted_settings['custom_export_type'])) {
            $settings['custom_export_type'] = sanitize_text_field((string) $posted_settings['custom_export_type']);
        }

        $selected_statuses = array();
        if (isset($posted_settings['on_subscription_statuses']) && is_array($posted_settings['on_subscription_statuses'])) {
            $selected_statuses = array_map('sanitize_key', $posted_settings['on_subscription_statuses']);
        }

        $allowed_statuses = array_keys(on_get_subscription_export_statuses());
        $selected_statuses = array_values(array_intersect($selected_statuses, $allowed_statuses));
        $settings['on_subscription_statuses'] = $selected_statuses;

        $plan = isset($posted_settings['on_subscription_plan']) ? strtolower(sanitize_text_field((string) $posted_settings['on_subscription_plan'])) : '';
        $settings['on_subscription_plan'] = in_array($plan, $allowed_plans, true) ? $plan : '';

        if (isset($posted_settings['on_subscription_issue_number']) && '' !== trim((string) $posted_settings['on_subscription_issue_number'])) {
            $settings['on_subscription_issue_number'] = absint($posted_settings['on_subscription_issue_number']);
        } else {
            $settings['on_subscription_issue_number'] = '';
        }

        if (isset($settings['custom_export_type']) && 'shop_subscription' === $settings['custom_export_type']) {
            $settings['statuses'] = $selected_statuses;
        }

        return $settings;
    }

    add_filter('woe_settings_validate', 'on_save_export_type_filter');
}

if (!function_exists('on_apply_custom_export_type')) {
    /**
     * Force le type de post exporté selon l'option sélectionnée.
     * Note: Ce hook ne reçoit qu'1 paramètre, on utilise donc une variable globale
     * pour accéder à $settings qui est définie dans on_apply_custom_subscription_export_filters.
     *
     * Les valeurs doivent être retournées avec guillemets simples (WOE ne les échappe pas).
     *
     * @param array $types Types de commandes à exporter.
     * @return array
     */
    function on_apply_custom_export_type($types)
    {
        $custom_type = $GLOBALS['_on_export_custom_type'] ?? 'shop_order';

        if ('shop_subscription' === $custom_type) {
            return array("'shop_subscription'");
        }

        return $types;
    }

    add_filter('woe_sql_order_types', 'on_apply_custom_export_type', 20, 1);
}

if (!function_exists('on_apply_custom_subscription_export_filters')) {
    /**
     * Injecte des filtres SQL supplémentaires pour les contrats d'abonnement.
     *
     * @param array $where    Conditions SQL de la requête d'export.
     * @param array $settings Paramètres du profil d'export.
     * @return array
     */
    function on_apply_custom_subscription_export_filters($where, $settings)
    {
        // Track the custom export type so on_apply_custom_export_type can use it
        // (that hook only receives 1 parameter, so we use a global variable)
        $GLOBALS['_on_export_custom_type'] = $settings['custom_export_type'] ?? 'shop_order';

        $has_custom_type = isset($settings['custom_export_type']) && 'shop_subscription' === $settings['custom_export_type'];
        $has_custom_filters = !empty($settings['on_subscription_plan'])
            || !empty($settings['on_subscription_issue_number'])
            || !empty($settings['on_subscription_statuses']);

        if (!$has_custom_type && !$has_custom_filters) {
            return $where;
        }

        global $wpdb;

        $is_hpos_query = false;
        foreach ((array) $where as $clause) {
            if (is_string($clause) && false !== strpos($clause, 'orders.type')) {
                $is_hpos_query = true;
                break;
            }
        }

        $status_column = $is_hpos_query ? 'orders.status' : 'orders.post_status';
        $order_id_column = $is_hpos_query ? 'orders.id' : 'orders.ID';

        $statuses = on_get_selected_subscription_statuses($settings);

        // Quand on exporte des subscriptions, les statuts WOE par défaut (wc-pending, wc-processing...)
        // ne correspondent pas aux statuts subscription (wc-active, wc-expired...).
        // Il faut toujours remplacer cette clause par les statuts subscription appropriés.
        if (empty($statuses)) {
            // Statuts subscription par défaut si aucun filtre personnalisé
            $statuses = array('wc-active', 'wc-pending', 'wc-on-hold', 'wc-pending-cancel', 'wc-cancelled', 'wc-expired');
        }

        if (!empty($statuses)) {
            $normalized_statuses = array();
            foreach ($statuses as $status) {
                $status = sanitize_key($status);
                if ('' === $status) {
                    continue;
                }

                $normalized_statuses[] = (0 === strpos($status, 'wc-')) ? $status : 'wc-' . $status;
            }

            $normalized_statuses = array_values(array_unique($normalized_statuses));

            if (!empty($normalized_statuses)) {
                $quoted_statuses = array_map('esc_sql', $normalized_statuses);
                $new_status_clause = $status_column . " IN ('" . implode("','", $quoted_statuses) . "')";

                // Remplace la clause de statuts par défaut de WOE pour éviter un conflit AND impossible.
                // WOE injecte post_status IN ('wc-pending',...) mais les subscriptions ont des statuts différents.
                $replaced = false;
                foreach ($where as $idx => $clause) {
                    if (is_string($clause) && false !== strpos($clause, $status_column . ' in (')) {
                        $where[$idx] = $new_status_clause;
                        $replaced = true;
                        break;
                    }
                }

                if (!$replaced) {
                    $where[] = $new_status_clause;
                }
            }
        }

        $plan = isset($settings['on_subscription_plan']) ? strtolower(sanitize_text_field((string) $settings['on_subscription_plan'])) : '';
        if ('' !== $plan) {
            $where[] = $wpdb->prepare(
                "(
                    EXISTS (
                        SELECT 1
                        FROM {$wpdb->postmeta} AS on_pm_formule_post
                        WHERE on_pm_formule_post.post_id = {$order_id_column}
                          AND on_pm_formule_post.meta_key = %s
                          AND UPPER(on_pm_formule_post.meta_value) = %s
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM {$wpdb->prefix}wc_orders_meta AS on_pm_formule_hpos
                        WHERE on_pm_formule_hpos.order_id = {$order_id_column}
                          AND on_pm_formule_hpos.meta_key = %s
                          AND UPPER(on_pm_formule_hpos.meta_value) = %s
                    )
                )",
                'on_formule',
                strtoupper($plan),
                'on_formule',
                strtoupper($plan)
            );
        }

        $issue_number = isset($settings['on_subscription_issue_number']) && '' !== (string) $settings['on_subscription_issue_number']
            ? absint($settings['on_subscription_issue_number'])
            : 0;

        if ($issue_number > 0) {
            $where[] = $wpdb->prepare(
                "(
                    EXISTS (
                        SELECT 1
                        FROM {$wpdb->postmeta} AS on_pm_number_start_post
                        WHERE on_pm_number_start_post.post_id = {$order_id_column}
                          AND on_pm_number_start_post.meta_key = %s
                          AND CAST(on_pm_number_start_post.meta_value AS UNSIGNED) <= %d
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM {$wpdb->prefix}wc_orders_meta AS on_pm_number_start_hpos
                        WHERE on_pm_number_start_hpos.order_id = {$order_id_column}
                          AND on_pm_number_start_hpos.meta_key = %s
                          AND CAST(on_pm_number_start_hpos.meta_value AS UNSIGNED) <= %d
                    )
                )",
                'number-start',
                $issue_number,
                'number-start',
                $issue_number
            );

            $where[] = $wpdb->prepare(
                "(
                    EXISTS (
                        SELECT 1
                        FROM {$wpdb->postmeta} AS on_pm_number_end_post
                        WHERE on_pm_number_end_post.post_id = {$order_id_column}
                          AND on_pm_number_end_post.meta_key = %s
                          AND CAST(on_pm_number_end_post.meta_value AS UNSIGNED) >= %d
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM {$wpdb->prefix}wc_orders_meta AS on_pm_number_end_hpos
                        WHERE on_pm_number_end_hpos.order_id = {$order_id_column}
                          AND on_pm_number_end_hpos.meta_key = %s
                          AND CAST(on_pm_number_end_hpos.meta_value AS UNSIGNED) >= %d
                    )
                )",
                'number-end',
                $issue_number,
                'number-end',
                $issue_number
            );
        }

        return $where;
    }

    add_filter('woe_sql_get_order_ids_where', 'on_apply_custom_subscription_export_filters', 20, 2);
}

if (!function_exists('on_add_subscription_export_custom_columns')) {
    /**
     * Ajoute les colonnes ON dans le segment "subscription".
     *
     * @param array $fields Champs du segment subscription.
     * @return array
     */
    function on_add_subscription_export_custom_columns($fields)
    {
        $fields['sub_on_formule'] = array(
            'segment' => 'subscription',
            'format'  => 'string',
            'label'   => __('Subscription Plan', 'orgues-nouvelles'),
        );
        $fields['sub_on_numero_start'] = array(
            'segment' => 'subscription',
            'format'  => 'number',
            'label'   => __('Subscription ON Start Number', 'orgues-nouvelles'),
        );
        $fields['sub_on_numero_end'] = array(
            'segment' => 'subscription',
            'format'  => 'number',
            'label'   => __('Subscription ON End Number', 'orgues-nouvelles'),
        );

        return $fields;
    }

    add_filter('woe_get_order_fields_subscription', 'on_add_subscription_export_custom_columns', 20, 1);
}

if (!function_exists('on_get_subscription_for_export_row')) {
    /**
     * Retourne l'abonnement associe a la ligne d'export.
     *
     * @param WC_Order|WC_Subscription $order Commande/abonnement exporte.
     * @return WC_Subscription|null
     */
    function on_get_subscription_for_export_row($order)
    {
        if ($order instanceof WC_Subscription) {
            return $order;
        }

        if (!$order instanceof WC_Order || !function_exists('wcs_get_subscriptions_for_order')) {
            return null;
        }

        $subs = wcs_get_subscriptions_for_order($order->get_id(), array('order_type' => 'any'));
        if (empty($subs) || !is_array($subs)) {
            return null;
        }

        $sub = reset($subs);

        return $sub instanceof WC_Subscription ? $sub : null;
    }
}

if (!function_exists('on_get_export_value_subscription_plan')) {
    /**
     * Valeur de colonne exportable: plan abonnement.
     */
    function on_get_export_value_subscription_plan($value, $order)
    {
        $subscription = on_get_subscription_for_export_row($order);
        if (!$subscription) {
            return $value;
        }

        $plan = strtoupper((string) $subscription->get_meta('on_formule', true));

        return '' !== $plan ? $plan : $value;
    }

    add_filter('woe_get_order_value_sub_on_formule', 'on_get_export_value_subscription_plan', 10, 2);
}

if (!function_exists('on_get_export_value_subscription_start_number')) {
    /**
     * Valeur de colonne exportable: numero ON de debut.
     */
    function on_get_export_value_subscription_start_number($value, $order)
    {
        $subscription = on_get_subscription_for_export_row($order);
        if (!$subscription) {
            return $value;
        }

        $number = $subscription->get_meta('number-start', true);

        return ('' !== $number && null !== $number) ? (int) $number : $value;
    }

    add_filter('woe_get_order_value_sub_on_numero_start', 'on_get_export_value_subscription_start_number', 10, 2);
}

if (!function_exists('on_get_export_value_subscription_end_number')) {
    /**
     * Valeur de colonne exportable: numero ON de fin.
     */
    function on_get_export_value_subscription_end_number($value, $order)
    {
        $subscription = on_get_subscription_for_export_row($order);
        if (!$subscription) {
            return $value;
        }

        $number = $subscription->get_meta('number-end', true);

        return ('' !== $number && null !== $number) ? (int) $number : $value;
    }

    add_filter('woe_get_order_value_sub_on_numero_end', 'on_get_export_value_subscription_end_number', 10, 2);
}

if (!function_exists('on_add_common_export_order_type_column')) {
    /**
     * Ajoute une colonne exportable dans le segment Common pour le type de commande.
     *
     * @param array $fields Champs du segment Common.
     * @return array
     */
    function on_add_common_export_order_type_column($fields)
    {
        $fields['on_order_type'] = array(
            'segment' => 'common',
            'format'  => 'string',
            'label'   => __('Order Entity Type', 'orgues-nouvelles'),
        );

        return $fields;
    }

    add_filter('woe_get_order_fields_common', 'on_add_common_export_order_type_column', 20, 1);
}

if (!function_exists('on_get_export_value_common_order_type')) {
    /**
     * Valeur de la colonne Common: type d'entite exportee.
     *
     * @param string $value Valeur par defaut.
     * @param mixed  $order Objet commande/subscription.
     * @return string
     */
    function on_get_export_value_common_order_type($value, $order)
    {
        if (is_object($order) && is_callable(array($order, 'get_type'))) {
            $type = (string) $order->get_type();
            if ('' !== $type) {
                return $type;
            }
        }

        return $value;
    }

    add_filter('woe_get_order_value_on_order_type', 'on_get_export_value_common_order_type', 10, 2);
}