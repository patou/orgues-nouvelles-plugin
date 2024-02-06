<?php

use SkyVerge\WooCommerce\Memberships\Profile_Fields as Profile_Fields_Handler;
use SkyVerge\WooCommerce\Memberships\Profile_Fields\Profile_Field;

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
    add_action('woocommerce_checkout_after_customer_details', 'on_woocommerce_checkout_after_customer_details');
}
