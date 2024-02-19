<?php

if ( !function_exists( 'on_url_ressources' ) ) {
    function on_url_ressources($url) {
        $args = array();
        if (isset($_GET['numero'])) {
            $args['numero'] = $_GET['numero'];
        }
        if (isset($_GET['page'])) {
            $args['page'] = $_GET['page'];
        }
        if (isset($_GET['s'])) {
            $args['s'] = $_GET['s'];
        }
        if (empty($args)) {
            return $url;
        }
        return $url . '?' . http_build_query($args);
    }
}
