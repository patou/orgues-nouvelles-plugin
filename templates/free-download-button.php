<?php
/**
 * Template pour le bouton de téléchargement gratuit
 * Remplace le bouton d'ajout au panier standard
 */
defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product ) {
	return;
}

$url = add_query_arg([
    'on_action' => 'download_free',
    'product_id' => $product->get_id(),
    '_wpnonce'   => wp_create_nonce('on_download_free_' . $product->get_id())
], home_url());

?>
<div class="on-free-download-button-wrapper cart">
    <a href="<?php echo esc_url($url); ?>" class="button single_add_to_cart_button button alt">
        <?php echo esc_html__('Télécharger gratuitement', 'orgues-nouvelles'); ?>
    </a>
</div>
