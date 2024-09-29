<?php

add_filter('wc_memberships_csv_import_delimiter_options', function($options) {
    $options['semicolon'] = __('Semicolon', 'woocommerce-memberships');
    return $options;
});

add_filter( 'wc_memberships_csv_delimiter', function($delimiter, $item) {
    if ( is_object( $item ) && isset( $item->fields_delimiter ) && is_string( $item->fields_delimiter ) &&  $item->fields_delimiter == 'semicolon') {
        return ';';
    }
    return $delimiter;
} , 10, 2);