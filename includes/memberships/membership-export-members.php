<?php
// Ajoute un champ personnalisé pour exporter uniquement les membres actifs après le champ des statuts
add_filter( 'wc_memberships_csv_export_user_memberships_options', function( $options ) {
    $new_field = [
        'id'       => 'wc_memberships_members_csv_export_active_only',
        'title'    => __( 'Exporter uniquement les membres actifs', 'woocommerce-memberships' ),
        'desc'     => __( 'Cochez cette case pour inclure uniquement les membres actifs dans l\'exportation.', 'woocommerce-memberships' ),
        'desc_tip' => __( 'Seulement les membres ayant une adhésion active seront inclus dans le fichier exporté.', 'woocommerce-memberships' ),
        'type'     => 'checkbox',
        'default'  => 'no',
    ];

    // Insère le nouveau champ après le champ des statuts
    $status_field_index = array_search( 'wc_memberships_members_csv_export_status', array_column( $options, 'id' ) );
    if ( $status_field_index !== false ) {
        array_splice( $options, $status_field_index + 1, 0, [$new_field] );
    } else {
        $options[] = $new_field;
    }

    return $options;
});

// Ajoute un BOM (Byte Order Mark) au fichier CSV exporté
add_filter( 'wc_memberships_csv_export_enable_bom', function( $enable_bom, $export_handler ) {
    return true; // Active le BOM pour tous les fichiers exportés
}, 10, 2 );

add_filter( 'wc_memberships_csv_export_user_memberships_query_args', function( $query_args ) {
    // Vérifie si l'option d'exportation des membres actifs uniquement est activée
    // Récupère dans $_POST la valeur du champ personnalisé export_params[active_only]

    if ( isset( $_POST['export_params']['active_only'] ) && 'yes' === $_POST['export_params']['active_only'] && $query_args['post_status'] == 'any' ) {
        // Modifie les arguments de la requête pour n'inclure que les membres actifs
        $query_args['post_status'] = array_map( function( $s ) { return 0 === strpos( $s, 'wcm-' ) ? $s : 'wcm-' . $s; }, (array) wc_memberships()->get_user_memberships_instance()->get_active_access_membership_statuses() );
    }

    return $query_args;
}, 10, 2 );