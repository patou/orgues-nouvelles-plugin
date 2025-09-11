<?php
/**
 * Modèle de la page "Mes magazines" du compte WooCommerce.
 *
 * @package orgues-nouvelles-plugin
 */

defined('ABSPATH') || exit;
// Récupérer la langue courante avec Polylang
$current_lang = function_exists('pll_current_language') ? pll_current_language() : '';


echo '<h2>' . esc_html__('Vos magazines abonnés :', 'orgues-nouvelles') . '</h2>';

if (!empty($numeros)) {
    echo '<ul class="magazines-grid">';
    // Préparer les arguments pour WP_Query
    $args = array(
        'post_type'      => 'magazine',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'DESC',
    );

    // Filtrer par langue si Polylang est actif
    if ($current_lang) {
        $args['lang'] = $current_lang;
    }

    $magazines_query = new WP_Query($args);

    if ($magazines_query->have_posts()) {
        while ($magazines_query->have_posts()) {
            $magazines_query->the_post();
            $magazine_id = get_the_ID();
            $magazine_numero = get_post_meta($magazine_id, 'numero', true);

            if (!in_array($magazine_numero, $numeros)) {
                continue;
            }

            // Récupérer le titre du magazine
            $magazine_titre = get_the_title($magazine_id);

            // Récupérer l'URL du magazine
            $magazine_url = get_permalink($magazine_id);

            // Récupérer la miniature du magazine
            $magazine_thumbnail = get_the_post_thumbnail($magazine_id, 'medium');

            echo '<li class="magazine-item">';
            if ($magazine_thumbnail) {
                echo '<a href="' . esc_url($magazine_url) . '">' . $magazine_thumbnail . '</a>';
            }
            echo '<div class="magazine-details">';
            echo '<a href="' . esc_url($magazine_url) . '">' . sprintf(
                esc_html__('Orgues nouvelles n°%s', 'orgues-nouvelles'),
                esc_html($magazine_numero)
            ) . '</a>';
            echo '</div>';
            echo '</li>';
        }
        
    }
    else {
        echo '<p>' . esc_html__('Aucun magazine publié.', 'orgues-nouvelles') . '</p>';
    }
    echo '</ul>';
} else {
    echo '<p>' . sprintf(
        esc_html__('Vous n\'êtes abonné à aucun magazine pour le moment. %1$sAbonnez-vous à Orgues-nouvelles%2$s ou ajoutez le code de l\'espace client dans le formulaire ci-dessous', 'orgues-nouvelles'),
        '<a href="/product-category/abonnement/">',
        '</a>'
    ) . '</p>';
}

if ($current_lang === 'fr') {
    echo '<h2>' . esc_html__('Ajouter un magazine avec un code :', 'orgues-nouvelles') . '</h2>';
    echo '<p>' . esc_html__('Retrouvez le code à saisir dans votre magazine pour accéder à l\'espace privé.', 'orgues-nouvelles') . '</p>';
    echo '<form method="post" action="" class="magazines-form">';
    echo '<input type="text" name="magazine_code" placeholder="' . esc_attr__('Code du magazine', 'orgues-nouvelles') . '">';
    echo '<input type="submit" value="' . esc_attr__('Ajouter', 'orgues-nouvelles') . '">';
    echo '</form>';
}
