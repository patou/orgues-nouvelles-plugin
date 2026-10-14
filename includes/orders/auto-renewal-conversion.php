<?php
/**
 * Convertit les nouvelles commandes d'abonnement payees en renouvellements.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('ON_AUTO_RENEWAL_CONVERSION_STATUS_META')) {
    define('ON_AUTO_RENEWAL_CONVERSION_STATUS_META', '_on_auto_renewal_conversion_status');
    define('ON_AUTO_RENEWAL_SUBSCRIPTION_ID_META', '_on_auto_renewal_subscription_id');
    define('ON_AUTO_RENEWAL_CONFIDENCE_META', '_on_auto_renewal_confidence');
    define('ON_AUTO_RENEWAL_PROCESSED_AT_META', '_on_auto_renewal_processed_at');
    define('ON_AUTO_RENEWAL_EXTENSION_APPLIED_META', '_on_auto_renewal_extension_applied');
    define('ON_AUTO_RENEWAL_DUPLICATE_SUBSCRIPTIONS_META', '_on_auto_renewal_duplicate_subscription_ids');
}

if (!function_exists('on_maybe_convert_paid_order_to_subscription_renewal')) {
    /**
     * Detecte les commandes payees pouvant etre rattachees a un abonnement existant.
     *
     * @param int      $order_id ID de la commande.
     * @param string   $from     Ancien statut.
     * @param string   $to       Nouveau statut.
     * @param WC_Order $order    Commande WooCommerce.
     * @return void
     */
    function on_maybe_convert_paid_order_to_subscription_renewal($order_id, $from, $to, $order)
    {
        if (!$order || !is_a($order, 'WC_Order')) {
            $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        }

        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }

        if (!is_callable(array($order, 'is_paid')) || !$order->is_paid()) {
            return;
        }

        $existing_status = (string) $order->get_meta(ON_AUTO_RENEWAL_CONVERSION_STATUS_META, true);
        if (in_array($existing_status, array('converted', 'review', 'ignored', 'error'), true)) {
            return;
        }

        if (!function_exists('wcs_get_subscriptions_for_order') || !class_exists('WCS_Related_Order_Store')) {
            return;
        }

        $renewal_subscriptions = wcs_get_subscriptions_for_order($order, array('order_type' => 'renewal'));
        if (!empty($renewal_subscriptions)) {
            $order->update_meta_data(ON_AUTO_RENEWAL_CONVERSION_STATUS_META, 'ignored');
            $order->add_order_note(__('Conversion en réabonnement ignorée : la commande est déjà un renouvellement WooCommerce Subscriptions.', 'orgues-nouvelles'));
            $order->save();
            return;
        }

        $formule = on_guess_subscription_formule_from_order_items($order);
        if ('' === $formule) {
            return;
        }

        $identity = on_get_order_effective_renewal_identity($order);
        $candidates = on_find_renewal_candidate_subscriptions_for_order($order, $formule, $identity);
        $matches = on_score_order_subscription_renewal_matches($order, $candidates, $formule, $identity);

        if (empty($matches)) {
            return;
        }

        $best_match = reset($matches);
        if (count($matches) === 1 && $best_match['score'] >= (int) apply_filters('on_auto_renewal_conversion_threshold', 95)) {
            try {
                on_convert_order_to_subscription_renewal($order, $best_match['subscription'], $best_match);
            } catch (Exception $exception) {
                on_mark_order_for_renewal_conversion_error($order, $exception->getMessage());
            }
            return;
        }

        on_mark_order_for_renewal_conversion_review($order, $matches);
    }

    add_action('woocommerce_order_status_changed', 'on_maybe_convert_paid_order_to_subscription_renewal', 30, 4);
}

if (!function_exists('on_guess_subscription_formule_from_order_items')) {
    /**
     * Retourne la formule unique portee par une commande d'abonnement.
     *
     * @param WC_Order $order Commande.
     * @return string
     */
    function on_guess_subscription_formule_from_order_items($order)
    {
        if (!$order || !is_a($order, 'WC_Order') || !is_callable(array($order, 'get_items'))) {
            return '';
        }

        $formules = array();

        foreach ($order->get_items() as $item) {
            if (!is_object($item) || !is_callable(array($item, 'get_product'))) {
                continue;
            }

            $product = call_user_func(array($item, 'get_product'));
            if (!$product || !is_a($product, 'WC_Product')) {
                continue;
            }

            if (!$product->is_type(array('subscription', 'variable-subscription', 'subscription_variation'))) {
                return '';
            }

            $formule = on_guess_subscription_formule_from_items_product($product);
            if ('' === $formule) {
                return '';
            }

            $formules[$formule] = true;
        }

        if (count($formules) !== 1) {
            return '';
        }

        return (string) key($formules);
    }
}

