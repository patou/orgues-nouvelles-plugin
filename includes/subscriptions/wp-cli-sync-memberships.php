<?php
/**
 * Commande WP-CLI pour synchroniser les abonnements depuis un export CSV.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_CLI')) {
    class WP_CLI
    {
        public static function log($message)
        {
        }

        public static function warning($message)
        {
        }

        public static function error($message)
        {
        }

        public static function success($message)
        {
        }

        public static function add_command($name, $callable)
        {
        }
    }
}

if (!function_exists('on_get_cli_flag_value')) {
    function on_get_cli_flag_value(array $assoc_args, $key, $default = '')
    {
        return array_key_exists($key, $assoc_args) ? $assoc_args[$key] : $default;
    }
}

if (!function_exists('on_get_subscription_sync_default_csv_path')) {
    function on_get_subscription_sync_default_csv_path()
    {
        $matches = glob(trailingslashit(ABSPATH) . 'orgues_nouvelles_user_memberships_*.csv');
        if (empty($matches)) {
            return '';
        }

        usort($matches, function ($left, $right) {
            return filemtime($right) <=> filemtime($left);
        });

        return (string) $matches[0];
    }
}

if (!function_exists('on_get_subscription_sync_csv_path')) {
    function on_get_subscription_sync_csv_path($provided_path)
    {
        $provided_path = is_scalar($provided_path) ? trim((string) $provided_path) : '';
        if ('' !== $provided_path) {
            return $provided_path;
        }

        return on_get_subscription_sync_default_csv_path();
    }
}

if (!function_exists('on_make_subscription_sync_csv_row')) {
    function on_make_subscription_sync_csv_row(array $headers, array $row)
    {
        $headers_count = count($headers);
        $row = array_slice(array_pad($row, $headers_count, ''), 0, $headers_count);

        return array_combine($headers, $row);
    }
}

if (!function_exists('on_format_subscription_sync_issue_number')) {
    function on_format_subscription_sync_issue_number($number)
    {
        return 'ON-' . max(0, (int) $number);
    }
}

if (!function_exists('on_sync_subscription_membership_row')) {
    function on_sync_subscription_membership_row(array $row, $data_index, $csv_line_number)
    {
        $subscription_id = isset($row['subscription_id']) ? absint($row['subscription_id']) : 0;
        $member_first_name = isset($row['member_first_name']) ? sanitize_text_field((string) $row['member_first_name']) : '';
        $member_last_name = isset($row['member_last_name']) ? sanitize_text_field((string) $row['member_last_name']) : '';
        $plan_slug = isset($row['membership_plan_slug']) ? strtoupper(sanitize_text_field((string) $row['membership_plan_slug'])) : '';
        $numero_start = isset($row['numero_since']) ? max(0, (int) $row['numero_since']) : 0;
        $numero_end_raw = isset($row['numero_end']) ? trim((string) $row['numero_end']) : '';
        $numero_end = '' === $numero_end_raw ? 99 : max(0, (int) $numero_end_raw);

        $result = array(
            'ok' => false,
            'subscription_id' => $subscription_id,
            'member_name' => trim($member_first_name . ' ' . $member_last_name),
            'plan_slug' => $plan_slug,
            'numero_start' => $numero_start,
            'numero_end' => $numero_end,
            'message' => '',
        );

        try {
            if (0 === $subscription_id) {
                throw new RuntimeException('ID de souscription manquant.');
            }

            if (!function_exists('wcs_get_subscription')) {
                throw new RuntimeException('WooCommerce Subscriptions n\'est pas disponible.');
            }

            $subscription = wcs_get_subscription($subscription_id);
            if (!$subscription instanceof WC_Subscription) {
                throw new RuntimeException(sprintf('Souscription introuvable (%d).', $subscription_id));
            }

            $subscription->update_meta_data('on_formule', $plan_slug);
            $subscription->update_meta_data('number-start', $numero_start);
            $subscription->update_meta_data('number-end', $numero_end);
            $subscription->save();

            $result['ok'] = true;
            $result['message'] = sprintf(
                '%s | %d | %s | %s | %s -> %s',
                $data_index,
                $subscription_id,
                $result['member_name'],
                $plan_slug,
                on_format_subscription_sync_issue_number($numero_start),
                on_format_subscription_sync_issue_number($numero_end)
            );
        } catch (Throwable $throwable) {
            $result['message'] = $throwable->getMessage();
        }

        $status_mark = $result['ok'] ? '✓' : '✗';
        $line = sprintf(
            '%s %s | %d | %s | %s | %s -> %s',
            $status_mark,
            $data_index,
            $subscription_id,
            $result['member_name'],
            $plan_slug,
            on_format_subscription_sync_issue_number($numero_start),
            on_format_subscription_sync_issue_number($numero_end)
        );

        if (!$result['ok'] && '' !== $result['message']) {
            $line .= ' | ' . $result['message'];
        }

        \WP_CLI::log($line);

        return $result;
    }
}

if (!function_exists('on_sync_subscription_memberships_command')) {
    function on_sync_subscription_memberships_command($args, $assoc_args)
    {
        $csv_path = on_get_subscription_sync_csv_path(on_get_cli_flag_value($assoc_args, 'file', ''));
        if ('' === $csv_path || !file_exists($csv_path)) {
            \WP_CLI::error('Impossible de trouver le fichier CSV. Utilisez --file=/chemin/vers/export.csv.');
        }

        $start_index = max(1, absint(on_get_cli_flag_value($assoc_args, 'start-index', 1)));

        $handle = fopen($csv_path, 'rb');
        if (false === $handle) {
            \WP_CLI::error(sprintf('Impossible d\'ouvrir le fichier CSV : %s', $csv_path));
        }

        $headers = array();
        $csv_line_number = 0;
        $data_index = 0;
        $processed = 0;
        $updated = 0;
        $failed = 0;

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $csv_line_number++;

            if (1 === $csv_line_number) {
                $headers = array_map('trim', $row);
                if (isset($headers[0])) {
                    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
                }
                continue;
            }

            $data_index++;

            if ($data_index < $start_index) {
                continue;
            }

            if (empty($headers)) {
                $failed++;
                \WP_CLI::warning(sprintf('✗ %d | En-têtes CSV introuvables.', $data_index));
                continue;
            }

            $assoc_row = on_make_subscription_sync_csv_row($headers, $row);
            if (!is_array($assoc_row)) {
                $failed++;
                \WP_CLI::warning(sprintf('✗ %d | Ligne CSV invalide.', $data_index));
                continue;
            }

            $processed++;
            $result = on_sync_subscription_membership_row($assoc_row, $data_index, $csv_line_number);
            if ($result['ok']) {
                $updated++;
            } else {
                $failed++;
            }
        }

        fclose($handle);

        \WP_CLI::success(sprintf(
            'Synchronisation terminée. Lignes traitées: %d, mises à jour: %d, erreurs: %d.',
            $processed,
            $updated,
            $failed
        ));
    }
}

if (defined('WP_CLI') && WP_CLI) {
    add_action('cli_init', function () {
        \WP_CLI::add_command('orgues-nouvelles sync-memberships', 'on_sync_subscription_memberships_command');
    });
}