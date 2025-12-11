<?php
/**
 * Intégration WooCommerce Subscriptions
 * Affiche les numéros de magazines ON dans les abonnements
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Affiche les numéros ON dans la metabox de détails d'un abonnement
 */
add_action('wcs_subscription_schedule_after_billing_schedule', 'on_display_subscription_numeros', 10, 1);

function on_display_subscription_numeros($subscription) {
    if (!$subscription) {
        return;
    }

    // Récupérer les dates
    $start_date = $subscription->get_date('start');
    $next_payment_date = $subscription->get_date('next_payment');
    $end_date = $subscription->get_date('end');

    if (empty($start_date)) {
        return;
    }

    // Calculer les numéros
    $numero_start = on_date_magazine_to_numero($start_date);
    
    // Utiliser la date de prochain paiement ou de fin (la plus récente)
    $date_fin = $next_payment_date;
    if (!empty($end_date) && (!empty($next_payment_date) && $end_date < $next_payment_date || empty($next_payment_date))) {
        $date_fin = $end_date;
    }
    
    $numero_end = !empty($date_fin) ? on_date_magazine_to_numero($date_fin) : $numero_start;

    // Afficher les numéros
    ?>
    <div class="on-subscription-numeros" style="margin-top: 20px; padding: 15px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 4px;">
        <h3 style="margin-top: 0;"><?php _e('Numéros de magazines', 'orgues-nouvelles'); ?></h3>
        <table class="shop_table">
            <tbody>
                <tr>
                    <th><?php _e('Numéro de début:', 'orgues-nouvelles'); ?></th>
                    <td><strong>ON-<?php echo esc_html($numero_start); ?></strong></td>
                </tr>
                <tr>
                    <th><?php _e('Numéro de fin:', 'orgues-nouvelles'); ?></th>
                    <td><strong>ON-<?php echo esc_html($numero_end); ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Ajoute des colonnes personnalisées dans la liste des abonnements
 */
add_filter('manage_edit-shop_subscription_columns', 'on_add_subscription_columns', 20);

function on_add_subscription_columns($columns) {
    // Insérer les nouvelles colonnes après la colonne "Total"
    $new_columns = array();
    
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        
        if ($key === 'last_payment_date') {
            $new_columns['on_numero_debut'] = __('N° Début', 'orgues-nouvelles');
            $new_columns['on_numero_fin'] = __('N° Fin', 'orgues-nouvelles');
        }
    }
    
    return $new_columns;
}

/**
 * Remplit les colonnes personnalisées dans la liste des abonnements
 */
add_action('manage_shop_subscription_posts_custom_column', 'on_fill_subscription_columns', 10, 2);

function on_fill_subscription_columns($column, $post_id) {
    if ($column === 'on_numero_debut' || $column === 'on_numero_fin') {
        $subscription = wcs_get_subscription($post_id);
        
        if (!$subscription) {
            echo '—';
            return;
        }

        // Récupérer les dates
        $start_date = $subscription->get_date('start');
        $next_payment_date = $subscription->get_date('next_payment');
        $end_date = $subscription->get_date('end');

        if (empty($start_date)) {
            echo '—';
            return;
        }

        if ($column === 'on_numero_debut') {
            $numero_start = on_date_magazine_to_numero($start_date);
            echo '<strong>ON-' . esc_html($numero_start) . '</strong>';
        }

        if ($column === 'on_numero_fin') {
            // Utiliser la date de prochain paiement ou de fin (la plus récente)
            $date_fin = $next_payment_date;
            if (!empty($end_date) && (!empty($next_payment_date) && $end_date < $next_payment_date || empty($next_payment_date))) {
                $date_fin = $end_date;
            }
            
            $numero_end = !empty($date_fin) ? on_date_magazine_to_numero($date_fin) : on_date_magazine_to_numero($start_date);
            echo '<strong>ON-' . esc_html($numero_end) . '</strong>';
        }
    }
}

/**
 * Rend les colonnes personnalisées triables
 */
add_filter('manage_edit-shop_subscription_sortable_columns', 'on_make_subscription_columns_sortable');

function on_make_subscription_columns_sortable($columns) {
    $columns['on_numero_debut'] = 'start_date';
    $columns['on_numero_fin'] = 'next_payment_date';
    return $columns;
}