if (!function_exists('on_get_order_effective_renewal_identity')) {
    /**
     * Retourne l'identite qui doit etre renouvelee par la commande.
     *
     * @param WC_Order $order Commande.
     * @return array{user_id:int,email:string,is_gift:bool}
     */
    function on_get_order_effective_renewal_identity($order)
    {
        $identity = array(
            'user_id' => 0,
            'email' => '',
            'is_gift' => false,
        );

        if (!$order || !is_a($order, 'WC_Order')) {
            return $identity;
        }

        foreach ($order->get_items() as $item) {
            $recipient_user_id = 0;

            if (class_exists('WCS_Gifting') && is_callable(array('WCS_Gifting', 'get_order_item_recipient_user_id'))) {
                $recipient_user_id = absint(WCS_Gifting::get_order_item_recipient_user_id($item));
            }

            $recipient_email = '';
            if (is_object($item) && is_callable(array($item, 'get_meta'))) {
                $recipient_email = sanitize_email((string) $item->get_meta('wcsg_gift_recipients_email', true));
            }

            if ($recipient_user_id > 0 || '' !== $recipient_email) {
                $identity['is_gift'] = true;
                $identity['user_id'] = $recipient_user_id;
                $identity['email'] = '' !== $recipient_email ? $recipient_email : on_get_user_email($recipient_user_id);
                return $identity;
            }
        }

        $parent_subscriptions = function_exists('wcs_get_subscriptions_for_order') ? wcs_get_subscriptions_for_order($order, array('order_type' => 'parent')) : array();
        foreach ($parent_subscriptions as $subscription) {
            if (!$subscription || !is_a($subscription, 'WC_Subscription')) {
                continue;
            }

            if (function_exists('on_is_gifted_subscription') && on_is_gifted_subscription($subscription)) {
                $identity['is_gift'] = true;
                $identity['user_id'] = function_exists('on_get_subscription_effective_shipping_user_id') ? on_get_subscription_effective_shipping_user_id($subscription) : 0;
                $identity['email'] = on_get_subscription_effective_renewal_email($subscription);
                return $identity;
            }
        }

        $identity['user_id'] = absint($order->get_user_id());
        $identity['email'] = sanitize_email((string) $order->get_billing_email());

        return $identity;
    }
}

if (!function_exists('on_find_renewal_candidate_subscriptions_for_order')) {
    /**
     * Trouve les abonnements existants pouvant etre renouveles par la commande.
     *
     * @param WC_Order $order    Commande.
     * @param string   $formule  Formule ON.
     * @param array    $identity Identite effective.
     * @return WC_Subscription[]
     */
    function on_find_renewal_candidate_subscriptions_for_order($order, $formule, $identity)
    {
        $subscriptions = array();
        $user_ids = array();

        if (!empty($identity['user_id'])) {
            $user_ids[] = absint($identity['user_id']);
        }

        if (empty($identity['is_gift']) && $order && is_callable(array($order, 'get_user_id'))) {
            $user_ids[] = absint($order->get_user_id());
        }

        if (!empty($identity['email']) && function_exists('email_exists')) {
            $email_user_id = absint(email_exists($identity['email']));
            if ($email_user_id > 0) {
                $user_ids[] = $email_user_id;
            }
        }

        $user_ids = array_unique(array_filter($user_ids));

        foreach ($user_ids as $user_id) {
            if (function_exists('wcs_get_users_subscriptions')) {
                $subscriptions = array_replace($subscriptions, wcs_get_users_subscriptions($user_id));
            }

            if (class_exists('WCSG_Recipient_Management') && is_callable(array('WCSG_Recipient_Management', 'get_recipient_subscriptions'))) {
                foreach ((array) WCSG_Recipient_Management::get_recipient_subscriptions($user_id) as $subscription_id) {
                    if (function_exists('wcs_get_subscription')) {
                        $subscription = wcs_get_subscription($subscription_id);
                        if ($subscription && is_a($subscription, 'WC_Subscription')) {
                            $subscriptions[$subscription->get_id()] = $subscription;
                        }
                    }
                }
            }
        }

        $parent_subscription_ids = array_map('absint', array_keys(wcs_get_subscriptions_for_order($order, array('order_type' => 'parent'))));
        $eligible_subscriptions = array();

        foreach ($subscriptions as $subscription) {
            if (!$subscription || !is_a($subscription, 'WC_Subscription')) {
                continue;
            }

            if (in_array(absint($subscription->get_id()), $parent_subscription_ids, true)) {
                continue;
            }

            if (!on_subscription_is_eligible_for_auto_renewal_conversion($subscription, $formule, $order)) {
                continue;
            }

            $eligible_subscriptions[$subscription->get_id()] = $subscription;
        }

        return $eligible_subscriptions;
    }
}

