<?php 
require_once('orgues-nouvelles.php');
if (!function_exists('on_account_dashboard')) {
    // Affiche le contenu de la page "Mon compte" dans le dashboard
    function on_account_dashboard() {
        $page = get_option('configuration_orgues-nouvelles_mon_compte_dashboard');
        if (isset($page[0])) {
            echo get_the_content(null, false, $page[0]);
        }

        echo '<h2>Abonnement</h2>';
        $numeros = get_option('configuration_orgues-nouvelles_numeros_on');
        
        print('<pre>');
        print_r($numeros);
        print('</pre>');
        echo '<ol>';
        foreach ($numeros as $numero => $date) {
            echo '<li><a href="https://www.orgues-nouvelles.org/magazine/', $numero, '" target="_blank">ON n°', $numero, ': ', $date, '</a></li>';
        }
        echo '</ol>';
        $settings = pods('configuration_orgues-nouvelles');
        echo $settings->display('numeros_on');
        $date = new DateTime('2008-03-01');
        $numeros = array();
        $numero = 0;
        while ($numero < 100) {
            $numero = on_date_magazine_to_numero($date->format('Y-m-d'));
            echo '<a href="https://www.orgues-nouvelles.org/magazine/', $numero, '" target="_blank">ON n°', $numero, ': ', $date->format('Y-m-d'), '</a><br>';
            $date->add(new DateInterval('P3M'));
            $numeros[$numero] = $date->format('Y-m-d');
        }
        print('<pre>');
        print_r($numeros);
        print('</pre>');
        //$settings->save('numeros_on', $numeros);
        //update_option('configuration_orgues-nouvelles_numeros_on', $numeros);
        echo 'mars 2008: ON-', on_date_magazine_to_numero('2008-03-01'), '<br>';
        echo 'juin 2008: ON-', on_date_magazine_to_numero('2008-06-01'), '<br>';
        echo 'septembre 2008: ON-', on_date_magazine_to_numero('2008-09-01'), '<br>';
        echo '2021-01-01 : ON', on_date_magazine_to_numero('2021-01-01'), '<br>';
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
                $new_headers['numero_since'] = 'Numero début';
            }
            if ('membership-end-date' == $key) {
                $new_headers['numero_end'] = 'Numero fin';
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
        if (empty($date)) {
            return '';
        }
        $numero_end = on_date_magazine_to_numero($date);
        echo "ON-", $numero_end;
    }
    add_action( 'wc_memberships_my_memberships_column_numero_end', 'on_wc_memberships_my_memberships_column_numero_end', 10, 1 );
}

