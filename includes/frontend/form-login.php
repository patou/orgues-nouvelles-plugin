<?php
/**
 * Modifications de la page de connexion "Mon compte"
 *
 * 1. Déplace et met en valeur le lien "Mot de passe oublié ?" juste sous le champ Mot de passe.
 * 2. Affiche un message d'info sous le bouton Se connecter pour inviter à s'abonner.
 *
 * @package Orgues-Nouvelles Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Injecte un lien "Mot de passe oublié ?" mis en valeur juste après le champ Mot de passe,
 * avant les boutons du formulaire de connexion.
 */
function on_lost_password_link_after_password_field() {
	printf(
		'<p class="on-lost-password-hint"><a href="%s">%s</a></p>',
		esc_url( wp_lostpassword_url() ),
		esc_html__( 'Mot de passe oublié ? Cliquez ici pour le réinitialiser', 'orgues-nouvelles' )
	);
}
add_action( 'woocommerce_login_form', 'on_lost_password_link_after_password_field', 5 );

/**
 * Ajoute les styles CSS nécessaires sur la page Mon compte :
 * - masque le lien d'origine (discret, après le bouton Se connecter) ;
 * - style le nouveau lien en couleur et le met en évidence.
 */
function on_login_form_inline_css() {
	if ( ! is_account_page() ) {
		return;
	}

	$css = '
		/* Masquer le lien "Mot de passe perdu ?" d\'origine (après le bouton, peu visible) */
		.woocommerce-LostPassword.lost_password {
			display: none;
		}

		/* Lien mot de passe oublié visible, juste sous le champ Mot de passe */
		.on-lost-password-hint {
			margin: -0.5em 0 1em;
		}
		.on-lost-password-hint a {
			color: #ff542e;
			font-weight: 600;
			text-decoration: underline;
		}
		.on-lost-password-hint a:hover,
		.on-lost-password-hint a:focus {
			color: #cc3a18;
			text-decoration: none;
		}

		/* Message d\'info abonnement sous le bouton Se connecter */
		.on-login-subscribe-info {
			margin-top: 1.25em;
			padding-top: 1em;
			border-top: 1px solid #e5e5e5;
			font-size: 0.95em;
		}
		.on-login-subscribe-info a {
			color: #ff542e;
			font-weight: 600;
			text-decoration: underline;
		}
		.on-login-subscribe-info a:hover,
		.on-login-subscribe-info a:focus {
			color: #cc3a18;
			text-decoration: none;
		}
        .woocommerce-form-login {
            width: 600px;
            margin: auto;
        }

	';

	wp_add_inline_style( 'woocommerce-general', $css );
}
add_action( 'wp_enqueue_scripts', 'on_login_form_inline_css' );

/**
 * Affiche un message d'information sous le bouton Se connecter :
 * pour créer un compte, il faut s'abonner sur le site.
 */
function on_login_form_subscribe_info() {
	printf(
		'<p class="on-login-subscribe-info">%s <a href="%s">%s</a></p>',
		esc_html__( 'Pas encore de compte ? Pour en créer un,', 'orgues-nouvelles' ),
		esc_url( home_url( '/product-category/abonnement/' ) ),
		esc_html__( 'abonnez-vous sur le site.', 'orgues-nouvelles' )
	);
}
add_action( 'woocommerce_login_form_end', 'on_login_form_subscribe_info' );
