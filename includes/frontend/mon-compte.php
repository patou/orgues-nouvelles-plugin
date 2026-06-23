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

if (!function_exists('on_account_subscription_issue_info')) {
    /**
     * Affiche les numéros ON calculés sur la page d'un abonnement côté client.
     */
    function on_account_subscription_issue_info($subscription)
    {
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
        <section class="on-account-subscription-issues">
            <h2><?php esc_html_e('Numéros Orgues-Nouvelles', 'orgues-nouvelles'); ?></h2>
            <ul>
                <li>
                    <?php
                    printf(
                        /* translators: 1: issue number */
                        esc_html__('Numéro de début : ON-%1$s', 'orgues-nouvelles'),
                        esc_html($info['numero_debut'])
                    );
                    ?>
                </li>
                <li>
                    <?php
                    printf(
                        /* translators: 1: issue number */
                        esc_html__('Numéro de fin : ON-%1$s', 'orgues-nouvelles'),
                        esc_html($info['numero_fin'])
                    );
                    ?>
                </li>
                <li>
                    <?php
                    printf(
                        /* translators: 1: count */
                        esc_html__('Nombre de numéros : %1$s', 'orgues-nouvelles'),
                        esc_html($info['nombre_numeros'])
                    );
                    ?>
                </li>
            </ul>
        </section>
        <?php
    }

    add_action('woocommerce_subscription_details_after_subscription_table', 'on_account_subscription_issue_info', 15, 1);
}
