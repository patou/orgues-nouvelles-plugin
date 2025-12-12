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
                $next_payment_date = on_next_payment_date_membership($membership);
                if ($next_payment_date) {
                    echo "ON-", on_date_magazine_to_numero($next_payment_date);
                } elseif ($end_date) {
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
        $next_payment_date = on_next_payment_date_membership($user_membership);
        
        // Récupérer l'abonnement lié à ce membership
        $linked_subscription = null;
        if ($user_membership instanceof \WC_Memberships_Integration_Subscriptions_User_Membership) {
            $linked_subscription = $user_membership->get_subscription();
        }
        
        ?>
         <div class="on-subscription-numeros">
        <h3 style="margin-top: 0;"><?php _e('Numéros de Orgues-Nouvelles', 'orgues-nouvelles'); ?></h3>
        <table class="shop_table">
            <tbody>
                <?php
                 if ($start_date) {
                    $numero_debut = on_date_magazine_to_numero($start_date);
                    ?>
                <tr>
                    <th><?php _e('Numéro de début:', 'orgues-nouvelles'); ?></th>
                    <td><strong>ON-<?php echo esc_html($numero_debut); ?></strong></td>
                </tr>
                <?php
                 }
                 if ($next_payment_date || $end_date) {
                    $numero_end = $next_payment_date ? on_date_magazine_to_numero($next_payment_date) : on_date_magazine_to_numero($end_date);
                    ?>
                <tr>
                    <th><?php _e('Numéro de fin:', 'orgues-nouvelles'); ?></th>
                    <td><strong>ON-<?php echo esc_html($numero_end); ?></strong></td>
                </tr>
                <?php
                 }
                ?>
            </tbody>
        </table>
        <?php if ($linked_subscription): ?>
        <p style="margin-top: 10px;">
            <a href="<?php echo esc_url(admin_url('post.php?post=' . $linked_subscription->get_id() . '&action=edit')); ?>" class="button">
                <?php _e('Voir l\'abonnement', 'orgues-nouvelles'); ?>
            </a>
        </p>
        <?php endif; ?>
    </div>
        <?php

    }
    add_action('wc_memberships_after_user_membership_details', 'on_wc_memberships_after_user_membership_details', 10, 1);
}
