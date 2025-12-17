<?php
$numeros = array(
    0 => "2008-01",
    1 => "2008-06",
    2 => "2008-10",
    3 => "2008-12",
    4 => "2009-03",
    5 => "2009-06",
    6 => "2009-10",
    7 => "2009-12",
    8 => "2010-03",
    9 => "2010-06",
    10 => "2010-10",
    11 => "2011-01",
    12 => "2011-04",
    13 => "2011-06",
    14 => "2011-10",
    15 => "2012-01",
    16 => "2012-04",
    17 => "2012-07",
    18 => "2012-10",
    19 => "2012-12",
    20 => "2013-03",
    21 => "2013-07",
    22 => "2013-10",
    23 => "2013-12",
    24 => "2014-03",
    25 => "2014-06",
    26 => "2014-10",
    27 => "2014-12",
    28 => "2015-04",
    29 => "2015-06",
    30 => "2015-10",
    31 => "2016-01",
    32 => "2016-04",
    33 => "2016-06",
    34 => "2016-10",
    35 => "2016-12",
    36 => "2017-04",
    37 => "2017-07",
    38 => "2017-10",
    39 => "2018-01",
    40 => "2018-04",
    41 => "2018-07",
    42 => "2018-10",
    43 => "2018-12",
    44 => "2019-03",
    45 => "2019-06",
    46 => "2019-10",
    47 => "2019-12",
    48 => "2020-03",
    49 => "2020-07",
    50 => "2020-10",
    51 => "2020-12",
    52 => "2021-03",
    53 => "2021-07",
    54 => "2021-10",
    55 => "2021-12",
    56 => "2022-03",
    57 => "2022-07",
    58 => "2022-10",
    59 => "2022-12",
    60 => "2023-03",
    61 => "2023-06",
    62 => "2023-10",
    63 => "2023-12",
    64 => "2024-03",
    65 => "2024-06",
    66 => "2024-10",
    67 => "2024-12",
    68 => "2025-03",
    69 => "2025-06",
    70 => "2025-10",
    71 => "2025-12",
    72 => "2026-03",
    73 => "2026-06",
    74 => "2026-10",
    75 => "2026-12",
    76 => "2027-03",
    77 => "2027-06",
    78 => "2027-10",
    79 => "2027-12",

);

// Pour les tests
if (!function_exists('get_option')) {
    function get_option($name)
    {
        if ('configuration_orgues-nouvelles_numeros_on' === $name) {
            return $GLOBALS['numeros'];
        }
        return null;
    }
}

if (!function_exists('on_date_magazine_to_numero')) {
    /**
     * Prend une date au format aaaa-mm-jj et la transforme en numéro de magazine
     * 
     * @param string $date Date au format aaaa-mm-jj
     */
    function on_date_magazine_to_numero($date)
    {
        $numeros = get_option('configuration_orgues-nouvelles_numeros_on');
        
        $input_date = new DateTime($date . (strlen($date) == 7 ? '-15' : ''));
        $input_date->setTime(0, 0, 0);

        $found_numero = 0;
        $last_pub_date = null;

        foreach ($numeros as $num_idx => $pub_month) {
            $pub_date = new DateTime($pub_month . '-01');
            $last_pub_date = clone $pub_date;
            
            // Date de début = 15 du mois précédent la parution
            $start_date = clone $pub_date;
            $start_date->modify('first day of previous month');
            $start_date->setDate($start_date->format('Y'), $start_date->format('m'), 15);
            $start_date->setTime(0, 0, 0);

            if ($input_date > $start_date) {
                $found_numero = $num_idx;
            } else {
                return $found_numero;
            }
        }

        // Gestion du futur (après le tableau)
        while (true) {
            $month = (int)$last_pub_date->format('m');
            switch ($month) {
                case 6:
                    $add_months = 4;  // 06 -> 10
                    break;
                case 10:
                    $add_months = 2; // 10 -> 12
                    break;
                default: // For months 3 and 12
                    $add_months = 3;
            }
            $last_pub_date->modify("+$add_months months");
            
            $start_date = clone $last_pub_date;
            $start_date->modify('first day of previous month');
            $start_date->setDate($start_date->format('Y'), $start_date->format('m'), 15);
            $start_date->setTime(0, 0, 0);
            
            if ($input_date > $start_date) {
                $found_numero++;
            } else {
                break;
            }
        }

        return $found_numero;
    }
}