if (!function_exists('on_subscription_is_eligible_for_auto_renewal_conversion')) {
    /**
     * Verifie les criteres non identitaires d'un abonnement candidat.
     *
     * @param WC_Subscription $subscription Abonnement.
     * @param string          $formule      Formule attendue.
     * @param WC_Order        $order        Commande.
     * @return bool
     */
    function on_subscription_is_eligible_for_auto_renewal_conversion($subscription, $formule, $order)
    {
        $statuses = (array) apply_filters('on_auto_renewal_conversion_eligible_statuses', array('active', 'on-hold', 'expired', 'cancelled', 'trash'));
        if (is_callable(array($subscription, 'has_status')) && !$subscription->has_status($statuses)) {
            return false;
        }

        if ($formule !== on_get_subscription_renewal_formule($subscription)) {
            return false;
        }

        if (is_callable(array($subscription, 'has_status')) && $subscription->has_status(array('active', 'on-hold'))) {
            return true;
        }

        return !on_subscription_is_too_old_for_auto_renewal_conversion($subscription, $order);
    }
}

if (!function_exists('on_score_order_subscription_renewal_matches')) {
    /**
     * Score et trie les abonnements candidats.
     *
     * @param WC_Order          $order         Commande.
     * @param WC_Subscription[] $subscriptions Abonnements.
     * @param string            $formule       Formule ON.
     * @param array             $identity      Identite effective de la commande.
     * @return array<int,array{subscription:WC_Subscription,score:int,reasons:array}>
     */
    function on_score_order_subscription_renewal_matches($order, $subscriptions, $formule, $identity)
    {
        $matches = array();

        foreach ($subscriptions as $subscription) {
            $score = on_score_order_subscription_renewal_match($order, $subscription, $formule, $identity);
            if ($score < (int) apply_filters('on_auto_renewal_conversion_review_threshold', 70)) {
                continue;
            }

            $matches[] = array(
                'subscription' => $subscription,
                'score' => $score,
                'reasons' => on_get_order_subscription_renewal_match_reasons($order, $subscription, $identity),
            );
        }

        usort(
            $matches,
            function ($left, $right) {
                return $right['score'] <=> $left['score'];
            }
        );

        if (count($matches) === 1) {
            $matches[0]['score'] = min(100, $matches[0]['score'] + 10);
            $matches[0]['reasons'][] = 'single_candidate';
        }

        return $matches;
    }
}

if (!function_exists('on_score_order_subscription_renewal_match')) {
    /**
     * Calcule le score d'un couple commande / abonnement.
     *
     * @param WC_Order        $order        Commande.
     * @param WC_Subscription $subscription Abonnement.
     * @param string          $formule      Formule ON.
     * @param array           $identity     Identite effective de la commande.
     * @return int
     */
    function on_score_order_subscription_renewal_match($order, $subscription, $formule, $identity)
    {
        $subscription_identity = on_get_subscription_effective_renewal_identity($subscription);

        if (!empty($subscription_identity['is_gift']) && empty($subscription_identity['user_id']) && '' === $subscription_identity['email']) {
            return 0;
        }

        $score = 0;
        if (!empty($identity['user_id']) && !empty($subscription_identity['user_id']) && absint($identity['user_id']) === absint($subscription_identity['user_id'])) {
            $score += 50;
        }

        if ('' !== $identity['email'] && '' !== $subscription_identity['email'] && strtolower($identity['email']) === strtolower($subscription_identity['email'])) {
            $score += 15;
        }

        if ($formule === on_get_subscription_renewal_formule($subscription)) {
            $score += 20;
        }

        if (on_subscription_due_date_is_within_order_window($subscription, $order)) {
            $score += 15;
        }

        return min(100, $score);
    }
}

