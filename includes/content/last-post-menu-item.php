<?php

function on_add_nav_menu_meta_boxes() {
    add_meta_box( 'last_posts_nav_link', __( 'Last post', 'orgues-nouvelles' ), 'on_nav_menu_links', 'nav-menus', 'side', 'low' );
}

add_action( 'admin_head-nav-menus.php', 'on_add_nav_menu_meta_boxes' );

function on_nav_menu_links() {
    $types = get_post_types(array('public' => true, 'hierarchical' => false), 'objects');

    $types = apply_filters( 'on_last_posts_nav_items_types', $types );

    ?>
    <div id="last_posts_nav" class="posttypediv">
        <div id="tabs-panel-last_posts_nav" class="tabs-panel tabs-panel-active">
            <ul id="last_posts_nav-checklist" class="categorychecklist form-no-clear">
                <?php
                $i = -1;
                foreach ( $types as $type ) :
                    $labels = get_post_type_labels( $type );
                    ?>
                    <li>
                        <label class="menu-item-title">
                            <input type="checkbox" class="menu-item-checkbox" name="menu-item[<?php echo esc_attr( $i ); ?>][menu-item-object-id]" value="<?php echo esc_attr( $i ); ?>" /> <?php echo esc_html( $labels->singular_name ); ?>
                        </label>
                        <input type="hidden" class="menu-item-type" name="menu-item[<?php echo esc_attr( $i ); ?>][menu-item-type]" value="last" />
                        <input type="hidden" class="menu-item-title" name="menu-item[<?php echo esc_attr( $i ); ?>][menu-item-title]" value="<?php echo esc_attr( $labels->singular_name ); ?>" />
                        <input type="hidden" class="menu-item-object" name="menu-item[<?php echo esc_attr( $i ); ?>][menu-item-object]" value="<?php echo esc_attr( $type->name ); ?>" />
                        <input type="hidden" class="menu-item-classes" name="menu-item[<?php echo esc_attr( $i ); ?>][menu-item-classes]" />
                    </li>
                    <?php
                    $i--;
                endforeach;
                ?>
            </ul>
        </div>
        <p class="button-controls" data-items-type="last_posts_nav">
            <span class="list-controls">
                <label>
                    <input type="checkbox" class="select-all" />
                    <?php esc_html_e( 'Select all', 'woocommerce' ); ?>
                </label>
            </span>
            <span class="add-to-menu">
                <button type="submit" class="button-secondary submit-add-to-menu right" value="<?php esc_attr_e( 'Add to menu', 'woocommerce' ); ?>" name="add-post-type-menu-item" id="submit-last_posts_nav"><?php esc_html_e( 'Add to menu', 'woocommerce' ); ?></button>
                <span class="spinner"></span>
            </span>
        </p>
    </div>
    <?php
}

add_filter( 'wp_setup_nav_menu_item', 'on_setup_nav_menu_item' );
function on_setup_nav_menu_item( $menu_item ) {
    if ( isset( $menu_item->type ) && 'last' === $menu_item->type ) {
        //print_r($menu_item);
        $menu_item->url = on_get_latest_permalink_by_menu_order( $menu_item->object );
        $menu_item->type_label = __( 'Last', 'orgues-nouvelles' );
    }

    return $menu_item;
}