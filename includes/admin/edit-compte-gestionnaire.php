<?php

add_filter('woocommerce_shop_manager_editable_roles', 'on_shop_manager_user_edit', 20, 1);

function on_shop_manager_user_edit( $roles ) {
    return array('customer', 'subscriber');
}