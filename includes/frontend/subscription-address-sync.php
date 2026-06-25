<?php
/**
 * Ajoute la possibilité de synchroniser l'adresse de livraison de mon compte
 * vers tous les abonnements papier (formule ON).
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_customer_meta_fields', 'on_add_shipping_sync_checkbox_to_edit_address');

if (!function_exists('on_add_shipping_sync_checkbox_to_edit_address')) {
    /**
     * Ajoute une case à cocher dans le formulaire d'édition d'adresse de livraison
     * pour permettre de synchroniser vers les abonnements.
     */
    function on_add_shipping_sync_checkbox_to_edit_address($meta_fields) {
        // Cette fonction s'exécute sur la page d'édition utilisateur en admin
        // Dans le frontend, on utilisera un hook différent
    }
}

// Hook frontend pour le formulaire de modification d'adresse
add_action('woocommerce_after_edit_address_form_billing', 'on_render_subscription_sync_checkbox_on_address_form');
add_action('woocommerce_after_edit_address_form_shipping', 'on_render_subscription_sync_checkbox_on_address_form');

if (!function_exists('on_render_subscription_sync_checkbox_on_address_form')) {
    /**
     * Affiche la case à cocher de synchronisation après le formulaire d'adresse de livraison.
     */
    function on_render_subscription_sync_checkbox_on_address_form($type = 'shipping') {
        // Vérifier qu'on est bien sur la page de modification d'adresse de livraison
        if ('shipping' !== $type) {
            return;
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return;
        }

        // Vérifier que l'utilisateur a des abonnements avec formule ON
        $formule_on_subs = on_get_user_formule_on_subscriptions($user_id);
        if (empty($formule_on_subs)) {
            return;
        }

        wp_nonce_field('on_sync_shipping_to_subscriptions', 'on_sync_shipping_nonce');
        ?>
        <p class="form-row form-row-wide on-sync-shipping-checkbox">
            <label>
                <input type="checkbox" name="on_sync_shipping_to_subscriptions" id="on_sync_shipping_to_subscriptions" value="1" />
                <?php
                printf(
                    /* translators: %d: count of subscriptions */
                    esc_html__('Mettre à jour l\'adresse de livraison de mes %d abonnement(s) papier', 'orgues-nouvelles'),
                    count($formule_on_subs)
                );
                ?>
            </label>
            <small class="on-sync-shipping-help"><?php esc_html_e('Si vous cochez cette option, votre nouvelle adresse de livraison sera appliquée à tous vos abonnements papier actifs.', 'orgues-nouvelles'); ?></small>
        </p>
        <?php
    }
}

add_action('woocommerce_customer_save_address', 'on_handle_shipping_address_sync_to_subscriptions', 10, 2);

if (!function_exists('on_handle_shipping_address_sync_to_subscriptions')) {
    /**
     * Traite la synchronisation de l'adresse de livraison vers les abonnements.
     */
    function on_handle_shipping_address_sync_to_subscriptions($user_id, $type) {
        // Vérifier qu'on modifie l'adresse de livraison
        if ('shipping' !== $type) {
            return;
        }

        // Vérifier le nonce
        if (!isset($_POST['on_sync_shipping_nonce']) || !wp_verify_nonce($_POST['on_sync_shipping_nonce'], 'on_sync_shipping_to_subscriptions')) {
            return;
        }

        // Vérifier que la case à cocher est cochée
        if (!isset($_POST['on_sync_shipping_to_subscriptions']) || '1' !== $_POST['on_sync_shipping_to_subscriptions']) {
            return;
        }

        // Récupérer les abonnements avec formule ON
        $formule_on_subs = on_get_user_formule_on_subscriptions($user_id);
        if (empty($formule_on_subs)) {
            return;
        }

        // Récupérer les nouvelles données de livraison depuis postdata
        $shipping_data = array(
            'shipping_first_name' => isset($_POST['shipping_first_name']) ? sanitize_text_field(wp_unslash($_POST['shipping_first_name'])) : '',
            'shipping_last_name'  => isset($_POST['shipping_last_name']) ? sanitize_text_field(wp_unslash($_POST['shipping_last_name'])) : '',
            'shipping_company'    => isset($_POST['shipping_company']) ? sanitize_text_field(wp_unslash($_POST['shipping_company'])) : '',
            'shipping_address_1'  => isset($_POST['shipping_address_1']) ? sanitize_text_field(wp_unslash($_POST['shipping_address_1'])) : '',
            'shipping_address_2'  => isset($_POST['shipping_address_2']) ? sanitize_text_field(wp_unslash($_POST['shipping_address_2'])) : '',
            'shipping_city'       => isset($_POST['shipping_city']) ? sanitize_text_field(wp_unslash($_POST['shipping_city'])) : '',
            'shipping_postcode'   => isset($_POST['shipping_postcode']) ? sanitize_text_field(wp_unslash($_POST['shipping_postcode'])) : '',
            'shipping_state'      => isset($_POST['shipping_state']) ? sanitize_text_field(wp_unslash($_POST['shipping_state'])) : '',
            'shipping_country'    => isset($_POST['shipping_country']) ? sanitize_text_field(wp_unslash($_POST['shipping_country'])) : '',
        );

        // Mettre à jour chaque abonnement
        foreach ($formule_on_subs as $subscription) {
            foreach ($shipping_data as $key => $value) {
                $method_name = 'set_' . $key;
                if (is_callable(array($subscription, $method_name))) {
                    $subscription->$method_name($value);
                }
            }
            $subscription->save();

            $subscription->add_order_note(
                sprintf(
                    /* translators: %s: customer username */
                    esc_html__('Adresse de livraison mise à jour par %s depuis les paramètres du compte.', 'orgues-nouvelles'),
                    esc_html(wp_get_current_user()->display_name)
                ),
                false,
                false
            );
        }

        wc_add_notice(
            sprintf(
                /* translators: %d: count of subscriptions */
                esc_html__('Adresse de livraison mise à jour pour %d abonnement(s).', 'orgues-nouvelles'),
                count($formule_on_subs)
            ),
            'success'
        );
    }
}
