<?php 
if (!function_exists('on_account_dashboard')) {
    // Affiche le contenu de la page "Mon compte" dans le dashboard
    function on_account_dashboard() {
        $page = get_option('configuration_orgues-nouvelles_mon_compte_dashboard');
        if (isset($page[0])) {
            $pageId = pll_get_post( $page[0] );
            echo get_the_content(null, false, $pageId);
        }
    }

    add_action('woocommerce_account_dashboard', 'on_account_dashboard');
}

function on_register_new_item_endpoint()
{
    add_rewrite_endpoint('mes-magazines', EP_PAGES);
}
add_action('init', 'on_register_new_item_endpoint');
// Enable endpoint
add_filter('query_vars', 'on_mes_magazines_query_var', 0);
function on_mes_magazines_query_var($query_vars)
{
    $query_vars[] = 'mes-magazines';

    return $query_vars;
}

function on_ajouter_menu_mes_magazines($items)
{
    $nouveaux_items = array(
        'mes-magazines' => __('Mes magazines', 'orgues-nouvelles'),
    );

    // Insérer le nouvel élément avant "Commandes"
    $position = array_search('orders', array_keys($items));
    $items = array_slice($items, 0, $position, true) + $nouveaux_items + array_slice($items, $position, count($items) - $position, true);

    return $items;
}
add_filter('woocommerce_account_menu_items', 'on_ajouter_menu_mes_magazines');

function on_ajouter_mes_magazines_code()
{
    if (isset($_POST['magazine_code'])) {
        $code_saisi = sanitize_text_field($_POST['magazine_code']);
        $user_id = get_current_user_id();

        // Récupérer tous les magazines
        $magazines = pods('magazine')->find();

        while ($magazines->fetch()) {
            $magazine_id = $magazines->field('ID');
            $magazine_code = $magazines->field('code');

            if ($magazine_code === $code_saisi) {
                // Ajouter le magazine à la liste de l'utilisateur
                $user_pods = pods('user', $user_id);
                $user_magazines = $user_pods->get_field('magazines');

                if (empty($user_magazines)) {
                    $user_magazines = array();
                }

                // Vérifier si le magazine n'est pas déjà dans la liste
                $magazine_exists = false;
                foreach ($user_magazines as $user_magazine) {
                    if ($user_magazine['ID'] == $magazine_id) {
                        $magazine_exists = true;
                        break;
                    }
                }

                if (!$magazine_exists) {// Ajouter un tableau associatif avec l'ID
                    $user_pods->add_to('magazines', $magazine_id);
                    echo '<p style="color: green;">' . esc_html__('Magazine ajouté avec succès !', 'orgues-nouvelles') . '</p>';
                } else {
                    echo '<p style="color: orange;">' . esc_html__('Ce magazine est déjà dans votre liste.', 'orgues-nouvelles') . '</p>';
                }
                return; // Arrêter la boucle
            }
        }

        echo '<p style="color: red;">' . esc_html__('Code invalide.', 'orgues-nouvelles') . '</p>';
    }
}

function on_ajouter_contenu_mes_magazines()
{
    on_ajouter_mes_magazines_code();
    $numeros = on_liste_numeros();
    include ORGUES_NOUVELLES_PLUGIN_DIR . 'templates/mon-compte-mes-magazines.php'; // Chemin vers votre modèle
}
add_action('woocommerce_account_mes-magazines_endpoint', 'on_ajouter_contenu_mes_magazines');



if (!function_exists('on_translate_formule')) {
    /**
     * Traduit les codes de formule en français en utilisant on_get_subscription_formule_choices().
     */
    function on_translate_formule(string $formule_code): string {
        if (function_exists('on_get_subscription_formule_label')) {
            return esc_html(on_get_subscription_formule_label($formule_code));
        }

        return esc_html($formule_code);
    }
}

