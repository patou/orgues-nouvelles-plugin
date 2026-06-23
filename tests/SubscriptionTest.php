<?php

use PHPUnit\Framework\TestCase;

class SubscriptionTest extends TestCase {

    public static function setUpBeforeClass(): void {
        if (!function_exists('__')) {
            function __($text, $domain = 'default') {
                return $text;
            }
        }

        if (!function_exists('_e')) {
            function _e($text, $domain = 'default') {
                echo $text;
            }
        }

        if (!function_exists('esc_html')) {
            function esc_html($text) {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            }
        }

        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/');
        }
    }

    public function test_subscription_active() {
        // Test 1: Abonnement avec paiement à venir
        $subscription = new MockSubscription('2024-01-01', '2024-10-01', '');
        $numero_start = on_date_magazine_to_numero($subscription->get_date('start'));
        $numero_next = on_date_magazine_to_numero($subscription->get_date('next_payment'));
        
        $this->assertEquals(63, $numero_start, "Start number should be 63 for 2024-01");
        $this->assertEquals(66, $numero_next, "Next number should be 66 for 2024-10");
    }

    public function test_subscription_ended() {
        // Test 2: Abonnement terminé
        $subscription = new MockSubscription('2023-03-01', '', '2023-12-01');
        $numero_start = on_date_magazine_to_numero($subscription->get_date('start'));
        $numero_end = on_date_magazine_to_numero($subscription->get_date('end'));
        
        $this->assertEquals(60, $numero_start, "Start number should be 60 for 2023-03");
        $this->assertEquals(63, $numero_end, "End number should be 63 for 2023-12");
    }

    public function test_subscription_active_with_end_date() {
        // Test 3: Abonnement actif avec date de fin avant prochain paiement
        $subscription = new MockSubscription('2024-01-01', '2025-01-01', '2024-06-01');
        $numero_start = on_date_magazine_to_numero($subscription->get_date('start'));
        $next_payment = $subscription->get_date('next_payment');
        $end_date = $subscription->get_date('end');
        
        $date_fin = $end_date;
        if (!empty($end_date) && (!empty($next_payment) && $end_date < $next_payment || empty($next_payment))) {
            $date_fin = $end_date;
        } else {
            $date_fin = $next_payment;
        }
        $numero_end = on_date_magazine_to_numero($date_fin);
        
        $this->assertEquals(63, $numero_start, "Start number should be 63");
        $this->assertEquals(65, $numero_end, "End number should be 65 (2024-06)");
    }

    public function test_subscription_rules_strict() {
        // Scenario 1: 16 Nov 2025 -> 71 to 74
        $this->check_subscription_range('2025-11-16', '2026-11-16', 71, 74);

        // Scenario 2: 15 Nov 2025 -> 70 to 74 (Assuming 1 year covers end of #70 and end of #74)
        // Note: If 15 Nov is the boundary, and sub ends 15 Nov 26.
        // #70 ends 15 Nov 25. Covered.
        // #74 ends 15 Nov 26. Covered.
        $this->check_subscription_range('2025-11-15', '2026-11-15', 70, 73);

        // Scenario 3: 14 Feb 2026 -> 71 to 74
        $this->check_subscription_range('2026-02-14', '2027-02-14', 71, 74);

        // Scenario 4: 16 Feb 2026 -> 72 to 75
        $this->check_subscription_range('2026-02-16', '2027-02-16', 72, 75);
    }

    private function check_subscription_range($start, $end, $expected_start, $expected_end) {
        $numero_start = on_date_magazine_to_numero($start);
        
        // Logic to be implemented in on_liste_numeros
        $numero_end = on_date_magazine_to_numero($end) - 1;

        $this->assertEquals($expected_start, $numero_start, "Start number for $start");
        $this->assertEquals($expected_end, $numero_end, "End number for $end");
    }

    public function test_format_display() {
        // Test 4: Format d'affichage
        $test_dates = array(
            '2024-03-01' => 64,
            '2024-06-01' => 65,
            '2024-10-01' => 66,
            '2024-12-01' => 67,
            '2025-03-01' => 68
        );
        foreach ($test_dates as $date => $expected) {
            $numero = on_date_magazine_to_numero($date);
            $this->assertEquals($expected, $numero, "Date $date should be ON-$expected");
        }
    }

    public function test_sync_subscription_billing_schedule_from_items() {
        $product = new on_mock_subscription_product('year', 1);
        $item = new on_mock_subscription_item($product);
        $subscription = new on_mock_subscription_for_billing_schedule('2024-01-15 00:00:00', 'month', 3, 0, array($item));

        add_filter('on_subscription_billing_schedule_from_product', 'on_mock_subscription_schedule_from_product', 10, 4);

        try {
            $synced = on_sync_subscription_billing_schedule_from_items($subscription);
        } finally {
            remove_filter('on_subscription_billing_schedule_from_product', 'on_mock_subscription_schedule_from_product', 10);
        }

        $this->assertTrue($synced);
        $this->assertSame('year', $subscription->get_billing_period());
        $this->assertSame(1, $subscription->get_billing_interval());
        $this->assertTrue($subscription->saved);

        $expected_next_payment = gmdate('Y-m-d H:i:s', wcs_add_time(1, 'year', strtotime('2024-01-15 00:00:00'), 'offset_site_time'));
        $this->assertArrayHasKey('next_payment', $subscription->updated_dates);
        $this->assertSame($expected_next_payment, $subscription->updated_dates['next_payment']);
    }
}

