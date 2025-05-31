<?php
/**
 * Modèle de la page "Mes magazines" du compte WooCommerce.
 *
 * @package orgues-nouvelles-plugin
 */

defined('ABSPATH') || exit;

echo '<h2>' . esc_html__('Vos magazines abonnés :', 'orgues-nouvelles') . '</h2>';

if (!empty($numeros)) {
    echo '<ul class="magazines-grid">';
    $magazines = pods('magazine')->find(array(
        'orderby' => 'menu_order DESC'
    ));

    while ($magazines->fetch()) {
        $magazine = $magazines->row();

        $magazine_numero = $magazines->field('numero');
        if (!in_array($magazine_numero, $numeros)) {
            continue;
        }

        $magazine_id = $magazines->field('ID');

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
    echo '</ul>';
} else {
    echo '<p>' . sprintf(
        esc_html__('Vous n\'êtes abonné à aucun magazine pour le moment. %1$sAbonnez-vous à Orgues-nouvelles%2$s ou ajoutez le code de l\'espace client dans le formulaire ci-dessous', 'orgues-nouvelles'),
        '<a href="/product-category/abonnement/">',
        '</a>'
    ) . '</p>';
}

echo '<h2>' . esc_html__('Ajouter un magazine avec un code :', 'orgues-nouvelles') . '</h2>';
echo '<p>' . esc_html__('Retrouvez le code à saisir dans votre magazine pour accéder à l\'espace privé.', 'orgues-nouvelles') . '</p>';
echo '<form method="post" action="" class="magazines-form">';
echo '<input type="text" name="magazine_code" placeholder="' . esc_attr__('Code du magazine', 'orgues-nouvelles') . '">';
echo '<input type="submit" value="' . esc_attr__('Ajouter', 'orgues-nouvelles') . '">';
echo '</form>';