if (!function_exists('on_convert_order_to_subscription_renewal')) {
    /**
     * Rattache une commande payee comme renouvellement d'un abonnement existant.
     *
     * @param WC_Order        $order        Commande.
     * @param WC_Subscription $subscription Abonnement cible.
     * @param array           $match        Details de matching.
     * @return void
     */
    function on_convert_order_to_subscription_renewal($order, $subscription, $match)
    {
        if (!$order || !is_a($order, 'WC_Order') || !$subscription || !is_a($subscription, 'WC_Subscription')) {
            throw new InvalidArgumentException(__('Commande ou abonnement invalide.', 'orgues-nouvelles'));
        }

        WCS_Related_Order_Store::instance()->add_relation($order, $subscription, 'renewal');

        $related_subscriptions = wcs_get_subscriptions_for_order($order, array('order_type' => 'renewal'));
        if (!isset($related_subscriptions[$subscription->get_id()])) {
            throw new RuntimeException(__('Le rattachement WooCommerce Subscriptions n\'a pas pu être validé.', 'orgues-nouvelles'));
        }

        if ('yes' !== $order->get_meta(ON_AUTO_RENEWAL_EXTENSION_APPLIED_META, true) && function_exists('on_extend_subscription_number_bounds_on_renewal')) {
            on_extend_subscription_number_bounds_on_renewal($subscription);
            on_extend_subscription_next_payment_on_renewal_conversion($subscription, $order);
            $order->update_meta_data(ON_AUTO_RENEWAL_EXTENSION_APPLIED_META, 'yes');
        }

        if (is_callable(array($subscription, 'has_status')) && $subscription->has_status(array('expired', 'cancelled', 'on-hold')) && is_callable(array($subscription, 'update_status'))) {
            $subscription->update_status('active', __('Réactivé automatiquement après commande de réabonnement.', 'orgues-nouvelles'));
        }

        $duplicate_subscription_ids = on_cancel_duplicate_parent_subscriptions_for_order($order, $subscription);

        $order->update_meta_data(ON_AUTO_RENEWAL_CONVERSION_STATUS_META, 'converted');
        $order->update_meta_data(ON_AUTO_RENEWAL_SUBSCRIPTION_ID_META, $subscription->get_id());
        $order->update_meta_data(ON_AUTO_RENEWAL_CONFIDENCE_META, absint($match['score']));
        $order->update_meta_data(ON_AUTO_RENEWAL_PROCESSED_AT_META, current_time('mysql', true));
        if (!empty($duplicate_subscription_ids)) {
            $order->update_meta_data(ON_AUTO_RENEWAL_DUPLICATE_SUBSCRIPTIONS_META, implode(',', array_map('absint', $duplicate_subscription_ids)));
        }

        $order->add_order_note(
            sprintf(
                /* translators: 1: subscription id, 2: score. */
                __('Commande convertie automatiquement en renouvellement de l’abonnement #%1$d (score %2$d).', 'orgues-nouvelles'),
                $subscription->get_id(),
                absint($match['score'])
            )
        );
        $order->save();

        $subscription->add_order_note(
            sprintf(
                /* translators: 1: order id, 2: score. */
                __('Commande #%1$d rattachée automatiquement comme réabonnement (score %2$d).', 'orgues-nouvelles'),
                $order->get_id(),
                absint($match['score'])
            )
        );
        $subscription->save();
    }
}

if (!function_exists('on_extend_subscription_next_payment_on_renewal_conversion')) {
    /**
     * Avance la prochaine échéance après conversion d'une commande en renouvellement.
     *
     * @param WC_Subscription $subscription Abonnement.
     * @param WC_Order        $order        Commande convertie.
     * @return bool
     */
    function on_extend_subscription_next_payment_on_renewal_conversion($subscription, $order)
    {
        if (!$subscription || !is_a($subscription, 'WC_Subscription') || !is_callable(array($subscription, 'update_dates'))) {
            return false;
        }

        if (!function_exists('wcs_add_time')) {
            return false;
        }

        $billing_interval = is_callable(array($subscription, 'get_billing_interval')) ? max(0, (int) $subscription->get_billing_interval()) : 0;
        $billing_period = is_callable(array($subscription, 'get_billing_period')) ? strtolower(sanitize_key((string) $subscription->get_billing_period())) : '';

        if ($billing_interval <= 0 || '' === $billing_period) {
            return false;
        }

        $current_next_payment = is_callable(array($subscription, 'get_time')) ? (int) $subscription->get_time('next_payment') : 0;
        $order_timestamp = on_get_order_renewal_conversion_timestamp($order);
        $base_timestamp = max($current_next_payment, $order_timestamp);

        if ($base_timestamp <= 0) {
            return false;
        }

        $next_payment_timestamp = wcs_add_time($billing_interval, $billing_period, $base_timestamp, 'offset_site_time');
        if ($next_payment_timestamp <= 0) {
            return false;
        }

        $subscription->update_dates(
            array(
                'next_payment' => gmdate('Y-m-d H:i:s', $next_payment_timestamp),
            ),
            'gmt'
        );

        return true;
    }
}

