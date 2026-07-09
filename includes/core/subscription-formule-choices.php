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

    /**
     * Retourne le label d'une formule d'abonnement.
     *
     * @param string $formule
     * @return string
     */
    function on_get_subscription_formule_label($formule)
    {
        $choices = array(
            'ON' => __('Magazine papier', 'orgues-nouvelles'),
            'ONED' => __('Magazine numérique', 'orgues-nouvelles'),
            'ONEDA' => __('English Digital magazine', 'orgues-nouvelles'),
        );
        return isset($choices[$formule]) ? $choices[$formule] : '';
    }
}