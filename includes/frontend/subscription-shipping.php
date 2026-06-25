<?php
/**
 * Affichage et modification de l'adresse de livraison pour les abonnements papier (formule ON)
 * depuis la page "Mon compte > Abonnements".
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_subscription_details_after_subscription_table', 'on_display_subscription_shipping_address', 20, 1);

if (!function_exists('on_display_subscription_shipping_address')) {
    /**
     * Affiche l'adresse de livraison et un formulaire de modification pour les abonnements ON.
     */
    function on_display_subscription_shipping_address($subscription) {
        if (!$subscription instanceof \WC_Subscription) {
            return;
        }

        // Vérifier que c'est un abonnement papier (formule ON)
        if (!on_subscription_has_formule_on($subscription)) {
            return;
        }

        $user_id = $subscription->get_customer_id();
        ?>
        <section class="on-account-subscription-shipping-address">
            <h2><?php esc_html_e('Adresse de livraison', 'orgues-nouvelles'); ?></h2>

            <?php
            // Afficher l'adresse actuellement enregistrée
            $address = array(
                'first_name' => $subscription->get_shipping_first_name(),
                'last_name'  => $subscription->get_shipping_last_name(),
                'company'    => $subscription->get_shipping_company(),
                'address_1'  => $subscription->get_shipping_address_1(),
                'address_2'  => $subscription->get_shipping_address_2(),
                'city'       => $subscription->get_shipping_city(),
                'postcode'   => $subscription->get_shipping_postcode(),
                'state'      => $subscription->get_shipping_state(),
                'country'    => $subscription->get_shipping_country(),
            );

            $formatted_address = WC()->countries->get_formatted_address($address);

            if (!empty($formatted_address)) {
                echo '<address class="on-subscription-shipping-address-display">' . wp_kses_post($formatted_address) . '</address>';
            } else {
                echo '<p class="on-subscription-shipping-address-empty">' . esc_html__('Aucune adresse de livraison enregistrée.', 'orgues-nouvelles') . '</p>';
            }
            ?>

            <!-- Formulaire de modification inline -->
            <form method="post" class="on-subscription-shipping-form">
                <?php wp_nonce_field('on_update_subscription_shipping_' . $subscription->get_id()); ?>
                <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription->get_id()); ?>" />
                <input type="hidden" name="action" value="on_update_subscription_shipping" />

                <p>
                    <label for="on_shipping_first_name"><?php esc_html_e('Prénom', 'woocommerce'); ?></label>
                    <input type="text" id="on_shipping_first_name" name="shipping_first_name" value="<?php echo esc_attr($address['first_name']); ?>" placeholder="<?php esc_attr_e('Prénom', 'woocommerce'); ?>" />
                </p>

                <p>
                    <label for="on_shipping_last_name"><?php esc_html_e('Nom', 'woocommerce'); ?></label>
                    <input type="text" id="on_shipping_last_name" name="shipping_last_name" value="<?php echo esc_attr($address['last_name']); ?>" placeholder="<?php esc_attr_e('Nom', 'woocommerce'); ?>" />
                </p>

                <p>
                    <label for="on_shipping_company"><?php esc_html_e('Entreprise', 'woocommerce'); ?></label>
                    <input type="text" id="on_shipping_company" name="shipping_company" value="<?php echo esc_attr($address['company']); ?>" placeholder="<?php esc_attr_e('Entreprise', 'woocommerce'); ?>" />
                </p>

                <p>
                    <label for="on_shipping_address_1"><?php esc_html_e('Adresse', 'woocommerce'); ?> <span class="required">*</span></label>
                    <input type="text" id="on_shipping_address_1" name="shipping_address_1" value="<?php echo esc_attr($address['address_1']); ?>" placeholder="<?php esc_attr_e('Adresse', 'woocommerce'); ?>" required />
                </p>

                <p>
                    <label for="on_shipping_address_2"><?php esc_html_e('Complément d\'adresse', 'woocommerce'); ?></label>
                    <input type="text" id="on_shipping_address_2" name="shipping_address_2" value="<?php echo esc_attr($address['address_2']); ?>" placeholder="<?php esc_attr_e('Appartement, suite, etc.', 'woocommerce'); ?>" />
                </p>

                <p>
                    <label for="on_shipping_postcode"><?php esc_html_e('Code postal', 'woocommerce'); ?> <span class="required">*</span></label>
                    <input type="text" id="on_shipping_postcode" name="shipping_postcode" value="<?php echo esc_attr($address['postcode']); ?>" placeholder="<?php esc_attr_e('Code postal', 'woocommerce'); ?>" required />
                </p>

                <p>
                    <label for="on_shipping_city"><?php esc_html_e('Ville', 'woocommerce'); ?> <span class="required">*</span></label>
                    <input type="text" id="on_shipping_city" name="shipping_city" value="<?php echo esc_attr($address['city']); ?>" placeholder="<?php esc_attr_e('Ville', 'woocommerce'); ?>" required />
                </p>

                <p>
                    <label for="on_shipping_country"><?php esc_html_e('Pays', 'woocommerce'); ?> <span class="required">*</span></label>
                    <select id="on_shipping_country" name="shipping_country" required>
                        <option value=""><?php esc_html_e('Sélectionnez un pays', 'woocommerce'); ?></option>
                        <?php
                        foreach (WC()->countries->get_countries() as $country_code => $country_name) {
                            ?>
                            <option value="<?php echo esc_attr($country_code); ?>" <?php selected($address['country'], $country_code); ?>>
                                <?php echo esc_html($country_name); ?>
                            </option>
                            <?php
                        }
                        ?>
                    </select>
                </p>

                <p>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Mettre à jour l\'adresse', 'orgues-nouvelles'); ?></button>
                </p>
            </form>
        </section>
        <?php
    }
}