if (!function_exists('on_cancel_duplicate_parent_subscriptions_for_order')) {
    /**
     * Annule les abonnements crees par erreur dans la commande convertie.
     *
     * @param WC_Order        $order               Commande.
     * @param WC_Subscription $target_subscription Abonnement cible.
     * @return int[]
     */
    function on_cancel_duplicate_parent_subscriptions_for_order($order, $target_subscription)
    {
        $cancelled_ids = array();
        $parent_subscriptions = wcs_get_subscriptions_for_order($order, array('order_type' => 'parent'));

        foreach ($parent_subscriptions as $duplicate_subscription) {
            if (!$duplicate_subscription || !is_a($duplicate_subscription, 'WC_Subscription')) {
                continue;
            }

            if ($duplicate_subscription->get_id() === $target_subscription->get_id()) {
                continue;
            }

            if (is_callable(array($duplicate_subscription, 'update_status'))) {
                $duplicate_subscription->update_status('cancelled', __('Abonnement annulé automatiquement : la commande a été rattachée à un abonnement existant.', 'orgues-nouvelles'));
                $cancelled_ids[] = $duplicate_subscription->get_id();
            }
        }

        return $cancelled_ids;
    }
}

if (!function_exists('on_mark_order_for_renewal_conversion_review')) {
    /**
     * Marque une commande ambigue pour revue admin.
     *
     * @param WC_Order $order   Commande.
     * @param array    $matches Candidats scores.
     * @return void
     */
    function on_mark_order_for_renewal_conversion_review($order, $matches)
    {
        $candidate_ids = array();
        foreach ($matches as $match) {
            if (!empty($match['subscription']) && is_a($match['subscription'], 'WC_Subscription')) {
                $candidate_ids[] = $match['subscription']->get_id() . ':' . absint($match['score']);
            }
        }

        $order->update_meta_data(ON_AUTO_RENEWAL_CONVERSION_STATUS_META, 'review');
        $order->update_meta_data(ON_AUTO_RENEWAL_SUBSCRIPTION_ID_META, implode(',', $candidate_ids));
        $order->update_meta_data(ON_AUTO_RENEWAL_PROCESSED_AT_META, current_time('mysql', true));
        $order->add_order_note(__('Réabonnement automatique à vérifier : plusieurs abonnements compatibles ou score insuffisant pour convertir sans revue.', 'orgues-nouvelles'));
        $order->save();
    }
}

if (!function_exists('on_mark_order_for_renewal_conversion_error')) {
    /**
     * Trace une erreur sans casser le changement de statut WooCommerce.
     *
     * @param WC_Order $order   Commande.
     * @param string   $message Message d'erreur.
     * @return void
     */
    function on_mark_order_for_renewal_conversion_error($order, $message)
    {
        $order->update_meta_data(ON_AUTO_RENEWAL_CONVERSION_STATUS_META, 'error');
        $order->update_meta_data(ON_AUTO_RENEWAL_PROCESSED_AT_META, current_time('mysql', true));
        $order->add_order_note(
            sprintf(
                /* translators: %s: error message. */
                __('Erreur pendant la conversion automatique en réabonnement : %s', 'orgues-nouvelles'),
                sanitize_text_field($message)
            )
        );
        $order->save();
    }
}

