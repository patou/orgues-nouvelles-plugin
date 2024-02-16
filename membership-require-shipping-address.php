<?php

/**
 * Ajoute une option sur les plans d'adhésion pour exiger une adresse de livraison
 */

if (!function_exists('on_wc_membership_plan_options_membership_plan_data_general')) {

    // Add a checkbox to the membership plan options
    // to require a shipping address
    function on_wc_membership_plan_options_membership_plan_data_general()
    {
        woocommerce_wp_checkbox(array('id' => 'require_shipping_address', 'label' => 'Livraison', 'description' => 'Nécéssite une adresse de livraison'));
    }
    add_action('wc_membership_plan_options_membership_plan_data_general', 'on_wc_membership_plan_options_membership_plan_data_general', 50, 0);

    // Save the checkbox value
    function on_wc_memberships_save_meta_box($args, $meta_box_id, $post_id, $post)
    {
        if ('wc-memberships-membership-plan-data' !== $meta_box_id) {
            return;
        }
        if (!isset($args['require_shipping_address'])) {
            delete_post_meta($post_id, 'require_shipping_address', 'yes');
            return;
        }
        update_post_meta($post_id, 'require_shipping_address', 'yes');
    }
    add_action('wc_memberships_save_meta_box', 'on_wc_memberships_save_meta_box', 10, 4);

    // Check if the cart needs a shipping address
    // if any of the products in the cart require a shipping address
    // then the cart needs a shipping address
    function on_woocommerce_cart_needs_shipping_address($need_shipping_address)
    {
        if ($need_shipping_address) {
            return $need_shipping_address;
        }
        $cart = WC()->cart;
        $items = $cart->get_cart();
        foreach ($items as $item => $values) {
            $_product =  wc_get_product($values['data']->get_id());
            foreach (wc_memberships()->get_plans_instance()->get_membership_plans_for_product($_product) as $membership_plan) {
                if (get_post_meta($membership_plan->get_id(), 'require_shipping_address', true) == 'yes') {
                    return true;
                    break;
                }
            }
        }
        return $need_shipping_address;
    }

    add_filter('woocommerce_cart_needs_shipping_address', 'on_woocommerce_cart_needs_shipping_address');


    define("SHIPPING_COLUMNS", [
'shipping_first_name',
'shipping_last_name',
'shipping_company',
'shipping_address_1',
'shipping_address_2',
'shipping_city',
'shipping_postcode',
'shipping_country',
'shipping_state'
    ]);
    function on_wc_memberships_modify_member_export_headers_require_shipping($headers, $export_instance, $job)
    {
        $require_shipping = false;
        $membership_plan = wc_memberships_get_membership_plans();
        foreach ($membership_plan as $plan) {
            if (get_post_meta($plan->get_id(), 'require_shipping_address', true) == 'yes') {
                $require_shipping = true;
            }
        }
        
        if ($require_shipping) {
            foreach (SHIPPING_COLUMNS as $column) {
                $headers[$column] = $column;
            }
        }
        return $headers;
    }
    add_filter('wc_memberships_csv_export_user_memberships_headers', 'on_wc_memberships_modify_member_export_headers_require_shipping', 50, 3);
    function on_wc_memberships_csv_export_user_memberships_shipping_column( $data, $key, $user_membership ) {
       $user_id = $user_membership->get_user_id();
       return get_user_meta($user_id, $key, true);
    }
    foreach (SHIPPING_COLUMNS as $column) {
        add_filter( "wc_memberships_csv_export_user_memberships_{$column}_column", 'on_wc_memberships_csv_export_user_memberships_shipping_column', 10, 3 );
    }
    /**
     * Importe les colonnes shipping dans du fichier CSV
     */
    function on_wc_memberships_modify_import_data_require_shipping( $import_data, $action, $columns, $row ) {

        foreach (SHIPPING_COLUMNS as $column) {
            if ( isset( $columns[$column] ) ) {
                $import_data[$column] = trim($row[ $columns[$column] ]);
            }
        }
        
        return $import_data;
    }
    add_filter( 'wc_memberships_csv_import_user_memberships_data', 'on_wc_memberships_modify_import_data_require_shipping', 10, 4 );


    /**
     * Ajoute les colonnes shipping dans les données de l'utilisateurs.
     */
    function on_wc_memberships_use_import_data_require_shipping( $user_membership, $action, $import_data ) {

        foreach (SHIPPING_COLUMNS as $column) {
            if (isset($import_data[$column])) {
                update_user_meta($user_membership->get_user_id(), $column, $import_data[$column]);
            }
        }
    }
    add_action( 'wc_memberships_csv_import_user_membership', 'on_wc_memberships_use_import_data_require_shipping', 10, 3 );

    /**
     * Affiche l'adresse de livraison dans les détails d'une adhésion
     */
    function on_user_membership_screen_columns_shipping($user_membership) {
        $membership_plan = $user_membership->get_plan();
        if (get_post_meta($membership_plan->get_id(), 'require_shipping_address', true) == 'yes') {
            $user_id = $user_membership->get_user_id();
            $user = get_user_by( 'id', (int) $user_id );
            
            ?>
            <h4>Adresse de livraison</h4>
            <address>
			<?php

			// prepare the address
			$address_parts = array(
				'first_name'  => get_user_meta( $user->ID, 'shipping_first_name', true ),
				'last_name'   => get_user_meta( $user->ID, 'shipping_last_name', true ),
				'company'     => get_user_meta( $user->ID, 'shipping_company', true ),
				'address_1'   => get_user_meta( $user->ID, 'shipping_address_1', true ),
				'address_2'   => get_user_meta( $user->ID, 'shipping_address_2', true ),
				'city'        => get_user_meta( $user->ID, 'shipping_city', true ),
				'state'       => get_user_meta( $user->ID, 'shipping_state', true ),
				'postcode'    => get_user_meta( $user->ID, 'shipping_postcode', true ),
				'country'     => get_user_meta( $user->ID, 'shipping_country', true )
			);

			// format the address with WooCommerce
			$address           = apply_filters( 'woocommerce_my_account_my_address_formatted_address', $address_parts, $user->ID, 'shipping' );
			$formatted_address = WC()->countries->get_formatted_address( $address );

			if ( ! $formatted_address ) {
				esc_html_e( 'User has not set up their shipping address yet.', 'woocommerce-memberships' );
			} else {
				echo $formatted_address;
			}

			?>
        </address>
            <?php
        }
    }
    add_action('wc_memberships_after_user_membership_billing_details', 'on_user_membership_screen_columns_shipping', 5, 2);
}
