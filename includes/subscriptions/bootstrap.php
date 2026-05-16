<?php
/**
 * Bootstrap du module WooCommerce Subscriptions.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/issue-count-core.php';
require_once __DIR__ . '/admin-metabox.php';
require_once __DIR__ . '/admin-columns.php';
require_once __DIR__ . '/issue-count-hooks.php';
require_once __DIR__ . '/lifecycle.php';