if (!function_exists('on_numero_to_date_magazine')) {
    /**
     * Prend un numéro de magazine et le transforme en date au format aaaa-mm
     * 
     * @param int $numero Numéro de magazine
     */
    function on_numero_to_date_magazine($numero)
    {
        $numeros = get_option('configuration_orgues-nouvelles_numeros_on');
        if ($numero < 0)
            return $numeros[0];
        
        $count = count($numeros);
        if ($numero < $count) {
            return $numeros[$numero];
        }

        // Futur
        $last_pub_date = new DateTime($numeros[$count - 1] . '-01');
        $current_num = $count - 1;

        while ($current_num < $numero) {
            $month = (int)$last_pub_date->format('m');
            switch ($month) {
                case 6:
                    $add_months = 4;  // 06 -> 10
                    break;
                case 10:
                    $add_months = 2; // 10 -> 12
                    break;
                default: // For months 3 and 12
                    $add_months = 3;
            }
            $last_pub_date->modify("+$add_months months");
            $current_num++;
        }
        
        return $last_pub_date->format('Y-m');
    }
}

if (!function_exists('on_magazine_title')) {
    /**
     * Retourne le titre d'un numéro de magazine
     * 
     * @param int $numero Numéro de magazine
     */
    function on_magazine_title()
    {
        $type = get_post_type();
        $numero = pods_field('numero', true);
        switch ($type) {
            case 'partition':
                return 'Cahier de Partitions n°' . $numero;
            case 'cd':
                return 'CD n°' . $numero;
            case 'magazine':
                return 'Orgues Nouvelles n°' . $numero;;
            default:
                return get_the_title();
        }
    }

}

if (!function_exists('on_next_payment_date_membership')) {
    /**
     * Retourne la date du prochain paiement d'un abonnement
     * 
     * @param \WC_Memberships_Integration_Subscriptions_User_Membership $membership
     */
    function on_next_payment_date_membership($membership)
    {
        if ($membership instanceof \WC_Memberships_Integration_Subscriptions_User_Membership) {
            $subscription = $membership->get_subscription();
            if ($subscription) {
                $next_payment_date = $subscription->get_date('next_payment');
                return $next_payment_date;
            }
        }
        return null;
    }
}

if (!function_exists('on_liste_numeros')) {
    /**
     * Retourne la liste des numéros de magazine que l'utilisateur peut consulter
     * 
     * @return array Liste des numéros de magazine
     */
    function on_liste_numeros($memberships_plan = array())
    {
        // Générer une clé de cache unique basée sur l'utilisateur et les paramètres
        $user_id = get_current_user_id();
        $cache_key = 'on_liste_numeros_' . $user_id . '_' . implode('_', (array)$memberships_plan);

        // Tenter de récupérer depuis le cache mémoire
        if (function_exists('wp_cache_get')) {
            $cached = wp_cache_get($cache_key, 'orgues_nouvelles');
            if ($cached !== false) {
                return $cached;
            }
        }

        $liste = array();
        // Numéros individuels achetés par l'utilisateur (si memberships_plan vide ou contient ON/ONED)
        if (empty($memberships_plan) || in_array('on', (array) $memberships_plan) || in_array('oned', (array) $memberships_plan)) {
            $user_pods = pods('user', $user_id);
            $magazines = $user_pods->get_field('magazines');
            if (!empty($magazines)) {
                foreach ($magazines as $magazine) {
                    $magazine_id = $magazine['ID'];
                    $numero = pods('magazine', $magazine_id)->get_field('numero');
                    $liste[] = $numero;
                }
            }
        }
        // Numéros accessibles via les abonnements de l'utilisateur
        $memberships = wc_memberships_get_user_memberships();
        foreach ($memberships as $membership) {
            // Filtrer par slug si le paramètre est fourni
            if (!empty($memberships_plan)) {
                $plan = $membership->get_plan();
                if (!$plan) {
                    continue;
                }
                $slug = is_callable(array($plan, 'get_slug')) ? $plan->get_slug() : '';
                if (!in_array($slug, $memberships_plan)) {
                    continue;
                }
            }
            $start_date = $membership->get_start_date();
            $end_date = $membership->get_end_date();
            $next_payment_date = on_next_payment_date_membership($membership);
            
            // Determine effective end date
            $effective_end_date = $end_date;
            if (!empty($next_payment_date)) {
                if (empty($end_date) || $next_payment_date < $end_date) {
                    $effective_end_date = $next_payment_date;
                }
            }

            $numero_start = on_date_magazine_to_numero($start_date);
            $numero_end = on_date_magazine_to_numero($effective_end_date) - 1;

            for ($numero = $numero_start; $numero <= $numero_end; $numero++) {
                $liste[] = $numero;
            }
        }
        $liste = array_unique($liste);
        sort($liste);

        // Stocker le résultat en cache mémoire
        if (function_exists('wp_cache_set')) {
            wp_cache_set($cache_key, $liste, 'orgues_nouvelles', 300); // 5 minutes
        }

        return $liste;
    }
}