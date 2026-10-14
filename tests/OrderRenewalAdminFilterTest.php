<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/includes/orders/auto-renewal-conversion.php';
require_once dirname(__DIR__) . '/includes/orders/admin-renewal-review-filter.php';

class OrderRenewalAdminFilterTest extends TestCase {
    public function test_status_filter_accepts_only_supported_states() {
        $this->assertSame('review', on_get_selected_auto_renewal_order_status_filter('review'));
        $this->assertSame('converted', on_get_selected_auto_renewal_order_status_filter('converted'));
        $this->assertSame('ignored', on_get_selected_auto_renewal_order_status_filter('ignored'));
        $this->assertSame('error', on_get_selected_auto_renewal_order_status_filter('error'));
        $this->assertSame('', on_get_selected_auto_renewal_order_status_filter('pending'));
        $this->assertSame('', on_get_selected_auto_renewal_order_status_filter('review<script>'));
    }

    public function test_meta_query_preserves_existing_conditions() {
        $existing_meta_query = array(
            'relation' => 'AND',
            array(
                'key' => '_customer_user',
                'value' => 123,
                'compare' => '=',
            ),
        );

        $query_args = on_add_auto_renewal_status_meta_query(
            array('meta_query' => $existing_meta_query),
            'review'
        );

        $this->assertSame('AND', $query_args['meta_query']['relation']);
        $this->assertSame($existing_meta_query[0], $query_args['meta_query'][0]);
        $this->assertSame(ON_AUTO_RENEWAL_CONVERSION_STATUS_META, $query_args['meta_query'][1]['key']);
        $this->assertSame('review', $query_args['meta_query'][1]['value']);
    }

    public function test_hpos_filter_only_changes_order_queries() {
        $_GET['on_renewal_conversion_status'] = 'review';

        $order_args = on_filter_hpos_orders_by_auto_renewal_status(array('type' => 'shop_order'));
        $subscription_args = on_filter_hpos_orders_by_auto_renewal_status(array('type' => 'shop_subscription'));

        unset($_GET['on_renewal_conversion_status']);

        $this->assertArrayHasKey('meta_query', $order_args);
        $this->assertSame('shop_subscription', $subscription_args['type']);
        $this->assertArrayNotHasKey('meta_query', $subscription_args);
    }
}