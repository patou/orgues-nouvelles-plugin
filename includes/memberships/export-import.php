<?php

/**
 * Ajoute les colonnes "numero_since" et "numero_end" dans l'export des membres
 * 
 */
function on_wc_memberships_modify_member_export_headers( $headers ) {

	// remove any unwanted headers
	unset( $headers['membership_plan_id'] );

	$new_headers = array();

	// add a column header for "numero_since" and "numero_end"
	foreach ( $headers as $key => $name ) {

		$new_headers[ $key ] = $name;

		if ( 'member_since' == $key ) {
			$new_headers['numero_since'] = 'numero_since';
		}
        if ( 'membership_expiration' == $key ) {
			$new_headers['numero_end'] = 'numero_end';
		}
	}

	return $new_headers;
}
add_filter( 'wc_memberships_csv_export_user_memberships_headers', 'on_wc_memberships_modify_member_export_headers' );

/**
 * Convertis les dates en numéro de magazine
 */
function on_wc_memberships_csv_export_user_memberships_numero_column( $data, $key, $user_membership ) {
    $date = $key == 'numero_since' ? $user_membership->get_start_date() : $user_membership->get_end_date();
    if ($key == 'numero_end') {
		$next_payment_date = on_next_payment_date_membership($user_membership);
		if ($next_payment_date) {
			$date = $next_payment_date;
		}
	}
	if (empty($date)) {
        return '';
    }
    $numero_since = on_date_magazine_to_numero($date);
    return $numero_since;
}
add_filter( 'wc_memberships_csv_export_user_memberships_numero_since_column', 'on_wc_memberships_csv_export_user_memberships_numero_column', 10, 3 );
add_filter( 'wc_memberships_csv_export_user_memberships_numero_end_column', 'on_wc_memberships_csv_export_user_memberships_numero_column', 10, 3 );

/**
 * Ajoute les colonnes "numero_since" et "numero_end" dans l'import des membres
 */
function on_wc_memberships_modify_import_data( $import_data, $action, $columns, $row ) {

	if ( isset( $columns['numero_since'] ) ) {
        $numero_since = $row[ $columns['numero_since'] ];
		$date_since = on_numero_to_date_magazine($numero_since);
		$import_data['member_since'] =  !empty($date_since) ? $date_since . '-01' : '';
	}
    if ( isset( $columns['numero_end'] ) ) {
        $numero_end = $row[ $columns['numero_end'] ];
		$date_end = on_numero_to_date_magazine($numero_end);
        $import_data['membership_expiration'] = !empty($date_end) ? $date_end . '-28' : 'unlimited';
    }
	
	return $import_data;
}
add_filter( 'wc_memberships_csv_import_user_memberships_data', 'on_wc_memberships_modify_import_data', 10, 4 );

/**
 * Filtre le champ personnalisé nombre_exemplaires pour retourner 1 si vide ou égal à 0
 */
function on_wc_memberships_filter_nombre_exemplaires( $row_data, $user_membership ) {
	if ( isset( $row_data['nombre_exemplaires'] ) ) {
		$value = trim($row_data['nombre_exemplaires']);
		if ( empty( $value ) || $value == 0 || $value == "0" ) {
			$row_data['nombre_exemplaires'] = 1;
		}
	}
	return $row_data;
}
add_filter( 'wc_memberships_csv_export_user_memberships_row', 'on_wc_memberships_filter_nombre_exemplaires', 10, 2 );
