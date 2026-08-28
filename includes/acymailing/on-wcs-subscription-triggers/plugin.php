<?php
/**
 * Intégration AcyMailing : déclencheurs de changement de statut WooCommerce Subscriptions.
 *
 * Ce plugin AcyMailing expose un déclencheur "wcs_subscription_status_change"
 * utilisable dans les Automations et les Scénarios AcyMailing.
 */

if (!defined('ABSPATH')) {
    exit;
}

use AcyMailing\Classes\AutomationClass;
use AcyMailing\Classes\UserClass;
use AcyMailing\Core\AcymPlugin;
use AcyMailing\Helpers\ScenarioHelper;

class plgAcymOnwcssubscriptions extends AcymPlugin
{
    const TRIGGER_KEY = 'wcs_subscription_status_change';

    public function __construct()
    {
        parent::__construct();

        $this->cms       = 'WordPress';
        $this->installed = acym_isExtensionActive('woocommerce-subscriptions/woocommerce-subscriptions.php');

        $this->pluginDescription->name        = 'WooCommerce Subscriptions Status';
        $this->pluginDescription->category    = 'E-commerce';
        $this->pluginDescription->description = 'Déclenche des automations/scénarios lors des changements de statut d\'abonnement WooCommerce.';
    }

    // -----------------------------------------------------------------------
    // Statuts WooCommerce Subscriptions
    // -----------------------------------------------------------------------

    private function on_get_subscription_statuses(bool $addAny = false): array
    {
        $statuses = [];

        if ($addAny) {
            $statuses['0'] = acym_translation('ACYM_ANY');
        }

        if (function_exists('wcs_get_subscription_statuses')) {
            foreach (wcs_get_subscription_statuses() as $key => $label) {
                // Les clés WCS sont déjà préfixées "wc-" ; on les conserve telles quelles.
                $statuses[$key] = $label;
            }
        } else {
            // Fallback minimal si WCS n'est pas encore chargé.
            $statuses['wc-active']         = __('Active', 'woocommerce-subscriptions');
            $statuses['wc-on-hold']        = __('On hold', 'woocommerce-subscriptions');
            $statuses['wc-cancelled']      = __('Cancelled', 'woocommerce-subscriptions');
            $statuses['wc-expired']        = __('Expired', 'woocommerce-subscriptions');
            $statuses['wc-pending-cancel'] = __('Pending Cancellation', 'woocommerce-subscriptions');
            $statuses['wc-pending']        = __('Pending', 'woocommerce-subscriptions');
        }

        return $statuses;
    }

    // -----------------------------------------------------------------------
    // Déclaration des déclencheurs (Automations & Scénarios)
    // -----------------------------------------------------------------------

    public function onAcymDeclareTriggers(&$triggers, &$defaultValues): void
    {
        $statuses = $this->on_get_subscription_statuses(true);

        $from = empty($defaultValues[self::TRIGGER_KEY]['from']) ? '0' : $defaultValues[self::TRIGGER_KEY]['from'];
        $to   = empty($defaultValues[self::TRIGGER_KEY]['to'])   ? 'wc-expired' : $defaultValues[self::TRIGGER_KEY]['to'];

        $triggers['user'][self::TRIGGER_KEY]         = new stdClass();
        $triggers['user'][self::TRIGGER_KEY]->name   = __('Changement de statut d\'abonnement WooCommerce', 'orgues-nouvelles');
        $triggers['user'][self::TRIGGER_KEY]->option  = '<div class="grid-x grid-margin-x" style="height: 40px;">';
        $triggers['user'][self::TRIGGER_KEY]->option .= '<div class="cell medium-shrink acym_vcenter">' . acym_translation('ACYM_FROM') . '</div>';
        $triggers['user'][self::TRIGGER_KEY]->option .= '<div class="cell medium-4">' . acym_select(
            $statuses,
            '[triggers][user][' . self::TRIGGER_KEY . '][from]',
            $from,
            ['data-class' => 'acym__select']
        ) . '</div>';
        $triggers['user'][self::TRIGGER_KEY]->option .= '<div class="cell medium-shrink acym_vcenter">' . acym_translation('ACYM_TO') . '</div>';
        $triggers['user'][self::TRIGGER_KEY]->option .= '<div class="cell medium-4">' . acym_select(
            $statuses,
            '[triggers][user][' . self::TRIGGER_KEY . '][to]',
            $to,
            ['data-class' => 'acym__select']
        ) . '</div>';
        $triggers['user'][self::TRIGGER_KEY]->option .= '</div>';
    }

