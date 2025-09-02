<?php
define('ON_VISIBILITY_SECTION', 'on_visibility_section');
define('ON_VISIBILITY_CONDITION', 'on_visibility_condition');
define('ON_VISIBILITY_MESSAGE', 'on_visibility_message');
function on_register_visibility_section($element_section)
{

    $element_section->start_controls_section(ON_VISIBILITY_SECTION, [
        'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
        'label' => __('Abonnés Magazines', 'orgues-nouvelles'),
    ]);

    $element_section->end_controls_section();
}
add_action('elementor/element/common/_section_style/after_section_end', 'on_register_visibility_section');

function on_register_visibility_controls($element) {
    $element->add_control(ON_VISIBILITY_CONDITION, [
        'label' => __('Visibilité aux magazines', 'orgues-nouvelles'),
        'type' => \Elementor\Controls_Manager::SELECT,
        'default' => 'visible_to_everyone',
        'options' => [
            'visible_to_everyone' => __('Visible pour tous', 'orgues-nouvelles'),
            'visible_only_subscribers' => __('Seulement les abonnés', 'orgues-nouvelles'),
            'hidden_for_subscribers' => __('Seulement les non abonnés', 'orgues-nouvelles'),
        ],
        'multiple' => false,
        'show_label' => true,
        'label_block' => true,
        'description' => __('Choisissez qui peut voir ce contenu pour les abonnés comprenant le numéro en cours', 'orgues-nouvelles'),
        'section' => ON_VISIBILITY_SECTION,
    ]);

    $element->add_control(ON_VISIBILITY_MESSAGE, [
        'label' => __('Affiche le message de restriction', 'orgues-nouvelles'),
        'type' => \Elementor\Controls_Manager::SWITCHER,
        'label_on' => __('Yes', 'woocommerce-memberships'),
        'label_off' => __('No', 'woocommerce-memberships'),
        'default' => 'yes',
        'section' => ON_VISIBILITY_SECTION,
    ]);
}

// register section controls
add_action('elementor/element/common/' . ON_VISIBILITY_SECTION . '/before_section_end', 'on_register_visibility_controls');

function on_ajouter_numero_apres_achat($order_id)
{
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();

    if ($user_id) {
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $magazine = pods('product', $product_id)->field('magazine', true);

            if ($magazine) {
                // Ajouter le numéro à la liste de l'utilisateur
                $magazine_id = $magazine['ID'];
                $user_pods = pods('user', $user_id);
                $user_magazines = $user_pods->get_field('magazines');

                if (empty($user_magazines)) {
                    $user_magazines = array();
                }

                $magazine_exists = false;
                foreach ($user_magazines as $user_magazine) {
                    if ($user_magazine['ID'] == $magazine_id) {
                        $magazine_exists = true;
                        break;
                    }
                }

                // Vérifier si le numéro n'est pas déjà dans la liste
                if ($magazine_id && !$magazine_exists) {
                    $user_pods->add_to('magazines', $magazine_id);
                }
            }
        }
    }
}
add_action('woocommerce_order_status_completed', 'on_ajouter_numero_apres_achat');

function on_retrict_magazines_elementor_should_render_element($should_render, $widget)
{
    if ($widget->get_settings(ON_VISIBILITY_CONDITION) === 'visible_to_everyone' || is_preview()) {
        return $should_render;
    }

    $numero = pods_field('numero', true);
    if (!$numero) {
        return $should_render;
    }

    $magazines = on_liste_numeros();
    $magazine_allowed = array_search($numero, $magazines) !== false;
    if ($widget->get_settings(ON_VISIBILITY_CONDITION) === 'visible_only_subscribers') {
        $display_message = $widget->get_settings(ON_VISIBILITY_MESSAGE) === 'yes';
        return $magazine_allowed || $display_message;
    }

    if ($widget->get_settings(ON_VISIBILITY_CONDITION) === 'hidden_for_subscribers') {
        return !$magazine_allowed;
    }

    return $should_render;
}

