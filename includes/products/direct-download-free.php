<?php

/**
 * Gestion du téléchargement direct pour les produits gratuits
 */

// 1. Intercepter la demande de téléchargement direct
add_action('template_redirect', 'on_handle_free_download_request');

function on_handle_free_download_request() {
    if (isset($_GET['on_action']) && $_GET['on_action'] === 'download_free' && isset($_GET['product_id'])) {
        $product_id = absint($_GET['product_id']);

        // Vérification du Nonce de sécurité
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'on_download_free_' . $product_id)) {
            wp_die(__('Lien de téléchargement invalide ou expiré.', 'orgues-nouvelles'), __('Erreur de sécurité', 'orgues-nouvelles'), array('response' => 403));
        }

        $product = wc_get_product($product_id);

        // Vérifications de sécurité et de validité
        if (!$product || !$product->is_downloadable() || $product->get_price() !== 0) {
            wp_die(
                __('Produit introuvable, non téléchargeable ou payant.', 'orgues-nouvelles'),
                __('Téléchargement impossible', 'orgues-nouvelles'),
                array('response' => 404)
            );
        }

        // Vérifier que le produit téléchargeable possède bien au moins un fichier
        $product_downloads = $product->get_downloads();
        if (empty($product_downloads)) {
            wp_die(
                __('Ce produit téléchargeable ne contient actuellement aucun fichier à télécharger.', 'orgues-nouvelles'),
                __('Téléchargement indisponible', 'orgues-nouvelles'),
                array('response' => 404)
            );
        }
        // Vérifier si l'utilisateur est connecté, seul les utilisateurs connectés peuvent télécharger les produits gratuits
        if (!is_user_logged_in()) {
            auth_redirect();
        }

        // Créer une commande programmatiquement
        $order = wc_create_order();

        // Vérifier que la commande a bien été créée
        if (is_wp_error($order) || !$order) {
            wp_die(
                __('Une erreur est survenue lors de la création de votre commande gratuite. Veuillez réessayer plus tard.', 'orgues-nouvelles'),
                __('Erreur lors de la création de la commande', 'orgues-nouvelles'),
                array('response' => 500)
            );
        }
        $added = $order->add_product($product);
        if ($added === false) {
            // Si le produit ne peut pas être ajouté, supprimer la commande vide et afficher une erreur explicite
            if ($order instanceof WC_Order) {
                $order->delete(true);
            }
            wp_die(
                __('Impossible d\'ajouter le produit à la commande. Veuillez réessayer plus tard.', 'orgues-nouvelles'),
                __('Erreur lors de la création de la commande', 'orgues-nouvelles'),
                array('response' => 500)
            );
        }

        // À ce stade, l'utilisateur est connecté (auth_redirect() a déjà été appelé si nécessaire)
        $user = wp_get_current_user();
        $order->set_customer_id($user->ID);

        // S'assurer que l'email est défini (priorité à l'email de facturation, sinon email du compte)
        $billing_email = get_user_meta( $user->ID, 'billing_email', true );
        $email = !empty($billing_email) ? $billing_email : $user->user_email;
        $order->set_billing_email($email);

        // Pré-remplir les autres infos de facturation si disponibles
        $address = array(
            'first_name' => get_user_meta( $user->ID, 'billing_first_name', true ),
            'last_name'  => get_user_meta( $user->ID, 'billing_last_name', true ),
            'email'      => $email,
            'phone'      => get_user_meta( $user->ID, 'billing_phone', true ),
            'address_1'  => get_user_meta( $user->ID, 'billing_address_1', true ),
            'address_2'  => get_user_meta( $user->ID, 'billing_address_2', true ),
            'city'       => get_user_meta( $user->ID, 'billing_city', true ),
            'state'      => get_user_meta( $user->ID, 'billing_state', true ),
            'postcode'   => get_user_meta( $user->ID, 'billing_postcode', true ),
            'country'    => get_user_meta( $user->ID, 'billing_country', true ),
        );
        $order->set_address($address, 'billing');
        $order->calculate_totals();
        $order->set_payment_method('other');
        $order->set_payment_method_title('Gratuit');
        $order->save();
        // 1. Passer la commande en "Terminée"
        $order->update_status('completed', 'Commande générée automatiquement pour téléchargement gratuit.');
        
        // 2. Recharger la commande pour s'assurer d'avoir les dernières données
        $order = wc_get_order($order->get_id());
        if ( ! $order ) {
            wp_die(
                __('Impossible de récupérer la commande pour le téléchargement gratuit.', 'orgues-nouvelles'),
                __('Erreur de commande', 'orgues-nouvelles'),
                array('response' => 500)
            );
        }

        // 3. Génération des permissions via la fonction standard de WooCommerce
        // On force la génération (true) même si le statut ou d'autres conditions pourraient l'empêcher
        if (function_exists('wc_downloadable_product_permissions')) {
            wc_downloadable_product_permissions($order->get_id(), true);
        }

        // Récupérer l'URL de téléchargement
        $downloads = $order->get_downloadable_items();
        $download_url = '';
        foreach ($downloads as $download) {
            $download_url = $download['download_url'];
            break; // On prend le premier fichier
        }

        if ($download_url) {
            wp_redirect($download_url);
            exit;
        } else {
            // Journaliser l'absence de lien de téléchargement pour faciliter le diagnostic
            error_log(sprintf(
                'Free download: no download URL generated for order %d, product %d',
                $order->get_id(),
                isset($product_id) ? $product_id : 0
            ));

            // Afficher un message d'erreur à l'utilisateur
            if (function_exists('wc_add_notice')) {
                wc_add_notice(
                    __('Impossible de générer le lien de téléchargement pour ce produit gratuit. Votre commande a bien été enregistrée, mais aucun fichier n\'a pu être trouvé. Veuillez réessayer ultérieurement ou contacter le support.', 'orgues-nouvelles'),
                    'error'
                );
            }
            // Fallback vers la page de confirmation de commande si pas de lien direct trouvé
            wp_redirect($order->get_checkout_order_received_url());
            exit;
        }
    }
}

// 2. Modifier le bouton "Ajouter au panier" dans les boucles (Shop, Catégories)
add_filter('woocommerce_loop_add_to_cart_link', 'on_change_free_download_button_loop', 10, 2);

function on_change_free_download_button_loop($button, $product) {
    if ($product->is_downloadable() && $product->get_price() == 0) {
        $url = add_query_arg([
            'on_action' => 'download_free',
            'product_id' => $product->get_id(),
            '_wpnonce'   => wp_create_nonce('on_download_free_' . $product->get_id())
        ], home_url());
        
        return sprintf('<a href="%s" class="button product_type_simple add_to_cart_button ajax_add_to_cart">%s</a>', esc_url($url), __('Télécharger', 'orgues-nouvelles'));
    }
    return $button;
}

// 3. Modifier le template du bouton d'ajout au panier pour les produits simples (compatible Elementor)
add_filter('wc_get_template', 'on_override_simple_add_to_cart_template', 10, 2);

function on_override_simple_add_to_cart_template($template, $template_name) {
    if ('single-product/add-to-cart/simple.php' === $template_name) {
        global $product;
        // Si $product n'est pas défini, on essaie de le récupérer
        if (!$product) {
            $post_id = get_the_ID();
            if ($post_id) {
                $maybe_product = wc_get_product($post_id);
                if ($maybe_product) {
                    $product = $maybe_product;
                }
            }
        }

        if ($product && $product->is_downloadable() && $product->get_price() == 0) {
            $custom_template = ORGUES_NOUVELLES_PLUGIN_DIR . 'templates/free-download-button.php';
            
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
    }
    return $template;
}