if (!function_exists('on_get_subscription_renewal_formule')) {
    /**
     * Retourne la formule d'un abonnement.
     *
     * @param WC_Subscription $subscription Abonnement.
     * @return string
     */
    function on_get_subscription_renewal_formule($subscription)
    {
        if (!$subscription || !is_a($subscription, 'WC_Subscription')) {
            return '';
        }

        $formule = function_exists('on_sanitize_subscription_formule') ? on_sanitize_subscription_formule((string) $subscription->get_meta('on_formule', true)) : '';
        if ('' !== $formule) {
            return $formule;
        }

        foreach ($subscription->get_items() as $item) {
            if (!is_object($item) || !is_callable(array($item, 'get_product'))) {
                continue;
            }

            $product = call_user_func(array($item, 'get_product'));
            if ($product && is_a($product, 'WC_Product')) {
                $formule = on_guess_subscription_formule_from_items_product($product);
                if ('' !== $formule) {
                    return $formule;
                }
            }
        }

        return '';
    }
}

if (!function_exists('on_get_subscription_effective_renewal_identity')) {
    /**
     * Retourne l'identite effective d'un abonnement.
     *
     * @param WC_Subscription $subscription Abonnement.
     * @return array{user_id:int,email:string,is_gift:bool}
     */
    function on_get_subscription_effective_renewal_identity($subscription)
    {
        $is_gift = function_exists('on_is_gifted_subscription') ? on_is_gifted_subscription($subscription) : false;
        $user_id = 0;
        $email = '';

        if ($is_gift) {
            $user_id = function_exists('on_get_subscription_effective_shipping_user_id') ? on_get_subscription_effective_shipping_user_id($subscription) : 0;
            $email = on_get_subscription_effective_renewal_email($subscription);
        } else {
            $user_id = is_callable(array($subscription, 'get_customer_id')) ? absint($subscription->get_customer_id()) : 0;
            $email = is_callable(array($subscription, 'get_billing_email')) ? sanitize_email((string) $subscription->get_billing_email()) : on_get_user_email($user_id);
        }

        return array(
            'user_id' => $user_id,
            'email' => $email,
            'is_gift' => (bool) $is_gift,
        );
    }
}

if (!function_exists('on_get_subscription_effective_renewal_email')) {
    /**
     * Retourne l'email du beneficiaire effectif d'un abonnement.
     *
     * @param WC_Subscription $subscription Abonnement.
     * @return string
     */
    function on_get_subscription_effective_renewal_email($subscription)
    {
        if (!$subscription || !is_a($subscription, 'WC_Subscription')) {
            return '';
        }

        $recipient_email = sanitize_email((string) $subscription->get_meta('_recipient_user_email_address', true));
        if ('' !== $recipient_email) {
            return $recipient_email;
        }

        $user_id = function_exists('on_get_subscription_effective_shipping_user_id') ? on_get_subscription_effective_shipping_user_id($subscription) : 0;

        return on_get_user_email($user_id);
    }
}

if (!function_exists('on_subscription_due_date_is_within_order_window')) {
    /**
     * Verifie que l'echeance est proche de la commande.
     *
     * @param WC_Subscription $subscription Abonnement.
     * @param WC_Order        $order        Commande.
     * @return bool
     */
    function on_subscription_due_date_is_within_order_window($subscription, $order)
    {
        $due_timestamp = on_get_subscription_renewal_due_timestamp($subscription);
        if ($due_timestamp <= 0) {
            return false;
        }

        $order_timestamp = time();
        if ($order && is_callable(array($order, 'get_date_paid')) && $order->get_date_paid()) {
            $order_timestamp = $order->get_date_paid()->getTimestamp();
        } elseif ($order && is_callable(array($order, 'get_date_created')) && $order->get_date_created()) {
            $order_timestamp = $order->get_date_created()->getTimestamp();
        }

        $window_days = (int) apply_filters('on_auto_renewal_conversion_window_days', 60);

        return abs($order_timestamp - $due_timestamp) <= $window_days * DAY_IN_SECONDS;
    }
}

if (!function_exists('on_subscription_is_too_old_for_auto_renewal_conversion')) {
    /**
     * Vérifie si un ancien abonnement est trop vieux pour absorber une nouvelle commande.
     *
     * @param WC_Subscription $subscription Abonnement.
     * @param WC_Order        $order        Commande.
     * @return bool
     */
    function on_subscription_is_too_old_for_auto_renewal_conversion($subscription, $order)
    {
        $reference_timestamp = on_get_subscription_previous_activity_timestamp($subscription);
        if ($reference_timestamp <= 0) {
            return true;
        }

        $order_timestamp = on_get_order_renewal_conversion_timestamp($order);
        $max_age_months = max(0, (int) apply_filters('on_auto_renewal_conversion_inactive_max_age_months', 5));
        if ($max_age_months <= 0) {
            return false;
        }

        $cutoff_timestamp = strtotime('-' . $max_age_months . ' months', $order_timestamp);
        if (!$cutoff_timestamp) {
            return true;
        }

        return $reference_timestamp < $cutoff_timestamp;
    }
}