function on_retrict_magazines_elementor_maybe_render_content_restricted_message_instead($content, $widget)
{
    if ($widget->get_settings(ON_VISIBILITY_CONDITION) === 'visible_to_everyone') {
        return $content;
    }

    if (is_preview()) {
        return '<div class="restrict-preview '.($widget->get_settings(ON_VISIBILITY_CONDITION) === 'hidden_for_subscribers' ? 'hidden' : 'visible').'">' . $content . '</div>';
    }

    $numero = pods_field('numero', true);
    if (!$numero || \Elementor\Plugin::$instance->editor->is_edit_mode()) {
        return $content;
    }
    $display_message = $widget->get_settings(ON_VISIBILITY_MESSAGE) === 'yes';

    $magazines = on_liste_numeros();
    $magazine_allowed = array_search($numero, $magazines) !== false;
    switch($widget->get_settings(ON_VISIBILITY_CONDITION)) {
        case 'visible_only_subscribers':
            if (!$magazine_allowed) {
                return $display_message ? message_restricted() : '';
            }
            break;
        case 'hidden_for_subscribers':
            if ($magazine_allowed)
                return '';
            break;
    }
    return $content;
}

function message_restricted() {
    ob_start();
    ?>
    <div class="on-restricted-message">
        <h5><?php _e("Vous n'avez pas accès à ce contenu", 'orgues-nouvelles'); ?><?php echo ' ON n°', pods_field('numero', true); ?></h5>
    <?php
    if (!is_user_logged_in()):
        ?>
        <?php _e('Vous devez être connecté pour accéder à ce contenu.', 'orgues-nouvelles'); ?><br/>
        <a href="<?php echo esc_url(wp_login_url(get_permalink())) ?>"><?php _e('Se connecter', 'orgues-nouvelles'); ?></a>
        <?php
    else:
        ?>
        <?php _e("Vous n'êtes pas abonné à ce magazine.", 'orgues-nouvelles'); ?>
        <a href="/product-category/abonnement/"><?php _e('Abonnez-vous', 'orgues-nouvelles'); ?></a>
        <?php _e('ou saisissez', 'orgues-nouvelles'); ?>
        <a href="/mon-compte/mes-magazines/"><?php _e("le code de l'espace privé", 'orgues-nouvelles'); ?></a>.
        <?php
    endif;
    ?> 
    </div>
    <?php
    $message = ob_get_clean();
    return $message;   
}

// determine whether element should be rendered or not
add_filter('elementor/frontend/section/should_render', 'on_retrict_magazines_elementor_should_render_element', 10, 2);
add_filter('elementor/frontend/widget/should_render', 'on_retrict_magazines_elementor_should_render_element', 10, 2);
add_filter('elementor/frontend/repeater/should_render', 'on_retrict_magazines_elementor_should_render_element', 10, 2);

// determine whether to replace widget's content with "Content Restricted" alert message or not
add_filter('elementor/widget/render_content', 'on_retrict_magazines_elementor_maybe_render_content_restricted_message_instead', 10, 2);

add_filter('pods_field_pick_data_ajax_items', 'custom_pods_labels_in_pick_field_ajax', 1, 6);
add_filter('pods_field_pick_data', 'custom_pods_labels_in_pick_field_data', 1, 6);
function custom_pods_labels_in_pick_field_ajax($items, $name, $value, $options, $pod, $id)
{
    if ('magazines' === $name || 'magazine' === $name) {
        foreach ($items as $key => &$data) {
            if (isset($data['id'])) {
                $data['text'] = custom_pods_select_field_label($data['id']);
                $data['name'] = $data['text'];
            }
        }
    }
    return $items;
}
function custom_pods_labels_in_pick_field_data($items, $name, $value, $options, $pod, $id)
{
    // pods_meta_ prefix for Pods backend, pods_field_ prefix for front-facing Pods form
    if ('magazines' === $name || 'magazine' === $name) {
        if (!empty($items) && is_array($items)) {
            foreach ($items as $key => $item) {
                if (isset($item['id'])) {
                    $data['text'] = custom_pods_select_field_label($data['id']);
                    $data['name'] = $data['text'];
                } elseif (is_numeric($key) && !is_array($item)) {
                    $items[$key] = custom_pods_select_field_label($key);
                }
            }
        }
    }

    return $items;
}
function custom_pods_select_field_label($id)
{
    $pod = pods('magazine', $id);
    $numero = $pod->field('numero');
    $date = $pod->field('date');
    $date = date_i18n('F Y', strtotime($date));
    return 'Orgues Nouvelles n°' . $numero . ' - ' . $date;
}