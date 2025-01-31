<?php

defined('ABSPATH') or exit;

/**
 * Membership justificatif etudiant email.
 *
 * @type string $email_heading email heading
 * @type string $additional_content Additional content to be added to the email
 *
 * @version 1.25.0
 * @since 1.0.0
 */
do_action('woocommerce_email_header', $email_heading, $email);

?>
<p><?php printf( esc_html__( 'Hi %s,', 'woocommerce' ), esc_html( $order->get_billing_first_name() ) ); ?></p>
<?php

echo wpautop(wptexturize($email_body));

if ($additional_content) {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

do_action('woocommerce_email_footer', $email);
?>

