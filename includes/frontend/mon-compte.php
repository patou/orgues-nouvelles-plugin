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

// Ajoute des colonnes "numero_since" et "numero_end" dans la liste des adhésions
if (!function_exists('on_wc_memberships_my_memberships_column_names')) {
    function on_wc_memberships_my_memberships_column_names($my_memberships_columns, $user_id) {
        $new_headers = array();

        // add a column header for "numero_since" and "numero_end"
        foreach ($my_memberships_columns as $key => $name) {

            $new_headers[$key] = $name;

            if ('membership-start-date' == $key) {
                $new_headers['numero_since'] = __('Numero début', 'orgues-nouvelles');
            }
            if ('membership-end-date' == $key) {
                $new_headers['numero_end'] = __('Numero fin', 'orgues-nouvelles');
            }
        }

        return $new_headers;
    }
    add_action('wc_memberships_my_memberships_column_names', 'on_wc_memberships_my_memberships_column_names', 10, 2);
    function on_wc_memberships_my_memberships_column_numero_since( $user_membership ) {
        $date = $user_membership->get_start_date();
        if (empty($date)) {
            return '';
        }
        $numero_since = on_date_magazine_to_numero($date);
        echo "ON-", $numero_since;
    }
    add_action( 'wc_memberships_my_memberships_column_numero_since', 'on_wc_memberships_my_memberships_column_numero_since', 10, 1 );
    function on_wc_memberships_my_memberships_column_numero_end( $user_membership ) {
        $date = $user_membership->get_end_date();
        $membership = wc_memberships_get_user_membership($user_membership);
        $next_payment_date = on_next_payment_date_membership($membership);
        if (empty($date) && empty($next_payment_date)) {
            return '';
        }
        if ($next_payment_date) {
            $date = $next_payment_date;
        }
        $numero_end = on_date_magazine_to_numero($date);
        echo "ON-", $numero_end;
    }
    add_action( 'wc_memberships_my_memberships_column_numero_end', 'on_wc_memberships_my_memberships_column_numero_end', 10, 1 );
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
    include plugin_dir_path(__FILE__) . 'templates/mon-compte-mes-magazines.php'; // Chemin vers votre modèle
}
add_action('woocommerce_account_mes-magazines_endpoint', 'on_ajouter_contenu_mes_magazines');
