<?php

if ( !function_exists( 'on_url_ressources' ) ) {
    /**
     * Fonction utilisée sur la page ressources pour garder les paramètres de recherche dans les liens de catégories de ressources
     * 
     * Utilisé par le Custom Function Dynamic Tag d'Elementor
     *
     * @param [type] $url
     * @return void
     */
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
        if (isset($_GET['id'])) {
            $args['id'] = $_GET['id'];
        }
        if (isset($_GET['s'])) {
            $args['s'] = $_GET['s'];
        }
        if (isset($_GET['post_type'])) {
            $args['post_type'] = $_GET['post_type'];
        }
        if (empty($args)) {
            return $url;
        }
        return $url . '?' . http_build_query($args);
    }
}

if ( !function_exists( 'on_lien_ressources' ) ) {
    /**
     * Fonction qui retourne le champ lien si il est renseigné, sinon retourne le permalien
     *
     * @return string le lien
     */
    function on_lien_ressources() {
        $lien = pods_field('lien', true);
        if (!empty($lien)) {
            return wp_kses_post( $lien );
        }
        return wp_kses_post( get_permalink() );
    }
}
