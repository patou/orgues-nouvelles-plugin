<?php

use SkyVerge\WooCommerce\Memberships\Profile_Fields as Profile_Fields_Handler;
use SkyVerge\WooCommerce\Memberships\Profile_Fields\Profile_Field;
use SkyVerge\WooCommerce\Memberships\Profile_Fields\Exceptions\Invalid_Field;

if (!function_exists('on_add_visibility_options')) {

    // Ajoute une nouvelle option de visibilité pour les champs de profil de membership
    function on_add_visibility_options($options)
    {
        $options['checkout-page'] = 'Page de commande';
        return $options;
    }
    add_filter('wc_memberships_profile_fields_visibility_options', 'on_add_visibility_options');

    // Ajoute la nouvelle option de visibilité accessible à tout les plans d'adhésion
    function on_wc_memberships_profile_fields_membership_plan_visibility_options($access, $membership_plan)
    {
        $access[] = 'checkout-page';
        return $access;
    }
    add_filter('wc_memberships_profile_fields_membership_plan_visibility_options', 'on_wc_memberships_profile_fields_membership_plan_visibility_options', 10, 2);

    // Affiche les champs de profil sur la page de commande
    function on_woocommerce_checkout_after_customer_details()
    {
        $cart = WC()->cart;
        $items = $cart->get_cart();
        $applicable_plans = array();
        foreach ($items as $item => $values) {
            $_product =  wc_get_product($values['data']->get_id());
            foreach (wc_memberships()->get_plans_instance()->get_membership_plans_for_product($_product) as $membership_plan) {
                $applicable_plans[] = $membership_plan->get_id();
            }
        }
        $profile_field_definitions = Profile_Fields_Handler::get_profile_field_definitions([
            'membership_plan_ids' => $applicable_plans,
            'visibility'          => ['checkout-page'],
            'editable_by'         => Profile_Fields_Handler\Profile_Field_Definition::EDITABLE_BY_CUSTOMER,
        ]);
        if (empty($profile_field_definitions)) {
            return;
        }
        $posted_data = isset($_POST['member_profile_fields']) ? $_POST['member_profile_fields'] : [];
        $user_id = get_current_user_id();

        foreach ($profile_field_definitions as $definition) {

            $profile_field = Profile_Fields_Handler::get_profile_field($user_id, $definition->get_slug()) ?: new Profile_Field();

            $profile_field->set_user_id($user_id);
            $profile_field->set_slug($definition->get_slug());

            if (isset($posted_data[$profile_field->get_slug()])) {
                $profile_field->set_value($posted_data[$profile_field->get_slug()]);
            }

            $profile_fields[] = $profile_field;
        }
?>
        <h3 id="order_review_heading">Votre commande</h3>
        <p>Merci de remplir ces informations pour mieux vous connaitre</p>

        <div class="wc-memberships-profile-fields-wrapper">

            <?php foreach ($profile_fields as $profile_field) : ?>

                <?php wc_memberships_profile_field_form_field($profile_field); ?>

            <?php endforeach; ?>

            <input type="hidden" id="wc-memberships-member-profile-fields-membership-plans" name="member_profile_fields_membership_plans" value="<?php echo implode(',', $applicable_plans); ?>" />

        </div>
<?php
    }
    add_action('woocommerce_after_order_notes', 'on_woocommerce_checkout_after_customer_details');

    // Enregistre les champs de profil sur la page de commande
    function on_woocommerce_checkout_update_order_meta($order)
    {
        $applicable_plans = explode(',', $_POST['member_profile_fields_membership_plans']);
        $profile_field_definitions = Profile_Fields_Handler::get_profile_field_definitions([
            'membership_plan_ids' => $applicable_plans,
            'visibility'          => ['checkout-page'],
            'editable_by'         => Profile_Fields_Handler\Profile_Field_Definition::EDITABLE_BY_CUSTOMER,
        ]);
        if (empty($profile_field_definitions)) {
            return;
        }
        $user_id = get_current_user_id();
        $values = $_POST['member_profile_fields'];
        $data = [];
        $errors = new \WP_Error();
        foreach ($profile_field_definitions as $definition) {
            $profile_field = new Profile_Field();
            $profile_field->set_user_id($user_id);
            $profile_field->set_slug($definition->get_slug());
            $profile_field->set_value(isset($_POST['member_profile_fields'][$profile_field->get_slug()]) ? $_POST['member_profile_fields'][$profile_field->get_slug()] : '');
            $field_errors = $profile_field->validate();

			if ( $message = $field_errors->get_error_message( Invalid_Field::ERROR_REQUIRED_VALUE ) ) {
				$errors->add( $profile_field->get_slug(), $message );
				continue;
			}

			if ( $message = $field_errors->get_error_message( Invalid_Field::ERROR_INVALID_VALUE ) ) {
				$errors->add( $profile_field->get_slug(), $message );
				continue;
			}

			$data[ $profile_field->get_slug() ] = $profile_field->get_value();
        }
        if ( $errors->has_errors() ) {
            throw new \Exception( $errors->get_error_message() );
            return;
        }
        $order->update_meta_data( Profile_Fields_Handler::ORDER_ITEM_PROFILE_FIELDS_META, $data );
    }
    add_action('woocommerce_checkout_create_order', 'on_woocommerce_checkout_update_order_meta');

    function on_wc_memberships_grant_membership_access_from_purchase($plan, $args)
    {
        if ( ! isset( $args['order_id'], $args['user_membership_id'] ) || ! $order = wc_get_order( $args['order_id'] ) ) {
			return;
		}
        $data = $order->get_meta(Profile_Fields_Handler::ORDER_ITEM_PROFILE_FIELDS_META);
        if (empty($data)) {
            return;
        }
        $user_id = $order->get_user_id();
        $profile_fields = Profile_Fields_Handler::get_profile_field_definitions([
            'membership_plan_ids' => [$plan->get_id()],
            'visibility'          => ['checkout-page'],
            'editable_by'         => Profile_Fields_Handler\Profile_Field_Definition::EDITABLE_BY_CUSTOMER,
        ]);
        foreach ($profile_fields as $profile_field) {
            if (isset($data[$profile_field->get_slug()])) {
                $value = $data[$profile_field->get_slug()];
                $field = new Profile_Field();
                $field->set_user_id($user_id);
                $field->set_slug($profile_field->get_slug());
                $field->set_value($value);
                $field->save();
            }
        }
    }
    add_action('wc_memberships_grant_membership_access_from_purchase', 'on_wc_memberships_grant_membership_access_from_purchase', 10, 2);
}
