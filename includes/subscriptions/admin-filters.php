<?php
/**
 * Filtres de la liste des abonnements.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('on_render_subscription_number_filter_field')) {
    /**
     * Affiche le filtre par numéro dans la liste des abonnements.
     */
    function on_render_subscription_number_filter_field($post_type)
    {
        if ('shop_subscription' !== $post_type) {
            return;
        }

        $value = isset($_GET['on_subscription_number']) ? absint(wp_unslash($_GET['on_subscription_number'])) : '';
        ?>
        <label for="on-subscription-number-filter" class="screen-reader-text">
            <?php esc_html_e('Filtrer par numéro ON', 'orgues-nouvelles'); ?>
        </label>
        <input
            type="number"
            name="on_subscription_number"
            id="on-subscription-number-filter"
            value="<?php echo esc_attr($value); ?>"
            min="0"
            step="1"
            placeholder="<?php esc_attr_e('Numéro ON', 'orgues-nouvelles'); ?>"
        />
        <?php
    }

    add_action('restrict_manage_posts', 'on_render_subscription_number_filter_field', 20);
}

if (!function_exists('on_filter_shop_subscriptions_by_number')) {
    /**
     * Filtre la liste des abonnements sur l'intervalle number-start / number-end.
     */
    function on_filter_shop_subscriptions_by_number($query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        global $pagenow;
        if ('edit.php' !== $pagenow) {
            return;
        }

        if (!isset($_GET['post_type']) || 'shop_subscription' !== $_GET['post_type']) {
            return;
        }

        $numero = isset($_GET['on_subscription_number']) ? absint(wp_unslash($_GET['on_subscription_number'])) : 0;
        if ($numero <= 0) {
            return;
        }

        $meta_query = (array) $query->get('meta_query');
        $meta_query['relation'] = 'AND';
        $meta_query[] = array(
            'key'     => 'number-start',
            'value'   => $numero,
            'type'    => 'NUMERIC',
            'compare' => '<=',
        );
        $meta_query[] = array(
            'key'     => 'number-end',
            'value'   => $numero,
            'type'    => 'NUMERIC',
            'compare' => '>=',
        );

        $query->set('meta_query', $meta_query);
    }

    add_action('pre_get_posts', 'on_filter_shop_subscriptions_by_number', 20);
}