<?php
/**
 * PHPUnit bootstrap file
 */

// Composer autoloader must be loaded before WP_PHPUNIT__DIR will be available
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load the plugin core file
require_once dirname( __DIR__ ) . '/includes/core/orgues-nouvelles.php';