if (!function_exists('on_get_order_renewal_conversion_timestamp')) {
    /**
     * Retourne la date de référence de la commande.
     *
     * @param WC_Order $order Commande.
     * @return int
     */
    function on_get_order_renewal_conversion_timestamp($order)
    {
        if ($order && is_callable(array($order, 'get_date_paid')) && $order->get_date_paid()) {
            return (int) $order->get_date_paid()->getTimestamp();
        }

        if ($order && is_callable(array($order, 'get_date_created')) && $order->get_date_created()) {
            return (int) $order->get_date_created()->getTimestamp();
        }

        return time();
    }
}

if (!function_exists('on_get_subscription_previous_activity_timestamp')) {
    /**
     * Retourne la dernière date métier connue d'un abonnement inactif.
     *
     * @param WC_Subscription $subscription Abonnement.
     * @return int
     */
    function on_get_subscription_previous_activity_timestamp($subscription)
    {
        foreach (array('end', 'cancelled', 'next_payment', 'last_order_date_created', 'start') as $date_type) {
            if (is_callable(array($subscription, 'get_time'))) {
                $timestamp = (int) $subscription->get_time($date_type);
                if ($timestamp > 0) {
                    return $timestamp;
                }
            }

            if (is_callable(array($subscription, 'get_date'))) {
                $date = (string) $subscription->get_date($date_type);
                if ('' !== $date) {
                    $timestamp = strtotime($date);
                    if ($timestamp > 0) {
                        return $timestamp;
                    }
                }
            }
        }

        return 0;
    }
}

if (!function_exists('on_get_subscription_renewal_due_timestamp')) {
    /**
     * Retourne la meilleure date d'echeance disponible.
     *
     * @param WC_Subscription $subscription Abonnement.
     * @return int
     */
    function on_get_subscription_renewal_due_timestamp($subscription)
    {
        foreach (array('next_payment', 'end', 'start') as $date_type) {
            if (is_callable(array($subscription, 'get_time'))) {
                $timestamp = (int) $subscription->get_time($date_type);
                if ($timestamp > 0) {
                    return $timestamp;
                }
            }

            if (is_callable(array($subscription, 'get_date'))) {
                $date = (string) $subscription->get_date($date_type);
                if ('' !== $date) {
                    $timestamp = strtotime($date);
                    if ($timestamp > 0) {
                        return $timestamp;
                    }
                }
            }
        }

        return 0;
    }
}

if (!function_exists('on_get_order_subscription_renewal_match_reasons')) {
    /**
     * Retourne les raisons de matching pour diagnostic.
     *
     * @param WC_Order        $order        Commande.
     * @param WC_Subscription $subscription Abonnement.
     * @param array           $identity     Identite effective.
     * @return array
     */
    function on_get_order_subscription_renewal_match_reasons($order, $subscription, $identity)
    {
        $subscription_identity = on_get_subscription_effective_renewal_identity($subscription);
        $reasons = array();

        if (!empty($identity['user_id']) && !empty($subscription_identity['user_id']) && absint($identity['user_id']) === absint($subscription_identity['user_id'])) {
            $reasons[] = 'same_effective_user';
        }

        if ('' !== $identity['email'] && '' !== $subscription_identity['email'] && strtolower($identity['email']) === strtolower($subscription_identity['email'])) {
            $reasons[] = 'same_effective_email';
        }

        if (on_subscription_due_date_is_within_order_window($subscription, $order)) {
            $reasons[] = 'due_date_in_window';
        }

        return $reasons;
    }
}

if (!function_exists('on_get_user_email')) {
    /**
     * Retourne l'email d'un utilisateur.
     *
     * @param int $user_id ID utilisateur.
     * @return string
     */
    function on_get_user_email($user_id)
    {
        $user_id = absint($user_id);
        if ($user_id <= 0 || !function_exists('get_userdata')) {
            return '';
        }

        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email)) {
            return '';
        }

        return sanitize_email((string) $user->user_email);
    }
}