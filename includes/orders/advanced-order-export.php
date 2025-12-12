<?php
class WOE_Memberships
{
    var $fields = array("plan", "status", "since", "expires");
    function __construct()
    {

        add_filter('woe_get_order_segments', function($segments) {
		$segments['membership'] = __( 'Membership', 'woocommerce-order-export' );
		return $segments;
    });

        add_filter('woe_get_order_fields_membership', function ($fields) {
            foreach ($this->fields as $f) {
                $fields['membership_' . $f] = array('segment' => 'membership', 'label' => "Member $f", 'colname' => "Member $f", 'checked' => 1);
            }
            return $fields;
        });

        add_filter('woe_settings_validate_defaults', function ($settings) {
            foreach ($this->fields as $f) {
                add_filter('woe_get_order_value_membership_' . $f, function ($value, $order, $field) {
                    return isset($this->data[$field]) ? $this->data[$field] : $value;
                }, 10, 3);
            }
            return $settings;
        });

        // rebuild for each order 
        add_filter('woe_order_export_started', function ($order_id) {
            // each order can create many memberships!
            $this->data = array();
            foreach ($this->fields as $f)
                $this->data[$f] = array();

            //gather details  
            $order = wc_get_order($order_id);
            $user_id = $order->get_user_id();
            $memberships = wc_memberships_get_user_active_memberships($user_id);
            foreach ($memberships as $m) {
                $this->data['plan'][] = $m->get_plan()->get_name();
                $this->data['status'][] = $m->get_status();
                $this->data['since'][] = $m->get_start_date();
                $this->data['expires'][] = $m->get_end_date();
            }

            // convert to multiline cells
            foreach ($this->data as $f => $v)
                $this->data['membership_' . $f] = join("\n", $v);
            return $order_id;
        });
    }
}
new WOE_Memberships();
