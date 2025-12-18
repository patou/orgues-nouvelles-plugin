<?php 
add_action( 'woocommerce_before_single_product', 'on_alerte_si_fichier_manquant', 5 );

function on_alerte_si_fichier_manquant() {
    global $product;

    
    // On s'assure que l'utilisateur est admin pour ne pas effrayer les clients
    if ( current_user_can( 'administrator' ) && $product->is_downloadable() ) {
        $downloads = $product->get_downloads();
        if ( empty( $downloads ) ) {
            echo '<div style="padding: 10px;background-color: #ffdddd;border-bottom: 3px solid #ff0000;color: #a70000;text-align: center;margin: 10px 0px;">⚠️ <strong>Attention téléchargement absent :</strong> Ce produit est téléchargeable mais ne contient aucun fichier !</div>';
        }
    }
}

add_action( 'admin_notices', 'on_verifier_fichier_download_admin' );

function on_verifier_fichier_download_admin() {
    global $pagenow, $post;

    // 1. On vérifie qu'on est bien sur la page d'édition d'un produit
    if ( 'post.php' !== $pagenow || ! $post || 'product' !== $post->post_type ) {
        return;
    }

    // 2. On récupère l'objet produit WooCommerce
    $product = wc_get_product( $post->ID );

    // 3. On vérifie : si téléchargeable ET sans fichiers
    if ( $product && $product->is_downloadable() && empty( $product->get_downloads() ) ) {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><strong>⚠️ Attention téléchargement absent :</strong> Ce produit est configuré comme "Téléchargeable" mais <u>aucun fichier</u> n'y est attaché ! Veuillez en ajouter un dans l'onglet "Général".</p>
        </div>
        <?php
    }
}