add_action('wp_loaded', 'on_handle_subscription_shipping_form_submission');

if (!function_exists('on_handle_subscription_shipping_form_submission')) {
    /**
     * Traite la soumission du formulaire de modification d'adresse de livraison.
     */
    function on_handle_subscription_shipping_form_submission() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || 'on_update_subscription_shipping' !== $_POST['action']) {
            return;
        }

        $subscription_id = isset($_POST['subscription_id']) ? absint($_POST['subscription_id']) : 0;
        $nonce_key = 'on_update_subscription_shipping_' . $subscription_id;

        if (!isset($_POST[$nonce_key]) || !wp_verify_nonce($_POST[$nonce_key], $nonce_key)) {
            wp_die(esc_html__('Erreur de sécurité.', 'orgues-nouvelles'));
        }

        if ($subscription_id <= 0) {
            wp_die(esc_html__('Abonnement non valide.', 'orgues-nouvelles'));
        }

        $subscription = wcs_get_subscription($subscription_id);
        if (!$subscription || !is_a($subscription, 'WC_Subscription')) {
            wp_die(esc_html__('Abonnement introuvable.', 'orgues-nouvelles'));
        }

        // Vérifier que l'utilisateur courant est bien le propriétaire de cet abonnement
        if ($subscription->get_customer_id() !== get_current_user_id()) {
            wp_die(esc_html__('Vous n\'avez pas l\'autorisation de modifier cet abonnement.', 'orgues-nouvelles'));
        }

        // Sanitize et valider les données
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

        // Valider les champs obligatoires
        if (empty($shipping_data['shipping_address_1']) || empty($shipping_data['shipping_city']) || empty($shipping_data['shipping_postcode']) || empty($shipping_data['shipping_country'])) {
            wc_add_notice(esc_html__('Veuillez remplir tous les champs obligatoires.', 'orgues-nouvelles'), 'error');
            return;
        }

        // Mettre à jour les données de livraison de la subscription
        foreach ($shipping_data as $key => $value) {
            $method_name = 'set_' . $key;
            if (is_callable(array($subscription, $method_name))) {
                $subscription->$method_name($value);
            }
        }

        $subscription->save();

        wc_add_notice(esc_html__('Adresse de livraison mise à jour avec succès.', 'orgues-nouvelles'), 'success');
    }
}
