<?php
/**
 * PHPUnit bootstrap file
 */

// Composer autoloader must be loaded before WP_PHPUNIT__DIR will be available
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $value ) {
		return strtolower( trim( (string) $value ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return max( 0, (int) $value );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		if ( 'timestamp' === $type ) {
			return time();
		}

		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'wcs_add_time' ) ) {
	function wcs_add_time( $interval, $period, $timestamp, $timezone = 'site' ) {
		$interval = max( 0, (int) $interval );
		$period   = sanitize_key( $period );

		return strtotime( '+' . $interval . ' ' . $period, (int) $timestamp );
	}
}

if ( ! isset( $GLOBALS['on_test_filters'] ) ) {
	$GLOBALS['on_test_filters'] = array();
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['on_test_filters'][ $hook_name ][ $priority ][] = array(
			'callback'      => $callback,
			'accepted_args' => (int) $accepted_args,
		);

		return true;
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	function remove_filter( $hook_name, $callback, $priority = 10 ) {
		if ( empty( $GLOBALS['on_test_filters'][ $hook_name ][ $priority ] ) ) {
			return false;
		}

		foreach ( $GLOBALS['on_test_filters'][ $hook_name ][ $priority ] as $index => $filter ) {
			if ( $filter['callback'] === $callback ) {
				unset( $GLOBALS['on_test_filters'][ $hook_name ][ $priority ][ $index ] );
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook_name, $value ) {
		if ( empty( $GLOBALS['on_test_filters'][ $hook_name ] ) ) {
			return $value;
		}

		$args = func_get_args();
		ksort( $GLOBALS['on_test_filters'][ $hook_name ] );

		foreach ( $GLOBALS['on_test_filters'][ $hook_name ] as $filters ) {
			foreach ( $filters as $filter ) {
				$filter_args = array_slice( $args, 1, $filter['accepted_args'] );
				$value       = call_user_func_array( $filter['callback'], $filter_args );
				$args[1]     = $value;
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook_name, $callback, $priority, $accepted_args );
	}
}

// Load the plugin core file
require_once dirname( __DIR__ ) . '/includes/core/orgues-nouvelles.php';
require_once dirname( __DIR__ ) . '/includes/core/subscription-formule-choices.php';
require_once dirname( __DIR__ ) . '/includes/subscriptions/admin-metabox.php';
require_once dirname( __DIR__ ) . '/includes/subscriptions/formule-on-detection.php';
require_once dirname( __DIR__ ) . '/includes/subscriptions/lifecycle.php';
