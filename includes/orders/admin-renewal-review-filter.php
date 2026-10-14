<?php
/**
 * Filtres d'administration pour les commandes de réabonnement automatique.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('on_get_auto_renewal_order_status_filter_options')) {
    /**
     * Retourne les états de conversion affichables dans la liste des commandes.
     *
     * @return array
     */
    function on_get_auto_renewal_order_status_filter_options()
    {
        return array(
            'review' => __('À vérifier', 'orgues-nouvelles'),
            'converted' => __('Converties en renouvellement', 'orgues-nouvelles'),
            'ignored' => __('Ignorées', 'orgues-nouvelles'),
            'error' => __('En erreur', 'orgues-nouvelles'),
        );
    }
}

if (!function_exists('on_get_selected_auto_renewal_order_status_filter')) {
    /**
     * Valide l'état de conversion demandé dans l'URL d'administration.
     *
     * @param mixed $value Valeur à valider, ou null pour lire la requête.
     * @return string
     */
    function on_get_selected_auto_renewal_order_status_filter($value = null)
    {
        if (null === $value) {
            $value = isset($_GET['on_renewal_conversion_status'])
                ? wp_unslash($_GET['on_renewal_conversion_status'])
                : '';
        }

        $value = sanitize_key((string) $value);

        return array_key_exists($value, on_get_auto_renewal_order_status_filter_options()) ? $value : '';
    }
}

if (!function_exists('on_render_auto_renewal_order_status_filter')) {
    /**
     * Affiche le filtre dans la liste HPOS des commandes.
     *
     * @param string $order_type Type d'entité de la liste.
     * @param string $which      Emplacement de la barre d'actions.
     * @return void
     */
    function on_render_auto_renewal_order_status_filter($order_type, $which)
    {
        if ('shop_order' !== $order_type || 'top' !== $which) {
            return;
        }

        on_render_auto_renewal_order_status_filter_select();
    }

    add_action('woocommerce_order_list_table_restrict_manage_orders', 'on_render_auto_renewal_order_status_filter', 10, 2);
}

if (!function_exists('on_render_legacy_auto_renewal_order_status_filter')) {
    /**
     * Affiche le filtre dans l'ancienne liste WordPress des commandes.
     *
     * @param string $post_type Type de contenu de la liste.
     * @param string $which    Emplacement de la barre d'actions.
     * @return void
     */
    function on_render_legacy_auto_renewal_order_status_filter($post_type, $which)
    {
        if ('shop_order' !== $post_type || 'top' !== $which) {
            return;
        }

        on_render_auto_renewal_order_status_filter_select();
    }

    add_action('restrict_manage_posts', 'on_render_legacy_auto_renewal_order_status_filter', 20, 2);
}

if (!function_exists('on_render_auto_renewal_order_status_filter_select')) {
    /**
     * Affiche le select de filtre des commandes.
     *
     * @return void
     */
    function on_render_auto_renewal_order_status_filter_select()
    {
        $selected_status = on_get_selected_auto_renewal_order_status_filter();
        ?>
        <label class="screen-reader-text" for="on-renewal-conversion-status">
            <?php esc_html_e('Filtrer par état de réabonnement automatique', 'orgues-nouvelles'); ?>
        </label>
        <select id="on-renewal-conversion-status" name="on_renewal_conversion_status">
            <option value=""><?php esc_html_e('Tous les états de réabonnement', 'orgues-nouvelles'); ?></option>
            <?php foreach (on_get_auto_renewal_order_status_filter_options() as $status => $label) : ?>
                <option value="<?php echo esc_attr($status); ?>" <?php selected($selected_status, $status); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
}

if (!function_exists('on_add_auto_renewal_status_meta_query')) {
    /**
     * Ajoute le critère de conversion sans remplacer les meta_query existantes.
     *
     * @param array  $query_args Arguments de requête WooCommerce/WordPress.
     * @param string $status     État de conversion.
     * @return array
     */
    function on_add_auto_renewal_status_meta_query($query_args, $status)
    {
        if (!is_array($query_args)) {
            $query_args = array();
        }

        $meta_query = isset($query_args['meta_query']) && is_array($query_args['meta_query'])
            ? $query_args['meta_query']
            : array();

        $meta_query[] = array(
            'key' => ON_AUTO_RENEWAL_CONVERSION_STATUS_META,
            'value' => $status,
            'compare' => '=',
        );

        $query_args['meta_query'] = $meta_query;

        return $query_args;
    }
}

if (!function_exists('on_filter_hpos_orders_by_auto_renewal_status')) {
    /**
     * Filtre les commandes HPOS selon leur état de conversion.
     *
     * @param array $query_args Arguments transmis à wc_get_orders().
     * @return array
     */
    function on_filter_hpos_orders_by_auto_renewal_status($query_args)
    {
        $order_types = isset($query_args['type']) ? (array) $query_args['type'] : array('shop_order');
        if (!in_array('shop_order', $order_types, true)) {
            return $query_args;
        }

        $status = on_get_selected_auto_renewal_order_status_filter();
        if ('' === $status) {
            return $query_args;
        }

        return on_add_auto_renewal_status_meta_query($query_args, $status);
    }

    add_filter('woocommerce_order_list_table_prepare_items_query_args', 'on_filter_hpos_orders_by_auto_renewal_status', 20, 1);
}

if (!function_exists('on_filter_legacy_orders_by_auto_renewal_status')) {
    /**
     * Filtre les commandes de l'ancienne liste WordPress (sans HPOS).
     *
     * @param WP_Query $query Requête courante.
     * @return void
     */
    function on_filter_legacy_orders_by_auto_renewal_status($query)
    {
        if (!is_admin() || !$query->is_main_query() || 'shop_order' !== $query->get('post_type')) {
            return;
        }

        $status = on_get_selected_auto_renewal_order_status_filter();
        if ('' === $status) {
            return;
        }

        $query_args = on_add_auto_renewal_status_meta_query(
            array('meta_query' => $query->get('meta_query')),
            $status
        );
        $query->set('meta_query', $query_args['meta_query']);
    }

    add_action('pre_get_posts', 'on_filter_legacy_orders_by_auto_renewal_status', 20, 1);
}