<?php

defined('ABSPATH') or exit;

/**
 * Membership justificatif etudiant plain text.
 *
 * @type string $email_heading email heading
 * @type string $additional_content Additional content to be added to the email
 *
 * @version 1.25.0
 * @since 1.0.0
 */
echo $email_heading . "\n\n";

echo "----------\n\n";

echo sprintf(esc_html__('Hi %s,', 'woocommerce'), esc_html($order->get_billing_first_name())) . "\n\n";


echo wptexturize( $email_body );

echo "----------\n\n";

if ( $additional_content ) {
	echo wptexturize( $additional_content ) . "\n\n";
}

echo (string) apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text', '' ) );
