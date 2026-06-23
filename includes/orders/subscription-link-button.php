<?php
/**
 * Affiche un bouton pour accéder à l'abonnement lié depuis l'écran d'édition d'une commande
 *
 * @package Orgues-Nouvelles Plugin
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajoute un bouton pour voir l'abonnement lié à une commande (écran admin)
 *
 * @param int $post_id L'ID du post de la commande.
 * @return void
 */
function on_add_subscription_link_button_to_order($post_id) {
    // Vérifier que c'est vraiment une commande WooCommerce
    $order = wc_get_order($post_id);

    if (!is_a($order, 'WC_Order')) {
        return;
    }

    // Vérifier que c'est une commande de renouvellement ou liée à un abonnement
    $subscriptions = wcs_get_subscriptions_for_order($order->get_id(), array('order_type' => 'any'));

    if (empty($subscriptions)) {
        return;
    }

    $subscription = reset($subscriptions);

    if (!is_a($subscription, 'WC_Subscription')) {
        return;
    }

    $subscription_url = admin_url('post.php?post=' . $subscription->get_id() . '&action=edit');

    ?>
    <div class="on-order-subscription-link" style="margin-top: 12px;">
        <a href="<?php echo esc_url($subscription_url); ?>" class="button button-primary">
            <?php esc_html_e('Voir l\'abonnement', 'orgues-nouvelles'); ?>
        </a>
    </div>
    <?php
}
add_action('woocommerce_admin_order_data_after_order_details', 'on_add_subscription_link_button_to_order', 10, 1);
