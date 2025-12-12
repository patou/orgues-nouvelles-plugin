<?
// Shortcode to output custom PHP in Elementor
if (!function_exists('on_content_shortcode')) {
    function on_content_shortcode( $atts ) {
        global $more;
        $last = $more;
        $more = 1;
        the_content();
        $more = $last;
    }
    add_shortcode( 'on_content', 'on_content_shortcode');
}