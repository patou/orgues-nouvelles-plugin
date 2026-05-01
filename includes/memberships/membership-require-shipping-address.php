<?php

/**
 * Ajoute une option sur les produits d'abonnement variable pour exiger une adresse de livraison.
 */

if (!defined('ABSPATH')) {
    exit;
}

// ─── Constantes ──────────────────────────────────────────────────────────────

if (!defined('ON_SHIPPING_COLUMNS')) {
    define('ON_SHIPPING_COLUMNS', array(
        'shipping_first_name',
        'shipping_last_name',
        'shipping_company',
        'shipping_address_1',
        'shipping_address_2',
        'shipping_city',
        'shipping_postcode',
        'shipping_country',
        'shipping_state',
        'shipping_phone',
    ));
}

if (!defined('ON_BILLING_COLUMNS')) {
    define('ON_BILLING_COLUMNS', array(
        'billing_first_name',
        'billing_last_name',
        'billing_company',
        'billing_address_1',
        'billing_address_2',
        'billing_city',
        'billing_postcode',
        'billing_country',
        'billing_state',
        'billing_phone',
        'billing_email',
    ));
}

// ─── Champ produit ────────────────────────────────────────────────────────────

/**
 * Affiche la case à cocher dans l'onglet Livraison du produit,
 * uniquement pour les abonnements simples et variables.
 */
add_action('woocommerce_product_options_shipping_product_data', 'on_product_options_require_shipping_address');

function on_product_options_require_shipping_address()
{
    global $post;

    $product = wc_get_product($post->ID);
    if (!$product || !$product->is_type(array('subscription', 'variable-subscription'))) {
        return;
    }

    woocommerce_wp_checkbox(array(
        'id'          => '_require_shipping_address',
        'label'       => __('Livraison', 'orgues-nouvelles'),
        'description' => __('Nécessite une adresse de livraison lors du passage en commande.', 'orgues-nouvelles'),
        'value'       => get_post_meta($post->ID, '_require_shipping_address', true),
    ));
}

/**
 * Sauvegarde la valeur lors de l'enregistrement du produit.
 */
add_action('woocommerce_process_product_meta', 'on_save_product_require_shipping_address');

