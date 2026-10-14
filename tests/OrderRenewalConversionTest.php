<?php

use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook_name, $value) {
        return $value;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($value) {
        return strtolower(trim((string) $value));
    }
}

if (!function_exists('absint')) {
    function absint($value) {
        return max(0, (int) $value);
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id) {
        $emails = array(
            10 => 'offreur@example.test',
            22 => 'beneficiaire@example.test',
        );

        if (!isset($emails[(int) $user_id])) {
            return false;
        }

        return (object) array('user_email' => $emails[(int) $user_id]);
    }
}

if (!class_exists('WC_Product')) {
    class WC_Product {}
}

if (!class_exists('WC_Order')) {
    class WC_Order {}
}

if (!class_exists('WC_Subscription')) {
    class WC_Subscription extends WC_Order {}
}

if (!class_exists('WCS_Gifting')) {
    class WCS_Gifting {
        public static function is_gifted_subscription($subscription) {
            return (bool) $subscription->get_meta('_recipient_user', true) || (bool) $subscription->get_meta('_recipient_user_email_address', true);
        }

        public static function get_recipient_user($subscription) {
            return $subscription->get_meta('_recipient_user', true);
        }

        public static function get_order_item_recipient_user_id($order_item) {
            return $order_item->get_meta('wcsg_recipient', true) ? str_replace('wcsg_recipient_id_', '', $order_item->get_meta('wcsg_recipient', true)) : false;
        }
    }
}

require_once dirname(__DIR__) . '/includes/core/subscription-formule-choices.php';

if (!function_exists('on_sanitize_subscription_formule')) {
    function on_sanitize_subscription_formule($value) {
        if (!is_scalar($value)) {
            return '';
        }

        $value = strtoupper(sanitize_text_field((string) $value));

        return in_array($value, array_keys(on_get_subscription_formule_choices()), true) ? $value : '';
    }
}

require_once dirname(__DIR__) . '/includes/subscriptions/formule-on-detection.php';
require_once dirname(__DIR__) . '/includes/orders/auto-renewal-conversion.php';

class OrderRenewalConversionTest extends TestCase {
    public function test_guess_order_formule_requires_one_subscription_formule() {
        $order = new on_mock_renewal_order(10, 'offreur@example.test', array(
            new on_mock_renewal_item(new on_mock_renewal_product('ONED')),
            new on_mock_renewal_item(new on_mock_renewal_product('ONED-ANNUEL')),
        ));

        $this->assertSame('ONED', on_guess_subscription_formule_from_order_items($order));
    }

    public function test_guess_order_formule_rejects_mixed_formules() {
        $order = new on_mock_renewal_order(10, 'offreur@example.test', array(
            new on_mock_renewal_item(new on_mock_renewal_product('ONED')),
            new on_mock_renewal_item(new on_mock_renewal_product('ONEDA')),
        ));

        $this->assertSame('', on_guess_subscription_formule_from_order_items($order));
    }

    public function test_gift_order_identity_uses_recipient_user() {
        $order = new on_mock_renewal_order(10, 'offreur@example.test', array(
            new on_mock_renewal_item(new on_mock_renewal_product('ON'), array('wcsg_recipient' => 'wcsg_recipient_id_22')),
        ));

        $identity = on_get_order_effective_renewal_identity($order);

        $this->assertTrue($identity['is_gift']);
        $this->assertSame(22, $identity['user_id']);
        $this->assertSame('beneficiaire@example.test', $identity['email']);
    }

    public function test_gifted_subscription_does_not_match_donor_identity() {
        $order = new on_mock_renewal_order(10, 'offreur@example.test', array(
            new on_mock_renewal_item(new on_mock_renewal_product('ON')),
        ), '2026-01-01 00:00:00');
        $subscription = new on_mock_renewal_subscription(101, 10, 'offreur@example.test', array(
            '_recipient_user' => 22,
            '_recipient_user_email_address' => 'beneficiaire@example.test',
            'on_formule' => 'ON',
        ), '2026-01-15 00:00:00');

        $score = on_score_order_subscription_renewal_match($order, $subscription, 'ON', on_get_order_effective_renewal_identity($order));

        $this->assertLessThan(70, $score);
    }

    public function test_gifted_subscription_matches_effective_recipient_identity() {
        $order = new on_mock_renewal_order(10, 'offreur@example.test', array(
            new on_mock_renewal_item(new on_mock_renewal_product('ON'), array('wcsg_recipient' => 'wcsg_recipient_id_22')),
        ), '2026-01-01 00:00:00');
        $subscription = new on_mock_renewal_subscription(101, 10, 'offreur@example.test', array(
            '_recipient_user' => 22,
            '_recipient_user_email_address' => 'beneficiaire@example.test',
            'on_formule' => 'ON',
        ), '2026-01-15 00:00:00');

        $score = on_score_order_subscription_renewal_match($order, $subscription, 'ON', on_get_order_effective_renewal_identity($order));

        $this->assertSame(100, $score);
    }

    public function test_active_subscription_same_formule_is_eligible_even_outside_due_window() {
        $order = new on_mock_renewal_order(10, 'offreur@example.test', array(
            new on_mock_renewal_item(new on_mock_renewal_product('ON')),
        ), '2026-01-01 00:00:00');
        $subscription = new on_mock_renewal_subscription(101, 10, 'offreur@example.test', array(
            'on_formule' => 'ON',
        ), '2027-01-01 00:00:00', 'active');

        $this->assertTrue(on_subscription_is_eligible_for_auto_renewal_conversion($subscription, 'ON', $order));
    }