if (!function_exists('on_display_subscription_formule_in_details_table')) {
    /**
     * Affiche la formule de l'abonnement dans le tableau de détails de l'abonnement.
     */
    function on_display_subscription_formule_in_details_table($subscription) {
        if (!$subscription instanceof \WC_Subscription) {
            return;
        }

        $formule = $subscription->get_meta('on_formule', true);
        if ('' === $formule || null === $formule) {
            $formule = on_guess_subscription_formule_from_items($subscription);
        }

        if ('' !== $formule) {
            ?>
            <tr>
                <td><?php esc_html_e('Formule', 'orgues-nouvelles'); ?></td>
                <td><?php echo on_translate_formule($formule); ?></td>
            </tr>
            <?php
        }
    }

    add_action('wcs_subscription_details_table_before_dates', 'on_display_subscription_formule_in_details_table', 10, 1);
}

if (!function_exists('on_display_subscription_issue_numbers_in_table')) {
    /**
     * Affiche les numéros de début et fin de l'abonnement dans le tableau de détails.
     */
    function on_display_subscription_issue_numbers_in_table($subscription) {
        if (!$subscription instanceof \WC_Subscription) {
            return;
        }

        $start_date = $subscription->get_date('start');
        if (empty($start_date)) {
            return;
        }

        $end_date = $subscription->get_date('end');
        $next_payment_date = $subscription->get_date('next_payment');
        $effective_end_date = $end_date;

        if (!empty($next_payment_date)) {
            if (empty($end_date) || $next_payment_date < $end_date) {
                $effective_end_date = $next_payment_date;
            }
        }

        $overrides = function_exists('on_get_subscription_number_overrides') ? on_get_subscription_number_overrides($subscription) : array();
        $info = on_get_subscription_info($start_date, $effective_end_date ?: $start_date, $overrides);

        if (empty($info)) {
            return;
        }

        ?>
        <tr>
            <td><?php esc_html_e('Numéro de début', 'orgues-nouvelles'); ?></td>
            <td><?php echo esc_html('ON-' . $info['numero_debut']); ?></td>
        </tr>
        <tr>
            <td><?php esc_html_e('Numéro de fin', 'orgues-nouvelles'); ?></td>
            <td><?php echo esc_html('ON-' . $info['numero_fin']); ?></td>
        </tr>
        <tr>
            <td><?php esc_html_e('Nombre de numéros', 'orgues-nouvelles'); ?></td>
            <td><?php echo esc_html($info['nombre_numeros']); ?></td>
        </tr>
        <?php
    }

    add_action('wcs_subscription_details_table_after_dates', 'on_display_subscription_issue_numbers_in_table', 10, 1);
}


if (!function_exists('on_display_subscription_formule_in_list')) {
    /**
     * Affiche la formule et les numéros de début/fin de l'abonnement dans le tableau "Mes abonnements".
     */
    function on_display_subscription_formule_in_list($subscription) {
        if (!$subscription instanceof \WC_Subscription) {
            return;
        }

        $formule = $subscription->get_meta('on_formule', true);
        if ('' === $formule || null === $formule) {
            $formule = on_guess_subscription_formule_from_items($subscription);
        }

        if ('' !== $formule) {
            ?>
            <br />
            <small class="on-subscription-formule-list" style="display: block; margin-top: 4px; color: #666;">
                <strong><?php esc_html_e('Formule:', 'orgues-nouvelles'); ?></strong> <?php echo esc_html(on_translate_formule($formule)); ?>
            </small>
            <?php
        }

        $number_start = $subscription->get_meta('number-start', true);
        $number_end   = $subscription->get_meta('number-end', true);

        if ('' !== $number_start && null !== $number_start) {
            ?>
            <small class="on-subscription-numbers-list" style="display: block; margin-top: 2px; color: #666;">
                <strong><?php esc_html_e('Numéros:', 'orgues-nouvelles'); ?></strong>
                <?php
                if ('' !== $number_end && null !== $number_end && (int) $number_end !== (int) $number_start) {
                    echo esc_html('ON-' . (int) $number_start . ' – ON-' . (int) $number_end);
                } else {
                    echo esc_html('ON-' . (int) $number_start);
                }
                ?>
            </small>
            <?php
        }
    }

    add_action('woocommerce_my_subscriptions_after_subscription_id', 'on_display_subscription_formule_in_list', 10, 1);
}
