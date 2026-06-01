<?php
/**
 * Choix de formules d'abonnement partages.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('on_get_subscription_formule_choices')) {
    /**
     * Retourne les formules disponibles pour les abonnements.
     *
     * @return array
     */
    function on_get_subscription_formule_choices()
    {
        $choices = array(
            'ON' => __('ON', 'orgues-nouvelles'),
            'ONED' => __('ONED', 'orgues-nouvelles'),
            'ONEDA' => __('ONEDA', 'orgues-nouvelles'),
        );

        return (array) apply_filters('on_subscription_formule_choices', $choices);
    }
}