    public function test_recent_cancelled_subscription_same_formule_is_eligible() {
        $order = new on_mock_renewal_order(10, 'offreur@example.test', array(
            new on_mock_renewal_item(new on_mock_renewal_product('ON')),
        ), '2026-09-22 00:00:00');
        $subscription = new on_mock_renewal_subscription(101, 10, 'offreur@example.test', array(
            'on_formule' => 'ON',
        ), array('cancelled' => '2026-06-01 00:00:00'), 'cancelled');

        $this->assertTrue(on_subscription_is_eligible_for_auto_renewal_conversion($subscription, 'ON', $order));
    }

    public function test_old_cancelled_subscription_same_formule_is_not_eligible() {
        $order = new on_mock_renewal_order(10, 'offreur@example.test', array(
            new on_mock_renewal_item(new on_mock_renewal_product('ON')),
        ), '2026-09-22 00:00:00');
        $subscription = new on_mock_renewal_subscription(101, 10, 'offreur@example.test', array(
            'on_formule' => 'ON',
        ), array('cancelled' => '2026-01-01 00:00:00'), 'cancelled');

        $this->assertFalse(on_subscription_is_eligible_for_auto_renewal_conversion($subscription, 'ON', $order));
    }

    public function test_next_payment_is_extended_from_future_due_date() {
        $order = new on_mock_renewal_order(10, 'offreur@example.test', array(
            new on_mock_renewal_item(new on_mock_renewal_product('ON')),
        ), '2026-09-22 00:00:00');
        $subscription = new on_mock_renewal_subscription(101, 10, 'offreur@example.test', array(
            'on_formule' => 'ON',
        ), array('next_payment' => '2026-12-01 00:00:00'), 'active', 'year', 1);

        $this->assertTrue(on_extend_subscription_next_payment_on_renewal_conversion($subscription, $order));
        $this->assertSame('2027-12-01 00:00:00', $subscription->updated_dates['next_payment']);
    }

    public function test_next_payment_is_extended_from_order_date_when_due_date_is_past() {
        $order = new on_mock_renewal_order(10, 'offreur@example.test', array(
            new on_mock_renewal_item(new on_mock_renewal_product('ON')),
        ), '2026-09-22 00:00:00');
        $subscription = new on_mock_renewal_subscription(101, 10, 'offreur@example.test', array(
            'on_formule' => 'ON',
        ), array('next_payment' => '2026-01-01 00:00:00'), 'expired', 'year', 1);

        $this->assertTrue(on_extend_subscription_next_payment_on_renewal_conversion($subscription, $order));
        $this->assertSame('2027-09-22 00:00:00', $subscription->updated_dates['next_payment']);
    }
}

class on_mock_renewal_product extends WC_Product {
    private $sku;

    public function __construct($sku) {
        $this->sku = $sku;
    }

    public function get_sku($context = 'view') {
        return $this->sku;
    }

    public function is_type($types) {
        return in_array('subscription', (array) $types, true);
    }
}

class on_mock_renewal_item {
    private $product;
    private $meta;

    public function __construct($product, $meta = array()) {
        $this->product = $product;
        $this->meta = $meta;
    }

    public function get_product() {
        return $this->product;
    }

    public function get_meta($key, $single = true) {
        return isset($this->meta[$key]) ? $this->meta[$key] : '';
    }
}

class on_mock_renewal_order extends WC_Order {
    private $user_id;
    private $email;
    private $order_items;
    private $date;

    public function __construct($user_id, $email, $items, $date = '2026-01-01 00:00:00') {
        $this->user_id = (int) $user_id;
        $this->email = $email;
        $this->order_items = $items;
        $this->date = new DateTimeImmutable($date);
    }

    public function get_items($types = 'line_item') {
        return $this->order_items;
    }

    public function get_user_id($context = 'view') {
        return $this->user_id;
    }

    public function get_billing_email($context = 'view') {
        return $this->email;
    }

    public function get_date_paid($context = 'view') {
        return $this->date;
    }
}

class on_mock_renewal_subscription extends WC_Subscription {
    private $subscription_id;
    private $customer_id;
    private $billing_email;
    private $meta;
    private $dates;
    private $status;
    private $billing_period;
    private $billing_interval;
    public $updated_dates = array();

    public function __construct($id, $customer_id, $billing_email, $meta, $dates, $status = 'active', $billing_period = 'year', $billing_interval = 1) {
        $this->subscription_id = (int) $id;
        $this->customer_id = (int) $customer_id;
        $this->billing_email = $billing_email;
        $this->meta = $meta;
        $this->dates = array();
        if (is_array($dates)) {
            foreach ($dates as $type => $date) {
                $this->dates[$type] = strtotime($date);
            }
        } else {
            $this->dates['next_payment'] = strtotime($dates);
        }
        $this->status = $status;
        $this->billing_period = $billing_period;
        $this->billing_interval = (int) $billing_interval;
    }

    public function get_id() {
        return $this->subscription_id;
    }

    public function get_customer_id($context = 'view') {
        return $this->customer_id;
    }

    public function get_billing_email($context = 'view') {
        return $this->billing_email;
    }

    public function get_meta($key = '', $single = true, $context = 'view') {
        return isset($this->meta[$key]) ? $this->meta[$key] : '';
    }

    public function get_items($types = 'line_item') {
        return array();
    }

    public function get_time($type, $timezone = 'gmt', $exclude_statuses = array()) {
        return isset($this->dates[$type]) ? $this->dates[$type] : 0;
    }

    public function has_status($status) {
        return in_array($this->status, (array) $status, true);
    }

    public function get_billing_period() {
        return $this->billing_period;
    }

    public function get_billing_interval() {
        return $this->billing_interval;
    }

    public function update_dates($dates, $context = 'gmt') {
        $this->updated_dates = array_merge($this->updated_dates, $dates);
        foreach ($dates as $type => $date) {
            $this->dates[$type] = strtotime($date);
        }
    }
}