    public function onAcymDeclareTriggersScenario(&$triggers, &$defaultValues): void
    {
        $this->onAcymDeclareTriggers($triggers, $defaultValues);
    }

    // -----------------------------------------------------------------------
    // Exécution du déclencheur (correspondance lors du traitement AcyMailing)
    // -----------------------------------------------------------------------

    public function onAcymExecuteTrigger(&$step, &$execute, &$data): void
    {
        $data     = is_array($data) ? $data : [];
        $triggers = $step->triggers;

        if (empty($triggers[self::TRIGGER_KEY]) || !is_array($triggers[self::TRIGGER_KEY])) {
            return;
        }

        $fromStatus = 'wc-' . (isset($data['statusFrom']) ? $data['statusFrom'] : '');
        $toStatus   = 'wc-' . (isset($data['statusTo'])   ? $data['statusTo']   : '');

        $configFrom = $triggers[self::TRIGGER_KEY]['from'] ?? '0';
        $configTo   = $triggers[self::TRIGGER_KEY]['to']   ?? '0';

        $fromMatch = ($configFrom === '0' || $fromStatus === $configFrom);
        $toMatch   = ($configTo   === '0' || $toStatus   === $configTo);

        if ($fromMatch && $toMatch) {
            $execute = true;
        }
    }

    // -----------------------------------------------------------------------
    // Résumé affiché dans l'interface AcyMailing
    // -----------------------------------------------------------------------

    public function onAcymDeclareSummary_triggers(object $automation): void
    {
        if (empty($automation->triggers[self::TRIGGER_KEY]['from'])) {
            return;
        }

        $statuses = $this->on_get_subscription_statuses(true);

        $fromLabel = $statuses[$automation->triggers[self::TRIGGER_KEY]['from']] ?? $automation->triggers[self::TRIGGER_KEY]['from'];
        $toLabel   = $statuses[$automation->triggers[self::TRIGGER_KEY]['to']]   ?? $automation->triggers[self::TRIGGER_KEY]['to'];

        $automation->triggers[self::TRIGGER_KEY] = sprintf(
            __('Abonnement passe de « %s » à « %s »', 'orgues-nouvelles'),
            $fromLabel,
            $toLabel
        );
    }

    // -----------------------------------------------------------------------
    // Enregistrement des hooks WordPress
    // -----------------------------------------------------------------------

    public function onAcymInitWordpressAddons(): void
    {
        if (!$this->installed) {
            return;
        }

        // woocommerce_subscription_status_changed( $subscription_id, $old_status, $new_status, $subscription )
        add_action(
            'woocommerce_subscription_status_changed',
            [$this, 'on_subscription_status_changed'],
            20,
            4
        );
    }

    // -----------------------------------------------------------------------
    // Callback : changement de statut d'abonnement
    // -----------------------------------------------------------------------

    public function on_subscription_status_changed(int $subscription_id, string $old_status, string $new_status, $subscription): void
    {
        $userClass = new UserClass();
        $acyUser   = null;

        $wpUserId = method_exists($subscription, 'get_user_id') ? $subscription->get_user_id() : 0;

        if (!empty($wpUserId)) {
            $acyUser = $userClass->getOneByCMSId($wpUserId);
        }

        if (empty($acyUser)) {
            $billingEmail = method_exists($subscription, 'get_billing_email') ? $subscription->get_billing_email() : '';
            if (!empty($billingEmail)) {
                $acyUser = $userClass->getOneByEmail($billingEmail);
            }
        }

        if (empty($acyUser)) {
            return;
        }

        $triggerData = [
            'userId'     => $acyUser->id,
            'statusFrom' => $old_status,
            'statusTo'   => $new_status,
        ];

        try {
            $automationClass = new AutomationClass();
            $automationClass->trigger(self::TRIGGER_KEY, $triggerData);
        } catch (\Throwable $e) {
            acym_logError('on_subscription_status_changed (automation) : ' . $e->getMessage());
        }

        try {
            $scenarioHelper = new ScenarioHelper();
            $scenarioHelper->trigger(self::TRIGGER_KEY, $triggerData);
        } catch (\Throwable $e) {
            acym_logError('on_subscription_status_changed (scenario) : ' . $e->getMessage());
        }
    }
}
