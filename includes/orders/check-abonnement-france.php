<?php 

add_action( 'woocommerce_after_checkout_validation', 'on_custom_shipping_validation', 10, 2 );

function on_custom_shipping_validation($posted_data, $errors) {
  $chosen_country = WC()->customer->get_shipping_country();
  $france_variation_exists = false;

  // Loop through cart items
  foreach ( WC()->cart->get_cart() as $cart_item ) {
    $variation_id = $cart_item['variation_id'];
    if ($variation_id) {
        $livraison = $cart_item['variation']['attribute_livraison'];
        if ($livraison === 'France') {
            $france_variation_exists = true;
            break;
        }
    }
  }

  print_r(array('chosen_country' => $chosen_country, 'france_variation_exists' => $france_variation_exists));
  if ( $chosen_country !== 'FR' && $france_variation_exists ) {
    $errors->add( 'shipping_error', __( 'Vous devez selectionner la livraison Monde pour une livraison en dehors de la France lors de votre abonnement à Orgues-Nouvelles', 'orgues-nouvelles' ) );
  }
}