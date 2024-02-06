<?php

/**
 * Affiche le numéro de magazine correspondant à une date dans l'interface d'administration de membership
 */

if (!function_exists('on_membership_customize_columns')) {
    // Ajoute les deux colonnes "numero_since" et "numero_end" dans la liste des adhésions
    function on_membership_customize_columns($columns)
    {
        $new_columns = array();
        foreach ($columns as $key => $name) {
            $new_columns[$key] = $name;
            if ('member_since' == $key) {
                $new_columns['numero_since'] = 'Numero début';
            }
            if ('expires' == $key) {
                $new_columns['numero_end'] = 'Numero fin';
            }
        }
        return $new_columns;
    }
    add_filter('manage_edit-wc_user_membership_columns', 'on_membership_customize_columns', 50);

    // Permet aux deux colonnes "numero_since" et "numero_end" d'être triées
    function on_wc_user_membership_sortable_columns($columns)
    {
        $columns['numero_since'] = 'start_date';
        $columns['numero_end'] = 'expiry_date';
        return $columns;
    }
    add_filter('manage_edit-wc_user_membership_sortable_columns', 'on_wc_user_membership_sortable_columns');

    // Code pour afficher les numéros de magazine dans les colonnes "numero_since" et "numero_end"
    function on_user_membership_screen_columns($column, $post_id)
    {
        if ('numero_since' === $column) {
            $membership = wc_memberships_get_user_membership($post_id);
            if ($membership) {
                $start_date = $membership->get_start_date();
                if ($start_date) {
                    echo "ON-", on_date_magazine_to_numero($start_date);
                } else {
                    echo '<span class="na">&ndash;</span>';
                }
            }
        } elseif ('numero_end' === $column) {
            $membership = wc_memberships_get_user_membership($post_id);
            if ($membership) {
                $end_date = $membership->get_end_date();
                if ($end_date) {
                    echo "ON-", on_date_magazine_to_numero($end_date);
                } else {
                    echo '<span class="na">&ndash;</span>';
                }
            }
        }
    }
    add_action('manage_wc_user_membership_posts_custom_column', 'on_user_membership_screen_columns', 5, 2);

    // Affiche les numéros de magazine dans le détail d'une adhésion
    function on_wc_memberships_after_user_membership_details($user_membership)
    {
        $start_date = $user_membership->get_start_date();
        $end_date = $user_membership->get_end_date();
        if ($start_date) {
?>
            <p class="form-field billing-detail">
                <label>Numero début :</label>
                <?php echo "ON-", on_date_magazine_to_numero($start_date); ?>
            </p>
        <?php
        }
        if ($end_date) {
        ?>
            <p class="form-field billing-detail">
                <label>Numero fin :</label>
                <?php echo "ON-", on_date_magazine_to_numero($end_date); ?>
            </p>
<?php
        }
    }
    add_action('wc_memberships_after_user_membership_details', 'on_wc_memberships_after_user_membership_details', 10, 1);
}
