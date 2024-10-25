<?php

// Ajouter une action personnalisée au menu déroulant des actions groupées
function update_subscription_price_add_custom_bulk_action( $bulk_actions ) {
    $bulk_actions['update_subscription_prices'] = __( 'Mettre à jour les prix des abonnements', 'text-domain' );
    return $bulk_actions;
}
add_filter( 'bulk_actions-edit-shop_subscription', 'update_subscription_price_add_custom_bulk_action' );
function update_subscription_price_parse_bulk_actions() {

		// We only want to deal with shop_subscriptions. In case any other CPTs have an 'active' action
		if ( ! isset( $_REQUEST['post_type'] ) || 'shop_subscription' !== $_REQUEST['post_type'] || ! isset( $_REQUEST['post'] ) ) {
			return;
		}

		// Verify the nonce before proceeding, using the bulk actions nonce name as defined in WP core.
		check_admin_referer( 'bulk-posts' );

		$action = '';

		if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] ) { // phpcs:ignore
			$action = wc_clean( wp_unslash( $_REQUEST['action'] ) );
		} elseif ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] ) { // phpcs:ignore
			$action = wc_clean( wp_unslash( $_REQUEST['action2'] ) );
		}

		if ( ! in_array( $action, [ 'update_subscription_prices' ], true ) ) {
			return;
		}

		$subscription_ids  = array_map( 'absint', (array) $_REQUEST['post'] );
		$base_redirect_url = wp_get_referer() ? wp_get_referer() : '';
		$redirect_url      = update_subscription_price_handle_custom_bulk_action( $base_redirect_url, $action, $subscription_ids );

		wp_safe_redirect( $redirect_url );
		exit();
	}
    add_action( 'load-edit.php', 'update_subscription_price_parse_bulk_actions' );
		
// Traiter l'action personnalisée
function update_subscription_price_handle_custom_bulk_action( $redirect_to, $doaction, $post_ids ) {
    if ( $doaction !== 'update_subscription_prices' ) {
        return $redirect_to;
    }

    $update_count = 0;

    foreach ( $post_ids as $post_id ) {
        $subscription = wcs_get_subscription( $post_id );

        if ( ! $subscription ) {
            continue;
        }
        $subscription_updated = 0;

        foreach ( $subscription->get_items() as $item_id => $item ) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $product = wc_get_product($variation_id ? $variation_id : $product_id);


            if ($product && ($product->is_type('subscription') || $product->is_type('variable-subscription') || $product->is_type('subscription_variation'))) {
                // Récupérer le nouveau prix du produit
                $new_price = wc_get_price_excluding_tax($product);

                // Mettre à jour le prix de l'article de l'abonnement
                $item->set_subtotal($new_price);
                $item->set_total($new_price);
                $item->calculate_taxes();
                $item->save();
                $update_count++;
                $subscription_updated++;
            }
        }

        // Sauvegarder l'abonnement après la modification
        if ($subscription_updated > 0) {
            $subscription->calculate_totals();
            $subscription->save();
        }
    }

    // Ajouter un paramètre de requête pour confirmer que l'action s'est terminée
    $redirect_to = add_query_arg( 'bulk_update_subscription_prices', $update_count, $redirect_to );
    return $redirect_to;
}
add_filter( 'handle_bulk_actions-edit-shop_subscription', 'update_subscription_price_handle_custom_bulk_action', 10, 3 );

// Afficher un message de confirmation après l'exécution de l'action
function update_subscription_price_custom_bulk_action_admin_notice() {
    if ( ! empty( $_REQUEST['bulk_update_subscription_prices'] ) ) {
        $updated_count = intval( $_REQUEST['bulk_update_subscription_prices'] );
        printf(
            '<div id="message" class="updated fade"><p>' .
            _n( '%s abonnement mis à jour avec succès.', '%s abonnements mis à jour avec succès.', $updated_count, 'text-domain' ) .
            '</p></div>',
            $updated_count
        );
    }
}
add_action( 'admin_notices', 'update_subscription_price_custom_bulk_action_admin_notice' );