function on_save_product_require_shipping_address($post_id)
{
    $product = wc_get_product($post_id);
    if (!$product || !$product->is_type(array('subscription', 'variable-subscription'))) {
        return;
    }

    if (!empty($_POST['_require_shipping_address'])) {
        update_post_meta($post_id, '_require_shipping_address', 'yes');
    } else {
        delete_post_meta($post_id, '_require_shipping_address');
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Vérifie si un produit (ou sa variation parente) exige une adresse de livraison.
 *
 * @param \WC_Product|null $product
 * @return bool
 */
function on_product_requires_shipping_address($product)
{
    if (!$product || !is_a($product, 'WC_Product')) {
        return false;
    }

    if (get_post_meta($product->get_id(), '_require_shipping_address', true) === 'yes') {
        return true;
    }

    // Pour les variations, vérifier le produit parent.
    if ($product->get_parent_id()) {
        if (get_post_meta($product->get_parent_id(), '_require_shipping_address', true) === 'yes') {
            return true;
        }
    }

    return false;
}

/**
 * Vérifie si une adhésion est liée à un abonnement dont un produit exige une adresse de livraison.
 *
 * @param \WC_Memberships_User_Membership $user_membership
 * @return bool
 */
function on_membership_requires_shipping_address($user_membership)
{
    if ($user_membership instanceof \WC_Memberships_Integration_Subscriptions_User_Membership) {
        $subscription = $user_membership->get_subscription();
        if ($subscription instanceof \WC_Subscription) {
            foreach ($subscription->get_items() as $item) {
                $product = is_callable(array($item, 'get_product')) ? $item->get_product() : null;
                if (on_product_requires_shipping_address($product)) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Vérifie si au moins un produit d'abonnement actif exige une adresse de livraison.
 * Utilisé pour conditionner les exports CSV.
 *
 * @return bool
 */
function on_any_subscription_requires_shipping_address()
{
    $ids = wc_get_products(array(
        'type'       => array('subscription', 'variable-subscription'),
        'meta_key'   => '_require_shipping_address',
        'meta_value' => 'yes',
        'limit'      => 1,
        'return'     => 'ids',
    ));

    return !empty($ids);
}

// ─── Panier ───────────────────────────────────────────────────────────────────

/**
 * Force l'adresse de livraison si un produit d'abonnement dans le panier l'exige.
 */
add_filter('woocommerce_cart_needs_shipping_address', 'on_woocommerce_cart_needs_shipping_address');

function on_woocommerce_cart_needs_shipping_address($need_shipping_address)
{
    if ($need_shipping_address) {
        return $need_shipping_address;
    }

    $cart = WC()->cart;
    if (!$cart) {
        return $need_shipping_address;
    }

    foreach ($cart->get_cart() as $cart_item) {
        $product = isset($cart_item['data']) ? $cart_item['data'] : null;
        if (on_product_requires_shipping_address($product)) {
            return true;
        }
    }

    return $need_shipping_address;
}

// ─── Commande ─────────────────────────────────────────────────────────────────

/**
 * Force l'adresse de livraison si un produit d'abonnement dans la commande l'exige.
 */
add_filter('woocommerce_order_needs_shipping_address', 'on_woocommerce_order_needs_shipping_address', 10, 3);

function on_woocommerce_order_needs_shipping_address($need_shipping_address, $hide, $order)
{
    if ($need_shipping_address) {
        return $need_shipping_address;
    }

    foreach ($order->get_items() as $item) {
        if (on_product_requires_shipping_address($item->get_product())) {
            return true;
        }
    }

    return $need_shipping_address;
}

// ─── Export CSV membres ───────────────────────────────────────────────────────

/**
 * Ajoute les colonnes d'adresse de livraison dans l'export CSV des membres
 * si au moins un produit d'abonnement requiert la livraison.
 */
add_filter('wc_memberships_csv_export_user_memberships_headers', 'on_wc_memberships_modify_member_export_headers_require_shipping', 50, 3);

function on_wc_memberships_modify_member_export_headers_require_shipping($headers, $export_instance, $job)
{
    if (on_any_subscription_requires_shipping_address()) {
        foreach (ON_SHIPPING_COLUMNS as $column) {
            $headers[$column] = $column;
        }
    }

    return $headers;
}

/**
 * Fournit la valeur d'une colonne d'adresse pour l'export CSV.
 */
function on_wc_memberships_csv_export_user_memberships_shipping_column($data, $key, $user_membership)
{
    return get_user_meta($user_membership->get_user_id(), $key, true);
}

foreach (ON_SHIPPING_COLUMNS as $column) {
    add_filter("wc_memberships_csv_export_user_memberships_{$column}_column", 'on_wc_memberships_csv_export_user_memberships_shipping_column', 10, 3);
}

// ─── Import CSV membres ───────────────────────────────────────────────────────

/**
 * Lit les colonnes d'adresse depuis le fichier CSV lors de l'import.
 */
add_filter('wc_memberships_csv_import_user_memberships_data', 'on_wc_memberships_modify_import_data_require_shipping', 10, 4);

function on_wc_memberships_modify_import_data_require_shipping($import_data, $action, $columns, $row)
{
    foreach (ON_SHIPPING_COLUMNS as $column) {
        if (isset($columns[$column])) {
            $import_data[$column] = sanitize_text_field(trim($row[$columns[$column]]));
        }
    }

    foreach (ON_BILLING_COLUMNS as $column) {
        if (isset($columns[$column])) {
            $import_data[$column] = sanitize_text_field(trim($row[$columns[$column]]));
        }
    }

    return $import_data;
}

/**
 * Enregistre les colonnes d'adresse dans les métas utilisateur lors de l'import.
 */
add_action('wc_memberships_csv_import_user_membership', 'on_wc_memberships_use_import_data_require_shipping', 10, 3);

function on_wc_memberships_use_import_data_require_shipping($user_membership, $action, $import_data)
{
    $user_id = $user_membership->get_user_id();

    foreach (ON_SHIPPING_COLUMNS as $column) {
        if (isset($import_data[$column])) {
            update_user_meta($user_id, $column, $import_data[$column]);
        }
    }

    foreach (ON_BILLING_COLUMNS as $column) {
        if (isset($import_data[$column])) {
            update_user_meta($user_id, $column, $import_data[$column]);
        }
    }
}

// ─── Affichage adhésion (admin) ───────────────────────────────────────────────

/**
 * Affiche l'adresse de livraison dans les détails d'une adhésion
 * si le produit d'abonnement lié l'exige.
 */
add_action('wc_memberships_after_user_membership_billing_details', 'on_user_membership_screen_columns_shipping', 5, 2);

function on_user_membership_screen_columns_shipping($user_membership)
{
    if (!on_membership_requires_shipping_address($user_membership)) {
        return;
    }

    $user_id = $user_membership->get_user_id();
    $user    = get_user_by('id', (int) $user_id);

    if (!$user) {
        return;
    }

    $address_parts = array(
        'first_name' => get_user_meta($user->ID, 'shipping_first_name', true),
        'last_name'  => get_user_meta($user->ID, 'shipping_last_name', true),
        'company'    => get_user_meta($user->ID, 'shipping_company', true),
        'address_1'  => get_user_meta($user->ID, 'shipping_address_1', true),
        'address_2'  => get_user_meta($user->ID, 'shipping_address_2', true),
        'city'       => get_user_meta($user->ID, 'shipping_city', true),
        'state'      => get_user_meta($user->ID, 'shipping_state', true),
        'postcode'   => get_user_meta($user->ID, 'shipping_postcode', true),
        'country'    => get_user_meta($user->ID, 'shipping_country', true),
    );

    $address           = apply_filters('woocommerce_my_account_my_address_formatted_address', $address_parts, $user->ID, 'shipping');
    $formatted_address = WC()->countries->get_formatted_address($address);
    ?>
    <h4><?php esc_html_e('Adresse de livraison', 'orgues-nouvelles'); ?></h4>
    <address>
        <?php if ($formatted_address) : ?>
            <?php echo wp_kses_post($formatted_address); ?>
        <?php else : ?>
            <?php esc_html_e('Adresse de livraison non renseignée.', 'orgues-nouvelles'); ?>
        <?php endif; ?>
    </address>
    <a class="edit-member" href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>">
        <span class="dashicons dashicons-edit"></span>
        <?php esc_html_e('Modifier l\'adresse de livraison.', 'orgues-nouvelles'); ?>
    </a>
    <?php
}

// ─── Colonne liste des adhérents ──────────────────────────────────────────────

/**
 * Ajoute une colonne avec l'adresse de livraison dans la liste des adhérents.
 */
add_filter('manage_edit-wc_user_membership_columns', 'on_wc_memberships_members_list_table_columns_shipping', 90);

function on_wc_memberships_members_list_table_columns_shipping($columns)
{
    $columns['shipping'] = __('Adresse de livraison', 'orgues-nouvelles');
    return $columns;
}

add_filter('manage_wc_user_membership_posts_custom_column', 'on_wc_memberships_members_list_table_column_shipping', 10, 2);

function on_wc_memberships_members_list_table_column_shipping($column, $post_id)
{
    if ('shipping' !== $column) {
        return;
    }

    $user_membership = wc_memberships_get_user_membership($post_id);
    if (!$user_membership) {
        echo '<span class="na">&ndash;</span>';
        return;
    }

    if (!on_membership_requires_shipping_address($user_membership)) {
        echo '<span class="na">&ndash;</span>';
        return;
    }

    $user_id = $user_membership->get_user_id();
    $user    = get_user_by('id', (int) $user_id);

    if (!$user) {
        echo '<span class="na">&ndash;</span>';
        return;
    }

    $address_parts = array(
        'first_name' => get_user_meta($user->ID, 'shipping_first_name', true),
        'last_name'  => get_user_meta($user->ID, 'shipping_last_name', true),
        'company'    => get_user_meta($user->ID, 'shipping_company', true),
        'address_1'  => get_user_meta($user->ID, 'shipping_address_1', true),
        'address_2'  => get_user_meta($user->ID, 'shipping_address_2', true),
        'city'       => get_user_meta($user->ID, 'shipping_city', true),
        'state'      => get_user_meta($user->ID, 'shipping_state', true),
        'postcode'   => get_user_meta($user->ID, 'shipping_postcode', true),
        'country'    => get_user_meta($user->ID, 'shipping_country', true),
    );

    $address           = apply_filters('woocommerce_my_account_my_address_formatted_address', $address_parts, $user->ID, 'shipping');
    $formatted_address = WC()->countries->get_formatted_address($address);

    if ($formatted_address) {
        echo wp_kses_post($formatted_address);
    } else {
        echo esc_html__('Non renseignée', 'orgues-nouvelles');
    }
}
