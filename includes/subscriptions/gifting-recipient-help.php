<?php
/**
 * Ajoute un message d'aide sous le champ email du destinataire cadeau.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('on_render_gifting_recipient_help')) {
    /**
     * Affiche un texte d'aide sous le champ email du destinataire cadeau.
     *
     * @return void
     */
    function on_render_gifting_recipient_help()
    {
        echo '<p class="form-row form-row-wide on-gifting-recipient-help">' . esc_html__('Indiquez ici l\'adresse e-mail de la personne à qui vous offrez cet abonnement.', 'orgues-nouvelles') . '</p>';
    }
}

add_action('wcsg_add_recipient_fields', 'on_render_gifting_recipient_help');