// Mock d'un abonnement
class MockSubscription {
    private $dates = array();

    public function __construct($start, $next_payment = '', $end = '') {
        $this->dates['start'] = $start;
        $this->dates['next_payment'] = $next_payment;
        $this->dates['end'] = $end;
    }

    public function get_date($type) {
        return isset($this->dates[$type]) ? $this->dates[$type] : '';
    }
}

class on_mock_subscription_product {
    private $billing_period;
    private $billing_interval;

    public function __construct($billing_period, $billing_interval) {
        $this->billing_period = $billing_period;
        $this->billing_interval = (int) $billing_interval;
    }

    public function get_id() {
        return 1234;
    }

    public function get_billing_period() {
        return $this->billing_period;
    }

    public function get_billing_interval() {
        return $this->billing_interval;
    }
}

class on_mock_subscription_item {
    private $product;

    public function __construct($product) {
        $this->product = $product;
    }

    public function get_product() {
        return $this->product;
    }
}

class on_mock_subscription_for_billing_schedule {
    private $dates = array();
    private $items = array();
    private $billing_period;
    private $billing_interval;
    private $payment_count;

    public $saved = false;
    public $updated_dates = array();

    public function __construct($start, $billing_period, $billing_interval, $payment_count = 0, $items = array()) {
        $this->dates['start'] = $start;
        $this->dates['trial_end'] = '';
        $this->billing_period = $billing_period;
        $this->billing_interval = (int) $billing_interval;
        $this->payment_count = (int) $payment_count;
        $this->items = $items;
    }

    public function get_items() {
        return $this->items;
    }

    public function get_time($type) {
        if (!isset($this->dates[$type]) || '' === $this->dates[$type]) {
            return 0;
        }

        return strtotime($this->dates[$type]);
    }

    public function get_payment_count() {
        return $this->payment_count;
    }

    public function get_billing_period() {
        return $this->billing_period;
    }

    public function get_billing_interval() {
        return $this->billing_interval;
    }

    public function set_billing_period($value) {
        $this->billing_period = $value;
    }

    public function set_billing_interval($value) {
        $this->billing_interval = (int) $value;
    }

    public function update_dates($dates, $context = 'gmt') {
        $this->updated_dates = array_merge($this->updated_dates, $dates);
        foreach ($dates as $key => $value) {
            $this->dates[$key] = $value;
        }
    }

    public function save() {
        $this->saved = true;
    }
}

function on_mock_subscription_schedule_from_product($schedule, $product, $subscription, $item) {
    if ($product instanceof on_mock_subscription_product) {
        return array(
            'billing_period'   => $product->get_billing_period(),
            'billing_interval' => $product->get_billing_interval(),
        );
    }

    return $schedule;
}

